# Google Places Module - Standard Test Configuration

## 🎯 Standard Test Place

For all Google Places module testing, we will consistently use:

### **Test Place Details**
- **Place ID**: `ChIJt5u_5QyPsUcRycCU6-zwZ9c`
- **Title**: `Alter Elbtunnel`
- **Location**: Hamburg, Deutschland
- **Address**: `Bei den St. Pauli-Landungsbrücken, 20359 Hamburg, Deutschland`

### **Why This Place?**
- **Historic Location**: Alter Elbtunnel (Old Elbe Tunnel) in Hamburg
- **German Context**: Perfect for testing German language responses
- **Photo Availability**: 10+ photos available for testing image download
- **Consistent Testing**: Same place ID for reproducible results

## 🧪 Testing Protocol

### **Before Each Test Session**
1. **Delete existing test place** (if any) in Drupal
2. **Create new place content** with our standard test data
3. **Use consistent place ID** for all tests
4. **Verify functionality** works as expected

### **Standard Test Data**
```
Content Type: Place
Title: Alter Elbtunnel
Google Place ID: ChIJt5u_5QyPsUcRycCU6-zwZ9c
Google Maps URL: https://maps.google.com/maps/place/?q=place_id:ChIJt5u_5QyPsUcRycCU6-zwZ9c
```

## ✅ API Verification (Confirmed Working)

### **API Response Test**
```bash
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJt5u_5QyPsUcRycCU6-zwZ9c&fields=name,formatted_address,photos&language=de&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs"
```

### **Expected German Response**
```json
{
  "status": "OK",
  "name": "Alter Elbtunnel",
  "address": "Bei den St. Pauli-Landungsbrücken, 20359 Hamburg, Deutschland",
  "photo_count": 10
}
```

### **Confirmed Features**
- ✅ **API Status**: OK
- ✅ **German Name**: "Alter Elbtunnel" 
- ✅ **German Address**: "Hamburg, Deutschland"
- ✅ **Photos Available**: 10 images for download testing
- ✅ **API Key**: Working with our key

## 🔄 Testing Workflow

### **1. Environment Setup**
- **Local**: http://drupal11.local/node/add/place
- **Production**: http://drupal11.travelm.de/node/add/place

### **2. Create Test Content**
1. Navigate to "Add Place" form
2. Enter title: `Alter Elbtunnel`
3. Add place ID: `ChIJt5u_5QyPsUcRycCU6-zwZ9c`
4. Save the content

### **3. Test "Get Image and Save"**
1. Edit the created place
2. Look for "Google Places" fieldset
3. Click "Get image and save" button
4. Verify image downloads successfully
5. Check `sites/default/files/places/ChIJt5u_5QyPsUcRycCU6-zwZ9c/` directory

### **4. Verify German Language**
1. Confirm place name appears in German
2. Check address format is German
3. Verify API responses use `language=de`

## 📁 Expected File Structure

After successful test:
```
sites/default/files/places/ChIJt5u_5QyPsUcRycCU6-zwZ9c/
└── [md5hash].jpg  # Downloaded image from Google Places
```

## 🎯 Test Scenarios

### **Basic Functionality**
- [ ] Place content creation
- [ ] Place ID recognition
- [ ] "Get image and save" button appears
- [ ] Button click triggers AJAX request
- [ ] Image downloads successfully

### **German Language**
- [ ] Place name in German: "Alter Elbtunnel"
- [ ] Address in German format with "Deutschland"
- [ ] API calls include `language=de` parameter

### **Error Handling**
- [ ] Invalid place ID handling
- [ ] Network error graceful degradation
- [ ] User feedback messages working

### **File Management**
- [ ] Images saved to correct directory
- [ ] File permissions correct
- [ ] No duplicate downloads

## 🔧 Development Testing Commands

### **Quick API Test**
```bash
# Test place details
curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJt5u_5QyPsUcRycCU6-zwZ9c&fields=name,photos&language=de&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs" | jq '{name: .result.name, photo_count: (.result.photos | length)}'

# Test photo download
PHOTO_REF=$(curl -s "https://maps.googleapis.com/maps/api/place/details/json?place_id=ChIJt5u_5QyPsUcRycCU6-zwZ9c&fields=photos&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs" | jq -r '.result.photos[0].photo_reference')
curl -L -s -o /tmp/alter-elbtunnel-test.jpg "https://maps.googleapis.com/maps/api/place/photo?photoreference=$PHOTO_REF&key=AIzaSyBadGk6QQ3EAvOW5IU8Ybt73AB86Zc-aHs&maxwidth=400"
file /tmp/alter-elbtunnel-test.jpg
```

### **Check Existing Test Data**
```bash
# Check if place directory exists
ls -la /var/www/drupal11/web/sites/default/files/places/ | grep ChIJt5u_5QyPsUcRycCU6-zwZ9c

# Check downloaded images
ls -la /var/www/drupal11/web/sites/default/files/places/ChIJt5u_5QyPsUcRycCU6-zwZ9c/
```

## 📝 Test Documentation

### **Test Results Template**
```
Test Date: [DATE]
Environment: [Local/Production]
Place ID: ChIJt5u_5QyPsUcRycCU6-zwZ9c
Title: Alter Elbtunnel

Results:
- [ ] Content creation: Pass/Fail
- [ ] German language: Pass/Fail  
- [ ] Image download: Pass/Fail
- [ ] File saved: Pass/Fail

Notes: [Any issues or observations]
```

---

**This standard test configuration ensures consistent, reproducible testing of the Google Places module functionality across all environments.**
