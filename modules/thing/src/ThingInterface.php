<?php

namespace Drupal\thing;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Thing entity.
 *
 * We have this interface so we can join the other interfaces it extends.
 *
 * @ingroup thing
 */
interface ThingInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
