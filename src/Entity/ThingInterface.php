<?php

namespace Drupal\wotapi\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining Thing entities.
 *
 * @ingroup wotapi
 */
interface ThingInterface extends ContentEntityInterface, EntityChangedInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Thing creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Thing.
   */
  public function getCreatedTime();

  /**
   * Sets the Thing creation timestamp.
   *
   * @param int $timestamp
   *   The Thing creation timestamp.
   *
   * @return \Drupal\wotapi\Entity\ThingInterface
   *   The called Thing entity.
   */
  public function setCreatedTime($timestamp);

}
