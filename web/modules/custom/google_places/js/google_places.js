/**
 * Google Places module JavaScript
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.googlePlaces = {
    attach: function (context, settings) {
      $('.google-places-get-image', context).once('google-places').on('click', function() {
        // Show loading state
        $(this).prop('disabled', true).val(Drupal.t('Loading...'));
        
        // Clear previous results
        $('#google-places-result').html('<div class="messages messages--status">' + 
          Drupal.t('Fetching image from Google Places...') + '</div>');
      });
    }
  };

})(jQuery, Drupal);
