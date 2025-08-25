# Development Server Documentation
**Project**: Drupal 11 Local Development Environment  
**Environment**: Windows 11 WSL2 Ubuntu  
**Created**: August 21, 2025  
**Last Updated**: August 21, 2025  

## 🖥️ System Information

### Host System
- **OS**: Windows 11
- **WSL Version**: WSL2
- **WSL Distribution**: Ubuntu 24.04.3 LTS (noble)
- **Kernel**: Linux 6.6.87.2-microsoft-standard-WSL2
- **Architecture**: x86_64

### Development User
- **WSL User**: leoge
- **Windows User**: leoge
- **Home Directory**: /home/leoge
- **Project Owner**: leoge:www-data (mixed ownership for proper web server permissions)

## 🛠️ Software Stack (LAMP-like)

### Web Server
- **Apache**: 2.4.58 (Ubuntu)
- **Status**: Active and configured
- **Config**: Standard Ubuntu Apache2 setup
- **Document Root**: `/var/www/drupal11/web`

### Database
- **MySQL**: 8.0.43-0ubuntu0.24.04.1 for Linux x86_64
- **Database Name**: drupal11_local
- **Username**: drupal
- **Password**: drupal123
- **Host**: localhost
- **Port**: 3306
- **Status**: ✅ Connected and working

### PHP
- **Version**: PHP 8.3.24 (cli) (NTS)
- **Zend Engine**: v4.3.24
- **Extensions**: Zend OPcache v8.3.24
- **Config**: /etc/php/8.3/cli/php.ini
- **Binary**: /usr/bin/php8.3
- **Status**: ✅ Fully compatible with Drupal 11

### Additional Tools
- **Composer**: 2.8.10 (2025-07-10)
- **Git**: 2.51.0
- **Node.js**: ❌ Not installed
- **Nginx**: ❌ Not installed (using Apache)

## 🌐 Drupal 11 Installation Details

### Core Information
- **Drupal Version**: 11.2.3 (latest stable)
- **Install Profile**: Thunder (media-focused distribution)
- **Installation Date**: ~August 19-20, 2025
- **Bootstrap Status**: ✅ Successful

### Directory Structure
```
/var/www/drupal11/
├── web/                    # Document root (Drupal core)
├── vendor/                 # Composer dependencies
├── config/                 # Configuration management
├── sites/default/          # Site-specific files
├── docs/                   # Project documentation
├── recipes/                # Drupal recipes
├── .git/                   # Git repository
├── composer.json           # PHP dependencies
├── .env                    # Environment variables
└── *.sql                   # Database backups
```

### Themes & Appearance
- **Default Theme**: Olivero (Drupal 11 default)
- **Admin Theme**: Gin (modern admin interface)
- **Status**: ✅ Configured and working

### Key Modules Installed
**Content & Media Management:**
- Paragraphs + Paragraphs Features
- Field Group, Inline Entity Form
- Media Entity (Instagram, Pinterest, Twitter, Slideshow)
- Focal Point, Media Library modifications
- Video Embed Field

**Admin & Development:**
- Admin Toolbar
- Gin admin theme
- Drush 13.6.2
- Update Helper

**SEO & Performance:**
- Metatag + Schema Metatag + Metatag Async Widget
- Simple Sitemap
- Pathauto, Redirect

**Content Features:**
- Scheduler + Content Moderation Integration
- Access Unpublished
- Content Lock, Autosave Form
- Views Bulk Edit

## 🔧 Development Tools & Scripts

### Drush
- **Version**: 13.6.2.0
- **Location**: `/var/www/drupal11/vendor/bin/drush`
- **Config**: `/var/www/drupal11/vendor/drush/drush/drush.yml`
- **Usage**: `./vendor/bin/drush [command]`

### Custom Scripts Available
- `fix-permissions.sh` - Fix file permissions
- `fix-wsl-permissions.sh` - WSL-specific permission fixes
- `deploy.sh` - Deployment script
- `production-deploy.sh` - Production deployment
- `cleanup-repository.sh` - Repository maintenance
- `create_video_bundles.php` - Video content setup

### Database Backups
Recent automated backups available:
- `backup_working_20250820_134351.sql` (114MB)
- `backup_working_20250820_134507.sql` (114MB) 
- `backup_working_20250820_134622.sql` (114MB)

## 🌍 Access Information

