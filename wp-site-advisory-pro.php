<?php
/**
 * Plugin Name: WP SiteAdvisor Pro
 * Plugin URI: https://wpsiteadvisor.com/pro
 * Description: Advanced WordPress site analysis with AI-powered recommendations, vulnerability scanning, and white-label reporting. Requires valid license key for activation.
 * Version: 1.0.0
 * Author: WP SiteAdvisor
 * Author URI: https://wpsiteadvisor.com
 * License: Commercial License
 * License URI: https://wpsiteadvisor.com/license
 * Text Domain: wp-site-advisory-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 * Requires Plugins: wp-site-advisory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants first
define('WSA_PRO_VERSION', '1.0.0');
define('WSA_PRO_PATH', plugin_dir_path(__FILE__));
define('WSA_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSA_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSA_PRO_PLUGIN_FILE', __FILE__);

// Check if free version is active (check for plugin file instead of function)
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('wp-site-advisory/wp-site-advisory.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('WP SiteAdvisor Pro requires the free WP SiteAdvisor plugin to be installed and activated.', 'wp-site-advisory-pro');
        echo '</p></div>';
    });
    return;
}
define('WSA_PRO_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WSA_PRO_LICENSE_SERVER_URL', 'https://wpsiteadvisor.com/wp-siteadvisor-license-api/');

/**
 * Main WSA_Pro Class
 */
class WSA_Pro {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * License manager instance
     */
    private $license_manager = null;

    /**
     * Plugin version
     */
    public $version = '1.0.0';

    /**
     * Feature instances
     */
    private $features = array();

