<?php
/**
 * Logging class for API requests and activities
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_Logger class
 */
class SPB_Logger {
    
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
        // Schedule cleanup job
        add_action('init', array($this, 'schedule_cleanup'));
        add_action('spb_cleanup_logs', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Log API request
     */
    public function log_api_request($api_key_id, $request_id, $endpoint, $method, $status, $data = array()) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_logs');
        
        $log_data = array(
            'api_key_id' => $api_key_id,
            'request_id' => $request_id,
            'endpoint' => $endpoint,
            'method' => $method,
            'status' => $status,
            'request_data' => wp_json_encode($data),
            'pages_created' => isset($data['pages_created']) ? intval($data['pages_created']) : 0,
            'response_time' => isset($data['response_time']) ? floatval($data['response_time']) : 0,
            'ip_address' => isset($data['ip_address']) ? $data['ip_address'] : '',
            'user_agent' => isset($data['user_agent']) ? substr($data['user_agent'], 0, 500) : '',
            'created_date' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $table_name,
            $log_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get API logs
     */
    public function get_api_logs($filters = array(), $limit = 50, $offset = 0) {
        global $wpdb;
        
        $api_logs_table = SPB_Database::get_table_name('api_logs');
        $api_keys_table = SPB_Database::get_table_name('api_keys');
        
        $where_conditions = array();
        $where_params = array();
        
        // Filter by API key
        if (!empty($filters['api_key_id'])) {
            $where_conditions[] = 'al.api_key_id = %d';
            $where_params[] = intval($filters['api_key_id']);
        }
        
        // Filter by status
        if (!empty($filters['status'])) {
            $where_conditions[] = 'al.status = %s';
            $where_params[] = $filters['status'];
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'al.created_date >= %s';
            $where_params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'al.created_date <= %s';
            $where_params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = $wpdb->prepare(
            "SELECT al.*, ak.key_name, ak.key_preview 
             FROM $api_logs_table al 
             LEFT JOIN $api_keys_table ak ON al.api_key_id = ak.id 
             $where_clause 
             ORDER BY al.created_date DESC 
             LIMIT %d OFFSET %d",
            array_merge($where_params, array($limit, $offset))
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get API logs count
     */
    public function get_api_logs_count($filters = array()) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_logs');
        
        $where_conditions = array();
        $where_params = array();
        
        // Filter by API key
        if (!empty($filters['api_key_id'])) {
            $where_conditions[] = 'api_key_id = %d';
            $where_params[] = intval($filters['api_key_id']);
        }
        
        // Filter by status
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_params[] = $filters['status'];
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_date >= %s';
            $where_params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_date <= %s';
            $where_params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name $where_clause",
            $where_params
        );
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Get API statistics
     */
    public function get_api_statistics($period = '30 days') {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_logs');
        $date_from = date('Y-m-d H:i:s', strtotime('-' . $period));
        
        $stats = array();
        
        // Total requests
        $stats['total_requests'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_date >= %s",
            $date_from
        )));
        
        // Successful requests
        $stats['successful_requests'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'success' AND created_date >= %s",
            $date_from
        )));
        
        // Failed requests
        $stats['failed_requests'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'failed' AND created_date >= %s",
            $date_from
        )));
        
        // Total pages created
        $stats['total_pages_created'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pages_created) FROM $table_name WHERE status = 'success' AND created_date >= %s",
            $date_from
        )));
        
        // Average response time
        $stats['avg_response_time'] = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time) FROM $table_name WHERE status = 'success' AND created_date >= %s",
            $date_from
        )));
        
        // Requests by day
        $stats['requests_by_day'] = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_date) as date, COUNT(*) as count 
             FROM $table_name 
             WHERE created_date >= %s 
             GROUP BY DATE(created_date) 
             ORDER BY date ASC",
            $date_from
        ));
        
        return $stats;
    }
    
    /**
     * Schedule cleanup job
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('spb_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'spb_cleanup_logs');
        }
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = intval(get_option('spb_log_retention_days', 30));
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days'));
        
        // Clean up API logs
        $api_logs_table = SPB_Database::get_table_name('api_logs');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $api_logs_table WHERE created_date < %s",
            $cutoff_date
        ));
        
        // Clean up webhook logs
        $webhook_logs_table = SPB_Database::get_table_name('webhook_logs');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $webhook_logs_table WHERE created_date < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs_csv($filters = array()) {
        $logs = $this->get_api_logs($filters, 1000, 0);
        
        $csv_data = array();
        $csv_data[] = array(
            'Date',
            'API Key',
            'Endpoint',
            'Method',
            'Status',
            'Pages Created',
            'Response Time (s)',
            'IP Address',
            'Request ID'
        );
        
        foreach ($logs as $log) {
            $csv_data[] = array(
                $log->created_date,
                $log->key_name ?: 'Unknown',
                $log->endpoint,
                $log->method,
                $log->status,
                $log->pages_created,
                $log->response_time,
                $log->ip_address,
                $log->request_id
            );
        }
        
        return $csv_data;
    }
}