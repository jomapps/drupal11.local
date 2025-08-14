#!/bin/bash

echo "========================================"
echo "Drupal 11 Production Deployment Script"
echo "========================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DOMAIN_PATH="/home/admin/domains/drupal11.travelm.de"
REPO_URL="https://github.com/jomapps/drupal11.local.git"
DB_NAME="admin_db"
DB_USER="admin_db"
DB_PASS="kaHsRGs5fDfTjfMytfrk"

echo -e "${YELLOW}Starting deployment process...${NC}"

# Step 1: Navigate to domain directory
echo -e "${YELLOW}Step 1: Navigating to domain directory...${NC}"
cd "$DOMAIN_PATH" || { echo -e "${RED}Failed to navigate to domain directory${NC}"; exit 1; }

# Step 2: Backup existing public_html if it exists
if [ -d "public_html" ]; then
    echo -e "${YELLOW}Step 2: Backing up existing public_html...${NC}"
    mv public_html "public_html_backup_$(date +%Y%m%d_%H%M%S)"
fi

# Step 3: Clone repository
echo -e "${YELLOW}Step 3: Cloning repository...${NC}"
git clone "$REPO_URL" public_html || { echo -e "${RED}Failed to clone repository${NC}"; exit 1; }

# Step 4: Navigate to public_html
cd public_html || { echo -e "${RED}Failed to navigate to public_html${NC}"; exit 1; }

# Step 5: Install Composer dependencies
echo -e "${YELLOW}Step 5: Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader || { echo -e "${RED}Composer install failed${NC}"; exit 1; }

# Step 6: Create production settings.php
echo -e "${YELLOW}Step 6: Creating production settings.php...${NC}"
cat > web/sites/default/settings.php << 'EOF'
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
 * Disable HTTP client SSL verification.
 */
$settings['http_client_config']['verify'] = FALSE;
EOF

# Step 7: Create services.yml
echo -e "${YELLOW}Step 7: Creating services.yml...${NC}"
cp web/sites/default/default.services.yml web/sites/default/services.yml

# Step 8: Create files directory
echo -e "${YELLOW}Step 8: Creating files directory...${NC}"
mkdir -p web/sites/default/files

# Step 9: Set proper permissions
echo -e "${YELLOW}Step 9: Setting proper permissions...${NC}"
chown -R admin:admin "$DOMAIN_PATH/public_html"
find "$DOMAIN_PATH/public_html" -type d -exec chmod 755 {} \;
find "$DOMAIN_PATH/public_html" -type f -exec chmod 644 {} \;
chmod 755 web/sites/default/files

# Step 10: Create future deployment script
echo -e "${YELLOW}Step 10: Creating future deployment script...${NC}"
cat > deploy.sh << 'EOF'
#!/bin/bash
echo "Updating Drupal 11 site..."
cd /home/admin/domains/drupal11.travelm.de/public_html
git pull origin master
composer install --no-dev --optimize-autoloader
./vendor/bin/drush updatedb -y
./vendor/bin/drush config:import -y
./vendor/bin/drush cache:rebuild
chown -R admin:admin /home/admin/domains/drupal11.travelm.de/public_html
echo "Deployment complete!"
EOF

chmod +x deploy.sh

echo -e "${GREEN}========================================"
echo -e "DEPLOYMENT SCRIPT COMPLETE!"
echo -e "========================================${NC}"
echo
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Import your database:"
echo "   mysql -u $DB_USER -p$DB_PASS $DB_NAME < /path/to/drupal11_production.sql"
echo
echo "2. Copy files from old production to:"
echo "   $DOMAIN_PATH/public_html/web/sites/default/files/"
echo
echo "3. Run Drupal updates:"
echo "   cd $DOMAIN_PATH/public_html"
echo "   ./vendor/bin/drush updatedb"
echo "   ./vendor/bin/drush config:import"
echo "   ./vendor/bin/drush cache:rebuild"
echo
echo "4. Test your site at: https://drupal11.travelm.de"
echo
echo -e "${GREEN}For future deployments, just run: ./deploy.sh${NC}"
