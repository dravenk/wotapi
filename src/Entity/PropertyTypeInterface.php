<?php

namespace Drupal\wotapi\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;

/**
 * Provides an interface for defining Property type entities.
 */
interface PropertyTypeInterface extends ConfigEntityInterface, EntityDescriptionInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * {@inheritdoc}
   */
  public function getTitle();

  /**
   * {@inheritdoc}
   */
  public function setTitle($title);

  /**
   * {@inheritdoc}
   */
  public function getDescription();

  /**
   * {@inheritdoc}
   */
  public function setDescription($description);

  /**
   * {@inheritdoc}
   */
  public function getAtType();

  /**
   * {@inheritdoc}
   */
  public function setAtType($at_type);

  /**
   * {@inheritdoc}
   */
  public function getUnit();

  /**
   * {@inheritdoc}
   */
  public function setUnit($unit);

}
