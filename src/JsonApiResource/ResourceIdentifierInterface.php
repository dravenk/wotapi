<?php

namespace Drupal\wotapi\WotApiResource;

/**
 * An interface for identifying a related resource.
 *
 * Implement this interface when an object is a stand-in for an Entity object.
 * For example, \Drupal\wotapi\Exception\EntityAccessDeniedHttpException
 * implements this interface because it often replaces an entity in a WOT:API
 * Data object.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
interface ResourceIdentifierInterface {

  /**
   * Gets the resource identifier's ID.
   *
   * @return string
   *   A resource ID.
   */
  public function getId();

  /**
   * Gets the resource identifier's WOT:API resource type name.
   *
   * @return string
   *   The WOT:API resource type name.
   */
  public function getTypeName();

  /**
   * Gets the resource identifier's WOT:API resource type.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType
   *   The WOT:API resource type.
   */
  public function getResourceType();

}
