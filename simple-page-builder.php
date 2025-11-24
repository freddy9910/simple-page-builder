<?php
/**
 * Plugin Name: Simple Page Builder
 * Plugin URI: https://github.com/freddy9910/simple-page-builder
 * Description: A WordPress plugin that creates bulk pages via secure REST API endpoint accessible from external applications.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: simple-page-builder
 * Domain Path: /languages
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPB_VERSION', '1.0.0');
define('SPB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPB_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class SimplePageBuilder {
    
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
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load classes
        $this->load_dependencies();
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin interface
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-database.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-api-keys.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-rest-api.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-webhook.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-logger.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-rate-limiter.php';
        require_once SPB_PLUGIN_DIR . 'admin/class-spb-admin.php';
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        // Initialize database
        SPB_Database::get_instance();
        
        // Initialize API components
        SPB_API_Keys::get_instance();
        SPB_REST_API::get_instance();
        SPB_Webhook::get_instance();
        SPB_Logger::get_instance();
        SPB_Rate_Limiter::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            SPB_Admin::get_instance();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        SPB_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'webhook_url' => '',
            'webhook_secret' => wp_generate_password(32, false),
            'rate_limit_per_hour' => 100,
            'api_enabled' => true,
            'default_expiration_days' => 90,
            'log_retention_days' => 30
        );
        
        foreach ($defaults as $key => $value) {
            if (!get_option('spb_' . $key)) {
                // Special handling for webhook_secret to ensure wp_generate_password is available
                if ($key === 'webhook_secret' && !function_exists('wp_generate_password')) {
                    $value = md5(uniqid(rand(), true));
                }
                add_option('spb_' . $key, $value);
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Page Builder', 'simple-page-builder'),
            __('Page Builder', 'simple-page-builder'),
            'manage_options',
            'simple-page-builder',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        $admin = SPB_Admin::get_instance();
        $admin->render_admin_page();
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('tools_page_simple-page-builder' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'spb-admin-style',
            SPB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SPB_VERSION
        );
        
        wp_enqueue_script(
            'spb-admin-script',
            SPB_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SPB_VERSION,
            true
        );
        
        wp_localize_script('spb-admin-script', 'spb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_admin_nonce'),
            'strings' => array(
                'confirm_revoke' => __('Are you sure you want to revoke this API key?', 'simple-page-builder'),
                'copied' => __('Copied to clipboard!', 'simple-page-builder'),
                'copy_failed' => __('Failed to copy. Please select and copy manually.', 'simple-page-builder')
            )
        ));
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'simple-page-builder',
            false,
            dirname(SPB_PLUGIN_BASENAME) . '/languages'
        );
    }
}

// Initialize the plugin
function simple_page_builder() {
    return SimplePageBuilder::get_instance();
}

// Start the plugin
simple_page_builder();