<?php

namespace Drupal\queue_throttle\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\CronInterface;

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
   * Constructs a cron object.
   *
   * @param \Drupal\Core\CronInterface $queue_throttle_cron
   *   The cron service.
   */
  public function __construct(CronInterface $queue_throttle_cron) {
    parent::__construct();
    $this->queueThrottleCron = $queue_throttle_cron;
  }

  /**
   * Runs the cron command.
   *
   * @command queue:throttle
   * @aliases queue-throttle
   */
  public function runQueueThrottleCron() {
    $this->queueThrottleCron->run();
  }

}
