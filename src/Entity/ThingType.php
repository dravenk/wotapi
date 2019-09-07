<?php

namespace Drupal\wotapi\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Thing type entity.
 *
 * @ConfigEntityType(
 *   id = "wotapi_thing_type",
 *   label = @Translation("Thing type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wotapi\ThingTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wotapi\Form\ThingTypeForm",
 *       "edit" = "Drupal\wotapi\Form\ThingTypeForm",
 *       "delete" = "Drupal\wotapi\Form\ThingTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wotapi_thing_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "wotapi_thing",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/wotapi_thing_type/{wotapi_thing_type}",
 *     "add-form" = "/admin/structure/wotapi_thing_type/add",
 *     "edit-form" = "/admin/structure/wotapi_thing_type/{wotapi_thing_type}/edit",
 *     "delete-form" = "/admin/structure/wotapi_thing_type/{wotapi_thing_type}/delete",
 *     "collection" = "/admin/structure/wotapi_thing_type"
 *   }
 * )
 */
class ThingType extends ConfigEntityBundleBase implements ThingTypeInterface {

  /**
   * The Thing type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Thing type label.
   *
   * @var string
   */
  protected $label;

  /**
   * Https://iot.mozilla.org/wot/#type-member.
   *
   * EXAMPLE 4: Example @type member
   * "@type": ["Light", "OnOffSwitch"]
   *
   * @var array
   */
  protected $at_type;

}
