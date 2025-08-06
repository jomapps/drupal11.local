# Media expire

This module enables you to unpublish your media entities.
This can be done automatically by setting an expiry field.

Instructions:
 - "Activate media expire" on admin/structure/media/manage/{media}
 - Specify an expiry field, and optionally,
 - You are able to provide a fallback entity for unpublished entities

Drupal checks on every cron-run if there are expired media elements. 
Additionally, you can perform an on-demand check.
For manual check use "drush media-expire-check".
