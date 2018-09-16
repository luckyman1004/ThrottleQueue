<?php

namespace Drupal\queue_throttle;

use Drupal\Core\CronInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Psr\Log\LoggerInterface;

/**
 * Class QueueThrottleCron.
 *
 * @package Drupal\queue_throttle
 */
class QueueThrottleCron implements CronInterface {

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a cron object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switching service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(LockBackendInterface $lock, QueueFactory $queue_factory, StateInterface $state, AccountSwitcherInterface $account_switcher, LoggerInterface $logger, QueueWorkerManagerInterface $queue_manager, ConfigFactoryInterface $config_factory, TimeInterface $time = NULL) {
    $this->lock = $lock;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
    $this->accountSwitcher = $account_switcher;
    $this->logger = $logger;
    $this->queueManager = $queue_manager;
    $this->configFactory = $config_factory;
    $this->time = $time ?: \Drupal::service('datetime.time');
  }

  /**
   * {@inheritdoc}
   */
  protected function setCronLastTime() {
    // Record cron time.
    $request_time = $this->time->getRequestTime();
    $this->state->set('queue_throttle.cron_last', $request_time);
    $this->logger->notice('Queue throttle cron run completed.');
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Force the current user to anonymous to ensure consistent permissions on
    // cron runs.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    // Try to allocate enough time to run all the hook_cron implementations.
    drupal_set_time_limit(240);

    $return = FALSE;

    // Try to acquire cron lock.
    if (!$this->lock->acquire('queue_throttle_cron', 900.0)) {
      // Cron is still running normally.
      $this->logger->warning('Attempting to re-run queue throttle cron while it is already running.');
    }
    else {
      // Process queues throttled.
      $this->processQueues();

      // Set last cron time.
      $this->setCronLastTime();

      // Release cron lock.
      $this->lock->release('queue_throttle_cron');

      // Return TRUE so other functions can check if it did run successfully.
      $return = TRUE;
    }

    // Restore the user.
    $this->accountSwitcher->switchBack();

    return $return;
  }

  /**
   * Processes cron queues.
   */
  protected function processQueues() {

    $config = $this->configFactory->get('queue_throttle.settings');

    // Grab the defined cron queues.
    foreach (array_keys($this->queueManager->getDefinitions()) as $queue_name) {
      if ($config->get($queue_name . '.enabled')) {
        // Make sure every queue exists. There is no harm in trying to recreate
        // an existing queue.
        $this->queueFactory->get($queue_name)->createQueue();

        $queue_worker = $this->queueManager->createInstance($queue_name);

        // Get throttle rate.
        $throttleRate = new ThrottleRate($config->get($queue_name . '.items'), $config->get($queue_name . '.unit'));
        $tokensPerSecond = $throttleRate->getTokensPerSecond();

        $processed = 0;
        $current = time();
        $end = $current + ($config->get($queue_name . '.time') ?: 15);
        $queue = $this->queueFactory->get($queue_name);
        $lease_time = $config->get($queue_name . '.time') ?: NULL;

        while (time() < $end && ($item = $queue->claimItem($lease_time))) {

          // Reset $current & $processed if unit differs.
          if (time() !== $current) {
            $current = time();
            $processed = 0;
          }
          // Check if limit per unit is reached.
          if ($processed >= $tokensPerSecond) {
            $queue->releaseItem($item);
            continue;
          }

          try {
            $queue_worker->processItem($item->data);
            $queue->deleteItem($item);
            $processed++;
          }
          catch (RequeueException $e) {
            // The worker requested the task be immediately re-queued.
            $queue->releaseItem($item);
          }
          catch (SuspendQueueException $e) {
            // If the worker indicates there is a problem with the whole queue,
            // release the item and skip to the next queue.
            $queue->releaseItem($item);

            watchdog_exception('queue_throttle_cron', $e);

            // Skip to the next queue.
            continue 2;
          }
          catch (\Exception $e) {
            // In case of any other kind of exception, log it and leave the item
            // in the queue to be processed again later.
            watchdog_exception('queue_throttle_cron', $e);
          }
        }
      }
    }
  }

}