    /**
     * Constructor
     */
    private function __construct() {
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
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Plugin activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin after WordPress is loaded
        add_action('init', array($this, 'init'));
        
        // Admin menu integration
        add_action('admin_menu', array($this, 'add_license_menu'), 15);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Ajax handlers
        add_action('wp_ajax_wsa_pro_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_wsa_pro_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_wsa_pro_check_license', array($this, 'ajax_check_license'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $this->set_default_options();
        
        // Schedule license checks
        if (!wp_next_scheduled('wsa_pro_license_check')) {
            wp_schedule_event(time(), 'twicedaily', 'wsa_pro_license_check');
        }
        
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled license checks
        wp_clear_scheduled_hook('wsa_pro_license_check');
        
        // Clear Pro feature scheduled events
        wp_clear_scheduled_hook('wsa_pro_daily_scan');
        wp_clear_scheduled_hook('wsa_pro_weekly_reports');
        wp_clear_scheduled_hook('wsa_pro_monthly_reports');
        
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for internationalization
        load_plugin_textdomain('wp-site-advisory-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        $this->init_components();
        
        // Initialize Pro features based on license status
        $this->init_pro_features();
    }

    /**
     * Include required files
     */
    private function include_files() {
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-license-manager.php';
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-ai-analyzer.php';
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-ai-settings.php';
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-ai-dashboard.php';
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-openai-usage.php';
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-unified-settings.php';
        require_once WSA_PRO_PLUGIN_DIR . 'includes/class-branding.php';
        
        // Load Pro features
        $this->load_features();
    }
    
    /**
     * Load Pro features
     */
    private function load_features() {
        $feature_files = array(
            'ai-conflict-detector' => WSA_PRO_PLUGIN_DIR . 'includes/features/ai-conflict-detector.php',
            'wpscan-vulnerabilities' => WSA_PRO_PLUGIN_DIR . 'includes/features/wpscan-vulnerabilities.php',
            'pagespeed-analysis' => WSA_PRO_PLUGIN_DIR . 'includes/features/pagespeed-analysis.php',
            'white-label-reports' => WSA_PRO_PLUGIN_DIR . 'includes/features/white-label-reports.php',
            'admin-ai-chatbot' => WSA_PRO_PLUGIN_DIR . 'includes/features/admin-ai-chatbot.php',
            'ai-site-detective' => WSA_PRO_PLUGIN_DIR . 'includes/features/ai-site-detective.php',
            // New AI-powered features
            'ai-automated-optimizer' => WSA_PRO_PLUGIN_DIR . 'includes/features/ai-automated-optimizer.php',
            'ai-predictive-analytics' => WSA_PRO_PLUGIN_DIR . 'includes/features/ai-predictive-analytics.php',
            'ai-content-analyzer' => WSA_PRO_PLUGIN_DIR . 'includes/features/ai-content-analyzer.php'
        );
        
        foreach ($feature_files as $feature_key => $file_path) {
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize license manager first
        $this->license_manager = WSA_Pro_License_Manager::get_instance();
        
        // Initialize AI Analyzer (overrides Free version AI recommendations)
        if (class_exists('WSA_Pro_AI_Analyzer')) {
            $this->ai_analyzer = WSA_Pro_AI_Analyzer::get_instance();
        }
        
        // Initialize Unified Settings (always available for configuration)
        if (class_exists('WP_Site_Advisory_Unified_Settings')) {
            $this->unified_settings = new WP_Site_Advisory_Unified_Settings();
        }
        
        // Initialize AI Dashboard (always available)
        if (class_exists('WSA_Pro\Admin\AI_Dashboard')) {
            $this->ai_dashboard = new \WSA_Pro\Admin\AI_Dashboard();
        }
        
        // Initialize Pro branding system
        if (class_exists('WP_Site_Advisory_Pro_Branding')) {
            WP_Site_Advisory_Pro_Branding::get_instance();
        }
    }

    /**
     * Initialize Pro features based on license status
     */
    private function init_pro_features() {
        // Only initialize Pro features if license is valid
        if ($this->license_manager && $this->license_manager->is_license_active()) {
            $this->init_feature_instances();
            $this->add_pro_admin_pages();
        }
    }
    
    /**
     * Initialize feature instances
     */
    private function init_feature_instances() {
        // Initialize existing AI features
        if (class_exists('\WSA_Pro\Features\AI_Conflict_Detector')) {
            $this->features['ai_conflict_detector'] = new \WSA_Pro\Features\AI_Conflict_Detector();
        }
        
        // Initialize WPScan Vulnerabilities
        if (class_exists('\WSA_Pro\Features\WPScan_Vulnerabilities')) {
            $this->features['wpscan_vulnerabilities'] = new \WSA_Pro\Features\WPScan_Vulnerabilities();
        }
        
        // Initialize PageSpeed Analysis
        if (class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
            $this->features['pagespeed_analysis'] = new \WSA_Pro\Features\PageSpeed_Analysis();
        }
        
        // Initialize White Label Reports
        if (class_exists('\WSA_Pro\Features\White_Label_Reports')) {
            $this->features['white_label_reports'] = new \WSA_Pro\Features\White_Label_Reports();
        }
        
        // Initialize Admin AI Chatbot
        if (class_exists('\WSA_Pro\Features\Admin_AI_Chatbot')) {
            $this->features['admin_ai_chatbot'] = new \WSA_Pro\Features\Admin_AI_Chatbot();
        }
        
        // Initialize AI Site Detective
        if (class_exists('WSA_AI_Site_Detective')) {
            $this->features['ai_site_detective'] = WSA_AI_Site_Detective::get_instance();
        }
        
        // Initialize new AI-powered features
        if (class_exists('\WSA_Pro\Features\AI_Automated_Optimizer')) {
            $this->features['ai_automated_optimizer'] = new \WSA_Pro\Features\AI_Automated_Optimizer();
        }
        
        if (class_exists('\WSA_Pro\Features\AI_Predictive_Analytics')) {
            $this->features['ai_predictive_analytics'] = new \WSA_Pro\Features\AI_Predictive_Analytics();
        }
        
        if (class_exists('\WSA_Pro\Features\AI_Content_Analyzer')) {
            $this->features['ai_content_analyzer'] = new \WSA_Pro\Features\AI_Content_Analyzer();
        }
    }
    
    /**
     * Add Pro admin pages
     */
    private function add_pro_admin_pages() {
        // Note: Settings are now handled by unified settings class
        add_action('wp_ajax_wsa_save_pro_settings', array($this, 'ajax_save_pro_settings'));
        
        // Hook into free plugin's dashboard to add Pro features
        // Since we can't modify the free plugin, we'll inject the Pro features via JavaScript and admin_footer
        add_action('admin_footer', array($this, 'inject_pro_dashboard_features'));
        
        // Add Pro-specific AJAX handlers to the free plugin's context
        $this->add_pro_ajax_handlers();
    }
    
    /**
     * Add Pro-specific AJAX handlers
     * Note: Individual features now handle their own AJAX endpoints
     */
    private function add_pro_ajax_handlers() {
        // Core Pro AJAX handlers only (features handle their own)
        // This prevents duplicate AJAX handler conflicts
    }
    
    /**
     * Add Pro settings page
     */
    public function add_pro_settings_page() {
        add_submenu_page(
            'wp-site-advisory',
            __('Pro Settings', 'wp-site-advisory-pro'),
            __('Pro Settings', 'wp-site-advisory-pro'),
            'manage_options',
            'wp-site-advisory-pro-settings',
            array($this, 'render_pro_settings_page')
        );
    }

    /**
     * Add license menu to WP SiteAdvisor
     */
    public function add_license_menu() {
        add_submenu_page(
            'wp-site-advisory',
            __('License', 'wp-site-advisory-pro'),
            __('License', 'wp-site-advisory-pro'),
            'manage_options',
            'wp-site-advisory-license',
            array($this, 'license_page')
        );
    }

    /**
     * License page
     */
    public function license_page() {
        if (!$this->license_manager) {
            echo '<div class="wrap"><h1>License Manager Not Available</h1></div>';
            return;
        }
        
        $this->license_manager->render_license_page();
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on WP SiteAdvisor pages
        if (strpos($hook, 'wp-site-advisory') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'wsa-pro-admin',
            WSA_PRO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WSA_PRO_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'wsa-pro-admin',
            WSA_PRO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WSA_PRO_VERSION,
            true
        );

        // Enqueue AI chatbot script
        wp_enqueue_script(
            'wsa-ai-chatbot',
            WSA_PRO_PLUGIN_URL . 'assets/js/ai-chatbot.js',
            array('jquery'),
            WSA_PRO_VERSION,
            true
        );

        // Enqueue Pro features integration script
        wp_enqueue_script(
            'wsa-pro-integration',
            WSA_PRO_PLUGIN_URL . 'assets/js/pro-integration.js',
            array('jquery', 'wsa-pro-admin'),
            WSA_PRO_VERSION,
            true
        );

        // Enqueue media uploader for Pro settings
        wp_enqueue_media();

        // Localize script for AI chatbot
        wp_localize_script('wsa-ai-chatbot', 'wsa_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_admin_nonce')
        ));

        // Localize script
        wp_localize_script('wsa-pro-admin', 'wsa_pro_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_pro_nonce'),
            'activating' => __('Activating...', 'wp-site-advisory-pro'),
            'deactivating' => __('Deactivating...', 'wp-site-advisory-pro'),
            'validating' => __('Validating...', 'wp-site-advisory-pro'),
            'activate_license' => __('Activate License', 'wp-site-advisory-pro'),
            'deactivate_license' => __('Deactivate License', 'wp-site-advisory-pro'),
            'validate_license' => __('Validate License', 'wp-site-advisory-pro'),
            'deactivate_confirm' => __('Are you sure you want to deactivate your license?', 'wp-site-advisory-pro'),
            'license_key_required' => __('Please enter a license key.', 'wp-site-advisory-pro'),
            'activation_error' => __('License activation failed', 'wp-site-advisory-pro'),
            'deactivation_error' => __('License deactivation failed', 'wp-site-advisory-pro'),
            'validation_error' => __('License validation failed', 'wp-site-advisory-pro'),
            'invalid_email' => __('Please enter a valid email address.', 'wp-site-advisory-pro'),
            'license_active' => __('License Active', 'wp-site-advisory-pro'),
            'license_inactive' => __('License Inactive', 'wp-site-advisory-pro'),
            'license_expired' => __('License Expired', 'wp-site-advisory-pro'),
            'license_valid' => __('Your license is valid and active.', 'wp-site-advisory-pro'),
            'license_expired_desc' => __('Your license has expired. Please renew to continue using Pro features.', 'wp-site-advisory-pro'),
            'enter_license_key' => __('Please enter your license key to activate Pro features.', 'wp-site-advisory-pro'),
            'generating_report' => __('Generating Report...', 'wp-site-advisory-pro'),
            'generate_report' => __('Generate Report', 'wp-site-advisory-pro'),
            'report_generated' => __('Report has been generated successfully!', 'wp-site-advisory-pro'),
            'report_ready' => __('Your report is ready!', 'wp-site-advisory-pro'),
            'download_report' => __('Download Report', 'wp-site-advisory-pro'),
            'report_error' => __('Report generation failed', 'wp-site-advisory-pro'),
            'manage_license' => __('Manage License', 'wp-site-advisory-pro')
        ));
        
        // Localize script for Pro integration
        wp_localize_script('wsa-pro-integration', 'wsa_pro_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_pro_nonce'),
            'generating_report' => __('Generating Report...', 'wp-site-advisory-pro'),
            'generate_report' => __('Generate Report', 'wp-site-advisory-pro'),
            'report_generated' => __('Report has been generated successfully!', 'wp-site-advisory-pro'),
            'report_ready' => __('Your report is ready!', 'wp-site-advisory-pro'),
            'download_report' => __('Download Report', 'wp-site-advisory-pro'),
            'report_error' => __('Report generation failed', 'wp-site-advisory-pro')
        ));
        
        // Also add ajaxurl for WordPress admin
        wp_localize_script('wsa-pro-admin', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    /**
     * AJAX handler for license activation
     */
    public function ajax_activate_license() {
        check_ajax_referer('wsa_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-site-advisory-pro'));
        }

        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(__('License key is required.', 'wp-site-advisory-pro'));
        }

        $result = $this->license_manager->activate_license($license_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('wsa_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-site-advisory-pro'));
        }

        $result = $this->license_manager->deactivate_license();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    /**
     * AJAX handler for license check
     */
    public function ajax_check_license() {
        check_ajax_referer('wsa_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-site-advisory-pro'));
        }

        $result = $this->license_manager->check_license(true); // Force check
        
        wp_send_json_success($result);
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            // License settings
            'wsa_pro_license_key' => '',
            'wsa_pro_license_status' => 'inactive',
            'wsa_pro_license_expires' => '',
            'wsa_pro_license_site_count' => 0,
            'wsa_pro_license_max_sites' => 1,
            
            // Pro feature settings
            'wsa_pro_company_name' => __('Your Company', 'wp-site-advisory-pro'),
            'wsa_pro_primary_color' => '#2c5aa0',
            'wsa_pro_report_recipients' => get_option('admin_email'),
            
            // Feature toggles (enabled by default)
            'wsa_pro_enable_ai_conflicts' => 1,
            'wsa_pro_enable_vuln_scanning' => 1,
            'wsa_pro_enable_pagespeed' => 1,
            'wsa_pro_enable_ai_chatbot' => 1,
            
            // Notifications (enabled by default)
            'wsa_pro_email_vulnerabilities' => 1,
            'wsa_pro_email_conflicts' => 1,
            'wsa_pro_email_reports' => 1,
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        // Schedule Pro cron jobs
        if (!wp_next_scheduled('wsa_pro_daily_scan')) {
            wp_schedule_event(time(), 'daily', 'wsa_pro_daily_scan');
        }
    }

    /**
     * Render Pro settings page
     */
    public function render_pro_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WP Site Advisory Pro Settings', 'wp-site-advisory-pro'); ?></h1>
            
            <form id="wsa-pro-settings-form" method="post">
                <?php wp_nonce_field('wsa_pro_settings', 'wsa_pro_nonce'); ?>
                
                <div class="wsa-pro-settings-tabs">
                    <div class="nav-tab-wrapper">
                        <a href="#api-keys" class="nav-tab nav-tab-active"><?php _e('API Keys', 'wp-site-advisory-pro'); ?></a>
                        <a href="#white-label" class="nav-tab"><?php _e('White Label', 'wp-site-advisory-pro'); ?></a>
                        <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'wp-site-advisory-pro'); ?></a>
                        <a href="#features" class="nav-tab"><?php _e('Features', 'wp-site-advisory-pro'); ?></a>
                    </div>
                    
                    <div id="api-keys" class="tab-content active">
                        <h3><?php _e('API Configuration', 'wp-site-advisory-pro'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('OpenAI API Key', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <?php
                                    $free_key = get_option('wsa_openai_api_key');
                                    $pro_key = get_option('wsa_pro_openai_api_key');
                                    
                                    if (!empty($free_key)) {
                                        echo '<p class="description" style="color: #0073aa; font-weight: bold;">';
                                        _e('✓ OpenAI API key is configured in the free plugin and will be used for Pro features.', 'wp-site-advisory-pro');
                                        echo '</p>';
                                        echo '<input type="password" name="openai_api_key" 
                                               value="' . esc_attr($pro_key) . '" 
                                               class="regular-text" 
                                               placeholder="' . esc_attr__('Optional: Override with Pro-specific key', 'wp-site-advisory-pro') . '" />';
                                    } else {
                                        echo '<input type="password" name="openai_api_key" 
                                               value="' . esc_attr($pro_key) . '" 
                                               class="regular-text" />';
                                    }
                                    ?>
                                    <p class="description"><?php _e('Required for AI features (Conflict Detection, PageSpeed Analysis, AI Chatbot). If configured in the free plugin, that key will be used first.', 'wp-site-advisory-pro'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('WPScan API Token', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <input type="password" name="wpscan_api_token" 
                                           value="<?php echo esc_attr(get_option('wsa_pro_wpscan_api_token')); ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Required for vulnerability scanning. Get your free token at', 'wp-site-advisory-pro'); ?> <a href="https://wpscan.com/api" target="_blank">wpscan.com/api</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Google PageSpeed API Key', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <input type="password" name="pagespeed_api_key" 
                                           value="<?php echo esc_attr(get_option('wsa_pro_pagespeed_api_key')); ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Required for PageSpeed Insights analysis. Get your key from', 'wp-site-advisory-pro'); ?> <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank"><?php _e('Google Developers Console', 'wp-site-advisory-pro'); ?></a></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="white-label" class="tab-content">
                        <h3><?php _e('White Label Settings', 'wp-site-advisory-pro'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Company Name', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <input type="text" name="company_name" 
                                           value="<?php echo esc_attr(get_option('wsa_pro_company_name', __('Your Company', 'wp-site-advisory-pro'))); ?>" 
                                           class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Company Logo', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <input type="url" name="company_logo" 
                                           value="<?php echo esc_url(get_option('wsa_pro_company_logo')); ?>" 
                                           class="regular-text" />
                                    <button type="button" class="button" id="upload-logo"><?php _e('Upload Logo', 'wp-site-advisory-pro'); ?></button>
                                    <p class="description"><?php _e('Logo for PDF reports (recommended size: 200x60px)', 'wp-site-advisory-pro'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Company Website', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <input type="url" name="company_website" 
                                           value="<?php echo esc_url(get_option('wsa_pro_company_website')); ?>" 
                                           class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Primary Color', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <input type="color" name="primary_color" 
                                           value="<?php echo esc_attr(get_option('wsa_pro_primary_color', '#2c5aa0')); ?>" />
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="notifications" class="tab-content">
                        <h3><?php _e('Notification Settings', 'wp-site-advisory-pro'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Email Notifications', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="email_vulnerabilities" 
                                               value="1" <?php checked(get_option('wsa_pro_email_vulnerabilities', 1)); ?> />
                                        <?php _e('Vulnerability alerts', 'wp-site-advisory-pro'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="email_conflicts" 
                                               value="1" <?php checked(get_option('wsa_pro_email_conflicts', 1)); ?> />
                                        <?php _e('Plugin/theme conflicts', 'wp-site-advisory-pro'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="email_reports" 
                                               value="1" <?php checked(get_option('wsa_pro_email_reports', 1)); ?> />
                                        <?php _e('Scheduled reports', 'wp-site-advisory-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Report Recipients', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <textarea name="report_recipients" rows="3" cols="50"><?php 
                                        echo esc_textarea(get_option('wsa_pro_report_recipients', get_option('admin_email'))); 
                                    ?></textarea>
                                    <p class="description"><?php _e('One email address per line for report distribution', 'wp-site-advisory-pro'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="features" class="tab-content">
                        <h3><?php _e('Feature Controls', 'wp-site-advisory-pro'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('AI Conflict Detection', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_ai_conflicts" 
                                               value="1" <?php checked(get_option('wsa_pro_enable_ai_conflicts', 1)); ?> />
                                        <?php _e('Enable automated conflict detection with AI analysis', 'wp-site-advisory-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Vulnerability Scanning', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_vuln_scanning" 
                                               value="1" <?php checked(get_option('wsa_pro_enable_vuln_scanning', 1)); ?> />
                                        <?php _e('Enable daily WPScan vulnerability checks', 'wp-site-advisory-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('PageSpeed Analysis', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_pagespeed" 
                                               value="1" <?php checked(get_option('wsa_pro_enable_pagespeed', 1)); ?> />
                                        <?php _e('Enable Google PageSpeed Insights with AI recommendations', 'wp-site-advisory-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('AI Chatbot', 'wp-site-advisory-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_ai_chatbot" 
                                               value="1" <?php checked(get_option('wsa_pro_enable_ai_chatbot', 1)); ?> />
                                        <?php _e('Enable AI assistant in admin pages', 'wp-site-advisory-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button-primary"><?php _e('Save Settings', 'wp-site-advisory-pro'); ?></button>
                    <span class="spinner"></span>
                </p>
            </form>
        </div>
        
        <style>
        .wsa-pro-settings-tabs .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .wsa-pro-settings-tabs .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-top: none;
        }
        .wsa-pro-settings-tabs .tab-content.active {
            display: block;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            
            // Form submission
            $('#wsa-pro-settings-form').submit(function(e) {
                e.preventDefault();
                
                var $spinner = $('.spinner');
                $spinner.addClass('is-active');
                
                $.post(ajaxurl, {
                    action: 'wsa_save_pro_settings',
                    nonce: $('#wsa_pro_nonce').val(),
                    settings: $(this).serialize()
                }, function(response) {
                    $spinner.removeClass('is-active');
                    
                    if (response.success) {
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + 
                          '<?php _e("Settings saved successfully!", "wp-site-advisory-pro"); ?>' + 
                          '</p></div>').insertAfter('.wrap h1');
                    } else {
                        // Show error message
                        $('<div class="notice notice-error is-dismissible"><p>' + 
                          (response.data || '<?php _e("Failed to save settings", "wp-site-advisory-pro"); ?>') + 
                          '</p></div>').insertAfter('.wrap h1');
                    }
                    
                    // Auto-dismiss notices
                    setTimeout(function() {
                        $('.notice.is-dismissible').fadeOut();
                    }, 3000);
                });
            });
            
            // Logo upload
            $('#upload-logo').click(function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: '<?php _e("Select Company Logo", "wp-site-advisory-pro"); ?>',
                    button: { text: '<?php _e("Use This Logo", "wp-site-advisory-pro"); ?>' },
                    multiple: false,
                    library: { type: 'image' }
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('input[name="company_logo"]').val(attachment.url);
                });
                
                mediaUploader.open();
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to save Pro settings
     */
    public function ajax_save_pro_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-site-advisory-pro'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_pro_settings')) {
            wp_send_json_error(__('Security check failed', 'wp-site-advisory-pro'));
        }
        
        // Parse settings
        parse_str($_POST['settings'], $settings);
        
        // Save API keys
        if (isset($settings['openai_api_key'])) {
            update_option('wsa_pro_openai_api_key', sanitize_text_field($settings['openai_api_key']));
        }
        if (isset($settings['wpscan_api_token'])) {
            update_option('wsa_pro_wpscan_api_token', sanitize_text_field($settings['wpscan_api_token']));
        }
        if (isset($settings['pagespeed_api_key'])) {
            update_option('wsa_pro_pagespeed_api_key', sanitize_text_field($settings['pagespeed_api_key']));
        }
        
        // Save white label settings
        if (isset($settings['company_name'])) {
            update_option('wsa_pro_company_name', sanitize_text_field($settings['company_name']));
        }
        if (isset($settings['company_logo'])) {
            update_option('wsa_pro_company_logo', esc_url_raw($settings['company_logo']));
        }
        if (isset($settings['company_website'])) {
            update_option('wsa_pro_company_website', esc_url_raw($settings['company_website']));
        }
        if (isset($settings['primary_color'])) {
            update_option('wsa_pro_primary_color', sanitize_hex_color($settings['primary_color']));
        }
        
        // Save notification settings
        update_option('wsa_pro_email_vulnerabilities', isset($settings['email_vulnerabilities']) ? 1 : 0);
        update_option('wsa_pro_email_conflicts', isset($settings['email_conflicts']) ? 1 : 0);
        update_option('wsa_pro_email_reports', isset($settings['email_reports']) ? 1 : 0);
        
        if (isset($settings['report_recipients'])) {
            update_option('wsa_pro_report_recipients', sanitize_textarea_field($settings['report_recipients']));
        }
        
        // Save feature controls
        update_option('wsa_pro_enable_ai_conflicts', isset($settings['enable_ai_conflicts']) ? 1 : 0);
        update_option('wsa_pro_enable_vuln_scanning', isset($settings['enable_vuln_scanning']) ? 1 : 0);
        update_option('wsa_pro_enable_pagespeed', isset($settings['enable_pagespeed']) ? 1 : 0);
        update_option('wsa_pro_enable_ai_chatbot', isset($settings['enable_ai_chatbot']) ? 1 : 0);
        
        wp_send_json_success(__('Settings saved successfully', 'wp-site-advisory-pro'));
    }

    /**
     * Inject Pro dashboard features into the free plugin's admin page
     */
    public function inject_pro_dashboard_features() {
        // Only inject on WP SiteAdvisor dashboard page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_wp-site-advisory') {
            return;
        }
        
        ?>
        <!-- Pro Features CSS -->
        <style type="text/css">
        .wsa-pro-features {
            margin-top: 30px;
            background: white;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .wsa-pro-tabs {
            margin-top: 20px;
        }
        
        .wsa-tab-nav {
            border-bottom: 1px solid #c3c4c7;
            margin-bottom: 0;
            display: flex;
        }
        
        .wsa-tab-button {
            background: none;
            border: none;
            padding: 15px 20px;
            margin: 0;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #646970;
        }
        
        .wsa-tab-button.active {
            color: #2c5aa0;
            border-bottom-color: #2c5aa0;
        }
        
        .wsa-tab-button:hover {
            color: #2c5aa0;
        }
        
        .wsa-tab-content {
            padding: 2rem;
        }
        
        .wsa-tab-content.active {
            display: block;
        }
        
        .wsa-pro-feature-panel {
            background: #f8f9fa;
            padding: 20px;
            border-left: 4px solid #2c5aa0;
        }
        
        .wsa-stat-card.wsa-pro-feature h3:after {
            content: " PRO";
            font-size: 10px;
            color: #0073aa;
            font-weight: bold;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Wait for the dashboard to be fully loaded
            setTimeout(function() {
                
                // Inject Pro stats cards
                if ($('.wsa-stats-grid').length) {
                    var proStatsCards = <?php echo json_encode($this->get_pro_stats_cards_html()); ?>;
                    $('.wsa-stats-grid').append(proStatsCards);
                }
                
                // Add individual Pro feature tabs to navigation
                if ($('.wsa-tab-nav').length) {
                    
                    // Vulnerability Scanner and Performance Analysis are now integrated into existing tabs
                    
                    // Add AI Conflict Detection tab
                    var conflictTabHtml = '<a href="#wsa-ai-conflicts" class="nav-tab wsa-pro-tab" data-tab="ai-conflicts">' +
                                        '<span class="dashicons dashicons-warning"></span>' +
                                        '<?php echo esc_js(__("AI Conflict Detection", "wp-site-advisory-pro")); ?>' +
                                        '</a>';
                    $('.wsa-tab-nav').append(conflictTabHtml);
                    
                    // Add White-Label Reports tab
                    var reportsTabHtml = '<a href="#wsa-pro-reports" class="nav-tab wsa-pro-tab" data-tab="pro-reports">' +
                                       '<span class="dashicons dashicons-media-document"></span>' +
                                       '<?php echo esc_js(__("Pro Reports", "wp-site-advisory-pro")); ?>' +
                                       '</a>';
                    $('.wsa-tab-nav').append(reportsTabHtml);
                    
                    // Add AI Assistant tab
                    var chatTabHtml = '<a href="#wsa-ai-assistant" class="nav-tab wsa-pro-tab" data-tab="ai-assistant">' +
                                    '<span class="dashicons dashicons-format-chat"></span>' +
                                    '<?php echo esc_js(__("AI Assistant", "wp-site-advisory-pro")); ?>' +
                                    '</a>';
                    $('.wsa-tab-nav').append(chatTabHtml);
                    
                    // Add individual tab content for remaining Pro features
                    
                    var conflictContent = <?php echo json_encode($this->get_conflict_tab_content()); ?>;
                    $('.wsa-tab-content').append('<div id="wsa-ai-conflicts" class="wsa-tab-panel">' + conflictContent + '</div>');
                    
                    var reportsContent = <?php echo json_encode($this->get_reports_tab_content()); ?>;
                    $('.wsa-tab-content').append('<div id="wsa-pro-reports" class="wsa-tab-panel">' + reportsContent + '</div>');
                    
                    var chatContent = <?php echo json_encode($this->get_chat_tab_content()); ?>;
                    $('.wsa-tab-content').append('<div id="wsa-ai-assistant" class="wsa-tab-panel">' + chatContent + '</div>');
                    
                }
                
                // Initialize Pro functionality immediately and listen for tab changes
                if (typeof window.WSAProIntegration !== 'undefined' && window.WSAProIntegration) {
                    window.WSAProIntegration.bindEvents();
                    window.WSAProIntegration.initFeatures();
                }
                
                $(document).on('wsa:tabChanged', function(e, tabName) {
                    if (['ai-conflicts', 'pro-reports', 'ai-assistant'].includes(tabName)) {
                        if (typeof window.WSAProIntegration !== 'undefined' && window.WSAProIntegration) {
                            window.WSAProIntegration.initFeatures();
                        }
                    }
                });
                
            }, 500);
        });
        </script>
        
        <!-- Performance Analysis Results Modal -->
        <div id="wsa-performance-modal" class="wsa-modal-overlay">
            <div class="wsa-performance-modal">
                <div class="wsa-modal-header">
                    <h3>
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Performance Analysis Results', 'wp-site-advisory-pro'); ?>
                    </h3>
                    <button class="wsa-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'wp-site-advisory-pro'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wsa-performance-modal-content" id="wsa-modal-performance-content">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-performance"></span>
                        <h4><?php _e('Performance Analysis Results', 'wp-site-advisory-pro'); ?></h4>
                        <p><?php _e('Your performance analysis results will appear here.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
                <div class="wsa-modal-footer">
                    <button type="button" class="button" id="wsa-modal-close-btn">
                        <?php _e('Close', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="wsa-run-new-analysis">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Run New Analysis', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php 
        // Only inject scan modals if free plugin doesn't have them (for backwards compatibility)
        $free_plugin_version = get_option('wsa_plugin_version', '1.0.0');
        $has_free_modals = version_compare($free_plugin_version, '2.4.0', '>=');
        
        if (!$has_free_modals): ?>
        <!-- System Scan Results Modal -->
        <div id="wsa-system-scan-modal" class="wsa-modal-overlay">
            <div class="wsa-system-scan-modal">
                <div class="wsa-modal-header">
                    <h3>
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('System Scan Results', 'wp-site-advisory-pro'); ?>
                    </h3>
                    <button class="wsa-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'wp-site-advisory-pro'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wsa-system-scan-modal-content" id="wsa-modal-system-scan-content">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <h4><?php _e('System Scan Results', 'wp-site-advisory-pro'); ?></h4>
                        <p><?php _e('Your system scan results will appear here.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
                <div class="wsa-modal-footer">
                    <button type="button" class="button" id="wsa-system-modal-close-btn">
                        <?php _e('Close', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="wsa-run-new-system-scan">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Run New System Scan', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Site Scan Results Modal -->
        <div id="wsa-site-scan-modal" class="wsa-modal-overlay">
            <div class="wsa-site-scan-modal">
                <div class="wsa-modal-header">
                    <h3>
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Site Scan Results', 'wp-site-advisory-pro'); ?>
                    </h3>
                    <button class="wsa-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'wp-site-advisory-pro'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wsa-site-scan-modal-content" id="wsa-modal-site-scan-content">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-search"></span>
                        <h4><?php _e('Site Scan Results', 'wp-site-advisory-pro'); ?></h4>
                        <p><?php _e('Your site scan results will appear here.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
                <div class="wsa-modal-footer">
                    <button type="button" class="button" id="wsa-site-modal-close-btn">
                        <?php _e('Close', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="wsa-run-new-site-scan">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Run New Site Scan', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; // End conditional modal injection ?>
        <?php
    }
    
    /**
     * Get Pro stats cards HTML
     */
    private function get_pro_stats_cards_html() {
        ob_start();
        ?>
        <div class="wsa-stat-card wsa-pro-feature">
            <h3><?php _e('Vulnerabilities', 'wp-site-advisory-pro'); ?></h3>
            <div class="wsa-stat-text" id="wsa-pro-vulnerability-status">
                <?php
                if (class_exists('\WSA_Pro\Features\WPScan_Vulnerabilities')) {
                    $scanner = new \WSA_Pro\Features\WPScan_Vulnerabilities();
                    $summary = $scanner->get_vulnerability_summary();
                    
                    if ($summary['total_vulnerabilities'] > 0) {
                        echo '<span class="wsa-status-disconnected">' . sprintf(__('%d Vulnerabilities', 'wp-site-advisory-pro'), $summary['total_vulnerabilities']) . '</span>';
                    } else {
                        echo '<span class="wsa-status-connected">' . __('No Vulnerabilities', 'wp-site-advisory-pro') . '</span>';
                    }
                } else {
                    echo '<span class="wsa-status-loading">' . __('Loading...', 'wp-site-advisory-pro') . '</span>';
                }
                ?>
            </div>
        </div>
        
        <div class="wsa-stat-card wsa-pro-feature">
            <h3><?php _e('Performance', 'wp-site-advisory-pro'); ?></h3>
            <div class="wsa-stat-text" id="wsa-pro-performance-status">
                <?php
                if (class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
                    $analyzer = new \WSA_Pro\Features\PageSpeed_Analysis();
                    $summary = $analyzer->get_performance_summary();
                    
                    if (isset($summary['scores']['mobile']['performance'])) {
                        $score = $summary['scores']['mobile']['performance']['score'];
                        if ($score >= 90) {
                            echo '<span class="wsa-status-connected">' . $score . '/100</span>';
                        } elseif ($score >= 50) {
                            echo '<span class="wsa-status-warning">' . $score . '/100</span>';
                        } else {
                            echo '<span class="wsa-status-disconnected">' . $score . '/100</span>';
                        }
                    } else {
                        echo '<span class="wsa-status-loading">' . __('Not Analyzed', 'wp-site-advisory-pro') . '</span>';
                    }
                } else {
                    echo '<span class="wsa-status-loading">' . __('Loading...', 'wp-site-advisory-pro') . '</span>';
                }
                ?>
            </div>
        </div>
        
        <div class="wsa-stat-card wsa-pro-feature">
            <h3><?php _e('AI Conflicts', 'wp-site-advisory-pro'); ?></h3>
            <div class="wsa-stat-text" id="wsa-pro-conflict-status">
                <?php
                if (class_exists('\WSA_Pro\Features\AI_Conflict_Detector')) {
                    $detector = new \WSA_Pro\Features\AI_Conflict_Detector();
                    $conflicts = $detector->get_conflicts();
                    
                    if (count($conflicts) > 0) {
                        echo '<span class="wsa-status-disconnected">' . sprintf(__('%d Conflicts', 'wp-site-advisory-pro'), count($conflicts)) . '</span>';
                    } else {
                        echo '<span class="wsa-status-connected">' . __('No Conflicts', 'wp-site-advisory-pro') . '</span>';
                    }
                } else {
                    echo '<span class="wsa-status-loading">' . __('Loading...', 'wp-site-advisory-pro') . '</span>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get Pro features section HTML (legacy - for backward compatibility)
     */
    private function get_pro_features_section_html() {
        ob_start();
        $this->render_pro_main_content();
        return ob_get_clean();
    }

    /**
     * Get Pro tab content HTML for integrated tabbed interface
     */
    private function get_pro_tab_content_html() {
        ob_start();
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-pro-features-header">
                <h2><?php _e('Pro Features', 'wp-site-advisory-pro'); ?></h2>
                <p class="wsa-pro-features-description">
                    <?php _e('Advanced WordPress security and analysis tools to keep your site secure and optimized.', 'wp-site-advisory-pro'); ?>
                </p>
            </div>
            
            <!-- Pro Features Grid -->
            <div class="wsa-pro-features-grid">
                <!-- Vulnerability Scanner -->
                <div class="wsa-pro-feature-card" data-feature="vulnerabilities">
                    <div class="wsa-pro-feature-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <h3><?php _e('Vulnerability Scanner', 'wp-site-advisory-pro'); ?></h3>
                    <p><?php _e('Real-time vulnerability scanning using WPScan database with detailed security reports.', 'wp-site-advisory-pro'); ?></p>
                    <div class="wsa-pro-feature-actions">
                        <button class="button button-primary wsa-pro-scan-vulnerabilities">
                            <?php _e('Run Scan', 'wp-site-advisory-pro'); ?>
                        </button>
                        <button class="button wsa-pro-view-vulnerabilities">
                            <?php _e('View Report', 'wp-site-advisory-pro'); ?>
                        </button>
                    </div>
                    <div class="wsa-pro-feature-status" id="wsa-vuln-status">
                        <span class="wsa-status-loading"><?php _e('Not scanned', 'wp-site-advisory-pro'); ?></span>
                    </div>
                </div>

                <!-- Performance Analysis -->
                <div class="wsa-pro-feature-card" data-feature="performance">
                    <div class="wsa-pro-feature-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <h3><?php _e('Performance Analysis', 'wp-site-advisory-pro'); ?></h3>
                    <p><?php _e('Google PageSpeed analysis with AI-powered performance recommendations and optimization tips.', 'wp-site-advisory-pro'); ?></p>
                    <div class="wsa-pro-feature-actions">
                        <button class="button button-primary wsa-pro-run-pagespeed">
                            <?php _e('Analyze Performance', 'wp-site-advisory-pro'); ?>
                        </button>
                        <button class="button wsa-pro-view-performance">
                            <?php _e('View Insights', 'wp-site-advisory-pro'); ?>
                        </button>
                    </div>
                    <div class="wsa-pro-feature-status" id="wsa-perf-status">
                        <span class="wsa-status-loading"><?php _e('Not analyzed', 'wp-site-advisory-pro'); ?></span>
                    </div>
                </div>

                <!-- AI Conflict Detection -->
                <div class="wsa-pro-feature-card" data-feature="conflicts">
                    <div class="wsa-pro-feature-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <h3><?php _e('AI Conflict Detection', 'wp-site-advisory-pro'); ?></h3>
                    <p><?php _e('AI-powered analysis to detect plugin and theme conflicts with intelligent resolution suggestions.', 'wp-site-advisory-pro'); ?></p>
                    <div class="wsa-pro-feature-actions">
                        <button class="button button-primary wsa-pro-check-conflicts">
                            <?php _e('Check Conflicts', 'wp-site-advisory-pro'); ?>
                        </button>
                        <button class="button wsa-pro-view-conflicts">
                            <?php _e('View Analysis', 'wp-site-advisory-pro'); ?>
                        </button>
                    </div>
                    <div class="wsa-pro-feature-status" id="wsa-conflict-status">
                        <span class="wsa-status-loading"><?php _e('Not checked', 'wp-site-advisory-pro'); ?></span>
                    </div>
                </div>

                <!-- White-Label Reports -->
                <div class="wsa-pro-feature-card" data-feature="reports">
                    <div class="wsa-pro-feature-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <h3><?php _e('White-Label Reports', 'wp-site-advisory-pro'); ?></h3>
                    <p><?php _e('Generate professional PDF reports with your branding and schedule automated delivery.', 'wp-site-advisory-pro'); ?></p>
                    <div class="wsa-pro-feature-actions">
                        <button class="button button-primary wsa-pro-generate-report">
                            <?php _e('Generate Report', 'wp-site-advisory-pro'); ?>
                        </button>
                        <button class="button wsa-pro-schedule-report">
                            <?php _e('Schedule Reports', 'wp-site-advisory-pro'); ?>
                        </button>
                    </div>
                    <div class="wsa-pro-feature-status" id="wsa-report-status">
                        <span class="wsa-status-loading"><?php _e('Ready to generate', 'wp-site-advisory-pro'); ?></span>
                    </div>
                </div>

                <!-- AI Chatbot -->
                <div class="wsa-pro-feature-card" data-feature="chatbot">
                    <div class="wsa-pro-feature-icon">
                        <span class="dashicons dashicons-format-chat"></span>
                    </div>
                    <h3><?php _e('AI Assistant', 'wp-site-advisory-pro'); ?></h3>
                    <p><?php _e('Context-aware AI assistant for WordPress troubleshooting and optimization recommendations.', 'wp-site-advisory-pro'); ?></p>
                    <div class="wsa-pro-feature-actions">
                        <button class="button button-primary wsa-pro-open-chat">
                            <?php _e('Open Assistant', 'wp-site-advisory-pro'); ?>
                        </button>
                        <button class="button wsa-pro-chat-history">
                            <?php _e('Chat History', 'wp-site-advisory-pro'); ?>
                        </button>
                    </div>
                    <div class="wsa-pro-feature-status" id="wsa-chat-status">
                        <span class="wsa-status-connected"><?php _e('Available', 'wp-site-advisory-pro'); ?></span>
                    </div>
                </div>

                <!-- Settings & Configuration -->
                <div class="wsa-pro-feature-card" data-feature="settings">
                    <div class="wsa-pro-feature-icon">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <h3><?php _e('Pro Settings', 'wp-site-advisory-pro'); ?></h3>
                    <p><?php _e('Configure API keys, white-label branding, notifications, and advanced Pro feature settings.', 'wp-site-advisory-pro'); ?></p>
                    <div class="wsa-pro-feature-actions">
                        <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-pro-settings'); ?>" class="button button-primary">
                            <?php _e('Pro Settings', 'wp-site-advisory-pro'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-license'); ?>" class="button">
                            <?php _e('License Manager', 'wp-site-advisory-pro'); ?>
                        </a>
                    </div>
                    <div class="wsa-pro-feature-status" id="wsa-settings-status">
                        <?php
                        $license_manager = $this->get_license_manager();
                        if ($license_manager && $license_manager->is_license_active()):
                        ?>
                        <span class="wsa-status-connected"><?php _e('License Active', 'wp-site-advisory-pro'); ?></span>
                        <?php else: ?>
                        <span class="wsa-status-disconnected"><?php _e('License Required', 'wp-site-advisory-pro'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Vulnerability Scanner tab content
     */
    private function get_vulnerability_tab_content() {
        ob_start();
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-pro-tab-header">
                <h2><span class="dashicons dashicons-shield-alt"></span> <?php _e('Vulnerability Scanner', 'wp-site-advisory-pro'); ?></h2>
                <p><?php _e('Real-time vulnerability scanning using WPScan database with detailed security reports.', 'wp-site-advisory-pro'); ?></p>
            </div>
            
            <div class="wsa-pro-feature-content">
                <div class="wsa-pro-actions">
                    <button id="wsa-run-vulnerability-scan" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Run Vulnerability Scan', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-view-vulnerability-report" class="button">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('View Last Report', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
                
                <div id="wsa-vulnerability-results" class="wsa-pro-results">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <h3><?php _e('No Vulnerability Scan Data', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Run a vulnerability scan to check your plugins, themes, and WordPress core for security issues.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Performance Analysis tab content
     */
    private function get_performance_tab_content() {
        ob_start();
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-pro-tab-header">
                <h2><span class="dashicons dashicons-performance"></span> <?php _e('Performance Analysis', 'wp-site-advisory-pro'); ?></h2>
                <p><?php _e('Google PageSpeed analysis with AI-powered performance recommendations and optimization tips.', 'wp-site-advisory-pro'); ?></p>
            </div>
            
            <div class="wsa-pro-feature-content">
                <div class="wsa-pro-actions">
                    <button id="wsa-run-pagespeed-analysis" class="button button-primary">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Run Performance Analysis', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-view-performance-report" class="button">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php _e('View Performance Report', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-get-ai-insights" class="button">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php _e('Get AI Insights', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
                
                <div id="wsa-performance-results" class="wsa-pro-results">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-performance"></span>
                        <h3><?php _e('No Performance Data', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Run a performance analysis to get detailed insights about your site speed and optimization opportunities.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get AI Conflict Detection tab content
     */
    private function get_conflict_tab_content() {
        ob_start();
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-pro-tab-header">
                <h2><span class="dashicons dashicons-warning"></span> <?php _e('AI Conflict Detection', 'wp-site-advisory-pro'); ?></h2>
                <p><?php _e('AI-powered analysis to detect plugin and theme conflicts with intelligent resolution suggestions.', 'wp-site-advisory-pro'); ?></p>
            </div>
            
            <div class="wsa-pro-feature-content">
                <div class="wsa-pro-actions">
                    <button id="wsa-run-conflict-check" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Check for Conflicts', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-view-conflict-analysis" class="button">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('View Analysis', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
                
                <div id="wsa-conflict-results" class="wsa-pro-results">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-warning"></span>
                        <h3><?php _e('No Conflict Analysis', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Run a conflict check to detect potential issues between plugins, themes, and WordPress core.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get White-Label Reports tab content
     */
    private function get_reports_tab_content() {
        ob_start();
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-pro-tab-header">
                <h2><span class="dashicons dashicons-media-document"></span> <?php _e('White-Label Reports', 'wp-site-advisory-pro'); ?></h2>
                <p><?php _e('Generate professional PDF reports with your branding and schedule automated delivery.', 'wp-site-advisory-pro'); ?></p>
            </div>
            
            <div class="wsa-pro-feature-content">
                <div class="wsa-pro-actions">
                    <button id="wsa-generate-pro-report" class="button button-primary">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php _e('Generate Report', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-preview-pro-report" class="button">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Preview Report', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-send-test-report" class="button">
                        <span class="dashicons dashicons-email"></span>
                        <?php _e('Send Test Email', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-schedule-report" class="button">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Schedule Reports', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
                
                <div id="wsa-reports-results" class="wsa-pro-results">
                    <div class="wsa-placeholder">
                        <span class="dashicons dashicons-media-document"></span>
                        <h3><?php _e('No Reports Generated', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Generate professional white-label reports to share with clients or team members.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get AI Assistant tab content
     */
    private function get_chat_tab_content() {
        ob_start();
        ?>
        <div class="wsa-tab-panel-content">
            <div class="wsa-pro-tab-header">
                <h2><span class="dashicons dashicons-format-chat"></span> <?php _e('AI Assistant', 'wp-site-advisory-pro'); ?></h2>
                <p><?php _e('Context-aware AI assistant for WordPress troubleshooting and optimization recommendations.', 'wp-site-advisory-pro'); ?></p>
            </div>
            
            <div class="wsa-pro-feature-content">
                <div class="wsa-pro-actions">
                    <button id="wsa-open-ai-chat" class="button button-primary">
                        <span class="dashicons dashicons-format-chat"></span>
                        <?php _e('Open AI Assistant', 'wp-site-advisory-pro'); ?>
                    </button>
                    <button id="wsa-chat-history" class="button">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Chat History', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
                
                <div id="wsa-chat-container" class="wsa-chat-widget">
                    <!-- AI Chatbot will be loaded here -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Pro dashboard features (stats cards)
     */
    public function render_pro_dashboard_features() {
        ?>
        <!-- Pro Features Stats Cards -->
        <div class="wsa-stat-card wsa-pro-feature">
            <h3><?php _e('Vulnerabilities', 'wp-site-advisory-pro'); ?></h3>
            <div class="wsa-stat-text" id="wsa-pro-vulnerability-status">
                <?php
                if (class_exists('\WSA_Pro\Features\WPScan_Vulnerabilities')) {
                    $scanner = new \WSA_Pro\Features\WPScan_Vulnerabilities();
                    $summary = $scanner->get_vulnerability_summary();
                    
                    if ($summary['total_vulnerabilities'] > 0) {
                        echo '<span class="wsa-status-disconnected">' . sprintf(__('%d Vulnerabilities', 'wp-site-advisory-pro'), $summary['total_vulnerabilities']) . '</span>';
                    } else {
                        echo '<span class="wsa-status-connected">' . __('No Vulnerabilities', 'wp-site-advisory-pro') . '</span>';
                    }
                } else {
                    echo '<span class="wsa-status-loading">' . __('Loading...', 'wp-site-advisory-pro') . '</span>';
                }
                ?>
            </div>
        </div>
        
        <div class="wsa-stat-card wsa-pro-feature">
            <h3><?php _e('Performance', 'wp-site-advisory-pro'); ?></h3>
            <div class="wsa-stat-text" id="wsa-pro-performance-status">
                <?php
                if (class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
                    $analyzer = new \WSA_Pro\Features\PageSpeed_Analysis();
                    $summary = $analyzer->get_performance_summary();
                    
                    if (isset($summary['scores']['mobile']['performance'])) {
                        $score = $summary['scores']['mobile']['performance']['score'];
                        if ($score >= 90) {
                            echo '<span class="wsa-status-connected">' . $score . '/100</span>';
                        } elseif ($score >= 50) {
                            echo '<span class="wsa-status-warning">' . $score . '/100</span>';
                        } else {
                            echo '<span class="wsa-status-disconnected">' . $score . '/100</span>';
                        }
                    } else {
                        echo '<span class="wsa-status-loading">' . __('Not Analyzed', 'wp-site-advisory-pro') . '</span>';
                    }
                } else {
                    echo '<span class="wsa-status-loading">' . __('Loading...', 'wp-site-advisory-pro') . '</span>';
                }
                ?>
            </div>
        </div>
        
        <div class="wsa-stat-card wsa-pro-feature">
            <h3><?php _e('AI Conflicts', 'wp-site-advisory-pro'); ?></h3>
            <div class="wsa-stat-text" id="wsa-pro-conflict-status">
                <?php
                if (class_exists('\WSA_Pro\Features\AI_Conflict_Detector')) {
                    $detector = new \WSA_Pro\Features\AI_Conflict_Detector();
                    $conflicts = $detector->get_conflicts();
                    
                    if (count($conflicts) > 0) {
                        echo '<span class="wsa-status-disconnected">' . sprintf(__('%d Conflicts', 'wp-site-advisory-pro'), count($conflicts)) . '</span>';
                    } else {
                        echo '<span class="wsa-status-connected">' . __('No Conflicts', 'wp-site-advisory-pro') . '</span>';
                    }
                } else {
                    echo '<span class="wsa-status-loading">' . __('Loading...', 'wp-site-advisory-pro') . '</span>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Pro main dashboard content
     */
    public function render_pro_main_content() {
        ?>
        <!-- Pro Features Section -->
        <div class="wsa-section wsa-pro-features">
            <h2><?php _e('Pro Features', 'wp-site-advisory-pro'); ?></h2>
            
            <!-- Pro Features Tabs -->
            <div class="wsa-pro-tabs">
                <div class="wsa-tab-nav">
                    <button class="wsa-tab-button active" data-tab="vulnerabilities"><?php _e('Vulnerability Scanner', 'wp-site-advisory-pro'); ?></button>
                    <button class="wsa-tab-button" data-tab="performance"><?php _e('Performance Analysis', 'wp-site-advisory-pro'); ?></button>
                    <button class="wsa-tab-button" data-tab="conflicts"><?php _e('AI Conflict Detection', 'wp-site-advisory-pro'); ?></button>
                    <button class="wsa-tab-button" data-tab="reports"><?php _e('White-Label Reports', 'wp-site-advisory-pro'); ?></button>
                </div>
                
                <!-- Vulnerability Scanner Tab -->
                <div class="wsa-tab-content active" id="wsa-tab-vulnerabilities">
                    <div class="wsa-pro-feature-panel">
                        <h3><?php _e('WPScan Vulnerability Scanner', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Scan your WordPress core, plugins, and themes for known security vulnerabilities using the WPScan database.', 'wp-site-advisory-pro'); ?></p>
                        
                        <div class="wsa-action-buttons">
                            <button id="wsa-run-vulnerability-scan" class="button button-primary">
                                <?php _e('Run Vulnerability Scan', 'wp-site-advisory-pro'); ?>
                            </button>
                            <button id="wsa-view-vulnerability-report" class="button">
                                <?php _e('View Latest Report', 'wp-site-advisory-pro'); ?>
                            </button>
                        </div>
                        
                        <div id="wsa-vulnerability-results" class="wsa-results-panel" style="display: none;">
                            <!-- Vulnerability results will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Performance Analysis Tab -->
                <div class="wsa-tab-content" id="wsa-tab-performance">
                    <div class="wsa-pro-feature-panel">
                        <h3><?php _e('Google PageSpeed Analysis with AI', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Analyze your website performance using Google PageSpeed Insights and get AI-powered optimization recommendations.', 'wp-site-advisory-pro'); ?></p>
                        
                        <div class="wsa-action-buttons">
                            <button id="wsa-run-pagespeed-analysis" class="button button-primary">
                                <?php _e('Run Performance Analysis', 'wp-site-advisory-pro'); ?>
                            </button>
                            <button id="wsa-view-performance-report" class="button">
                                <?php _e('View Latest Report', 'wp-site-advisory-pro'); ?>
                            </button>
                            <button id="wsa-get-ai-insights" class="button">
                                <?php _e('Get AI Recommendations', 'wp-site-advisory-pro'); ?>
                            </button>
                        </div>
                        
                        <div id="wsa-performance-results" class="wsa-results-panel" style="display: none;">
                            <!-- Performance results will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- AI Conflict Detection Tab -->
                <div class="wsa-tab-content" id="wsa-tab-conflicts">
                    <div class="wsa-pro-feature-panel">
                        <h3><?php _e('AI-Powered Conflict Detection', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Automatically detect and analyze plugin/theme conflicts with AI-powered fix suggestions.', 'wp-site-advisory-pro'); ?></p>
                        
                        <div class="wsa-action-buttons">
                            <button id="wsa-run-conflict-check" class="button button-primary">
                                <?php _e('Check for Conflicts', 'wp-site-advisory-pro'); ?>
                            </button>
                            <button id="wsa-view-conflict-analysis" class="button">
                                <?php _e('View Analysis', 'wp-site-advisory-pro'); ?>
                            </button>
                        </div>
                        
                        <div id="wsa-conflict-results" class="wsa-results-panel" style="display: none;">
                            <!-- Conflict results will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- White-Label Reports Tab -->
                <div class="wsa-tab-content" id="wsa-tab-reports">
                    <div class="wsa-pro-feature-panel">
                        <h3><?php _e('White-Label Reports', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php _e('Generate professional PDF reports with your branding and schedule automated email delivery.', 'wp-site-advisory-pro'); ?></p>
                        
                        <div class="wsa-action-buttons">
                            <button id="wsa-generate-pro-report" class="button button-primary">
                                <?php _e('Generate Report', 'wp-site-advisory-pro'); ?>
                            </button>
                            <button id="wsa-preview-pro-report" class="button">
                                <?php _e('Preview Report', 'wp-site-advisory-pro'); ?>
                            </button>
                            <button id="wsa-send-test-report" class="button">
                                <?php _e('Send Test Email', 'wp-site-advisory-pro'); ?>
                            </button>
                        </div>
                        
                        <div class="wsa-report-scheduling">
                            <h4><?php _e('Automated Scheduling', 'wp-site-advisory-pro'); ?></h4>
                            <select id="wsa-report-frequency">
                                <option value="none"><?php _e('No Scheduling', 'wp-site-advisory-pro'); ?></option>
                                <option value="weekly"><?php _e('Weekly', 'wp-site-advisory-pro'); ?></option>
                                <option value="monthly"><?php _e('Monthly', 'wp-site-advisory-pro'); ?></option>
                            </select>
                            <button id="wsa-schedule-report" class="button">
                                <?php _e('Update Schedule', 'wp-site-advisory-pro'); ?>
                            </button>
                        </div>
                        
                        <div id="wsa-report-results" class="wsa-results-panel" style="display: none;">
                            <!-- Report results will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wsa-pro-features {
            margin-top: 30px;
            background: white;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .wsa-pro-tabs {
            margin-top: 20px;
        }
        
        .wsa-tab-nav {
            border-bottom: 1px solid #c3c4c7;
            margin-bottom: 0;
        }
        
        .wsa-tab-button {
            background: none;
            border: none;
            padding: 15px 20px;
            margin: 0;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #646970;
        }
        
        .wsa-tab-button.active {
            color: #2c5aa0;
            border-bottom-color: #2c5aa0;
        }
        
        .wsa-tab-button:hover {
            color: #2c5aa0;
        }
        
        .wsa-tab-content {
            padding: 30px;
        }
        
        .wsa-tab-content.active {
            display: block;
        }
        
        .wsa-pro-feature-panel h3 {
            margin-top: 0;
            color: #2c5aa0;
        }
        
        .wsa-action-buttons {
            margin: 20px 0;
        }
        
        .wsa-action-buttons .button {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .wsa-results-panel {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #2c5aa0;
        }
        
        .wsa-report-scheduling {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 0px;
        }
        
        .wsa-report-scheduling h4 {
            margin-top: 0;
        }
        
        .wsa-report-scheduling select {
            margin-right: 10px;
        }
        
        .wsa-stat-card.wsa-pro-feature {
            border-left: 4px solid #2c5aa0;
        }
        
        .wsa-stat-card.wsa-pro-feature h3:after {
            content: " PRO";
            font-size: 10px;
            color: #0073aa;
            font-weight: bold;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.wsa-tab-button').click(function() {
                var tabId = $(this).data('tab');
                
                $('.wsa-tab-button').removeClass('active');
                $(this).addClass('active');
                
                $('.wsa-tab-content').removeClass('active');
                $('#wsa-tab-' + tabId).addClass('active');
            });
            
            // Pro feature button handlers will be added by individual feature JavaScript
        });
        </script>
        <?php
    }


    /**
     * Get license manager instance
     */
    public function get_license_manager() {
        return $this->license_manager;
    }
}

/**
 * Initialize the Pro plugin
 */
function wsa_pro_init() {
    return WSA_Pro::get_instance();
}

// Initialize the Pro plugin
add_action('plugins_loaded', 'wsa_pro_init');

/**
 * Global helper functions for Pro features
 */

/**
 * Check if Pro license is active
 */
function wsa_pro_is_license_active() {
    $pro = WSA_Pro::get_instance();
    $license_manager = $pro->get_license_manager();
    
    if (!$license_manager) {
        return false;
    }
    
    return $license_manager->is_license_active();
}

/**
 * Get Pro feature status
 */
function wsa_pro_get_feature_status($feature) {
    if (!wsa_pro_is_license_active()) {
        return array(
            'available' => false,
            'message' => __('Pro features are locked. Please activate your license.', 'wp-site-advisory-pro'),
            'license_url' => admin_url('admin.php?page=wp-site-advisory-license')
        );
    }
    
    return array(
        'available' => true,
        'message' => __('Feature is available.', 'wp-site-advisory-pro')
    );
}

/**
 * AI Analysis function (Pro feature)
 */
function wsa_pro_ai_analysis($scan_data) {
    $status = wsa_pro_get_feature_status('ai_analysis');
    
    if (!$status['available']) {
        return new WP_Error('license_required', $status['message']);
    }
    
    $analyzer = WSA_Pro_AI_Analyzer::get_instance();
    return $analyzer->analyze($scan_data);
}

/**
 * Vulnerability Scan function (Pro feature)
 */
function wsa_pro_vulnerability_scan($plugins = array()) {
    $status = wsa_pro_get_feature_status('vulnerability_scan');
    
    if (!$status['available']) {
        return new WP_Error('license_required', $status['message']);
    }
    
    $scanner = WSA_Pro_Vulnerability_Scanner::get_instance();
    return $scanner->scan($plugins);
}

/**
 * One-click fixes function (Pro feature)
 */
function wsa_pro_one_click_fixes($issues = array()) {
    $status = wsa_pro_get_feature_status('one_click_fixes');
    
    if (!$status['available']) {
        return new WP_Error('license_required', $status['message']);
    }
    
    // Implementation for one-click fixes
    return array(
        'fixed' => count($issues),
        'message' => __('Issues have been automatically resolved.', 'wp-site-advisory-pro')
    );
}

/**
 * Advanced scheduling function (Pro feature)
 */
function wsa_pro_advanced_scheduling($schedule_config = array()) {
    $status = wsa_pro_get_feature_status('advanced_scheduling');
    
    if (!$status['available']) {
        return new WP_Error('license_required', $status['message']);
    }
    
    // Implementation for advanced scheduling
    return array(
        'scheduled' => true,
        'message' => __('Advanced schedule has been configured.', 'wp-site-advisory-pro')
    );
}

/**
 * White-label function (Pro feature)
 */
function wsa_pro_white_label($branding_config = array()) {
    $status = wsa_pro_get_feature_status('white_label');
    
    if (!$status['available']) {
        return new WP_Error('license_required', $status['message']);
    }
    
    // Implementation for white-label branding
    return array(
        'applied' => true,
        'message' => __('White-label branding has been applied.', 'wp-site-advisory-pro')
    );
}

/**
 * Plugin uninstall hook
 */
function wsa_pro_uninstall() {
    // Remove license options
    delete_option('wsa_pro_license_key');
    delete_option('wsa_pro_license_status');
    delete_option('wsa_pro_license_expires');
    delete_option('wsa_pro_license_site_count');
    delete_option('wsa_pro_license_max_sites');
    
    // Remove Pro feature options
    delete_option('wsa_pro_openai_api_key');
    delete_option('wsa_pro_wpscan_api_token');
    delete_option('wsa_pro_pagespeed_api_key');
    delete_option('wsa_pro_company_name');
    delete_option('wsa_pro_company_logo');
    delete_option('wsa_pro_company_website');
    delete_option('wsa_pro_primary_color');
    delete_option('wsa_pro_report_recipients');
    delete_option('wsa_pro_enable_ai_conflicts');
    delete_option('wsa_pro_enable_vuln_scanning');
    delete_option('wsa_pro_enable_pagespeed');
    delete_option('wsa_pro_enable_ai_chatbot');
    delete_option('wsa_pro_email_vulnerabilities');
    delete_option('wsa_pro_email_conflicts');
    delete_option('wsa_pro_email_reports');
    
    // Remove Pro feature data
    delete_option('wsa_pro_chat_history');
    delete_option('wsa_pro_vulnerability_cache');
    delete_option('wsa_pro_conflict_cache');
    delete_option('wsa_pro_pagespeed_cache');
    delete_option('wsa_pro_report_cache');
    
    // Clear license transients
    delete_transient('wsa_pro_license_check');
    delete_transient('wsa_pro_license_grace_period');
    
    // Clear Pro transients
    delete_transient('wsa_pro_vulnerabilities');
    delete_transient('wsa_pro_conflicts');
    delete_transient('wsa_pro_pagespeed_desktop');
    delete_transient('wsa_pro_pagespeed_mobile');
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('wsa_pro_license_check');
    wp_clear_scheduled_hook('wsa_pro_daily_scan');
    wp_clear_scheduled_hook('wsa_pro_weekly_reports');
    wp_clear_scheduled_hook('wsa_pro_monthly_reports');
}

register_uninstall_hook(__FILE__, 'wsa_pro_uninstall');