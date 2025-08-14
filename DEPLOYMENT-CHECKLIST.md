# Production Deployment Checklist

## Pre-Deployment Checklist
- [ ] Local repository is clean and committed
- [ ] All changes pushed to GitHub master branch
- [ ] Database export is ready
- [ ] Files from old production are accessible
- [ ] Production server SSH access confirmed
- [ ] Production database credentials confirmed
- [ ] GitHub repository URL confirmed

## Deployment Execution Checklist

### Phase 1: Local Preparation
- [ ] Repository cleaned and committed
- [ ] Configuration exported
- [ ] Database exported for production import

### Phase 2: Production Server Setup
- [ ] SSH into production server successful
- [ ] Repository cloned to public_html
- [ ] Composer dependencies installed
- [ ] File permissions set correctly

### Phase 3: Environment Configuration
- [ ] Production settings.php created with correct DB credentials
- [ ] Services.yml configured
- [ ] Files directory created with proper permissions
- [ ] Trusted host patterns configured

### Phase 4: Data Migration
- [ ] Database imported successfully
- [ ] Files copied from old production
- [ ] File permissions verified

### Phase 5: Drupal Configuration
- [ ] Cache cleared
- [ ] Database updates run
- [ ] Configuration imported
- [ ] Final cache clear completed

### Phase 6: Testing & Verification
- [ ] Site loads at https://drupal11.travelm.de
- [ ] Admin login works
- [ ] Media files accessible
- [ ] All functionality tested
- [ ] SSL certificate working (if configured)

### Phase 7: Future Deployment Setup
- [ ] Deployment script created
- [ ] Git pull workflow tested
- [ ] Permissions script verified

## Post-Deployment Tasks
- [ ] Update DNS if needed
- [ ] Configure SSL certificate in DirectAdmin
- [ ] Set up automated backups
- [ ] Configure monitoring
- [ ] Update documentation

## Emergency Rollback Plan
If something goes wrong:
1. SSH into production server
2. Navigate to: `/home/admin/domains/drupal11.travelm.de/`
3. Rename current public_html: `mv public_html public_html_backup`
4. Restore from backup or re-clone repository
5. Restore database from backup

## Contact Information
- **Production Server**: 173.249.18.165
- **SSH**: root@173.249.18.165
- **Domain**: https://drupal11.travelm.de
- **Control Panel**: DirectAdmin

## Important File Locations
- **Document Root**: `/home/admin/domains/drupal11.travelm.de/public_html`
- **Web Root**: `/home/admin/domains/drupal11.travelm.de/public_html/web`
- **Settings**: `/home/admin/domains/drupal11.travelm.de/public_html/web/sites/default/settings.php`
- **Files**: `/home/admin/domains/drupal11.travelm.de/public_html/web/sites/default/files`
