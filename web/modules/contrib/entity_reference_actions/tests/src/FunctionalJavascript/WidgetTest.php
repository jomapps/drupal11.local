<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_reference_actions\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Testing the widget.
 *
 * @group entity_reference_actions
 */
class WidgetTest extends WebDriverTestBase {

  use EntityReferenceFieldCreationTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'media_library',
    'entity_reference_actions',
    'views_bulk_edit',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The test type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $mediaType;

  /**
   * The test media.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mediaType = $this->createMediaType('image');
    $this->media = Media::create([
      'bundle' => $this->mediaType->id(),
      'published' => TRUE,
    ]);
    $this->media->save();

    $handler_settings = [
      'target_bundles' => [
        $this->mediaType->id() => $this->mediaType->id(),
      ],
    ];
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test', 'Test', 'media', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $entity = EntityTest::create();
    $entity->field_test = [$this->media];
    $entity->save();

    $this->drupalLogin($this->createUser([
      'administer entity_test content',
      'administer media',
      'use views bulk edit',
    ]));
  }

  /**
   * Tests different widgets.
   *
   * @dataProvider providerTestWidgets
   */
  public function testWidgets($widget) {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', [
        'type' => $widget,
        'third_party_settings' => ['entity_reference_actions' => ['enabled' => TRUE]],
      ])
      ->save();

    $this->drupalGet('/entity_test/manage/1/edit');

    $this->assertTrue($this->media->isPublished());

    // Unpublish the entity.
    $this->getSession()->getPage()->find('css', 'li.dropbutton-toggle button')->click();
    $this->assertSession()->waitForButton('Unpublish all media items');
    $this->getSession()->getPage()->pressButton('Unpublish all media items');

    $this->assertSession()->waitForText('Unpublish all media items was successfully applied');
    $this->assertSession()->pageTextContains('Unpublish all media items was successfully applied');

    $this->media = \Drupal::entityTypeManager()->getStorage('media')->loadUnchanged($this->media->id());
    $this->assertFalse($this->media->isPublished());

    // Publish the entity again.
    $this->drupalGet('/entity_test/manage/1/edit');
    $this->getSession()->getPage()->find('css', 'li.dropbutton-toggle button')->click();
    $this->assertSession()->waitForButton('Publish all media items');
    $this->getSession()->getPage()->pressButton('Publish all media items');

    $this->assertSession()->waitForText('Publish all media items was successfully applied');
    $this->assertSession()->pageTextContains('Publish all media items was successfully applied');

    $this->media = \Drupal::entityTypeManager()->getStorage('media')->loadUnchanged($this->media->id());
    $this->assertTrue($this->media->isPublished());

    // Bulk edit entity.
    $this->drupalGet('/entity_test/manage/1/edit');
    $this->getSession()->getPage()->find('css', 'li.dropbutton-toggle button')->click();
    $this->assertSession()->waitForButton('Modify field values all media items');
    $this->getSession()->getPage()->pressButton('Modify field values all media items');

    $this->assertSession()->assertWaitOnAjaxRequest();
    // @todo do a bulk edit.
    $this->getSession()->getPage()->pressButton('Cancel');
    $this->assertSession()->assertNoElementAfterWait('css', 'ui.dialog');

    $this->getSession()->getPage()->pressButton('Save');
    $entity = EntityTest::load(1);
    $this->assertNotEmpty($entity->field_test);
  }

  /**
   * Provides test data for testWidgets().
   */
  public static function providerTestWidgets() {
    return [
      ['entity_reference_autocomplete_tags'],
      ['entity_reference_autocomplete'],
      ['options_select'],
      ['options_buttons'],
      ['media_library_widget'],
    ];
  }

  /**
   * Tests user with no bulk edit access.
   */
  public function testNoBulkEditAccess() {
    $this->drupalLogin($this->createUser([
      'administer entity_test content',
      'administer media',
    ]));

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', [
        'type' => 'entity_reference_autocomplete',
        'third_party_settings' => ['entity_reference_actions' => ['enabled' => TRUE]],
      ])
      ->save();

    $this->drupalGet('/entity_test/manage/1/edit');

    $this->assertTrue($this->media->isPublished());

    // Use views bulk edit without permission.
    $this->getSession()->getPage()->find('css', 'li.dropbutton-toggle button')->click();
    $this->assertSession()->waitForButton('Modify field values all media items');
    $this->getSession()->getPage()->pressButton('Modify field values all media items');

    $this->assertSession()->waitForText("Unable to perform Edit media because: The 'use views bulk edit' permission is required.");
    $this->assertSession()->pageTextContains("Unable to perform Edit media because: The 'use views bulk edit' permission is required.");
  }

  /**
   * Test an action with confirmation page.
   */
  public function testConfirmationAction() {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', [
        'type' => 'media_library_widget',
        'third_party_settings' => ['entity_reference_actions' => ['enabled' => TRUE]],
      ])
      ->save();

    $this->drupalGet('/entity_test/manage/1/edit');

    $this->getSession()->getPage()->pressButton('Delete all media items');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Delete');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Action was successfully applied');

    $this->assertEmpty(Media::load($this->media->id()));
  }

}
