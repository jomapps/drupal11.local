<?php

namespace Drupal\config_selector\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the feature entity type.
 *
 * @see \Drupal\config_selector\Entity\Feature
 */
class FeatureAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    return match ($operation) {
      'view', 'manage' => AccessResult::allowedIf($account->hasPermission('administer site configuration'))->cachePerPermissions(),
      // Leave plumbing for editing and deleting via the UI.
      // @todo implement.
      'update', 'delete' => AccessResult::forbidden(),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    // Leave plumbing for creating via the UI.
    // @todo implement.
    return AccessResult::forbidden();
  }

}
