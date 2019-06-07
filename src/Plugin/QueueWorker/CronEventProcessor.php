<?php

namespace Drupal\neg_gcal\Plugin\QueueWorker;

/**
 *
 * @QueueWorker(
 * id = "gcal_sync",
 * title = "Syncs Google Calendar with Drupal",
 * cron = {"time" = 60}
 * )
 */
class CronEventProcessor extends SyncGoogleCalendar {
}
