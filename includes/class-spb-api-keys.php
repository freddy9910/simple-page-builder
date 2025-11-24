<?php
/**
 * API Keys management class
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_API_Keys class
 */
class SPB_API_Keys {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_spb_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_spb_revoke_api_key', array($this, 'ajax_revoke_api_key'));
        add_action('wp_ajax_spb_get_api_keys', array($this, 'ajax_get_api_keys'));
    }
    
    /**
     * Generate a new API key
     */
    public function generate_api_key($key_name, $expiration_days = null) {
        global $wpdb;
        
        if (empty($key_name)) {
            return new WP_Error('invalid_name', __('Key name is required.', 'simple-page-builder'));
        }
        
        // Generate random API key
        $api_key = $this->generate_random_key();
        $key_hash = wp_hash_password($api_key);
        $key_preview = substr($api_key, 0, 8) . '********';
        
        // Calculate expiration date
        $expiration_date = null;
        if ($expiration_days && $expiration_days > 0) {
            $expiration_date = date('Y-m-d H:i:s', strtotime('+' . $expiration_days . ' days'));
        }
        
        // Insert into database
        $table_name = SPB_Database::get_table_name('api_keys');
        
        // Prepare data and format specifiers
        $data = array(
            'key_name' => sanitize_text_field($key_name),
            'key_hash' => $key_hash,
            'key_preview' => $key_preview,
            'status' => 'active',
            'permissions' => json_encode(array('create_pages')),
            'created_by' => get_current_user_id(),
            'created_date' => current_time('mysql')
        );
        
        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%s');
        
        // Add expiration_date if set
        if ($expiration_date) {
            $data['expiration_date'] = $expiration_date;
            $formats[] = '%s';
        }
        
        $result = $wpdb->insert($table_name, $data, $formats);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create API key.', 'simple-page-builder'));
        }
        
        $key_id = $wpdb->insert_id;
        
        return array(
            'id' => $key_id,
            'api_key' => $api_key,
            'key_name' => $key_name,
            'key_preview' => $key_preview,
            'expiration_date' => $expiration_date,
            'created_date' => current_time('mysql')
        );
    }
    
    /**
     * Generate random API key
     */
    private function generate_random_key($length = 64) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $key = '';
        
        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $key;
    }
    
    /**
     * Validate API key
     */
    public function validate_api_key($api_key) {
        global $wpdb;
        
        if (empty($api_key)) {
            return false;
        }
        
        $table_name = SPB_Database::get_table_name('api_keys');
        $api_keys = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = 'active' AND (expiration_date IS NULL OR expiration_date > %s)",
                current_time('mysql')
            )
        );
        
        foreach ($api_keys as $key_data) {
            if (wp_check_password($api_key, $key_data->key_hash)) {
                // Update last used
                $this->update_last_used($key_data->id);
                return $key_data;
            }
        }
        
        return false;
    }
    
    /**
     * Update last used timestamp
     */
    private function update_last_used($key_id) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_keys');
        
        // Update last used timestamp
        $wpdb->update(
            $table_name,
            array('last_used' => current_time('mysql')),
            array('id' => $key_id),
            array('%s'),
            array('%d')
        );
        
        // Update request count manually
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET request_count = request_count + 1 WHERE id = %d",
            $key_id
        ));
    }
    
    /**
     * Revoke API key
     */
    public function revoke_api_key($key_id) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_keys');
        $result = $wpdb->update(
            $table_name,
            array('status' => 'revoked'),
            array('id' => $key_id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all API keys
     */
    public function get_api_keys($limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_keys');
        $users_table = $wpdb->users;
        
        $sql = $wpdb->prepare(
            "SELECT ak.*, u.display_name as created_by_name 
             FROM $table_name ak 
             LEFT JOIN $users_table u ON ak.created_by = u.ID 
             ORDER BY ak.created_date DESC 
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get API key by ID
     */
    public function get_api_key($key_id) {
        global $wpdb;
        
        $table_name = SPB_Database::get_table_name('api_keys');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $key_id
        ));
    }
    
    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        $key_name = sanitize_text_field($_POST['key_name'] ?? '');
        $expiration_days = intval($_POST['expiration_days'] ?? 0);
        
        if ($expiration_days <= 0) {
            $expiration_days = null;
        }
        
        $result = $this->generate_api_key($key_name, $expiration_days);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        $key_id = intval($_POST['key_id'] ?? 0);
        
        if (!$key_id) {
            wp_send_json_error(__('Invalid key ID.', 'simple-page-builder'));
        }
        
        $result = $this->revoke_api_key($key_id);
        
        if (!$result) {
            wp_send_json_error(__('Failed to revoke API key.', 'simple-page-builder'));
        }
        
        wp_send_json_success(__('API key revoked successfully.', 'simple-page-builder'));
    }
    
    /**
     * AJAX: Get API keys
     */
    public function ajax_get_api_keys() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $api_keys = $this->get_api_keys($limit, $offset);
        
        wp_send_json_success($api_keys);
    }
}