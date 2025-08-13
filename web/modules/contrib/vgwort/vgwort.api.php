<?php

/**
 * @file
 * Describes hooks provided by the VG Wort module.
 */

/**
 * Determines if an entity should have VG Wort counter ID.
 *
 * This hook can be used to exclude entities that the site does not have the
 * rights to send to VG Wort. See \Drupal\vgwort\Api\NewMessage for more
 * information.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity to check.
 *
 * @return bool
 *   TRUE if the entity should contain the VG Wort web bug and be sent to
 *   VG Wort, FALSE if not.
 */
function hook_vgwort_enable_for_entity(\Drupal\Core\Entity\EntityInterface $entity): bool {
  return $entity->bundle() !== 'not_my_content';
}

/**
 * Determines if entity information should be queued for sending to VG Wort.
 *
 * NOTE: this hook is for a strange use-case where you want to add the counter
 * ID to content but do not want to send the entity to VG Wort. Ideally if you
 * implement this hook you have a plan to remove it in the future. Normally, you
 * should implement hook_vgwort_enable_for_entity() to exclude an entity
 * completely from VG Wort.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity to check.
 *
 * @return bool
 *   TRUE if the entity should be queued for sending to VG Wort, FALSE if not.
 */
function hook_vgwort_queue_entity(\Drupal\Core\Entity\EntityInterface $entity): bool {
  return match ($entity->bundle()) {
    'not_my_content' => FALSE,
    default => TRUE,
  };
}

/**
 * Overrides the field used to determine the VG Wort counter ID.
 *
 * This hook can be used to override the VG Wort counter ID value. By default,
 * the entity's UUID is used, however if you've migrated the content and wish to
 * preserve the ID this hook can be used to provide an alternative field name
 * from which to derive the value.
 *
 * NOTE: It is the hook's responsibility to ensure that the entity has a value
 * for the field that is being returned. If the field does not exist or results
 * in a zero length string the UUID will be used instead and this will be
 * logged.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity to determine the field for.
 *
 * @return string|null
 *   The field to use for the VG Wort counter ID. Return NULL to leave as UUID
 *   or be determined by another implementation. First hook to return a value
 *   wins.
 */
function hook_vgwort_entity_counter_id_field(\Drupal\Core\Entity\EntityInterface $entity): ?string {
  if ($entity->bundle() === 'not_my_content' && $entity instanceof \Drupal\Core\Entity\FieldableEntityInterface && $entity->hasField('field_my_special_id')) {
    return 'field_my_special_id';
  }
  return NULL;
}

/**
 * Alters the information sent about an entity to VG Wort.
 *
 * @param array $data
 *   Data to alter. This consists of the following data with the keys:
 *   - webranges: An array of \Drupal\vgwort\Api\Webrange objects. Defaults to
 *     a webrange containing the canonical URL of the entity.
 *   - legal_rights: An array of booleans with the same structure as
 *     vgwort.settings:legal_rights. Defaults to the values set in
 *     configuration.
 *   - without_own_participation: A boolean that indicates whether the publisher
 *     is involved. Defaults FALSE that indicates the publisher is involved.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity that is being used to generate the data to send to VG Wort.
 */
function hook_vgwort_new_message_alter(array &$data, \Drupal\Core\Entity\EntityInterface $entity): void {
  $data['without_own_participation'] = TRUE;
  $data['webranges'] = [new \Drupal\vgwort\Api\Webrange(['http://decoupled_site.com/' . $entity->id()])];
  $data['legal_rights']['other_public_communication'] = TRUE;
}