### Local Development URLs
- **Primary URL**: http://drupal11.local
- **Drupal Detection**: http://default (Drush default)
- **Document Root**: `/var/www/drupal11/web`

### File Paths
- **Public Files**: `sites/default/files`
- **Temporary Files**: `/tmp`
- **Configuration**: `../config/sync` (relative to web root)
- **WSL Path**: `/var/www/drupal11`
- **Windows Path**: `\\wsl.localhost\Ubuntu\var\www\drupal11`

## 📁 Project Files & Documentation

### Available Documentation
- `DEPLOYMENT-CHECKLIST.md` - Pre-deployment checklist
- `DEPLOYMENT-GUIDE.md` - General deployment guide  
- `DEPLOYMENT-TO-PRODUCTION.md` - Production deployment steps
- `DEV-TO-PRODUCTION-WORKFLOW.md` - Development workflow
- `PRODUCTION-DEPLOYMENT-STEPS.md` - Detailed production steps
- `QUICK-FIX-COMMANDS.md` - Common troubleshooting commands
- `wsl-setup.md` - WSL configuration guide

### Environment Configuration
Environment variables in `.env`:
```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=drupal11_local
DB_USER=drupal
DB_PASSWORD=drupal123
WSL_USER=leoge
WEB_USER=www-data
WEB_GROUP=www-data
LOCAL_URL=http://drupal11.local
LOCAL_PATH=/var/www/drupal11
HASH_SALT=AM2GGQElxCN6x_xI5QFMA1vkK1pFNU1pHU-Y41GWUVVX0b4rZaa7z7Ey_wRG6PCk-bk2H-5agw
```

## 🚀 Common Development Commands

### Drupal/Drush Commands
```bash
# System status
wsl ./vendor/bin/drush status

# Cache operations
wsl ./vendor/bin/drush cr          # Clear all caches
wsl ./vendor/bin/drush cex         # Export configuration
wsl ./vendor/bin/drush cim         # Import configuration

# Module management
wsl ./vendor/bin/drush en [module] # Enable module
wsl ./vendor/bin/drush pm:list     # List modules

# Database operations
wsl ./vendor/bin/drush sql:dump > backup.sql
wsl ./vendor/bin/drush sql:cli     # Database shell
```

### File & Permission Management
```bash
# Fix permissions (use project scripts)
wsl ./fix-permissions.sh
wsl ./fix-wsl-permissions.sh

# Manual permission fixes
wsl sudo chown -R leoge:www-data /var/www/drupal11
wsl sudo chmod -R 755 /var/www/drupal11
wsl sudo chmod -R 664 /var/www/drupal11/sites/default/files
```

### Composer Operations
```bash
# Dependency management
wsl composer install              # Install dependencies
wsl composer update               # Update dependencies
wsl composer require drupal/[module] # Add module
wsl composer remove drupal/[module]  # Remove module
```

### Git Operations
```bash
# Standard git workflow
wsl git status
wsl git add .
wsl git commit -m "message"
wsl git push origin main
```

## ⚠️ Important Notes

### WSL Considerations
- Always use `wsl` prefix when running commands from PowerShell
- File permissions can be tricky between Windows and Linux
- Use WSL-specific permission scripts when needed
- Database and web server run natively in WSL Ubuntu

### Development Workflow
- Primary development happens in WSL Ubuntu environment
- Files accessible from both Windows and WSL
- Use bash/Linux commands for all development tasks
- Thunder profile provides media-heavy site foundation

### Backup Strategy
- Automated SQL backups are created regularly
- Multiple recent backups available for rollback
- Always backup before major changes
- Configuration managed via Drupal's config sync

## 🔍 Troubleshooting Quick Reference

### Common Issues
1. **Permission Problems**: Run `./fix-wsl-permissions.sh`
2. **Cache Issues**: Run `wsl ./vendor/bin/drush cr`
3. **Database Connection**: Check `.env` file settings
4. **File Access**: Ensure proper `leoge:www-data` ownership

### Health Check Commands
```bash
# System health
wsl ./vendor/bin/drush status
wsl php -m | grep -i mysql
wsl mysql -u drupal -p drupal11_local -e "SELECT 1;"

# File permissions check
wsl ls -la sites/default/files/
wsl ls -la web/
```

---
**Last System Check**: August 21, 2025 - All systems ✅ operational
