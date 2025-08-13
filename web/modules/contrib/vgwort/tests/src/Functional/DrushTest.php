<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Functional;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

// cspell:ignore vgws vgwq

/**
 * Tests the vgwort drush command.
 *
 * @group vgwort
 *
 * @covers \Drupal\vgwort\Commands\VgwortCommands
 */
class DrushTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vgwort', 'node', 'vgwort_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('vgwort.settings')
      ->set('publisher_id', 123456)
      ->set('image_domain', 'http://example.com')
      // Disable VG Wort on all entity types.
      ->set('entity_types', [])
      ->set('legal_rights', [
        'distribution' => TRUE,
        'public_access' => TRUE,
        'reproduction' => TRUE,
        'declaration_of_granting' => TRUE,
        'other_public_communication' => FALSE,
      ])
      ->save();
  }

  public function testSendInvalidEntityId(): void {
    $this->config('vgwort.settings')->set('entity_types', ['node' => []])->save();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('/The entity node:1 does not exist\./');
    $this->drush('vgws', ['node', '1']);
  }

  public function testSendCommand(): void {
    $this->config('vgwort.settings')
      ->set('entity_types', ['node' => []])
      // This test should not send anything, but just in case send to the test
      // API.
      ->set('test_mode', TRUE)
      ->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page 1',
    ]);
    $node->save();
    \Drupal::state()->set('vgwort_test_vgwort_enable_for_entity', ['1']);
    // This test ensures that RegistrationNotification::process() is called with
    // the correct entity. Other test coverage ensure that that function works
    // correctly if the entity is actually valid.
    $this->drush('vgws', ['node', '1'], [], NULL, NULL, 1);
    $this->assertStringStartsWith('[ERROR] Sending failed: The entity node:1 failed:', $this->getOutput());
  }

  public function testInvalidEntityType(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('/Entity type "node" is not configured for VG Wort/');
    $this->drush('vgwq', ['node']);
  }

  public function testInvalidBatchSize(): void {
    $this->config('vgwort.settings')->set('entity_types', ['node' => []])->save();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('/The batch-size "-1" is not valid. It must be an integer and greater than 0./');
    $this->drush('vgwq', ['node'], ['batch-size' => -1]);
  }

  public function testQueueCommand(): void {
    $this->createContentType(['type' => 'page']);
    // Create 80 nodes.
    for ($i = 1; $i <= 80; $i++) {
      $node = Node::create([
        'type' => 'page',
        'title' => 'Page ' . $i,
      ]);
      // Set each node to be created a number of days ago, starting at 80.
      $node->setChangedTime(time() - ((80 - $i) * 60 * 60 * 24));
      $node->save();
    }
    \Drupal::state()->set('vgwort_test_vgwort_enable_for_entity', ['70']);
    $this->config('vgwort.settings')->set('entity_types', ['node' => []])->save();
    $this->drush('vgwort:queue', ['node'], ['batch-size' => 20]);
    $this->assertMatchesRegularExpression('/Processed 80 out of 80 entities, added 79 to the queue/', $this->getErrorOutput());

    /** @var \Drupal\advancedqueue\Entity\Queue $queue */
    $queue = Queue::load('vgwort');
    /** @var \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database $queue_backend */
    $queue_backend = $queue->getBackend();
    $this->assertSame(79, (int) $queue_backend->countJobs()['queued']);

    $job = $queue_backend->loadJob('1');
    $this->assertLessThanOrEqual(time(), $job->getAvailableTime());
    $job = $queue_backend->loadJob('66');
    $this->assertLessThanOrEqual(time(), $job->getAvailableTime());
    // The last 13 nodes should be available in the future.
    $job = $queue_backend->loadJob('67');
    $this->assertGreaterThan(time(), $job->getAvailableTime());
    $job = $queue_backend->loadJob('79');
    $this->assertGreaterThan(time(), $job->getAvailableTime());
    $this->assertSame('80', $job->getPayload()['entity_id']);

    // Ensure no jobs are actually processed.
    $queue_backend->deleteQueue();
  }

}
