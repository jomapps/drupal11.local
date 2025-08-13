<?php

namespace Drupal\vgwort\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides dynamic local tasks for VG Wort.
 */
class VGWortLocalTasks extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * Constructs a new VGWortLocalTasks.
   *
   * @param string $basePluginId
   *   The base plugin ID.
   * @param string[] $entityTypes
   *   The VG Wort supported entity types.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The translation manager.
   */
  public function __construct(protected readonly string $basePluginId, protected readonly array $entityTypes, TranslationInterface $stringTranslation) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      array_keys($container->get('config.factory')->get('vgwort.settings')->get('entity_types')),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Create tabs for all possible entity types.
    foreach ($this->entityTypes as $entity_type_id) {
      // Find the route name for the VG Wort overview.
      $vgwort_route_name = "entity.$entity_type_id.vgwort";

      $base_route_name = "entity.$entity_type_id.canonical";
      $this->derivatives[$vgwort_route_name] = [
        'entity_type' => $entity_type_id,
        'title' => $this->t('VG Wort'),
        'route_name' => $vgwort_route_name,
        'base_route' => $base_route_name,
      ] + $base_plugin_definition;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
