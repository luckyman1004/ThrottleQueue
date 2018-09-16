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
    'queue-throttle' => [
      'description' => 'Run queue throttle cron',
    ],
  ];
}

/**
 * Callback for drush queue-throttle.
 */
function drush_queue_throttle() {
  \Drupal::service('queue_throttle.cron')->run();
}
