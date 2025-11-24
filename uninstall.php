<?php
/**
 * Uninstall script for Simple Page Builder
 * 
 * This file is called when the plugin is uninstalled
 * It cleans up all data created by the plugin
 *
 * @package SimplePageBuilder
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include database class
require_once plugin_dir_path(__FILE__) . 'includes/class-spb-database.php';

// Remove all plugin data
spb_uninstall_plugin();

/**
 * Complete plugin uninstall
 */
function spb_uninstall_plugin() {
    // Drop database tables
    SPB_Database::drop_tables();
    
    // Remove all plugin options
    $options = array(
        'spb_webhook_url',
        'spb_webhook_secret',
        'spb_rate_limit_per_hour',
        'spb_api_enabled',
        'spb_default_expiration_days',
        'spb_log_retention_days',
        'spb_db_version'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Clear scheduled events
    wp_clear_scheduled_hook('spb_send_webhook');
    wp_clear_scheduled_hook('spb_cleanup_logs');
    
    // Remove meta data from pages created by API
    global $wpdb;
    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => '_spb_created_via_api'
        )
    );
    
    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => '_spb_api_key_id'
        )
    );
    
    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => '_spb_request_id'
        )
    );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}