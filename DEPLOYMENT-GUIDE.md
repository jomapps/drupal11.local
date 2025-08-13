# Drupal 11 Production Deployment Guide

## Overview
This guide covers deploying your Drupal 11 Thunder distribution site to production with automated deployment capabilities.

## Phase 1: Initial Production Setup (First Time)

### 1. Prepare Your Repository

#### Clean up untracked files
```bash
# Review what's currently untracked
git status

# Add files that should be tracked
git add web/modules/contrib/address/
git add web/modules/contrib/advancedqueue/
git add web/modules/contrib/geofield/
git add web/modules/contrib/vgwort/

# Remove or ignore temporary files
rm -rf drush-scripts-temp/
rm '$v){'
rm 'save()'

# Commit the changes
git add .
git commit -m "Clean up repository and optimize .gitignore for production deployment"
```

#### Create production settings template
```bash
# Create a production settings template
cp web/sites/default/default.settings.php web/sites/default/settings.production.php
```

### 2. Set Up Configuration Management

#### Export current configuration
```bash
# Create config directory if it doesn't exist
mkdir -p config/sync

# Export current configuration
drush config:export --destination=config/sync

# Add config to git
git add config/
git commit -m "Add initial configuration export"
```

### 3. Create Production Environment Files

#### Create production-specific files that won't be tracked:
- `web/sites/default/settings.php` (production database settings)
- `web/sites/default/services.yml` (production services)
- `.env` (environment variables)

### 4. Server Setup Requirements

#### On your production server:
1. **PHP 8.1+** with required extensions
2. **Composer** installed globally
3. **Drush** (will be installed via Composer)
4. **Git** for deployment
5. **Web server** (Apache/Nginx) configured
6. **Database** (MySQL/MariaDB) set up

## Phase 2: Automated Deployment Workflow

### Option A: Simple Git-Based Deployment

#### 1. Server-side deployment script
Create `/path/to/your/site/deploy.sh`:
```bash
#!/bin/bash
set -e

echo "Starting deployment..."

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Run database updates
drush updatedb -y

# Import configuration
drush config:import -y

# Clear caches
drush cache:rebuild

echo "Deployment completed successfully!"
```

#### 2. Make it executable
```bash
chmod +x deploy.sh
```

### Option B: GitHub Actions (Recommended)

Create `.github/workflows/deploy.yml` in your repository for automated deployment.

### Current Repository Status

Based on analysis of your repository:
- ✅ Core and vendor directories are properly ignored
- ✅ Contrib modules are tracked (good for deployment)
- ⚠️ Some temporary files need cleanup
- ⚠️ Configuration management needs setup

### Next Steps

1. **Clean up repository** (remove temp files, add missing modules)
2. **Set up configuration management** (export config to sync directory)
3. **Create production server setup**
4. **Test deployment process** on staging environment first
5. **Set up automated deployment** (GitHub Actions or simple git hooks)

### Security Considerations

- Never commit sensitive settings files
- Use environment variables for database credentials
- Set proper file permissions on production
- Keep `composer.lock` in version control for consistent deployments
- Regular security updates via Composer

### Files Created for Your Deployment

1. **`.gitignore`** - Optimized for production deployment
2. **`deployment-config.yml`** - Deployment configuration
3. **`.github/workflows/deploy.yml`** - GitHub Actions workflow
4. **`deploy.sh`** - Manual deployment script
5. **`cleanup-repository.sh`** - Repository cleanup script
6. **`web/sites/default/settings.production.template.php`** - Production settings template

### Quick Start Commands

#### Phase 1: Repository Cleanup (Run these first)
```bash
# 1. Clean up repository
./cleanup-repository.sh

# 2. Review and commit changes
git add .
git commit -m "Prepare repository for production deployment"
git push origin main
```

#### Phase 2: Production Server Setup
```bash
# 1. Clone repository on production server
git clone https://github.com/yourusername/your-repo.git /path/to/production

# 2. Copy and configure settings
cp web/sites/default/settings.production.template.php web/sites/default/settings.php
# Edit settings.php with your production database credentials

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Set up file permissions
chmod 755 web/sites/default/files
chmod 644 web/sites/default/settings.php

# 5. Import configuration and clear cache
drush config:import -y
drush cache:rebuild
```

### Ongoing Deployment Options

#### Option 1: Manual Deployment
```bash
./deploy.sh production
```

#### Option 2: Automated via GitHub Actions
- Push to `main` branch triggers automatic deployment
- Configure secrets in GitHub repository settings:
  - `HOST`: Your server IP/domain
  - `USERNAME`: SSH username
  - `SSH_KEY`: Private SSH key
  - `PORT`: SSH port (usually 22)

### Monitoring & Maintenance

- Set up log monitoring
- Regular database backups
- Monitor disk space (especially files directory)
- Keep Drupal core and contrib modules updated

### Troubleshooting

#### Common Issues:
1. **Permission errors**: Check file permissions on files directory
2. **Database connection**: Verify settings.php database credentials
3. **Configuration import fails**: Check config/sync directory exists and has proper files
4. **Memory issues**: Increase PHP memory limit in production
