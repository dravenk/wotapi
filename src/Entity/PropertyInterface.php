<?php

namespace Drupal\wotapi\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining Property entities.
 *
 * @ingroup wotapi
 */
interface PropertyInterface extends ContentEntityInterface, EntityChangedInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Property creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Property.
   */
  public function getCreatedTime();

  /**
   * Sets the Property creation timestamp.
   *
   * @param int $timestamp
   *   The Property creation timestamp.
   *
   * @return \Drupal\wotapi\Entity\PropertyInterface
   *   The called Property entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Property read_only value.
   *
   * @return bool
   *   The Property read_only value.
   */
  public function isReadOnly();

}
