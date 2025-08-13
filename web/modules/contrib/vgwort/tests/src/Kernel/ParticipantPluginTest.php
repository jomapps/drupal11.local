<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;

/**
 * Tests the participant plugins and manager.
 *
 * @group vgwort
 */
class ParticipantPluginTest extends VgWortKernelTestBase {

  protected function setUp(): void {
    parent::setUp();
    // Create an entity reference field so we can reference multiple users.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'users',
      'entity_type' => static::ENTITY_TYPE,
      'translatable' => FALSE,
      'settings' => [
        'target_type' => 'user',
      ],
      'type' => 'entity_reference',
      'cardinality' => 4,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => static::ENTITY_TYPE,
      'bundle' => static::ENTITY_TYPE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [],
      ],
    ]);
    $field->save();
    _vgwort_add_entity_reference_to_participant_map(static::ENTITY_TYPE, 'users');
  }

  public function testParticipantListManager(): void {
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
    /** @var \Drupal\vgwort\ParticipantListManager $participant_manager */
    $participant_manager = $this->container->get('vgwort.participant_list_manager');
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(1, $participants);
    $bob_jones = [
      'cardNumber' => 123123123,
      'firstName' => 'Bob',
      'surName' => 'Jones',
      'involvement' => 'AUTHOR',
    ];
    $this->assertSame($bob_jones, $participants[0]->jsonSerialize());

    // Add another participant.
    $entity->vgwort_test[1] = [
      'card_number' => '4321431',
      'firstname' => 'Anna',
      'surname' => 'Burns',
      'agency_abbr' => '',
    ];
    $entity->save();
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(2, $participants);
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $anna_burns = [
      'cardNumber' => 4321431,
      'firstName' => 'Anna',
      'surName' => 'Burns',
      'involvement' => 'AUTHOR',
    ];
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());

    // Add a translator.
    $entity->vgwort_test2 = [
      'card_number' => '8924354',
      'firstname' => 'Sarah',
      'surname' => 'Smith',
      'agency_abbr' => '',
    ];
    $entity->save();
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(3, $participants);
    $sarah_smith_translator = [
      'cardNumber' => 8924354,
      'firstName' => 'Sarah',
      'surName' => 'Smith',
      'involvement' => 'TRANSLATOR',
    ];
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[2]->jsonSerialize());

    // Add an entity owner with no VG Wort info.
    $user = User::create(['name' => 'test', 'status' => TRUE]);
    $user->save();
    $entity->users = [['target_id' => $user->id()]];
    $entity->save();
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(3, $participants);
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[2]->jsonSerialize());

    // Add VG Wort to a new user.
    $user2 = User::create(['name' => 'simon_george', 'status' => TRUE]);
    $user2->vgwort_test = [
      'card_number' => '45325342',
      'firstname' => 'Simon',
      'surname' => 'George',
      'agency_abbr' => '',
    ];
    $user2->save();
    $entity->users = [['target_id' => $user->id()], ['target_id' => $user2->id()]];
    $entity->save();
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $storage->resetCache();
    $entity = $storage->load($entity->id());

    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(4, $participants);
    $simon_george = [
      'cardNumber' => 45325342,
      'firstName' => 'Simon',
      'surName' => 'George',
      'involvement' => 'AUTHOR',
    ];
    $this->assertSame($simon_george, $participants[1]->jsonSerialize());
    $this->assertSame($bob_jones, $participants[2]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[3]->jsonSerialize());

    // Make the user and entity participant info mostly match apart from
    // involvement.
    $user2->vgwort_test = [
      'card_number' => '8924354',
      'firstname' => 'Sarah',
      'surname' => 'Smith',
      'agency_abbr' => '',
    ];
    $user2->save();
    $storage->resetCache();
    $entity = $storage->load($entity->id());
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(4, $participants);
    $sarah_smith_author = [
      'cardNumber' => 8924354,
      'firstName' => 'Sarah',
      'surName' => 'Smith',
      'involvement' => 'AUTHOR',
    ];
    $this->assertSame($sarah_smith_author, $participants[2]->jsonSerialize());
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[3]->jsonSerialize());

    // Make the user and entity participant info mostly match apart from
    // involvement.
    $user2->vgwort_test = [
      'card_number' => '123123123',
      'firstname' => 'Bob',
      'surname' => 'Jones',
      'agency_abbr' => '',
    ];
    $user2->save();
    $storage->resetCache();
    $entity = $storage->load($entity->id());
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(3, $participants);
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[2]->jsonSerialize());

    // And an agency to ensure that works.
    $entity->vgwort_test[2] = [
      'agency_abbr' => 'BBC',
    ];
    $entity->save();
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(4, $participants);
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame(['code' => 'BBC', 'involvement' => 'AUTHOR'], $participants[3]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[2]->jsonSerialize());

    // Make the first user in the users field have participant info.
    $user->vgwort_test = [
      'card_number' => '245243526',
      'firstname' => 'Sam',
      'surname' => 'Wilde',
      'agency_abbr' => '',
    ];
    $user->save();
    $storage->resetCache();
    $entity = $storage->load($entity->id());
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(5, $participants);
    $this->assertSame($bob_jones, $participants[1]->jsonSerialize());
    $this->assertSame($anna_burns, $participants[0]->jsonSerialize());
    $this->assertSame(['code' => 'BBC', 'involvement' => 'AUTHOR'], $participants[4]->jsonSerialize());
    $this->assertSame($sarah_smith_translator, $participants[2]->jsonSerialize());
    $this->assertSame([
      'cardNumber' => 245243526,
      'firstName' => 'Sam',
      'surName' => 'Wilde',
      'involvement' => 'AUTHOR',
    ], $participants[3]->jsonSerialize());

    // Duplicate references to the same user.
    $entity->user_id->target_id = $user->id();
    $entity->save();
    $participants = $participant_manager->getParticipants($entity);
    $this->assertCount(5, $participants);
  }

}
