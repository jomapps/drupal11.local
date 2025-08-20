# Drupal 11 Google Places Module Documentation

## Documentation Structure

This documentation is organized into logical sections to support development, troubleshooting, and maintenance of the Google Places module integration.

### 📁 Directory Structure
```
docs/
├── README.md                                    # This file - overview and navigation
├── modules/google-places/                       # Google Places module documentation
│   ├── google-places-technical-overview.md     # Main technical reference document
│   ├── api/                                     # API documentation and references
│   │   └── google-places-api-reference.md      # Google Places API integration guide
│   ├── architecture/                           # Module architecture documentation
│   ├── development/                            # Development guides and checklists
│   │   └── development-checklist.md           # Development workflow and tasks
│   └── troubleshooting/                        # Issue resolution and debugging
│       └── error-monitoring-setup.md          # Error monitoring and log analysis
├── infrastructure/                             # Server and environment documentation
├── deployment/                                 # Deployment procedures and configs
└── testing/                                   # Testing procedures and frameworks
```

## Quick Start

### 🔍 Current Investigation Status
We are investigating an issue where Google Places images are being blocked during fetch operations in production. This is a new development environment setup to reproduce and resolve the issue.

### 📋 Key Documents

1. **[Technical Overview](modules/google-places/google-places-technical-overview.md)**
   - Current system configuration
   - Known issues and findings
   - Development environment setup

2. **[API Reference](modules/google-places/api/google-places-api-reference.md)**
   - Google Places API integration details
   - Error codes and troubleshooting
   - Testing procedures

3. **[Development Checklist](modules/google-places/development/development-checklist.md)**
   - Module discovery tasks
   - Code analysis workflow
   - Testing environment setup

4. **[Error Monitoring](modules/google-places/troubleshooting/error-monitoring-setup.md)**
   - Log monitoring setup
   - Error pattern identification
   - Debugging procedures

## Current System Overview

### 🏗️ Environment
- **Drupal**: Version 11 with Thunder profile
- **Database**: drupal11_local (MySQL)
- **Web Server**: Apache 2.4.58 on Ubuntu WSL2
- **PHP**: 8.3 with FPM

### 🔑 API Configuration
- **Google Maps API Key**: Configured in settings.php
- **Known Issue**: Images not being permitted during fetch
- **Evidence**: 873 place directories with existing images

### 🎯 Immediate Goals
1. Locate Google Places module source code
2. Reproduce image fetching error
3. Identify root cause (likely API key restrictions)
4. Implement solution and testing

## Navigation Guide

### 🔧 For Developers
Start with:
1. [Development Checklist](modules/google-places/development/development-checklist.md) - Current tasks and workflow
2. [Technical Overview](modules/google-places/google-places-technical-overview.md) - System understanding
3. [API Reference](modules/google-places/api/google-places-api-reference.md) - Integration details

### 🚨 For Troubleshooting
Start with:
1. [Error Monitoring Setup](modules/google-places/troubleshooting/error-monitoring-setup.md) - Log analysis
2. [API Reference](modules/google-places/api/google-places-api-reference.md) - Error codes and meanings
3. [Technical Overview](modules/google-places/google-places-technical-overview.md) - Known issues

### 📊 For Project Management
Start with:
1. [Technical Overview](modules/google-places/google-places-technical-overview.md) - Project status
2. [Development Checklist](modules/google-places/development/development-checklist.md) - Task progress

## Document Maintenance

### 📝 Update Protocol
- Update documents as discoveries are made
- Maintain version history in git
- Add timestamps to significant changes
- Link related documents for cross-reference

### 🔄 Review Schedule
- **Daily**: Update development checklist progress
- **Weekly**: Review and update technical overview
- **After Issues**: Update troubleshooting documentation

## Key Contacts and Resources

### 🔗 External Resources
- [Google Places API Documentation](https://developers.google.com/maps/documentation/places/web-service/overview)
- [Drupal 11 API Documentation](https://api.drupal.org/api/drupal/11)
- [Thunder Profile Documentation](https://www.thunder.org/)

### 📞 Internal References
- **Current Session Focus**: Image fetching error resolution
- **Development Environment**: Ubuntu WSL2 setup
- **Production Issue**: Image blocking (to be reproduced)

---

## Quick Commands

### 🔍 Module Discovery
```bash
# Search for Google Places module files
find /var/www/drupal11 -name "*google*" -o -name "*places*" -type f

# Monitor logs during testing
tail -f /var/log/apache2/error.log | grep -i "google\|places\|api"
```

### 📊 System Status
```bash
# Check Drupal status
cd /var/www/drupal11/web && php -r "echo 'Drupal accessible: ' . (file_exists('autoload.php') ? 'Yes' : 'No') . PHP_EOL;"

# Verify places directory
ls -la /var/www/drupal11/web/sites/default/files/places/ | wc -l
```

---
*This documentation is actively maintained during the Google Places module investigation and development process.*
