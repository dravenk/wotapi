<?php

namespace Drupal\wotapi_property\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Property type entity.
 *
 * @ConfigEntityType(
 *   id = "wotapi_property_type",
 *   label = @Translation("Property type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wotapi_property\PropertyTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wotapi_property\Form\PropertyTypeForm",
 *       "edit" = "Drupal\wotapi_property\Form\PropertyTypeForm",
 *       "delete" = "Drupal\wotapi_property\Form\PropertyTypeDeleteForm"
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

}
