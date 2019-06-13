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
 *   label = @Translation("Property"),
 *   bundle_label = @Translation("Property type"),
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
 *   field_ui_base_route = "entity.wotapi_property_type.edit_form"
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
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('description')->value;
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

    // Title field for the property.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The name member is a human friendly string which describes the device.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      // Set no default value.
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Description'))
      ->setDescription(t('The description member is a human friendly string which describes the device and its functions. '))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      // Set no default value.
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::postSave($storage);

    if ($this->isNew()) {
      $this->isNew = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
  }

}

