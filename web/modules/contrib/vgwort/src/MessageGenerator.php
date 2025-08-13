<?php

namespace Drupal\vgwort;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\vgwort\Api\MessageText;
use Drupal\vgwort\Api\NewMessage;
use Drupal\vgwort\Api\Webrange;
use Drupal\vgwort\Exception\NoCounterIdException;

/**
 * Converts entities to VG Wort messages.
 */
class MessageGenerator {

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected readonly Config $config;

  public function __construct(protected readonly ParticipantListManager $participantListManager, protected readonly EntityTypeManagerInterface $entityTypeManager, protected readonly RendererInterface $renderer, ConfigFactoryInterface $configFactory, protected readonly ModuleHandlerInterface $moduleHandler) {
    $this->config = $configFactory->get('vgwort.settings');
  }

  /**
   * Creates a NewMessage Object for the provided entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to create a new message object for.
   *
   * @return \Drupal\vgwort\Api\NewMessage
   *   The new message object representing the entity.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Throw if the entity cannot generate a URL because it does not have an
   *   entity ID.
   * @throws \Drupal\vgwort\Exception\NewMessageException
   *   Throw if the entity cannot be converted to a
   *   \Drupal\vgwort\Api\NewMessage object. Note, that the message represented
   *   by the object might not be valid for VG Wort. Call NewMessage::validate()
   *   to check validity.
   */
  public function entityToNewMessage(FieldableEntityInterface $entity): NewMessage {
    if (!$entity->hasField('vgwort_counter_id') || $entity->vgwort_counter_id->isEmpty()) {
      throw new NoCounterIdException('Entities must have the vgwort_counter_id in order to generate a VG Wort new message notification');
    }
    $vgwort_id = $entity->vgwort_counter_id->value;
    $view_mode = $this->config->get("entity_types.{$entity->getEntityTypeId()}.view_mode") ?? 'full';
    $build = $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode);

    $text = new MessageText(
      $entity->label(),
      (string) $this->renderer->renderInIsolation($build)
    );
    $participants = $this->participantListManager->getParticipants($entity);
    $webranges = [new Webrange([$entity->toUrl()->setAbsolute()->toString()])];
    $legal_rights = $this->config->get('legal_rights');

    // All modules to alter some of the data we send to VG Wort.
    $alter_data = [
      'webranges' => $webranges,
      'legal_rights' => $legal_rights,
      'without_own_participation' => FALSE,
    ];

    $this->moduleHandler->alter('vgwort_new_message', $alter_data, $entity);

    return new NewMessage(
      $vgwort_id,
      $text,
      $participants,
      $alter_data['webranges'],
      $alter_data['legal_rights']['distribution'],
      $alter_data['legal_rights']['public_access'],
      $alter_data['legal_rights']['reproduction'],
      $alter_data['legal_rights']['declaration_of_granting'],
      $alter_data['legal_rights']['other_public_communication'],
      $alter_data['without_own_participation']
    );
  }

}
