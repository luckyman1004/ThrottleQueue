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
  public function runQueue($queue_name, $time_limit, $items, $unit = 'second') {

    $start = microtime(TRUE);

    $queue = $this->queueFactory->get($queue_name);
    $queue_worker = $this->queueManager->createInstance($queue_name);

    // Get throttle rate.
    $throttleRate = new ThrottleRate($items, $unit);
    $tokensPerSecond = $throttleRate->getTokensPerSecond();

    $processed = 0;
    $current = time();
    $end = time() + $time_limit;

    while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {

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
        $this->logger->info(dt('Processing item @id from @name queue.', ['@name' => $queue_name, '@id' => $item->item_id]));
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (RequeueException $e) {
        // The worker requested the task to be immediately re-queued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item.
        $queue->releaseItem($item);
        throw new \Exception($e->getMessage());
      }
    }

    $elapsed = microtime(TRUE) - $start;

    $this->logger->info(dt('Processed @count items from the @name queue in @elapsed sec.', [
      '@count' => $processed,
      '@name' => $queue_name,
      '@elapsed' => round($elapsed, 2),
    ]));
  }

}
