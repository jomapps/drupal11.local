<?php

namespace Drupal\content_lock\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Respond to an entity's lock being released.
 */
class ContentLockReleaseEvent extends Event {

  const EVENT_NAME = 'release';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    public string $entityId,
    public string $langcode,
    public ?string $formOp,
    public string $entityType,
  ) {
  }

}
