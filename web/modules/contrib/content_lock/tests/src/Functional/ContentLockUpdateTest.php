<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Update tests.
 *
 * @group content_lock
 */
class ContentLockUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    if (file_exists(DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz')) {
      $this->databaseDumpFiles = [
        DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      ];
    }
    else {
      $this->databaseDumpFiles = [
        DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
      ];
    }
    $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/install-content-lock.php';

    if ($this->name() === 'testContentLockTimeout') {
      $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/install-content-lock-timeout.php';
    }
  }

  /**
   * Tests updating Content Lock when Content Lock Timeout is not installed.
   */
  public function testContentLock() {
    $config = $this->config('content_lock.settings')->get();
    $this->assertSame(1, $config['verbose']);
    $this->assertArrayNotHasKey('timeout', $config);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module not installed');

    $this->runUpdates();
    $config = $this->config('content_lock.settings')->get();
    $this->assertTrue($config['verbose']);
    $this->assertNull($config['timeout']);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module not installed');
  }

  /**
   * Tests updating Content Lock when Content Lock Timeout is installed.
   */
  public function testContentLockTimeout() {
    $config = $this->config('content_lock.settings')->get();
    $this->assertArrayNotHasKey('timeout', $config);
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module installed');

    $this->runUpdates();
    $config = $this->config('content_lock.settings')->get();
    $this->assertSame(1800, $config['timeout']);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('content_lock_timeout'), 'Content Lock Timeout module not installed');
  }

}
