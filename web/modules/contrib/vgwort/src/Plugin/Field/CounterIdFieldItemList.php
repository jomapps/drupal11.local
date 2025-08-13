<?php

namespace Drupal\vgwort\Plugin\Field;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes the publisher key value and url for vgwort_counter_id field.
 *
 * @see https://tom.vgwort.de/portal/showHelp
 */
class CounterIdFieldItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * The base field name for suffixing the VG Wort counter ID.
   */
  public const SUFFIX_FIELD_NAME = 'vgwort_counter_suffix';

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();
    $config = \Drupal::config('vgwort.settings');
    $prefix = $config->get('prefix');
    $publisher_id = $config->get('publisher_id');
    $domain = $config->get('image_domain');

    if (empty($prefix) || empty($publisher_id) || empty($domain)) {
      // VG wort is not configured there is no possible value.
      return;
    }

    $enabled_for_entity = TRUE;
    \Drupal::moduleHandler()->invokeAllWith('vgwort_enable_for_entity', function (callable $hook) use ($entity, &$enabled_for_entity) {
      // Once an implementation has returned false do not call any other
      // implementation.
      if ($enabled_for_entity) {
        $enabled_for_entity = $hook($entity);
      }
    });

    if (!$enabled_for_entity) {
      // An implementation of hook_vgwort_enable_for_entity() has returned
      // false.
      return;
    }

    $override_counter_field_value = NULL;
    if ($entity instanceof FieldableEntityInterface) {
      $override_counter_field = NULL;
      \Drupal::moduleHandler()->invokeAllWith('vgwort_entity_counter_id_field', function (callable $hook) use ($entity, &$override_counter_field) {
        // Once an implementation has returned a value do not call any other
        // implementation.
        if ($override_counter_field === NULL) {
          $override_counter_field = $hook($entity);
        }
      });

      // Ensure the value returned is valid.
      if ($override_counter_field !== NULL) {
        if ($entity->hasField($override_counter_field)) {
          $override_counter_field_value = (string) $entity->get($override_counter_field)->value;
          $log_error = strlen($override_counter_field_value) === 0;
        }
        else {
          $log_error = TRUE;
        }
        // If the hook implementation has returned a bogus value, ignore it and
        // use the UUID instead.
        if ($log_error) {
          \Drupal::logger('vgwort')->error(
            'An implementation of hook_vgwort_entity_counter_id_field() has returned %field_name which is not valid for entity @entity_type:@entity_id',
            ['%field_name' => $override_counter_field, '@entity_type' => $entity->getEntityTypeId(), '@entity_id' => $entity->id()]
          );
          $override_counter_field_value = NULL;
        }
      }
    }

    $value = "$prefix.$publisher_id-";
    $value .= $override_counter_field_value ?? $entity->uuid();

    $suffix = (int) $entity->get(static::SUFFIX_FIELD_NAME)->value;
    if ($suffix !== 0) {
      $value .= '-' . $suffix;
    }

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->createItem(0, $value);
    // Example: domain.met.vgwort.de/na/vgzm.970-123456789
    // There is no protocol so that formatter or front-end can decide whether to
    // go protocol relative or on the protocol to use.
    $item->set('url', "$domain/na/$value");
    $this->list[0] = $item;
  }

  /**
   * Gets the suffix base field definition.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The suffix base field definition.
   */
  public static function getSuffixFieldDefinition(): BaseFieldDefinition {
    return BaseFieldDefinition::create('integer')
      ->setLabel(t('VG Wort counter suffix'))
      ->setSetting('unsigned', TRUE)
      ->setInternal(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $entity = $this->getEntity();
    if (isset($entity->original) && (int) $entity->get(static::SUFFIX_FIELD_NAME)->value !== (int) $entity->original->get(static::SUFFIX_FIELD_NAME)->value) {
      // If the suffix value has changed force the value to be computed again.
      $this->valueComputed = FALSE;
    }
    return parent::postSave($update);
  }

}
