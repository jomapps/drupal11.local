<?php

namespace Drupal\vgwort;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\vgwort\Plugin\Field\CounterIdFieldItemList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly EntityFieldManagerInterface $entityFieldManager, private readonly ParticipantListManager $participantListManager, private readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager) {
  }

  /**
   * Clears caches and creates base fields when VG Wort entity types change.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $saved_config = $event->getConfig();
    if ($saved_config->getName() == 'vgwort.settings' && $event->isChanged('entity_types')) {
      $this->entityFieldManager->clearCachedFieldDefinitions();
      $this->participantListManager->clearCachedDefinitions();

      // Manage base field creation.
      $new_entity_types = array_keys($saved_config->get('entity_types') ?? []);
      $old_entity_types = array_keys($saved_config->getOriginal('entity_types') ?? []);

      // Uninstall base fields.
      foreach (array_diff($old_entity_types, $new_entity_types) as $entity_type_id) {
        $field_storage_definition = $this->entityDefinitionUpdateManager->getFieldStorageDefinition(CounterIdFieldItemList::SUFFIX_FIELD_NAME, $entity_type_id);
        if ($field_storage_definition) {
          $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($field_storage_definition);
        }
      }

      // Install base fields.
      foreach (array_diff($new_entity_types, $old_entity_types) as $entity_type_id) {
        $this->entityDefinitionUpdateManager->installFieldStorageDefinition(CounterIdFieldItemList::SUFFIX_FIELD_NAME, $entity_type_id, 'vgwort', CounterIdFieldItemList::getSuffixFieldDefinition());
      }

    }
  }

  /**
   * Removes base fields when the module is uninstalled.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $saved_config = $event->getConfig();
    if ($saved_config->getName() == 'vgwort.settings') {
      foreach (array_keys($saved_config->getOriginal('entity_types') ?? []) as $entity_type_id) {
        $field_storage_definition = $this->entityDefinitionUpdateManager->getFieldStorageDefinition(CounterIdFieldItemList::SUFFIX_FIELD_NAME, $entity_type_id);
        if ($field_storage_definition) {
          $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($field_storage_definition);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    $events[ConfigEvents::DELETE][] = ['onConfigDelete', 0];
    return $events;
  }

}
