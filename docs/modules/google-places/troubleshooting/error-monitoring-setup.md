# Google Places - Error Monitoring Setup

## Log File Locations

### System Logs
- **Apache Error Log**: `/var/log/apache2/error.log`
- **PHP-FPM Log**: `/var/log/php8.3-fpm.log`
- **System Log**: `/var/log/syslog`

### Drupal Logs
- **Database Logs**: Available through Drupal admin interface
- **File-based Logs**: To be configured
- **Watchdog Logs**: Stored in database `watchdog` table

## Real-time Monitoring Commands

### Start Log Monitoring Session
```bash
# Monitor Apache errors in real-time
tail -f /var/log/apache2/error.log

# Monitor PHP errors
tail -f /var/log/php8.3-fpm.log

# Monitor multiple logs simultaneously
multitail /var/log/apache2/error.log /var/log/php8.3-fpm.log
```

### Filter for Google Places Specific Errors
```bash
# Search for Google/Places related errors
grep -i "google\|places\|api" /var/log/apache2/error.log

# Monitor for HTTP errors (likely API failures)
tail -f /var/log/apache2/error.log | grep -E "(4[0-9]{2}|5[0-9]{2})"

# Watch for specific error patterns
tail -f /var/log/apache2/error.log | grep -E "(blocked|forbidden|denied|quota|rate)"
```

## Error Patterns to Watch For

### Google API Errors
- `PERMISSION_DENIED`
- `QUOTA_EXCEEDED`
- `RATE_LIMIT_EXCEEDED`
- `INVALID_REQUEST`
- `NOT_FOUND`
- `REQUEST_DENIED`

### HTTP Status Codes
- `403` - Forbidden (likely API key restrictions)
- `429` - Too Many Requests (rate limiting)
- `400` - Bad Request (malformed API request)
- `404` - Not Found (invalid place ID or photo reference)

### Network/Connection Errors
- `cURL error`
- `Connection timeout`
- `SSL certificate problem`
- `Could not resolve host`

## Drupal-Specific Monitoring

### Enable Database Logging
```php
// Add to settings.php for detailed logging
$settings['logging']['channels']['google_places'] = [
  'handlers' => ['file'],
  'level' => 'debug',
];
```

### Custom Log File Setup
```php
// Custom log file for Google Places operations
$settings['google_places_log_file'] = '/var/www/drupal11/logs/google-places.log';
```

## Testing Error Scenarios

### Simulate Common Errors
1. **Invalid API Key**: Temporarily modify API key
2. **Rate Limiting**: Make rapid successive requests
3. **Invalid Place ID**: Use non-existent place ID
4. **Network Issues**: Block outbound HTTPS requests

### Error Reproduction Steps
1. Navigate to Places content type
2. Create/edit a place entry
3. Click "Get image and save" button
4. Monitor logs for errors
5. Document exact error messages and timestamps

## Automated Monitoring Script
```bash
#!/bin/bash
# Save as: /var/www/drupal11/scripts/monitor-places-errors.sh

LOG_FILE="/var/log/apache2/error.log"
ALERT_FILE="/tmp/places-errors.log"

tail -f $LOG_FILE | while read line; do
    if echo "$line" | grep -qi "google\|places\|api"; then
        echo "$(date): $line" >> $ALERT_FILE
        echo "PLACES ERROR DETECTED: $line"
    fi
done
```

## Log Analysis Commands

### Extract Google Places Related Entries
```bash
# Get last 24 hours of Google/Places errors
grep -i "google\|places" /var/log/apache2/error.log | grep "$(date '+%b %d')"

# Count error types
grep -i "google\|places" /var/log/apache2/error.log | cut -d' ' -f9 | sort | uniq -c

# Extract error messages with timestamps
awk '/google|places/i {print $1" "$2" "$3": "$0}' /var/log/apache2/error.log
```

## Next Steps After Error Detection

1. **Capture Full Error Context**
   - Complete error message
   - HTTP status code
   - Timestamp
   - Request details

2. **Verify API Key Configuration**
   - Check Google Cloud Console
   - Verify API restrictions
   - Test API key manually

3. **Analyze Request Pattern**
   - Check request frequency
   - Verify request format
   - Test with curl/postman

4. **Document Resolution**
   - Update this document with findings
   - Create resolution procedures
   - Add to knowledge base
