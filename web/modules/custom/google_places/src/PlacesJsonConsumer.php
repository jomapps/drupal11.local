<?php

namespace Drupal\google_places;

use Drupal\Component\Utility\Html;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

class PlacesJsonConsumer {

  protected ClientInterface $httpClient;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  public function getAutocomplete(string $input): array {
    $api_key = Settings::get('maps_api_key_open');
    if (empty($api_key) || empty($input)) {
      return [];
    }

    $query = [
      'key' => $api_key,
      'language' => 'de',
      'components' => 'country:de',
      'input' => $input,
    ];

    $uri = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    try {
      $response = $this->httpClient->request('GET', $uri, ['query' => $query]);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if ($data['status'] === 'OK' && !empty($data['predictions'])) {
        $matches = [];
        foreach ($data['predictions'] as $prediction) {
          if (!empty($prediction['description']) && !empty($prediction['place_id'])) {
            $matches[] = [
              'value' => Html::escape($prediction['place_id']),
              'label' => Html::escape($prediction['description']),
            ];
          }
        }
        return $matches;
      }
    } catch (RequestException $e) {
      \Drupal::logger('google_places')->error('Error fetching autocomplete: @message', ['@message' => $e->getMessage()]);
    }

    return [];
  }

  public function getDetails(string $place_id): array {
    $api_key = Settings::get('maps_api_key_open');
    if (empty($api_key) || empty($place_id)) {
      return [];
    }

    $query = [
      'key' => $api_key,
      'language' => 'de',
      'region' => 'de',
      'placeid' => $place_id,
      'fields' => 'address_components,formatted_phone_number,formatted_address,geometry/location,name,url,website,opening_hours/weekday_text,types,photos',
    ];

    $uri = 'https://maps.googleapis.com/maps/api/place/details/json';

    try {
      $response = $this->httpClient->request('GET', $uri, ['query' => $query]);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if ($data['status'] === 'OK' && !empty($data['result'])) {
        return $this->mapPlaceDetails($data['result']);
      }
    } catch (RequestException $e) {
      \Drupal::logger('google_places')->error('Error fetching place details: @message', ['@message' => $e->getMessage()]);
    }

    return [];
  }

  public function getPhotos(string $place_id): array {
    $api_key = Settings::get('maps_api_key_open');
    if (empty($api_key) || empty($place_id)) {
      return [];
    }

    // First get place details to get photo references
    $details = $this->getPlaceDetailsRaw($place_id);
    if (empty($details['photos'])) {
      return [];
    }

    $photos = [];
    foreach ($details['photos'] as $photo) {
      if (!empty($photo['photo_reference'])) {
        $photo_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=800&photoreference={$photo['photo_reference']}&key={$api_key}";
        $photos[] = [
          'reference' => $photo['photo_reference'],
          'url' => $photo_url,
          'width' => $photo['width'] ?? 800,
          'height' => $photo['height'] ?? 600,
        ];
      }
    }

    return $photos;
  }

  private function getPlaceDetailsRaw(string $place_id): array {
    $api_key = Settings::get('maps_api_key_open');
    $query = [
      'key' => $api_key,
      'language' => 'de',
      'region' => 'de',
      'placeid' => $place_id,
      'fields' => 'photos',
    ];

    $uri = 'https://maps.googleapis.com/maps/api/place/details/json';

    try {
      $response = $this->httpClient->request('GET', $uri, ['query' => $query]);
      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $data['result'] ?? [];
    } catch (RequestException $e) {
      \Drupal::logger('google_places')->error('Error fetching place photos: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  private function mapPlaceDetails(array $result): array {
    $details = [];

    // Basic information
    if (!empty($result['name'])) {
      $details[] = ['name' => 'title[0][value]', 'value' => $result['name']];
      $details[] = ['name' => 'field_company[0][value]', 'value' => $result['name']];
    }

    // Contact information
    if (!empty($result['formatted_phone_number'])) {
      $details[] = ['name' => 'field_phone[0][value]', 'value' => $result['formatted_phone_number']];
    }

    if (!empty($result['website'])) {
      $details[] = ['name' => 'field_url[0][uri]', 'value' => $result['website']];
    }

    // Location information
    if (!empty($result['geometry']['location']['lat'])) {
      $details[] = ['name' => 'field_latitude[0][value]', 'value' => $result['geometry']['location']['lat']];
    }

    if (!empty($result['geometry']['location']['lng'])) {
      $details[] = ['name' => 'field_longitude[0][value]', 'value' => $result['geometry']['location']['lng']];
    }

    if (!empty($result['formatted_address'])) {
      $details[] = ['name' => 'field_formatted_address[0][value]', 'value' => $result['formatted_address']];
    }

    // Generate Google Maps URL
    if (!empty($result['geometry']['location']['lat']) && !empty($result['geometry']['location']['lng'])) {
      $lat = $result['geometry']['location']['lat'];
      $lng = $result['geometry']['location']['lng'];
      $google_maps_url = "https://www.google.com/maps/place/{$lat},{$lng}";
      $details[] = ['name' => 'field_google_map_url[0][uri]', 'value' => $google_maps_url];
    }

    // Opening hours
    if (!empty($result['opening_hours']['weekday_text'])) {
      $opening_hours = implode("\n", $result['opening_hours']['weekday_text']);
      $details[] = ['name' => 'field_opening_hours[0][value]', 'value' => $opening_hours];
    }

    // Address components for structured address
    if (!empty($result['address_components'])) {
      $address_parts = $this->parseAddressComponents($result['address_components']);
      if (!empty($address_parts['full_address'])) {
        $details[] = ['name' => 'field_address[0][address_line1]', 'value' => $address_parts['street'] ?? ''];
        $details[] = ['name' => 'field_address[0][locality]', 'value' => $address_parts['city'] ?? ''];
        $details[] = ['name' => 'field_address[0][postal_code]', 'value' => $address_parts['postal_code'] ?? ''];
        $details[] = ['name' => 'field_address[0][country_code]', 'value' => $address_parts['country_code'] ?? 'DE'];
      }
    }

    return array_filter($details, fn($item) => !empty($item['value']));
  }

  private function parseAddressComponents(array $components): array {
    $parsed = [
      'street' => '',
      'city' => '',
      'postal_code' => '',
      'country_code' => 'DE',
    ];

    foreach ($components as $component) {
      $types = $component['types'] ?? [];
      
      if (in_array('street_number', $types) || in_array('route', $types)) {
        $parsed['street'] .= $component['long_name'] . ' ';
      }
      
      if (in_array('locality', $types)) {
        $parsed['city'] = $component['long_name'];
      }
      
      if (in_array('postal_code', $types)) {
        $parsed['postal_code'] = $component['long_name'];
      }
      
      if (in_array('country', $types)) {
        $parsed['country_code'] = $component['short_name'];
      }
    }

    $parsed['street'] = trim($parsed['street']);
    $parsed['full_address'] = !empty($parsed['street']) || !empty($parsed['city']);

    return $parsed;
  }

}
