<?php

namespace Drupal\wotapi_thing\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Thing edit forms.
 *
 * @ingroup wotapi_thing
 */
class ThingForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\wotapi_thing\Entity\Thing */
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage('Created the %label Thing.', [
          '%label' => $entity->label(),
        ]);
        break;

      default:
        $this->messenger()->addMessage('Saved the %label Thing.', [
          '%label' => $entity->label(),
        ]);
    }
    $form_state->setRedirect('entity.wotapi_thing.canonical', ['wotapi_thing' => $entity->id()]);
  }

}
