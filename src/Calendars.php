<?php

namespace Drupal\neg_gcal;

/**
 * Class Calendars.
 */
class Calendars {

  /**
   * Lists all synced gcal calendars.
   */
  public static function listSynced() {
    $config = \Drupal::config(CalendarSettings::CONFIGNAME);

    $calendars = [];
    foreach ($config->get('synced_calendars') as $id => $data) {
      // Only use "checked" items.
      if ($id === $data) {
        $calendars[] = base64_decode($id);
      }
    }

    return $calendars;
  }

  /**
   * Lists all synced gcal calendars with their labels.
   */
  public static function listSyncedWithLabels() {
    $gcal_calendars = self::list();
    $synced = self::listSynced();
    $calendars = [];

    foreach ($synced as $id) {
      $bid = base64_encode($id);
      if (isset($gcal_calendars[$bid])) {
        $calendars[$id] = $gcal_calendars[$bid];
      }

    }

    return $calendars;
  }

  /**
   * Lists all Gcal Calendars.
   */
  public static function list() {
    $calendars = [];

    try {
      $service = CalendarService::instance();
      $results = $service->calendarList->listCalendarList();
      foreach ($results->getItems() as $item) {
        $calendars[base64_encode($item->getId())] = $item->getSummary();
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'warning', TRUE);
      return FALSE;
    }

    return $calendars;
  }

}
