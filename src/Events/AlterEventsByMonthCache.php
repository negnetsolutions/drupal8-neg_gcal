<?php

namespace Drupal\neg_gcal\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * {@inheritdoc}
 */
class AlterEventsByMonthCache extends Event {

  /**
   * {@inheritdoc}
   */
  const CACHE = 'neg_gcal_events_cache';

  /**
   * {@inheritdoc}
   */
  public $cache;

  /**
   * {@inheritdoc}
   */
  public function __construct(&$cache) {
    $this->cache = &$cache;
  }

}
