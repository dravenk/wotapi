<?php

namespace Drupal\wotapi_thing\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Thing entity.
 *
 * @ingroup wotapi_thing
 *
 * @ContentEntityType(
 *   id = "wotapi_thing",
 *   label = @Translation("Thing"),
 *   bundle_label = @Translation("Thing type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wotapi_thing\ThingListBuilder",
 *     "views_data" = "Drupal\wotapi_thing\Entity\ThingViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\wotapi_thing\Form\ThingForm",
 *       "add" = "Drupal\wotapi_thing\Form\ThingForm",
 *       "edit" = "Drupal\wotapi_thing\Form\ThingForm",
 *       "delete" = "Drupal\wotapi_thing\Form\ThingDeleteForm",
 *     },
 *     "inline_form" = "Drupal\wotapi_thing\Form\ThingInlineForm",
 *     "access" = "Drupal\wotapi_thing\ThingAccessControlHandler",
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "wotapi_thing",
 *   admin_permission = "administer thing entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/wotapi_thing/{wotapi_thing}",
 *     "add-page" = "/admin/structure/wotapi_thing/add",
 *     "add-form" = "/admin/structure/wotapi_thing/add/{wotapi_thing_type}",
 *     "edit-form" = "/admin/structure/wotapi_thing/{wotapi_thing}/edit",
 *     "delete-form" = "/admin/structure/wotapi_thing/{wotapi_thing}/delete",
 *     "collection" = "/admin/structure/wotapi_thing/overview",
 *   },
 *   bundle_entity_type = "wotapi_thing_type",
 *   field_ui_base_route = "entity.wotapi_thing_type.edit_form"
 * )
 */
class Thing extends ContentEntityBase implements ThingInterface {

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

    // Name field for the thing.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title member is a human friendly string which describes the device.'))
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

    // // Owner property of the thing.
    //    $fields['properties'] = BaseFieldDefinition::create('entity_reference')
    //      ->setLabel(t('Property'))
    //      ->setDescription(t('The Property.'))
    //      ->setSetting('target_type', 'wotapi_property')
    //      ->setSetting('handler', 'default')
    //      ->setDisplayOptions('view', [
    //        'label' => 'above',
    //        'type' => 'wotapi_property',
    //        'weight' => -3,
    //      ])
    //      ->setDisplayOptions('form', [
    //        'type' => 'entity_reference_autocomplete',
    //        'settings' => [
    //          'match_operator' => 'CONTAINS',
    //          'size' => 60,
    //          'placeholder' => '',
    //        ],
    //        'weight' => -3,
    //      ])
    //      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
    //      ->setDisplayConfigurable('form', TRUE)
    //      ->setDisplayConfigurable('view', TRUE);
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
