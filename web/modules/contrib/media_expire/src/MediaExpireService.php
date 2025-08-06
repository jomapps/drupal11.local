<?php

namespace Drupal\media_expire;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;

/**
 * Contains the media unpublish logic.
 *
 * @package Drupal\media_expire
 */
class MediaExpireService {

  /**
   * Constructs the MediaExpireService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   */
  public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager, protected readonly EntityRepositoryInterface $entityRepository) {
  }

  /**
   * Unpublishes already expired media elements.
   */
  public function unpublishExpiredMedia() {

    /** @var \Drupal\media\MediaTypeInterface[] $bundles */
    $bundles = $this->entityTypeManager->getStorage('media_type')
      ->loadMultiple();

    foreach ($bundles as $bundle) {
      if ($bundle->getThirdPartySetting('media_expire', 'enable_expiring')) {

        $expireField = $bundle->getThirdPartySetting('media_expire', 'expire_field');
        $query = $this->entityTypeManager->getStorage('media')->getQuery('AND');
        $query->condition('status', 1);
        $query->accessCheck(FALSE);
        $query->condition('bundle', $bundle->id());
        $query->condition($expireField, date("Y-m-d\TH:i:s"), '<');
        $entityIds = $query->execute();

        /** @var \Drupal\media\Entity\Media[] $medias */
        $medias = $this->entityTypeManager->getStorage('media')
          ->loadMultiple($entityIds);

        foreach ($medias as $media) {
          $media->setUnpublished();
          $media->$expireField->removeItem(0);
          $media->save();
        }
      }
    }
  }

  /**
   * Returns the fallback media item.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The current media item.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The fallback media.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getFallbackMedia(MediaInterface $media) {
    /** @var \Drupal\media\MediaTypeInterface $bundle */
    $bundle = $this->entityTypeManager
      ->getStorage('media_type')
      ->load($media->bundle());

    if ($bundle->getThirdPartySetting('media_expire', 'enable_expiring') && !$media->isPublished()) {
      $fallbackMediaUuid = $bundle->getThirdPartySetting('media_expire', 'fallback_media');
      if ($fallbackMediaUuid && $media = $this->entityRepository->loadEntityByUuid('media', $fallbackMediaUuid)) {
        return $media;
      }
    }
  }

}
