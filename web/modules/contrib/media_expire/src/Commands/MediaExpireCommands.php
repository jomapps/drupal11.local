<?php

namespace Drupal\media_expire\Commands;

use Drupal\media_expire\MediaExpireService;
use Drush\Commands\DrushCommands;

/**
 * Defines Drush commands for the media_expire module.
 */
class MediaExpireCommands extends DrushCommands {

  /**
   * MediaExpireCommands constructor.
   *
   * @param \Drupal\media_expire\MediaExpireService $mediaExpireService
   *   The media expire service.
   */
  public function __construct(protected readonly MediaExpireService $mediaExpireService) {
    parent::__construct();
  }

  /**
   * Checks for expired media.
   *
   * @command media:expire-check
   * @aliases mec,media-expire-check
   */
  public function expireCheck() {
    $this->mediaExpireService->unpublishExpiredMedia();
  }

}
