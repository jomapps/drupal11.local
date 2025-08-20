# Google Places Module - Field Population Troubleshooting Guide

## 🎯 **Overview**

This guide helps troubleshoot and verify field population functionality in the Google Places module after successful autocomplete implementation.

## ✅ **Prerequisites - What Should Be Working**

Before troubleshooting field population, ensure these components are working:

1. **✅ Autocomplete Widget**: Google Places autocomplete suggestions appear
2. **✅ Place ID Storage**: Place ID (starting with "ChIJ") is stored in `field_place_id`
3. **✅ Get Details Button**: No AJAX errors when pressed
4. **✅ API Connection**: Successfully fetches data from Google Places API

## 🏗️ **Field Population Architecture**

### **How Field Population Works**:

1. **User selects place** from autocomplete → Place ID stored in `field_place_id`
2. **User presses "Get Details"** → AJAX call to `google_places_get_details_ajax()`
3. **Service extracts Place ID** from form values using `getPlaceIdFromFormOrNode()`
4. **API call to Google Places** using `getPlaceDetailsForPopulation()`
5. **Field mapping** via `populatePlaceData()` populates form fields
6. **JavaScript updates** visible form fields via AJAX commands

### **Field Mapping Configuration**:

```php
// Target field mapping in populatePlaceData()
$populated_fields = [
    'title[0][value]' => $place_data['name'],
    'field_formatted_address[0][value]' => $place_data['formatted_address'],
    'field_latitude[0][value]' => $lat,
    'field_longitude[0][value]' => $lng,
    'field_phone[0][value]' => $place_data['formatted_phone_number'],
    'field_url[0][uri]' => $place_data['website'],
    'field_opening_hours[0][value]' => $hours_text,
];
```

## 🔍 **Debugging Field Population Issues**

### **Step 1: Verify Place ID Detection**

1. **Go to**: `http://drupal11.local/admin/reports/dblog`
2. **Filter by**: `google_places` type
3. **Look for**: 
   ```
   ✅ "Found Place ID in form field field_place_id: ChIJt5u_5QyPsUcRycCU6-zwZ9c"
   ❌ "No Google Place ID found for this place"
   ```

### **Step 2: Check Google Places API Response**

Enable detailed API logging:

```bash
# Check if API is responding correctly
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJt5u_5QyPsUcRycCU6-zwZ9c&fields=name,formatted_address,geometry,opening_hours,formatted_phone_number,website&language=de&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs" | python3 -m json.tool
```

**Expected Response**:
```json
{
    "status": "OK",
    "result": {
        "name": "Alter Elbtunnel",
        "formatted_address": "Bei den Sankt Pauli-Landungsbrücken, 20359 Hamburg, Germany",
        "geometry": {
            "location": {
                "lat": 53.5444494,
                "lng": 9.9655775
            }
        },
        "formatted_phone_number": "+49 40 42828-0",
        "website": "https://www.hamburg.de/...",
        "opening_hours": {
            "weekday_text": ["Montag: 06:00–20:00", ...]
        }
    }
}
```

### **Step 3: Verify Field Existence in Content Type**

Check if the target fields exist in the Place content type:

1. **Go to**: `http://drupal11.local/admin/structure/types/manage/place/fields`
2. **Verify these fields exist**:
   - `field_formatted_address` (Text field)
   - `field_latitude` (Number/Decimal field)
   - `field_longitude` (Number/Decimal field) 
   - `field_phone` (Text field)
   - `field_url` (Link field)
   - `field_opening_hours` (Text area field)

### **Step 4: Check Form Field Names**

The field mapping uses specific form field names. Verify the actual form field names:

1. **Go to place creation form**
2. **Right-click → Inspect** on each field
3. **Check the `name` attribute**:

```html
<!-- Expected field names -->
<input name="title[0][value]" ...>
<input name="field_formatted_address[0][value]" ...>
<input name="field_latitude[0][value]" ...>
<input name="field_longitude[0][value]" ...>
<input name="field_phone[0][value]" ...>
<input name="field_url[0][uri]" ...>
<textarea name="field_opening_hours[0][value]" ...>
```

