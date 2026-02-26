<?php
/**
 * Unified Settings Class
 * Consolidates Free, Pro, and AI settings into one interface
 *
 * @package WP_Site_Advisory_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Unified_Settings {
    
    /**
     * Settings key
     */
    private $settings_key = 'wsa_unified_settings';
    
    /**
     * Default settings
     */
    private $default_settings = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_default_settings();
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_unified_settings_menu'), 20);
        add_action('wp_ajax_wsa_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_wsa_refresh_usage_pro', array($this, 'ajax_refresh_usage'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Migrate existing settings on first load
        add_action('init', array($this, 'migrate_existing_settings'), 5);
    }
    
    /**
     * Initialize default settings
     */
    private function init_default_settings() {
        $this->default_settings = array(
            // === CORE API CONFIGURATION ===
            'openai_api_key' => '',
            'openai_model' => 'gpt-4',
            'openai_temperature' => 0.3,
            'openai_max_tokens' => 1500,
            
            // === FREE PLUGIN SETTINGS ===
            // Scan Settings
            'scan_frequency' => 'daily',
            'scan_depth' => 'full',
            'auto_fix_enabled' => false,
            'scan_types' => array(
                'security' => true,
                'performance' => true,
                'seo' => true,
                'accessibility' => false
            ),
            
            // Notification Settings (Free)
            'email_notifications' => true,
            'notification_email' => '',
            'notification_frequency' => 'weekly',
            'critical_only' => false,
            
            // === PRO SETTINGS ===
            // White Label Settings
            'white_label_enabled' => false,
            'company_name' => '',
            'company_logo' => '',
            'brand_color' => '#0073aa',
            'hide_wp_site_advisory_branding' => false,
            
            // Pro API Keys
            'pro_openai_api_key' => '',
            'google_pagespeed_api_key' => '',
            'screaming_frog_api_key' => '',
            
            // Pro Features
            'automated_reports' => true,
            'white_label_reports' => false,
            'advanced_analytics' => true,
            'priority_support' => true,
            
            // === AI SETTINGS ===
            // Feature Toggles
            'enable_automated_optimizer' => true,
            'enable_predictive_analytics' => true,
            'enable_content_analyzer' => true,
            'enable_ai_chatbot' => true,
            'enable_site_detective' => true,
            
            // Automated Optimizer Settings
            'optimizer_auto_run' => false,
            'optimizer_schedule' => 'weekly',
            'optimizer_tasks' => array(
                'database_cleanup' => true,
                'image_optimization' => false,
                'cache_optimization' => true,
                'css_js_optimization' => false,
                'font_optimization' => true
            ),
            'optimizer_risk_level' => 'medium',
            'optimizer_notifications' => true,
            
            // Predictive Analytics Settings
            'analytics_data_retention' => 90,
            'analytics_prediction_window' => 30,
            'analytics_confidence_threshold' => 0.75,
            'analytics_auto_alerts' => true,
            'analytics_alert_thresholds' => array(
                'performance_degradation' => 20,
                'traffic_drop' => 25,
                'security_risk_increase' => 15
            ),
            
            // Content Analyzer Settings
            'analyzer_auto_analyze' => true,
            'analyzer_real_time_suggestions' => true,
            'analyzer_seo_focus' => true,
            'analyzer_accessibility_level' => 'aa',
            'analyzer_duplicate_check_external' => false,
            'analyzer_min_content_score' => 70,
            'analyzer_post_types' => array('post', 'page'),
            'analyzer_seo_depth' => 'comprehensive',
            
            // AI Behavior Settings
            'ai_response_style' => 'professional',
            'ai_verbosity' => 'balanced',
            'ai_include_explanations' => true,
            'ai_cache_responses' => true,
            'ai_cache_duration' => 3600,
            'ai_confidence_threshold' => 0.8,
            'ai_learning_enabled' => true,
            
            // Advanced Settings
            'ai_fallback_enabled' => true,
            'debug_mode' => false,
            'rate_limiting_enabled' => true,
            'max_requests_per_hour' => 100,
            'api_timeout' => 30,
            'enable_logging' => false,
            'cache_ai_responses' => true
        );
        
        // Set default email if not set
        if (empty($this->default_settings['notification_email'])) {
            $this->default_settings['notification_email'] = get_option('admin_email');
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wsa_unified_settings_group', $this->settings_key, array($this, 'sanitize_settings'));
    }
    
    /**
     * Add unified settings menu
     */
    public function add_unified_settings_menu() {
        // Remove existing settings pages
        remove_submenu_page('wp-site-advisory', 'wp-site-advisory-settings');
        remove_submenu_page('wp-site-advisory', 'wp-site-advisory-pro-settings');
        remove_submenu_page('wp-site-advisory', 'wp-site-advisory-ai-settings');
        
        // Add unified settings page
        add_submenu_page(
            'wp-site-advisory',
            __('Settings', 'wp-site-advisory-pro'),
            __('Settings', 'wp-site-advisory-pro'),
            'manage_options',
            'wp-site-advisory-unified-settings',
            array($this, 'render_unified_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-site-advisory-unified-settings') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_media();
    }
    
    /**
     * Get settings with defaults
     */
    public function get_settings() {
        $settings = get_option($this->settings_key, array());
        return wp_parse_args($settings, $this->default_settings);
    }
    
    /**
     * Get actual API key from various sources
     */
    private function get_actual_api_key() {
        $settings = $this->get_settings();
        
        // Check unified settings first
        if (!empty($settings['openai_api_key'])) {
            return $settings['openai_api_key'];
        }
        
        // Check free plugin setting
        $free_key = get_option('wsa_openai_api_key');
        if (!empty($free_key)) {
            return $free_key;
        }
        
        // Check pro plugin setting
        $pro_settings = get_option('wsa_pro_settings', array());
        if (!empty($pro_settings['openai_api_key'])) {
            return $pro_settings['openai_api_key'];
        }
        
        return '';
    }
    
    /**
     * Mask API key for display
     */
    private function mask_api_key($key) {
        if (empty($key)) {
            return '';
        }
        
        $key_length = strlen($key);
        if ($key_length <= 8) {
            return str_repeat('*', $key_length);
        }
        
        // Show first 3 characters + fixed 12 asterisks + last 4 characters
        // This gives a consistent display length regardless of actual key length
        return substr($key, 0, 3) . str_repeat('*', 12) . substr($key, -4);
    }
    
    /**
     * Get API key display value
     */
    private function get_api_key_display_value() {
        $actual_key = $this->get_actual_api_key();
        return $this->mask_api_key($actual_key);
    }
    
    /**
     * Render unified settings page
     */
    public function render_unified_settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <?php
            // Render branded header - check both Free and Pro branding class
            if (class_exists('WP_Site_Advisory_Branding')) {
                WP_Site_Advisory_Branding::render_admin_header('All Settings');
            } elseif (class_exists('WP_Site_Advisory_Pro_Branding')) {
                WP_Site_Advisory_Pro_Branding::render_admin_header('All Settings');
            } else {
                ?>
                <h1><?php echo esc_html__('WP Site Advisory - All Settings', 'wp-site-advisory-pro'); ?></h1>
                <?php
            }
            ?>
            
            <div class="wsa-unified-settings-container">
                <form id="wsa-unified-settings-form" method="post" action="options.php">
                    <?php
                    settings_fields('wsa_unified_settings_group');
                    wp_nonce_field('wsa_unified_settings_nonce', 'wsa_unified_settings_nonce');
                    ?>
                    
                    <div class="wsa-settings-tabs">
                        <nav class="nav-tab-wrapper">
                            <a href="#core-api" class="nav-tab nav-tab-active"><?php _e('Core & API', 'wp-site-advisory-pro'); ?></a>
                            <a href="#scanning" class="nav-tab"><?php _e('Scanning', 'wp-site-advisory-pro'); ?></a>
                            <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'wp-site-advisory-pro'); ?></a>
                            <a href="#white-label" class="nav-tab"><?php _e('White Label', 'wp-site-advisory-pro'); ?></a>
                            <a href="#ai-features" class="nav-tab"><?php _e('AI Features', 'wp-site-advisory-pro'); ?></a>
                            <a href="#ai-automation" class="nav-tab"><?php _e('AI Automation', 'wp-site-advisory-pro'); ?></a>
                            <a href="#ai-analytics" class="nav-tab"><?php _e('AI Analytics', 'wp-site-advisory-pro'); ?></a>
                            <a href="#ai-content" class="nav-tab"><?php _e('Content Analysis', 'wp-site-advisory-pro'); ?></a>
                            <a href="#ai-behavior" class="nav-tab"><?php _e('AI Behavior', 'wp-site-advisory-pro'); ?></a>
                            <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'wp-site-advisory-pro'); ?></a>
                        </nav>
                        
                        <!-- Core & API Tab -->
                        <div id="core-api" class="tab-content active">
                            <h3><?php _e('Core API Configuration', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('OpenAI API Key', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <?php
                                        $actual_key = $this->get_actual_api_key();
                                        $display_value = $this->get_api_key_display_value();
                                        $has_key = !empty($actual_key);
                                        ?>
                                        <div class="api-key-container">
                                            <input type="password" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[openai_api_key]" 
                                                   id="openai_api_key"
                                                   value="<?php echo esc_attr($settings['openai_api_key']); ?>" 
                                                   placeholder="<?php echo $has_key ? esc_attr($display_value) : esc_attr__('Enter your OpenAI API key...', 'wp-site-advisory-pro'); ?>"
                                                   class="regular-text" />
                                            <button type="button" class="button button-secondary" id="test-api-connection">
                                                <?php _e('Test Connection', 'wp-site-advisory-pro'); ?>
                                            </button>
                                            <?php if ($has_key): ?>
                                                <button type="button" class="button button-secondary" id="clear-api-key">
                                                    <?php _e('Clear Key', 'wp-site-advisory-pro'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($has_key): ?>
                                            <div class="api-key-status">
                                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                <strong><?php _e('API Key Configured:', 'wp-site-advisory-pro'); ?></strong>
                                                <code><?php echo esc_html($display_value); ?></code>
                                                <?php
                                                // Show source of the API key
                                                if (!empty($settings['openai_api_key'])) {
                                                    echo '<em>(' . __('from unified settings', 'wp-site-advisory-pro') . ')</em>';
                                                } elseif (get_option('wsa_openai_api_key')) {
                                                    echo '<em>(' . __('from free plugin', 'wp-site-advisory-pro') . ')</em>';
                                                } elseif (!empty(get_option('wsa_pro_settings', array())['openai_api_key'])) {
                                                    echo '<em>(' . __('from pro plugin', 'wp-site-advisory-pro') . ')</em>';
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <p class="description">
                                            <?php _e('Required for all AI features. Get your API key from OpenAI.', 'wp-site-advisory-pro'); ?>
                                            <?php if ($has_key): ?>
                                                <br><strong><?php _e('Note:', 'wp-site-advisory-pro'); ?></strong> 
                                                <?php _e('Leave empty to keep existing key, or enter a new key to replace it.', 'wp-site-advisory-pro'); ?>
                                            <?php endif; ?>
                                        </p>
                                        <div id="api-test-result"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('OpenAI Model', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[openai_model]" class="regular-text">
                                            <option value="gpt-4" <?php selected($settings['openai_model'], 'gpt-4'); ?>>GPT-4</option>
                                            <option value="gpt-4-turbo" <?php selected($settings['openai_model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                            <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                        </select>
                                        <p class="description"><?php _e('Choose the AI model for analysis and recommendations.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('AI Temperature', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="range" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[openai_temperature]" 
                                               id="openai_temperature"
                                               min="0" max="1" step="0.1" 
                                               value="<?php echo esc_attr($settings['openai_temperature']); ?>" />
                                        <span id="temperature-value"><?php echo esc_html($settings['openai_temperature']); ?></span>
                                        <p class="description"><?php _e('Controls AI response creativity. Lower = more focused, Higher = more creative.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Max Tokens', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="number" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[openai_max_tokens]" 
                                               value="<?php echo esc_attr($settings['openai_max_tokens']); ?>" 
                                               min="100" max="4000" class="small-text" />
                                        <p class="description"><?php _e('Maximum response length from AI (100-4000 tokens).', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                
                                <h3><?php _e('Additional API Keys (Pro Features)', 'wp-site-advisory-pro'); ?></h3>
                                <tr>
                                    <th scope="row"><?php _e('Google PageSpeed API Key', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <?php 
                                        $pagespeed_key = $settings['google_pagespeed_api_key'];
                                        $has_pagespeed_key = !empty($pagespeed_key);
                                        $pagespeed_display = $has_pagespeed_key ? $this->mask_api_key($pagespeed_key) : '';
                                        ?>
                                        <input type="password" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[google_pagespeed_api_key]" 
                                               value="<?php echo esc_attr($settings['google_pagespeed_api_key']); ?>" 
                                               placeholder="<?php echo $has_pagespeed_key ? esc_attr($pagespeed_display) : esc_attr__('Enter Google PageSpeed API key (optional)...', 'wp-site-advisory-pro'); ?>"
                                               class="regular-text" />
                                        <?php if ($has_pagespeed_key): ?>
                                            <div class="api-key-status" style="margin-top: 5px;">
                                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                <strong><?php _e('PageSpeed API Key:', 'wp-site-advisory-pro'); ?></strong>
                                                <code><?php echo esc_html($pagespeed_display); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <p class="description">
                                            <?php _e('Optional: For enhanced PageSpeed analysis.', 'wp-site-advisory-pro'); ?>
                                            <?php if ($has_pagespeed_key): ?>
                                                <br><?php _e('Leave empty to keep existing key.', 'wp-site-advisory-pro'); ?>
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php $this->display_openai_usage_dashboard(); ?>
                        </div>
                        
                        <!-- Scanning Tab -->
                        <div id="scanning" class="tab-content">
                            <h3><?php _e('Scan Configuration', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Scan Frequency', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[scan_frequency]">
                                            <option value="daily" <?php selected($settings['scan_frequency'], 'daily'); ?>><?php _e('Daily', 'wp-site-advisory-pro'); ?></option>
                                            <option value="weekly" <?php selected($settings['scan_frequency'], 'weekly'); ?>><?php _e('Weekly', 'wp-site-advisory-pro'); ?></option>
                                            <option value="monthly" <?php selected($settings['scan_frequency'], 'monthly'); ?>><?php _e('Monthly', 'wp-site-advisory-pro'); ?></option>
                                            <option value="manual" <?php selected($settings['scan_frequency'], 'manual'); ?>><?php _e('Manual Only', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Scan Depth', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[scan_depth]">
                                            <option value="quick" <?php selected($settings['scan_depth'], 'quick'); ?>><?php _e('Quick Scan', 'wp-site-advisory-pro'); ?></option>
                                            <option value="full" <?php selected($settings['scan_depth'], 'full'); ?>><?php _e('Full Scan', 'wp-site-advisory-pro'); ?></option>
                                            <option value="deep" <?php selected($settings['scan_depth'], 'deep'); ?>><?php _e('Deep Scan (Pro)', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Auto-Fix Issues', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[auto_fix_enabled]" 
                                                   value="1" 
                                                   <?php checked($settings['auto_fix_enabled']); ?> />
                                            <?php _e('Automatically fix simple issues when possible', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Only applies to safe, reversible fixes.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Scan Types', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <?php
                                        $scan_types = array(
                                            'security' => __('Security Issues', 'wp-site-advisory-pro'),
                                            'performance' => __('Performance Issues', 'wp-site-advisory-pro'),
                                            'seo' => __('SEO Issues', 'wp-site-advisory-pro'),
                                            'accessibility' => __('Accessibility Issues (Pro)', 'wp-site-advisory-pro')
                                        );
                                        foreach ($scan_types as $type => $label): 
                                        ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[scan_types][<?php echo esc_attr($type); ?>]" 
                                                   value="1" 
                                                   <?php checked(!empty($settings['scan_types'][$type])); ?> />
                                            <?php echo esc_html($label); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Notifications Tab -->
                        <div id="notifications" class="tab-content">
                            <h3><?php _e('Notification Settings', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Enable Email Notifications', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[email_notifications]" 
                                                   value="1" 
                                                   <?php checked($settings['email_notifications']); ?> />
                                            <?php _e('Send email notifications for issues and updates', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Notification Email', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="email" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[notification_email]" 
                                               value="<?php echo esc_attr($settings['notification_email']); ?>" 
                                               class="regular-text" />
                                        <p class="description"><?php _e('Email address to receive notifications.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Notification Frequency', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[notification_frequency]">
                                            <option value="immediately" <?php selected($settings['notification_frequency'], 'immediately'); ?>><?php _e('Immediately', 'wp-site-advisory-pro'); ?></option>
                                            <option value="daily" <?php selected($settings['notification_frequency'], 'daily'); ?>><?php _e('Daily Summary', 'wp-site-advisory-pro'); ?></option>
                                            <option value="weekly" <?php selected($settings['notification_frequency'], 'weekly'); ?>><?php _e('Weekly Summary', 'wp-site-advisory-pro'); ?></option>
                                            <option value="monthly" <?php selected($settings['notification_frequency'], 'monthly'); ?>><?php _e('Monthly Summary', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Critical Issues Only', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[critical_only]" 
                                                   value="1" 
                                                   <?php checked($settings['critical_only']); ?> />
                                            <?php _e('Only notify about critical security and performance issues', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- White Label Tab -->
                        <div id="white-label" class="tab-content">
                            <h3><?php _e('White Label Settings (Pro)', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Enable White Labeling', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[white_label_enabled]" 
                                                   value="1" 
                                                   <?php checked($settings['white_label_enabled']); ?> />
                                            <?php _e('Replace WP Site Advisory branding with your own', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Company Name', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="text" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[company_name]" 
                                               value="<?php echo esc_attr($settings['company_name']); ?>" 
                                               class="regular-text" />
                                        <p class="description"><?php _e('Your company name to display in reports and interface.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Company Logo', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="text" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[company_logo]" 
                                               id="company_logo_url"
                                               value="<?php echo esc_attr($settings['company_logo']); ?>" 
                                               class="regular-text" />
                                        <button type="button" class="button" id="upload_logo_button">
                                            <?php _e('Upload Logo', 'wp-site-advisory-pro'); ?>
                                        </button>
                                        <p class="description"><?php _e('Logo to display in reports and interface (recommended: 200x50px).', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Brand Color', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="color" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[brand_color]" 
                                               value="<?php echo esc_attr($settings['brand_color']); ?>" />
                                        <p class="description"><?php _e('Primary color for your brand in reports and interface.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Hide WP Site Advisory Branding', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[hide_wp_site_advisory_branding]" 
                                                   value="1" 
                                                   <?php checked($settings['hide_wp_site_advisory_branding']); ?> />
                                            <?php _e('Completely remove WP Site Advisory references', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- AI Features Tab -->
                        <div id="ai-features" class="tab-content">
                            <h3><?php _e('AI Feature Toggles', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Enable Automated Optimizer', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[enable_automated_optimizer]" 
                                                   value="1" 
                                                   <?php checked($settings['enable_automated_optimizer']); ?> />
                                            <?php _e('AI-powered website optimization and cleanup', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Automatically optimize database, images, and performance.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Enable Predictive Analytics', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[enable_predictive_analytics]" 
                                                   value="1" 
                                                   <?php checked($settings['enable_predictive_analytics']); ?> />
                                            <?php _e('AI predictions for performance and traffic trends', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Predict issues before they occur and recommend preventive actions.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Enable Content Analyzer', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[enable_content_analyzer]" 
                                                   value="1" 
                                                   <?php checked($settings['enable_content_analyzer']); ?> />
                                            <?php _e('AI-powered content analysis and SEO suggestions', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Analyze content quality, SEO optimization, and readability.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Enable AI Chatbot', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[enable_ai_chatbot]" 
                                                   value="1" 
                                                   <?php checked($settings['enable_ai_chatbot']); ?> />
                                            <?php _e('Interactive AI assistant for website analysis', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Chat with AI about your website issues and get instant help.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Enable Site Detective', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[enable_site_detective]" 
                                                   value="1" 
                                                   <?php checked($settings['enable_site_detective']); ?> />
                                            <?php _e('AI investigation of complex website issues', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Deep AI analysis to identify root causes of problems.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- AI Automation Tab -->
                        <div id="ai-automation" class="tab-content">
                            <h3><?php _e('AI Automation Settings', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Auto-Run Optimizer', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[optimizer_auto_run]" 
                                                   value="1" 
                                                   <?php checked($settings['optimizer_auto_run']); ?> />
                                            <?php _e('Automatically run optimization tasks on schedule', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Risk Level', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[optimizer_risk_level]">
                                            <option value="conservative" <?php selected($settings['optimizer_risk_level'], 'conservative'); ?>><?php _e('Conservative (Safe only)', 'wp-site-advisory-pro'); ?></option>
                                            <option value="medium" <?php selected($settings['optimizer_risk_level'], 'medium'); ?>><?php _e('Medium (Balanced)', 'wp-site-advisory-pro'); ?></option>
                                            <option value="aggressive" <?php selected($settings['optimizer_risk_level'], 'aggressive'); ?>><?php _e('Aggressive (Maximum optimization)', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- AI Analytics Tab -->
                        <div id="ai-analytics" class="tab-content">
                            <h3><?php _e('AI Analytics Settings', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Data Retention (days)', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <input type="number" 
                                               name="<?php echo esc_attr($this->settings_key); ?>[analytics_data_retention]" 
                                               value="<?php echo esc_attr($settings['analytics_data_retention']); ?>" 
                                               min="30" max="365" class="small-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Auto Alerts', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[analytics_auto_alerts]" 
                                                   value="1" 
                                                   <?php checked($settings['analytics_auto_alerts']); ?> />
                                            <?php _e('Automatically send alerts when predictions meet thresholds', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Content Analysis Tab -->
                        <div id="ai-content" class="tab-content">
                            <h3><?php _e('Content Analysis Settings', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Auto-Analyze New Content', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[analyzer_auto_analyze]" 
                                                   value="1" 
                                                   <?php checked($settings['analyzer_auto_analyze']); ?> />
                                            <?php _e('Automatically analyze content when published or updated', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('SEO Analysis Depth', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[analyzer_seo_depth]">
                                            <option value="basic" <?php selected($settings['analyzer_seo_depth'], 'basic'); ?>><?php _e('Basic SEO Check', 'wp-site-advisory-pro'); ?></option>
                                            <option value="comprehensive" <?php selected($settings['analyzer_seo_depth'], 'comprehensive'); ?>><?php _e('Comprehensive Analysis', 'wp-site-advisory-pro'); ?></option>
                                            <option value="expert" <?php selected($settings['analyzer_seo_depth'], 'expert'); ?>><?php _e('Expert Level Analysis', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- AI Behavior Tab -->
                        <div id="ai-behavior" class="tab-content">
                            <h3><?php _e('AI Behavior Settings', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Response Style', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr($this->settings_key); ?>[ai_response_style]">
                                            <option value="professional" <?php selected($settings['ai_response_style'], 'professional'); ?>><?php _e('Professional', 'wp-site-advisory-pro'); ?></option>
                                            <option value="friendly" <?php selected($settings['ai_response_style'], 'friendly'); ?>><?php _e('Friendly', 'wp-site-advisory-pro'); ?></option>
                                            <option value="technical" <?php selected($settings['ai_response_style'], 'technical'); ?>><?php _e('Technical', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Cache AI Responses', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[ai_cache_responses]" 
                                                   value="1" 
                                                   <?php checked($settings['ai_cache_responses']); ?> />
                                            <?php _e('Cache AI responses to improve performance', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Advanced Tab -->
                        <div id="advanced" class="tab-content">
                            <h3><?php _e('Advanced Settings', 'wp-site-advisory-pro'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Debug Mode', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[debug_mode]" 
                                                   value="1" 
                                                   <?php checked($settings['debug_mode']); ?> />
                                            <?php _e('Enable debug logging and detailed error reporting', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Rate Limiting', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->settings_key); ?>[rate_limiting_enabled]" 
                                                   value="1" 
                                                   <?php checked($settings['rate_limiting_enabled']); ?> />
                                            <?php _e('Enable rate limiting for API calls', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button-primary" 
                               value="<?php esc_attr_e('Save All Settings', 'wp-site-advisory-pro'); ?>" />
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .wsa-unified-settings-container {
            max-width: 1200px;
        }
        
        .tab-content {
            display: none;
            background: #fff;
            padding: 20px;
            border: 1px solid #c3c4c7;
            border-top: 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        #api-test-result {
            padding: 8px 12px;
            border-radius: 4px;
            display: none;
            margin-top: 10px;
        }
        
        #api-test-result.success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #a3cfbb;
        }
        
        #api-test-result.error {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        
        .api-key-container {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .api-key-status {
            background: #f0f6fc;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #c3c4c7;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .api-key-status code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Monaco, 'Lucida Console', monospace;
        }
        
        .api-key-status em {
            color: #666;
            font-size: 0.9em;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            
            // Temperature slider
            $('#openai_temperature').on('input', function() {
                $('#temperature-value').text($(this).val());
            });
            
            // Test API connection
            $('#test-api-connection').on('click', function() {
                var apiKey = $('#openai_api_key').val();
                
                // If no key entered, test with existing key
                if (!apiKey) {
                    // Use existing key for testing
                    apiKey = 'use_existing_key';
                }
                
                $(this).prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsa_test_api_connection',
                        api_key: apiKey,
                        nonce: $('#wsa_unified_settings_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#api-test-result').removeClass('error').addClass('success')
                                .text('✓ API connection successful!')
                                .show();
                        } else {
                            $('#api-test-result').removeClass('success').addClass('error')
                                .text('✗ ' + (response.data || 'Connection failed'))
                                .show();
                        }
                    },
                    complete: function() {
                        $('#test-api-connection').prop('disabled', false).text('Test Connection');
                    }
                });
            });
            
            // Clear API key
            $('#clear-api-key').on('click', function() {
                if (confirm('Are you sure you want to clear the API key? This will disable AI features until a new key is entered.')) {
                    $('#openai_api_key').val('').attr('placeholder', 'Enter your OpenAI API key...');
                    $('.api-key-status').hide();
                    $('#api-test-result').hide();
                }
            });
            
            // Refresh OpenAI usage data (Pro)
            $('#wsa-refresh-usage-pro').on('click', function() {
                var $button = $(this);
                var $dashboard = $('.wsa-usage-dashboard');
                
                // Show loading state
                $button.addClass('refreshing').prop('disabled', true);
                $button.find('.dashicons').addClass('wsa-spin');
                
                // Add loading notice
                var loadingNotice = '<div class="wsa-usage-success wsa-loading-notice"><span class="dashicons dashicons-update wsa-spin"></span>Refreshing usage data...</div>';
                $dashboard.prepend(loadingNotice);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsa_refresh_usage_pro',
                        nonce: $('#wsa_unified_settings_nonce').val()
                    },
                    success: function(response) {
                        // Remove loading notice
                        $('.wsa-loading-notice').remove();
                        
                        if (response.success) {
                            // Show success notice
                            var successNotice = '<div class="wsa-usage-success wsa-notice-dismissible"><span class="dashicons dashicons-yes-alt"></span>Usage data refreshed successfully!</div>';
                            $dashboard.prepend(successNotice);
                            
                            // Auto-dismiss after 3 seconds
                            setTimeout(function() {
                                $('.wsa-notice-dismissible').addClass('wsa-notice-fadeout');
                                setTimeout(function() {
                                    $('.wsa-notice-dismissible').remove();
                                    // Reload the page to show updated data
                                    window.location.reload();
                                }, 500);
                            }, 3000);
                        } else {
                            // Show error notice
                            var errorNotice = '<div class="wsa-usage-error"><span class="dashicons dashicons-warning"></span><div class="wsa-usage-error-content"><h4>Refresh failed</h4><p>' + (response.data || 'Unknown error occurred') + '</p></div></div>';
                            $dashboard.prepend(errorNotice);
                        }
                    },
                    error: function() {
                        $('.wsa-loading-notice').remove();
                        var errorNotice = '<div class="wsa-usage-error"><span class="dashicons dashicons-warning"></span><div class="wsa-usage-error-content"><h4>Network error</h4><p>Unable to connect to server. Please try again.</p></div></div>';
                        $dashboard.prepend(errorNotice);
                    },
                    complete: function() {
                        // Reset button state
                        $button.removeClass('refreshing').prop('disabled', false);
                        $button.find('.dashicons').removeClass('wsa-spin');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display OpenAI usage dashboard
     */
    public function display_openai_usage_dashboard() {
        // Initialize OpenAI usage class
        if (!class_exists('WP_Site_Advisory_Pro_OpenAI_Usage')) {
            return;
        }
        
        $usage_handler = new WP_Site_Advisory_Pro_OpenAI_Usage();
        $usage_data = $usage_handler->get_usage_data();
        ?>
        <div class="wsa-openai-usage-section">
            <div class="wsa-usage-header">
                <h3>
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php _e('OpenAI Usage & Billing', 'wp-site-advisory-pro'); ?>
                </h3>
                <div class="wsa-usage-actions">
                    <button type="button" id="wsa-refresh-usage-pro" class="wsa-refresh-usage">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Usage', 'wp-site-advisory-pro'); ?>
                    </button>
                </div>
            </div>
            
            <div class="wsa-usage-dashboard">
                <?php if (is_wp_error($usage_data)): ?>
                    <div class="wsa-usage-error">
                        <span class="dashicons dashicons-warning"></span>
                        <div class="wsa-usage-error-content">
                            <h4><?php _e('Unable to fetch usage data', 'wp-site-advisory-pro'); ?></h4>
                            <p><?php echo esc_html($usage_data->get_error_message()); ?></p>
                        </div>
                    </div>
                    
                    <div class="wsa-topup-actions">
                        <h4><?php _e('Need to add credits?', 'wp-site-advisory-pro'); ?></h4>
                        <p><?php _e('Top up your OpenAI account to continue using AI features.', 'wp-site-advisory-pro'); ?></p>
                        <a href="https://platform.openai.com/account/billing" target="_blank" class="wsa-topup-button">
                            <span class="dashicons dashicons-external"></span>
                            <?php _e('Manage Billing', 'wp-site-advisory-pro'); ?>
                        </a>
                    </div>
                <?php else: 
                    $formatted_data = $usage_handler->format_for_display($usage_data);
                ?>
                    <!-- Usage Statistics Cards -->
                    <div class="wsa-usage-stats-grid">
                        <div class="wsa-usage-card">
                            <div class="wsa-usage-card-header">
                                <h4 class="wsa-usage-card-title"><?php _e('Total Usage', 'wp-site-advisory-pro'); ?></h4>
                                <span class="dashicons dashicons-chart-line wsa-usage-card-icon"></span>
                            </div>
                            <div class="wsa-usage-card-value"><?php echo esc_html($formatted_data['total_tokens']); ?></div>
                            <p class="wsa-usage-card-label"><?php _e('tokens used (30 days)', 'wp-site-advisory-pro'); ?></p>
                            <div class="wsa-usage-card-meta">
                                <span class="dashicons dashicons-clock"></span>
                                <?php printf(__('Daily avg: %s tokens', 'wp-site-advisory-pro'), esc_html($formatted_data['daily_average_tokens'])); ?>
                            </div>
                        </div>
                        
                        <div class="wsa-usage-card">
                            <div class="wsa-usage-card-header">
                                <h4 class="wsa-usage-card-title"><?php _e('Total Cost', 'wp-site-advisory-pro'); ?></h4>
                                <span class="dashicons dashicons-money-alt wsa-usage-card-icon"></span>
                            </div>
                            <div class="wsa-usage-card-value"><?php echo esc_html($formatted_data['total_cost']); ?></div>
                            <p class="wsa-usage-card-label"><?php _e('spent (30 days)', 'wp-site-advisory-pro'); ?></p>
                            <div class="wsa-usage-card-meta">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php printf(__('Daily avg: %s', 'wp-site-advisory-pro'), esc_html($formatted_data['daily_average_cost'])); ?>
                            </div>
                        </div>
                        
                        <div class="wsa-usage-card">
                            <div class="wsa-usage-card-header">
                                <h4 class="wsa-usage-card-title"><?php _e('API Requests', 'wp-site-advisory-pro'); ?></h4>
                                <span class="dashicons dashicons-networking wsa-usage-card-icon"></span>
                            </div>
                            <div class="wsa-usage-card-value"><?php echo esc_html($formatted_data['requests']); ?></div>
                            <p class="wsa-usage-card-label"><?php _e('total requests (30 days)', 'wp-site-advisory-pro'); ?></p>
                            <div class="wsa-usage-card-meta">
                                <span class="dashicons dashicons-update"></span>
                                <?php printf(__('Last updated: %s', 'wp-site-advisory-pro'), esc_html(date('M j, g:i a', strtotime($formatted_data['last_updated'])))); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Usage by Model -->
                    <?php if (!empty($formatted_data['models'])): ?>
                    <div class="wsa-usage-by-model">
                        <h4>
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Usage by Model', 'wp-site-advisory-pro'); ?>
                        </h4>
                        <div class="wsa-model-usage-grid">
                            <?php foreach ($formatted_data['models'] as $model => $stats): ?>
                            <div class="wsa-model-usage-item">
                                <div class="wsa-model-name"><?php echo esc_html($model); ?></div>
                                <div class="wsa-model-stats">
                                    <span class="wsa-model-tokens"><?php echo esc_html(number_format($stats['tokens'])); ?> tokens</span>
                                    <span class="wsa-model-cost">$<?php echo esc_html(number_format($stats['cost'], 4)); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Usage Guidance -->
                    <div class="wsa-usage-guidance">
                        <h4>
                            <span class="dashicons dashicons-lightbulb"></span>
                            <?php _e('Usage Optimization Tips', 'wp-site-advisory-pro'); ?>
                        </h4>
                        <div class="wsa-guidance-grid">
                            <div class="wsa-guidance-item">
                                <h5><?php _e('Cost Management', 'wp-site-advisory-pro'); ?></h5>
                                <p><?php _e('Monitor your daily usage to stay within budget. Consider using GPT-3.5 for simpler tasks to reduce costs.', 'wp-site-advisory-pro'); ?></p>
                            </div>
                            <div class="wsa-guidance-item">
                                <h5><?php _e('Efficient Usage', 'wp-site-advisory-pro'); ?></h5>
                                <p><?php _e('Enable caching to reduce duplicate API calls and set appropriate token limits for your use cases.', 'wp-site-advisory-pro'); ?></p>
                            </div>
                            <div class="wsa-guidance-item">
                                <h5><?php _e('Performance Tips', 'wp-site-advisory-pro'); ?></h5>
                                <p><?php _e('Use specific prompts and lower temperature settings for more consistent, cost-effective results.', 'wp-site-advisory-pro'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top-up Actions -->
                    <div class="wsa-topup-actions">
                        <h4><?php _e('Manage Your OpenAI Account', 'wp-site-advisory-pro'); ?></h4>
                        <p><?php _e('View detailed billing, set usage limits, and add credits to your OpenAI account.', 'wp-site-advisory-pro'); ?></p>
                        <a href="https://platform.openai.com/account/billing" target="_blank" class="wsa-topup-button">
                            <span class="dashicons dashicons-external"></span>
                            <?php _e('OpenAI Billing Dashboard', 'wp-site-advisory-pro'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (is_array($input)) {
            foreach ($this->default_settings as $key => $default_value) {
                if (isset($input[$key])) {
                    switch ($key) {
                        case 'openai_api_key':
                        case 'google_pagespeed_api_key':
                        case 'screaming_frog_api_key':
                            $new_value = sanitize_text_field($input[$key]);
                            // Only update if a value is provided (don't overwrite existing with empty)
                            if (!empty($new_value)) {
                                $sanitized[$key] = $new_value;
                            } else {
                                // Keep existing value if no new value provided
                                $current_settings = $this->get_settings();
                                $sanitized[$key] = $current_settings[$key];
                            }
                            break;
                        
                        case 'openai_temperature':
                            $sanitized[$key] = floatval($input[$key]);
                            $sanitized[$key] = max(0, min(1, $sanitized[$key]));
                            break;
                        
                        case 'openai_max_tokens':
                        case 'analytics_data_retention':
                        case 'max_requests_per_hour':
                            $sanitized[$key] = absint($input[$key]);
                            break;
                        
                        case 'notification_email':
                            $sanitized[$key] = sanitize_email($input[$key]);
                            break;
                        
                        case 'scan_types':
                        case 'optimizer_tasks':
                        case 'analyzer_post_types':
                            $sanitized[$key] = is_array($input[$key]) ? $input[$key] : array();
                            break;
                        
                        default:
                            if (is_bool($default_value)) {
                                $sanitized[$key] = !empty($input[$key]);
                            } else {
                                $sanitized[$key] = sanitize_text_field($input[$key]);
                            }
                            break;
                    }
                } else {
                    // Handle checkboxes that aren't checked
                    if (is_bool($default_value)) {
                        $sanitized[$key] = false;
                    }
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('wsa_unified_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        // If requesting to use existing key, get it from storage
        if ($api_key === 'use_existing_key') {
            $api_key = $this->get_actual_api_key();
        }
        
        if (empty($api_key)) {
            wp_send_json_error('No API key found. Please enter an API key first.');
        }
        
        // Simple test - try to make a basic API call
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'user', 'content' => 'Test')
                ),
                'max_tokens' => 5
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wp_send_json_success('API connection successful');
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * AJAX: Refresh OpenAI usage data
     */
    public function ajax_refresh_usage() {
        check_ajax_referer('wsa_unified_settings_nonce', 'nonce');
        
        if (!class_exists('WP_Site_Advisory_Pro_OpenAI_Usage')) {
            wp_send_json_error('OpenAI usage handler not available');
            return;
        }
        
        $usage_handler = new WP_Site_Advisory_Pro_OpenAI_Usage();
        $usage_data = $usage_handler->refresh_usage_data();
        
        if (is_wp_error($usage_data)) {
            wp_send_json_error($usage_data->get_error_message());
            return;
        }
        
        $formatted_data = $usage_handler->format_for_display($usage_data);
        
        wp_send_json_success(array(
            'message' => 'Usage data refreshed successfully',
            'data' => $formatted_data,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Migrate existing settings from separate plugins to unified format
     */
    public function migrate_existing_settings() {
        // Check if migration has already been done
        if (get_option('wsa_unified_settings_migrated')) {
            return;
        }
        
        $unified_settings = $this->get_settings();
        $needs_update = false;
        
        // Migrate from free plugin settings
        $free_settings = get_option('wsa_settings', array());
        if (!empty($free_settings)) {
            $setting_map = array(
                'wsa_openai_api_key' => 'openai_api_key',
                'wsa_notification_email' => 'notification_email',
                'wsa_scan_frequency' => 'scan_frequency',
                'wsa_email_notifications' => 'email_notifications'
            );
            
            foreach ($setting_map as $old_key => $new_key) {
                $old_value = get_option($old_key);
                if ($old_value !== false && empty($unified_settings[$new_key])) {
                    $unified_settings[$new_key] = $old_value;
                    $needs_update = true;
                }
            }
        }
        
        // Migrate from pro plugin settings
        $pro_settings = get_option('wsa_pro_settings', array());
        if (!empty($pro_settings)) {
            $pro_setting_map = array(
                'openai_api_key' => 'openai_api_key',
                'company_name' => 'company_name',
                'company_logo' => 'company_logo',
                'brand_color' => 'brand_color',
                'white_label_enabled' => 'white_label_enabled'
            );
            
            foreach ($pro_setting_map as $old_key => $new_key) {
                if (isset($pro_settings[$old_key]) && empty($unified_settings[$new_key])) {
                    $unified_settings[$new_key] = $pro_settings[$old_key];
                    $needs_update = true;
                }
            }
        }
        
        // Migrate from AI settings
        $ai_settings = get_option('wsa_pro_ai_settings', array());
        if (!empty($ai_settings)) {
            foreach ($ai_settings as $key => $value) {
                if (isset($this->default_settings[$key]) && empty($unified_settings[$key])) {
                    $unified_settings[$key] = $value;
                    $needs_update = true;
                }
            }
        }
        
        // Update unified settings if needed
        if ($needs_update) {
            update_option($this->settings_key, $unified_settings);
        }
        
        // Mark migration as completed
        update_option('wsa_unified_settings_migrated', true);
    }
}