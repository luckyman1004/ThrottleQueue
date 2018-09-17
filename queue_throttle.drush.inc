<?php

/**
 * @file
 * Drush commands for the Queue Throttle module.
 */

/**
 * Implements hook_drush_command().
 */
function queue_throttle_drush_command() {
  return [
    'queue-throttle-cron' => [
      'description' => 'Run queue throttle cron',
      'aliases' => [
        'queue-throttle',
        'queue-throttle:cron',
      ],
    ],
    'queue-throttle-run' => [
      'description' => 'Throttle a specific queue by name',
      'arguments' => [
        'name' => 'Queue to throttle',
      ],
      'options' => [
        'time-limit' => 'Time limit for the queue to run or 0 for no limit.',
        'items' => 'Items to process per unit.  e.g. --items=60',
        'unit' => 'Time unit, e.g. --unit=second',
      ],
    ],
  ];
}

/**
 * Callback for drush queue-throttle-cron.
 */
function drush_queue_throttle_cron() {
  if (\Drupal::service('queue_throttle.cron')->run() !== TRUE) {
    throw new \Exception(dt('Queue throttle cron run failed.'));
  }
}

/**
 * Callback for drush queue-throttle-run.
 */
function drush_queue_throttle_run($name) {
  $items = (int) drush_get_option('items');
  $unit = drush_get_option('unit') ?: 'second';
  $time_limit = (int) drush_get_option('time-limit');
  \Drupal::service('queue_throttle.service')->runQueue($name, $time_limit, $items, $unit);
}
