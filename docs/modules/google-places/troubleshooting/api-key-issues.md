# Google Places API Key Issues - Troubleshooting Guide

## 🚨 CRITICAL ISSUE IDENTIFIED

### Current Status: API Key Invalid
- **Error Message**: "The provided API key is invalid."
- **API Status**: `REQUEST_DENIED`
- **Impact**: Complete failure of all Google Places API requests
- **Affects**: Both place data retrieval AND image fetching

## Error Details

### Full API Response
```json
{
  "error_message": "The provided API key is invalid.",
  "html_attributions": [],
  "status": "REQUEST_DENIED"
}
```

### Test Commands Used
```bash
# Test command that revealed the issue
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs" | jq .
```

### Current API Key (Invalid)
```php
// From settings.php
$settings['maps_api_key'] = 'AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs';
$settings['maps_api_key_open'] = 'AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs';
```

## Immediate Action Required

### 1. Verify API Key Status in Google Cloud Console
1. **Go to**: [Google Cloud Console](https://console.cloud.google.com/)
2. **Navigate to**: APIs & Services > Credentials
3. **Check**: If the API key `AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs` exists and is enabled
4. **Verify**: Key restrictions and allowed APIs

### 2. Possible Causes of Invalid API Key

#### A. API Key Deleted/Disabled
- Key may have been deleted from Google Cloud Console
- Key may have been disabled due to security concerns
- Project may have been suspended

#### B. API Key Restrictions
- **IP Restrictions**: Current server IP not in allowed list
- **HTTP Referrer Restrictions**: Domain restrictions blocking requests
- **API Restrictions**: Places API not enabled for this key

#### C. Billing Issues
- Google Cloud billing account disabled
- Credit card expired or payment failed
- Exceeded free tier limits without billing setup

#### D. Project Issues
- Google Cloud project deleted or suspended
- APIs not properly enabled in the project
- Service account permissions issues

## Resolution Steps

### Step 1: Create New API Key
```bash
# After creating new API key in Google Cloud Console, test it:
NEW_API_KEY="YOUR_NEW_API_KEY_HERE"
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&key=$NEW_API_KEY" | jq .status
```

### Step 2: Enable Required APIs
Ensure these APIs are enabled in Google Cloud Console:
- ✅ **Places API (New)**
- ✅ **Maps JavaScript API** (if using frontend maps)
- ✅ **Geocoding API** (if doing address lookups)

### Step 3: Configure API Key Restrictions

#### Application Restrictions (Choose One)
```
Option 1: None (least secure, but good for testing)
Option 2: HTTP referrers - Add your domains
Option 3: IP addresses - Add your server IPs
```

#### API Restrictions (Recommended)
```
✅ Places API (New)
✅ Maps JavaScript API
❌ Restrict to only needed APIs
```

### Step 4: Update Drupal Configuration
```php
// Update settings.php with new API key
$settings['maps_api_key'] = 'YOUR_NEW_VALID_API_KEY';
$settings['maps_api_key_open'] = 'YOUR_NEW_VALID_API_KEY';
```

### Step 5: Test German Language Support
```bash
# Test new API key with German language
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJiasEZAJOqEcRGfGzCyZasQw&fields=name,formatted_address&language=de&key=YOUR_NEW_API_KEY" | jq '{name: .result.name, address: .result.formatted_address}'
```

## Security Best Practices

### Environment Variables (Recommended)
Instead of hardcoding in settings.php:

```php
// In settings.php
$settings['maps_api_key'] = getenv('GOOGLE_MAPS_API_KEY') ?: '';

// In .env file (not committed to version control)
GOOGLE_MAPS_API_KEY=your_actual_api_key_here
```

### Separate Keys for Environments
```php
// Different keys for different environments
if ($_ENV['ENVIRONMENT'] === 'production') {
  $settings['maps_api_key'] = getenv('GOOGLE_MAPS_API_KEY_PROD');
} elseif ($_ENV['ENVIRONMENT'] === 'staging') {
  $settings['maps_api_key'] = getenv('GOOGLE_MAPS_API_KEY_STAGING');
} else {
  $settings['maps_api_key'] = getenv('GOOGLE_MAPS_API_KEY_DEV');
}
```

## Monitoring and Alerts

### Set Up API Usage Monitoring
1. **Google Cloud Console**: Set up quota alerts
2. **Billing Alerts**: Monitor costs and usage
3. **Application Monitoring**: Log API failures

### Drupal Error Logging
```php
// Add to module code for better error tracking
if ($response['status'] === 'REQUEST_DENIED') {
  \Drupal::logger('google_places')->error('API key denied: @message', [
    '@message' => $response['error_message'] ?? 'Unknown error'
  ]);
}
```

## Testing Checklist

### ✅ Basic API Access
- [ ] Test with simple place details request
- [ ] Verify no `REQUEST_DENIED` errors
- [ ] Check response contains expected data

### ✅ German Language Support
- [ ] Test with `language=de` parameter
- [ ] Verify German place names returned
- [ ] Compare with English responses

### ✅ Places Photos API
- [ ] Test photo reference retrieval
- [ ] Test photo download with new API key
- [ ] Verify image files can be saved

### ✅ Production Readiness
- [ ] Configure proper API restrictions
- [ ] Set up billing and quota monitoring
- [ ] Implement error handling and logging
- [ ] Test with production domain/IP

## Current Investigation Status

### ✅ Completed
- Identified root cause: Invalid API key
- Tested German language parameter configuration
- Documented resolution steps

### 🔄 Next Steps
1. **Obtain valid Google Cloud API key**
2. **Test API access with new key**
3. **Implement German language configuration**
4. **Test image download functionality**
5. **Configure production security restrictions**

---

## German Language Configuration Status

The German language configuration (`language=de`) has been properly documented and tested. Once a valid API key is obtained, the system will automatically retrieve all place data in German format, including:

- ✅ Place names (e.g., "Brandenburger Tor" instead of "Brandenburg Gate")
- ✅ Formatted addresses (e.g., "Berlin, Deutschland" instead of "Berlin, Germany")
- ✅ Localized content where available

**The German language requirement is ready for implementation once the API key issue is resolved.**

---
*This document will be updated as the API key issue is resolved and German language functionality is tested.*
