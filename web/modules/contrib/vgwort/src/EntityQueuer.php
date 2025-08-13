<?php

namespace Drupal\vgwort;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification;

/**
 * Adds eligible entities to the VG Wort queue.
 */
class EntityQueuer {

  /**
   * @var \Drupal\Core\Config\Config
   */
  private readonly Config $config;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs the EntityQueuer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\vgwort\EntityJobMapper $entityJobMapper
   *   The entity job mapper service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $moduleHandler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $configFactory, private readonly EntityJobMapper $entityJobMapper, ?ModuleHandlerInterface $moduleHandler = NULL) {
    $this->config = $configFactory->get('vgwort.settings');
    // Provide BC so sites do not need to run a container rebuild.
    // @todo remove in a few releases.
    if ($moduleHandler === NULL) {
      $moduleHandler = \Drupal::service('module_handler');
      @trigger_error('Calling ' . __METHOD__ . '() without the $moduleHandler argument is deprecated in vgwort:2.0.0 and will be required in vgwort:2.1.0. See https://www.drupal.org/node/3418306', E_USER_DEPRECATED);
    }
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Adds a registration notification for an entity to the VG wort queue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to notify VG Wort about.
   * @param int|null $delay
   *   (optional) Override the default in processing the created queue item.
   *
   * @return bool
   *   TRUE if the entity is in the queue, FALSE if not.
   */
  public function queueEntity(EntityInterface $entity, ?int $delay = NULL): bool {
    // This service only supports entity types that can be configured in the UI.
    // @see \Drupal\vgwort\Form\SettingsForm::buildForm()
    if (!$entity instanceof EntityPublishedInterface || !$entity instanceof FieldableEntityInterface) {
      return FALSE;
    }

    // Only published entities can be added to the queue.
    if (!$entity->isPublished()) {
      return FALSE;
    }

    // Only entities with a counter ID can be queued.
    if ($entity->vgwort_counter_id->isEmpty()) {
      return FALSE;
    }

    $queue_entity = TRUE;
    $this->moduleHandler->invokeAllWith('vgwort_queue_entity', function (callable $hook) use ($entity, &$queue_entity) {
      // Once an implementation has returned false do not call any other
      // implementation.
      if ($queue_entity) {
        $queue_entity = $hook($entity);
      }
    });

    if (!$queue_entity) {
      // An implementation of hook_vgwort_queue_entity() has returned
      // false.
      return FALSE;
    }

    $queue = Queue::load('vgwort');
    // If there is no queue fail silently. This ensures content can be inserted
    // or updated prior to vgwort_post_update_create_queue() running.
    if (!$queue instanceof Queue) {
      return FALSE;
    }

    // Since we're doing a select, delete and then insert we need to be sure
    // that no other process is doing the same thing at the same time.
    if (!$this->entityJobMapper->lock($entity)) {
      // Another process is already queueing this entity.
      return FALSE;
    }

    // Failed jobs should be added to the queue again.
    $job = $this->entityJobMapper->getJob($entity);
    if ($job && $job->getState() === Job::STATE_FAILURE) {
      $queue_backend = $queue->getBackend();
      // Reset the number of retries. As an entity save should cause the process
      // to start again.
      $job->setNumRetries(-1);
      $queue_backend->retryJob($job, $this->config->get('queue_retry_time'));
      $this->entityJobMapper->lockRelease($entity);
      return TRUE;
    }
    elseif ($job && in_array($job->getState(), [Job::STATE_SUCCESS, Job::STATE_PROCESSING, Job::STATE_QUEUED], TRUE)) {
      $this->entityJobMapper->lockRelease($entity);
      // Nothing to do.
      return TRUE;
    }

    $job = RegistrationNotification::createJob($entity);
    $queue->enqueueJob($job, $delay ?? $this->getDefaultDelay());
    $this->entityJobMapper->addJobToMap($job, $entity->vgwort_counter_id->value);
    $this->entityJobMapper->lockRelease($entity);
    return TRUE;
  }

  /**
   * Gets the default delay before processing a queue item.
   *
   * @return int
   *   The default delay in seconds.
   *
   * @see vgwort.settings:registration_wait_days
   */
  public function getDefaultDelay(): int {
    return $this->config->get('registration_wait_days') * 24 * 60 * 60;
  }

}
