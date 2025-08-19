# Quick Fix Commands for WSL Permission Issues

## Immediate Solutions for Common Problems

### 1. Fix File Permissions (Most Common Issue)
```bash
# Quick permission fix for entire project
sudo chown -R leoge:www-data /var/www/drupal11
sudo chmod -R 755 /var/www/drupal11
sudo chmod 664 /var/www/drupal11/web/sites/default/settings.php
sudo chmod -R 775 /var/www/drupal11/web/sites/default/files

# Make scripts executable
sudo chmod +x /var/www/drupal11/vendor/bin/*
```

### 2. Database Connection Issues
```bash
# Test database connection
mysql -u drupal -p'drupal123' -e "SELECT 1;"

# Check MySQL status
systemctl status mysql

# Start MySQL if needed
sudo systemctl start mysql
```

### 3. VS Code WSL Extension Issues
```bash
# Fix VS Code server permissions
sudo chown -R leoge:leoge ~/.vscode-server

# Restart VS Code connection (close VS Code, then run):
code .
```

### 4. Web Server Issues
```bash
# Check Apache status
systemctl status apache2

# Restart Apache if needed
sudo systemctl restart apache2

# Test if site loads
curl -I http://drupal11.local
```

### 5. Drupal Cache and Status
```bash
# Check Drupal status
vendor/bin/drush status

# Clear Drupal cache
vendor/bin/drush cache:rebuild

# Get admin login link
vendor/bin/drush user:login
```

## One-Line Solutions

### Complete Permission Reset
```bash
sudo chown -R leoge:www-data /var/www/drupal11 && sudo chmod -R 755 /var/www/drupal11 && sudo chmod 664 /var/www/drupal11/web/sites/default/settings.php && sudo chmod -R 775 /var/www/drupal11/web/sites/default/files
```

### VS Code + Permissions Fix
```bash
sudo chown -R leoge:leoge ~/.vscode-server && sudo chown -R leoge:www-data /var/www/drupal11 && code .
```

### Full Service Restart
```bash
sudo systemctl restart mysql apache2 && vendor/bin/drush cache:rebuild
```

## Current Working Configuration

✅ **Database:** Connected successfully
- Host: localhost
- Database: drupal11_local  
- User: drupal
- Password: drupal123

✅ **File Permissions:** Fixed
- Owner: leoge:www-data
- settings.php: 664 permissions
- Project files: 755/664 permissions

✅ **Services:** Running
- MySQL: Active
- Apache2: Active
- PHP 8.3: Available

## What to Do When Issues Occur

1. **Permission denied errors:** Run the complete permission reset command above
2. **Database connection fails:** Check MySQL service and credentials
3. **VS Code not working:** Fix VS Code server permissions and restart
4. **Site not loading:** Restart Apache and clear Drupal cache
5. **Git issues:** Run permission fix after any Git operations

## Prevention

Add this to your bash profile (`~/.bashrc` or `~/.profile`):
```bash
# Drupal development aliases
alias drupal-fix='sudo chown -R leoge:www-data /var/www/drupal11 && sudo chmod -R 755 /var/www/drupal11 && sudo chmod 664 /var/www/drupal11/web/sites/default/settings.php'
alias drupal-status='cd /var/www/drupal11 && vendor/bin/drush status'
alias drupal-cache='cd /var/www/drupal11 && vendor/bin/drush cache:rebuild'
```

Reload your profile:
```bash
source ~/.bashrc
```

Now you can use:
- `drupal-fix` - Fix permissions
- `drupal-status` - Check Drupal status  
- `drupal-cache` - Clear cache
