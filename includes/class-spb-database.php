<?php
/**
 * Database management class
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_Database class
 */
class SPB_Database {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress init
        add_action('init', array($this, 'check_database_version'));
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // API Keys table
        $api_keys_table = $wpdb->prefix . 'spb_api_keys';
        $api_keys_sql = "CREATE TABLE $api_keys_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            key_name varchar(100) NOT NULL,
            key_hash varchar(255) NOT NULL,
            key_preview varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'active',
            permissions text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            expiration_date datetime NULL,
            last_used datetime NULL,
            request_count int(11) DEFAULT 0,
            created_by int(11) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY key_hash (key_hash),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // API Logs table
        $api_logs_table = $wpdb->prefix . 'spb_api_logs';
        $api_logs_sql = "CREATE TABLE $api_logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            api_key_id mediumint(9),
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            status varchar(20) NOT NULL,
            request_data longtext,
            response_data longtext,
            pages_created int(11) DEFAULT 0,
            response_time float DEFAULT 0,
            ip_address varchar(45),
            user_agent text,
            request_id varchar(50),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_key_id (api_key_id),
            KEY status (status),
            KEY created_date (created_date),
            KEY request_id (request_id)
        ) $charset_collate;";
        
        // Created Pages table
        $created_pages_table = $wpdb->prefix . 'spb_created_pages';
        $created_pages_sql = "CREATE TABLE $created_pages_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            api_key_id mediumint(9),
            request_id varchar(50),
            page_title varchar(255) NOT NULL,
            page_url varchar(500),
            page_status varchar(20),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY api_key_id (api_key_id),
            KEY request_id (request_id),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        // Webhook Logs table
        $webhook_logs_table = $wpdb->prefix . 'spb_webhook_logs';
        $webhook_logs_sql = "CREATE TABLE $webhook_logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            request_id varchar(50),
            webhook_url varchar(500) NOT NULL,
            payload longtext NOT NULL,
            response_code int(11),
            response_body text,
            delivery_attempts int(11) DEFAULT 1,
            status varchar(20) NOT NULL,
            error_message text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            delivered_date datetime NULL,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY status (status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($api_keys_sql);
        dbDelta($api_logs_sql);
        dbDelta($created_pages_sql);
        dbDelta($webhook_logs_sql);
        
        // Update database version
        add_option('spb_db_version', SPB_VERSION);
    }
    
    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        $installed_version = get_option('spb_db_version');
        
        if ($installed_version !== SPB_VERSION) {
            self::create_tables();
        }
    }
    
    /**
     * Drop database tables (used on uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'spb_api_keys',
            $wpdb->prefix . 'spb_api_logs',
            $wpdb->prefix . 'spb_created_pages',
            $wpdb->prefix . 'spb_webhook_logs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('spb_db_version');
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'spb_' . $table;
    }
}