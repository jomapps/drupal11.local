<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_lock\Tools\LogoutTrait;

/**
 * Block tests.
 *
 * @group content_lock
 */
class ContentLockBlockTest extends BrowserTestBase {
  use LogoutTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'content_lock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (floatval(\Drupal::VERSION) < 10) {
      $this->markTestSkipped("This test fails on Drupal 9");
    }
    parent::setUp();
  }

  /**
   * Creates a custom block.
   *
   * @param bool|string $title
   *   (optional) Title of block. When no value is given uses a random name.
   *   Defaults to FALSE.
   * @param string $bundle
   *   (optional) Bundle name. Defaults to 'basic'.
   * @param bool $save
   *   (optional) Whether to save the block. Defaults to TRUE.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created custom block.
   */
  protected function createBlockContent(string|false $title = FALSE, string $bundle = 'basic', bool $save = TRUE): BlockContent {
    $title = $title ?: $this->randomMachineName();
    $block_content = BlockContent::create([
      'info' => $title,
      'type' => $bundle,
      'langcode' => 'en',
    ]);
    if ($block_content && $save === TRUE) {
      $block_content->save();
    }
    return $block_content;
  }

  /**
   * Creates a custom block type (bundle).
   *
   * @param string $label
   *   The block type label.
   * @param bool $create_body
   *   Whether to create the body field.
   *
   * @return \Drupal\block_content\Entity\BlockContentType
   *   Created custom block type.
   */
  protected function createBlockContentType(string $label, bool $create_body = FALSE): BlockContentType {
    $bundle = BlockContentType::create([
      'id' => $label,
      'label' => $label,
      'revision' => FALSE,
    ]);
    $bundle->save();
    if ($create_body) {
      block_content_add_body_field($bundle->id());
    }
    return $bundle;
  }

  /**
   * Test simultaneous edit on block.
   */
  public function testContentLockBlock(): void {

    // Create block.
    $this->createBlockContentType('basic', TRUE);
    $block1 = $this->createBlockContent('Block 1');

    $admin = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'administer content lock',
      'view the administration theme',
    ]);

    $user1 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'access content',
      'view the administration theme',
    ]);
    $user2 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'break content lock',
      'view the administration theme',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'block_content[bundles][basic]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // We lock block1.
    $this->drupalLogin($user1);
    // Edit a node without saving.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit block1.
    $this->drupalLogin($user2);
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains("This content is being edited by the user {$user1->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We save block1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/admin/content/block/' . $block1->id());
    $this->submitForm([], 'Save');

    // We lock block1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit block1.
    $this->drupalLogin($user1);
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains("This content is being edited by the user {$user2->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkNotExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock block1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('has been updated.');
  }

  /**
   * Tests deleting blocks with content locks.
   *
   * @covers content_lock_entity_access
   */
  public function testContentLockBlockDeleteAccess(): void {
    // Create two test blocks.
    $this->createBlockContentType('basic', TRUE);
    $block1 = $this->createBlockContent('Block for user without break permission');
    $block2 = $this->createBlockContent('Block for user with break permission');

    $admin = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'administer content lock',
      'delete any basic block content',
      'view the administration theme',
    ]);

    // User without break lock permission.
    $user1 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'delete any basic block content',
      'access content',
      'view the administration theme',
    ]);

    // User with break lock permission.
    $user2 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'delete any basic block content',
      'access content',
      'break content lock',
      'view the administration theme',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'block_content[bundles][basic]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Lock both blocks.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $this->drupalGet("/admin/content/block/{$block2->id()}");

    // Test user1 (without break lock permission) cannot delete the locked
    // block.
    $this->drupalLogin($user1);
    $this->drupalGet("/admin/content/block/{$block1->id()}/delete");
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(403);

    // Test user2 (with break lock permission) can delete the locked block.
    $this->drupalLogin($user2);
    $this->drupalGet("/admin/content/block/{$block2->id()}/delete");
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Are you sure you want to delete');
    // In order to delete the user must break the lock. Breaking the lock is
    // tested above.
    $assert_session->pageTextContains('Click here to Break lock');
  }

}
