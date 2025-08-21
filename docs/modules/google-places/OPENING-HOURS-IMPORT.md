# Google Places - Opening Hours Import Feature

## 🕒 Opening Hours Import Implementation

### **Feature Added**: Automatic Opening Hours Import
Our Google Places module now **automatically imports opening hours** from Google Places API when you click "Get image and save".

## ✅ What's Been Implemented

### **1. Enhanced API Fields**
```php
// Updated API request to include opening hours
$params = [
  'place_id' => $place_id,
  'fields' => 'name,formatted_address,photos,opening_hours', // Added opening_hours
  'language' => 'de', // German language for localized hours
  'key' => $this->apiKey,
];
```

### **2. Opening Hours Field Integration**
- **Field Name**: `field_opening_hours` 
- **Label**: "Öffnungszeiten" (German)
- **Type**: Text (long) with basic HTML formatting
- **Usage**: Automatically populated from Google Places API

### **3. German Language Format**
Opening hours are imported in **perfect German format**:
```
Montag: 05:30–20:00 Uhr
Dienstag: 05:30–20:00 Uhr
Mittwoch: 05:30–20:00 Uhr
Donnerstag: 05:30–20:00 Uhr
Freitag: 05:30–20:00 Uhr
Samstag: 10:00–18:00 Uhr
Sonntag: 10:00–18:00 Uhr
```

## 🧪 Testing with Alter Elbtunnel

### **Test Place Verification**
```bash
# Verify opening hours data is available
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJt5u_5QyPsUcRycCU6-zwZ9c&fields=opening_hours&language=de&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs" | jq -r '.result.opening_hours.weekday_text[]'
```

### **Expected Result**
✅ **API Response Confirmed**:
- Place: Alter Elbtunnel, Hamburg
- Opening hours available in German format
- Days properly formatted with "Uhr" suffix
- Weekend hours differ from weekday hours

## 🔄 How It Works

### **User Workflow**
1. **Edit place content** (or create new)
2. **Click "Get image and save"** button
3. **Module automatically**:
   - Downloads place image
   - **Imports opening hours** to `field_opening_hours`
   - Saves the node with updated data
4. **User sees success message**: "Image successfully downloaded and saved. Opening hours have been imported."

### **Technical Implementation**
```php
// New method in GooglePlacesApiService
protected function importOpeningHours(NodeInterface $node, array $place_data) {
  if (!$node->hasField('field_opening_hours') || empty($place_data['opening_hours'])) {
    return;
  }

  $opening_hours = $place_data['opening_hours'];
  
  // Use the German weekday_text format which is more readable
  if (!empty($opening_hours['weekday_text'])) {
    $hours_text = implode("\n", $opening_hours['weekday_text']);
    
    // Set the opening hours field
    $node->set('field_opening_hours', [
      'value' => $hours_text,
      'format' => 'basic_html',
    ]);
    
    // Save the node
    $node->save();
  }
}
```

## 📋 Field Configuration

### **Existing Field Details**
- **Machine Name**: `field_opening_hours`
- **Label**: "Öffnungszeiten" 
- **Type**: `text_long` (Long text with HTML)
- **Required**: No
- **Translatable**: Yes
- **Status**: Already configured in database

### **Data Format**
- **Source**: Google Places API `opening_hours.weekday_text[]`
- **Format**: Line-separated German day/time format
- **Example**:
  ```
  Montag: 05:30–20:00 Uhr
  Dienstag: 05:30–20:00 Uhr
  ...
  ```

## ✅ Integration Status

### **Module Updates Applied**
- [x] **API Request**: Added `opening_hours` to fields parameter
- [x] **Import Method**: Created `importOpeningHours()` function
- [x] **Node Integration**: Automatic field population and saving
- [x] **User Feedback**: Updated success message
- [x] **German Format**: Properly formatted German weekday text

### **Testing Confirmed**
- [x] **API Data Available**: Alter Elbtunnel has opening hours
- [x] **German Language**: Hours returned in German format
- [x] **Field Configuration**: `field_opening_hours` exists and ready
- [x] **Module Code**: Import functionality implemented

## 🎯 User Testing Instructions

### **Test with Alter Elbtunnel**
1. **Navigate to**: http://drupal11.local/node/add/place
2. **Create place**:
   - Title: `Alter Elbtunnel`
   - Place ID: `ChIJt5u_5QyPsUcRycCU6-zwZ9c`
3. **Save and edit** the place
4. **Click "Get image and save"** button
5. **Verify results**:
   - Success message mentions opening hours
   - Check `field_opening_hours` field for German format hours
   - Image downloaded to places directory

### **Expected Opening Hours**
```
Montag: 05:30–20:00 Uhr
Dienstag: 05:30–20:00 Uhr
Mittwoch: 05:30–20:00 Uhr
Donnerstag: 05:30–20:00 Uhr
Freitag: 05:30–20:00 Uhr
Samstag: 10:00–18:00 Uhr
Sonntag: 10:00–18:00 Uhr
```

## 🔧 Troubleshooting

### **Common Issues**
1. **No opening hours imported**:
   - Check if place has opening hours data in Google Places
   - Verify `field_opening_hours` field exists
   - Check module logs for errors

2. **Wrong format**:
   - Ensure `language=de` parameter is used
   - Verify API response contains `weekday_text`

3. **Field not saving**:
   - Check field permissions
   - Verify text format is allowed
   - Check node save permissions

### **Verification Commands**
```bash
# Test API directly
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=PLACE_ID&fields=opening_hours&language=de&key=API_KEY" | jq '.result.opening_hours'

# Check field configuration
mysql -u drupal -pdrupal123 drupal11_local -e "SELECT * FROM config WHERE name = 'field.field.node.place.field_opening_hours';"
```

## 📊 Benefits

### **For Users**
- ✅ **Automatic Data Population**: No manual entry needed
- ✅ **German Language**: Properly localized opening hours
- ✅ **Consistent Format**: Standardized across all places
- ✅ **Time Saving**: Import with image download

### **For Content Management**
- ✅ **Data Accuracy**: Direct from Google Places
- ✅ **Regular Updates**: Re-import updates hours
- ✅ **Structured Data**: Ready for Schema.org markup
- ✅ **Search Optimization**: Better SEO with opening hours

---

**The opening hours import feature is now fully implemented and ready for testing with our standard test place "Alter Elbtunnel"!** 🎯
