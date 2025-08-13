#!/bin/bash

# Drupal 11 Production Deployment Script
# Usage: ./deploy.sh [environment]
# Example: ./deploy.sh production

set -e  # Exit on any error

# Configuration
ENVIRONMENT=${1:-production}
SITE_PATH="/path/to/your/drupal/site"
BACKUP_PATH="/path/to/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    log_error "composer.json not found. Are you in the Drupal root directory?"
    exit 1
fi

log_info "Starting deployment for environment: $ENVIRONMENT"

# 1. Backup current state
log_info "Creating backup..."
if [ -d "$BACKUP_PATH" ]; then
    mkdir -p "$BACKUP_PATH/code_$DATE"
    cp -r . "$BACKUP_PATH/code_$DATE/"
    log_info "Code backup created at $BACKUP_PATH/code_$DATE"
fi

# 2. Put site in maintenance mode
log_info "Enabling maintenance mode..."
drush state:set system.maintenance_mode 1 --input-format=integer

# 3. Pull latest code
log_info "Pulling latest code from repository..."
git pull origin main

# 4. Install/update Composer dependencies
log_info "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# 5. Run database updates
log_info "Running database updates..."
drush updatedb -y

# 6. Import configuration
log_info "Importing configuration..."
drush config:import -y

# 7. Clear all caches
log_info "Clearing caches..."
drush cache:rebuild

# 8. Disable maintenance mode
log_info "Disabling maintenance mode..."
drush state:set system.maintenance_mode 0 --input-format=integer

# 9. Final cache clear
log_info "Final cache clear..."
drush cache:rebuild

log_info "Deployment completed successfully!"

# Optional: Send notification (uncomment and configure as needed)
# curl -X POST -H 'Content-type: application/json' \
#     --data '{"text":"Deployment completed successfully for '"$ENVIRONMENT"'"}' \
#     YOUR_SLACK_WEBHOOK_URL
