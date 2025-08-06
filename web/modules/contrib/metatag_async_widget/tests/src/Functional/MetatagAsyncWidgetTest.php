<?php

declare(strict_types=1);

namespace Drupal\Tests\metatag_async_widget\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Ensures that metatag_async_widget works with JavaScript disabled.
 *
 * @group metatag_async_widget
 */
class MetatagAsyncWidgetTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'field_ui',
    'metatag_async_widget',
    'node',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Setup basic environment.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    // Set up a content type.
    $name = $this->randomMachineName() . ' ' . $this->randomMachineName();
    $this->drupalCreateContentType(['type' => 'metatag_node', 'name' => $name]);

    // Create and login user.
    $this->drupalLogin($this->drupalCreateUser([
      'administer content types',
      'administer nodes',
      'administer node fields',
      'bypass node access',
      'administer node form display',
    ]));
  }

  /**
   * Tests the Metatag Async Widget.
   */
  public function testMetatagAsyncWidget(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add a Metatag field to the content type.
    $this->drupalGet('admin/structure/types/manage/metatag_node/fields/add-field');
    $assert->fieldExists('new_storage_type')->setValue('metatag');
    if ((float) \Drupal::VERSION < 10.2) {
      $page->pressButton('Save and continue');
    }
    else {
      $page->pressButton('Continue');
    }
    $assert->fieldExists('label')->setValue('Meta tags');
    $assert->fieldExists('field_name')->setValue('meta_tags');
    if ((float) \Drupal::VERSION < 10.2) {
      $page->pressButton('Save and continue');
      $page->pressButton('Save field settings');
    }
    else {
      $page->pressButton('Continue');
    }
    $page->pressButton('Save settings');

    // Set the form display.
    $this->drupalGet('admin/structure/types/manage/metatag_node/form-display');
    $assert->fieldExists('edit-fields-field-meta-tags-type')->setValue('metatag_async_widget_firehose');
    $page->pressButton('Save');
    $assert->pageTextContains('Your settings have been saved.');

    // Create a node.
    $this->drupalGet('node/add/metatag_node');
    $assert->fieldExists('edit-title-0-value')->setValue($this->getRandomGenerator()->sentences(4));
    $assert->fieldNotExists('edit-field-meta-tags-0-basic-abstract');
    $page->pressButton('Customize meta tags');
    $assert->pageTextContains('Configure the meta tags below.');
    $abstract = $this->getRandomGenerator()->sentences(10);
    $assert->fieldExists('edit-field-meta-tags-0-basic-abstract')->setValue($abstract);
    $page->pressButton('Save');

    // Edit the node and ensure the abstract is saved and previewing does not
    // cause errors.
    $page->clickLink('Edit');
    $page->pressButton('Preview');
    $page->clickLink('Back to content editing');
    $assert->fieldNotExists('edit-field-meta-tags-0-basic-abstract');
    $page->pressButton('Customize meta tags');
    $assert->fieldValueEquals('edit-field-meta-tags-0-basic-abstract', $abstract);
  }

}
