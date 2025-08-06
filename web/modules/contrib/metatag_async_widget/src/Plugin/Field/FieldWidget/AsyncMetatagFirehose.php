<?php

namespace Drupal\metatag_async_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\metatag\MetatagManagerInterface;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\metatag\Plugin\Field\FieldWidget\MetatagFirehose;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Asynchronous widget for the Metatag field.
 *
 * @FieldWidget(
 *   id = "metatag_async_widget_firehose",
 *   label = @Translation("Advanced meta tags form (async)"),
 *   description = @Translation("Asynchronous widget for more performant entity editing."),
 *   field_types = {
 *     "metatag"
 *   }
 * )
 */
class AsyncMetatagFirehose extends MetatagFirehose {

  /**
   * Instance of Entity Type Manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
      $container->get('metatag.manager'),
      $container->get('plugin.manager.metatag.tag'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    MetatagManagerInterface $manager,
    MetatagTagPluginManager $plugin_manager,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $manager, $plugin_manager, $config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Ajax callback for the "Customize meta tags" button.
   */
  public static function ajaxFormRefresh(array $form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();

    // This will be placed inside a details element so remove everything that
    // would make add a nested details element.
    $form = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    $children = Element::children($form);
    return array_intersect_key($form, array_flip($children));
  }

  /**
   * Submit callback for the "Customize meta tags" button.
   */
  public static function customizeMetaTagsSubmit(array $form, FormStateInterface $form_state): void {
    $form_state->set('metatag_async_widget_customize_meta_tags', TRUE);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], [$field_name]);
    $form_values = $form_state->getValues();
    $values = NestedArray::getValue($form_values, array_merge($path, [0]));
    // We don't want to override saved meta tags settings if the meta tags
    // fields were not present.
    if (!empty($values) && count($values) === 1 && isset($values['metatag_async_widget_customize_meta_tags'])) {
      NestedArray::unsetValue($form_values, $path);
      return;
    }

    parent::extractFormValues($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): array {
    if ($form_state->get('metatag_async_widget_customize_meta_tags')) {
      $element += parent::formElement($items, $delta, $element, $form, $form_state);
      // Open the meta tags group upon selection.
      $element['#open'] = TRUE;

      // Make sure that basic details is opened by default and all the others
      // are closed.
      foreach (Element::children($element) as $key) {
        if (isset($element[$key]['#type']) && $element[$key]['#type'] == 'details') {
          $element[$key]['#open'] = $key == 'basic';
        }
      }
    }
    else {
      $wrapper_id = Html::getUniqueId('metatag-async-widget-wrapper');
      $element['metatag_async_widget_customize_meta_tags'] = [
        '#type' => 'submit',
        '#name' => 'metatag_async_widget_customize_meta_tags',
        '#value' => $this->t('Customize meta tags'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_called_class(), 'customizeMetaTagsSubmit']],
        '#ajax' => [
          'callback' => [__CLASS__, 'ajaxFormRefresh'],
          'wrapper' => $wrapper_id,
        ],
        '#prefix' => "<span id=\"$wrapper_id\">",
        '#suffix' => "</span>",
      ];

      // Put the form element into the form's "advanced" group.
      // Add the outer fieldset.
      $element += [
        '#type' => 'details',
      ];
      // If the "sidebar" option was checked on the field widget, put the
      // form element into the form's "advanced" group. Otherwise, let it
      // default to the main field area.
      $sidebar = $this->getSetting('sidebar');
      if ($sidebar) {
        $element['#group'] = 'advanced';
      }
    }

    // Add current entity node as hidden field for saving purposes.
    $entity = $items->getEntity();
    $element['metatag-async-widget-entity-new'] = [
      '#type' => 'hidden',
      '#name' => 'metatag-async-widget-entity-new',
      '#value' => $entity->isNew() ? 1 : 0,
    ];

    $element['metatag-async-widget-entity-id'] = [
      '#type' => 'hidden',
      '#name' => 'metatag-async-widget-entity-id',
      '#value' => !$entity->isNew() ? $entity->id() : NULL,
    ];

    $element['metatag-async-widget-entity-revision-id'] = [
      '#type' => 'hidden',
      '#name' => 'metatag-async-widget-entity-revision',
      '#value' => !$entity->isNew() && $entity->getEntityType()->isRevisionable() && $entity instanceof RevisionableInterface ? $entity->getLoadedRevisionId() : NULL,
    ];

    $element['metatag-async-widget-entity-language'] = [
      '#type' => 'hidden',
      '#name' => 'metatag-async-widget-entity-language',
      '#value' => $entity->language()->getId(),
    ];

    $element['metatag-async-widget-entity-type'] = [
      '#type' => 'hidden',
      '#name' => 'metatag-async-widget-entity-type',
      '#value' => $entity->getEntityTypeId(),
    ];

    return $element;
  }

  /**
   * Apply default entity values to the metatag field if no async edit was requested.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $unchangedValues = array_filter($values, function ($value) {
      return isset($value['metatag_async_widget_customize_meta_tags']) || !isset($value['basic']) || empty($value);
    });

    foreach ($unchangedValues as $key => &$value) {
      $isNew = (bool) $value['metatag-async-widget-entity-new'];
      $entityType = $value['metatag-async-widget-entity-type'];
      $entityID = $value['metatag-async-widget-entity-id'];
      $entityRevisionID = $value['metatag-async-widget-entity-revision-id'];
      $entityLanguage = $value['metatag-async-widget-entity-language'];
      unset(
        $value['metatag-async-widget-entity-new'],
        $value['metatag-async-widget-entity-id'],
        $value['metatag-async-widget-entity-revision-id'],
        $value['metatag-async-widget-entity-type'],
        $value['metatag-async-widget-entity-language']
      );

      if ($isNew) {
        continue;
      }
      try {
        $storage = $this->entityTypeManager->getStorage($entityType);
      }
      catch (\Exception) {
        continue;
      }

      if ($this->entityTypeManager->getDefinition($entityType)->isRevisionable()) {
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
        $entity = $storage->loadRevision($entityRevisionID);
      }
      else {
        $entity = $storage->load($entityID);
      }

      if (!$entity instanceof ContentEntityInterface) {
        continue;
      }

      // Check if currently handled entity is in a different language than the
      // original entity.
      if ($entity->language()->getId() !== $entityLanguage && $entity->hasTranslation($entityLanguage)) {
        $entity = $entity->getTranslation($entityLanguage);
      }

      // Get current field value from the entity.
      if ($originalValues = $entity->get($this->fieldDefinition->getName())->getValue()) {
        $value = $originalValues[$key]['value'];
      }
    }

    // The rest of the values that don't have the
    // metatag_async_widget_customize_meta_tags value needs to be parsed by
    // parent::messageFormValues.
    $customizedValues = array_diff_key($values, $unchangedValues);
    $customizedValues = parent::massageFormValues($customizedValues, $form, $form_state);

    return array_merge($unchangedValues, $customizedValues);
  }

}
