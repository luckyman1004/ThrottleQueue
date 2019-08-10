<?php

namespace Drupal\queue_throttle;

use Drupal\Core\CronInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Environment;
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
   * The queue throttle service.
   *
   * @var \Drupal\queue_throttle\QueueThrottleServiceInterface
   */
  protected $queueThrottleService;

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
   * @param \Drupal\queue_throttle\QueueThrottleServiceInterface $queue_throttle_service
   *   The cron service.
   */
  public function __construct(LockBackendInterface $lock, QueueFactory $queue_factory, StateInterface $state, AccountSwitcherInterface $account_switcher, LoggerInterface $logger, QueueWorkerManagerInterface $queue_manager, ConfigFactoryInterface $config_factory, TimeInterface $time = NULL, QueueThrottleServiceInterface $queue_throttle_service) {
    $this->lock = $lock;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
    $this->accountSwitcher = $account_switcher;
    $this->logger = $logger;
    $this->queueManager = $queue_manager;
    $this->configFactory = $config_factory;
    $this->time = $time ?: \Drupal::service('datetime.time');
    $this->queueThrottleService = $queue_throttle_service;
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
    Environment::setTimeLimit(240);

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
        // Throttle queue.
        $this->queueThrottleService->runQueue(
          $queue_name,
          $config->get($queue_name . '.time'),
          $config->get($queue_name . '.items'),
          $config->get($queue_name . '.unit')
        );
      }
    }
  }

}
