<?php

// Create video media bundle
try {
  $media_type = \Drupal\media\Entity\MediaType::create([
    'id' => 'video',
    'label' => 'Video',
    'description' => 'Video media for embedding YouTube, Vimeo, etc.',
    'source' => 'video_embed_field'
  ]);
  $media_type->save();
  echo "✅ Video media bundle created\n";
} catch (Exception $e) {
  echo "❌ Error creating video media bundle: " . $e->getMessage() . "\n";
}

// Create video paragraph bundle
try {
  $para_type = \Drupal\paragraphs\Entity\ParagraphsType::create([
    'id' => 'video',
    'label' => 'Video',
    'description' => 'Video paragraph with media reference'
  ]);
  $para_type->save();
  echo "✅ Video paragraph bundle created\n";
} catch (Exception $e) {
  echo "❌ Error creating video paragraph bundle: " . $e->getMessage() . "\n";
}

// Create field storage if it doesn't exist
try {
  $field_storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('paragraph', 'field_media');
  if (!$field_storage) {
    $field_storage = \Drupal\field\Entity\FieldStorageConfig::create([
      'field_name' => 'field_media',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media']
    ]);
    $field_storage->save();
    echo "✅ field_media storage created\n";
  } else {
    echo "✅ field_media storage already exists\n";
  }
} catch (Exception $e) {
  echo "❌ Error creating field storage: " . $e->getMessage() . "\n";
}

// Add field_media to video paragraph
try {
  $field_config = \Drupal\field\Entity\FieldConfig::create([
    'field_name' => 'field_media',
    'entity_type' => 'paragraph',
    'bundle' => 'video',
    'label' => 'Media',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:media',
      'handler_settings' => [
        'target_bundles' => ['video' => 'video']
      ]
    ]
  ]);
  $field_config->save();
  echo "✅ field_media added to video paragraph\n";
} catch (Exception $e) {
  echo "❌ Error adding field to video paragraph: " . $e->getMessage() . "\n";
}

echo "\n��� Bundle creation complete!\n";
