<?php

namespace Drupal\google_places\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Google Places API service.
 * 
 * Handles integration with Google Places API to fetch place data and images
 * with German language support.
 */
class GooglePlacesApiService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;



  /**
   * Google Maps API key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * Constructs a GooglePlacesApiService object.
   */
  public function __construct(
    ClientInterface $http_client,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('google_places');
    
    // Get API key from settings
    $this->apiKey = Settings::get('maps_api_key');
  }

  /**
   * Fetch place image from Google Places API.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   *
   * @return array
   *   Result array with success status and data/error.
   */
  public function fetchPlaceImage(NodeInterface $node) {
    try {
      // Get Google Place ID from node
      $place_id = $this->getPlaceIdFromNode($node);
      
      if (!$place_id) {
        return [
          'success' => FALSE,
          'error' => 'No Google Place ID found for this place.',
        ];
      }

      // Get place details with photos and opening hours
      $place_details = $this->getPlaceDetails($place_id);
      
      if (!$place_details['success']) {
        return $place_details;
      }

      // Import opening hours if available
      $this->importOpeningHours($node, $place_details['data']);

      $photos = $place_details['data']['photos'] ?? [];
      if (empty($photos)) {
        return [
          'success' => FALSE,
          'error' => 'No photos available for this place.',
        ];
      }

      // Download the first photo
      $photo_reference = $photos[0]['photo_reference'];
      $image_result = $this->downloadPlacePhoto($photo_reference, $place_id);

      return $image_result;

    } catch (\Exception $e) {
      $this->logger->error('Error fetching place image: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Get Google Place ID from form state or node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The place ID or NULL if not found.
   */
  protected function getPlaceIdFromFormOrNode(NodeInterface $node, FormStateInterface $form_state) {
    // First, try to get place ID from form values (current user input)
    $form_values = $form_state->getValues();
    $place_id_fields = ['field_google_place_id', 'field_place_id', 'title'];
    
    $this->logger->debug('Searching for Place ID in form values. Available fields: @fields', [
      '@fields' => implode(', ', array_keys($form_values)),
    ]);
    
    // Debug: Log all form values to see what we actually have
    foreach ($form_values as $field_name => $field_value) {
      if (is_string($field_value) && !empty($field_value)) {
        $this->logger->debug('Form field @field has string value: @value', [
          '@field' => $field_name,
          '@value' => $field_value,
        ]);
        
        // Check if this string value is a Place ID
        if (strpos($field_value, 'ChIJ') === 0) {
          $this->logger->info('Found Place ID in form field @field: @place_id', [
            '@field' => $field_name,
            '@place_id' => $field_value,
          ]);
          return $field_value;
        }
      } elseif (is_array($field_value) && isset($field_value[0]['value']) && !empty($field_value[0]['value'])) {
        $this->logger->debug('Form field @field has array value: @value', [
          '@field' => $field_name,
          '@value' => $field_value[0]['value'],
        ]);
        
        // Check if this array value is a Place ID
        $value = $field_value[0]['value'];
        if (is_string($value) && strpos($value, 'ChIJ') === 0) {
          $this->logger->info('Found Place ID in form field @field: @place_id', [
            '@field' => $field_name,
            '@place_id' => $value,
          ]);
          return $value;
        }
      }
    }
    
    // Original logic as fallback for specific fields
    foreach ($place_id_fields as $field_name) {
      if (isset($form_values[$field_name])) {
        $field_value = $form_values[$field_name];
        
        // Handle different field value structures
        $value = null;
        if (is_array($field_value) && isset($field_value[0]['value'])) {
          $value = $field_value[0]['value'];
        } elseif (is_string($field_value)) {
          $value = $field_value;
        }
        
        // Ensure value is a string before using strpos
        if (is_array($value)) {
          $this->logger->debug('Field @field value is still an array, skipping: @value', [
            '@field' => $field_name,
            '@value' => json_encode($value),
          ]);
          continue;
        }
        
        $this->logger->debug('Checking specific field @field with value: @value', [
          '@field' => $field_name,
          '@value' => $value,
        ]);
        
        // Check if this looks like a Google Place ID (starts with ChIJ)
        if ($value && is_string($value) && strpos($value, 'ChIJ') === 0) {
          $this->logger->info('Found Place ID in specific field @field: @place_id', [
            '@field' => $field_name,
            '@place_id' => $value,
          ]);
          return $value;
        }
      }
    }
    
    // If not found in form, fallback to node values
    $this->logger->debug('No Place ID found in form values, checking node values');
    return $this->getPlaceIdFromNode($node);
  }

  /**
   * Get Google Place ID from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return string|null
   *   The place ID or NULL if not found.
   */
  protected function getPlaceIdFromNode(NodeInterface $node) {
    // Try to get place ID from various possible fields
    $place_id_fields = ['field_google_place_id', 'field_place_id', 'title'];
    
    foreach ($place_id_fields as $field_name) {
      if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
        $value = $node->get($field_name)->value;
        
        // Check if this looks like a Google Place ID (starts with ChIJ)
        if (strpos($value, 'ChIJ') === 0) {
          return $value;
        }
      }
    }
    
    // If no place ID found, try to extract from URL field
    if ($node->hasField('field_google_map_url') && !$node->get('field_google_map_url')->isEmpty()) {
      $url = $node->get('field_google_map_url')->uri;
      // Try to extract place ID from Google Maps URL
      if (preg_match('/place\/([^\/]+)/', $url, $matches)) {
        return $matches[1];
      }
    }
    
    return NULL;
  }

  /**
   * Get place details from Google Places API.
   *
   * @param string $place_id
   *   The Google Place ID.
   *
   * @return array
   *   Result array with success status and data/error.
   */
  protected function getPlaceDetails($place_id) {
    $url = 'https://maps.googleapis.com/maps/api/place/details/json';
    
    $params = [
      'place_id' => $place_id,
      'fields' => 'name,formatted_address,photos,opening_hours',
      'language' => 'de', // German language
      'key' => $this->apiKey,
    ];

    try {
      $response = $this->httpClient->get($url, ['query' => $params]);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if ($data['status'] === 'OK') {
        return [
          'success' => TRUE,
          'data' => $data['result'],
        ];
      } else {
        $error = $data['error_message'] ?? $data['status'];
        $this->logger->error('Google Places API error: @error', ['@error' => $error]);
        
        return [
          'success' => FALSE,
          'error' => $error,
        ];
      }
    } catch (RequestException $e) {
      $this->logger->error('HTTP request failed: @error', ['@error' => $e->getMessage()]);
      
      return [
        'success' => FALSE,
        'error' => 'Failed to connect to Google Places API: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Download photo from Google Places Photos API.
   *
   * @param string $photo_reference
   *   The photo reference.
   * @param string $place_id
   *   The place ID for directory organization.
   *
   * @return array
   *   Result array with success status and data/error.
   */
  protected function downloadPlacePhoto($photo_reference, $place_id) {
    $url = 'https://maps.googleapis.com/maps/api/place/photo';
    
    $params = [
      'photoreference' => $photo_reference,
      'maxwidth' => 800,
      'key' => $this->apiKey,
    ];

    try {
      // Create directory for this place
      $directory = 'public://places/' . $place_id;
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Generate filename
      $filename = md5($photo_reference) . '.jpg';
      $destination = $directory . '/' . $filename;

      // Download image with redirect following
      $response = $this->httpClient->get($url, [
        'query' => $params,
        'allow_redirects' => TRUE,
      ]);

      // Save image data
      $image_data = $response->getBody()->getContents();
      $file_uri = $this->fileSystem->saveData($image_data, $destination, FileSystemInterface::EXISTS_REPLACE);

      if ($file_uri) {
        $this->logger->info('Successfully downloaded image for place @place_id to @uri', [
          '@place_id' => $place_id,
          '@uri' => $file_uri,
        ]);

        return [
          'success' => TRUE,
          'file_uri' => $file_uri,
          'filename' => $filename,
        ];
      } else {
        return [
          'success' => FALSE,
          'error' => 'Failed to save image file.',
        ];
      }

    } catch (RequestException $e) {
      $this->logger->error('Failed to download photo: @error', ['@error' => $e->getMessage()]);
      
      return [
        'success' => FALSE,
        'error' => 'Failed to download image: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Import opening hours from Google Places data into node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   * @param array $place_data
   *   The place data from Google Places API.
   */
  protected function importOpeningHours(NodeInterface $node, array $place_data) {
    if (!$node->hasField('field_opening_hours') || empty($place_data['opening_hours'])) {
      return;
    }

    $opening_hours = $place_data['opening_hours'];
    
    // Use the German weekday_text format which is more readable
    if (!empty($opening_hours['weekday_text'])) {
      $hours_text = implode("\n", $opening_hours['weekday_text']);
      
      // Set the opening hours field
      $node->set('field_opening_hours', [
        'value' => $hours_text,
        'format' => 'basic_html', // Assuming basic_html format
      ]);
      
      // Save the node
      $node->save();
      
      $this->logger->info('Successfully imported opening hours for place @place_id: @hours', [
        '@place_id' => $this->getPlaceIdFromNode($node),
        '@hours' => $hours_text,
      ]);
    }
  }

  /**
   * Populate place data from Google Places API.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Result array with success status and populated fields.
   */
  public function populatePlaceData(NodeInterface $node, array &$form, FormStateInterface $form_state) {
    $place_id = $this->getPlaceIdFromFormOrNode($node, $form_state);
    
    if (!$place_id) {
      return [
        'success' => FALSE,
        'error' => 'No Google Place ID found for this place.',
      ];
    }

    // Get place details with additional fields
    $place_details = $this->getPlaceDetailsForPopulation($place_id);
    
    if (!$place_details['success']) {
      return $place_details;
    }

    $place_data = $place_details['data'];
    $populated_fields = [];

    // Populate title/name
    if (!empty($place_data['name'])) {
      $populated_fields['title[0][value]'] = $place_data['name'];
      
      // Populate company field with same value as title
      if ($node->hasField('field_company')) {
        $populated_fields['field_company[0][value]'] = $place_data['name'];
        $this->logger->debug('Company field mapped: @company', [
          '@company' => $place_data['name'],
        ]);
      }
    }

    // Populate formatted address
    if (!empty($place_data['formatted_address']) && $node->hasField('field_formatted_address')) {
      $populated_fields['field_formatted_address[0][value]'] = $place_data['formatted_address'];
    }

    // Populate coordinates
    if (!empty($place_data['geometry']['location'])) {
      $lat = $place_data['geometry']['location']['lat'];
      $lng = $place_data['geometry']['location']['lng'];
      
      if ($node->hasField('field_latitude')) {
        $populated_fields['field_latitude[0][value]'] = $lat;
      }
      
      if ($node->hasField('field_longitude')) {
        $populated_fields['field_longitude[0][value]'] = $lng;
      }
    }

    // Populate phone number
    if (!empty($place_data['formatted_phone_number']) && $node->hasField('field_phone')) {
      // Try multiple possible field name formats
      $phone_field_names = [
        'field_phone[0][value]',
        'field_phone[value]',
        'field_phone'
      ];
      
      foreach ($phone_field_names as $field_name) {
        $populated_fields[$field_name] = $place_data['formatted_phone_number'];
      }
      
      $this->logger->debug('Phone field mapped: @phone to multiple field formats', [
        '@phone' => $place_data['formatted_phone_number'],
      ]);
    } else {
      $this->logger->debug('Phone field not mapped. Has data: @has_data, Has field: @has_field', [
        '@has_data' => !empty($place_data['formatted_phone_number']) ? 'yes' : 'no',
        '@has_field' => $node->hasField('field_phone') ? 'yes' : 'no',
      ]);
    }

    // Populate website URL
    if (!empty($place_data['website']) && $node->hasField('field_url')) {
      $populated_fields['field_url[0][uri]'] = $place_data['website'];
      
      // Format the link title based on domain logic
      $link_title = $this->formatDomainForLinkTitle($place_data['website']);
      $populated_fields['field_url[0][title]'] = $link_title;
      
      $this->logger->debug('URL field mapped: @url with title: @title', [
        '@url' => $place_data['website'],
        '@title' => $link_title,
      ]);
    }

    // Populate opening hours
    if (!empty($place_data['opening_hours']['weekday_text']) && $node->hasField('field_opening_hours')) {
      $hours_text = implode(", ", $place_data['opening_hours']['weekday_text']);
      $populated_fields['field_opening_hours[0][value]'] = $hours_text;
      $this->logger->debug('Opening hours field mapped: @hours', [
        '@hours' => $hours_text,
      ]);
    } else {
      $this->logger->debug('Opening hours not mapped. Has data: @has_data, Has field: @has_field', [
        '@has_data' => !empty($place_data['opening_hours']['weekday_text']) ? 'yes' : 'no',
        '@has_field' => $node->hasField('field_opening_hours') ? 'yes' : 'no',
      ]);
    }

    // Populate entity reference fields
    $entity_ref_messages = $this->populateEntityReferenceFields($place_data, $populated_fields, $node);

    $this->logger->info('Successfully populated place data for place @place_id', [
      '@place_id' => $place_id,
    ]);

    // Debug: Log all populated fields
    $this->logger->debug('All populated fields: @fields', [
      '@fields' => json_encode($populated_fields),
    ]);

    $result = [
      'success' => TRUE,
      'populated_fields' => $populated_fields,
    ];

    // Add entity reference messages if any
    if (!empty($entity_ref_messages)) {
      $result['messages'] = $entity_ref_messages;
    }

    return $result;
  }

  /**
   * Populate entity reference fields with taxonomy terms.
   *
   * @param array $place_data
   *   The place data from Google Places API.
   * @param array &$populated_fields
   *   The populated fields array to modify.
   * @param \Drupal\node\NodeInterface $node
   *   The node being populated.
   *
   * @return array
   *   Array of messages for fields that couldn't be populated.
   */
  protected function populateEntityReferenceFields(array $place_data, array &$populated_fields, NodeInterface $node) {
    $messages = [];

    // Populate Region field (Autocomplete)
    if ($node->hasField('field_region')) {
      $region_info = $this->getRegionInfo($place_data);
      if ($region_info['term_id']) {
        // For entity reference autocomplete fields, use the format "Term Name (Term ID)"
        $display_value = $region_info['name'] . ' (' . $region_info['term_id'] . ')';
        $populated_fields['field_region[0][target_id]'] = $display_value;
        
        $this->logger->debug('Region field mapped to term ID: @term_id (@name)', [
          '@term_id' => $region_info['term_id'],
          '@name' => $region_info['name'],
        ]);
      } else {
        $region_name = $region_info['name'] ?: 'Unknown';
        if ($region_name && $region_name !== 'Unknown') {
          $messages[] = "Region '{$region_name}' not found in system. Please create '{$region_name}' taxonomy term or select existing region manually.";
        } else {
          $messages[] = "No region information found in place data. Please select the region manually.";
        }
        $this->logger->info('Region not found: @region. Available regions need to be manually managed.', [
          '@region' => $region_name,
        ]);
      }
    }

    // Populate Place Type field
    if ($node->hasField('field_place_type')) {
      $place_type_info = $this->getPlaceTypeInfo($place_data);
      if ($place_type_info['term_id']) {
        // For entity reference autocomplete fields, use the format "Term Name (Term ID)"
        $display_value = $place_type_info['name'] . ' (' . $place_type_info['term_id'] . ')';
        $populated_fields['field_place_type[0][target_id]'] = $display_value;
        
        $this->logger->debug('Place type field mapped to term ID: @term_id (@name) - Google types: @types', [
          '@term_id' => $place_type_info['term_id'],
          '@name' => $place_type_info['name'],
          '@types' => implode(', ', $place_type_info['google_types']),
        ]);
      } else {
        $google_types = implode(', ', $place_type_info['google_types']);
        $messages[] = "Place type not mapped. Google types: $google_types. Please select category manually.";
        $this->logger->info('Place type not mapped. Google types: @types', [
          '@types' => $google_types,
        ]);
      }
    }

    return $messages;
  }

  /**
   * Get Region information from place data.
   *
   * @param array $place_data
   *   The place data from Google Places API.
   *
   * @return array
   *   Array with 'name', 'term_id' keys.
   */
  protected function getRegionInfo(array $place_data) {
    // Extract region from address components
    $region_name = null;
    
    if (!empty($place_data['address_components'])) {
      foreach ($place_data['address_components'] as $component) {
        // Look for administrative_area_level_1 (state/region)
        if (in_array('administrative_area_level_1', $component['types'])) {
          $region_name = $component['long_name'];
          break;
        }
      }
    }

    if (!$region_name) {
      $this->logger->debug('No region found in place data');
      return ['name' => null, 'term_id' => null];
    }

    // Only find existing terms, don't create new ones for regions
    $term_id = $this->findExistingTaxonomyTerm($region_name, 'region');
    
    return [
      'name' => $region_name,
      'term_id' => $term_id,
    ];
  }

  /**
   * Get Place Type information from place data.
   *
   * @param array $place_data
   *   The place data from Google Places API.
   *
   * @return array
   *   Array with 'name', 'term_id', 'google_types' keys.
   */
  protected function getPlaceTypeInfo(array $place_data) {
    // Extract place types from Google Places API
    if (empty($place_data['types'])) {
      $this->logger->debug('No place types found in place data');
      return ['name' => null, 'term_id' => null, 'google_types' => []];
    }

    $google_types = $place_data['types'];

    // Map Google place types to more user-friendly names
    $type_mapping = [
      'tourist_attraction' => 'Touristenattraktion',
      'establishment' => 'Einrichtung',
      'point_of_interest' => 'Sehenswürdigkeit',
      'museum' => 'Museum',
      'restaurant' => 'Restaurant',
      'lodging' => 'Unterkunft',
      'park' => 'Park',
      'zoo' => 'Zoo',
      'aquarium' => 'Aquarium',
      'amusement_park' => 'Freizeitpark',
    ];

    // Find the first mapped type
    foreach ($google_types as $google_type) {
      if (isset($type_mapping[$google_type])) {
        $mapped_type = $type_mapping[$google_type];
        $term_id = $this->findOrCreateTaxonomyTerm($mapped_type, 'place_types');
        return [
          'name' => $mapped_type,
          'term_id' => $term_id,
          'google_types' => $google_types,
        ];
      }
    }

    // If no mapping found, return info without creating term
    return [
      'name' => null,
      'term_id' => null,
      'google_types' => $google_types,
    ];
  }

  /**
   * Find existing taxonomy term (without creating).
   *
   * @param string $term_name
   *   The term name to find.
   * @param string $vocabulary_id
   *   The vocabulary machine name.
   *
   * @return int|null
   *   The term ID or NULL if not found.
   */
  protected function findExistingTaxonomyTerm($term_name, $vocabulary_id) {
    try {
      $existing_terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'name' => $term_name,
          'vid' => $vocabulary_id,
        ]);

      if (!empty($existing_terms)) {
        $term = reset($existing_terms);
        return $term->id();
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->error('Failed to find taxonomy term @name in @vocab: @error', [
        '@name' => $term_name,
        '@vocab' => $vocabulary_id,
        '@error' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * Find or create a taxonomy term in the specified vocabulary.
   *
   * @param string $term_name
   *   The term name to find or create.
   * @param string $vocabulary_id
   *   The vocabulary machine name.
   *
   * @return int|null
   *   The term ID or NULL if creation failed.
   */
  protected function findOrCreateTaxonomyTerm($term_name, $vocabulary_id) {
    try {
      // First, try to find existing term
      $existing_terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'name' => $term_name,
          'vid' => $vocabulary_id,
        ]);

      if (!empty($existing_terms)) {
        $term = reset($existing_terms);
        $this->logger->debug('Found existing term: @name (ID: @id)', [
          '@name' => $term_name,
          '@id' => $term->id(),
        ]);
        return $term->id();
      }

      // Create new term if not found
      $term = Term::create([
        'name' => $term_name,
        'vid' => $vocabulary_id,
      ]);
      $term->save();

      $this->logger->info('Created new taxonomy term: @name (ID: @id) in vocabulary @vocab', [
        '@name' => $term_name,
        '@id' => $term->id(),
        '@vocab' => $vocabulary_id,
      ]);

      return $term->id();

    } catch (\Exception $e) {
      $this->logger->error('Failed to find or create taxonomy term @name in @vocab: @error', [
        '@name' => $term_name,
        '@vocab' => $vocabulary_id,
        '@error' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * Format domain for link title based on subdomain logic.
   *
   * @param string $url
   *   The full URL.
   *
   * @return string
   *   Formatted domain for link title.
   */
  protected function formatDomainForLinkTitle($url) {
    // Parse the URL to get the host
    $parsed_url = parse_url($url);
    if (!isset($parsed_url['host'])) {
      return $url; // Return original if parsing fails
    }
    
    $host = $parsed_url['host'];
    
    // Split the host into parts
    $host_parts = explode('.', $host);
    
    // Need at least 2 parts for a valid domain (e.g., example.com)
    if (count($host_parts) < 2) {
      return $host;
    }
    
    // Check if there's a subdomain (more than 2 parts, or first part is not 'www')
    if (count($host_parts) > 2) {
      // Has subdomain - use as is
      return $host;
    } elseif (count($host_parts) == 2) {
      // No subdomain - add www
      return 'www.' . $host;
    } else {
      // Fallback
      return $host;
    }
  }

  /**
   * Get place details with extended fields for population.
   *
   * @param string $place_id
   *   The Google Place ID.
   *
   * @return array
   *   Result array with success status and data/error.
   */
  protected function getPlaceDetailsForPopulation($place_id) {
    $url = 'https://maps.googleapis.com/maps/api/place/details/json';
    
    $params = [
      'place_id' => $place_id,
      'fields' => 'name,formatted_address,geometry,opening_hours,formatted_phone_number,website,types,address_components',
      'language' => 'de', // German language
      'key' => $this->apiKey,
    ];

    try {
      $response = $this->httpClient->get($url, ['query' => $params]);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if ($data['status'] === 'OK') {
        return [
          'success' => TRUE,
          'data' => $data['result'],
        ];
      } else {
        $error = $data['error_message'] ?? $data['status'];
        $this->logger->error('Google Places API error: @error', ['@error' => $error]);
        
        return [
          'success' => FALSE,
          'error' => $error,
        ];
      }
    } catch (RequestException $e) {
      $this->logger->error('HTTP request failed: @error', ['@error' => $e->getMessage()]);
      
      return [
        'success' => FALSE,
        'error' => 'Failed to connect to Google Places API: ' . $e->getMessage(),
      ];
    }
  }
}
