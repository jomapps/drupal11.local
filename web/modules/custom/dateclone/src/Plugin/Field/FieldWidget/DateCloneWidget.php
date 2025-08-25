<?php

namespace Drupal\dateclone\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'dateclone_default' widget.
 *
 * @FieldWidget(
 *   id = "dateclone_default",
 *   label = @Translation("Date Clone Widget"),
 *   description = @Translation("Enables AJAX functionality for Date Clone with event duplication"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class DateCloneWidget extends DateTimeWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The date format storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dateStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityStorageInterface $date_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->dateStorage = $date_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('date_format')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    
    // Get the form object and node entity
    $form_object = $form_state->getFormObject();
    $node = $form_object->getEntity();
    
    // Only apply to event content type and startdate field
    if (!$node || $node->getEntityTypeId() !== 'node' || $node->bundle() !== 'event' || $this->fieldDefinition->getName() !== 'field_startdate') {
      return $element;
    }
    
    // Debug logging
    \Drupal::logger('dateclone')->info('DateClone widget activated for field @field on @bundle', [
      '@field' => $this->fieldDefinition->getName(),
      '@bundle' => $node->bundle(),
    ]);

    // Configure the datetime element based on field settings
    if ($this->getFieldSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      $element['value']['#title'] = $this->fieldDefinition->getLabel();
      $element['value']['#description'] = $this->fieldDefinition->getDescription();
      $date_type = 'date';
      $time_type = 'none';
      $date_format = $this->dateStorage->load('html_date')->getPattern();
      $time_format = '';
    }
    else {
      $element['#theme_wrappers'][] = 'fieldset';
      $date_type = 'date';
      $time_type = 'time';
      $date_format = $this->dateStorage->load('html_date')->getPattern();
      $time_format = $this->dateStorage->load('html_time')->getPattern();
    }

    $element['value'] += [
      '#date_date_format' => $date_format,
      '#date_date_element' => $date_type,
      '#date_date_callbacks' => [],
      '#date_time_format' => $time_format,
      '#date_time_element' => $time_type,
      '#date_time_callbacks' => [],
    ];

    // Get node ID (null for new nodes)
    $nid = $node->id();

    // Add the DateClone container
    $element['dateclone_container'] = [
      '#type' => 'inline_template',
      '#template' => '<div id="js-dateclone"></div>',
      '#weight' => 10,
    ];

    // Attach the DateClone library and settings
    $element['dateclone_container']['#attached']['library'][] = 'dateclone/dateclone';
    $element['dateclone_container']['#attached']['drupalSettings']['dateclone'] = [
      'nid' => $nid,
      'url' => '/dateclone/create',
      'containerId' => '#js-dateclone',
      'dateFieldId' => '#edit-field-startdate-0-value-date',
      'timeFieldId' => '#edit-field-startdate-0-value-time',
      'titleFieldId' => '#edit-title-0-value',
      'tabindex' => [
        '#edit-field-event-type',
        '#edit-title-0-value',
        '#remove-versalien',
        '#edit-field-place-0-target-id',
        '#edit-field-startdate-0-value-date',
        '#edit-field-startdate-0-value-time',
        '#weekday-1',
        '#weekday-2',
        '#weekday-3',
        '#weekday-4',
        '#weekday-5',
        '#weekday-6',
        '#weekday-0',
        '#add-date',
        '#edit-body-0-value',
        '#edit-createandcontinue',
      ],
    ];

    return $element;
  }

}
