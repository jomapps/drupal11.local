<?php

namespace Drupal\content_lock\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Respond to a lock being successfully set.
 */
class ContentLockLockedEvent extends Event {

  const EVENT_NAME = 'locked';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    public string $entityId,
    public string $langcode,
    public string $formOp,
    public int $uid,
    public string $entityType,
  ) {
  }

}
