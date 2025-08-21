/**
 * Google Places Autocomplete functionality
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.googlePlacesAutocomplete = {
    attach: function (context, settings) {
      console.log('Google Places Autocomplete behavior attaching...');
      once('google-places-autocomplete', '.google-places-autocomplete', context).forEach(function(element) {
        console.log('Found autocomplete field:', element);
        var $field = $(element);
        var autoPopulate = $field.attr('data-auto-populate') === 'true';
        
        // Check if Google Places API is available
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.places !== 'undefined') {
          // API already loaded, initialize directly
          initAutocomplete(element, autoPopulate);
        } else if (!window.googlePlacesApiLoading) {
          // Mark that we're loading the API to prevent duplicates
          window.googlePlacesApiLoading = true;
          
          var apiKey = drupalSettings.googlePlaces ? drupalSettings.googlePlaces.apiKey : '';
          if (apiKey) {
            // Store field reference for callback
            window.googlePlacesFields = window.googlePlacesFields || [];
            window.googlePlacesFields.push({
              field: element,
              autoPopulate: autoPopulate
            });
            
            // Check if script already exists
            var existingScript = document.querySelector('script[src*="maps.googleapis.com"]');
            if (!existingScript) {
              var script = document.createElement('script');
              script.src = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&libraries=places&loading=async&callback=initGooglePlacesAutocomplete&v=beta';
              script.async = true;
              script.defer = true;
              script.onload = function() {
                console.log('Google Maps API script loaded successfully');
              };
              script.onerror = function() {
                console.error('Failed to load Google Maps API script');
                window.googlePlacesApiLoading = false;
              };
              document.head.appendChild(script);
            }
          } else {
            console.error('Google Places API key not configured');
          }
        } else {
          // API is currently loading, store field for later initialization
          window.googlePlacesFields = window.googlePlacesFields || [];
          window.googlePlacesFields.push({
            field: element,
            autoPopulate: autoPopulate
          });
        }
      });
    }
  };

  // Global callback for Google Maps API
  window.initGooglePlacesAutocomplete = function() {
    console.log('Google Maps API loaded, initializing autocomplete fields...');
    try {
      // Reset loading flag
      window.googlePlacesApiLoading = false;
      
      if (window.googlePlacesFields) {
        window.googlePlacesFields.forEach(function(fieldData) {
          try {
            initAutocomplete(fieldData.field, fieldData.autoPopulate);
          } catch (error) {
            console.error('Error initializing autocomplete for field:', fieldData.field, error);
          }
        });
        window.googlePlacesFields = [];
      }
    } catch (error) {
      console.error('Error in initGooglePlacesAutocomplete:', error);
      window.googlePlacesApiLoading = false;
    }
  };

  /**
   * Initialize autocomplete for a field
   */
  function initAutocomplete(field, autoPopulate) {
    // Check what APIs are available
    var apiAvailability = {
      'AutocompleteService': typeof google.maps.places.AutocompleteService !== 'undefined',
      'Place': typeof google.maps.places.Place !== 'undefined',
      'Autocomplete (Legacy)': typeof google.maps.places.Autocomplete !== 'undefined',
      'PlacesService (Legacy)': typeof google.maps.places.PlacesService !== 'undefined',
      'google.maps.places': typeof google.maps.places !== 'undefined'
    };
    console.log('Available APIs:', apiAvailability);
    
    // Use Modern Web Service API approach (HTTP-based Autocomplete New + Place Details New)
    var apiKey = drupalSettings.googlePlaces ? drupalSettings.googlePlaces.apiKey : '';
    if (apiKey) {
      console.log('Using Modern Web Service API (Autocomplete New + Place Details New)');
      try {
        initModernJavaScriptAPI(field, autoPopulate);
      } catch (error) {
        console.error('Modern Web Service API failed, falling back to Legacy:', error);
        if (typeof google.maps.places.Autocomplete !== 'undefined') {
          initLegacyAutocomplete(field, autoPopulate);
        }
      }
    } else if (typeof google.maps.places.Autocomplete !== 'undefined') {
      console.log('Using Legacy Autocomplete API (Modern API key not available)');
      initLegacyAutocomplete(field, autoPopulate);
    } else {
      console.error('No Google Places Autocomplete API available');
    }
  }

  /**
   * Initialize modern Web Service API (HTTP-based Autocomplete New)
   */
  function initModernJavaScriptAPI(field, autoPopulate) {
    console.log('Initializing modern Web Service API for field:', field);
    
    // Get API key for direct HTTP requests
    var apiKey = drupalSettings.googlePlaces ? drupalSettings.googlePlaces.apiKey : '';
    if (!apiKey) {
      console.error('Modern API: No API key available for Web Service calls');
      return;
    }
    
    console.log('Modern API: Using Web Service API with key:', apiKey.substring(0, 10) + '...');
    
    // Create a dropdown for suggestions
    var suggestionsList = document.createElement('ul');
    suggestionsList.style.cssText = 'position: absolute; background: white; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; z-index: 1000; width: 100%; margin: 0; padding: 0; list-style: none; display: none;';
    $(field).after(suggestionsList);
    
    // Handle input changes with debouncing
    var debounceTimer;
    $(field).on('input', function() {
      var input = $(this).val();
      console.log('Modern Web Service API: Input changed to:', input);
      
      clearTimeout(debounceTimer);
      
      if (input.length < 2) {
        $(suggestionsList).hide().empty();
        return;
      }
      
      // Debounce API calls
      debounceTimer = setTimeout(function() {
        // Call the modern Autocomplete (New) Web Service API
        var requestBody = {
          input: input,
          includedPrimaryTypes: ["establishment"],
          languageCode: "de",
          regionCode: "de",
          locationBias: {
            rectangle: {
              low: {
                latitude: 47.2701115,
                longitude: 5.8663425
              },
              high: {
                latitude: 55.0815,
                longitude: 15.0418962
              }
            }
          }
        };
        
        console.log('Modern Web Service API: Making request:', requestBody);
        
        fetch('https://places.googleapis.com/v1/places:autocomplete', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Goog-Api-Key': apiKey
          },
          body: JSON.stringify(requestBody)
        })
        .then(function(response) {
          console.log('Modern Web Service API: Response received:', response);
          return response.json();
        })
        .then(function(data) {
          console.log('Modern Web Service API: Data parsed:', data);
          
          if (data.suggestions && data.suggestions.length > 0) {
            displayModernPredictions(data.suggestions, suggestionsList, field, autoPopulate, apiKey);
          } else {
            $(suggestionsList).hide().empty();
          }
        })
        .catch(function(error) {
          console.error('Modern Web Service API: Error:', error);
          $(suggestionsList).hide().empty();
        });
      }, 300); // 300ms debounce
    });
    
    // Hide suggestions when clicking outside
    $(document).on('click', function(event) {
      if (!$(event.target).closest(field).length && !$(event.target).closest(suggestionsList).length) {
        $(suggestionsList).hide();
      }
    });
    
    console.log('Modern Web Service API: Event listeners attached');
  }
  
  /**
   * Display predictions from modern Web Service API
   */
  function displayModernPredictions(suggestions, suggestionsList, field, autoPopulate, apiKey) {
    $(suggestionsList).empty().show();
    
    suggestions.forEach(function(suggestion) {
      if (suggestion.placePrediction) {
        var prediction = suggestion.placePrediction;
        var li = document.createElement('li');
        li.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;';
        li.textContent = prediction.text.text;
        
        // Hover effects
        $(li).hover(
          function() { $(this).css('background-color', '#f0f0f0'); },
          function() { $(this).css('background-color', 'white'); }
        );
        
        // Click handler
        $(li).click(function() {
          console.log('Modern Web Service API: Place selected:', prediction);
          
          // Set the display name in the field
          $(field).val(prediction.text.text);
          
          // Extract Place ID from the place field
          var placeId = prediction.placeId;
          console.log('Modern Web Service API: Place ID from prediction:', placeId);
          
          // Get place details using the modern Place Details (New) API
          fetchPlaceDetailsModern(placeId, apiKey).then(function(placeData) {
            console.log('Modern Web Service API: Place details received:', placeData);
            
            // Update the original field with Place ID for backend processing
            $(field).val(placeData.id);
            
            console.log('Modern Web Service API: Place ID set:', placeData.id);
            console.log('Modern Web Service API: Place ID length:', placeData.id.length);
            console.log('Modern Web Service API: Place ID starts with:', placeData.id.substring(0, 10));
            
            // Add visual confirmation
            var existingIndicator = $(field).next('.place-id-indicator');
            if (existingIndicator.length) {
              existingIndicator.remove();
            }
            $(field).after('<span class="place-id-indicator" style="color: green; font-size: 12px; margin-left: 5px;">✓ Place ID: ' + placeData.id.substring(0, 15) + '...</span>');
            
            setTimeout(function() {
              $('.place-id-indicator').fadeOut(500, function() { $(this).remove(); });
            }, 3000);
            
            // Auto-populate other fields if enabled
            if (autoPopulate) {
              populateFormFieldsFromModernWebService(placeData);
            }
            
            // Trigger change event
            $(field).trigger('change');
          }).catch(function(error) {
            console.error('Modern Web Service API: Error fetching place details:', error);
            
            // Fallback: Just use the Place ID from prediction
            $(field).val(placeId);
            console.log('Modern Web Service API: Fallback - using prediction Place ID:', placeId);
            
            var existingIndicator = $(field).next('.place-id-indicator');
            if (existingIndicator.length) {
              existingIndicator.remove();
            }
            $(field).after('<span class="place-id-indicator" style="color: orange; font-size: 12px; margin-left: 5px;">⚠ Place ID: ' + placeId.substring(0, 15) + '...</span>');
            
            setTimeout(function() {
              $('.place-id-indicator').fadeOut(500, function() { $(this).remove(); });
            }, 3000);
            
            $(field).trigger('change');
          });
          
          $(suggestionsList).hide();
        });
        
        suggestionsList.appendChild(li);
      }
    });
  }
  
  /**
   * Fetch place details using modern Place Details (New) API
   */
  function fetchPlaceDetailsModern(placeId, apiKey) {
    var requestBody = {
      fields: ["id", "displayName", "formattedAddress", "location", "types"]
    };
    
    return fetch('https://places.googleapis.com/v1/places/' + placeId, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Goog-Api-Key': apiKey,
        'X-Goog-FieldMask': requestBody.fields.join(',')
      }
    })
    .then(function(response) {
      if (!response.ok) {
        throw new Error('Place Details API request failed: ' + response.status);
      }
      return response.json();
    });
  }
  
  /**
   * Populate form fields with data from modern Web Service API
   */
  function populateFormFieldsFromModernWebService(placeData) {
    var $form = $(document).find('form');
    
    // Populate title/name
    if (placeData.displayName && $form.find('[name="title[0][value]"]').length) {
      $form.find('[name="title[0][value]"]').val(placeData.displayName);
    }
    
    // Populate formatted address
    if (placeData.formattedAddress && $form.find('[name*="field_formatted_address"]').length) {
      $form.find('[name*="field_formatted_address"]').val(placeData.formattedAddress);
    }
    
    // Populate coordinates
    if (placeData.location) {
      var lat = placeData.location.latitude;
      var lng = placeData.location.longitude;
      
      console.log('Modern Web Service API: Coordinates - Lat:', lat, 'Lng:', lng);
      
      if (lat && $form.find('[name*="field_latitude"]').length) {
        $form.find('[name*="field_latitude"]').val(lat);
        console.log('Modern Web Service API: Set latitude:', lat);
      }
      
      if (lng && $form.find('[name*="field_longitude"]').length) {
        $form.find('[name*="field_longitude"]').val(lng);
        console.log('Modern Web Service API: Set longitude:', lng);
      }
    }
    
    // Show success message
    if (Drupal.announce) {
      Drupal.announce(Drupal.t('Place data populated successfully!'));
    }
  }
  
  /**
   * Display predictions in dropdown (Legacy support)
   */
  function displayPredictions(predictions, suggestionsList, field, autoPopulate) {
    $(suggestionsList).empty().show();
    
    predictions.forEach(function(prediction) {
      var li = document.createElement('li');
      li.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;';
      li.textContent = prediction.description;
      
      // Hover effects
      $(li).hover(
        function() { $(this).css('background-color', '#f0f0f0'); },
        function() { $(this).css('background-color', 'white'); }
      );
      
      // Click handler
      $(li).click(function() {
        console.log('Modern JavaScript API: Place selected:', prediction);
        
        // Set the display name in the field
        $(field).val(prediction.description);
        
        // Use the modern Place API instead of PlacesService
        try {
          // Create a Place object using the place_id
          var place = new google.maps.places.Place({
            id: prediction.place_id
          });
          
          console.log('Modern JavaScript API: Place object created:', place);
          
          // Fetch place details using the modern fetchFields method
          place.fetchFields({
            fields: ['id', 'displayName', 'formattedAddress', 'location', 'types']
          }).then(function(response) {
            console.log('Modern JavaScript API: Place details fetched:', response);
            
            var placeData = response.place;
            
            // Store the Place ID in a hidden field or data attribute
            $(field).attr('data-place-id', placeData.id);
            
            // Update the original field with Place ID for backend processing
            $(field).val(placeData.id);
            
            console.log('Modern JavaScript API: Place ID set:', placeData.id);
            
            // Add visual confirmation
            var existingIndicator = $(field).next('.place-id-indicator');
            if (existingIndicator.length) {
              existingIndicator.remove();
            }
            $(field).after('<span class="place-id-indicator" style="color: green; font-size: 12px; margin-left: 5px;">✓ Place ID: ' + placeData.id.substring(0, 15) + '...</span>');
            
            // Show the friendly name to user but keep Place ID in field
            setTimeout(function() {
              $('.place-id-indicator').fadeOut(500, function() { $(this).remove(); });
            }, 3000);
            
            // Auto-populate other fields if enabled
            if (autoPopulate) {
              populateFormFieldsFromModernPlace(placeData);
            }
            
            // Trigger change event
            $(field).trigger('change');
          }).catch(function(error) {
            console.error('Modern JavaScript API: Error fetching place details:', error);
            
            // Fallback: Just use the Place ID from prediction
            $(field).val(prediction.place_id);
            console.log('Modern JavaScript API: Fallback - using prediction Place ID:', prediction.place_id);
            
            // Add visual confirmation
            var existingIndicator = $(field).next('.place-id-indicator');
            if (existingIndicator.length) {
              existingIndicator.remove();
            }
            $(field).after('<span class="place-id-indicator" style="color: orange; font-size: 12px; margin-left: 5px;">⚠ Place ID: ' + prediction.place_id.substring(0, 15) + '...</span>');
            
            setTimeout(function() {
              $('.place-id-indicator').fadeOut(500, function() { $(this).remove(); });
            }, 3000);
            
            $(field).trigger('change');
          });
          
        } catch (error) {
          console.error('Modern JavaScript API: Error creating Place object:', error);
          
          // Fallback: Just use the Place ID from prediction
          $(field).val(prediction.place_id);
          console.log('Modern JavaScript API: Fallback - using prediction Place ID:', prediction.place_id);
          
          // Add visual confirmation
          var existingIndicator = $(field).next('.place-id-indicator');
          if (existingIndicator.length) {
            existingIndicator.remove();
          }
          $(field).after('<span class="place-id-indicator" style="color: red; font-size: 12px; margin-left: 5px;">✗ Place ID: ' + prediction.place_id.substring(0, 15) + '...</span>');
          
          setTimeout(function() {
            $('.place-id-indicator').fadeOut(500, function() { $(this).remove(); });
          }, 3000);
          
          $(field).trigger('change');
        }
        
        $(suggestionsList).hide();
      });
      
      suggestionsList.appendChild(li);
    });
  }
  


  /**
   * Initialize legacy Autocomplete (fallback)
   */
  function initLegacyAutocomplete(field, autoPopulate) {
    console.log('Initializing legacy autocomplete for field:', field);
    
    try {
      // Small delay to ensure DOM is ready
      setTimeout(function() {
        var autocomplete = new google.maps.places.Autocomplete(field, {
          fields: ['place_id', 'name', 'formatted_address', 'geometry', 'opening_hours', 'formatted_phone_number', 'website'],
          types: ['establishment']
        });
        
        setupAutocompleteListener(autocomplete, field, autoPopulate);
      }, 100);
    } catch (error) {
      console.error('Error creating legacy autocomplete:', error);
    }
  }
  
  function setupAutocompleteListener(autocomplete, field, autoPopulate) {
    autocomplete.addListener('place_changed', function() {
      console.log('Legacy API: Place changed event fired');
      var place = autocomplete.getPlace();
      
      console.log('Legacy API: Place object:', place);
      
      if (!place.place_id) {
        console.error('Legacy API: No place_id found in place object');
        return;
      }

      // Set the place ID in the field
      $(field).val(place.place_id);
      
      // Debug: Log what we're setting
      console.log('Legacy API: Setting Place ID', place.place_id, 'in field', field);

      // Auto-populate other fields if enabled
      if (autoPopulate) {
        populateFormFields(place);
      }

      // Trigger change event
      $(field).trigger('change');
    });
    
    console.log('Legacy API: Event listener attached to autocomplete');
  }

  /**
   * Populate form fields with place data
   */
  function populateFormFields(place) {
    var $form = $(document).find('form');
    
    // Populate title/name
    if (place.name && $form.find('[name="title[0][value]"]').length) {
      $form.find('[name="title[0][value]"]').val(place.name);
    }
    
    // Populate formatted address
    if (place.formatted_address && $form.find('[name*="field_formatted_address"]').length) {
      $form.find('[name*="field_formatted_address"]').val(place.formatted_address);
    }
    
    // Populate coordinates
    if (place.geometry && place.geometry.location) {
      var lat = place.geometry.location.lat();
      var lng = place.geometry.location.lng();
      
      if ($form.find('[name*="field_latitude"]').length) {
        $form.find('[name*="field_latitude"]').val(lat);
      }
      
      if ($form.find('[name*="field_longitude"]').length) {
        $form.find('[name*="field_longitude"]').val(lng);
      }
    }
    
    // Populate phone
    if (place.formatted_phone_number && $form.find('[name*="field_phone"]').length) {
      $form.find('[name*="field_phone"]').val(place.formatted_phone_number);
    }
    
    // Populate website URL
    if (place.website && $form.find('[name*="field_url"]').length) {
      $form.find('[name*="field_url[0][uri]"]').val(place.website);
    }
    
    // Show success message
    if (Drupal.announce) {
      Drupal.announce(Drupal.t('Place data populated successfully!'));
    }
  }

  /**
   * Populate form fields with place data from modern API
   */
  function populateFormFieldsFromModernPlace(place) {
    var $form = $(document).find('form');
    
    // Populate title/name (modern API uses displayName)
    if (place.displayName && $form.find('[name="title[0][value]"]').length) {
      $form.find('[name="title[0][value]"]').val(place.displayName);
    }
    
    // Populate formatted address
    if (place.formattedAddress && $form.find('[name*="field_formatted_address"]').length) {
      $form.find('[name*="field_formatted_address"]').val(place.formattedAddress);
    }
    
    // Populate coordinates (modern API uses location)
    if (place.location) {
      var lat, lng;
      
      // Modern API location can be a function or object
      if (typeof place.location.lat === 'function') {
        lat = place.location.lat();
        lng = place.location.lng();
      } else if (place.location.lat && place.location.lng) {
        lat = place.location.lat;
        lng = place.location.lng;
      } else if (place.location.latitude && place.location.longitude) {
        lat = place.location.latitude;
        lng = place.location.longitude;
      }
      
      console.log('Modern API: Coordinates - Lat:', lat, 'Lng:', lng);
      
      if (lat && $form.find('[name*="field_latitude"]').length) {
        $form.find('[name*="field_latitude"]').val(lat);
        console.log('Modern API: Set latitude:', lat);
      }
      
      if (lng && $form.find('[name*="field_longitude"]').length) {
        $form.find('[name*="field_longitude"]').val(lng);
        console.log('Modern API: Set longitude:', lng);
      }
    }
    
    // For other details, we'll need to fetch them via Place Details API
    // as the modern autocomplete provides limited data
    if (place.id) {
      fetchAdditionalPlaceDetails(place.id, $form);
    }
    
    // Show success message
    if (Drupal.announce) {
      Drupal.announce(Drupal.t('Place data populated successfully!'));
    }
  }

  /**
   * Fetch additional place details for modern API
   */
  function fetchAdditionalPlaceDetails(placeId, $form) {
    // This would typically be done via a Drupal AJAX call to our backend service
    // For now, we'll just populate what we have from the autocomplete
    console.log('Additional place details would be fetched for place ID: ' + placeId);
  }

  // Debug AJAX button clicks
  $(document).on('click', '.google-places-get-image', function(event) {
    console.log('Image button clicked!', event);
    console.log('Button element:', this);
  });

})(jQuery, Drupal, drupalSettings, once);
