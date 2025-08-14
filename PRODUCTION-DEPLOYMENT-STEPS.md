# Production Deployment Steps

## Phase 1: Prepare Local Repository for Production

### Step 1: Clean and Prepare Repository
```bash
# Navigate to your local site
cd d:\wamp64\www\drupal11.local

# Check current git status
git status

# Add any untracked files that should be in production
git add .
git commit -m "Prepare repository for production deployment"

# Push to GitHub
git push origin master
```

### Step 2: Create Production Settings File
```bash
# Create production-specific settings
cp web/sites/default/settings.php web/sites/default/settings.production.php
```

### Step 3: Export Configuration
```bash
# Export current configuration
drush config:export

# Add config to git if not already tracked
git add config/
git commit -m "Export configuration for production"
git push origin master
```

## Phase 2: Set Up Production Server

### Step 4: SSH into Production Server
```bash
ssh root@173.249.18.165
# Password: s6G9givupHoKaP3qUdLd
```

### Step 5: Navigate to Document Root and Clone Repository
```bash
# Navigate to the domain directory
cd /home/admin/domains/drupal11.travelm.de

# Remove any existing public_html if it exists
rm -rf public_html

# Clone your repository as public_html
git clone https://github.com/jomapps/drupal11.local.git public_html

# Navigate into the cloned directory
cd public_html
```

### Step 6: Install Composer Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
chown -R admin:admin /home/admin/domains/drupal11.travelm.de/public_html
find /home/admin/domains/drupal11.travelm.de/public_html -type d -exec chmod 755 {} \;
find /home/admin/domains/drupal11.travelm.de/public_html -type f -exec chmod 644 {} \;
```

## Phase 3: Configure Production Environment

### Step 7: Create Production Settings
```bash
# Navigate to settings directory
cd /home/admin/domains/drupal11.travelm.de/public_html/web/sites/default

# Create production settings.php
cat > settings.php << 'EOF'
<?php

/**
 * @file
 * Drupal site-specific configuration file.
 */

/**
 * Database settings:
 */
$databases['default']['default'] = array (
  'database' => 'admin_db',
  'username' => 'admin_db',
  'password' => 'kaHsRGs5fDfTjfMytfrk',
  'prefix' => '',
  'host' => 'localhost',
  'port' => 3306,
  'isolation_level' => 'READ COMMITTED',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql\\src\\Driver\\Database\\mysql\\',
);

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 */
$settings['hash_salt'] = 'AM2GGQElxCN6x_xI5QFMA1vkK1pFNU1pHU-Y41GWUVVX0b4rZaa7z7Ey_wRG6PCk-bk2H-5agw';

/**
 * Access control for update.php script.
 */
$settings['update_free_access'] = FALSE;

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

/**
 * File system settings.
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

/**
 * Entity update settings.
 */
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;

/**
 * Configuration sync directory.
 */
$settings['config_sync_directory'] = 'sites/default/files/config_8Qxp0-q0yAfcpiAdZUSEdka3LBLe4kh06WUngmf-yCB4VtVDTRB7HZZgtaqrNRBOnIVGg5iLcA/sync';

/**
 * Trusted host configuration.
 */
$settings['trusted_host_patterns'] = [
  '^drupal11\.travelm\.de$',
  '^173\.249\.18\.165$',
];

/**
 * Production optimizations.
 */
$config['system.performance']['cache']['page']['max_age'] = 3600;
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;

/**
 * Disable HTTP client SSL verification for development.
 */
$settings['http_client_config']['verify'] = FALSE;
EOF

# Set proper permissions
chmod 644 settings.php
```

### Step 8: Create Services Configuration
```bash
# Create services.yml
cp default.services.yml services.yml

# Set proper permissions
chmod 644 services.yml
```

### Step 9: Create Files Directory
```bash
# Create files directory with proper permissions
mkdir -p files
chown -R admin:admin files
chmod 755 files
```

## Phase 4: Database and Files Setup

### Step 10: Import Database (Do this from your local machine)
```bash
# Export your local database
mysqldump -u root drupal11 > drupal11_production.sql

# Upload to production server (from local machine)
scp drupal11_production.sql root@173.249.18.165:/tmp/

# Import on production server (SSH into production)
mysql -u admin_db -pkaHsRGs5fDfTjfMytfrk admin_db < /tmp/drupal11_production.sql

# Clean up
rm /tmp/drupal11_production.sql
```

### Step 11: Copy Files from Old Production
```bash
# You'll need to copy files from your old production server
# This step depends on how you access your old server
# Example if you have the files locally:
# scp -r /path/to/old/files/* root@173.249.18.165:/home/admin/domains/drupal11.travelm.de/public_html/web/sites/default/files/
```

## Phase 5: Final Configuration

### Step 12: Clear Cache and Update
```bash
# Navigate to Drupal root
cd /home/admin/domains/drupal11.travelm.de/public_html

# Clear cache
./vendor/bin/drush cache:rebuild

# Update database
./vendor/bin/drush updatedb

# Import configuration
./vendor/bin/drush config:import

# Clear cache again
./vendor/bin/drush cache:rebuild
```

### Step 13: Set Up Future Git Deployment
```bash
# Create deployment script
cat > deploy.sh << 'EOF'
#!/bin/bash
cd /home/admin/domains/drupal11.travelm.de/public_html
git pull origin master
composer install --no-dev --optimize-autoloader
./vendor/bin/drush updatedb -y
./vendor/bin/drush config:import -y
./vendor/bin/drush cache:rebuild
chown -R admin:admin /home/admin/domains/drupal11.travelm.de/public_html
EOF

chmod +x deploy.sh
```

## Phase 6: Verification

### Step 14: Test the Site
1. Visit https://drupal11.travelm.de
2. Check that the site loads correctly
3. Test admin login
4. Verify media files are accessible
5. Check that all functionality works

## Future Deployments

For future updates, you can simply:
```bash
# SSH into production
ssh root@173.249.18.165

# Run deployment script
cd /home/admin/domains/drupal11.travelm.de/public_html
./deploy.sh
```

Or manually:
```bash
git pull origin master
composer install --no-dev --optimize-autoloader
./vendor/bin/drush updatedb -y
./vendor/bin/drush config:import -y
./vendor/bin/drush cache:rebuild
```
