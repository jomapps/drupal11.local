<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests adding counter ID to entity types.
 *
 * @group vgwort
 */
class EntityTypesConfigTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['user', 'field', 'entity_test', 'vgwort'];

  /**
   * Tests adding the counter ID field to an entity type.
   */
  public function testEntityTypes(): void {
    $this->installEntitySchema('entity_test');
    $this->assertArrayNotHasKey('vgwort_counter_id', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('entity_test'));
    $this->config('vgwort.settings')->set('entity_types', ['entity_test' => []])->save();
    // Test that enabling on entity test has created the suffix field.
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('entity_test', 'vgwort_counter_suffix'));
    $this->assertArrayHasKey('vgwort_counter_id', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('entity_test'));
    $this->config('vgwort.settings')->set('entity_types', [])->save();
    $this->assertArrayNotHasKey('vgwort_counter_id', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('entity_test'));
    // Test that disabling on entity_test has removed the suffix field.
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('entity_test', 'vgwort_counter_suffix'));
  }

}
