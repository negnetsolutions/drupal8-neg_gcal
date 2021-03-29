<?php

namespace Drupal\neg_gcal\Plugin;

use Drupal\neg_gcal\CalendarService;
use Drupal\neg_gcal\CalendarEvent;
use Drupal\neg_gcal\CalendarSettings;
use Drupal\Core\Cache\Cache;

/**
 * Class CalendarSync.
 */
class CalendarSync {
  protected $id;
  protected $conn = FALSE;

  /**
   * Implements __construct().
   */
  public function __construct($id) {
    $this->id = $id;
  }

  /**
   * Logs a message.
   */
  protected function log($message, $params = [], $log_level = 'notice') {
    \Drupal::logger('neg_gcal')->$log_level($message, $params);
  }

  /**
   * Gets an editable config object.
   */
  protected function editableConfig() {
    return \Drupal::service('config.factory')->getEditable(CalendarSettings::CONFIGNAME);
  }

  /**
   * Gets a config object.
   */
  protected function config() {
    return \Drupal::config(CalendarSettings::CONFIGNAME);
  }

  /**
   * Syncs the calendar.
   */
  public function sync($nextPageToken = NULL, $eventIdList = []) {
    $this->log("Begin Sync with Google Calendar ID: " . $this->id, [], 'debug');

    try {
      $service = CalendarService::instance();
    }
    catch (\Exception $e) {
      $this->log($e->getMessage(), [], 'error');
      return FALSE;
    }

    $year = date('Y', time());
    $timeStampMin = mktime(0, 0, 0, 1, 1, ($year - 1));

    $optParams = [
      'singleEvents' => 'true',
      'timeMin' => date('c', $timeStampMin),
      'timeMax' => date('c', (time() + 63072000)),
    ];

    $syncToken = $this->config()->get('nextSyncToken_' . md5($this->id));

    if ($syncToken !== NULL) {
      $optParams = [
        'syncToken' => $syncToken,
      ];
      $this->log('Using Sync Token: ' . $syncToken, [], 'debug');
    }

    if (!is_null($nextPageToken)) {
      $optParams['pageToken'] = $nextPageToken;
      $this->log('Using Next Page Token: ' . $nextPageToken, [], 'debug');
    }

    try {
      $results = $service->events->listEvents($this->id, $optParams);
    }
    catch (Exception $e) {

      if ($syncToken !== NULL) {
        // Delete the sync token so we can try a full sync next time!
        $this->editableConfig()->clear('nextSyncToken_' . md5($this->id))->save();
      }

      $this->log($e->getMessage(), [], 'error');

      return FALSE;
    }

    $items = $results->getItems();
    $this->log('Syncing ' . count($items) . ' events.', [], 'debug');

    foreach ($items as $item) {
      $ids = $this->processItem($item);
      $eventIdList = array_merge($eventIdList, $ids);
    }

    $nextPageToken = $results->getNextPageToken();
    $nextSyncToken = $results->getNextSyncToken();

    if (!is_null($nextSyncToken)) {
      // Set the next sync token.
      $this->editableConfig()->set('nextSyncToken_' . md5($this->id), $nextSyncToken)->save();
    }
    elseif (is_null($nextPageToken)) {
      // We didn't get one and we are on the last page of data,
      // let's delete the stored token.
      $this->editableConfig()->clear('nextSyncToken_' . md5($this->id))->save();
    }

    if (!is_null($nextPageToken)) {
      // Need to get the next page.
      return $this->sync($nextPageToken, $eventIdList);
    }

    if ($syncToken === NULL) {
      // Delete anything that's not in this sync.
      $q = $this->db()->delete(CalendarSettings::TABLENAME)
        ->condition('calendar_id', $this->id)
        ->condition('id', $eventIdList, 'not in')
        ->execute();

      // Set last_full_sync.
      $runtime = time();
      $this->editableConfig()->set('lastFullSync_' . md5($this->id), $runtime)->save();
      $this->log('Setting Last Full Sync Time for Calendar @id to @time', ['@id' => $this->id, '@time' => $runtime], 'notice');
    }

    // Invalidate Cache Tags.
    Cache::invalidateTags(['gcal_' . $this->id]);
    return TRUE;
  }

