<?php

namespace Drupal\vgwort\Controller;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\vgwort\EntityJobMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class MessageTextView extends ControllerBase {

  public function __construct(
    private readonly EntityJobMapper $jobMapper,
    private readonly RendererInterface $renderer,
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('vgwort.entity_job_mapper'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  public function __invoke(RouteMatchInterface $route_match, string $entity_type_id, Request $request): array {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);

    // Use the sent revision if one is available.
    if ($entity instanceof RevisionableInterface) {
      $revisions_sent = $this->jobMapper->getRevisionsSent($entity);
      if ($last_revision_sent_id = array_key_last($revisions_sent)) {
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
        $storage = $this->entityTypeManager()->getStorage($entity->getEntityTypeId());
        $entity = $storage->loadRevision($last_revision_sent_id);
      }
    }

    $view_mode = $this->config('vgwort.settings')->get("entity_types.{$entity_type_id}.view_mode") ?? 'full';
    $content = $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode);
    $text = PlainTextOutput::renderFromHtml((string) $this->renderer->renderInIsolation($content));

    $build = [];
    $build['#title'] = $this->t('VG Wort text for %title', ['%title' => $entity->label()]);

    $build['content'] = [
      '#prefix' => '<pre>',
      '#plain_text' => $text,
      '#suffix' => '</pre>',
    ];

    // If not accessed via a modal then provide a button to go to the VG Wort
    // tab.
    if ($request->query->get('_wrapper_format') !== 'drupal_modal') {
      $build['button'] = [
        '#type' => 'link',
        '#title' => $this->t('Back to VG Wort overview'),
        '#url' => Url::fromRoute("entity.{$entity->getEntityTypeId()}.vgwort", [$entity->getEntityTypeId() => $entity->id()]),
        '#attributes' => [
          'class' => ['button', 'button--small'],
        ],
      ];
    }

    // This page depends on queue information and potentially data on references
    // therefore it is not possible to cache this info.
    CacheableMetadata::createFromObject($entity)->setCacheMaxAge(0)->applyTo($build);
    return $build;
  }

}
