<?php

namespace Drupal\neg_gcal;

use Google\Service\Calendar;
use Google\Service\Drive;
use Google\Client;

/**
 * Class CalendarService.
 */
class CalendarService {

  /**
   * Google service.
   */
  protected $service = FALSE;

  /**
   * Google file service.
   */
  protected $fileService = FALSE;

  /**
   * Google client.
   */
  protected $client = FALSE;

  /**
   * Settings array.
   */
  protected $settings = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $inst = NULL;

  /**
   * Gets the static instance.
   */
  public static function instance() {

    if (self::$inst === NULL) {
      self::$inst = new self();
    }

    return self::$inst->getService();
  }

  /**
   * Gets the static instance.
   */
  public static function fileInstance() {
    if (self::$inst === NULL) {
      self::$inst = new self();
    }

    return self::$inst->getFileService();
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
        throw new \Exception('Service Account Auth JSON is not set!');
      }

      if (is_null($subject)) {
        throw new \Exception('Service Account Subject is not set!');
      }

      $this->settings = json_decode($json);

      if (!$this->settings) {
        throw new \Exception('Could not decode Service Account JSON!');
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

      $this->client = new Client();
      $this->client->setAuthConfig((array) $settings);

      $this->client->addScope('https://www.googleapis.com/auth/calendar.readonly');
      $this->client->addScope('https://www.googleapis.com/auth/drive.readonly');
      $this->client->setSubject($settings->subject);

      if (!$this->client) {
        throw new \Exception('Could not get Google Calendar Client!');
      }
    }

    return $this->client;
  }

  /**
   * Gets the File Service.
   */
  public function getFileService() {
    if ($this->fileService === FALSE) {
      $this->fileService = new Drive($this->getClient());
      if (!$this->fileService) {
        throw new \Exception('Could not get Google Drive Service!');
      }
    }

    return $this->fileService;
  }

  /**
   * Gets the Calendar Service.
   */
  protected function getService() {

    if ($this->service === FALSE) {
      $this->service = new Calendar($this->getClient());
      if (!$this->service) {
        throw new \Exception('Could not get Google Calendar Service!');
      }
    }

    return $this->service;
  }

}
