<?php

namespace Drupal\google_places;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

class PlacesPhotoImporter {

  protected ClientInterface $httpClient;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileSystemInterface $fileSystem;

  public function __construct(ClientInterface $http_client, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  public function importPhotos(string $place_id, string $place_name = ''): array {
    $imported_media = [];
    
    try {
      $places_service = \Drupal::service('google_places.json_consumer');
      $photos = $places_service->getPhotos($place_id);
      
      if (empty($photos)) {
        return $imported_media;
      }

      // Import only the first photo to field_teaser_media
      $photo = $photos[0];
      $media_entity = $this->downloadAndCreateMedia($photo, $place_name);
      
      if ($media_entity) {
        $imported_media[] = $media_entity->id();
      }

    } catch (\Exception $e) {
      \Drupal::logger('google_places')->error('Error importing photos: @message', ['@message' => $e->getMessage()]);
    }

    return $imported_media;
  }

  private function downloadAndCreateMedia(array $photo, string $place_name): ?Media {
    try {
      // Download the image
      $response = $this->httpClient->request('GET', $photo['url']);
      $image_data = $response->getBody()->getContents();

      if (empty($image_data)) {
        return NULL;
      }

      // Create a unique filename
      $filename = $this->createFilename($place_name, $photo['reference']);
      $destination = 'public://google_places/' . $filename;

      // Ensure the directory exists
      $directory = dirname($destination);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Save the file
      $file_uri = $this->fileSystem->saveData($image_data, $destination, FileSystemInterface::EXISTS_REPLACE);
      
      if (!$file_uri) {
        return NULL;
      }

      // Create a file entity
      $file = File::create([
        'uri' => $file_uri,
        'status' => 1,
        'uid' => \Drupal::currentUser()->id(),
      ]);
      $file->save();

      // Create a media entity
      $media = Media::create([
        'bundle' => 'image',
        'name' => !empty($place_name) ? $place_name . ' - Google Places Image' : 'Google Places Image',
        'field_media_image' => [
          'target_id' => $file->id(),
          'alt' => !empty($place_name) ? $place_name : 'Google Places Image',
        ],
        'status' => 1,
        'uid' => \Drupal::currentUser()->id(),
      ]);
      
      $media->save();
      
      return $media;

    } catch (RequestException $e) {
      \Drupal::logger('google_places')->error('Error downloading image: @message', ['@message' => $e->getMessage()]);
      return NULL;
    } catch (\Exception $e) {
      \Drupal::logger('google_places')->error('Error creating media: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  private function createFilename(string $place_name, string $reference): string {
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $place_name);
    $safe_name = trim($safe_name, '_');
    
    if (empty($safe_name)) {
      $safe_name = 'google_place';
    }
    
    $short_ref = substr($reference, 0, 8);
    return $safe_name . '_' . $short_ref . '_' . time() . '.jpg';
  }

}
