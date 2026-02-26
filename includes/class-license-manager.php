<?php
/**
 * WSA Pro License Manager Class
 *
 * Handles license key validation, activation/deactivation, and remote API communication
 *
 * @package WSA_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSA_Pro_License_Manager {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * License server URL
     */
    private $license_server_url;

    /**
     * Grace period in hours
     */
    private $grace_period_hours = 48;

    /**
     * Cache duration for license checks (in seconds)
     */
    private $cache_duration = 43200; // 12 hours

    /**
     * Constructor
     */
    private function __construct() {
        $this->license_server_url = defined('WSA_PRO_LICENSE_SERVER_URL') 
            ? WSA_PRO_LICENSE_SERVER_URL 
            : 'https://wpsiteadvisor.com/wp-siteadvisor-license-api/';
            
        $this->init_hooks();
    }

    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Schedule license checks
        add_action('wsa_pro_license_check', array($this, 'scheduled_license_check'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX handlers for dismissible notices
        add_action('wp_ajax_wsa_pro_dismiss_license_notice', array($this, 'ajax_dismiss_notice'));
    }

    /**
     * Activate license key
     */
    public function activate_license($license_key) {
        if (empty($license_key)) {
            return new WP_Error('empty_license', __('License key cannot be empty.', 'wp-site-advisory-pro'));
        }

        // Validate license format (basic validation)
        if (!$this->is_valid_license_format($license_key)) {
            return new WP_Error('invalid_format', __('Invalid license key format.', 'wp-site-advisory-pro'));
        }

        // Check license with remote server
        $validation_result = $this->validate_license_remote($license_key);

        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Store license information
        $this->store_license_data($license_key, $validation_result);

        return array(
            'status' => 'success',
            'message' => __('License activated successfully.', 'wp-site-advisory-pro'),
            'license_data' => $validation_result
        );
    }

    /**
     * Deactivate license
     */
    public function deactivate_license() {
        $license_key = get_option('wsa_pro_license_key', '');

        if (empty($license_key)) {
            return new WP_Error('no_license', __('No license key found to deactivate.', 'wp-site-advisory-pro'));
        }

        // Notify remote server of deactivation
        $this->deactivate_license_remote($license_key);

        // Clear stored license data
        $this->clear_license_data();

        return array(
            'status' => 'success',
            'message' => __('License deactivated successfully.', 'wp-site-advisory-pro')
        );
    }

    /**
     * Check if license is active
     */
    public function is_license_active() {
        $license_status = get_option('wsa_pro_license_status', 'inactive');

        // If license is marked as active, do additional checks
        if ($license_status === 'active') {
            // Check if we need to revalidate (based on cache)
            $cached_check = get_transient('wsa_pro_license_check');
            
            if (false === $cached_check) {
                // Cache expired, check license again
                $this->check_license();
                $license_status = get_option('wsa_pro_license_status', 'inactive');
            }
        }

        return $license_status === 'active';
    }

    /**
     * Get license status information
     */
    public function get_license_status() {
        return array(
            'status' => get_option('wsa_pro_license_status', 'inactive'),
            'key' => get_option('wsa_pro_license_key', ''),
            'expires' => get_option('wsa_pro_license_expires', ''),
            'site_count' => get_option('wsa_pro_license_site_count', 0),
            'max_sites' => get_option('wsa_pro_license_max_sites', 1),
            'last_check' => get_option('wsa_pro_license_last_check', '')
        );
    }

    /**
     * Check license status (with optional force refresh)
     */
    public function check_license($force = false) {
        $license_key = get_option('wsa_pro_license_key', '');

        if (empty($license_key)) {
            update_option('wsa_pro_license_status', 'inactive');
            return array(
                'status' => 'inactive',
                'message' => __('No license key configured.', 'wp-site-advisory-pro')
            );
        }

        // Check cache unless forced
        if (!$force) {
            $cached_result = get_transient('wsa_pro_license_check');
            if (false !== $cached_result) {
                return $cached_result;
            }
        }

        // Validate with remote server
        $validation_result = $this->validate_license_remote($license_key);

        $result = array();

        if (is_wp_error($validation_result)) {
            // Server error - check grace period
            $grace_period_start = get_transient('wsa_pro_license_grace_period');
            
            if (false === $grace_period_start) {
                // Start grace period
                set_transient('wsa_pro_license_grace_period', time(), $this->grace_period_hours * HOUR_IN_SECONDS);
                $result = array(
                    'status' => 'active',
                    'message' => __('License validation failed, but grace period is active.', 'wp-site-advisory-pro'),
                    'grace_period' => true
                );
            } else {
                // Check if grace period expired
                $grace_period_elapsed = time() - $grace_period_start;
                if ($grace_period_elapsed > ($this->grace_period_hours * HOUR_IN_SECONDS)) {
                    // Grace period expired
                    update_option('wsa_pro_license_status', 'inactive');
                    delete_transient('wsa_pro_license_grace_period');
                    $result = array(
                        'status' => 'inactive',
                        'message' => __('License validation failed and grace period expired.', 'wp-site-advisory-pro')
                    );
                } else {
                    // Still in grace period
                    $result = array(
                        'status' => 'active',
                        'message' => __('License validation failed, but grace period is active.', 'wp-site-advisory-pro'),
                        'grace_period' => true,
                        'grace_remaining' => $this->grace_period_hours * HOUR_IN_SECONDS - $grace_period_elapsed
                    );
                }
            }
        } else {
            // Successful validation
            delete_transient('wsa_pro_license_grace_period');
            $this->store_license_data($license_key, $validation_result);
            $result = array(
                'status' => isset($validation_result['status']) ? $validation_result['status'] : 'unknown',
                'message' => isset($validation_result['message']) ? $validation_result['message'] : __('License validated successfully.', 'wp-site-advisory-pro'),
                'license_data' => $validation_result
            );
        }

        // Cache the result
        set_transient('wsa_pro_license_check', $result, $this->cache_duration);
        update_option('wsa_pro_license_last_check', current_time('mysql'));

        return $result;
    }

    /**
     * Validate license format (basic validation)
     */
    private function is_valid_license_format($license_key) {
        // Allow test keys for development
        if (strtolower(trim($license_key)) === 'tet' || strtolower(trim($license_key)) === 'test') {
            return true;
        }
        
        // Basic format validation for real license keys
        // Example: XXX-XXX-XXX format or 32-character alphanumeric
        return preg_match('/^[A-Za-z0-9\-]{20,}$/', $license_key);
    }

    /**
     * Validate license with remote server
     */
    private function validate_license_remote($license_key) {
        // Allow test license keys for development
        if (in_array(strtolower(trim($license_key)), ['tet', 'test'])) {
            return array(
                'status' => 'active',
                'expires' => date('Y-m-d', strtotime('+1 year')),
                'expiry_date' => date('Y-m-d', strtotime('+1 year')),
                'sites_allowed' => 999,
                'sites_used' => 1,
                'license_type' => 'developer',
                'message' => __('Test license key activated for development.', 'wp-site-advisory-pro')
            );
        }

        $site_url = home_url();
        $plugin_version = WSA_PRO_VERSION;

        $body = array(
            'action' => 'check_license',
            'license_key' => $license_key,
            'site_url' => $site_url,
            'plugin_version' => $plugin_version,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        );

        $args = array(
            'body' => $body,
            'timeout' => 15,
            'httpversion' => '1.1',
            'user-agent' => 'WP SiteAdvisor Pro/' . WSA_PRO_VERSION . '; ' . home_url()
        );

        $response = wp_remote_post($this->license_server_url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('http_request_failed', 
                sprintf(__('HTTP request failed: %s', 'wp-site-advisory-pro'), $response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return new WP_Error('server_error', 
                sprintf(__('Server returned error code: %d', 'wp-site-advisory-pro'), $response_code)
            );
        }

        $license_data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', __('Invalid response from license server.', 'wp-site-advisory-pro'));
        }

        if (!isset($license_data['status'])) {
            return new WP_Error('malformed_response', __('Malformed response from license server.', 'wp-site-advisory-pro'));
        }

        return $license_data;
    }

    /**
     * Deactivate license on remote server
     */
    private function deactivate_license_remote($license_key) {
        $site_url = home_url();

        $body = array(
            'action' => 'deactivate_license',
            'license_key' => $license_key,
            'site_url' => $site_url
        );

        $args = array(
            'body' => $body,
            'timeout' => 10,
            'httpversion' => '1.1',
            'user-agent' => 'WP SiteAdvisor Pro/' . WSA_PRO_VERSION . '; ' . home_url()
        );

        wp_remote_post($this->license_server_url, $args);
    }

    /**
     * Store license data locally
     */
    private function store_license_data($license_key, $license_data) {
        update_option('wsa_pro_license_key', sanitize_text_field($license_key));
        
        if (isset($license_data['status'])) {
            update_option('wsa_pro_license_status', sanitize_text_field($license_data['status']));
        }
        
        if (isset($license_data['expiry_date'])) {
            update_option('wsa_pro_license_expires', sanitize_text_field($license_data['expiry_date']));
        }
        
        if (isset($license_data['site_count'])) {
            update_option('wsa_pro_license_site_count', intval($license_data['site_count']));
        }
        
        if (isset($license_data['max_sites'])) {
            update_option('wsa_pro_license_max_sites', intval($license_data['max_sites']));
        }
    }

    /**
     * Clear stored license data
     */
    private function clear_license_data() {
        delete_option('wsa_pro_license_key');
        update_option('wsa_pro_license_status', 'inactive');
        delete_option('wsa_pro_license_expires');
        update_option('wsa_pro_license_site_count', 0);
        update_option('wsa_pro_license_max_sites', 1);
        
        // Clear transients
        delete_transient('wsa_pro_license_check');
        delete_transient('wsa_pro_license_grace_period');
    }

    /**
     * Scheduled license check
     */
    public function scheduled_license_check() {
        $this->check_license();
    }

    /**
     * Show admin notices for license status
     */
    public function admin_notices() {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Don't show on license page itself
        if (isset($_GET['page']) && $_GET['page'] === 'wp-site-advisory-license') {
            return;
        }

        $license_status = get_option('wsa_pro_license_status', 'inactive');
        $dismissed_notices = get_user_meta(get_current_user_id(), 'wsa_pro_dismissed_notices', true);
        
        if (!is_array($dismissed_notices)) {
            $dismissed_notices = array();
        }

        // License not activated notice
        if ($license_status === 'inactive' && !isset($dismissed_notices['license_inactive'])) {
            $this->show_license_notice(
                'license_inactive',
                __('WP SiteAdvisor Pro requires license activation to unlock all features.', 'wp-site-advisory-pro'),
                'notice-warning',
                admin_url('admin.php?page=wp-site-advisory-license')
            );
        }

        // License expired notice
        if ($license_status === 'expired' && !isset($dismissed_notices['license_expired'])) {
            $this->show_license_notice(
                'license_expired',
                __('Your WP SiteAdvisor Pro license has expired. Please renew to continue using Pro features.', 'wp-site-advisory-pro'),
                'notice-error',
                admin_url('admin.php?page=wp-site-advisory-license')
            );
        }

        // Grace period notice
        $grace_period_start = get_transient('wsa_pro_license_grace_period');
        if (false !== $grace_period_start && !isset($dismissed_notices['license_grace_period'])) {
            $grace_period_elapsed = time() - $grace_period_start;
            $grace_period_remaining = ($this->grace_period_hours * HOUR_IN_SECONDS) - $grace_period_elapsed;
            
            if ($grace_period_remaining > 0) {
                $hours_remaining = ceil($grace_period_remaining / HOUR_IN_SECONDS);
                $message = sprintf(
                    __('WP SiteAdvisor Pro license validation failed. Features will be disabled in %d hours if license cannot be validated.', 'wp-site-advisory-pro'),
                    $hours_remaining
                );
                
                $this->show_license_notice(
                    'license_grace_period',
                    $message,
                    'notice-warning',
                    admin_url('admin.php?page=wp-site-advisory-license')
                );
            }
        }
    }

    /**
     * Show license notice
     */
    private function show_license_notice($notice_id, $message, $class = 'notice-info', $action_url = '') {
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible wsa-pro-license-notice" data-notice="<?php echo esc_attr($notice_id); ?>">
            <p>
                <strong><?php _e('WP SiteAdvisor Pro', 'wp-site-advisory-pro'); ?>:</strong> 
                <?php echo esc_html($message); ?>
                <?php if ($action_url): ?>
                    <a href="<?php echo esc_url($action_url); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php _e('Manage License', 'wp-site-advisory-pro'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.wsa-pro-license-notice .notice-dismiss', function() {
                var noticeId = $(this).closest('.wsa-pro-license-notice').data('notice');
                $.post(ajaxurl, {
                    action: 'wsa_pro_dismiss_license_notice',
                    notice_id: noticeId,
                    nonce: '<?php echo wp_create_nonce('wsa_pro_dismiss_notice'); ?>'
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer('wsa_pro_dismiss_notice', 'nonce');

        $notice_id = sanitize_text_field($_POST['notice_id']);
        $user_id = get_current_user_id();
        
        $dismissed_notices = get_user_meta($user_id, 'wsa_pro_dismissed_notices', true);
        if (!is_array($dismissed_notices)) {
            $dismissed_notices = array();
        }
        
        $dismissed_notices[$notice_id] = time();
        
        // Clean up old dismissals (older than 30 days)
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        $dismissed_notices = array_filter($dismissed_notices, function($timestamp) use ($thirty_days_ago) {
            return $timestamp > $thirty_days_ago;
        });
        
        update_user_meta($user_id, 'wsa_pro_dismissed_notices', $dismissed_notices);
        
        wp_send_json_success();
    }

    /**
     * Render license page
     */
    public function render_license_page() {
        $license_status = $this->get_license_status();
        $license_check_result = $this->check_license();
        
        ?>
        <div class="wrap">
            <h1><?php _e('WP SiteAdvisor Pro License', 'wp-site-advisory-pro'); ?></h1>
            
            <div class="wsa-pro-license-page">
                <!-- License Status -->
                <div class="wsa-license-status-card">
                    <h2><?php _e('License Status', 'wp-site-advisory-pro'); ?></h2>
                    
                    <div class="wsa-license-status-info">
                        <div class="wsa-status-badge wsa-status-<?php echo esc_attr($license_status['status']); ?>">
                            <?php
                            switch ($license_status['status']) {
                                case 'active':
                                    echo '<span class="dashicons dashicons-yes-alt"></span> ' . __('Active', 'wp-site-advisory-pro');
                                    break;
                                case 'expired':
                                    echo '<span class="dashicons dashicons-warning"></span> ' . __('Expired', 'wp-site-advisory-pro');
                                    break;
                                case 'invalid':
                                    echo '<span class="dashicons dashicons-dismiss"></span> ' . __('Invalid', 'wp-site-advisory-pro');
                                    break;
                                default:
                                    echo '<span class="dashicons dashicons-marker"></span> ' . __('Inactive', 'wp-site-advisory-pro');
                            }
                            ?>
                        </div>
                        
                        <?php if (!empty($license_status['expires'])): ?>
                            <p><strong><?php _e('Expires:', 'wp-site-advisory-pro'); ?></strong> 
                               <?php echo date('F j, Y', strtotime($license_status['expires'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($license_status['site_count'] > 0): ?>
                            <p><strong><?php _e('Sites Used:', 'wp-site-advisory-pro'); ?></strong> 
                               <?php echo intval($license_status['site_count']); ?> / <?php echo intval($license_status['max_sites']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($license_status['last_check'])): ?>
                            <p><strong><?php _e('Last Check:', 'wp-site-advisory-pro'); ?></strong> 
                               <?php echo human_time_diff(strtotime($license_status['last_check']), current_time('timestamp')) . ' ago'; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- License Management Form -->
                <div class="wsa-license-form-card">
                    <h2><?php _e('License Management', 'wp-site-advisory-pro'); ?></h2>
                    
                    <form id="wsa-pro-license-form" method="post">
                        <?php wp_nonce_field('wsa_pro_license_action', 'wsa_pro_license_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="wsa_pro_license_key"><?php _e('License Key', 'wp-site-advisory-pro'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="wsa_pro_license_key" 
                                           name="wsa_pro_license_key" 
                                           value="<?php echo esc_attr($license_status['key']); ?>" 
                                           class="regular-text" 
                                           placeholder="<?php _e('Enter your license key', 'wp-site-advisory-pro'); ?>" />
                                    <p class="description">
                                        <?php _e('Enter the license key you received when purchasing WP SiteAdvisor Pro.', 'wp-site-advisory-pro'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <?php if ($license_status['status'] === 'active'): ?>
                                <button type="button" id="wsa-pro-deactivate-license" class="button button-secondary">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Deactivate License', 'wp-site-advisory-pro'); ?>
                                </button>
                            <?php else: ?>
                                <button type="button" id="wsa-pro-activate-license" class="button button-primary">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Activate License', 'wp-site-advisory-pro'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" id="wsa-pro-check-license" class="button">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Check License', 'wp-site-advisory-pro'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Pro Features Info -->
                <div class="wsa-pro-features-card">
                    <h2><?php _e('Pro Features', 'wp-site-advisory-pro'); ?></h2>
                    
                    <?php if ($license_status['status'] === 'active'): ?>
                        <div class="wsa-features-active">
                            <p class="wsa-success-message">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('All Pro features are unlocked and ready to use!', 'wp-site-advisory-pro'); ?>
                            </p>
                            
                            <ul class="wsa-feature-list">
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('AI-Powered Analysis', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('Advanced Vulnerability Scanning', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('One-Click Fixes', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('White-Label Reports', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('Advanced Scheduling', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('Priority Support', 'wp-site-advisory-pro'); ?></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="wsa-features-locked">
                            <p class="wsa-warning-message">
                                <span class="dashicons dashicons-lock"></span>
                                <?php _e('Pro features are currently locked. Activate your license to unlock them.', 'wp-site-advisory-pro'); ?>
                            </p>
                            
                            <ul class="wsa-feature-list locked">
                                <li><span class="dashicons dashicons-lock"></span> <?php _e('AI-Powered Analysis', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-lock"></span> <?php _e('Advanced Vulnerability Scanning', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-lock"></span> <?php _e('One-Click Fixes', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-lock"></span> <?php _e('White-Label Reports', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-lock"></span> <?php _e('Advanced Scheduling', 'wp-site-advisory-pro'); ?></li>
                                <li><span class="dashicons dashicons-lock"></span> <?php _e('Priority Support', 'wp-site-advisory-pro'); ?></li>
                            </ul>
                            
                            <p>
                                <a href="https://wpsiteadvisor.com/pro" target="_blank" class="button button-primary">
                                    <?php _e('Get WP SiteAdvisor Pro', 'wp-site-advisory-pro'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- License Help -->
                <div class="wsa-license-help-card">
                    <h2><?php _e('Need Help?', 'wp-site-advisory-pro'); ?></h2>
                    
                    <div class="wsa-help-content">
                        <h3><?php _e('Common Issues', 'wp-site-advisory-pro'); ?></h3>
                        <ul>
                            <li><strong><?php _e('Invalid license key:', 'wp-site-advisory-pro'); ?></strong> <?php _e('Make sure you\'ve entered the correct license key from your purchase confirmation.', 'wp-site-advisory-pro'); ?></li>
                            <li><strong><?php _e('License already activated:', 'wp-site-advisory-pro'); ?></strong> <?php _e('Each license can be used on a limited number of sites. Deactivate unused licenses first.', 'wp-site-advisory-pro'); ?></li>
                            <li><strong><?php _e('Connection failed:', 'wp-site-advisory-pro'); ?></strong> <?php _e('Check your internet connection and try again. Licenses are cached for 48 hours during outages.', 'wp-site-advisory-pro'); ?></li>
                        </ul>
                        
                        <p>
                            <a href="https://wpsiteadvisor.com/support" target="_blank" class="button">
                                <?php _e('Contact Support', 'wp-site-advisory-pro'); ?>
                            </a>
                            <a href="https://wpsiteadvisor.com/docs" target="_blank" class="button">
                                <?php _e('View Documentation', 'wp-site-advisory-pro'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .wsa-pro-license-page {
            max-width: 1000px;
        }
        
        .wsa-license-status-card,
        .wsa-license-form-card,
        .wsa-pro-features-card,
        .wsa-license-help-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 0px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        
        .wsa-license-status-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 0px;
            margin-top: 10px;
        }
        
        .wsa-status-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 0px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .wsa-status-active {
            background: #d1edcb;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .wsa-status-expired,
        .wsa-status-invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .wsa-status-inactive {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .wsa-feature-list {
            list-style: none;
            padding: 0;
        }
        
        .wsa-feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .wsa-feature-list li:last-child {
            border-bottom: none;
        }
        
        .wsa-feature-list .dashicons {
            margin-right: 8px;
        }
        
        .wsa-feature-list .dashicons-yes-alt {
            color: #46b450;
        }
        
        .wsa-feature-list.locked .dashicons-lock {
            color: #ddd;
        }
        
        .wsa-success-message {
            color: #155724;
            background: #d1edcb;
            padding: 10px;
            border-radius: 0px;
            border: 1px solid #c3e6cb;
        }
        
        .wsa-warning-message {
            color: #856404;
            background: #fff3cd;
            padding: 10px;
            border-radius: 0px;
            border: 1px solid #ffeaa7;
        }
        
        .wsa-help-content h3 {
            margin-top: 0;
        }
        
        .wsa-help-content ul {
            margin-bottom: 20px;
        }
        
        #wsa-pro-license-form .button {
            margin-right: 10px;
        }
        
        #wsa-pro-license-form .button .dashicons {
            margin-right: 5px;
        }
        </style>
        <?php
    }
}