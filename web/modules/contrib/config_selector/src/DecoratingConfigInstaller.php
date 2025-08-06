<?php

namespace Drupal\config_selector;

use Drupal\config_selector\Compatibility\CompatibilityTrait;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Decorates the config.installer service so install_install_profiles() works.
 *
 * The install step that installs installation profiles calls the config
 * installer directly which results in the Configuration Selector not being able
 * to select configuration.
 */
class DecoratingConfigInstaller implements ConfigInstallerInterface {
  // @see config_selector.module
  use CompatibilityTrait;

  /**
   * DecoratingConfigInstaller constructor.
   *
   * @param \Drupal\Core\Config\ConfigInstallerInterface $decoratedService
   *   The config.installer service to decorate.
   * @param \Drupal\config_selector\ConfigSelector $configSelector
   *   The config_selector service.
   */
  public function __construct(
    protected ConfigInstallerInterface $decoratedService,
    protected ConfigSelector $configSelector,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function installOptionalConfig(?StorageInterface $storage = NULL, $dependency = []): void {
    $this->decoratedService->installOptionalConfig($storage, $dependency);
    if ($storage === NULL && empty($dependency)) {
      // This is not called as part of a regular module install. It's called
      // install_install_profile(). This means that
      // \Drupal\config_selector\ConfigSelector::$modulePreinstallTriggered will
      // be NULL and therefore this will proceed to check all the configuration.
      $this->configSelector->selectConfigOnInstall([]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function installCollectionDefaultConfig($collection): void {
    $this->decoratedService->installCollectionDefaultConfig($collection);
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceStorage(StorageInterface $storage): static {
    $this->decoratedService->setSourceStorage($storage);
    return $this;
  }

  /**
   * Gets the configuration storage that provides the default configuration.
   *
   * @return \Drupal\Core\Config\StorageInterface|null
   *   The configuration storage that provides the default configuration.
   *   Returns null if the source storage has not been set.
   */
  public function getSourceStorage(): ?StorageInterface {
    return $this->decoratedService->getSourceStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function setSyncing($status): static {
    $this->decoratedService->setSyncing($status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSyncing(): bool {
    return $this->decoratedService->isSyncing();
  }

  /**
   * {@inheritdoc}
   */
  public function checkConfigurationToInstall($type, $name): void {
    $this->decoratedService->checkConfigurationToInstall($type, $name);
  }

}
