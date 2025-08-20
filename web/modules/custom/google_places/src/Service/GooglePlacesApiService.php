<?php

namespace Drupal\google_places\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
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
    $this->apiKey = \Drupal::settings()->get('maps_api_key');
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
    $place_id = $this->getPlaceIdFromNode($node);
    
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
      $populated_fields['field_phone[0][value]'] = $place_data['formatted_phone_number'];
    }

    // Populate website URL
    if (!empty($place_data['website']) && $node->hasField('field_url')) {
      $populated_fields['field_url[0][uri]'] = $place_data['website'];
    }

    // Populate opening hours
    if (!empty($place_data['opening_hours']['weekday_text']) && $node->hasField('field_opening_hours')) {
      $hours_text = implode("\n", $place_data['opening_hours']['weekday_text']);
      $populated_fields['field_opening_hours[0][value]'] = $hours_text;
    }

    $this->logger->info('Successfully populated place data for place @place_id', [
      '@place_id' => $place_id,
    ]);

    return [
      'success' => TRUE,
      'populated_fields' => $populated_fields,
    ];
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
      'fields' => 'name,formatted_address,geometry,opening_hours,formatted_phone_number,website',
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
