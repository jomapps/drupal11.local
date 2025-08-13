<?php

namespace Drupal\vgwort;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsDeletingJobsInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsLoadingJobsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maintains the maps between entities and jobs.
 *
 * The map allows the VG Wort module to know whether an entity has been
 * successfully posted to VG Wort even after the queue has been cleaned.
 */
final class EntityJobMapper {

  /**
   * The table name for the map.
   */
  public const TABLE = 'vgwort_entity_registration';

  public function __construct(
    protected readonly Connection $connection,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'lock')]
    protected readonly LockBackendInterface $lock,
  ) {
  }

  /**
   * Marks a job as successful in the map.
   *
   * This method should only be called after to sending the entity to VG Wort.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job to insert or update.
   * @param int $success_timestamp
   *   The timestamp to set success_timestamp to.
   * @param string $counter_id
   *   The counter ID sent to VG Wort.
   * @param int|null $revision_id
   *   The revision ID for the entity that was sent to VGWort. Must be provided
   *   if the entity type is revisionable and the success_timestamp is being
   *   set.
   *
   * @return $this
   *   The entity job mapper.
   */
  public function markSuccessful(Job $job, int $success_timestamp, string $counter_id, ?int $revision_id): self {
    [$entity_type, $entity_id] = RegistrationNotification::getEntityInfoFromJob($job);

    // Ensure the revision ID is provided for revisionable entities.
    if ($revision_id === NULL && $this->entityTypeManager->getDefinition($entity_type)->isRevisionable()) {
      throw new \LogicException(sprintf('The revision ID must be supplied when marking %s entities as successfully sent to VG Wort', $entity_type));
    }

    // If the job has been created outside of the advanced queue module it will
    // not have an ID. In this case assign an ID of 0 so we can still save the
    // map although no job with that ID will ever exist in advanced queue. This
    // allows drush to send an individual entity to VG Wort outside of the full
    // queue system.
    $job_id = $job->getId() === '' ? 0 : $job->getId();

    $this->connection->merge(self::TABLE)
      ->keys(['entity_type' => $entity_type, 'entity_id' => $entity_id, 'counter_id' => $counter_id])
      ->fields(['revision_id' => $revision_id, 'job_id' => $job_id, 'success_timestamp' => $success_timestamp])
      ->execute();
    return $this;
  }

  /**
   * Adds a job to the map.
   *
   * This method should only be called prior to sending the entity to VG Wort.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job to insert or update.
   * @param string $counter_id
   *   The counter ID for the entity.
   *
   * @return $this
   *   The entity job mapper.
   */
  public function addJobToMap(Job $job, string $counter_id) {
    [$entity_type, $entity_id] = RegistrationNotification::getEntityInfoFromJob($job);

    // Use a transaction to keep everything consistent. It will be committed as
    // soon as $transaction is out of scope.
    // @phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $transaction = $this->connection->startTransaction();

    // Find rows for the entity that are still to be processed by the queue.
    // If they are rows:
    // - Remove the queue item if possible.
    // - Delete them from the map.
    // The following code is optimized to do this in the minimum queries and
    // amount of logic possible as this is run as part of saving an entity.
    $result = $this->connection->select(self::TABLE, 'map')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->isNull('success_timestamp')
      ->fields('map', ['job_id', 'counter_id'])
      ->execute();

    $queue_backend = $do_delete = FALSE;
    foreach ($result as $row) {
      if (!$do_delete) {
        $do_delete = TRUE;
        $queue = Queue::load('vgwort');
        $queue_backend = $queue->getBackend();
      }
      // Intentional loose comparison because database return types are not
      // trustworthy. If the passed in job matches job in the map do not delete
      // the job. This is a check for robustness and safety and should never
      // occur.
      if ($row->job_id != $job->getId() && $queue_backend instanceof SupportsDeletingJobsInterface) {
        $queue_backend->deleteJob($row->job_id);
      }
    }

    if ($do_delete) {
      // Remove all unprocessed rows for the entity.
      $this->connection->delete(self::TABLE)
        ->condition('entity_type', $entity_type)
        ->condition('entity_id', $entity_id)
        ->isNull('success_timestamp')
        ->execute();
    }

    // Insert a row into the map for the entity.
    $this->connection->insert(self::TABLE)
      ->fields([
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'counter_id' => $counter_id,
        'job_id' => $job->getId(),
      ])
      ->execute();

    // Commit the transaction.
    // @phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
    $transaction = NULL;

    return $this;
  }

  /**
   * Creates a lock held for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to release the lock for.
   *
   * @return bool
   *   TRUE if the lock is created, FALSE if not.
   */
  public function lock(EntityInterface $entity): bool {
    $lock_name = $this->getLockName($entity);
    // Since we're doing a select, delete and then insert we need to be sure
    // that no other process is doing the same thing at the same time.
    if (!$this->lock->acquire($lock_name)) {
      $this->lock->wait($lock_name);
      return $this->lock->acquire($lock_name);
    }
    return TRUE;
  }

  /**
   * Releases a lock held for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to release the lock for.
   */
  public function lockRelease(EntityInterface $entity): void {
    $lock_name = $this->getLockName($entity);
    $this->lock->release($lock_name);
  }

  /**
   * Creates a lock name for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to create a lock name for.
   *
   * @return string
   *   The lock name.
   */
  private function getLockName(EntityInterface $entity): string {
    return "EntityJobMapper::lock:{$entity->getEntityTypeId()}:{$entity->id()}:{$entity->vgwort_counter_id->value}";
  }

  /**
   * Gets a Job object for entity.
   *
   * If the entity has a job on the queue the job object return will be loaded
   * from the queue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get a job for.
   *
   * @return \Drupal\advancedqueue\Job|null
   *   The job object. NULL if a new job needs to be queued.
   */
  public function getJob(EntityInterface $entity): ?Job {
    $result = $this->connection->select(self::TABLE, 'map')
      ->fields('map', ['entity_type', 'entity_id', 'job_id', 'success_timestamp'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('counter_id', $entity->vgwort_counter_id->value)
      ->execute()
      ->fetchObject();

    if (empty($result)) {
      return NULL;
    }

    $queue = Queue::load('vgwort');
    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof SupportsLoadingJobsInterface) {
      try {
        return $queue_backend->loadJob($result->job_id);
      }
      catch (\InvalidArgumentException) {
        // Fall through to the last return.
      }
    }

    if ((int) $result->success_timestamp > 0) {
      $job = RegistrationNotification::createJob($entity);
      $job->setId($result->job_id);
      $job->setState(Job::STATE_SUCCESS);
      $job->setProcessedTime($result->success_timestamp);
      return $job;
    }

    return NULL;
  }

  /**
   * Gets the revision IDs and counter IDs sent to VG Wort for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return string[]
   *   A list of counter IDs sent to VG Wort for the entity keyed by the
   *   revision ID.
   */
  public function getRevisionsSent(EntityInterface $entity): array {
    $result = $this->connection->select(self::TABLE, 'map')
      ->fields('map', ['revision_id', 'counter_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->isNotNull('success_timestamp')
      ->orderBy('revision_id')
      ->execute();
    $revisions = [];
    foreach ($result as $row) {
      $revisions[(int) $row->revision_id] = $row->counter_id;
    }
    return $revisions;
  }

  /**
   * Cleans up when an entity is deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove from the map and queue.
   *
   * @return $this
   *   The entity job mapper.
   */
  public function removeEntity(EntityInterface $entity): self {
    $result = $this->connection->select(self::TABLE, 'map')
      ->fields('map', ['job_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute()
      ->fetchObject();

    if (empty($result)) {
      return $this;
    }

    $queue = Queue::load('vgwort');
    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof SupportsDeletingJobsInterface) {
      $queue_backend->deleteJob($result->job_id);
    }

    $this->connection->delete(self::TABLE)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();
    return $this;
  }

  /**
   * Gets the schema definition for the map table.
   *
   * If this specification changes vgwort_update_20001() will need to be
   * updated to have the original spec and a new update function should be
   * added.
   *
   * @return array
   *   The schema definition.
   *
   * @see vgwort_update_20001()
   */
  public static function schemaDefinition(): array {
    return [
      'description' => 'Stores map from entity to job ID for registration notification.',
      'fields' => [
        'entity_type' => [
          'type' => 'varchar_ascii',
          'length' => EntityTypeInterface::ID_MAX_LENGTH,
          'not null' => TRUE,
          'description' => 'The entity type.',
        ],
        'entity_id' => [
          // Support string entity IDs
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The entity ID.',
        ],
        'counter_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The counter ID',
        ],
        'revision_id' => [
          'type' => 'int',
          'not null' => FALSE,
          'default' => NULL,
          'description' => 'The entity revision ID of the version sent to VG Wort (if available).',
        ],
        'job_id' => [
          'type' => 'int',
          'size' => 'big',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'The job ID.',
        ],
        'success_timestamp' => [
          'description' => 'The Unix timestamp when this entity was successfully post to VG Wort.',
          'type' => 'int',
          'size' => 'big',
          'unsigned' => TRUE,
        ],
      ],
      'primary key' => ['entity_type', 'entity_id', 'counter_id'],
      'indexes' => [
        'job_id' => ['job_id'],
      ],
    ];
  }

}
