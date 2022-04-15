<?php

namespace Drupal\neg_gcal;

use Drupal\neg_gcal\Events\AlterEventsByMonthEvent;

/**
 * Class Events.
 */
class Events {

  protected $conn = FALSE;

  /**
   * Gets all events by month.
   */
  public function getEventsByMonth($calendarId, $month = NULL, $year = NULL, $mergeSequencedEvents = FALSE) {
    if ($month == NULL) {
      $month = date('m', time());
    }
    if ($year == NULL) {
      $year = date('Y', time());
    }

    $month_start = $year . '-' . $month . '-01 00:00:00';
    $month_end = date('Y-m-t 23:59:59', strtotime($month_start));

    $params = [
      'calendarId' => $calendarId,
      'StartDateTime' => $month_start,
      'EndDateTime' => $month_end,
      'mergeSequencedEvents' => $mergeSequencedEvents,
    ];

    $e = $this->getEvents($params);

    $event = new AlterEventsByMonthEvent($e);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(AlterEventsByMonthEvent::READY, $event);

    return $this->sortEventsByDay($e);
  }

  /**
   * Groups and sorts events by day.
   */
  private function sortEventsByDay($e) {

    $current_date = FALSE;
    $events = [];
    foreach ($e as $event) {
      $eday = $event->getStartDate();

      if ($eday != $current_date) {
        $current_date = $eday;
        $events[$current_date] = [];
      }

      $events[$current_date][] = $event;

    }

    return $events;
  }

  /**
   * Queries for Events.
   */
  public function getEvents($params = []) {

    $events = [];

    $query = $this->db()->select('google_calendar_events', 'u')
      ->fields('u');
    if (isset($params['calendarId'])) {
      $query->condition("calendar_id", $params['calendarId']);
    }
    if (isset($params['StartDateTime'])) {
      $query->condition('start', $params['StartDateTime'], '>=');
    }
    if (isset($params['EndDateTime'])) {
      $query->condition('end', $params['EndDateTime'], '<=');
    }

    if (isset($params['mergeSequencedEvents']) && $params['mergeSequencedEvents'] == TRUE) {
      $query->condition('sequence', 0, '=');
    }

    if (isset($params['hasColor'])) {
      $query->isNotNull('color_id');
    }

    if (isset($params['limit'])) {
      $query->range(0, $params['limit']);
    }

    $query->orderBy('start', 'ASC');
    $query->orderBy('allDay', 'DESC');
    $query->orderBy('title', 'ASC');

    $results = $query->execute();

    $events = [];
    while ($record = $results->fetchAssoc()) {
      $event = new CalendarEvent($record['calendar_id'], unserialize($record['data']));
      $events[] = $event;
    }

    return $events;
  }

  /**
   * Gets the database.
   */
  protected function db() {
    if ($this->conn === FALSE) {
      $this->conn = \Drupal::service('database');
    }
    return $this->conn;
  }

}
