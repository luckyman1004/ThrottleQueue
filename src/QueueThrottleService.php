<?php

namespace Drupal\queue_throttle;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Psr\Log\LoggerInterface;

/**
 * Class QueueThrottleService.
 *
 * @package Drupal\queue_throttle
 */
class QueueThrottleService implements QueueThrottleServiceInterface {

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a cron object.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(QueueWorkerManagerInterface $queue_manager, QueueFactory $queue_factory, LoggerInterface $logger) {
    $this->queueManager = $queue_manager;
    $this->queueFactory = $queue_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function runQueue($queue_name, $time_limit, $tokens, $unit = 'second', $detailed_logging = FALSE) {

    // Get initial time pointers.
    $start = $current = $end = microtime(TRUE);
    $end += $time_limit;

    // Get queue & worker.
    $queue = $this->queueFactory->get($queue_name);
    $queue_worker = $this->queueManager->createInstance($queue_name);

    $processedTotal = 0;
    $processedTokens = 0;

    while ((!$time_limit || microtime(TRUE) < $end) && ($item = $queue->claimItem())) {

      // Reset $current & $processed if unit differs.
      if (microtime(TRUE) >= $current + ThrottleRate::getUnitInSeconds($unit)) {
        $current = microtime(TRUE);
        $processedTokens = 0;
      }

      // Check if limit per unit is reached.
      if ($processedTokens >= $tokens) {
        $queue->releaseItem($item);
        continue;
      }

      try {
        if ($detailed_logging) {
          $this->logger->info(dt('Processing item @id from @name queue.', ['@name' => $queue_name, '@id' => $item->item_id]));
        }
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $processedTokens++;
        $processedTotal++;
      }
      catch (RequeueException $e) {
        // The worker requested the task to be immediately re-queued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item.
        $queue->releaseItem($item);
        watchdog_exception('queue_throttle', $e);
        break;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('queue_throttle', $e);
      }
    }

    $elapsed = microtime(TRUE) - $start;

    $this->logger->info(dt('Processed @count items from the @name queue in @elapsed sec.', [
      '@count' => $processedTotal,
      '@name' => $queue_name,
      '@elapsed' => round($elapsed, 2),
    ]));
  }

}
