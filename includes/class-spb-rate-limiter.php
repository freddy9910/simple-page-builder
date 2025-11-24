<?php
/**
 * Rate limiting class
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_Rate_Limiter class
 */
class SPB_Rate_Limiter {
    
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
        // Initialize
    }
    
    /**
     * Check rate limit for API key
     */
    public function check_rate_limit($api_key_id) {
        $rate_limit = intval(get_option('spb_rate_limit_per_hour', 100));
        
        if ($rate_limit <= 0) {
            return true; // No rate limiting
        }
        
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_logs');
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $request_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE api_key_id = %d AND created_date >= %s",
            $api_key_id,
            $one_hour_ago
        )));
        
        return $request_count < $rate_limit;
    }
    
    /**
     * Get remaining requests for API key
     */
    public function get_remaining_requests($api_key_id) {
        $rate_limit = intval(get_option('spb_rate_limit_per_hour', 100));
        
        if ($rate_limit <= 0) {
            return -1; // Unlimited
        }
        
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_logs');
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $request_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE api_key_id = %d AND created_date >= %s",
            $api_key_id,
            $one_hour_ago
        )));
        
        return max(0, $rate_limit - $request_count);
    }
    
    /**
     * Get rate limit reset time
     */
    public function get_reset_time($api_key_id) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_logs');
        
        $oldest_request = $wpdb->get_var($wpdb->prepare(
            "SELECT created_date FROM $table_name 
             WHERE api_key_id = %d AND created_date >= %s 
             ORDER BY created_date ASC 
             LIMIT 1",
            $api_key_id,
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));
        
        if ($oldest_request) {
            return strtotime($oldest_request) + 3600; // Add 1 hour
        }
        
        return time();
    }
}