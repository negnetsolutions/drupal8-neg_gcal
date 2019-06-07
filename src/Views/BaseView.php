<?php

namespace Drupal\neg_gcal\Views;

use Drupal\neg_gcal\Events;

/**
 * Class BaseView.
 */
class BaseView {

  protected $variables;
  protected $calendar;
  protected $dates = [];
  protected $query = FALSE;

  /**
   * Implements __construct().
   */
  public function __construct(string $calendar, array &$variables) {
    $this->calendar = $calendar;
    $this->variables = &$variables;

    if (isset($this->variables['dates'])) {
      $this->dates = $this->variables['dates'];
    }

  }

  /**
   * Renders the view.
   */
  public function render() {
    $this->variables['attributes']['class'][] = str_replace("Drupal\\neg_gcal\\Views\\", '', get_class($this));
  }

  /**
   * Gets the query object.
   */
  protected function query() {
    if ($this->query === FALSE) {
      $this->query = new Events();
    }

    return $this->query;
  }

}
