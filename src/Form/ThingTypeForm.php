<?php

namespace Drupal\wotapi\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ThingTypeForm.
 *
 * @package Drupal\wotapi\Form
 */
class ThingTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $wotapi_thing_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $wotapi_thing_type->label(),
      '#description' => $this->t("Label for the Thing type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $wotapi_thing_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\wotapi_thing\Entity\ThingType::load',
      ],
      '#disabled' => !$wotapi_thing_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $wotapi_thing_type = $this->entity;
    $status = $wotapi_thing_type->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Thing type.', [
          '%label' => $wotapi_thing_type->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Thing type.', [
          '%label' => $wotapi_thing_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($wotapi_thing_type->toUrl('collection'));
  }

}
