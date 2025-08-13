<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Exception\NoCounterIdException;

/**
 * Tests the vgwort message generator service.
 *
 * @group vgwort
 */
class MessageGeneratorTest extends VgWortKernelTestBase {

  use PrettyJsonTrait;

  public function testNoCounterIdException(): void {
    $this->config('vgwort.settings')
      ->set('entity_types', [])
      ->save();
    $entity_type = 'entity_test';
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
      ]);
    $entity->save();
    $this->expectExceptionMessage('Entities must have the vgwort_counter_id in order to generate a VG Wort new message notification');
    $this->expectException(NoCounterIdException::class);
    $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);
  }

  public function testNoParticipantValidation(): void {
    $entity_type = 'entity_test';
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'text' => $this->getRandomGenerator()->paragraphs(30),
        'name' => 'A title',
      ]);
    $entity->save();
    /** @var \Drupal\vgwort\ParticipantListManager $participant_manager */
    $participant_manager = $this->container->get('vgwort.participant_list_manager');
    $this->assertCount(0, $participant_manager->getParticipants($entity));
    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);
    $errors = array_map('strval', $message->validate());
    $this->assertSame(['In order to be counted by VG Wort there must be at least one author.'], $errors);
  }

  public function testNoAuthorsNoParticipantValidation(): void {
    $entity_type = 'entity_test';
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        // Add a translator.
        'vgwort_test2' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
        'text' => $this->getRandomGenerator()->paragraphs(30),
        'name' => 'A title',
      ]);
    $entity->save();

    /** @var \Drupal\vgwort\ParticipantListManager $participant_manager */
    $participant_manager = $this->container->get('vgwort.participant_list_manager');
    $this->assertCount(1, $participant_manager->getParticipants($entity));

    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);
    $errors = array_map('strval', $message->validate());
    $this->assertSame(['In order to be counted by VG Wort there must be at least one author.'], $errors);
  }

  public function testEntityMessageLengthValidation(): void {
    $entity_type = 'entity_test';
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
        'text' => 'Some text',
        'name' => 'A title',
      ]);
    $entity->save();

    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);
    $errors = array_map('strval', $message->validate());
    $this->assertSame(['The minimum numbers of characters in order to be counted by VG Wort is 1800. The current count is 30.'], $errors);

    $entity->text = $this->getRandomGenerator()->paragraphs(30);
    $entity->save();
    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);
    $this->assertEmpty($message->validate());
  }

  public function testEntityToNewMessage(): void {
    $entity_type = 'entity_test';
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
        'text' => 'Some text',
        'name' => 'A title',
      ]);
    $entity->save();

    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);

    $expected_message = <<<JSON
{
    "distributionRight": true,
    "messagetext": {
        "lyric": false,
        "shorttext": "A title",
        "text": {
            "plainText": "ZnVsbCB8IEEgdGl0bGVBIHRpdGxlU29tZSB0ZXh0"
        }
    },
    "otherRightsOfPublicReproduction": false,
    "participants": [
        {
            "cardNumber": 123123123,
            "firstName": "Bob",
            "surName": "Jones",
            "involvement": "AUTHOR"
        }
    ],
    "privateidentificationid": "vgzm.123456-{$entity->uuid()}",
    "publicAccessRight": true,
    "reproductionRight": true,
    "rightsGrantedConfirmation": true,
    "webranges": [
        {
            "urls": [
                "http:\/\/localhost\/entity_test\/1"
            ]
        }
    ],
    "withoutOwnParticipation": false
}
JSON;

    $this->assertSame($expected_message, $this->jsonEncode($message));
  }

  /**
   * @see \hook_vgwort_new_message_alter()
   */
  public function testVgwortNewMessageAlter(): void {
    $this->enableModules(['vgwort_test']);
    $this->container->get('state')->set('vgwort_test_vgwort_new_message_alter', TRUE);
    $entity_type = 'entity_test';
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
        'text' => 'Some text',
        'name' => 'A title',
      ]);
    $entity->save();

    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity);

    $expected_message = <<<JSON
{
    "distributionRight": true,
    "messagetext": {
        "lyric": false,
        "shorttext": "A title",
        "text": {
            "plainText": "ZnVsbCB8IEEgdGl0bGVBIHRpdGxlU29tZSB0ZXh0"
        }
    },
    "otherRightsOfPublicReproduction": true,
    "participants": [
        {
            "cardNumber": 123123123,
            "firstName": "Bob",
            "surName": "Jones",
            "involvement": "AUTHOR"
        }
    ],
    "privateidentificationid": "vgzm.123456-{$entity->uuid()}",
    "publicAccessRight": true,
    "reproductionRight": true,
    "rightsGrantedConfirmation": true,
    "webranges": [
        {
            "urls": [
                "http:\/\/decoupled_site.example.com\/{$entity->uuid()}"
            ]
        }
    ],
    "withoutOwnParticipation": true
}
JSON;

    $this->assertSame($expected_message, $this->jsonEncode($message));
  }

  public function testEntityToNewMessageCustomViewMode(): void {
    $mode = EntityViewMode::create([
      'id' => static::ENTITY_TYPE . '.test',
      'targetEntityType' => static::ENTITY_TYPE,
      'status' => FALSE,
      'enabled' => TRUE,
      'label' => 'Test',
    ]);
    $mode->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => static::ENTITY_TYPE,
      'bundle' => static::ENTITY_TYPE,
      'mode' => 'test',
      'label' => 'My test view mode',
      'status' => TRUE,
    ])
      ->removeComponent('text');
    $display->save();
    $this->config('vgwort.settings')->set('entity_types.' . static::ENTITY_TYPE . '.view_mode', 'test')->save();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage(static::ENTITY_TYPE)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
        'text' => 'Some text',
        'name' => 'A title',
      ]);
    $entity->save();

    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity)->jsonSerialize();
    $this->assertSame('test | A titleA title', base64_decode($message['messagetext']->jsonSerialize()['text']['plainText']));

    $display->setComponent('text', [
      'type' => 'string',
      'region' => 'content',
    ]);
    $display->save();
    $message = $this->container->get('vgwort.message_generator')->entityToNewMessage($entity)->jsonSerialize();
    $this->assertSame('test | A titleA titleSome text', base64_decode($message['messagetext']->jsonSerialize()['text']['plainText']));

    // Ensure deleted display modes result in vgwort.settings being updated.
    $mode->delete();
    $this->assertSame('default', $this->config('vgwort.settings')->get('entity_types.' . static::ENTITY_TYPE . '.view_mode'));
  }

}
