<?php

namespace Drupal\vgwort;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides users with information about the VG Wort integration for an entity.
 */
class EntityInfo {
  use StringTranslationTrait;

  public function __construct(
    private readonly MessageGenerator $apiMessageGenerator,
    private readonly MessengerInterface $messenger,
    private readonly RendererInterface $renderer,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {
  }

  public function validateAndWarn(EntityInterface $entity): void {
    // This service only supports entity types that can be configured in the UI.
    // @see \Drupal\vgwort\Form\SettingsForm::buildForm()
    if (!$entity instanceof EntityPublishedInterface || !$entity instanceof FieldableEntityInterface) {
      return;
    }

    // Only entities with a counter ID can be checked.
    if ($entity->vgwort_counter_id->isEmpty()) {
      return;
    }

    $api_message = $this->apiMessageGenerator->entityToNewMessage($entity);
    $warnings = $api_message->validate();
    if (empty($warnings)) {
      return;
    }

    $bundle_label = $this->bundleInfo->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'] ?? $entity->bundle();
    $message = [
      '#type' => 'inline_template',
      '#template' => '{% trans %}Resolve all issues below to register this {{ bundle_label }} with VG Wort.{% endtrans %}{{ errors }}',
      '#context' => [
        'errors' => [
          '#theme' => 'item_list',
          '#items' => $warnings,
        ],
        'bundle_label' => $bundle_label,
      ],
    ];
    $this->messenger->addWarning($this->renderer->renderInIsolation($message));
  }

}
