<?php

namespace Drupal\property;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Property entity.
 *
 * We have this interface so we can join the other interfaces it extends.
 *
 * @ingroup property
 */
interface PropertyInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
