<?php

namespace Drupal\wotapi_property;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Property entity.
 *
 * @see \Drupal\wotapi_property\Entity\Property.
 */
class PropertyAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\wotapi_property\Entity\PropertyInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view property entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit property entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete property entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add property entities');
  }

}
