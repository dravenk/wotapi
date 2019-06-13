<?php

namespace Drupal\wotapi_property\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PropertyTypeForm.
 *
 * @package Drupal\wotapi_property\Form
 */
class PropertyTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $wotapi_property_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $wotapi_property_type->label(),
      '#description' => $this->t("Label for the Property type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $wotapi_property_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\wotapi_property\Entity\PropertyType::load',
      ],
      '#disabled' => !$wotapi_property_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $wotapi_property_type = $this->entity;
    $status = $wotapi_property_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage('Created the %label Property type.', [
          '%label' => $wotapi_property_type->label(),
        ]);
        break;

      default:
        $this->messenger()->addMessage('Saved the %label Property type.', [
          '%label' => $wotapi_property_type->label(),
        ]);
    }
    $form_state->setRedirectUrl($wotapi_property_type->toUrl('collection'));
  }

}
