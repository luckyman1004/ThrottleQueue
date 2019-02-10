<?php

namespace Drupal\queue_throttle;

/**
 * Interface QueueThrottleServiceInterface.
 *
 * @package Drupal\queue_throttle
 */
interface QueueThrottleServiceInterface {

  /**
   * Run a specific queue by name.
   *
   * @param string $queue_name
   *   The name of the queue to run, as defined in either hook_queue_info or
   *   hook_cron_queue_info.
   * @param int $time_limit
   *   Time limit or 0 for no limit.
   * @param int $tokens
   *   Number of tokens to process per unit.
   * @param string $unit
   *   Time unit.
   * @param bool $detailed_logging
   *   Detailed logging of process.
   *
   * @throws \Exception
   *   If the queue is suspended.
   */
  public function runQueue($queue_name, $time_limit, $tokens, $unit = 'second', $detailed_logging = FALSE);

}
