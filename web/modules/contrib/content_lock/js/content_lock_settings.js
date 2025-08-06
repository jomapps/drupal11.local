/**
 * @file
 * Defines Javascript behaviors for the content lock module.
 */

(function ($, Drupal, once) {
  /**
   * Behaviors for the content lock settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the content lock settings form behavior.
   */
  Drupal.behaviors.contentLockSettings = {
    attach(context, settings) {
      once(
        'content-lock-settings',
        '.content-lock-entity-settings[value="*"]',
        context,
      ).forEach(function (elem) {
        // Init
        Drupal.behaviors.contentLockSettings.toggleBundles.call(elem);
        // Change
        $(elem).change(Drupal.behaviors.contentLockSettings.toggleBundles);
      });
      once(
        'content-lock-settings',
        '.content-lock-entity-types input',
        context,
      ).forEach(function (elem) {
        $(elem).change(Drupal.behaviors.contentLockSettings.toggleEntityType);
      });
    },

    /**
     * Toggle the bundle rows if all option is changed.
     */
    toggleBundles() {
      const allBundlesSelected = this.checked;
      $(this)
        .closest('tbody')
        .find('.bundle-settings')
        .each(function () {
          // If the "All bundles" checkbox is checked then uncheck and disable
          // all other options.
          const $checkbox = $('[type="checkbox"]', this);
          if (allBundlesSelected) {
            $checkbox
              .prop('disabled', true)
              .prop('checked', false)
              .addClass('is-disabled');
            $(this).hide();
          } else {
            $checkbox.prop('disabled', false).removeClass('is-disabled');
            $(this).show();
          }
        });
    },

    /**
     * Remove all selected bundles or auto select all when changing an entity type.
     */
    toggleEntityType() {
      const entityTypeId = this.value;
      if (this.checked) {
        $(`.${entityTypeId} .content-lock-entity-settings[value="*"]`)
          .prop('checked', true)
          .trigger('change');
      } else {
        $(`.${entityTypeId} .content-lock-entity-settings`).prop(
          'checked',
          false,
        );
      }
    },
  };
})(jQuery, Drupal, once);
