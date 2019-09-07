<?php

namespace Drupal\wotapi;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Thing entity.
 *
 * @see \Drupal\wotapi\Entity\Thing.
 */
class ThingAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\wotapi\Entity\ThingInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view thing entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit thing entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete thing entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add thing entities');
  }

}
