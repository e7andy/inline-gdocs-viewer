<?php
/**
 * Inline Google Spreadsheet Viewer uninstaller
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

delete_option('gdoc_settings');

// Delete caches.
global $wpdb;
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_gdoc') . '%',
    $wpdb->esc_like('_transient_timeout_gdoc') . '%'
));

// Delete the record of which SQL shortcodes were authorized.
delete_post_meta_by_key('_gdoc_sql_authorized');

// Delete RBAC settings.
$delete_caps = array(
    'gdoc_query_sql_databases'
);
foreach ($delete_caps as $cap) {
    foreach (wp_roles()->role_objects as $role) {
        $role->remove_cap($cap);
    }
}
