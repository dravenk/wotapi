<?php

namespace Drupal\wotapi\WotApiResource;

/**
 * Used to associate an object like an exception to a particular resource.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see \Drupal\wotapi\WotApiResource\ResourceIdentifierInterface
 */
trait ResourceIdentifierTrait {

  /**
   * A ResourceIdentifier object.
   *
   * @var \Drupal\wotapi\WotApiResource\ResourceIdentifier
   */
  protected $resourceIdentifier;

  /**
   * The WOT:API resource type of of the identified resource object.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceType
   */
  protected $resourceType;

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->resourceIdentifier->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName() {
    return $this->resourceIdentifier->getTypeName();
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceType() {
    if (!isset($this->resourceType)) {
      $this->resourceType = $this->resourceIdentifier->getResourceType();
    }
    return $this->resourceType;
  }

}
