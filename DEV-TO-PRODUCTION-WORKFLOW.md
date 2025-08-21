# Drupal 11.2 Development-to-Production Deployment Workflow

## Overview
This guide provides a complete workflow for deploying changes from your development environment to production using Drupal 11.2 configuration management and modern deployment practices.

## Prerequisites

### Development Environment
- ✅ Drupal 11.2 with Thunder profile
- ✅ Configuration management enabled (`config` module)
- ✅ Configuration sync directory: `../config/sync`
- ✅ Git repository: `git@github.com:jomapps/drupal11.local.git`

### Production Environment Requirements
- PHP 8.3+ with required extensions
- Composer installed globally
- Git access to your repository
- Database (MySQL/MariaDB)
- Web server (Apache/Nginx) configured
- SSH access for automated deployment

## 🔄 Development Workflow

### 1. Making Changes in Development

When you make changes to your site (content types, views, configurations, etc.):

```bash
# After making changes in the Drupal admin interface
cd /var/www/drupal11

# Export configuration changes
./vendor/bin/drush config:export

# Check what was exported
git status
git diff config/sync/
```

### 2. Commit Changes to Git

```bash
# Add configuration changes
git add config/sync/

# Add any new custom modules/themes
git add web/modules/custom/
git add web/themes/custom/

# Commit with descriptive message
git commit -m "Add new content type and view configuration"

# Push to develop branch
git push origin develop
```

### 3. Testing and Merging

```bash
# Switch to main branch for production deployment
git checkout main

# Merge develop branch
git merge develop

# Push to main (this triggers production deployment)
git push origin main
```

## 🚀 Production Deployment Options

### Option A: Automated GitHub Actions (Recommended)

The repository includes `.github/workflows/deploy.yml` for automated deployment.

#### Setup GitHub Secrets:
1. Go to your GitHub repository → Settings → Secrets and Variables → Actions
2. Add these secrets:
   - `HOST`: Your production server IP/domain
   - `USERNAME`: SSH username for production server
   - `SSH_KEY`: Private SSH key for authentication
   - `PORT`: SSH port (usually 22)
   - `DEPLOY_PATH`: Full path to your Drupal installation on production

#### Automatic Deployment:
- Every push to `main` branch triggers automatic deployment
- Deployment includes: code update, dependency installation, database updates, config import, cache rebuild

### Option B: Manual Deployment Script

Use the included `deploy.sh` script for manual deployments:

```bash
# On your production server
cd /path/to/your/drupal/site
./deploy.sh production
```

### Option C: Manual Step-by-Step

If you prefer manual control:

```bash
# On production server
cd /path/to/your/drupal/site

# 1. Enable maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer

# 2. Pull latest code
git pull origin main

# 3. Install/update dependencies
composer install --no-dev --optimize-autoloader

# 4. Run database updates
./vendor/bin/drush updatedb -y

# 5. Import configuration
./vendor/bin/drush config:import -y

# 6. Clear caches
./vendor/bin/drush cache:rebuild

# 7. Disable maintenance mode
./vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer

# 8. Final cache clear
./vendor/bin/drush cache:rebuild
```

## 📁 Project Structure

```
/var/www/drupal11/
├── config/sync/              # Exported Drupal configuration
├── web/                      # Drupal web root
│   ├── modules/custom/       # Your custom modules (tracked)
│   ├── themes/custom/        # Your custom themes (tracked)
│   └── sites/default/
│       ├── settings.php      # Environment-specific (not tracked)
│       └── files/            # User uploads (not tracked)
├── vendor/                   # Composer dependencies (not tracked)
├── composer.json             # Dependencies definition (tracked)
├── composer.lock             # Locked versions (tracked)
├── .gitignore               # Git ignore rules
├── deploy.sh                # Manual deployment script
└── .github/workflows/       # GitHub Actions workflows
```

## 🔧 Configuration Management Commands

