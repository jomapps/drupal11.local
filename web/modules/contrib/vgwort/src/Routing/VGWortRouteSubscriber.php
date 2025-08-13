<?php

namespace Drupal\vgwort\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\vgwort\Controller\MessageTextView;
use Drupal\vgwort\Controller\Overview;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for entity VG Wort routes.
 */
class VGWortRouteSubscriber extends RouteSubscriberBase {

  /**
   * The supported entity types.
   */
  protected readonly array $entityTypes;

  public function __construct(ConfigFactoryInterface $configFactory, protected readonly EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypes = array_keys($configFactory->get('vgwort.settings')->get('entity_types'));
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypes as $entity_type_id) {
      // Inherit admin route status from edit route, if exists.
      $is_admin = FALSE;
      $route_name = "entity.$entity_type_id.edit_form";
      if ($edit_route = $collection->get($route_name)) {
        $is_admin = (bool) $edit_route->getOption('_admin_route');
      }
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if ($entity_type->hasLinkTemplate('drupal:vgwort-overview')) {
        $path = $entity_type->getLinkTemplate('drupal:vgwort-overview');
        $route = new Route(
          $path,
          [
            '_controller' => Overview::class,
            'entity_type_id' => $entity_type_id,
          ],
          [
            '_entity_access' => $entity_type_id . '.view',
            '_access_vgwort_overview' => $entity_type_id,
          ],
          [
            'parameters' => [
              $entity_type_id => [
                'type' => 'entity:' . $entity_type_id,
              ],
            ],
            '_admin_route' => $is_admin,
          ]
        );
        $collection->add("entity.$entity_type_id.vgwort", $route);

        // Add a route for text modals.
        $text_route = clone $route;
        $text_route->setPath($path . '_text');
        $text_route->setDefault('_controller', MessageTextView::class);
        $collection->add("entity.$entity_type_id.vgwort.text", $text_route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Should run after AdminRouteSubscriber so the routes can inherit admin
    // status of the edit routes on entities. Therefore, priority -210. Has to
    // be higher than -220 so param converters run.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -210];
    return $events;
  }

}
