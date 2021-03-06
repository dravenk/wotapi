<?php

namespace Drupal\wotapi\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PropertyTypeForm.
 *
 * @package Drupal\wotapi\Form
 */
class PropertyTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\wotapi\Entity\PropertyTypeInterface $wotapi_property_type */
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
        'exists' => '\Drupal\wotapi\Entity\PropertyType::load',
      ],
      '#disabled' => !$wotapi_property_type->isNew(),
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $wotapi_property_type->getTitle(),
      '#description' => $this->t("A title (A string providing a human friendly name)"),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#maxlength' => 255,
      '#default_value' => $wotapi_property_type->getDescription(),
      '#description' => $this->t("The description member is a human friendly string which describes the device and its functions."),
    ];

    $form['at_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@type'),
      '#maxlength' => 255,
      '#default_value' => $wotapi_property_type->getAtType(),
      '#description' => $this->t("A semantic @type (a string identifying a type from the linked @context)"),
    ];

    $form['unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unit'),
      '#maxlength' => 255,
      '#default_value' => $wotapi_property_type->getUnit(),
      '#description' => $this->t("A unit ([SI] unit)."),
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
        // $this->messenger()->addMessage('Created the %label Property type.', [
        //          '%label' => $wotapi_property_type->label(),
        //        ]);
        break;

      default:
        // $this->messenger()->addMessage('Saved the %label Property type.', [
        //          '%label' => $wotapi_property_type->label(),
        //        ]);
    }
    $form_state->setRedirectUrl($wotapi_property_type->toUrl('collection'));
  }

}
