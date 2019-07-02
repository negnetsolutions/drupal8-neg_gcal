<?php

namespace Drupal\neg_gcal\Views;

/**
 * Class MonthListView.
 */
class MonthListView extends BaseView {

  /**
   * Sets options.
   */
  protected function setOptions() {

    // Set defaults.
    if (count($this->dates) === 0) {
      $this->dates = [
        'month' => date('m', time()),
        'year' => date('Y', time()),
      ];
    }

    if (isset($_GET['month']) && is_numeric($_GET['month'])) {
      $this->dates['month'] = sprintf("%02d", (int) $_GET['month']);
    }

    if (isset($_GET['year']) && is_numeric($_GET['year'])) {
      $this->dates['year'] = sprintf("%04d", (int) $_GET['year']);
    }

    $day = "{$this->dates['month']}-01-{$this->dates['year']}";
    $dt = \DateTime::createFromFormat('m-d-Y', $day);
    $this->dates['label'] = $dt->format('F Y');

    $dt->modify('+1 month');
    $this->dates['nextmonth'] = $dt->format('m');
    $this->dates['nextyear'] = $dt->format('Y');
    $dt->modify('-2 month');
    $this->dates['prevmonth'] = $dt->format('m');
    $this->dates['prevyear'] = $dt->format('Y');
  }

  /**
   * Renders the view.
   */
  public function render() {

    parent::render();

    $this->setOptions();
    $days = $this->fetchEventDays();

    $this->variables['view'] = [
      '#theme' => 'neg_gcal__month_list_view',
      '#days' => $days,
      '#dates' => $this->dates,
      '#attached' => [
        'library' => [
          'neg_gcal/monthlist',
        ],
      ],
      '#cache' => [
        'contexts' => ['url'],
        'tags' => ['gcal_' . $this->calendar],
      ],
    ];

  }

  /**
   * Fetches events.
   */
  protected function fetchEventDays() {
    $days = $this->query()->getEventsByMonth($this->calendar, $this->dates['month'], $this->dates['year'], FALSE);
    $output = [];

    foreach ($days as $day => $events) {
      $day_label = \DateTime::createFromFormat('m-d-Y', $day)->format('j');
      $sequenced = [];

      if (!isset($output[$day_label])) {
        $output[$day_label] = [
          'normal' => [],
          'sequenced' => [],
        ];
      }

      foreach ($events as $event) {
        $output[$day_label]['normal'][] = $event;
      }

    }

    return $output;
  }

}