  /**
   * Requests a single recurring event.
   */
  protected function requestSingleRecurringEvent(\Google_Service_Calendar_Event $item) {
    try {
      $service = CalendarService::instance();
    }
    catch (\Exception $e) {
      $this->log($e->getMessage());
      return FALSE;
    }

    $optParams = [
      'singleEvents' => 'true',
      'iCalUID' => $item->getICalUID(),
    ];

    // Make api call.
    try {
      $results = $service->events->listEvents($this->id, $optParams);
    }
    catch (Exception $e) {
      $this->log($e->getMessage());
      return [];
    }

    return $results;
  }

  /**
   * Processes a calendar item.
   */
  protected function processItem($item) {

    if (!is_null($item->getRecurrence())) {
      $this->log('Requesting Recurring ' . $item->getICalUID());

      // This is a recurring event.
      // We need to request SingleEvents for this event.
      $new_events = $this->requestSingleRecurringEvent($item);

      $new_event_ids = [];
      foreach ($new_events as $e) {
        if ($e->getStatus() != 'cancelled') {
          $new_event_ids[] = $e->getId();
        }
      }

      // We need to delete all entries that have the same iCalUID
      // but not ids IN $new_event_ids.
      $nid = $this->db()->delete(CalendarSettings::TABLENAME)
        ->condition('calendar_id', $this->id)
        ->condition('ical_id', $item->getICalUID());

      if (count($new_event_ids) > 0) {
        // Exclude ids that match.
        $nid->condition('id', $new_event_ids, 'NOT IN');
      }

      // Run the delete query.
      $nid->execute();
      $this->log('Syncing ' . count($new_events) . ' recurring events from event ' . $item->getICalUID(), [], 'debug');

      // Then we need to process the rest of the events.
      $ids = [];
      foreach ($new_events as $event) {
        $id = $this->processItem($event);
        $ids = array_merge($ids, $id);
      }

      return $ids;
    }

    $data = (array) $item;
    $event = new CalendarEvent($this->id, $data);

    if ($item->status === 'cancelled' || $event->shouldSync() === FALSE) {
      // Delete the item.
      $nid = $this->db()->delete(CalendarSettings::TABLENAME)
        ->condition('calendar_id', $this->id)
        ->condition('id', $this->db()->escapeLike($item->getId()) . '%', 'LIKE')
        ->execute();
      return [];
    }
    else {

      // Calculate Start And End Dates.
      $event->setSequenceStartAndEnd();

      if ($event->eventSpansMultipleDays()) {
        // Delete all sequenced events that are longer than the actual sequence.
        $q = $this->db()->delete(CalendarSettings::TABLENAME)
          ->condition('calendar_id', $this->id)
          ->condition('id', $event->getId())
          ->condition('sequence', (ceil($event->getEventLengthDays())), '>=');

        $q->execute();
        foreach ($event->getSequencedEvents() as $e) {
          $this->addEventToDatabase($e);
        }
      }
      else {
        $this->addEventToDatabase($event);
      }

      return [$event->getId()];

    }

  }

  /**
   * Adds an event to the database.
   */
  protected function addEventToDatabase(CalendarEvent $event) {

    $nid = $this->db()->merge(CalendarSettings::TABLENAME)
      ->keys([
        'calendar_id' => $event->getCalendarId(),
        'id' => $event->getId(),
        'sequence' => $event->getSequence(),
      ])
      ->fields([
        'calendar_id' => $event->getCalendarId(),
        'id' => $event->getId(),
        'ical_id' => $event->getiCalUID(),
        'start' => $event->getStartDateTime(),
        'end' => $event->getEndDateTime(),
        'allDay' => (int) $event->isAllDayEvent(),
        'title' => $event->getTitle(),
        'sequence' => $event->getSequence(),
        'data' => serialize($event->getData()),
      ])
      ->execute();

    return $nid;
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
