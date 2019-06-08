<?php

namespace Drupal\wotapi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure WOT:API settings for this site.
 *
 * @internal
 */
class WotApiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wotapi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wotapi.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wotapi_config = $this->config('wotapi.settings');

    $form['read_only'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allowed operations'),
      '#options' => [
        'r' => $this->t('Accept only WOT:API read operations.'),
        'rw' => $this->t('Accept all WOT:API create, read, update, and delete operations.'),
      ],
      '#default_value' => $wotapi_config->get('read_only') === TRUE ? 'r' : 'rw',
      '#description' => $this->t('Warning: Only enable all operations if the site requires it. <a href=":docs">Learn more about securing your site with WOT:API.</a>', [':docs' => 'https://www.drupal.org/docs/8/modules/wotapi/security-considerations']),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('wotapi.settings')
      ->set('read_only', $form_state->getValue('read_only') === 'r')
      ->save();

    parent::submitForm($form, $form_state);
  }

}
