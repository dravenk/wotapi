<?php

namespace Drupal\wotapi_action\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Contains field widget "wotapi_action_default".
 *
 * @FieldWidget(
 *   id = "wotapi_action_default",
 *   label = @Translation("Thing action default"),
 *   field_types = {
 *     "wotapi_action",
 *   }
 * )
 */
class WotapiActionDefaultWidget extends WidgetBase implements WidgetInterface {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = [];
    if (!$element['#required']) {
      $options[''] = $this->t('- None -');
    }
    $supported_actions = \Drupal::service('wotapi_action.handler')->supportedActions();
    // $options += $supported_actions;
    // set the key same with the value. ['fade' => 'fade']
    foreach ($supported_actions as $k => $v) {
      $options[$k] = $k;
    }

    $element['action'] = $element + [
        '#type' => 'select',
        '#default_value' => isset($items[$delta]->action) ? $items[$delta]->action : NULL,
        '#options' => $options,
      ];

    return $element;
  }
}
