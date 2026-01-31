<?php
/**
 * Uninstall handler for Research Access Gate
 * 
 * This file is called when the plugin is deleted via WordPress admin.
 * It cleans up all plugin data from the database.
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.1
 */

// Security: Verify this is a legitimate uninstall request
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only proceed if user has permission
if ( ! current_user_can( 'activate_plugins' ) ) {
    exit;
}

// Verify the uninstall is for this plugin
if ( plugin_basename( __FILE__ ) !== WP_UNINSTALL_PLUGIN ) {
    exit;
}

/**
 * Clean up plugin options
 */
delete_option( 'rag_settings' );
delete_option( 'rag_version' );
delete_option( 'rag_installed_at' );
delete_option( 'rag_license' );

/**
 * Clean up transients
 * Using direct SQL for efficiency with prefixed transients
 */
global $wpdb;

// Delete settings cache transient
delete_transient( 'rag_settings_cache' );
delete_transient( 'rag_license_status' );

// Delete any other transients with our prefix
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_rag_%' 
        OR option_name LIKE '_transient_timeout_rag_%'"
);

/**
 * Clean up user meta (optional - uncomment if you want to remove user data)
 * Note: This removes Terms acceptance records which may be needed for compliance
 */
// $wpdb->query(
//     "DELETE FROM {$wpdb->usermeta} 
//      WHERE meta_key LIKE 'rag_%'"
// );

/**
 * Remove MU-Plugin if installed
 */
$mu_file = WPMU_PLUGIN_DIR . '/research-access-gate-loader.php';
if ( file_exists( $mu_file ) ) {
    @unlink( $mu_file );
}

/**
 * Clear any cached data
 */
wp_cache_flush();
