<?php

namespace Drupal\neg_gcal;

use function DeepCopy\deep_copy;
use Drupal\Core\Render\Markup;
use Drupal\Core\File\FileSystemInterface;

/**
 * Class CalendarEvent.
 */
class CalendarEvent {
  private $data;
  private $sequence = 0;
  private $calendarId;

  /**
   * Implements __construct().
   */
  public function __construct($calendarId, $data) {
    $this->data = $data;
    $this->calendarId = $calendarId;
  }

  /**
   * Gets the calendar ID.
   */
  public function getCalendarId() {
    return $this->calendarId;
  }

  /**
   * Gets Data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Gets model data.
   */
  public function getModelData() {
    return $this->data;
  }

  /**
   * Checks if an event is visible and should be synced.
   */
  public function shouldSync() {
    return TRUE;
    switch ($this->data['transparency']) {
      case 'transparent':
        return FALSE;
    }

    return TRUE;
  }

  /**
   * Get's event color.
   */
  public function getColorId() {
    return $this->data['colorId'];
  }

  /**
   * Gets the uid.j.
   */
  public function getiCalUID() {
    return $this->data['iCalUID'];
  }

  /**
   * Gets the event id.
   */
  public function getId() {
    return $this->data['id'];
  }

  /**
   * Gets the sequence.
   */
  public function getSequence() {
    return $this->sequence;
  }

  /**
   * Gets the location.
   */
  public function getLocation() {
    return $this->data['location'];
  }

  /**
   * Gets the title.
   */
  public function getTitle() {
    return $this->data['summary'];
  }

  /**
   * Gets link to event.
   */
  public function getLink() {
    return $this->data['htmlLink'];
  }

