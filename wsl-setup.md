# WSL Setup Guide for Drupal Development

## Overview
This guide helps resolve authentication and permission issues when developing Drupal in WSL (Windows Subsystem for Linux) with VS Code.

## Common Issues and Solutions

### 1. File Permission Issues
WSL can have permission conflicts between Windows and Linux file systems. Our solution:

**Quick Fix:**
```bash
# Run our automated permission fix script
./fix-permissions.sh
```

**Manual Fix:**
```bash
# Fix ownership (run from project root)
sudo chown -R leoge:www-data /var/www/drupal11
sudo chmod -R 755 /var/www/drupal11
sudo chmod 664 /var/www/drupal11/web/sites/default/settings.php
sudo chmod -R 775 /var/www/drupal11/web/sites/default/files
```

### 2. Database Authentication
Your database credentials are configured in `settings.php`:
- **Host:** localhost
- **Database:** drupal11_local  
- **Username:** drupal
- **Password:** drupal123

**Test Connection:**
```bash
mysql -u drupal -p'drupal123' -e "SELECT 1;"
```

### 3. VS Code WSL Extension Issues
If VS Code is having trouble with authentication:

1. **Ensure you're in the correct WSL context:**
   - Open VS Code
   - Press `Ctrl+Shift+P`
   - Type "WSL: Connect to WSL"
   - Select your distribution

2. **Check VS Code server permissions:**
   ```bash
   # Fix VS Code server permissions
   sudo chown -R leoge:leoge ~/.vscode-server
   ```

3. **Restart VS Code WSL connection:**
   - Close VS Code
   - In WSL terminal: `code .`

### 4. Sudo Password Issues
If you're constantly prompted for sudo password:

**Option 1: Configure passwordless sudo for specific commands (Recommended)**
```bash
# Edit sudoers file safely
sudo visudo

# Add this line at the end (replace 'leoge' with your username):
leoge ALL=(ALL) NOPASSWD: /bin/chown, /bin/chmod, /usr/bin/systemctl
```

**Option 2: Extend sudo timeout**
```bash
# Edit sudoers file
sudo visudo

# Add or modify this line:
Defaults timestamp_timeout=60
```

### 5. Web Server Permissions
Ensure Apache/Nginx can read your files:

```bash
# Check web server status
systemctl status apache2

# Ensure web server user can access files
sudo usermod -a -G www-data leoge
```

### 6. Git Operations in WSL
If Git operations are slow or cause permission issues:

```bash
# Configure Git to ignore file mode changes
git config core.filemode false

# Fix line ending issues
git config core.autocrlf input
```

## Environment Configuration

### .env File
Your project uses a `.env` file for configuration. Key variables:
- `WSL_USER=leoge` - Your WSL username
- `WEB_USER=www-data` - Web server user
- `DB_*` variables - Database configuration

### Auto-fix Script
Run `./fix-permissions.sh` whenever you encounter permission issues. This script:
- Fixes file ownership and permissions
- Tests database connectivity
- Checks web server status
- Displays current configuration

## VS Code Specific Solutions

### Extension Settings
Add these to your VS Code `settings.json`:

```json
{
    "remote.WSL.fileWatcher.polling": true,
    "remote.WSL.fileWatcher.pollingInterval": 5000,
    "files.watcherExclude": {
        "**/vendor/**": true,
        "**/node_modules/**": true
    }
}
```

### Terminal Integration
Set VS Code to use WSL terminal by default:
- Press `Ctrl+Shift+P`
- Type "Terminal: Select Default Profile"
- Choose "WSL"

## Troubleshooting

### Issue: "Permission denied" errors
**Solution:** Run `./fix-permissions.sh` or manually fix permissions as shown above.

### Issue: Database connection fails
**Solution:** 
1. Check if MySQL is running: `systemctl status mysql`
2. Verify credentials in `settings.php`
3. Test connection: `mysql -u drupal -p'drupal123' -e "SELECT 1;"`

### Issue: Files not updating in browser
**Solution:**
1. Clear Drupal cache: `vendor/bin/drush cache:rebuild`
2. Check file permissions in `web/sites/default/files`
3. Restart web server: `sudo systemctl restart apache2`

### Issue: VS Code extensions not working
**Solution:**
1. Reload VS Code window: `Ctrl+Shift+P` → "Developer: Reload Window"
2. Reinstall extensions in WSL context
3. Check VS Code server logs: `code --verbose`

## Best Practices

1. **Always use the fix-permissions script after Git operations**
2. **Keep your .env file updated with correct paths and credentials**
3. **Use WSL terminal in VS Code for all command-line operations**
4. **Avoid editing files directly in Windows file system when possible**
5. **Regularly check and fix permissions if you encounter issues**

## Quick Commands

```bash
# Fix all permissions
./fix-permissions.sh

# Test everything is working
vendor/bin/drush status

# Clear cache
vendor/bin/drush cache:rebuild

# Check database
mysql -u drupal -p'drupal123' -e "SHOW DATABASES;"

# Restart services
sudo systemctl restart apache2 mysql
```
