# Google Places API - German Language Configuration

## Language Parameter Requirements

The Google Places API supports localization through the `language` parameter. To ensure all place data is retrieved in German, you must include `language=de` in all API requests.

## API Request Configuration

### Place Details API with German Language
```bash
curl "https://maps.googleapis.com/maps/api/place/details/json?place_id=PLACE_ID&fields=name,formatted_address,international_phone_number,website,rating,reviews&language=de&key=API_KEY"
```

### Place Search API with German Language
```bash
curl "https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=LAT,LNG&radius=RADIUS&language=de&key=API_KEY"
```

### Text Search API with German Language
```bash
curl "https://maps.googleapis.com/maps/api/place/textsearch/json?query=SEARCH_TERM&language=de&key=API_KEY"
```

## Language Parameter Details

### Parameter: `language`
- **Value**: `de` (for German)
- **Required**: No (defaults to English if not specified)
- **Affects**: 
  - Place names
  - Formatted addresses
  - Review text
  - Business categories
  - Other localized content

### ISO 639-1 Language Codes for German
- `de` - German (standard)
- `de-DE` - German (Germany) - more specific
- `de-AT` - German (Austria)
- `de-CH` - German (Switzerland)

**Recommendation**: Use `de` for general German localization.

## Implementation in Drupal Module

### Configuration Setting
Add language configuration to your Drupal settings or module configuration:

```php
// In settings.php or module configuration
$settings['google_places_language'] = 'de';
```

### Example Module Implementation
```php
<?php

/**
 * Google Places API service with German language support.
 */
class GooglePlacesService {

  protected $apiKey;
  protected $language;
  
  public function __construct() {
    $this->apiKey = \Drupal::config('google_places.settings')->get('api_key');
    $this->language = \Drupal::config('google_places.settings')->get('language') ?: 'de';
  }
  
  /**
   * Get place details in German.
   */
  public function getPlaceDetails($placeId, $fields = []) {
    $url = 'https://maps.googleapis.com/maps/api/place/details/json';
    $params = [
      'place_id' => $placeId,
      'fields' => implode(',', $fields),
      'language' => $this->language,
      'key' => $this->apiKey,
    ];
    
    $response = $this->makeRequest($url, $params);
    return $response;
  }
  
  /**
   * Search places in German.
   */
  public function searchPlaces($query, $location = null, $radius = null) {
    $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
    $params = [
      'query' => $query,
      'language' => $this->language,
      'key' => $this->apiKey,
    ];
    
    if ($location) {
      $params['location'] = $location;
    }
    if ($radius) {
      $params['radius'] = $radius;
    }
    
    $response = $this->makeRequest($url, $params);
    return $response;
  }
}
```

## Testing German Language Responses

### Test Command with Real Place ID
```bash
# Test with a German place (Berlin Brandenburg Gate)
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&fields=name,formatted_address,types&language=de&key=YOUR_API_KEY" | jq '{name: .result.name, address: .result.formatted_address, types: .result.types}'
```

### Expected German Response Example
```json
{
  "name": "Brandenburger Tor",
  "address": "Pariser Platz, 10117 Berlin, Deutschland",
  "types": [
    "tourist_attraction",
    "point_of_interest",
    "establishment"
  ]
}
```

### Comparison with English Response
```bash
# Same place without language parameter (defaults to English)
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&fields=name,formatted_address&key=YOUR_API_KEY" | jq '{name: .result.name, address: .result.formatted_address}'
```

Expected English response:
```json
{
  "name": "Brandenburg Gate",
  "address": "Pariser Platz, 10117 Berlin, Germany"
}
```

## Fields Affected by Language Parameter

### Localized Fields
- ✅ **name** - Business/place name
- ✅ **formatted_address** - Complete address
- ✅ **vicinity** - Neighborhood/area description
- ✅ **reviews.text** - Review content (if reviewer wrote in German)
- ✅ **types** - Place categories (some may be localized)

### Non-localized Fields
- ❌ **place_id** - Always same format
- ❌ **geometry** - Coordinates unchanged
- ❌ **photos** - Photo references unchanged
- ❌ **opening_hours** - Hours format may vary but times are same
- ❌ **rating** - Numeric values unchanged

## Configuration Checklist

### ✅ Module Configuration
- [ ] Add language setting to module configuration form
- [ ] Set default language to 'de' in module settings
- [ ] Allow administrators to override language setting
- [ ] Include language parameter in all API requests

### ✅ Database Storage Considerations
- [ ] Consider storing language code with place data
- [ ] Handle multilingual content if needed
- [ ] Plan for potential language switching

### ✅ User Interface
- [ ] Display German place names and addresses
- [ ] Handle special German characters (ä, ö, ü, ß)
- [ ] Consider date/time formatting for German locale

## Current API Key Configuration

Based on your settings.php:
```php
$settings['maps_api_key'] = 'AIzaSyBLzZBWyuCUqSjX_YDlG0JgJOL0dX3-7-I';
```

### Test Current API Key with German Language
```bash
cd /var/www/drupal11
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&fields=name,formatted_address&language=de&key=AIzaSyBLzZBWyuCUqSjX_YDlG0JgJOL0dX3-7-I"
```

## Implementation Priority

### High Priority
1. **Modify existing API calls** to include `language=de`
2. **Test current functionality** with German language parameter
3. **Verify German responses** are returned correctly

### Medium Priority
1. **Add configuration option** for language selection
2. **Update error handling** for German error messages
3. **Consider German-specific formatting**

### Low Priority
1. **Multilingual support** for different German regions
2. **User preference** for language selection
3. **Fallback handling** if German not available

## Troubleshooting German Language Issues

### Common Issues
1. **Missing language parameter**: Responses default to English
2. **Invalid language code**: API ignores invalid codes
3. **Mixed language responses**: Some content may not be available in German
4. **Character encoding**: Ensure UTF-8 handling for special characters

### Debug Commands
```bash
# Test if API key supports German language
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&language=de&key=YOUR_API_KEY" | jq '.status'

# Compare German vs English responses
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=PLACE_ID&fields=name&language=de&key=YOUR_API_KEY" | jq '.result.name'
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=PLACE_ID&fields=name&language=en&key=YOUR_API_KEY" | jq '.result.name'
```

---
*This configuration ensures all Google Places data is retrieved in German language for the Drupal module.*
