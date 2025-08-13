<?php

namespace Drupal\Tests\advancedqueue\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\plugin\PluginType\PluginType;

/**
 * @group advancedqueue
 */
class PluginModuleIntegrationTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'advancedqueue',
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('advancedqueue', ['advancedqueue']);
    $this->installConfig(['advancedqueue']);
  }

  /**
   * Tests that the plugin module can be installed and the integration works.
   */
  public function testPluginModuleIntegration() {
    $this->container->get('module_installer')->install(['plugin']);

    /** @var \Drupal\plugin\PluginType\PluginTypeManager $plugin_type_manager */
    $plugin_type_manager = $this->container->get('plugin.plugin_type_manager');
    $plugin_type = $plugin_type_manager->getPluginType('advancedqueue.backend');
    $this->assertInstanceOf(PluginType::class, $plugin_type);
  }

}