## 🛠️ **Common Issues and Solutions**

### **Issue 1: Fields Not Populating Despite Successful API Call**

**Symptoms**: 
- ✅ Place ID detected correctly
- ✅ API returns valid data
- ❌ Form fields remain empty

**Solution**: Check field name mapping

```php
// In GooglePlacesApiService.php, update field names if needed
$populated_fields['actual_field_name[0][value]'] = $place_data['name'];
```

### **Issue 2: Only Some Fields Populate**

**Symptoms**: Some fields populate, others don't

**Debugging**:
1. **Check browser console** for JavaScript errors
2. **Verify field types** match expected values
3. **Check field permissions** (user can edit the field)

### **Issue 3: Opening Hours Not Importing**

**Symptoms**: Opening hours field remains empty

**Check**:
```php
// Verify opening hours field exists and accepts text
if ($node->hasField('field_opening_hours')) {
    // Field exists
} else {
    // Field missing - create it
}
```

### **Issue 4: Coordinates Not Populating**

**Symptoms**: Latitude/longitude fields empty

**Check**:
- Field type should be "Number (decimal)" or "Number (float)"
- Field should accept decimal values
- Check if geometry data exists in API response

## 🔧 **Manual Field Creation**

If fields are missing, create them manually:

### **Create Formatted Address Field**:
1. **Go to**: Structure → Content types → Place → Manage fields
2. **Add field**: 
   - Type: Text (plain)
   - Label: "Formatted Address"
   - Machine name: `field_formatted_address`

### **Create Coordinate Fields**:
1. **Add field**:
   - Type: Number (decimal)
   - Label: "Latitude" / "Longitude" 
   - Machine name: `field_latitude` / `field_longitude`
   - Decimal places: 8

### **Create Opening Hours Field**:
1. **Add field**:
   - Type: Text (formatted, long)
   - Label: "Opening Hours"
   - Machine name: `field_opening_hours`

## 📊 **Testing Checklist**

### **Complete Field Population Test**:

1. **✅ Create new place node**
2. **✅ Use autocomplete** to select "Alter Elbtunnel"
3. **✅ Verify Place ID** stored: `ChIJt5u_5QyPsUcRycCU6-zwZ9c`
4. **✅ Press "Get Details"**
5. **✅ Check each field populates**:
   - [ ] Title: "Alter Elbtunnel"
   - [ ] Formatted Address: "Bei den Sankt Pauli-Landungsbrücken, 20359 Hamburg, Germany"
   - [ ] Latitude: 53.5444494
   - [ ] Longitude: 9.9655775
   - [ ] Phone: "+49 40 42828-0"
   - [ ] Website: URL populated
   - [ ] Opening Hours: German weekday text

## 🐛 **Advanced Debugging**

### **Enable Detailed Logging**:

Add temporary debug logging to `GooglePlacesApiService.php`:

```php
// In populatePlaceData() method
$this->logger->debug('API Response data: @data', [
    '@data' => json_encode($place_data)
]);

$this->logger->debug('Populated fields: @fields', [
    '@fields' => json_encode($populated_fields)
]);
```

### **Check AJAX Response**:

1. **Open Network tab** in browser dev tools
2. **Press "Get Details"**
3. **Check AJAX response** for populated field commands
4. **Look for errors** in response

## 📋 **Next Steps**

Once field population is working:

1. **✅ Test "Get image and save"** functionality
2. **✅ Verify image download** and storage
3. **✅ Test with multiple places**
4. **✅ Performance optimization**
5. **✅ User documentation**

## 🔗 **Related Documentation**

- [API Configuration Guide](./API-CONFIGURATION.md)
- [Standard Test Configuration](./STANDARD-TEST-CONFIGURATION.md)
- [Troubleshooting Guide](./troubleshooting/README.md)
- [Google API Modernization](./GOOGLE-API-MODERNIZATION.md)

---

**Last Updated**: August 20, 2025  
**Version**: 2.0 (Post-Fix Implementation)  
**Test Place**: Alter Elbtunnel (ChIJt5u_5QyPsUcRycCU6-zwZ9c)
