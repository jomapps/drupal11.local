<?php

namespace Drupal\config_selector\Compatibility;

trait Drupal11Dot1CompatibilityTrait {

  /**
   * @see \Drupal\Core\Config\ConfigInstallerInterface::installDefaultConfig()
   */
  public function installDefaultConfig($type, $name): void {
    $this->decoratedService->installDefaultConfig($type, $name);
  }

}
