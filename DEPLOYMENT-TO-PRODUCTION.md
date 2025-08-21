# Manual Deployment to Production Guide

## Overview
This guide provides step-by-step instructions for manually deploying your Drupal 11.2 site from development to production without automation.

## Prerequisites Checklist

### ✅ Before You Start
- [ ] Production server is set up with PHP 8.3+, MySQL, and web server
- [ ] Git is installed on production server
- [ ] Composer is installed on production server
- [ ] You have SSH access to production server
- [ ] Production database is created and accessible
- [ ] Domain is pointing to production server

## Step 1: Initial Production Server Setup (First Time Only)

### 1.1 Clone Repository on Production Server

```bash
# SSH into your production server
ssh username@your-production-server.com

# Navigate to web directory (adjust path as needed)
cd /var/www/html

# Clone your repository
git clone https://github.com/jomapps/drupal11.local.git your-site-name
cd your-site-name

# Switch to master branch
git checkout master
```

### 1.2 Install Dependencies

```bash
# Install Composer dependencies (production optimized)
composer install --no-dev --optimize-autoloader

# Set proper permissions
sudo chown -R www-data:www-data web/sites/default/files
sudo chmod -R 755 web/sites/default/files
```

### 1.3 Create Production Settings File

```bash
# Create production settings.php file
sudo nano web/sites/default/settings.php
```

Copy and modify this template:

```php
<?php

/**
 * Production settings.php
 */

// Database configuration - UPDATE THESE VALUES
$databases['default']['default'] = [
  'database' => 'your_production_database_name',
  'username' => 'your_production_db_user',
  'password' => 'your_production_db_password',
  'prefix' => '',
  'host' => 'localhost',  // or your database host
  'port' => '3306',
  'driver' => 'mysql',
];

// Configuration sync directory
$settings['config_sync_directory'] = '../config/sync';

// Production-specific settings
$config['system.logging']['error_level'] = 'hide';
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;

// Trusted host patterns - UPDATE WITH YOUR DOMAIN
$settings['trusted_host_patterns'] = [
  '^your-production-domain\.com$',
  '^www\.your-production-domain\.com$',
];

// Hash salt - COPY FROM YOUR DEVELOPMENT SETTINGS.PHP
$settings['hash_salt'] = 'YOUR_HASH_SALT_FROM_DEVELOPMENT';

// Disable CSS/JS aggregation for development (remove these for production)
// $config['system.performance']['css']['preprocess'] = FALSE;
// $config['system.performance']['js']['preprocess'] = FALSE;

// Production security settings
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

// Disable verbose error logging in production
$config['system.logging']['error_level'] = 'hide';
```

### 1.4 Set File Permissions

```bash
# Set correct permissions
sudo chmod 644 web/sites/default/settings.php
sudo chown www-data:www-data web/sites/default/settings.php

# Ensure files directory is writable
sudo mkdir -p web/sites/default/files
sudo chown -R www-data:www-data web/sites/default/files
sudo chmod -R 755 web/sites/default/files
```

### 1.5 Import Database and Configuration

```bash
# If this is a new site, install Drupal first
# ./vendor/bin/drush site:install thunder --yes

# If migrating from existing site, import your database backup first
# mysql -u username -p database_name < your_database_backup.sql

# Import configuration
./vendor/bin/drush config:import --yes

# Clear caches
./vendor/bin/drush cache:rebuild

# Check site status
./vendor/bin/drush status
```

## Step 2: Regular Deployment Process

### 2.1 Prepare for Deployment

```bash
# On your local development machine, ensure everything is committed
cd /var/www/drupal11

# Export latest configuration
./vendor/bin/drush config:export

# Commit any new configuration changes
git add config/sync/
git commit -m "Export configuration changes for deployment"

# Push to master branch
git push origin master
```

### 2.2 Deploy to Production Server

```bash
# SSH into production server
ssh username@your-production-server.com

# Navigate to your site directory
cd /var/www/html/your-site-name

# STEP 1: Put site in maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer
echo "✅ Site is now in maintenance mode"

# STEP 2: Create backup (recommended)
mkdir -p backups/$(date +%Y%m%d_%H%M%S)
mysqldump -u db_user -p database_name > backups/$(date +%Y%m%d_%H%M%S)/database_backup.sql
echo "✅ Database backup created"

# STEP 3: Pull latest code
git pull origin master
echo "✅ Latest code pulled from repository"

# STEP 4: Update Composer dependencies
composer install --no-dev --optimize-autoloader
echo "✅ Dependencies updated"

# STEP 5: Run database updates (if any)
./vendor/bin/drush updatedb --yes
echo "✅ Database updates completed"

# STEP 6: Import configuration changes
./vendor/bin/drush config:import --yes
echo "✅ Configuration imported"

# STEP 7: Clear all caches
./vendor/bin/drush cache:rebuild
echo "✅ Caches cleared"

# STEP 8: Take site out of maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer
echo "✅ Site is now live"

# STEP 9: Final cache clear
./vendor/bin/drush cache:rebuild
echo "🚀 Deployment completed successfully!"
```

