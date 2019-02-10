INTRODUCTION
-

Allows one to adapt queues to throttle whilst processing. Handy when ie. your
queue is consuming a third party rate limited API.

Enabling a queue for throttled processing will disable it from running on the
default core cron.

Set up a new cron job to schedule throttled queue processing, or manually run
one of the following drush commands (Support for both drush 8 and 9):

The primary features of this module:

- Adapt queues to be throttled whilst processing
- Custom drush command

REQUIREMENTS
-

This module depends on queues that are provided by either core, contrib or
custom modules.

INSTALLATION
-

- Enable module at `/admin/modules` 

- or through drush `drush en queue_throttle -y`

CONFIGURATION
-

1. Navigate to settings form through `Admin > Configuration > System > Queue
Throttle` 

   or directly at path `/admin/config/system/queue`

2. Enable/disable queues for throttled processing.
