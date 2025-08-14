# Production Deployment Configuration

## Server Details
- **Production Server IP**: 173.249.18.165
- **Domain**: https://drupal11.travelm.de
- **SSH Access**: Available
- **SSH User**: root
- **SSH Password**: s6G9givupHoKaP3qUdLd
- **Document Root**: /home/admin/domains/drupal11.travelm.de/public_html
- **Web Directory**: /home/admin/domains/drupal11.travelm.de/public_html/web
- **Control Panel**: DirectAdmin
- **Web Server**: Apache

## Database Configuration
- **Host**: localhost
- **Database Name**: admin_db
- **Username**: admin_db
- **Password**: kaHsRGs5fDfTjfMytfrk

## Git Repository
- **Platform**: GitHub
- **Repository HTTPS**: https://github.com/jomapps/drupal11.local.git
- **Repository SSH**: git@github.com:jomapps/drupal11.local.git
- **Deploy Token**: Set up and configured
- **Branch**: master

## Local Development
- **Local Domain**: http://drupal11.local
- **Local Path**: d:\wamp64\www\drupal11.local
- **Local DB**: drupal11 (root/no password)

## Deployment Strategy
1. **Code Deployment**: Git pull from GitHub to production
2. **Database**: Copy from local development to production
3. **Files**: Copy from old production server directly to new production
4. **Configuration**: Environment-specific settings for production

## Files to Handle Separately
- `web/sites/default/files/` - User uploads and media files
- `web/sites/default/settings.php` - Production database credentials
- `web/sites/default/services.yml` - Production services configuration

## DirectAdmin Considerations
- File permissions may need adjustment after deployment
- Apache configuration should already point to public_html/web
- SSL certificate may need setup for HTTPS

## Security Notes
- Change default passwords after initial setup
- Ensure proper file permissions (644 for files, 755 for directories)
- Verify .htaccess files are properly configured
