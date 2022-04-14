<?php

namespace Drupal\neg_gcal\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * {@inheritdoc}
 */
class AlterEventsByMonthEvent extends Event {

  /**
   * {@inheritdoc}
   */
  const READY = 'neg_gcal_events_ready';

  /**
   * {@inheritdoc}
   */
  public $events;

  /**
   * {@inheritdoc}
   */
  public function __construct(&$events) {
    $this->events = &$events;
  }

}
