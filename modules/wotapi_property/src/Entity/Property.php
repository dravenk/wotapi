<?php

namespace Drupal\wotapi_property\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Property entity.
 *
 * @ingroup wotapi_property
 *
 * @ContentEntityType(
 *   id = "wotapi_property",
 *   label = @Translation("Thing property"),
 *   bundle_label = @Translation("Thing property type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wotapi_property\PropertyListBuilder",
 *     "views_data" = "Drupal\wotapi_property\Entity\PropertyViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\wotapi_property\Form\PropertyForm",
 *       "add" = "Drupal\wotapi_property\Form\PropertyForm",
 *       "edit" = "Drupal\wotapi_property\Form\PropertyForm",
 *       "delete" = "Drupal\wotapi_property\Form\PropertyDeleteForm",
 *     },
 *     "inline_form" = "Drupal\wotapi_property\Form\PropertyInlineForm",
 *     "access" = "Drupal\wotapi_property\PropertyAccessControlHandler",
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

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

