<?php

namespace Drupal\google_places\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'google_places_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "google_places_autocomplete",
 *   label = @Translation("Google Places Autocomplete"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class GooglePlacesAutocompleteWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder' => 'Search for a place...',
      'auto_populate' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text shown in the autocomplete field when empty.'),
    ];

    $elements['auto_populate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-populate place data'),
      '#default_value' => $this->getSetting('auto_populate'),
      '#description' => $this->t('Automatically populate other form fields when a place is selected.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    if ($this->getSetting('auto_populate')) {
      $summary[] = $this->t('Auto-populate enabled');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
      '#attributes' => [
        'class' => ['google-places-autocomplete'],
        'data-auto-populate' => $this->getSetting('auto_populate') ? 'true' : 'false',
      ],
      '#attached' => [
        'library' => ['google_places/google_places_autocomplete'],
        'drupalSettings' => [
          'googlePlaces' => [
            'apiKey' => $this->getApiKey(),
          ],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Get the Google Places API key from settings.
   *
   * @return string|null
   *   The API key or NULL if not configured.
   */
  protected function getApiKey() {
    $settings = \Drupal::service('settings');
    return $settings->get('maps_api_key');
  }

}