### Export Configuration (Development)
```bash
# Export all configuration
./vendor/bin/drush config:export

# Check configuration status
./vendor/bin/drush config:status
```

### Import Configuration (Production)
```bash
# Import configuration
./vendor/bin/drush config:import

# Import specific config
./vendor/bin/drush config:import --partial
```

### Check Configuration Differences
```bash
# See what configuration changes exist
./vendor/bin/drush config:status

# See detailed differences
./vendor/bin/drush config:status --state=different
```

## 🔐 Environment-Specific Settings

### Production Settings Template
The repository includes production settings guidance. Create your production `settings.php`:

```php
// Database configuration
$databases['default']['default'] = [
  'database' => 'your_production_db',
  'username' => 'your_production_user',
  'password' => 'your_production_password',
  'prefix' => '',
  'host' => 'your_production_host',
  'port' => '3306',
  'driver' => 'mysql',
];

// Configuration sync directory
$settings['config_sync_directory'] = '../config/sync';

// Production-specific settings
$config['system.logging']['error_level'] = 'hide';
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;

// Trusted host patterns
$settings['trusted_host_patterns'] = [
  '^your-production-domain\.com$',
];

// Hash salt (use the same as development for consistency)
$settings['hash_salt'] = 'your_hash_salt_here';
```

## 🚨 Troubleshooting

### Common Issues and Solutions

#### 1. Configuration Import Fails
```bash
# Check what's causing the issue
./vendor/bin/drush config:status

# Import with forced overwrite
./vendor/bin/drush config:import --partial --source=../config/sync
```

#### 2. Permission Errors
```bash
# Fix file permissions
chmod 755 web/sites/default/files
chmod 644 web/sites/default/settings.php
```

#### 3. Cache Issues
```bash
# Complete cache rebuild
./vendor/bin/drush cache:rebuild

# Clear specific cache
./vendor/bin/drush cache:clear render
```

#### 4. Database Update Issues
```bash
# Check pending updates
./vendor/bin/drush updatedb --dry-run

# Force updates
./vendor/bin/drush updatedb -y
```

## 📋 Regular Workflow Checklist

### Development Phase:
- [ ] Make changes in Drupal admin interface
- [ ] Export configuration: `./vendor/bin/drush config:export`
- [ ] Test changes locally
- [ ] Commit and push to develop branch

### Deployment Phase:
- [ ] Merge develop to main branch
- [ ] Verify automated deployment (or run manual deployment)
- [ ] Test production site functionality
- [ ] Monitor logs for any issues

### Post-Deployment:
- [ ] Verify site is working correctly
- [ ] Check that configuration was imported properly
- [ ] Test critical functionality
- [ ] Monitor performance and logs

## 📊 Monitoring and Maintenance

### Regular Tasks:
1. **Weekly**: Check for Drupal core and module updates
2. **Monthly**: Review and clean up old configuration exports
3. **Quarterly**: Review and update deployment scripts

### Security Considerations:
- Keep `composer.lock` in version control for consistent deployments
- Never commit sensitive settings files (`settings.php`)
- Regular security updates via Composer
- Monitor Drupal security advisories

## 🎯 Best Practices

1. **Always test in development first**
2. **Use descriptive commit messages**
3. **Deploy during low-traffic periods**
4. **Keep backups of database and files**
5. **Monitor deployments and have rollback plans**
6. **Use feature branches for large changes**
7. **Document any manual configuration changes**

---

## Quick Commands Reference

```bash
# Development workflow
./vendor/bin/drush config:export          # Export config
git add config/ && git commit -m "msg"    # Commit changes
git push origin develop                   # Push to develop

# Production deployment
git checkout main && git merge develop    # Prepare for production
git push origin main                      # Deploy (auto) or run ./deploy.sh

# Emergency rollback
git revert HEAD                           # Revert last commit
git push origin main                      # Deploy rollback
```

This workflow ensures consistent, reliable deployments while maintaining the flexibility to handle both automated and manual deployment scenarios.
