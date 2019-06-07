<?php

namespace Drupal\neg_gcal;

use Google_Client;
use Google_Service_Calendar;

/**
 * Class CalendarService.
 */
class CalendarService {
  protected $service = FALSE;
  protected $client = FALSE;
  protected $settings = FALSE;

  /**
   * Gets the static instance.
   */
  public static function instance() {
    static $inst = NULL;

    if ($inst === NULL) {
      $inst = new self();
    }

    return $inst->getService();
  }

  /**
   * Constructs a Google Calendar Service Instance.
   */
  protected function __construct() {
  }

  /**
   * Gets the service account settings.
   */
  protected function getServiceAccountSettings() {
    if ($this->settings === FALSE) {

      // Get config.
      $config = \Drupal::config(CalendarSettings::CONFIGNAME);
      $json = $config->get('json');
      $subject = $config->get('subject');

      if (is_null($json)) {
        throw new Exception('Service Account Auth JSON is not set!');
      }

      if (is_null($subject)) {
        throw new Exception('Service Account Subject is not set!');
      }

      $this->settings = json_decode($json);

      if (!$this->settings) {
        throw new Exception('Could not decode Service Account JSON!');
      }

      $this->settings->subject = $subject;
    }

    return $this->settings;
  }

  /**
   * Gets the Calendar Client.
   */
  protected function getClient() {
    if ($this->client === FALSE) {
      $settings = $this->getServiceAccountSettings();

      $this->client = new Google_Client();
      $this->client->setAuthConfig((array) $settings);

      $this->client->addScope('https://www.googleapis.com/auth/calendar.readonly');
      $this->client->setSubject($settings->subject);

      if (!$this->client) {
        throw new Exception('Could not get Google Calendar Client!');
      }
    }

    return $this->client;
  }

  /**
   * Gets the Calendar Service.
   */
  protected function getService() {

    if ($this->service === FALSE) {
      $this->service = new Google_Service_Calendar($this->getClient());
      if (!$this->service) {
        throw new Exception('Could not get Google Calendar Service!');
      }
    }

    return $this->service;
  }

}
