<?php

namespace Drupal\wotapi\ResourceType;

/**
 * Provides a repository of all WOT:API resource types.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
interface ResourceTypeRepositoryInterface {

  /**
   * Gets all WOT:API resource types.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType[]
   *   The set of all WOT:API resource types in this Drupal instance.
   */
  public function all();

  /**
   * Gets a specific WOT:API resource type based on entity type ID and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The ID for the bundle to find. If the entity type does not have a bundle,
   *   then the entity type ID again.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType
   *   The requested WOT:API resource type, if it exists. NULL otherwise.
   *
   * @see \Drupal\Core\Entity\EntityInterface::bundle()
   */
  public function get($entity_type_id, $bundle);

  /**
   * Gets a specific WOT:API resource type based on a supplied typename.
   *
   * @param string $type_name
   *   The public typename of a WOT:API resource.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType|null
   *   The resource type, or NULL if none found.
   */
  public function getByTypeName($type_name);

}
