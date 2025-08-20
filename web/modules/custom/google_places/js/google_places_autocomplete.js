/**
 * Google Places Autocomplete functionality
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.googlePlacesAutocomplete = {
    attach: function (context, settings) {
      once('google-places-autocomplete', '.google-places-autocomplete', context).forEach(function(element) {
        var $field = $(element);
        var autoPopulate = $field.attr('data-auto-populate') === 'true';
        
        // Load Google Places API if not already loaded
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
          var apiKey = drupalSettings.googlePlaces ? drupalSettings.googlePlaces.apiKey : '';
          if (apiKey) {
            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&libraries=places,marker&loading=async&callback=initGooglePlacesAutocomplete';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
            
            // Store field reference for callback
            window.googlePlacesFields = window.googlePlacesFields || [];
            window.googlePlacesFields.push({
              field: element,
              autoPopulate: autoPopulate
            });
          } else {
            console.error('Google Places API key not configured');
          }
        } else {
          // API already loaded, initialize directly
          initAutocomplete(element, autoPopulate);
        }
      });
    }
  };

  // Global callback for Google Maps API
  window.initGooglePlacesAutocomplete = function() {
    if (window.googlePlacesFields) {
      window.googlePlacesFields.forEach(function(fieldData) {
        initAutocomplete(fieldData.field, fieldData.autoPopulate);
      });
      window.googlePlacesFields = [];
    }
  };

  /**
   * Initialize autocomplete for a field
   */
  function initAutocomplete(field, autoPopulate) {
    // Check if new PlaceAutocompleteElement is available
    if (typeof google.maps.places.PlaceAutocompleteElement !== 'undefined') {
      // Use the new PlaceAutocompleteElement API
      initModernAutocomplete(field, autoPopulate);
    } else if (typeof google.maps.places.Autocomplete !== 'undefined') {
      // Fallback to legacy Autocomplete API with deprecation warning
      console.warn('Using deprecated google.maps.places.Autocomplete. Consider migrating to PlaceAutocompleteElement.');
      initLegacyAutocomplete(field, autoPopulate);
    } else {
      console.error('No Google Places Autocomplete API available');
    }
  }

  /**
   * Initialize modern PlaceAutocompleteElement
   */
  function initModernAutocomplete(field, autoPopulate) {
    // Create a custom element for the new API
    var autocompleteElement = document.createElement('gmp-place-autocomplete');
    autocompleteElement.setAttribute('type', 'establishment');
    
    // Hide the original field and insert the new element
    $(field).hide();
    $(field).after(autocompleteElement);
    
    // Listen for place selection
    autocompleteElement.addEventListener('gmp-placeselect', function(event) {
      var place = event.place;
      
      if (!place.id) {
        return;
      }

      // Set the place ID in the original field
      $(field).val(place.id).show().focus().blur();

      // Auto-populate other fields if enabled
      if (autoPopulate) {
        populateFormFieldsFromModernPlace(place);
      }

      // Trigger change event
      $(field).trigger('change');
    });
  }

  /**
   * Initialize legacy Autocomplete (fallback)
   */
  function initLegacyAutocomplete(field, autoPopulate) {
    var autocomplete = new google.maps.places.Autocomplete(field, {
      fields: ['place_id', 'name', 'formatted_address', 'geometry', 'opening_hours', 'formatted_phone_number', 'website'],
      types: ['establishment']
    });

    autocomplete.addListener('place_changed', function() {
      var place = autocomplete.getPlace();
      
      if (!place.place_id) {
        return;
      }

      // Set the place ID in the field
      $(field).val(place.place_id);

      // Auto-populate other fields if enabled
      if (autoPopulate) {
        populateFormFields(place);
      }

      // Trigger change event
      $(field).trigger('change');
    });
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
      var lat = place.location.lat();
      var lng = place.location.lng();
      
      if ($form.find('[name*="field_latitude"]').length) {
        $form.find('[name*="field_latitude"]').val(lat);
      }
      
      if ($form.find('[name*="field_longitude"]').length) {
        $form.find('[name*="field_longitude"]').val(lng);
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

})(jQuery, Drupal, drupalSettings, once);
