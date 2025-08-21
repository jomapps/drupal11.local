/**
 * Google Places module JavaScript
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.googlePlaces = {
    attach: function (context, settings) {
      once('google-places', '.google-places-get-image', context).forEach(function(element) {
        $(element).on('click', function() {
          // Show loading state
          $(this).prop('disabled', true).val(Drupal.t('Loading...'));
          
          // Clear previous results
          $('#google-places-result').html('<div class="messages messages--status">' + 
            Drupal.t('Fetching image from Google Places...') + '</div>');
        });
      });
    }
  };

})(jQuery, Drupal, once);