## Step 3: Post-Deployment Verification

### 3.1 Verify Deployment

```bash
# Check site status
./vendor/bin/drush status

# Verify configuration is synchronized
./vendor/bin/drush config:status

# Check for any errors
./vendor/bin/drush watchdog:show --severity=Error --count=10

# Test critical functionality
echo "🔍 Please test the following:"
echo "- Site loads correctly"
echo "- Login functionality works"
echo "- Content displays properly"
echo "- Forms are working"
echo "- Any custom functionality"
```

### 3.2 Monitor After Deployment

```bash
# Watch for errors in real-time
tail -f /var/log/apache2/error.log
# or for Nginx:
# tail -f /var/log/nginx/error.log

# Check Drupal logs
./vendor/bin/drush watchdog:show --count=20
```

## Step 4: Quick Deployment Script (Optional)

Create a deployment script on your production server:

```bash
# Create deployment script
nano deploy-manual.sh
```

Add this content:

```bash
#!/bin/bash
set -e

echo "🚀 Starting manual deployment..."

# Put in maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer
echo "✅ Maintenance mode enabled"

# Create backup
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR
mysqldump -u YOUR_DB_USER -p YOUR_DB_NAME > $BACKUP_DIR/database_backup.sql
echo "✅ Database backed up to $BACKUP_DIR"

# Pull latest code
git pull origin master
echo "✅ Code updated"

# Update dependencies
composer install --no-dev --optimize-autoloader
echo "✅ Dependencies updated"

# Database updates
./vendor/bin/drush updatedb --yes
echo "✅ Database updated"

# Import configuration
./vendor/bin/drush config:import --yes
echo "✅ Configuration imported"

# Clear caches
./vendor/bin/drush cache:rebuild
echo "✅ Caches cleared"

# Disable maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer
echo "✅ Maintenance mode disabled"

# Final cache clear
./vendor/bin/drush cache:rebuild

echo "🎉 Deployment completed successfully!"
echo "Please verify your site is working correctly."
```

Make it executable:

```bash
chmod +x deploy-manual.sh
```

## Troubleshooting Common Issues

### Database Connection Issues
```bash
# Test database connection
./vendor/bin/drush sql:connect

# Check database settings
./vendor/bin/drush status | grep -i database
```

### Configuration Import Issues
```bash
# Check what configuration changes exist
./vendor/bin/drush config:status

# Import specific configuration
./vendor/bin/drush config:import --partial

# Force import if needed
./vendor/bin/drush config:import --partial --source=../config/sync
```

### Permission Issues
```bash
# Fix file permissions
sudo chown -R www-data:www-data web/sites/default/files
sudo chmod -R 755 web/sites/default/files
sudo chmod 644 web/sites/default/settings.php
```

### Cache Issues
```bash
# Complete cache rebuild
./vendor/bin/drush cache:rebuild

# Clear specific caches
./vendor/bin/drush cache:clear render
./vendor/bin/drush cache:clear dynamic_page_cache
```

## Security Checklist

- [ ] Remove any development modules from production
- [ ] Ensure error reporting is disabled
- [ ] Verify file permissions are correct
- [ ] SSL certificate is installed and working
- [ ] Regular backups are configured
- [ ] Security headers are configured in web server

## Deployment Checklist

### Before Each Deployment:
- [ ] Test changes thoroughly in development
- [ ] Export configuration (`drush config:export`)
- [ ] Commit and push changes to master
- [ ] Inform stakeholders of maintenance window

### During Deployment:
- [ ] Enable maintenance mode
- [ ] Create database backup
- [ ] Pull latest code
- [ ] Update dependencies
- [ ] Run database updates
- [ ] Import configuration
- [ ] Clear caches
- [ ] Disable maintenance mode
- [ ] Verify site functionality

### After Deployment:
- [ ] Test critical site functionality
- [ ] Monitor error logs
- [ ] Verify configuration is synchronized
- [ ] Confirm site performance
- [ ] Update deployment documentation if needed

---

## Quick Reference Commands

```bash
# Put site in maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer

# Take site out of maintenance mode  
./vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer

# Export configuration (development)
./vendor/bin/drush config:export

# Import configuration (production)
./vendor/bin/drush config:import --yes

# Clear all caches
./vendor/bin/drush cache:rebuild

# Check site status
./vendor/bin/drush status

# View recent errors
./vendor/bin/drush watchdog:show --severity=Error --count=10
```

This manual deployment process ensures you have full control over each step and can troubleshoot any issues that arise during deployment.
