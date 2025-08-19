(function ($, Drupal, drupalSettings) {
  'use strict';

  function locateInput(context) {
    const selectors = [
      '#edit-field-place-id-0-value',
      'input[name="field_place_id[0][value]"]',
      'input[id^="edit-field-place-id"]'
    ];

    for (const selector of selectors) {
      const $input = $(selector, context);
      if ($input.length > 0) {
        return $input.first();
      }
    }
    return $();
  }

  function setupAutocomplete($input) {
    if (!$input.length || $input.data('places-autocomplete-attached')) {
      return;
    }

    $input.data('places-autocomplete-attached', true);

    $input.autocomplete({
      source: function(request, response) {
        $.get('/google-places/autocomplete', { q: request.term })
          .done(function(data) {
            response(data);
          })
          .fail(function() {
            response([]);
          });
      },
      minLength: 2,
      select: function(event, ui) {
        if (ui.item && ui.item.value) {
          $(this).val(ui.item.value);
          return false;
        }
      }
    });
  }

  function attachImportButtons($input) {
    if (!$input.length || $input.data('places-import-attached')) {
      return;
    }

    $input.data('places-import-attached', true);

    const $container = $input.closest('.form-item');
    
    // Load Information button
    const $loadButton = $('<button type="button" class="button button--small">Load Information</button>');
    $loadButton.css('margin-left', '10px');
    
    // Save & Import Photo button
    const $photoButton = $('<button type="button" class="button button--primary">Save & Import Photo</button>');
    $photoButton.css('margin-left', '5px');

    const $info = $('<div class="places-info"></div>');
    $info.css({
      'margin-top': '5px',
      'font-size': '12px',
      'color': '#e74c3c'
    });

    $container.append($loadButton).append($photoButton).append($info);

    // Load Information functionality
    $loadButton.on('click', function() {
      const placeId = $input.val().trim();
      if (!placeId) {
        $info.html('Please enter a Place ID first.');
        return;
      }

      $loadButton.prop('disabled', true).text('Loading...');
      $info.html('Fetching place details...');

      $.get('/google-places/detail', { place_id: placeId })
        .done(function(response) {
          if (response && Object.keys(response).length > 0) {
            fillFields(response, $info);
            $info.prepend('<div style="color: #27ae60; font-weight: bold;">Details loaded successfully!</div>');
          } else {
            $info.html('No details found for this Place ID.');
          }
        })
        .fail(function() {
          $info.html('Error loading place details. Please try again.');
        })
        .always(function() {
          $loadButton.prop('disabled', false).text('Load Information');
        });
    });

    // Save & Import Photo functionality
    $photoButton.on('click', function() {
      const placeId = $input.val().trim();
      if (!placeId) {
        $info.html('Please enter a Place ID first.');
        return;
      }

      $photoButton.prop('disabled', true).text('Saving & Importing...');
      $info.html('Saving node and importing photo from Google Places...');

      // Collect all form data
      const formData = collectFormData();
      const nodeId = getNodeId();

      // Get CSRF token
      $.get('/session/token')
        .done(function(token) {
          // Send POST request with form data
          $.ajax({
            url: '/google-places/import-photo-and-save',
            method: 'POST',
            headers: {
              'X-CSRF-Token': token,
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            data: {
              place_id: placeId,
              node_id: nodeId,
              form_data: formData
            }
          })
          .done(function(response) {
            if (response.status === 'success') {
              $info.html('<div style="color: #27ae60; font-weight: bold;">' + 
                response.message + '<br>' +
                'Node ID: ' + response.node_id + '<br>' +
                'Media ID: ' + response.media_id + '<br>' +
                '<a href="' + response.node_url + '" target="_blank">View Node</a>' +
                '</div>');
              
              // Update the form to show we're now editing the saved node
              updateFormWithNodeId(response.node_id);
            } else {
              $info.html('Error: ' + (response.error || 'Unknown error occurred'));
            }
          })
          .fail(function(xhr) {
            let errorMsg = 'Error saving node and importing photo.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
              errorMsg += ' ' + xhr.responseJSON.error;
            }
            $info.html(errorMsg);
          })
          .always(function() {
            $photoButton.prop('disabled', false).text('Save & Import Photo');
          });
        });
    });
  }

  function collectFormData() {
    const formData = {};
    
    // Collect all form inputs, selects, and textareas
    $('#node-place-form input, #node-place-form select, #node-place-form textarea').each(function() {
      const $field = $(this);
      const name = $field.attr('name');
      const value = $field.val();
      
      if (name && value !== undefined && value !== '') {
        formData[name] = value;
      }
    });
    
    return formData;
  }

  function getNodeId() {
    // Try to get node ID from the URL or form
    const urlParts = window.location.pathname.split('/');
    if (urlParts.includes('edit') && urlParts.length > 3) {
      const potentialId = urlParts[urlParts.indexOf('edit') - 1];
      if (potentialId && !isNaN(potentialId)) {
        return potentialId;
      }
    }
    
    // Check for hidden field with node ID
    const $nidField = $('input[name="nid"]');
    if ($nidField.length && $nidField.val()) {
      return $nidField.val();
    }
    
    return '';
  }

  function updateFormWithNodeId(nodeId) {
    // Update the page URL to edit mode if we were creating a new node
    if (window.location.pathname.includes('/node/add/')) {
      const newUrl = '/node/' + nodeId + '/edit';
      window.history.replaceState({}, '', newUrl);
    }
  }

  function fillFields(fields, $info) {
    Object.values(fields).forEach(function(field) {
      if (!field.name || !field.value) {
        return;
      }

      const elements = document.getElementsByName(field.name);
      if (elements.length === 1) {
        const $element = $(elements[0]);
        $element.val(field.value).trigger('change').trigger('keyup');
      }
    });
  }

  Drupal.behaviors.googlePlaces = {
    attach: function(context) {
      const $input = locateInput(context);
      if ($input.length > 0) {
        setupAutocomplete($input);
        attachImportButtons($input);
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
