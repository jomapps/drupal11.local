#!/bin/bash

# WSL Drupal Permission Fix Script
# This script fixes common permission issues in WSL environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Load environment variables
if [ -f .env ]; then
    source .env
else
    echo -e "${RED}Error: .env file not found${NC}"
    exit 1
fi

echo -e "${GREEN}Starting WSL permission fix...${NC}"

# Function to check if user has sudo access
check_sudo() {
    if ! sudo -n true 2>/dev/null; then
        echo -e "${YELLOW}This script requires sudo access. You may be prompted for your password.${NC}"
    fi
}

# Function to fix file ownership and permissions
fix_permissions() {
    echo -e "${YELLOW}Fixing file ownership and permissions...${NC}"
    
    # Fix ownership of the entire project to current user and web group
    sudo chown -R ${WSL_USER}:${WEB_GROUP} ${LOCAL_PATH}
    
    # Set proper directory permissions
    find ${LOCAL_PATH} -type d -exec sudo chmod ${DIR_PERMISSION} {} \;
    
    # Set proper file permissions
    find ${LOCAL_PATH} -type f -exec sudo chmod ${FILE_PERMISSION} {} \;
    
    # Fix executable permissions for specific files
    sudo chmod ${EXECUTABLE_PERMISSION} ${LOCAL_PATH}/vendor/bin/*
    sudo chmod ${EXECUTABLE_PERMISSION} ${LOCAL_PATH}/*.sh
    
    # Ensure files directory is writable by web server
    if [ -d "${LOCAL_PATH}/web/sites/default/files" ]; then
        sudo chown -R ${WSL_USER}:${WEB_GROUP} ${LOCAL_PATH}/web/sites/default/files
        sudo chmod -R 775 ${LOCAL_PATH}/web/sites/default/files
    fi
    
    # Fix settings.php specifically
    if [ -f "${LOCAL_PATH}/web/sites/default/settings.php" ]; then
        sudo chown ${WSL_USER}:${WEB_GROUP} ${LOCAL_PATH}/web/sites/default/settings.php
        sudo chmod 664 ${LOCAL_PATH}/web/sites/default/settings.php
    fi
    
    echo -e "${GREEN}Permissions fixed successfully!${NC}"
}

# Function to test database connection
test_database() {
    echo -e "${YELLOW}Testing database connection...${NC}"
    
    if mysql -h${DB_HOST} -u${DB_USER} -p${DB_PASSWORD} -e "SELECT 1;" >/dev/null 2>&1; then
        echo -e "${GREEN}Database connection successful!${NC}"
    else
        echo -e "${RED}Database connection failed. Please check your credentials.${NC}"
        return 1
    fi
}

# Function to check web server status
check_web_server() {
    echo -e "${YELLOW}Checking web server status...${NC}"
    
    if systemctl is-active --quiet apache2; then
        echo -e "${GREEN}Apache2 is running${NC}"
    elif systemctl is-active --quiet nginx; then
        echo -e "${GREEN}Nginx is running${NC}"
    else
        echo -e "${YELLOW}No web server detected or not running${NC}"
    fi
}

# Function to display current status
show_status() {
    echo -e "${GREEN}=== Current Status ===${NC}"
    echo "Current User: $(whoami)"
    echo "User Groups: $(groups)"
    echo "Project Path: ${LOCAL_PATH}"
    echo "Database Host: ${DB_HOST}"
    echo "Database Name: ${DB_NAME}"
    echo "Database User: ${DB_USER}"
    echo ""
    
    if [ -f "${LOCAL_PATH}/web/sites/default/settings.php" ]; then
        echo "settings.php permissions: $(ls -la ${LOCAL_PATH}/web/sites/default/settings.php)"
    fi
    
    if [ -d "${LOCAL_PATH}/web/sites/default/files" ]; then
        echo "files directory permissions: $(ls -lad ${LOCAL_PATH}/web/sites/default/files)"
    fi
}

# Main execution
main() {
    echo -e "${GREEN}WSL Drupal Environment Setup${NC}"
    echo "================================"
    
    check_sudo
    fix_permissions
    test_database
    check_web_server
    show_status
    
    echo -e "${GREEN}Setup complete! Your WSL Drupal environment should now work properly.${NC}"
    echo -e "${YELLOW}If you still encounter permission issues, run this script again or contact support.${NC}"
}

# Check if script is being sourced or executed
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
