<?php

namespace Drupal\vgwort\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

class OverviewAccess implements AccessInterface {

  public function __construct(private readonly AccountInterface $currentUser) {
  }

  /**
   * Checks access to the VG Wort overview for the entity and bundle.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account, $entity_type_id) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    return AccessResult::allowedIfHasPermission($this->currentUser, 'view vgwort info')->andIf($entity->access('update', $this->currentUser, TRUE));
  }

}
