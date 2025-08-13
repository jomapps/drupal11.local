<?php

/**
 * @file
 * Post updates for the VG Wort module.
 *
 * Note: never use 'vgwort_post_update_add_node_to_entity_types' as a post
 * update name as it was used temporarily during the 2.0.0 beta cycle.
 */

/**
 * Implements hook_removed_post_updates().
 */
function vgwort_removed_post_updates(): array {
  return [
    'vgwort_post_update_set_test_mode' => '3.0.0',
    'vgwort_post_update_create_queue' => '3.0.0',
    'vgwort_post_update_add_legal_rights_to_config' => '3.0.0',
    'vgwort_post_update_daemon_queue' => '3.0.0',
    'vgwort_post_update_add_info_tab' => '3.0.0',
  ];
}
