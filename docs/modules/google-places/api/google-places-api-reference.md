# Google Places API Reference

## Current API Configuration

### API Keys (from settings.php)
```php
$settings['maps_api_key'] = 'AIzaSyBLzZBWyuCUqSjX_YDlG0JgJOL0dX3-7-I';
$settings['maps_api_key_open'] = 'AIzaSyBLzZBWyuCUqSjX_YDlG0JgJOL0dX3-7-I';
```

**Note**: Both keys are identical. Consider separating for different use cases.

## Google Places Photos API

### Base URL
```
https://maps.googleapis.com/maps/api/place/photo
```

### Required Parameters
- `photoreference`: Photo reference from Place Details response
- `key`: API key
- `maxwidth` OR `maxheight`: Maximum dimension (1-1600)

### Example Request (with German Language)
```bash
curl "https://maps.googleapis.com/maps/api/place/photo?photoreference=PHOTO_REFERENCE&key=API_KEY&maxwidth=400"
```

**Note**: Photos API doesn't support language parameter - images are universal. However, all text-based APIs should include `language=de` for German responses.

### Response
- Returns the actual image data (JPEG format)
- HTTP 200 for success
- Various error codes for failures

## Common API Endpoints

### Place Details (German Language)
```
GET https://maps.googleapis.com/maps/api/place/details/json
```
**Parameters**:
- `place_id`: Google Place ID (e.g., ChIJ-8pgKuGOsUcRO6gVZxdzOus)
- `fields`: Comma-separated list (name,photos,rating,etc.)
- `language`: `de` (for German responses)
- `key`: API key

**Example**:
```bash
curl "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJ-8pgKuGOsUcRO6gVZxdzOus&fields=name,formatted_address&language=de&key=API_KEY"
```

### Place Search
```
GET https://maps.googleapis.com/maps/api/place/nearbysearch/json
```
**Parameters**:
- `location`: lat,lng coordinates
- `radius`: Search radius in meters
- `key`: API key

## Error Codes and Meanings

### API Response Status Codes
| Status | Meaning | Resolution |
|--------|---------|------------|
| `OK` | Success | Continue processing |
| `ZERO_RESULTS` | No results found | Check search parameters |
| `OVER_QUERY_LIMIT` | Rate limit exceeded | Implement delay/backoff |
| `REQUEST_DENIED` | API key invalid/restricted | Check API key settings |
| `INVALID_REQUEST` | Required parameter missing | Validate request format |
| `NOT_FOUND` | Place/photo not found | Verify place ID/photo reference |

### HTTP Status Codes
| Code | Meaning | Common Causes |
|------|---------|---------------|
| 200 | Success | Request successful |
| 400 | Bad Request | Invalid parameters |
| 403 | Forbidden | API key restrictions |
| 404 | Not Found | Invalid endpoint/resource |
| 429 | Too Many Requests | Rate limiting |
| 500 | Server Error | Google API issues |

## API Key Restrictions (Google Cloud Console)

### Application Restrictions
- **HTTP referrers**: Domain-based restrictions
- **IP addresses**: Server IP restrictions
- **Android apps**: Package name restrictions
- **iOS apps**: Bundle ID restrictions

### API Restrictions
- **Places API**: Basic place data
- **Places Details API**: Detailed place information
- **Places Photos API**: Access to place photos ⚠️
- **Maps JavaScript API**: Frontend map display

### Common Restriction Issues
1. **Missing Places Photos API**: Most common cause of image fetch failures
2. **HTTP Referrer Mismatch**: Domain not in allowed list
3. **IP Restriction**: Server IP not whitelisted
4. **Daily Quota Exceeded**: Usage limits reached

## Testing API Access

### Test Place Details Request
```bash
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJ-8pgKuGOsUcRO6gVZxdzOus&fields=name,photos&key=YOUR_API_KEY" | jq .
```

### Test Photo Download
```bash
# Get photo reference from place details first
PHOTO_REF="YOUR_PHOTO_REFERENCE"
curl -s "https://maps.googleapis.com/maps/api/place/photo?photoreference=$PHOTO_REF&key=YOUR_API_KEY&maxwidth=400" --output test-image.jpg
```

### Verify API Key Permissions
```bash
# Test basic Places API access
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJN1t_tDeuEmsRUsoyG83frY4&key=YOUR_API_KEY" | jq '.status'
```

## Rate Limits and Quotas

### Standard Quotas (as of 2024)
- **Places Details**: 100,000 requests/day
- **Places Photos**: No separate limit (counted as Details requests)
- **Rate Limit**: 50 requests/second

### Best Practices
1. **Implement Caching**: Store place data locally
2. **Request Only Needed Fields**: Reduce API calls
3. **Use Batch Requests**: When possible
4. **Monitor Quotas**: Set up alerts in Google Cloud Console

## Expected File Formats

### Place Directory Naming
- Format: `ChIJ-*` (Google Place ID format)
- Example: `ChIJ-8pgKuGOsUcRO6gVZxdzOus`

### Image File Naming
- Format: MD5 hash of original URL or timestamp
- Example: `0b9683ff7f477e9f1eabad44b1e27a2a.jpg`
- Typical Size: 100KB - 500KB

## Integration Points

### Drupal Module Integration
- Custom field type for place selection
- Background job for image downloading
- Error handling and retry mechanisms
- Cache management for API responses

### Database Storage
- Place ID storage in content entity
- Photo reference caching
- Download status tracking
- Error logging

## Security Considerations

### API Key Security
- ❌ **Current**: Exposed in settings.php
- ✅ **Recommended**: Environment variables
- ✅ **Best Practice**: Separate keys for dev/staging/prod

### File Storage Security
- Ensure proper file permissions
- Validate image file types
- Implement access controls
- Regular cleanup of unused images

## Troubleshooting Checklist

1. ✅ **API Key Valid**: Test with curl
2. ✅ **Places Photos API Enabled**: Check Google Cloud Console
3. ✅ **Quotas Available**: Monitor usage
4. ✅ **Network Access**: Verify outbound HTTPS
5. ✅ **File Permissions**: Check write access to places directory
6. ✅ **Place ID Format**: Verify ChIJ format
7. ✅ **Photo Reference**: Check if still valid (can expire)

---
*This reference will be updated as we discover more about the specific implementation and resolve the image fetching issues.*
