# Google Places Module - Technical Overview

## Project Information
- **Project**: Drupal 11 Google Places Integration
- **Local Development**: http://drupal11.local/ (Ubuntu WSL2)
- **Production Site**: http://drupal11.travelm.de/ (Thunder-powered)
- **Database**: drupal11_local (MySQL)
- **Document Version**: 1.0
- **Last Updated**: August 20, 2025

## Module Overview

### Current Status
- **Installation**: New Drupal 11 installation
- **Google Places Module**: Custom implementation (location TBD)
- **Production Issue**: Image fetching blocked (exact error pending reproduction)
- **API Keys**: Configured in settings.php

### API Configuration

#### Google Maps API Keys
```php
// From settings.php (lines 854-855)
$settings['maps_api_key'] = 'AIzaSyBLzZBWyuCUqSjX_YDlG0JgJOL0dX3-7-I';
$settings['maps_api_key_open'] = 'AIzaSyBLzZBWyuCUqSjX_YDlG0JgJOL0dX3-7-I';
```

**⚠️ Security Note**: API keys are currently exposed in settings.php and should be moved to environment variables.

## Architecture Findings

### File Structure Evidence
- **Places Directory**: `/web/sites/default/files/places/` (873 subdirectories)
- **Google Place IDs**: Directory names follow pattern `ChIJ-*` (Google Places API format)
- **Image Storage**: Each place directory contains downloaded images (e.g., `0b9683ff7f477e9f1eabad44b1e27a2a.jpg`)

### Example Places Structure
```
/web/sites/default/files/places/
├── ChIJ-8pgKuGOsUcRO6gVZxdzOus/
│   └── 0b9683ff7f477e9f1eabad44b1e27a2a.jpg (397KB)
├── ChIJ-SwXbomPsUcRV4H37t0Y0Y8/
├── ChIJ-TXd_LZjq0cRjAQhid2Rxi0/
└── ... (870+ more place directories)
```

### Content Type Structure
- **Content Type**: "places" (inferred from file structure)
- **Functionality**: "Get image and save" feature for Google Places photos
- **Current State**: No places content type found in fresh installation (expected for new setup)

## Known Issues

### Image Fetching Problem - ROOT CAUSE IDENTIFIED ✅
- **Issue**: Images not being permitted/blocked during fetch
- **Production Site**: http://drupal11.travelm.de/ (where issue was first reported)
- **Development Site**: http://drupal11.local/ (used for reproduction and testing)
- **Root Cause**: **API Key Invalid** - `REQUEST_DENIED` with message "The provided API key is invalid."
- **API Response**: `{"error_message": "The provided API key is invalid.", "status": "REQUEST_DENIED"}`
- **Resolution**: New API key implemented and tested successfully

### Error Monitoring Setup
- **Apache Logs**: `/var/log/apache2/error.log`
- **PHP Logs**: `/var/log/php8.3-fpm.log`
- **Drupal Logging**: Verbose error level enabled in settings.php (line 889)

## Development Environment

### System Configuration
- **OS**: Ubuntu WSL2 (Linux 6.6.87.2-microsoft-standard-WSL2)
- **Web Server**: Apache 2.4.58
- **PHP**: 8.3 with FPM
- **Database**: MySQL/MariaDB
- **Drupal**: Version 11 with Thunder profile

### Logging Configuration
```php
// Development settings (lines 889-891)
$config['system.logging']['error_level'] = 'verbose';
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
```

### Database Connection
```php
$databases['default']['default'] = [
  'database' => 'drupal11_local',
  'username' => 'drupal',
  'password' => 'drupal123',
  'prefix' => '',
  'host' => 'localhost',
  'port' => '3306',
  'driver' => 'mysql',
];
```

## Next Steps

### Immediate Actions Required
1. **Locate Google Places Module Code**
   - Search for custom module files
   - Identify the "get image and save" functionality
   - Document module architecture

2. **Set Up Error Monitoring**
   - Configure real-time log monitoring
   - Create dedicated log files for Google Places operations
   - Set up alerts for API failures

3. **Reproduce Production Issue**
   - Test image fetching functionality
   - Capture exact error messages
   - Analyze API response codes

4. **API Key Investigation**
   - Verify API key permissions
   - Check Google Cloud Console restrictions
   - Test Places Photos API access

### Technical Investigation Areas
- [ ] Custom module location and code structure
- [ ] Google Places API integration implementation
- [ ] Image download and storage mechanism
- [ ] Error handling and logging
- [ ] API key restrictions and permissions

## References
- **Google Places API Documentation**: [Places API Overview](https://developers.google.com/maps/documentation/places/web-service/overview)
- **Places Photos API**: [Photo Requests](https://developers.google.com/maps/documentation/places/web-service/photos)
- **Drupal 11 Documentation**: [Drupal.org](https://www.drupal.org/docs/drupal-apis)

---
*This document will be updated as we discover more about the Google Places module implementation and resolve the image fetching issues.*
