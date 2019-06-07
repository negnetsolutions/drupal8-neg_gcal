<?php

namespace Drupal\neg_gcal\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\neg_gcal\Plugin\CalendarSync;
use Drupal\neg_gcal\CalendarSettings;

/**
 * Class SyncGoogleCalendar.
 */
class SyncGoogleCalendar extends QueueWorkerBase {

  /**
   * Processes a queue item.
   */
  public function processItem($data) {

    switch ($data['op']) {
      case 'syncCalendar':
        $id = $data['calendarId'];

        $calendar = new CalendarSync($id);
        $calendar->sync();
        break;

      case 'finishSync':
        $config = \Drupal::service('config.factory')->getEditable(CalendarSettings::CONFIGNAME);
        $config->set('last_sync', time());
        $config->save();
        break;
    }
  }

}
