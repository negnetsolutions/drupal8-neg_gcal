<?php

namespace Drupal\neg_gcal\Form;

use Drupal\neg_gcal\Calendars;
use Drupal\neg_gcal\CalendarSettings;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for Google Calendar.
 */
class GcalSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gcal_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      CalendarSettings::CONFIGNAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(CalendarSettings::CONFIGNAME);

    $form['json'] = [
      '#type' => 'textarea',
      '#title' => t('Service Account Auth JSON'),
      '#default_value' => $config->get('json'),
      '#description' => t('JSON exported from Google Service Account JSON'),
      '#required' => TRUE,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service Account Subject (Email Address)'),
      '#default_value' => $config->get('subject'),
      '#required' => TRUE,
    ];

    $form['frequency'] = [
      '#type' => 'select',
      '#title' => t('Sync Frequency'),
      '#default_value' => $config->get('frequency'),
      '#options' => [
        '0' => 'Every Cron Run',
        '900' => 'Every 15 Minutes',
        '1800' => 'Every 30 Minutes',
        '3600' => 'Every Hour',
        '10800' => 'Every 3 Hours',
        '21600' => 'Every 6 Hours',
        '86400' => 'Every 24 Hours',
      ],
      '#required' => TRUE,
    ];

    $calendars = $this->listCalendars();
    if ($calendars !== FALSE) {
      $form['synced_calendars'] = [
        '#type' => 'checkboxes',
        '#title' => t('Google Event Calendar To Sync'),
        '#description' => t('Choose which event calendars to sync from your google account.'),
        '#default_value' => $config->get('synced_calendars'),
        '#options' => $calendars,
      ];
    }

    $last_sync = \Drupal::state()->get('neg_gcal.last_sync', 0);
    $form['last_sync'] = [
      '#markup' => '<p>Last Sync: ' . date('r', $last_sync) . '</p>',
    ];

    $form['reset_last_sync'] = [
      '#type' => 'submit',
      '#value' => t('Reset Last Sync Time'),
      '#submit' => ['::resetLastSync'],
    ];

    $form['full_resync'] = [
      '#type' => 'submit',
      '#value' => t('Force Full Sync'),
      '#submit' => ['::fullSync'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Gets google calendars.
   */
  protected function listCalendars() {
    return Calendars::list();
  }

  /**
   * Forces a full sync next time.
   */
  public function fullSync(array &$form, FormStateInterface $form_state) {

    // Delete all next sync tokens.
    $query = \Drupal::database()->delete('key_value')
      ->condition('collection', 'state')
      ->condition('name', 'neg_gcal.nextSyncToken_%', 'LIKE')
      ->execute();

    // Clear Drupal State Cache after database deletion.
    \Drupal::state()->resetCache();

    // Reset last_sync time.
    \Drupal::state()->set('neg_gcal.last_sync', 0);

    \Drupal::messenger()->addStatus('A full sync has been queued. Run cron to sync.');
  }

  /**
   * Resets last sync time.
   */
  public function resetLastSync(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set('neg_gcal.last_sync', 0);
    \Drupal::messenger()->addStatus('Last Sync time has been reset. Run cron to sync.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $config = $this->configFactory->getEditable(CalendarSettings::CONFIGNAME);
    $config->set('json', $form_state->getValue('json'))
      ->set('subject', $form_state->getValue('subject'))
      ->set('frequency', $form_state->getValue('frequency'));

    // Set the synced calendars if the form has the value.
    if ($form_state->hasValue('synced_calendars')) {
      $config->set('synced_calendars', $form_state->getValue('synced_calendars'));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
