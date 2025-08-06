<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Kernel;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests simultaneous edit on test entity.
 *
 * @group content_lock
 */
class ContentLockEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_lock',
    'content_lock_hooks_test',
    'entity_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_mul_changed');
    $this->installEntitySchema('user');
    $this->installSchema('content_lock', 'content_lock');
    $this->installConfig('content_lock');
  }

  /**
   * Tests deleting entities with content locks and form op locking enabled.
   *
   * @covers \Drupal\content_lock\ContentLock\ContentLock::isLockable
   */
  public function testHookContentLockEntityLockable(): void {
    $entity1 = EntityTestMulChanged::create([
      'name' => 'Entity for user without break permission',
    ]);
    $entity1->save();
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = $this->container->get('content_lock');
    $this->assertFalse($lock_service->isLockable($entity1));

    $this->config('content_lock.settings')->set('types.entity_test_mul_changed', ['*'])->save();
    $this->assertTrue($lock_service->isLockable($entity1));
  }

  /**
   * Tests deleting entities with content locks and form op locking enabled.
   *
   * @covers content_lock_entity_access
   */
  public function testContentLockEntityProgrammaticDelete(): void {
    $this->config('content_lock.settings')
      ->set('types.entity_test_mul_changed', ['*'])
      ->set('form_op_lock.entity_test_mul_changed.mode', ContentLockInterface::FORM_OP_MODE_ALLOWLIST)
      ->save();
    $entity1 = EntityTestMulChanged::create([
      'name' => 'Entity for user without break permission',
    ]);
    $entity1->save();
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = $this->container->get('content_lock');
    $this->assertTrue($lock_service->locking($entity1, '*', 1, TRUE));
    $this->assertInstanceOf(\StdClass::class, $lock_service->fetchLock($entity1));

    // Deleting the entity will cause the lock to be released.
    $entity1->delete();

    $this->assertFalse($lock_service->fetchLock($entity1));
  }

}
