<?php
/**
 * Admin interface class
 *
 * @package SimplePageBuilder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SPB_Admin class
 */
class SPB_Admin {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_spb_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_spb_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_spb_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_spb_get_created_pages', array($this, 'ajax_get_created_pages'));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api-keys';
        ?>
        <div class="wrap">
            <h1><?php _e('Simple Page Builder', 'simple-page-builder'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=simple-page-builder&tab=api-keys" class="nav-tab <?php echo $current_tab === 'api-keys' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('API Keys', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=activity-log" class="nav-tab <?php echo $current_tab === 'activity-log' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Activity Log', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=created-pages" class="nav-tab <?php echo $current_tab === 'created-pages' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Created Pages', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=documentation" class="nav-tab <?php echo $current_tab === 'documentation' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Documentation', 'simple-page-builder'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'api-keys':
                        $this->render_api_keys_tab();
                        break;
                    case 'activity-log':
                        $this->render_activity_log_tab();
                        break;
                    case 'created-pages':
                        $this->render_created_pages_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'documentation':
                        $this->render_documentation_tab();
                        break;
                    default:
                        $this->render_api_keys_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render API Keys tab
     */
    private function render_api_keys_tab() {
        $api_keys_manager = SPB_API_Keys::get_instance();
        $api_keys = $api_keys_manager->get_api_keys();
        ?>
        <div class="spb-tab-content">
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('Generate New API Key', 'simple-page-builder'); ?></h3>
                </div>
                <div class="spb-card-body">
                    <form id="spb-generate-key-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="key_name"><?php _e('Key Name', 'simple-page-builder'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="key_name" name="key_name" class="regular-text" required 
                                           placeholder="<?php esc_attr_e('e.g., Production Server', 'simple-page-builder'); ?>">
                                    <p class="description"><?php _e('A friendly name to identify this API key.', 'simple-page-builder'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="expiration_days"><?php _e('Expiration', 'simple-page-builder'); ?></label>
                                </th>
                                <td>
                                    <select id="expiration_days" name="expiration_days">
                                        <option value="0"><?php _e('Never', 'simple-page-builder'); ?></option>
                                        <option value="30"><?php _e('30 days', 'simple-page-builder'); ?></option>
                                        <option value="60"><?php _e('60 days', 'simple-page-builder'); ?></option>
                                        <option value="90" selected><?php _e('90 days', 'simple-page-builder'); ?></option>
                                        <option value="365"><?php _e('1 year', 'simple-page-builder'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('When this API key should expire.', 'simple-page-builder'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Generate API Key', 'simple-page-builder'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- API Key Display Modal -->
            <div id="spb-api-key-modal" class="spb-modal" style="display:none;">
                <div class="spb-modal-content">
                    <div class="spb-modal-header">
                        <h3><?php _e('API Key Generated Successfully', 'simple-page-builder'); ?></h3>
                        <span class="spb-modal-close">&times;</span>
                    </div>
                    <div class="spb-modal-body">
                        <div class="notice notice-warning">
                            <p><strong><?php _e('Important:', 'simple-page-builder'); ?></strong> <?php _e('Save this API key securely. You will not be able to see it again.', 'simple-page-builder'); ?></p>
                        </div>
                        
                        <div class="spb-api-key-display">
                            <label><?php _e('Your new API key:', 'simple-page-builder'); ?></label>
                            <div class="spb-api-key-field">
                                <input type="text" id="generated-api-key" readonly>
                                <button type="button" class="button" id="copy-api-key"><?php _e('Copy', 'simple-page-builder'); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="spb-modal-footer">
                        <button type="button" class="button button-primary spb-modal-close"><?php _e('I have saved the key', 'simple-page-builder'); ?></button>
                    </div>
                </div>
            </div>
            
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('Existing API Keys', 'simple-page-builder'); ?></h3>
                </div>
                <div class="spb-card-body">
                    <div class="tablenav top">
                        <button type="button" class="button" id="refresh-api-keys"><?php _e('Refresh', 'simple-page-builder'); ?></button>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'simple-page-builder'); ?></th>
                                <th><?php _e('Key Preview', 'simple-page-builder'); ?></th>
                                <th><?php _e('Status', 'simple-page-builder'); ?></th>
                                <th><?php _e('Created', 'simple-page-builder'); ?></th>
                                <th><?php _e('Last Used', 'simple-page-builder'); ?></th>
                                <th><?php _e('Requests', 'simple-page-builder'); ?></th>
                                <th><?php _e('Actions', 'simple-page-builder'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="api-keys-table-body">
                            <?php foreach ($api_keys as $key): ?>
                            <tr data-key-id="<?php echo esc_attr($key->id); ?>">
                                <td><strong><?php echo esc_html($key->key_name); ?></strong></td>
                                <td><code><?php echo esc_html($key->key_preview); ?></code></td>
                                <td>
                                    <span class="spb-status spb-status-<?php echo esc_attr($key->status); ?>">
                                        <?php echo esc_html(ucfirst($key->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($key->created_date))); ?></td>
                                <td><?php echo $key->last_used ? esc_html(date('Y-m-d H:i', strtotime($key->last_used))) : __('Never', 'simple-page-builder'); ?></td>
                                <td><?php echo esc_html(number_format($key->request_count)); ?></td>
                                <td>
                                    <?php if ($key->status === 'active'): ?>
                                    <button type="button" class="button button-small revoke-api-key" data-key-id="<?php echo esc_attr($key->id); ?>">
                                        <?php _e('Revoke', 'simple-page-builder'); ?>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Activity Log tab
     */
    private function render_activity_log_tab() {
        $logger = SPB_Logger::get_instance();
        $logs = $logger->get_api_logs();
        $stats = $logger->get_api_statistics();
        ?>
        <div class="spb-tab-content">
            <div class="spb-stats-grid">
                <div class="spb-stat-card">
                    <h4><?php _e('Total Requests', 'simple-page-builder'); ?></h4>
                    <div class="spb-stat-number"><?php echo esc_html(number_format($stats['total_requests'])); ?></div>
                    <div class="spb-stat-period"><?php _e('Last 30 days', 'simple-page-builder'); ?></div>
                </div>
                
                <div class="spb-stat-card">
                    <h4><?php _e('Successful Requests', 'simple-page-builder'); ?></h4>
                    <div class="spb-stat-number"><?php echo esc_html(number_format($stats['successful_requests'])); ?></div>
                    <div class="spb-stat-period"><?php _e('Last 30 days', 'simple-page-builder'); ?></div>
                </div>
                
                <div class="spb-stat-card">
                    <h4><?php _e('Pages Created', 'simple-page-builder'); ?></h4>
                    <div class="spb-stat-number"><?php echo esc_html(number_format($stats['total_pages_created'])); ?></div>
                    <div class="spb-stat-period"><?php _e('Last 30 days', 'simple-page-builder'); ?></div>
                </div>
                
                <div class="spb-stat-card">
                    <h4><?php _e('Avg Response Time', 'simple-page-builder'); ?></h4>
                    <div class="spb-stat-number"><?php echo esc_html(number_format($stats['avg_response_time'], 2)); ?>s</div>
                    <div class="spb-stat-period"><?php _e('Last 30 days', 'simple-page-builder'); ?></div>
                </div>
            </div>
            
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('API Request Log', 'simple-page-builder'); ?></h3>
                    <div class="spb-card-actions">
                        <button type="button" class="button" id="export-logs"><?php _e('Export CSV', 'simple-page-builder'); ?></button>
                    </div>
                </div>
                <div class="spb-card-body">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'simple-page-builder'); ?></th>
                                <th><?php _e('API Key', 'simple-page-builder'); ?></th>
                                <th><?php _e('Endpoint', 'simple-page-builder'); ?></th>
                                <th><?php _e('Status', 'simple-page-builder'); ?></th>
                                <th><?php _e('Pages Created', 'simple-page-builder'); ?></th>
                                <th><?php _e('Response Time', 'simple-page-builder'); ?></th>
                                <th><?php _e('IP Address', 'simple-page-builder'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_date))); ?></td>
                                <td><?php echo esc_html($log->key_name ?: 'Unknown'); ?></td>
                                <td><code><?php echo esc_html($log->endpoint); ?></code></td>
                                <td>
                                    <span class="spb-status spb-status-<?php echo esc_attr($log->status); ?>">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->pages_created); ?></td>
                                <td><?php echo esc_html(number_format($log->response_time, 3)); ?>s</td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Created Pages tab
     */
    private function render_created_pages_tab() {
        ?>
        <div class="spb-tab-content">
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('Pages Created via API', 'simple-page-builder'); ?></h3>
                </div>
                <div class="spb-card-body">
                    <div id="created-pages-container">
                        <?php _e('Loading...', 'simple-page-builder'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            loadCreatedPages();
            
            function loadCreatedPages() {
                $.post(ajaxurl, {
                    action: 'spb_get_created_pages',
                    nonce: spb_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        renderCreatedPages(response.data);
                    } else {
                        $('#created-pages-container').html('<p>Error loading pages: ' + response.data + '</p>');
                    }
                });
            }
            
            function renderCreatedPages(pages) {
                if (pages.length === 0) {
                    $('#created-pages-container').html('<p><?php _e('No pages have been created via the API yet.', 'simple-page-builder'); ?></p>');
                    return;
                }
                
                let html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                html += '<th><?php _e('Title', 'simple-page-builder'); ?></th>';
                html += '<th><?php _e('URL', 'simple-page-builder'); ?></th>';
                html += '<th><?php _e('Status', 'simple-page-builder'); ?></th>';
                html += '<th><?php _e('Created Date', 'simple-page-builder'); ?></th>';
                html += '<th><?php _e('API Key', 'simple-page-builder'); ?></th>';
                html += '</tr></thead><tbody>';
                
                pages.forEach(function(page) {
                    html += '<tr>';
                    html += '<td><strong>' + page.page_title + '</strong></td>';
                    html += '<td><a href="' + page.page_url + '" target="_blank">' + page.page_url + '</a></td>';
                    html += '<td><span class="spb-status spb-status-' + page.page_status + '">' + page.page_status + '</span></td>';
                    html += '<td>' + page.created_date + '</td>';
                    html += '<td>' + (page.key_name || 'Unknown') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#created-pages-container').html(html);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render Settings tab
     */
    private function render_settings_tab() {
        $webhook_url = get_option('spb_webhook_url', '');
        $webhook_secret = get_option('spb_webhook_secret', '');
        $rate_limit = get_option('spb_rate_limit_per_hour', 100);
        $api_enabled = get_option('spb_api_enabled', true);
        ?>
        <div class="spb-tab-content">
            <form id="spb-settings-form">
                <div class="spb-card">
                    <div class="spb-card-header">
                        <h3><?php _e('General Settings', 'simple-page-builder'); ?></h3>
                    </div>
                    <div class="spb-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="api_enabled"><?php _e('API Access', 'simple-page-builder'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="api_enabled" name="api_enabled" value="1" <?php checked($api_enabled); ?>>
                                    <label for="api_enabled"><?php _e('Enable API access', 'simple-page-builder'); ?></label>
                                    <p class="description"><?php _e('Disable to temporarily block all API requests.', 'simple-page-builder'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="rate_limit_per_hour"><?php _e('Rate Limit', 'simple-page-builder'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="rate_limit_per_hour" name="rate_limit_per_hour" value="<?php echo esc_attr($rate_limit); ?>" min="0" class="small-text">
                                    <span><?php _e('requests per hour per API key', 'simple-page-builder'); ?></span>
                                    <p class="description"><?php _e('Set to 0 for unlimited requests.', 'simple-page-builder'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="spb-card">
                    <div class="spb-card-header">
                        <h3><?php _e('Webhook Settings', 'simple-page-builder'); ?></h3>
                    </div>
                    <div class="spb-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="webhook_url"><?php _e('Webhook URL', 'simple-page-builder'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="webhook_url" name="webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text">
                                    <p class="description"><?php _e('URL to receive webhook notifications when pages are created.', 'simple-page-builder'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="webhook_secret"><?php _e('Webhook Secret', 'simple-page-builder'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="webhook_secret" name="webhook_secret" value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text">
                                    <button type="button" class="button" id="generate-webhook-secret"><?php _e('Generate New', 'simple-page-builder'); ?></button>
                                    <p class="description"><?php _e('Secret key used to sign webhook payloads for security verification.', 'simple-page-builder'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"></th>
                                <td>
                                    <button type="button" class="button" id="test-webhook"><?php _e('Test Webhook', 'simple-page-builder'); ?></button>
                                    <div id="webhook-test-result"></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'simple-page-builder'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render Documentation tab
     */
    private function render_documentation_tab() {
        $rest_url = rest_url('pagebuilder/v1/');
        ?>
        <div class="spb-tab-content">
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('API Documentation', 'simple-page-builder'); ?></h3>
                </div>
                <div class="spb-card-body">
                    <h4><?php _e('Base URL', 'simple-page-builder'); ?></h4>
                    <code><?php echo esc_html($rest_url); ?></code>
                    
                    <h4><?php _e('Authentication', 'simple-page-builder'); ?></h4>
                    <p><?php _e('All API requests must include an API key in the request header:', 'simple-page-builder'); ?></p>
                    <pre><code>X-API-Key: your_api_key_here</code></pre>
                    
                    <h4><?php _e('Create Pages Endpoint', 'simple-page-builder'); ?></h4>
                    <p><strong><?php _e('URL:', 'simple-page-builder'); ?></strong> <code>POST <?php echo esc_html($rest_url); ?>create-pages</code></p>
                    
                    <h5><?php _e('Request Body Example:', 'simple-page-builder'); ?></h5>
                    <pre><code>{
  "pages": [
    {
      "title": "About Us",
      "content": "&lt;p&gt;This is our about page.&lt;/p&gt;",
      "slug": "about-us",
      "status": "publish"
    },
    {
      "title": "Contact",
      "content": "&lt;p&gt;Contact us here.&lt;/p&gt;",
      "slug": "contact",
      "status": "draft"
    }
  ],
  "webhook_url": "https://example.com/webhook" // Optional override
}</code></pre>
                    
                    <h5><?php _e('Request Parameters:', 'simple-page-builder'); ?></h5>
                    <ul>
                        <li><strong>pages</strong> (array, required): Array of page objects to create</li>
                        <li><strong>pages[].title</strong> (string, required): Page title</li>
                        <li><strong>pages[].content</strong> (string, optional): Page content (HTML allowed)</li>
                        <li><strong>pages[].slug</strong> (string, optional): Custom URL slug</li>
                        <li><strong>pages[].status</strong> (string, optional): Page status (publish, draft, private)</li>
                        <li><strong>pages[].parent_id</strong> (integer, optional): Parent page ID</li>
                        <li><strong>webhook_url</strong> (string, optional): Override default webhook URL</li>
                    </ul>
                    
                    <h5><?php _e('Response Example:', 'simple-page-builder'); ?></h5>
                    <pre><code>{
  "success": true,
  "request_id": "req_abc123xyz",
  "total_requested": 2,
  "total_created": 2,
  "total_errors": 0,
  "created_pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "<?php echo home_url(); ?>/about-us",
      "status": "publish"
    },
    {
      "id": 124,
      "title": "Contact",
      "url": "<?php echo home_url(); ?>/contact",
      "status": "draft"
    }
  ],
  "errors": [],
  "processing_time": 0.245
}</code></pre>
                    
                    <h4><?php _e('cURL Example', 'simple-page-builder'); ?></h4>
                    <pre><code>curl -X POST "<?php echo esc_html($rest_url); ?>create-pages" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{
    "pages": [
      {
        "title": "Sample Page",
        "content": "&lt;p&gt;This is a sample page created via API.&lt;/p&gt;",
        "status": "publish"
      }
    ]
  }'</code></pre>
                    
                    <h4><?php _e('Rate Limiting', 'simple-page-builder'); ?></h4>
                    <p><?php printf(__('API requests are limited to %d requests per hour per API key.', 'simple-page-builder'), get_option('spb_rate_limit_per_hour', 100)); ?></p>
                    <p><?php _e('Rate limit information is included in response headers:', 'simple-page-builder'); ?></p>
                    <ul>
                        <li><code>X-RateLimit-Limit</code>: Total requests allowed per hour</li>
                        <li><code>X-RateLimit-Remaining</code>: Remaining requests in current window</li>
                        <li><code>X-RateLimit-Reset</code>: Unix timestamp when limit resets</li>
                    </ul>
                    
                    <h4><?php _e('Webhook Notifications', 'simple-page-builder'); ?></h4>
                    <p><?php _e('When pages are successfully created, a webhook notification is sent to the configured URL:', 'simple-page-builder'); ?></p>
                    <pre><code>{
  "event": "pages_created",
  "timestamp": "2025-11-24T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "<?php echo home_url(); ?>/about-us",
      "status": "publish"
    }
  ]
}</code></pre>
                    
                    <h5><?php _e('Webhook Security', 'simple-page-builder'); ?></h5>
                    <p><?php _e('Webhook requests include a signature header for security verification:', 'simple-page-builder'); ?></p>
                    <pre><code>X-Webhook-Signature: sha256=hash_of_payload</code></pre>
                    <p><?php _e('Verify the signature using HMAC-SHA256 with your webhook secret.', 'simple-page-builder'); ?></p>
                    
                    <h4><?php _e('Error Responses', 'simple-page-builder'); ?></h4>
                    <p><?php _e('Error responses include appropriate HTTP status codes and error messages:', 'simple-page-builder'); ?></p>
                    <ul>
                        <li><strong>401 Unauthorized</strong>: Invalid or missing API key</li>
                        <li><strong>429 Too Many Requests</strong>: Rate limit exceeded</li>
                        <li><strong>400 Bad Request</strong>: Invalid request data</li>
                        <li><strong>503 Service Unavailable</strong>: API temporarily disabled</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        $webhook_url = sanitize_url($_POST['webhook_url'] ?? '');
        $webhook_secret = sanitize_text_field($_POST['webhook_secret'] ?? '');
        $rate_limit = intval($_POST['rate_limit_per_hour'] ?? 100);
        $api_enabled = isset($_POST['api_enabled']) ? 1 : 0;
        
        update_option('spb_webhook_url', $webhook_url);
        update_option('spb_webhook_secret', $webhook_secret);
        update_option('spb_rate_limit_per_hour', $rate_limit);
        update_option('spb_api_enabled', $api_enabled);
        
        wp_send_json_success(__('Settings saved successfully.', 'simple-page-builder'));
    }
    
    /**
     * AJAX: Test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        $webhook_url = sanitize_url($_POST['webhook_url'] ?? '');
        
        if (empty($webhook_url)) {
            wp_send_json_error(__('Webhook URL is required.', 'simple-page-builder'));
        }
        
        $webhook = SPB_Webhook::get_instance();
        $result = $webhook->test_webhook($webhook_url);
        
        if ($result['success']) {
            wp_send_json_success(__('Webhook test successful!', 'simple-page-builder'));
        } else {
            wp_send_json_error(__('Webhook test failed: ', 'simple-page-builder') . $result['error']);
        }
    }
    
    /**
     * AJAX: Export logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        $logger = SPB_Logger::get_instance();
        $csv_data = $logger->export_logs_csv();
        
        // Create CSV content
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        $filename = 'spb-api-logs-' . date('Y-m-d') . '.csv';
        
        wp_send_json_success(array(
            'filename' => $filename,
            'content' => base64_encode($csv_content)
        ));
    }
    
    /**
     * AJAX: Get created pages
     */
    public function ajax_get_created_pages() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'simple-page-builder'));
        }
        
        global $wpdb;
        $created_pages_table = SPB_Database::get_table_name('created_pages');
        $api_keys_table = SPB_Database::get_table_name('api_keys');
        
        $pages = $wpdb->get_results(
            "SELECT cp.*, ak.key_name 
             FROM $created_pages_table cp 
             LEFT JOIN $api_keys_table ak ON cp.api_key_id = ak.id 
             ORDER BY cp.created_date DESC 
             LIMIT 100"
        );
        
        wp_send_json_success($pages);
    }
}