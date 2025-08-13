<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Tests\scheduler_content_moderation_integration\Kernel\SchedulerContentModerationTestBase;
use Drupal\Tests\vgwort\Traits\KernelSetupTrait;

/**
 * Tests the vgwort entity queue with scheduler and content_moderation..
 *
 * @group vgwort
 */
class SchedulerIntegrationTest extends SchedulerContentModerationTestBase {
  use KernelSetupTrait;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['advancedqueue', 'vgwort'];

  private const JOB_COUNT = [
    Job::STATE_QUEUED => 0,
    Job::STATE_PROCESSING => 0,
    Job::STATE_SUCCESS => 0,
    Job::STATE_FAILURE => 0,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installVgWort();
  }

  /**
   * Tests moderated entity publish scheduling with VGWort queueing.
   */
  public function testIntegration(): void {
    $queue = Queue::load('vgwort');
    $this->assertInstanceOf(Queue::class, $queue);

    $storage = \Drupal::service('entity_type.manager')->getStorage('node');

    $entity = $this->createEntity('node', 'example', [
      'title' => 'Published title',
      'moderation_state' => 'draft',
      'publish_on' => strtotime('yesterday'),
      'publish_state' => 'published',
    ]);
    $entity_id = $entity->id();

    // Make sure entity is unpublished.
    $this->assertInstanceOf(EntityPublishedInterface::class, $entity);
    $this->assertFalse($entity->isPublished());

    // Make sure the entity is not queued.
    $this->assertSame(self::JOB_COUNT, $queue->getBackend()->countJobs());

    $this->container->get('cron')->run();

    $entity = $storage->loadRevision($storage->getLatestRevisionId($entity_id));

    // Assert entity is now published.
    $this->assertInstanceOf(EntityPublishedInterface::class, $entity);
    $this->assertTrue($entity->isPublished());
    $this->assertEquals('published', $entity->moderation_state->value);

    // Assert entity is now queued.
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_QUEUED] = '1';
    $this->assertSame($jobs, $queue->getBackend()->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('1', $job->getId());
  }

}
