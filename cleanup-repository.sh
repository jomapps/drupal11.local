#!/bin/bash

# Repository Cleanup Script for Drupal 11 Production Deployment
# This script helps clean up your repository before the first production deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

echo "=========================================="
echo "Drupal 11 Repository Cleanup Script"
echo "=========================================="

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    log_error "This is not a git repository!"
    exit 1
fi

log_step "1. Checking current repository status..."
git status --porcelain

log_step "2. Adding contrib modules that should be tracked..."

# Add contrib modules that are currently untracked
if [ -d "web/modules/contrib/address" ]; then
    log_info "Adding address module..."
    git add web/modules/contrib/address/
fi

if [ -d "web/modules/contrib/advancedqueue" ]; then
    log_info "Adding advancedqueue module..."
    git add web/modules/contrib/advancedqueue/
fi

if [ -d "web/modules/contrib/geofield" ]; then
    log_info "Adding geofield module..."
    git add web/modules/contrib/geofield/
fi

if [ -d "web/modules/contrib/vgwort" ]; then
    log_info "Adding vgwort module..."
    git add web/modules/contrib/vgwort/
fi

log_step "3. Removing temporary and unwanted files..."

# Remove temporary files that shouldn't be tracked
if [ -f '$v){' ]; then
    log_info "Removing temporary file: \$v){"
    rm '$v){'
fi

if [ -f 'save()' ]; then
    log_info "Removing temporary file: save()"
    rm 'save()'
fi

# Remove temporary script directory
if [ -d "drush-scripts-temp" ]; then
    log_warning "Removing drush-scripts-temp directory..."
    rm -rf drush-scripts-temp/
fi

# Remove any PHP check files
if [ -f "web/_ini_check.php" ]; then
    log_info "Removing web/_ini_check.php"
    rm web/_ini_check.php
fi

if [ -f "web/ini_check.php" ]; then
    log_info "Removing web/ini_check.php"
    rm web/ini_check.php
fi

log_step "4. Setting up configuration management..."

# Create config directory if it doesn't exist
if [ ! -d "config/sync" ]; then
    log_info "Creating config/sync directory..."
    mkdir -p config/sync
fi

# Export current configuration
log_info "Exporting current configuration..."
if command -v drush &> /dev/null; then
    drush config:export --destination=config/sync
    git add config/
else
    log_warning "Drush not found. Please export configuration manually:"
    log_warning "drush config:export --destination=config/sync"
fi

log_step "5. Final repository status..."
git status

log_step "6. Ready to commit changes..."
echo ""
echo "Review the changes above. If everything looks good, run:"
echo "git add ."
echo "git commit -m 'Prepare repository for production deployment'"
echo ""
echo "Then push to your repository:"
echo "git push origin main"

log_info "Repository cleanup completed!"
