<?php

namespace Drupal\wotapi\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Property type entity.
 *
 * @ConfigEntityType(
 *   id = "wotapi_property_type",
 *   label = @Translation("Property type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wotapi\PropertyTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wotapi\Form\PropertyTypeForm",
 *       "edit" = "Drupal\wotapi\Form\PropertyTypeForm",
 *       "delete" = "Drupal\wotapi\Form\PropertyTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wotapi_property_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "wotapi_property",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "description",
 *     "title",
 *     "at_type",
 *     "unit",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/wotapi_property_type/{wotapi_property_type}",
 *     "add-form" = "/admin/structure/wotapi_property_type/add",
 *     "edit-form" = "/admin/structure/wotapi_property_type/{wotapi_property_type}/edit",
 *     "delete-form" = "/admin/structure/wotapi_property_type/{wotapi_property_type}/delete",
 *     "collection" = "/admin/structure/wotapi_property_type"
 *   }
 * )
 */
class PropertyType extends ConfigEntityBundleBase implements PropertyTypeInterface {

  /**
   * Https://w3c.github.io/wot-thing-description/#dataschema.
   */


  /**
   * The Property type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Property type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The name member is a human friendly string which describes the device..
   *
   * @var string
   */
  protected $title;

  /**
   * A semantic @type (a string identifying a type from the linked @context).
   *
   * @var string
   */
  protected $at_type;

  /**
   * A unit ([SI] unit).
   *
   * @var string
   */
  protected $unit;

  /**
   * A brief description of this Property type.
   * The description member is a human friendly string which describes the device and its functions.
   *
   * @var string
   */
  protected $description;

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAtType() {
    return $this->at_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setAtType($at_type) {
    $this->at_type = $at_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnit() {
    return $this->unit;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnit($unit) {
    $this->unit = $unit;
    return $this;
  }

}
