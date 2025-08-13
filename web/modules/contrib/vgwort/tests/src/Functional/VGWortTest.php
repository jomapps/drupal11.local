<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Functional;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\vgwort\Api\Participant;

/**
 * Tests the VG Wort module.
 *
 * @group vgwort
 */
class VGWortTest extends BrowserTestBase {
  use AssertPageCacheContextsAndTagsTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'taxonomy', 'vgwort', 'field_ui', 'page_cache', 'block'];

  /**
   * @see \Drupal\vgwort\Form\SettingsForm::buildForm()
   */
  private const TEST_MODE_MESSAGE = 'The test mode is enabled. The 1x1 pixel will be added as HTML comment to the selected entity_types.';

  /**
   * @see \Drupal\vgwort\Form\SettingsForm::buildForm()
   */
  public const LEGAL_MESSAGE = 'The right of reproduction (§ 16 UrhG), right of distribution (§ 17 UrhG), right of public access (§ 19a UrhG) and the declaration of granting rights must be confirmed.';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->createContentType(['type' => 'article', 'display_submitted' => FALSE, 'new_revision' => TRUE]);
    $this->enablePageCaching();
    $this->config('vgwort.settings')->set('registration_wait_days', 0)->save();
  }

  /**
   * Tests the normal operation of the module.
   */
  public function testVgWortHappyPath(): void {
    // Add an VGWort author and translator fields to nodes.
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'vgwort_authors',
      'entity_type' => 'node',
      'type' => 'vgwort_participant_info',
      'cardinality' => 4,
    ]);
    $fieldStorage->save();
    $author_field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
    ]);
    $author_field->save();
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'vgwort_translators',
      'entity_type' => 'node',
      'type' => 'vgwort_participant_info',
      'cardinality' => 2,
    ]);
    $fieldStorage->save();
    $translator_field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
      'settings' => [
        'involvement' => Participant::TRANSLATOR,
      ],
    ]);
    $translator_field->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('vgwort_authors', ['type' => 'vgwort_participant_info'])
      ->setComponent('vgwort_translators', ['type' => 'vgwort_participant_info'])
      ->save();

    // Test that installing the module has created the suffix field on nodes.
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));

    // Login as an admin user to set up VG Wort, use the field UI and create
    // content.
    $admin_user = $this->createUser([], 'site admin', TRUE);
    $this->drupalLogin($admin_user);

    // Module settings.
    $this->drupalGet('admin/config');
    $this->clickLink('VG Wort settings');
    $this->assertSession()->pageTextContains(self::LEGAL_MESSAGE);
    $this->assertSession()->pageTextNotContains(self::TEST_MODE_MESSAGE);
    $this->assertSession()->fieldValueEquals('username', '');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSession()->fieldValueEquals('publisher_id', '');
    $this->assertSession()->fieldValueEquals('domain', '');
    $this->assertSession()->fieldExists('username')->setValue('aaaBBB');
    $this->assertSession()->fieldExists('password')->setValue('t3st');
    $this->assertSession()->fieldExists('publisher_id')->setValue('1234567');
    $this->assertSession()->fieldExists('domain')->setValue('example.com');
    // Ensure only publishable entity types with canonical links are listed.
    $options = $this->assertSession()->elementExists('css', '#edit-entity-types')->findAll('css', 'input');
    $this->assertCount(2, $options);
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[node][enabled]')->isChecked());
    $this->assertFalse($this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->isChecked());
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->check();
    $this->assertSession()->fieldExists('entity_types[node][view_mode]')->selectOption('search_index');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    // The legal fields cause a message to be set.
    $this->assertSession()->pageTextContains(self::LEGAL_MESSAGE);
    $this->assertSession()->fieldExists('legal_rights[distribution]')->check();
    $this->assertSession()->fieldExists('legal_rights[public_access]')->check();
    $this->assertSession()->fieldExists('legal_rights[reproduction]')->check();
    $this->assertSession()->fieldExists('legal_rights[declaration_of_granting]')->check();
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextNotContains(self::LEGAL_MESSAGE);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('username', 'aaaBBB');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSame('t3st', $this->config('vgwort.settings')->get('password'));
    $this->assertSession()->fieldValueEquals('publisher_id', '1234567');
    $this->assertSession()->fieldValueEquals('domain', 'example.com');
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[node][enabled]')->isChecked());
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->isChecked());
    $this->assertSame('search_index', $this->assertSession()->fieldExists('entity_types[node][view_mode]')->getValue());

    // Ensure saving without checking the "delete password" field does not change
    // the password.
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSame('t3st', $this->config('vgwort.settings')->get('password'));
    // Ensure password can be changed.
    $this->assertSession()->buttonExists('Reset')->press();
    $this->refreshVariables();
    $this->assertSame('', $this->config('vgwort.settings')->get('password'));
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSession()->fieldExists('password')->setValue('t3ster');
    $this->submitForm([], 'Save configuration');
    $this->assertSame('t3ster', $this->config('vgwort.settings')->get('password'));

    // Test counter domain and publisher ID validation.
    $this->assertSession()->fieldExists('publisher_id')->setValue('12345678');
    $this->assertSession()->fieldExists('domain')->setValue('http://example.com');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('Publisher ID (Karteinummer) cannot be longer than 7 characters but is currently 8 characters long.');
    $this->assertSession()->pageTextContains('Counter domain must be a valid counter domain. For example, vg07.met.vgwort.de.');
    $this->assertSession()->fieldExists('publisher_id')->setValue('a test');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('Publisher ID (Karteinummer) must be a numeric ID between 10 and 9999999.');

    // Field UI.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->fieldValueEquals('fields[vgwort_counter_id][region]', 'hidden');
    $this->assertSession()->fieldExists('fields[vgwort_counter_id][region]')->setValue('content');
    $this->submitForm([], 'Save');

    // Create a node and ensure the image is displayed as expected.
    $this->drupalGet('node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Test node');
    $this->submitForm([], 'Save');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'In order to be counted by VG Wort there must be at least one author.');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'The minimum numbers of characters in order to be counted by VG Wort is 1800. The current count is 60.');

    $this->assertSession()->addressEquals('node/1');
    $element = $this->assertSession()->elementExists('css', 'main img');
    $src = $element->getAttribute('src');
    $this->assertMatchesRegularExpression('#^' . preg_quote('//example.com/na/vgzm.1234567-', '#') . '#', $src);
    preg_match('#^' . preg_quote('//example.com/na/vgzm.1234567-', '#') . '(.*)$#', $src, $matches);
    $node = $this->drupalGetNodeByTitle('Test node', TRUE);
    $this->assertSame($matches[1], $node->uuid());
    $this->assertSame('1', $node->id());
    $this->assertSame('Test node', $node->label());
    $this->assertSame('vgzm.1234567-' . $matches[1], $node->vgwort_counter_id->value);

    // Test the warning messages and VG Wort info tab.
    $this->clickLink('Edit');
    $this->submitForm(['body[0][value]' => 'Some more content but not enough'], 'Save');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'In order to be counted by VG Wort there must be at least one author.');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'The minimum numbers of characters in order to be counted by VG Wort is 1800. The current count is 113.');
    $this->clickLink('VG Wort');
    $this->assertSession()->addressEquals('node/1/vgwort');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'In order to be counted by VG Wort there must be at least one author.');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'The minimum numbers of characters in order to be counted by VG Wort is 1800. The current count is 113.');
    $node = $this->drupalGetNodeByTitle('Test node', TRUE);
    $this->assertSession()->elementTextContains('css', 'main table', 'Counter ID ' . $node->vgwort_counter_id->value);
    $this->assertSession()->elementTextContains('css', 'main table', 'Queue status Queued');
    $this->assertSession()->linkExists('Administer queue');
    $this->clickLink('Administer queue');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('admin/config/system/queues/jobs/vgwort');
    $this->getSession()->getDriver()->back();
    $this->clickLink('Edit');
    $this->submitForm(['body[0][value]' => 'Some random test content for us.' . $this->getRandomGenerator()->paragraphs(30)], 'Save');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'Resolve all issues below to register this article with VG Wort.');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'In order to be counted by VG Wort there must be at least one author.');
    $this->assertSession()->elementTextNotContains('css', '[data-drupal-messages]', 'The minimum numbers of characters in order to be counted by VG Wort is 1800.');

    $this->clickLink('Edit');
    $this->submitForm([
      'vgwort_authors[0][card_number]' => '1231233',
      'vgwort_authors[0][firstname]' => 'John',
      'vgwort_authors[0][surname]' => 'Smith',
      'vgwort_translators[0][card_number]' => '1231234',
      'vgwort_translators[0][firstname]' => 'Bobby',
      'vgwort_translators[0][surname]' => 'Jones',
      'vgwort_translators[1][card_number]' => '1231235',
      'vgwort_translators[1][firstname]' => 'Sally',
      'vgwort_translators[1][surname]' => 'Field',
    ], 'Save');
    $this->assertSession()->elementTextContains('css', '[data-drupal-messages]', 'article Test node has been updated.');
    $this->assertSession()->elementTextNotContains('css', '[data-drupal-messages]', 'Resolve all issues below to register this article with VG Wort.');
    $this->assertSession()->elementTextNotContains('css', '[data-drupal-messages]', 'In order to be counted by VG Wort there must be at least one author.');
    $this->clickLink('VG Wort');
    // There are no warnings anymore.
    $this->assertSession()->elementNotExists('css', '[data-drupal-messages]');
    $this->assertSession()->elementTextContains('css', 'main table', 'Author John Smith');
    $this->assertSession()->elementTextContains('css', 'main table', 'Translators Sally FieldBobby Jones');

    $this->clickLink('View text sent');
    $this->assertSession()->pageTextContains('Some random test content for us.');
    $this->clickLink('Back to VG Wort overview');
    $this->assertSession()->elementTextContains('css', 'main table', 'Counter ID ' . $node->vgwort_counter_id->value);

    // Pretend to process the queue.
    $queue = Queue::load('vgwort');
    $queue_backend = $queue->getBackend();
    $job = $queue_backend->claimJob();

    $this->getSession()->getDriver()->reload();
    $this->assertSession()->elementTextContains('css', 'main table', 'Queue status Processing');

    $job->setState(Job::STATE_SUCCESS);
    $queue_backend->onSuccess($job);
    $node = $this->drupalGetNodeByTitle('Test node', TRUE);
    \Drupal::service('vgwort.entity_job_mapper')->markSuccessful($job, $job->getProcessedTime(), $node->vgwort_counter_id->value, (int) $node->getRevisionId());

    $this->getSession()->getDriver()->reload();
    $this->assertSession()->elementTextContains('css', 'main table', 'Queue status Success');
    $this->assertSession()->elementTextContains('css', 'main table', 'Revision sent 4');

    $this->clickLink('Edit');
    $this->submitForm([
      'vgwort_authors[1][card_number]' => '2451343',
      'vgwort_authors[1][firstname]' => 'Fiona',
      'vgwort_authors[1][surname]' => 'Apple',
      'vgwort_translators[0][card_number]' => '',
      'vgwort_translators[0][firstname]' => '',
      'vgwort_translators[0][surname]' => '',
      'body[0][value]' => 'Some different random test content for us.' . $this->getRandomGenerator()->paragraphs(30),
    ], 'Save');

    $this->clickLink('VG Wort');
    $this->assertSession()->elementTextContains('css', 'main table', 'Author John Smith');
    $this->assertSession()->elementTextContains('css', 'main table', 'Translators Sally FieldBobby Jones');
    $this->assertSession()->elementTextContains('css', 'main table tr:nth-child(2) > td:nth-child(2)', 'This has changed since being sent. It is not possible to update this information on VG Wort.');
    $this->assertSession()->elementTextContains('css', 'main table tr:nth-child(2) > td:nth-child(2)', 'The current value is: Fiona AppleJohn Smith');
    $this->assertSession()->elementTextContains('css', 'main table tr:nth-child(3) > td:nth-child(2)', 'This has changed since being sent. It is not possible to update this information on VG Wort.');
    $this->assertSession()->elementTextContains('css', 'main table tr:nth-child(3) > td:nth-child(2)', 'The current value is: Sally Field');

    // Ensure that the text is the one at the time of sending - not how it is
    // now.
    $this->clickLink('View text sent');
    $this->assertSession()->pageTextContains('Some random test content for us.');
    $this->assertSession()->pageTextNotContains('Some different random test content for us.');

    $this->drupalLogout();
    // Ensure access is denied to the VG Wort info tab.
    $this->drupalGet('node/1/vgwort');
    $this->assertSession()->statusCodeEquals(403);

    // Ensure that loading a node from the cache still propagates the cache tag
    // added by vgwort_node_storage_load().
    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $this->assertContains('config:vgwort.settings', $this->getCacheHeaderValues('X-Drupal-Cache-Tags'));
    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    // Change the settings and ensure the node is cleared from cache.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/vgwort');
    $this->assertSession()->fieldExists('publisher_id')->setValue('8765432');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalGet('node/1');
    $element = $this->assertSession()->elementExists('css', 'main img');
    $src = $element->getAttribute('src');
    $this->assertMatchesRegularExpression('#^' . preg_quote('//example.com/na/vgzm.8765432-', '#') . '#', $src);

    // Ensure the page cache has been cleared and is still cached correctly.
    $this->drupalLogout();
    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $element = $this->assertSession()->elementExists('css', 'main img');
    $src = $element->getAttribute('src');
    $this->assertMatchesRegularExpression('#^' . preg_quote('//example.com/na/vgzm.8765432-', '#') . '#', $src);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
    $this->assertContains('config:vgwort.settings', $this->getCacheHeaderValues('X-Drupal-Cache-Tags'));

    // Test the test mode.
    $settings['config']['vgwort.settings']['test_mode'] = (object) [
      'value' => TRUE,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildAll();
    $this->drupalGet('node/1');
    $this->assertSession()->elementNotExists('css', 'main img');
    $this->assertSession()->responseContains('<!-- <img src="//example.com/na/vgzm.8765432-');
    $this->assertSession()->pageTextNotContains('<!-- <img src="//example.com/na/vgzm.8765432-');
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config');
    $this->clickLink('VG Wort settings');
    $this->assertSession()->pageTextContains(self::TEST_MODE_MESSAGE);

    // Ensure the module can be uninstalled without everything breaking.
    $author_field->delete();
    $translator_field->delete();
    field_purge_batch(10);
    $this->drupalGet('admin/modules/uninstall');
    $this->assertSession()->fieldExists('uninstall[vgwort]')->check();
    $this->submitForm([], 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('uninstall[vgwort]');
    $this->drupalLogout();

    // Test that uninstalling the module has removed the suffix field on nodes.
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));

    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', 'main img');

    // Install the module again to ensure it can be.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/modules');
    $this->assertSession()->fieldExists('modules[vgwort][enable]')->check();
    $this->submitForm([], 'Install');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Module VG Wort has been installed.');

    // Test that reinstalling the module has created the suffix field on nodes.
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));
  }

  /**
   * Tests adding a reference to participants.
   */
  public function testAddParticipantsReference(): void {
    $this->createEntityReferenceField('node', 'article', 'field_participants', 'Participants', 'user');
    _vgwort_add_entity_reference_to_participant_map('node', 'field_participants');

    $config = $this->config('vgwort.settings');
    $this->assertSame(['field_participants'], $config->get('entity_types.node.fields'));
    $this->assertSame('full', $config->get('entity_types.node.view_mode'));

    // Ensure that using the UI does not overwrite this information.
    // Module settings.
    $this->drupalLogin($this->createUser([], 'site admin', TRUE));
    $this->drupalGet('admin/config/system/vgwort');
    $this->assertSession()->fieldExists('username')->setValue('aaaBBB');
    $this->assertSession()->fieldExists('password')->setValue('t3st');
    $this->assertSession()->fieldExists('publisher_id')->setValue('1234567');
    $this->assertSession()->fieldExists('domain')->setValue('example.com');
    // Ensure only publishable entity types with canonical links are listed.
    $options = $this->assertSession()->elementExists('css', '#edit-entity-types')->findAll('css', 'input');
    $this->assertCount(2, $options);
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[node][enabled]')->isChecked());
    $this->assertFalse($this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->isChecked());
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->check();
    $this->assertSession()->fieldExists('entity_types[node][view_mode]')->selectOption('search_index');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('vgwort.settings');
    $this->assertSame(['field_participants'], $config->get('entity_types.node.fields'));
    $this->assertSame('search_index', $config->get('entity_types.node.view_mode'));
  }

  /**
   * Tests enabling and disabling on entity types.
   */
  public function testSuffixBaseField(): void {
    // Initial state.
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('taxonomy_term_field_data', 'vgwort_counter_suffix'));
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));

    $this->drupalLogin($this->createUser([], 'site admin', TRUE));
    // Enable on taxonomy
    $this->drupalGet('admin/config/system/vgwort');
    $this->assertSession()->fieldExists('username')->setValue('aaaBBB');
    $this->assertSession()->fieldExists('password')->setValue('t3st');
    $this->assertSession()->fieldExists('publisher_id')->setValue('1234567');
    $this->assertSession()->fieldExists('domain')->setValue('example.com');
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->check();
    $this->submitForm([], 'Save configuration');
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('taxonomy_term_field_data', 'vgwort_counter_suffix'));
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));

    // Remove taxonomy.
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->uncheck();
    $this->submitForm([], 'Save configuration');
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('taxonomy_term_field_data', 'vgwort_counter_suffix'));
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));

    // Remove node and add taxonomy.
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->check();
    $this->assertSession()->fieldExists('entity_types[node][enabled]')->uncheck();
    $this->submitForm([], 'Save configuration');
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('taxonomy_term_field_data', 'vgwort_counter_suffix'));
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));

    // Remove taxonomy.
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->uncheck();
    $this->submitForm([], 'Save configuration');
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('taxonomy_term_field_data', 'vgwort_counter_suffix'));
    $this->assertFalse(\Drupal::database()->schema()->fieldExists('node_field_data', 'vgwort_counter_suffix'));
  }

}
