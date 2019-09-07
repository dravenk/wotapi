<?php

namespace Drupal\wotapi\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Contains field type "wotapi_action".
 *
 * @FieldType(
 *   id = "wotapi_action",
 *   label = @Translation("Thing action"),
 *   description = @Translation("Thing action."),
 *   category = @Translation("Thing"),
 *   default_widget = "wotapi_action_default",
 *   default_formatter = "wotapi_action_default",
 * )
 */
class WotapiActionItem extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'action' => [
          'type' => 'text',
          'size' => 'tiny',
        ],
      ],
    ];
  }

  /**
   *
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['action'] = DataDefinition::create('string')
      ->setLabel(t('Action'));

    return $properties;

  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('action')->getValue();
    return $value === NULL || $value === '';
  }

}
