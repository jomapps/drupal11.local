<?php

namespace Drupal\config_selector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an interface defining a Configuration Selector feature entity.
 */
interface FeatureInterface extends ConfigEntityInterface {

  /**
   * Gets the feature's description.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The feature's description.
   */
  public function getDescription(): string|TranslatableMarkup;

  /**
   * Gets the all feature's configuration entities.
   *
   * @return array
   *   The arrays of configuration entity objects keyed by their entity type ID.
   */
  public function getConfiguration(): array;

  /**
   * Gets the feature's configuration entities of the specified type.
   *
   * @param string $entity_type_id
   *   The entity type of the returned configuration entities.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   The feature's configuration entities of the specified type. Keyed by
   *   their ID.
   */
  public function getConfigurationByType(string $entity_type_id): array;

}