  /**
   * Get's file attachments.
   */
  public function getAttachments() {
    $attachments = [];
    if (isset($this->data['attachments'])) {
      foreach ($this->data['attachments'] as $attachment) {
        $data = [
          'type' => $attachment->mimeType,
          'id' => $attachment->fileId,
          'file' => NULL,
        ];

        try {
          $data['file'] = $this->getLocalFileFromAttachment($attachment);
        }
        catch (\Exception $e) {
          \Drupal::logger('neg_gcal')->error($e->getMessage());
        }

        $attachments[] = $data;
      }
    }

    return $attachments;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLocalFileFromAttachment($attachment) {
    $directory = file_build_uri('neg_gcal_attachments');
    if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      // If our directory doesn't exist and can't be created, use the default.
      $directory = NULL;
    }
    $fs = \Drupal::service('file_system');

    // Check destination.
    if (!is_dir($fs->realpath($directory))) {
      throw new \Exception('Couldn\'t get attachment download folder!');
    }

    $path = str_replace('///', '//', "{$directory}/") . $attachment->fileId;

    // Check for existing file.
    $existing_files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
      'uri' => $path,
    ]);

    if (count($existing_files) > 0) {
      return reset($existing_files);
    }

    // Download the file.
    $service = CalendarService::fileInstance();
    $file = $service->files->get($attachment->fileId, ['alt' => 'media']);
    $data = $file->getBody()->getContents();

    // Save the file.
    $local = file_save_data($data, $path, FileSystemInterface::EXISTS_REPLACE);

    return $local;
  }

  /**
   * {@inheritdoc}
   */
  public function getThumbnailFile() {
    $types = [
      'image/jpeg',
      'image/png',
      'image/gif',
    ];
    $attachments = $this->getAttachments();
    foreach ($attachments as $attachment) {
      if ($attachment['file'] && in_array($attachment['type'], $types)) {
        return $attachment['file'];
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getThumbnail(string $style = 'rs_image') {
    $file = $this->getThumbnailFile();
    if (!$file) {
      return FALSE;
    }

    $build = [
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => $style,
      '#uri' => $file->getFileUri(),
    ];

    // Get file information.
    $image = \Drupal::service('image.factory')->get($file->getFileUri());
    if (!$image->isValid()) {
      return FALSE;
    }

    $build['#width'] = $image->getWidth();
    $build['#height'] = $image->getHeight();

    return $build;
  }

  /**
   * Gets the description.
   */
  public function getDescription() {
    return $this->data['description'];
  }

  /**
   * Prints the description.
   */
  public function getHtmlDescription() {
    $text = $this->getDescription();
    $text = str_replace('html-blob', 'p', $text);
    $text = str_replace("\n", "<br />\n", $text);

    if (!strpos($text, '<p>')) {
      $text = "<p>$text</p>";
    }
    return [
      '#type' => 'processed_text',
      '#text' => $text,
      '#format' => 'html',
    ];
  }

  /**
   * Gets start date.
   */
  public function getStartDate() {
    $dt = $this->getDateTime($this->getStart());
    return $dt->format('m-d-Y');
  }

  /**
   * Gets start month.
   */
  public function getStartMonth() {
    $dt = $this->getDateTime($this->getStart());
    return $dt->format('M');
  }

  /**
   * Gets start day.
   */
  public function getStartDay() {
    $dt = $this->getDateTime($this->getStart());
    return $dt->format('d');
  }

  /**
   * Gets the end date.
   */
  public function getEndDate() {
    $dt = $this->getDateTime($this->getEnd());
    return $dt->format('m-d-Y');
  }

  /**
   * Gets StartDatetime.
   */
  public function getStartDateTime() {
    return $this->getMysqlDateTime($this->getStart());
  }

  /**
   * Gets end datetime.
   */
  public function getEndDateTime() {
    return $this->getMysqlDateTime($this->getEnd());
  }

  /**
   * Gets the delineator.
   */
  private function getProtectedDelineator() {
    return "\0*\0";
  }

  /**
   * Get last sequenced event.
   */
  public function getSequencedLastEvent() {
    $events = $this->getSequencedEvents();
    return end($events);
  }

  /**
   * Prints the sequence days.
   */
  public function getPrintableSequenceDays() {
    $start = $this->getDateTime($this->getSequenceStart());
    $lastEvent = $this->getSequencedLastEvent();
    $end = $this->getDateTime($lastEvent->getStart());

    $output = $start->format('j') . ' - ';

    if ($start->format('m/Y') != $end->format('m/Y')) {
      $output .= $end->format('M j');
    }
    else {
      $output .= $end->format('j');
    }

    return $output;
  }

  /**
   * Gets sequence start.
   */
  protected function getSequenceStart() {
    if (is_null($this->data['sequence']['start']['dateTime'])) {
      return $this->data['sequence']['start']['date'];
    }
    else {
      return $this->data['sequence']['start']['dateTime'];
    }
  }

  /**
   * Gets sequence end.
   */
  protected function getSequenceEnd() {
    if (is_null($this->data['sequence']['end']['dateTime'])) {
      return $this->data['sequence']['end']['date'];
    }
    else {
      return $this->data['sequence']['end']['dateTime'];
    }
  }

  /**
   * Gets Start.
   */
  protected function getStart() {
    if (is_null($this->data['start']['dateTime'])) {
      return $this->data['start']['date'];
    }
    else {
      return $this->data['start']['dateTime'];
    }
  }

  /**
   * Gets end.
   */
  protected function getEnd() {
    if (is_null($this->data['end']['dateTime'])) {
      return $this->data['end']['date'];
    }
    else {
      return $this->data['end']['dateTime'];
    }
  }

  /**
   * Prints duration.
   */
  public function printDuration() {
    $start = $this->getDateTime($this->getStart())->getTimeStamp();
    $end = $this->getDateTime($this->getEnd())->getTimeStamp();

    $chunks = [
      [60 * 60 * 24 * 365 , 'year'],
      [60 * 60 * 24 * 30 , 'month'],
      [60 * 60 * 24 * 7, 'week'],
      [60 * 60 * 24 , 'day'],
      [60 * 60 , 'hour'],
      [60 , 'minute'],
      [1 , 'second'],
    ];

    $since = $end - $start;

    for ($i = 0, $j = count($chunks); $i < $j; $i++) {
      $seconds = $chunks[$i][0];
      $name = $chunks[$i][1];
      if (($count = floor($since / $seconds)) != 0) {
        break;
      }
    }

    return ($count == 1) ? '1 ' . $name : "$count {$name}s";
  }

  /**
   * Prints Event Time.
   */
  public function getEventTimestamp() {
    return $this->getDateTime($this->getStart())->getTimeStamp();
  }

  /**
   * Prints Event Time.
   */
  public function getEventTime() {

    $start = $this->getDateTime($this->getStart())->getTimeStamp();
    $end = $this->getDateTime($this->getEnd())->getTimeStamp();

    $date = date('g:i', $start);

    if (date('a', $start) == 'am') {
      $pm = ' AM';
    }
    else {
      $pm = ' PM';
    }
    $output = $date . $pm;

    // End Time.
    if ($this->isSequenced() == FALSE) {
      $date = date('g:i', $end);
      if (date('a', $end) == 'am') {
        $pm = ' AM';
      }
      else {
        $pm = ' PM';
      }
      $output .= '<span> - ' . $date . $pm . '</span>';
    }

    return Markup::create($output);
  }

  /**
   * Gets sequenced Events.
   */
  public function getSequencedEvents() {
    $days = $this->getSequencedEventLengthDays();
    $events = [];
    $count = ceil($days);
    for ($i = 0; $i < $count; $i++) {
      $event = $this->getSequencedEvent($i);
      $events[] = $event;
    }

    return $events;
  }

  /**
   * Gets sequenced Event.
   */
  protected function getSequencedEvent($index) {
    $newEvent = new self($this->getCalendarId(), deep_copy($this->getData()));
    $newEvent->setSequence($index);
    $newEvent->addDaystoEventTime($index);

    return $newEvent;
  }

  /**
   * Sets sequence index.
   */
  protected function setSequence($index) {
    $this->sequence = $index;
  }

  /**
   * Sets sequence start and end.
   */
  public function setSequenceStartAndEnd() {
    if (!isset($this->data['sequence']) || !is_array($this->data['sequence'])) {
      $this->data['sequence'] = [];
    }

    $this->data['sequence']['start'] = (array) clone(object) $this->data['start'];
    $this->data['sequence']['end'] = (array) clone(object) $this->data['end'];
    $this->data['sequenced'] = $this->eventSpansMultipleDays();
  }

  /**
   * Adds days.
   */
  protected function addDaysToEventTime($days) {
    $start = $this->getStart();
    $sDt = clone $this->getDateTime($start);
    $sDt->add(new \DateInterval('P' . $days . 'D'));

    $eDt = clone $sDt;
    $eDt->add(new \DateInterval('PT23H59M'));

    if ($this->isAllDayEvent()) {
      $this->data['start']['date'] = $sDt->format('Y-m-d');
      $this->data['end']['date'] = $eDt->format('Y-m-d');
    }
    else {
      $this->data['start']['dateTime'] = $sDt->format('c');
      $this->data['end']['dateTime'] = $eDt->format('c');
    }

    $this->data['start'] = (array) clone(object) $this->data['start'];
    $this->data['end'] = (array) clone(object) $this->data['end'];
  }

  /**
   * Gets mysql datetime.
   */
  private function getMysqlDateTime($value) {
    $dt = new \DateTime($value);
    return $dt->format('Y-m-d H:i:s');
  }

  /**
   * Gets datetime.
   */
  private function getDateTime($time) {
    if ($this->isAllDayEvent()) {
      return \DateTime::createFromFormat('Y-m-d H:i:s', $time . ' 00:00:00');
    }

    return new \DateTime($time);
  }

  /**
   * Gets length in seconds.
   */
  public function getEventLengthSeconds() {
    $start = $this->getDateTime($this->getStart());
    $sTime = $start->getTimestamp();

    $end = $this->getDateTime($this->getEnd());
    $eTime = $end->getTimestamp();

    $diff = $eTime - $sTime;
    return $diff;
  }

  /**
   * Gets event length in days.
   */
  public function getEventLengthDays() {
    $diff = $this->getEventLengthSeconds();
    return ($diff / (60 * 60 * 24));
  }

  /**
   * Gets sequence length in seconds.
   */
  public function getSequencedEventLengthSeconds() {
    $start = $this->getDateTime($this->getSequenceStart());
    $sTime = $start->getTimestamp();

    $end = $this->getDateTime($this->getSequenceEnd());
    $eTime = $end->getTimestamp();

    $diff = $eTime - $sTime;
    return $diff;
  }

  /**
   * Gets event length in days.
   */
  public function getSequencedEventLengthDays() {
    $diff = $this->getSequencedEventLengthSeconds();
    return ($diff / (60 * 60 * 24));
  }

  /**
   * Checks if event is sequenced.
   */
  public function isSequenced() {
    return $this->data['sequenced'];
  }

  /**
   * Checks if all day event.
   */
  public function isAllDayEvent() {
    if (is_null($this->data['end']['dateTime'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if event spans multiple days.
   */
  public function eventSpansMultipleDays() {

    $days = $this->getEventLengthDays();

    if ($days > 1) {
      return TRUE;
    }

    return FALSE;

  }

}
