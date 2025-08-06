<?php

namespace Drupal\config_selector;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\DependencyInjection\Reference;

/**
 * ServiceProvider class for the Configuration Selector.
 */
class ConfigSelectorServiceProvider implements ServiceProviderInterface {

  /**
   * Registers the config_selector.decorating_config.installer service.
   *
   * This service decorates the config.installer so the optional profile
   * configuration can be selected during installation.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   */
  public function register(ContainerBuilder $container): void {
    // We only need to do this during an installation.
    if (!InstallerKernel::installationAttempted()) {
      return;
    }

    $container->register('config_selector.decorating_config.installer', DecoratingConfigInstaller::class)
      ->setDecoratedService('config.installer')
      ->addArgument(new Reference('config_selector.decorating_config.installer.inner'))
      ->addArgument(new Reference('config_selector'))
      ->setPublic(FALSE);
  }

}
