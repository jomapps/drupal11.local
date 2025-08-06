<?php

namespace Drupal\config_selector\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test derivative with an unsafe string.
 */
class FeatureListMenuLink extends DeriverBase implements ContainerDeriverInterface, ContainerInjectionInterface {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id = ''): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    if ($this->count()) {
      $this->derivatives['config_selector.admin_display'] = $base_plugin_definition;
    }
    return $this->derivatives;
  }

  /**
   * Allows access to Config Selector UI if there are entities.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    $entity_type = $this->entityTypeManager->getDefinition('config_selector_feature');
    return AccessResult::allowedIf($this->count() > 0)->addCacheTags($entity_type->getListCacheTags())->addCacheContexts($entity_type->getListCacheContexts());
  }

  /**
   * Gets the count of feature entities.
   *
   * @return int
   *   The count of feature entities.
   */
  protected function count(): int {
    return $this->entityTypeManager
      ->getStorage('config_selector_feature')
      ->getQuery()
      ->accessCheck()
      ->count()
      ->execute();
  }

}
