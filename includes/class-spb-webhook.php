<?php
/**
 * Webhook notification class
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_Webhook class
 */
class SPB_Webhook {
    
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
        // Initialize hooks
        add_action('spb_send_webhook', array($this, 'process_webhook_queue'), 10, 1);
    }
    
    /**
     * Send webhook notification
     */
    public function send_notification($request_id, $api_key_data, $created_pages, $webhook_url = null) {
        // Get webhook URL
        $url = $webhook_url ?: get_option('spb_webhook_url');
        
        if (empty($url)) {
            return false;
        }
        
        // Prepare payload
        $payload = array(
            'event' => 'pages_created',
            'timestamp' => current_time('c'),
            'request_id' => $request_id,
            'api_key_name' => $api_key_data->key_name,
            'total_pages' => count($created_pages),
            'pages' => $created_pages
        );
        
        // Schedule webhook delivery
        wp_schedule_single_event(time(), 'spb_send_webhook', array(
            array(
                'url' => $url,
                'payload' => $payload,
                'request_id' => $request_id
            )
        ));
        
        return true;
    }
    
    /**
     * Process webhook queue
     */
    public function process_webhook_queue($webhook_data) {
        $url = $webhook_data['url'];
        $payload = $webhook_data['payload'];
        $request_id = $webhook_data['request_id'];
        
        $this->deliver_webhook($url, $payload, $request_id);
    }
    
    /**
     * Deliver webhook with retry logic
     */
    private function deliver_webhook($url, $payload, $request_id, $attempt = 1, $max_attempts = 3) {
        $webhook_secret = get_option('spb_webhook_secret');
        $json_payload = wp_json_encode($payload);
        
        // Generate signature
        $signature = hash_hmac('sha256', $json_payload, $webhook_secret);
        
        // Prepare headers
        $headers = array(
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => 'sha256=' . $signature,
            'User-Agent' => 'SimplePageBuilder/' . SPB_VERSION
        );
        
        // Send request
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $json_payload,
            'timeout' => 10,
            'blocking' => true,
            'data_format' => 'body'
        ));
        
        $this->log_webhook_delivery($request_id, $url, $json_payload, $response, $attempt);
        
        // Check if delivery was successful
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code >= 200 && $response_code < 300) {
                // Success
                return true;
            }
            $error_message = 'HTTP ' . $response_code . ': ' . wp_remote_retrieve_response_message($response);
        }
        
        // Retry logic with exponential backoff
        if ($attempt < $max_attempts) {
            $delay = pow(2, $attempt) * 60; // 2, 4, 8 minutes
            wp_schedule_single_event(time() + $delay, 'spb_send_webhook', array(
                array(
                    'url' => $url,
                    'payload' => $payload,
                    'request_id' => $request_id,
                    'attempt' => $attempt + 1
                )
            ));
        }
        
        return false;
    }
    
    /**
     * Log webhook delivery
     */
    private function log_webhook_delivery($request_id, $url, $payload, $response, $attempt) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('webhook_logs');
        
        if (is_wp_error($response)) {
            $status = 'failed';
            $response_code = null;
            $response_body = null;
            $error_message = $response->get_error_message();
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 200 && $response_code < 300) {
                $status = 'delivered';
                $error_message = null;
            } else {
                $status = 'failed';
                $error_message = 'HTTP ' . $response_code . ': ' . wp_remote_retrieve_response_message($response);
            }
        }
        
        // Insert or update log
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE request_id = %s",
            $request_id
        ));
        
        $log_data = array(
            'request_id' => $request_id,
            'webhook_url' => $url,
            'payload' => $payload,
            'response_code' => $response_code,
            'response_body' => substr($response_body, 0, 1000), // Limit response body
            'delivery_attempts' => $attempt,
            'status' => $status,
            'error_message' => $error_message
        );
        
        if ($existing_log) {
            // Update existing log
            $log_data['delivered_date'] = ($status === 'delivered') ? current_time('mysql') : null;
            
            $wpdb->update(
                $table_name,
                $log_data,
                array('id' => $existing_log->id),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new log
            $log_data['created_date'] = current_time('mysql');
            $log_data['delivered_date'] = ($status === 'delivered') ? current_time('mysql') : null;
            
            $wpdb->insert(
                $table_name,
                $log_data,
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Get webhook logs
     */
    public function get_webhook_logs($limit = 50, $offset = 0, $status = null) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('webhook_logs');
        
        $where_clause = '';
        $where_params = array();
        
        if ($status) {
            $where_clause = 'WHERE status = %s';
            $where_params[] = $status;
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name $where_clause ORDER BY created_date DESC LIMIT %d OFFSET %d",
            array_merge($where_params, array($limit, $offset))
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Test webhook URL
     */
    public function test_webhook($url, $secret = null) {
        if (!$secret) {
            $secret = get_option('spb_webhook_secret');
        }
        
        $test_payload = array(
            'event' => 'test',
            'timestamp' => current_time('c'),
            'message' => 'This is a test webhook from Simple Page Builder'
        );
        
        $json_payload = wp_json_encode($test_payload);
        $signature = hash_hmac('sha256', $json_payload, $secret);
        
        $headers = array(
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => 'sha256=' . $signature,
            'User-Agent' => 'SimplePageBuilder/' . SPB_VERSION
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $json_payload,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return array(
            'success' => ($response_code >= 200 && $response_code < 300),
            'response_code' => $response_code,
            'response_body' => wp_remote_retrieve_body($response)
        );
    }
}