<?php
/**
 * REST API endpoint class
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_REST_API class
 */
class SPB_REST_API {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * API namespace
     */
    private $namespace = 'pagebuilder/v1';
    
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Create pages endpoint
        register_rest_route($this->namespace, '/create-pages', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_pages'),
            'permission_callback' => array($this, 'authenticate_request'),
            'args' => array(
                'pages' => array(
                    'required' => true,
                    'type' => 'array',
                    'description' => 'Array of pages to create',
                    'validate_callback' => array($this, 'validate_pages_data')
                ),
                'webhook_url' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Override webhook URL for this request',
                    'validate_callback' => function($param) {
                        return empty($param) || filter_var($param, FILTER_VALIDATE_URL);
                    }
                )
            )
        ));
        
        // Health check endpoint
        register_rest_route($this->namespace, '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Authenticate API request
     */
    public function authenticate_request($request) {
        // Check if API is enabled
        if (!get_option('spb_api_enabled', true)) {
            return new WP_Error('api_disabled', __('API access is currently disabled.', 'simple-page-builder'), array('status' => 503));
        }
        
        // Get API key from header
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required.', 'simple-page-builder'), array('status' => 401));
        }
        
        // Validate API key
        $api_keys_manager = SPB_API_Keys::get_instance();
        $key_data = $api_keys_manager->validate_api_key($api_key);
        
        if (!$key_data) {
            return new WP_Error('invalid_api_key', __('Invalid API key.', 'simple-page-builder'), array('status' => 401));
        }
        
        // Check rate limiting
        $rate_limiter = SPB_Rate_Limiter::get_instance();
        if (!$rate_limiter->check_rate_limit($key_data->id)) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded.', 'simple-page-builder'), array('status' => 429));
        }
        
        // Store key data in request for later use
        $request->set_param('_api_key_data', $key_data);
        
        return true;
    }
    
    /**
     * Validate pages data
     */
    public function validate_pages_data($pages) {
        if (!is_array($pages) || empty($pages)) {
            return false;
        }
        
        foreach ($pages as $page) {
            if (!is_array($page)) {
                return false;
            }
            
            // Required fields
            if (empty($page['title'])) {
                return false;
            }
            
            // Optional fields validation
            if (isset($page['content']) && !is_string($page['content'])) {
                return false;
            }
            
            if (isset($page['slug']) && (!is_string($page['slug']) || !preg_match('/^[a-z0-9\-]+$/', $page['slug']))) {
                return false;
            }
            
            if (isset($page['status']) && !in_array($page['status'], array('publish', 'draft', 'private'))) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create pages endpoint
     */
    public function create_pages($request) {
        $start_time = microtime(true);
        $request_id = 'req_' . wp_generate_uuid4();
        $api_key_data = $request->get_param('_api_key_data');
        $pages_data = $request->get_param('pages');
        $webhook_url = $request->get_param('webhook_url');
        
        // Log request start
        $logger = SPB_Logger::get_instance();
        $logger->log_api_request($api_key_data->id, $request_id, 'create-pages', 'POST', 'started', array(
            'pages_count' => count($pages_data),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('User-Agent')
        ));
        
        $created_pages = array();
        $errors = array();
        
        foreach ($pages_data as $index => $page_data) {
            $result = $this->create_single_page($page_data, $api_key_data, $request_id);
            
            if (is_wp_error($result)) {
                $errors[] = array(
                    'index' => $index,
                    'title' => $page_data['title'] ?? '',
                    'error' => $result->get_error_message()
                );
            } else {
                $created_pages[] = $result;
            }
        }
        
        $response_time = microtime(true) - $start_time;
        
        // Prepare response
        $response_data = array(
            'success' => true,
            'request_id' => $request_id,
            'total_requested' => count($pages_data),
            'total_created' => count($created_pages),
            'total_errors' => count($errors),
            'created_pages' => $created_pages,
            'errors' => $errors,
            'processing_time' => round($response_time, 3)
        );
        
        // Log successful request
        $logger->log_api_request($api_key_data->id, $request_id, 'create-pages', 'POST', 'success', array(
            'pages_created' => count($created_pages),
            'response_time' => $response_time,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('User-Agent')
        ));
        
        // Send webhook notification
        if (!empty($created_pages)) {
            $webhook = SPB_Webhook::get_instance();
            $webhook->send_notification($request_id, $api_key_data, $created_pages, $webhook_url);
        }
        
        return new WP_REST_Response($response_data, 200);
    }
    
    /**
     * Create single page
     */
    private function create_single_page($page_data, $api_key_data, $request_id) {
        // Prepare page arguments
        $page_args = array(
            'post_title' => sanitize_text_field($page_data['title']),
            'post_content' => wp_kses_post($page_data['content'] ?? ''),
            'post_status' => sanitize_text_field($page_data['status'] ?? 'publish'),
            'post_type' => 'page',
            'post_author' => 1, // Default to admin user
            'meta_input' => array(
                '_spb_created_via_api' => true,
                '_spb_api_key_id' => $api_key_data->id,
                '_spb_request_id' => $request_id
            )
        );
        
        // Set custom slug if provided
        if (!empty($page_data['slug'])) {
            $page_args['post_name'] = sanitize_title($page_data['slug']);
        }
        
        // Set parent page if provided
        if (!empty($page_data['parent_id']) && is_numeric($page_data['parent_id'])) {
            $parent_page = get_post($page_data['parent_id']);
            if ($parent_page && $parent_page->post_type === 'page') {
                $page_args['post_parent'] = $page_data['parent_id'];
            }
        }
        
        // Create the page
        $page_id = wp_insert_post($page_args, true);
        
        if (is_wp_error($page_id)) {
            return $page_id;
        }
        
        // Get page URL
        $page_url = get_permalink($page_id);
        
        // Store in created pages table
        global $wpdb;
        $table_name = SPB_Database::get_table_name('created_pages');
        $wpdb->insert(
            $table_name,
            array(
                'page_id' => $page_id,
                'api_key_id' => $api_key_data->id,
                'request_id' => $request_id,
                'page_title' => $page_data['title'],
                'page_url' => $page_url,
                'page_status' => $page_data['status'] ?? 'publish',
                'created_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return array(
            'id' => $page_id,
            'title' => $page_data['title'],
            'url' => $page_url,
            'status' => $page_data['status'] ?? 'publish'
        );
    }
    
    /**
     * Health check endpoint
     */
    public function health_check($request) {
        return new WP_REST_Response(array(
            'status' => 'healthy',
            'version' => SPB_VERSION,
            'timestamp' => current_time('c'),
            'api_enabled' => get_option('spb_api_enabled', true)
        ), 200);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}