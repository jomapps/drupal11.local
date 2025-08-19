<?php

namespace Drupal\google_places\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\google_places\PlacesJsonConsumer;
use Drupal\google_places\PlacesPhotoImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TestController extends ControllerBase {

  protected PlacesJsonConsumer $placesService;
  protected PlacesPhotoImporter $photoImporter;

  public function __construct(PlacesJsonConsumer $places_service, PlacesPhotoImporter $photo_importer) {
    $this->placesService = $places_service;
    $this->photoImporter = $photo_importer;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('google_places.json_consumer'),
      $container->get('google_places.photo_importer')
    );
  }

  public function test() {
    return new JsonResponse([
      'status' => 'success',
      'message' => 'Google Places module is working on production',
      'drupal_version' => \Drupal::VERSION,
      'timestamp' => time(),
    ]);
  }

  public function autocomplete(Request $request): JsonResponse {
    $matches = $this->placesService->getAutocomplete($request->query->get('q', ''));
    return new JsonResponse($matches);
  }

  public function detail(Request $request): JsonResponse {
    $details = $this->placesService->getDetails($request->query->get('place_id', ''));
    return new JsonResponse($details);
  }

  public function debugPhoto(Request $request): JsonResponse {
    $place_id = $request->query->get('place_id', 'ChIJN35pEUhWqEcRJGFKE_x5IEI');
    
    try {
      \Drupal::logger('google_places')->info('Debug: Testing photo import for place_id: @place_id', ['@place_id' => $place_id]);
      
      $imported_media = $this->photoImporter->importPhotos($place_id, 'Test Place');
      
      return new JsonResponse([
        'status' => 'success',
        'place_id' => $place_id,
        'imported_media' => $imported_media,
        'count' => count($imported_media),
        'message' => 'Photo import test completed'
      ]);
      
    } catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
    }
  }

  public function importPhotoAndSave(Request $request): JsonResponse {
    try {
      // Log all received data for debugging
      $all_data = $request->request->all();
      \Drupal::logger('google_places')->info('Received POST data: @data', [
        '@data' => print_r($all_data, TRUE)
      ]);

      $place_id = $request->request->get('place_id', '');
      $node_id = $request->request->get('node_id', '');
      
      \Drupal::logger('google_places')->info('Extracted: place_id=@place_id, node_id=@node_id', [
        '@place_id' => $place_id,
        '@node_id' => $node_id
      ]);
      
      if (empty($place_id)) {
        return new JsonResponse(['error' => 'Place ID is required'], 400);
      }

      // Get the basic info we need from form data
      $title = $this->getFormValue($request, 'title[0][value]', 'Untitled Place');
      $company = $this->getFormValue($request, 'field_company[0][value]', $title);
      
      \Drupal::logger('google_places')->info('Extracted form values: title=@title, company=@company', [
        '@title' => $title,
        '@company' => $company
      ]);
      
      \Drupal::logger('google_places')->info('Starting photo import for place_id: @place_id', [
        '@place_id' => $place_id,
      ]);

      // Import the photo first
      $imported_media = $this->photoImporter->importPhotos($place_id, $company);
      
      if (empty($imported_media)) {
        \Drupal::logger('google_places')->warning('No photos found for place_id: @place_id', ['@place_id' => $place_id]);
        return new JsonResponse(['error' => 'No photos found for this place'], 404);
      }

      $media_id = $imported_media[0];
      \Drupal::logger('google_places')->info('Photo imported successfully, media_id: @media_id', ['@media_id' => $media_id]);

      // Create a simple node with the key fields
      $node = $this->createSimpleNode($request, $media_id);
      
      if ($node) {
        \Drupal::logger('google_places')->info('Node saved successfully, node_id: @node_id', ['@node_id' => $node->id()]);
        
        return new JsonResponse([
          'status' => 'success',
          'message' => 'Node saved and photo attached successfully',
          'node_id' => $node->id(),
          'media_id' => $media_id,
          'node_url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        ]);
      } else {
        return new JsonResponse(['error' => 'Failed to save node'], 500);
      }

    } catch (\Exception $e) {
      \Drupal::logger('google_places')->error('Error in importPhotoAndSave: @message, trace: @trace', [
        '@message' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);
      return new JsonResponse(['error' => 'Internal server error: ' . $e->getMessage()], 500);
    }
  }

  private function getFormValue(Request $request, string $key, string $default = ''): string {
    $form_data = $request->request->all();
    
    // Log what we're looking for
    \Drupal::logger('google_places')->info('Looking for key: @key in form data', ['@key' => $key]);
    
    // Try direct key first
    if (isset($form_data[$key]) && !empty($form_data[$key])) {
      $value = is_string($form_data[$key]) ? urldecode($form_data[$key]) : (string) $form_data[$key];
      \Drupal::logger('google_places')->info('Found direct key @key = @value', ['@key' => $key, '@value' => $value]);
      return $value;
    }
    
    // Try form_data array
    if (isset($form_data['form_data']) && is_array($form_data['form_data'])) {
      if (isset($form_data['form_data'][$key]) && !empty($form_data['form_data'][$key])) {
        $value = is_string($form_data['form_data'][$key]) ? urldecode($form_data['form_data'][$key]) : (string) $form_data['form_data'][$key];
        \Drupal::logger('google_places')->info('Found in form_data array @key = @value', ['@key' => $key, '@value' => $value]);
        return $value;
      }
    }
    
    // Try searching all keys that contain our pattern
    foreach ($form_data as $form_key => $form_value) {
      if (strpos($form_key, $key) !== FALSE) {
        $value = is_string($form_value) ? urldecode($form_value) : (string) $form_value;
        \Drupal::logger('google_places')->info('Found similar key @form_key = @value for search @key', ['@form_key' => $form_key, '@value' => $value, '@key' => $key]);
        return $value;
      }
    }
    
    \Drupal::logger('google_places')->info('Key @key not found, using default: @default', ['@key' => $key, '@default' => $default]);
    return $default;
  }

  private function createSimpleNode(Request $request, int $media_id) {
    try {
      \Drupal::logger('google_places')->info('Creating node with media_id: @media_id', ['@media_id' => $media_id]);
      
      $node_storage = $this->entityTypeManager()->getStorage('node');
      
      // Create new node with essential fields
      $title = $this->getFormValue($request, 'title[0][value]', 'Google Places Import');
      
      $node = $node_storage->create([
        'type' => 'place',
        'status' => 1,
        'uid' => $this->currentUser()->id(),
        'title' => $title,
      ]);

      \Drupal::logger('google_places')->info('Node created with title: @title', ['@title' => $title]);

      // Set the key fields we know work
      $this->setSimpleField($node, 'field_place_id', $this->getFormValue($request, 'field_place_id[0][value]'));
      $this->setSimpleField($node, 'field_company', $this->getFormValue($request, 'field_company[0][value]'));
      $this->setSimpleField($node, 'field_formatted_address', $this->getFormValue($request, 'field_formatted_address[0][value]'));
      $this->setSimpleField($node, 'field_latitude', $this->getFormValue($request, 'field_latitude[0][value]'));
      $this->setSimpleField($node, 'field_longitude', $this->getFormValue($request, 'field_longitude[0][value]'));
      
      // Handle URL field (link field type)
      $url_value = $this->getFormValue($request, 'field_url[0][uri]');
      if (!empty($url_value) && $node->hasField('field_url')) {
        $node->set('field_url', ['uri' => $url_value]);
        \Drupal::logger('google_places')->info('Set field_url: @url', ['@url' => $url_value]);
      }
      
      // Handle Google Maps URL field (link field type)
      $gmap_url = $this->getFormValue($request, 'field_google_map_url[0][uri]');
      if (!empty($gmap_url) && $node->hasField('field_google_map_url')) {
        $node->set('field_google_map_url', ['uri' => $gmap_url]);
        \Drupal::logger('google_places')->info('Set field_google_map_url: @url', ['@url' => $gmap_url]);
      }

      // Set the teaser media
      if ($node->hasField('field_teaser_media')) {
        $node->set('field_teaser_media', ['target_id' => $media_id]);
        \Drupal::logger('google_places')->info('Set field_teaser_media: @media_id', ['@media_id' => $media_id]);
      }

      // Save the node
      \Drupal::logger('google_places')->info('Attempting to save node...');
      $result = $node->save();
      \Drupal::logger('google_places')->info('Node save result: @result', ['@result' => $result]);
      
      return $result ? $node : NULL;

    } catch (\Exception $e) {
      \Drupal::logger('google_places')->error('Error creating node: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  private function setSimpleField($node, string $field_name, string $value) {
    if (!empty($value) && $node->hasField($field_name)) {
      $node->set($field_name, $value);
      \Drupal::logger('google_places')->info('Set @field = @value', ['@field' => $field_name, '@value' => $value]);
    }
  }

}
