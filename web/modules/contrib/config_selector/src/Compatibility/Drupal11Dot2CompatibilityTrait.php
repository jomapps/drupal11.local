<?php

namespace Drupal\config_selector\Compatibility;

use Drupal\Core\Config\DefaultConfigMode;

trait Drupal11Dot2CompatibilityTrait {

  /**
   * @see \Drupal\Core\Config\ConfigInstallerInterface::installDefaultConfig()
   */
  public function installDefaultConfig($type, $name, DefaultConfigMode $mode = DefaultConfigMode::All): void {
    $this->decoratedService->installDefaultConfig($type, $name, $mode);
  }

}
