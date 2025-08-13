<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Traits;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\vgwort\EntityJobMapper;

/**
 * Installs VG Wort correctly in kernel tests.
 */
trait KernelSetupTrait {

  /**
   * Installs VGWort schemas and configuration correctly.
   */
  private function installVgWort(): void {
    $this->installSchema('advancedqueue', ['advancedqueue']);
    $this->installSchema('vgwort', [EntityJobMapper::TABLE]);

    // We can not use installConfig() for vgwort because the module has a
    // dependency on node.
    $default_install_path = $this->container->get('extension.path.resolver')->getPath('module', 'vgwort') . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    $storage = new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION);
    foreach ($storage->listAll() as $config_name) {
      $data = $storage->read($config_name);
      if ($config_name === 'vgwort.settings') {
        $data['publisher_id'] = 123456;
        $data['image_domain'] = 'http://example.com';
        $data['legal_rights'] = [
          'distribution' => TRUE,
          'public_access' => TRUE,
          'reproduction' => TRUE,
          'declaration_of_granting' => TRUE,
          'other_public_communication' => FALSE,
        ];

        if (!$this->container->get('module_handler')->moduleExists('node')) {
          $data['entity_types'] = [];
        }
      }
      $this->config($config_name)->setData($data)->save();
    }

    if (array_key_exists('ENTITY_TYPE', (new \ReflectionClass($this))->getConstants())) {
      // @phpstan-ignore-next-line
      _vgwort_add_entity_reference_to_participant_map($this::ENTITY_TYPE, 'user_id');
    }
  }

}
