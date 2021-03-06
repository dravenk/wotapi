<?php

namespace Drupal\wotapi\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Property entity.
 *
 * @ingroup wotapi
 *
 * @ContentEntityType(
 *   id = "wotapi_property",
 *   label = @Translation("Thing property"),
 *   bundle_label = @Translation("Thing property type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wotapi\PropertyListBuilder",
 *     "views_data" = "Drupal\wotapi\Entity\PropertyViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\wotapi\Form\PropertyForm",
 *       "add" = "Drupal\wotapi\Form\PropertyForm",
 *       "edit" = "Drupal\wotapi\Form\PropertyForm",
 *       "delete" = "Drupal\wotapi\Form\PropertyDeleteForm",
 *     },
 *     "inline_form" = "Drupal\wotapi\Form\PropertyInlineForm",
 *     "access" = "Drupal\wotapi\PropertyAccessControlHandler",
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "wotapi_property",
 *   admin_permission = "administer property entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/wotapi_property/{wotapi_property}",
 *     "add-page" = "/admin/structure/wotapi_property/add",
 *     "add-form" = "/admin/structure/wotapi_property/add/{wotapi_property_type}",
 *     "edit-form" = "/admin/structure/wotapi_property/{wotapi_property}/edit",
 *     "delete-form" = "/admin/structure/wotapi_property/{wotapi_property}/delete",
 *     "collection" = "/admin/structure/wotapi_property/overview",
 *   },
 *   bundle_entity_type = "wotapi_property_type",
 *   field_ui_base_route = "entity.wotapi_property_type.edit_form",
 *   common_reference_target = TRUE
 * )
 */
class Property extends ContentEntityBase implements PropertyInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return $this->get('read_only')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    // readOnly (A boolean indicating whether or not the property is read-only, defaulting to false)
    // see https://iot.mozilla.org/wot/#property-object
    $fields['read_only'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Read-Only'))
      ->setDescription(t('A boolean indicating whether or not the property is read-only, defaulting to false.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'settings' => ['display_label' => TRUE],
        'weight' => 0,
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::postSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
  }

}
