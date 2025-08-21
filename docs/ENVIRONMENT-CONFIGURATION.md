# Environment Configuration - Drupal 11 Google Places

## 🌍 Environment Overview

### **Local Development Environment**
- **URL**: http://drupal11.local/
- **Purpose**: Development, testing, and debugging
- **Location**: `/var/www/drupal11/` (Ubuntu WSL2)
- **Database**: `drupal11_local` (MySQL)
- **Web Server**: Apache 2.4.58
- **PHP**: 8.3 with FPM

### **Production Environment**  
- **URL**: http://drupal11.travelm.de/
- **Purpose**: Live website serving real users
- **Platform**: Thunder-powered Drupal 11
- **Issue Context**: Where Google Places image blocking was first reported

## 🔧 Configuration Synchronization

### API Key Configuration (Both Environments)
```php
// In settings.php (lines 855-856)
$settings['maps_api_key'] = 'AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs';
$settings['maps_api_key_open'] = 'AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs';
```

### Trusted Host Patterns
```php
// Configured for both domains (lines 879-884)
$settings['trusted_host_patterns'] = [
  '^drupal11\.local$',          // Local development
  '^local\.ft\.tc$',            // Alternative local
  '^localhost$',                // Localhost access
  '^127\.0\.0\.1$',            // IP access
  // Production domain should also be included:
  '^drupal11\.travelm\.de$',   // Production site
];
```

### Google Places Module Status
- **Local**: `/web/modules/custom/google_places/` (recreated)
- **Production**: Module files need to be deployed
- **Functionality**: "Get image and save" with German language support

## 🚀 Deployment Workflow

### From Local to Production
1. **Test locally** at http://drupal11.local/
2. **Verify functionality** with new API key
3. **Deploy module files** to production
4. **Clear production caches**
5. **Test on** http://drupal11.travelm.de/

### Key Files to Deploy
```
web/modules/custom/google_places/          # Complete module directory
web/sites/default/settings.php             # Updated API key
```

## 🧪 Testing Procedures

### Local Development Testing
```bash
# Test site accessibility
curl -I "http://drupal11.local/"

# Test place content type
curl -s "http://drupal11.local/node/add/place" | grep -i "google places"

# Test API key functionality
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJ-8pgKuGOsUcRO6gVZxdzOus&fields=name&language=de&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs"
```

### Production Validation
- Access http://drupal11.travelm.de/
- Edit existing place content
- Test "Get image and save" functionality
- Verify German language responses

## 📊 Environment Comparison

| Aspect | Local (drupal11.local) | Production (drupal11.travelm.de) |
|--------|------------------------|-----------------------------------|
| Purpose | Development/Testing | Live Site |
| Google Places Module | ✅ Recreated | ⏳ Needs Deployment |
| API Key | ✅ Working | ✅ Same Key |
| German Language | ✅ Configured | ✅ Configured |
| Place Content Type | ✅ Available | ✅ Available |
| Image Directories | ✅ 873+ places | ✅ 873+ places |

## 🔍 Issue Timeline

1. **Production Issue**: Image fetching blocked on http://drupal11.travelm.de/
2. **Local Investigation**: Reproduced issue on http://drupal11.local/
3. **Root Cause**: Invalid API key affecting both environments
4. **Resolution**: New API key + recreated module files
5. **Current Status**: Local fixed, production deployment pending

## 🎯 Current State

### ✅ Local Development (drupal11.local)
- Module files recreated and functional
- New API key configured and tested
- German language support implemented
- Ready for "Get image and save" testing

### ⏳ Production (drupal11.travelm.de)
- Needs module files deployment
- API key configuration ready
- Waiting for deployment and testing

---

**Remember**: Always test changes on http://drupal11.local/ before deploying to http://drupal11.travelm.de/
