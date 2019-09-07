<?php

namespace Drupal\wotapi\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'thing_action' formatter.
 *
 * @FieldFormatter(
 *   id = "wotapi_action_default",
 *   label = @Translation("Thing action"),
 *   field_types = {
 *     "wotapi_action"
 *   }
 * )
 */
class WotapiActionDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays the thing action.');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = ['#markup' => $item->action];
    }

    return $element;
  }

}
