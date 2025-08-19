#!/bin/bash

# Fix permissions for Drupal 11 + Thunder on Windows WSL + Apache
# This script sets the correct permissions for WSL, Apache (www-data), and Drupal

echo "🔧 Fixing permissions for Windows WSL + Apache + Drupal..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
PROJECT_ROOT="/var/www/drupal11"
WEB_ROOT="$PROJECT_ROOT/web"
WSL_USER="leoge"
WEB_USER="www-data"

echo -e "${BLUE}Current user: $(whoami)${NC}"
echo -e "${BLUE}Apache user: $WEB_USER${NC}"
echo -e "${BLUE}Project root: $PROJECT_ROOT${NC}"

# Step 1: Add user to www-data group
echo -e "${YELLOW}Step 1: Adding $WSL_USER to $WEB_USER group...${NC}"
sudo usermod -a -G $WEB_USER $WSL_USER

# Step 2: Set ownership for entire project
echo -e "${YELLOW}Step 2: Setting ownership ($WSL_USER:$WEB_USER)...${NC}"
sudo chown -R $WSL_USER:$WEB_USER $PROJECT_ROOT

# Step 3: Set base permissions for directories and files
echo -e "${YELLOW}Step 3: Setting base permissions...${NC}"
# Directories: 755 (owner: rwx, group: rx, other: rx)
find $PROJECT_ROOT -type d -exec chmod 755 {} \;
# Files: 644 (owner: rw, group: r, other: r)
find $PROJECT_ROOT -type f -exec chmod 644 {} \;

# Step 4: Set special permissions for writable directories
echo -e "${YELLOW}Step 4: Setting writable directory permissions...${NC}"
# Files directory needs to be writable by Apache
chmod -R 775 $WEB_ROOT/sites/default/files/
# Set sticky bit and group write for files directory
chmod g+s $WEB_ROOT/sites/default/files/
find $WEB_ROOT/sites/default/files/ -type d -exec chmod 775 {} \;
find $WEB_ROOT/sites/default/files/ -type f -exec chmod 664 {} \;

# Step 5: Set permissions for configuration files
echo -e "${YELLOW}Step 5: Setting configuration file permissions...${NC}"
# settings.php should be writable by owner and group
chmod 664 $WEB_ROOT/sites/default/settings.php
# services.yml should exist and be readable
if [ ! -f "$WEB_ROOT/sites/default/services.yml" ]; then
    cp $WEB_ROOT/sites/default/default.services.yml $WEB_ROOT/sites/default/services.yml
    chown $WSL_USER:$WEB_USER $WEB_ROOT/sites/default/services.yml
fi
chmod 644 $WEB_ROOT/sites/default/services.yml

# Step 6: Set executable permissions for scripts
echo -e "${YELLOW}Step 6: Setting executable permissions for scripts...${NC}"
chmod +x $PROJECT_ROOT/vendor/bin/drush
chmod +x $PROJECT_ROOT/*.sh
find $PROJECT_ROOT -name "*.sh" -exec chmod +x {} \;

# Step 7: Set permissions for vendor directory
echo -e "${YELLOW}Step 7: Setting vendor directory permissions...${NC}"
chmod -R 755 $PROJECT_ROOT/vendor/
find $PROJECT_ROOT/vendor/ -type f -exec chmod 644 {} \;
find $PROJECT_ROOT/vendor/bin/ -type f -exec chmod 755 {} \;

# Step 8: Set correct permissions for web directory
echo -e "${YELLOW}Step 8: Setting web directory permissions...${NC}"
chmod 755 $WEB_ROOT
chmod 644 $WEB_ROOT/.htaccess
chmod 644 $WEB_ROOT/index.php

# Step 9: Set permissions for core and modules
echo -e "${YELLOW}Step 9: Setting core and modules permissions...${NC}"
if [ -d "$WEB_ROOT/core" ]; then
    chmod -R 755 $WEB_ROOT/core/
    find $WEB_ROOT/core/ -type f -exec chmod 644 {} \;
fi

if [ -d "$WEB_ROOT/modules" ]; then
    chmod -R 755 $WEB_ROOT/modules/
    find $WEB_ROOT/modules/ -type f -exec chmod 644 {} \;
fi

if [ -d "$WEB_ROOT/themes" ]; then
    chmod -R 755 $WEB_ROOT/themes/
    find $WEB_ROOT/themes/ -type f -exec chmod 644 {} \;
fi

if [ -d "$WEB_ROOT/profiles" ]; then
    chmod -R 755 $WEB_ROOT/profiles/
    find $WEB_ROOT/profiles/ -type f -exec chmod 644 {} \;
fi

# Step 10: WSL-specific fixes
echo -e "${YELLOW}Step 10: Applying WSL-specific fixes...${NC}"
# Fix WSL file mode issues
if [ -f "$PROJECT_ROOT/.git/config" ]; then
    git config core.filemode false
    git config core.autocrlf input
fi

# Step 11: Create necessary directories if missing
echo -e "${YELLOW}Step 11: Creating necessary directories...${NC}"
mkdir -p $WEB_ROOT/sites/default/files/tmp
mkdir -p $WEB_ROOT/sites/default/files/private
mkdir -p $WEB_ROOT/sites/default/files/css
mkdir -p $WEB_ROOT/sites/default/files/js
mkdir -p $WEB_ROOT/sites/default/files/php

# Set permissions for new directories
chmod -R 775 $WEB_ROOT/sites/default/files/tmp
chmod -R 775 $WEB_ROOT/sites/default/files/private
chmod -R 775 $WEB_ROOT/sites/default/files/css
chmod -R 775 $WEB_ROOT/sites/default/files/js
chmod -R 775 $WEB_ROOT/sites/default/files/php

# Step 12: Set ACLs for better WSL compatibility (if available)
echo -e "${YELLOW}Step 12: Setting ACLs (if available)...${NC}"
if command -v setfacl &> /dev/null; then
    setfacl -R -m u:$WSL_USER:rwX $WEB_ROOT/sites/default/files/ 2>/dev/null || true
    setfacl -R -m g:$WEB_USER:rwX $WEB_ROOT/sites/default/files/ 2>/dev/null || true
    setfacl -R -d -m u:$WSL_USER:rwX $WEB_ROOT/sites/default/files/ 2>/dev/null || true
    setfacl -R -d -m g:$WEB_USER:rwX $WEB_ROOT/sites/default/files/ 2>/dev/null || true
else
    echo "ACL tools not available, skipping..."
fi

echo -e "${GREEN}✅ Permission fixing complete!${NC}"
echo ""
echo -e "${BLUE}Summary:${NC}"
echo -e "📁 Project files: $WSL_USER:$WEB_USER with 755/644"
echo -e "📁 Files directory: $WSL_USER:$WEB_USER with 775/664 + group sticky bit"
echo -e "📄 settings.php: $WSL_USER:$WEB_USER with 664"
echo -e "📄 .htaccess: $WSL_USER:$WEB_USER with 644"
echo -e "🔧 Scripts: executable permissions set"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Restart Apache: sudo systemctl restart apache2"
echo "2. Clear Drupal cache: vendor/bin/drush cache:rebuild"
echo "3. Test site: curl http://drupal11.local"
echo ""
echo -e "${GREEN}You may need to log out and back in for group changes to take effect.${NC}"
