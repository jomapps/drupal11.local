<?php

namespace Drupal\content_lock\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\views\Views;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class acts on config save event.
 */
class SettingsSaveEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteBuilderInterface $routeBuilder,
  ) {
  }

  /**
   * On config save.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The save event.
   */
  public function onSave(ConfigCrudEvent $event): void {

    if ($event->getConfig()->getName() == 'content_lock.settings' && $event->isChanged('types')) {
      $this->routeBuilder->setRebuildNeeded();
      foreach (array_filter($event->getConfig()->get('types')) as $type => $value) {
        // Skip if the entity type does not exist.
        if (!$this->entityTypeManager->getDefinition($type, FALSE)) {
          continue;
        }

        // Create an action config for all activated entity types.
        $action = $this->entityTypeManager->getStorage('action')->loadByProperties([
          'plugin' => 'entity:break_lock:' . $type,
        ]);
        if (empty($action)) {
          $action = $this->entityTypeManager->getStorage('action')->create([
            'id' => $type . '_break_lock_action',
            'label' => 'Break lock ' . $type,
            'plugin' => 'entity:break_lock:' . $type,
            'type' => $type,
          ]);
          $action->save();
        }
      }
      if ($this->moduleHandler->moduleExists('views')) {
        Views::viewsData()->clear();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
