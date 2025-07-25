<?php

namespace Drupal\poll_system\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Configure Poll System settings.
 */
class PollSystemSettingsForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'poll_system_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('poll_system.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable poll system'),
      '#description' => $this->t('Enable or disable the poll system globally.'),
      '#default_value' => $config->get('enabled') ?? TRUE,
    ];

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API Settings'),
      '#open' => TRUE,
    ];

    $form['api']['api_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable API'),
      '#description' => $this->t('Enable or disable the REST API for polls.'),
      '#default_value' => $config->get('api.enabled') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('poll_system.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('api.enabled', $form_state->getValue('api_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['poll_system.settings'];
  }

}
