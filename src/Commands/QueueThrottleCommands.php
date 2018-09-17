<?php

namespace Drupal\queue_throttle\Commands;

use Drupal\Core\CronInterface;
use Drupal\queue_throttle\QueueThrottleServiceInterface;
use Drush\Commands\DrushCommands;

/**
 * Class QueueThrottleCommands.
 *
 * @package Drupal\queue_throttle\Commands
 */
class QueueThrottleCommands extends DrushCommands {

  /**
   * The schedule runner service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $queueThrottleCron;

  /**
   * The queue throttle service.
   *
   * @var \Drupal\queue_throttle\QueueThrottleServiceInterface
   */
  protected $queueThrottleService;

  /**
   * Constructs a cron object.
   *
   * @param \Drupal\Core\CronInterface $queue_throttle_cron
   *   The cron service.
   * @param \Drupal\queue_throttle\QueueThrottleServiceInterface $queue_throttle_service
   *   The cron service.
   */
  public function __construct(CronInterface $queue_throttle_cron, QueueThrottleServiceInterface $queue_throttle_service) {
    parent::__construct();
    $this->queueThrottleCron = $queue_throttle_cron;
    $this->queueThrottleService = $queue_throttle_service;
  }

  /**
   * Runs all queues marked for throttling through cron.
   *
   * @command queue-throttle:cron
   * @aliases queue-throttle,queue-throttle-cron
   */
  public function cron() {
    if ($this->queueThrottleCron->run() !== TRUE) {
      throw new \Exception(dt('Queue throttle cron run failed.'));
    }
  }

  /**
   * Throttle a specific queue by name.
   *
   * @param string $name
   *   The name of the queue to run, as defined in either hook_queue_info or
   *   hook_cron_queue_info.
   * @param array $options
   *   Options passed to the run command.
   *
   * @throws \Exception
   *   If the queue is suspended.
   *
   * @validate-queue name
   * @option time-limit The maximum number of seconds allowed to run the queue
   * @option items The maximum number of items allowed per unit
   * @option unit The time unit
   * @command queue-throttle:run
   * @aliases queue-throttle-run
   */
  public function run($name, array $options = [
    'time-limit' => self::REQ,
    'items' => self::REQ,
    'unit' => self::OPT,
  ]) {

    $items = (int) $options['items'];
    $unit = $options['unit'] ?: 'second';
    $time_limit = (int) $options['time-limit'];

    $this->queueThrottleService->runQueue($name, $time_limit, $items, $unit);
  }

}
