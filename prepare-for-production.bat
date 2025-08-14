@echo off
echo ========================================
echo Preparing Drupal 11 for Production Deployment
echo ========================================

echo.
echo Step 1: Checking Git status...
git status

echo.
echo Step 2: Adding all changes to Git...
git add .

echo.
echo Step 3: Committing changes...
set /p commit_message="Enter commit message (or press Enter for default): "
if "%commit_message%"=="" set commit_message=Prepare for production deployment

git commit -m "%commit_message%"

echo.
echo Step 4: Pushing to GitHub...
git push origin master

echo.
echo Step 5: Exporting current configuration...
php vendor/bin/drush config:export

echo.
echo Step 6: Adding config changes to Git...
git add config/
git commit -m "Export configuration for production"
git push origin master

echo.
echo Step 7: Creating database export...
mysqldump -u root drupal11 > drupal11_production.sql
echo Database exported to drupal11_production.sql

echo.
echo ========================================
echo PREPARATION COMPLETE!
echo ========================================
echo.
echo Next steps:
echo 1. Upload drupal11_production.sql to production server
echo 2. Follow the steps in PRODUCTION-DEPLOYMENT-STEPS.md
echo 3. Copy files from old production to new production
echo.
echo Repository URL: https://github.com/jomapps/drupal11.local.git
echo Production Server: 173.249.18.165
echo Domain: https://drupal11.travelm.de
echo.
pause
