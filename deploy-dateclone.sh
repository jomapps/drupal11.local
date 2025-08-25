#!/bin/bash

# DateClone Module Deployment Script for Drupal 11
# Usage: ./deploy-dateclone.sh

echo "🚀 Deploying DateClone Module to Drupal 11..."

# Check if we're in a Drupal directory
if [ ! -f "vendor/bin/drush" ]; then
    echo "❌ Error: Not in a Drupal directory. Please run from Drupal root."
    exit 1
fi

# Enable the module
echo "📦 Enabling DateClone module..."
vendor/bin/drush en dateclone -y

# Grant permissions
echo "🔐 Setting permissions..."
vendor/bin/drush role:perm:add administrator 'access dateclone'
vendor/bin/drush role:perm:add authenticated 'access dateclone'

# Configure field widget for event content type
echo "⚙️ Configuring field widget..."
vendor/bin/drush ev "
\$display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.event.default');
if (\$display) {
  \$display->setComponent('field_startdate', [
    'type' => 'dateclone_default', 
    'weight' => 13, 
    'region' => 'content',
    'settings' => [],
    'third_party_settings' => []
  ]);
  \$display->save();
  echo 'Widget configured successfully\n';
} else {
  echo 'Warning: Could not find event form display\n';
}"

# Clear cache
echo "🧹 Clearing cache..."
vendor/bin/drush cr

# Verify installation
echo "✅ Verifying installation..."
if vendor/bin/drush pml --filter=dateclone --status=enabled | grep -q "dateclone"; then
    echo "✅ SUCCESS: DateClone module is installed and enabled!"
    echo ""
    echo "🎯 Next steps:"
    echo "1. Go to /node/add/event on your site"
    echo "2. Look for DateClone buttons below the Start date field"
    echo "3. Test weekday buttons (MO, DI, MI, etc.)"
    echo ""
else
    echo "❌ ERROR: Module installation failed. Check logs."
    exit 1
fi

echo "🎉 DateClone deployment complete!"
