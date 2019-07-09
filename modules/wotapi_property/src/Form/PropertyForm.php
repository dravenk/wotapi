<?php

namespace Drupal\wotapi_property\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Property edit forms.
 *
 * @ingroup wotapi_property
 */
class PropertyForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\wotapi_property\Entity\Property */
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
//        $this->messenger()->addMessage('Created the %label Property.', [
////          '%label' => $entity->label(),
//          '%label' => $entity->id(),
//        ]);
        break;

      default:
//        $this->messenger()->addMessage('Saved the %label Property.', [
////          '%label' => $entity->label(),
//          '%label' => $entity->id(),
//        ]);
    }
    $form_state->setRedirect('entity.wotapi_property.canonical', ['wotapi_property' => $entity->id()]);
  }

}
