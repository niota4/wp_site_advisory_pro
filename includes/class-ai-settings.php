<?php
/**
 * AI Settings Page for WP Site Advisory Pro
 * 
 * Centralized configuration for all AI-powered features
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSA_Pro_AI_Settings {
    
    private $settings_key = 'wsa_pro_ai_settings';
    private $default_settings = array();
    
    /**
     * Initialize AI settings
     */
    public function __construct() {
        $this->init_default_settings();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_ai_settings_menu'), 25);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_wsa_save_ai_settings', array($this, 'ajax_save_ai_settings'));
        add_action('wp_ajax_wsa_test_ai_connection', array($this, 'ajax_test_ai_connection'));
        add_action('wp_ajax_wsa_reset_ai_settings', array($this, 'ajax_reset_ai_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_ai_settings_scripts'));
    }
    
    /**
     * Initialize default settings
     */
    private function init_default_settings() {
        $this->default_settings = array(
            // API Configuration
            'openai_api_key' => '',
            'openai_model' => 'gpt-4',
            'openai_temperature' => 0.3,
            'openai_max_tokens' => 1500,
            
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
            
            // AI Behavior Settings
            'ai_response_style' => 'professional',
            'ai_verbosity' => 'balanced',
            'ai_include_explanations' => true,
            'ai_cache_responses' => true,
            'ai_cache_duration' => 3600,
            
            // Notification Settings
            'email_notifications' => true,
            'notification_email' => get_option('admin_email'),
            'notification_frequency' => 'daily',
            'critical_alerts_only' => false,
            
            // Advanced Settings
            'ai_fallback_enabled' => true,
            'debug_mode' => false,
            'rate_limiting_enabled' => true,
            'max_requests_per_hour' => 100
        );
    }
    
    /**
     * Add AI settings menu
     */
    public function add_ai_settings_menu() {
        add_submenu_page(
            'wp-site-advisory',
            __('AI Settings', 'wp-site-advisory-pro'),
            __('AI Settings', 'wp-site-advisory-pro'),
            'manage_options',
            'wp-site-advisory-ai-settings',
            array($this, 'render_ai_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wsa_pro_ai_settings_group', $this->settings_key, array($this, 'sanitize_settings'));
    }
    
    /**
     * Render AI settings page
     */
    public function render_ai_settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AI Settings', 'wp-site-advisory-pro'); ?></h1>
            
            <div class="wsa-ai-settings-container">
                <form id="wsa-ai-settings-form" method="post" action="options.php">
                    <?php
                    settings_fields('wsa_pro_ai_settings_group');
                    wp_nonce_field('wsa_ai_settings_nonce', 'wsa_ai_settings_nonce');
                    ?>
                    
                    <div class="wsa-settings-tabs">
                        <nav class="nav-tab-wrapper">
                            <a href="#api-config" class="nav-tab nav-tab-active"><?php _e('API Configuration', 'wp-site-advisory-pro'); ?></a>
                            <a href="#features" class="nav-tab"><?php _e('AI Features', 'wp-site-advisory-pro'); ?></a>
                            <a href="#automation" class="nav-tab"><?php _e('Automation', 'wp-site-advisory-pro'); ?></a>
                            <a href="#analytics" class="nav-tab"><?php _e('Analytics', 'wp-site-advisory-pro'); ?></a>
                            <a href="#content" class="nav-tab"><?php _e('Content Analysis', 'wp-site-advisory-pro'); ?></a>
                            <a href="#behavior" class="nav-tab"><?php _e('AI Behavior', 'wp-site-advisory-pro'); ?></a>
                            <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'wp-site-advisory-pro'); ?></a>
                            <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'wp-site-advisory-pro'); ?></a>
                        </nav>
                        
                        <!-- API Configuration Tab -->
                        <div id="api-config" class="tab-content active">
                            <h2><?php _e('OpenAI API Configuration', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Configure your OpenAI API settings for AI-powered features.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="openai_api_key"><?php _e('OpenAI API Key', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" id="openai_api_key" name="<?php echo $this->settings_key; ?>[openai_api_key]" 
                                               value="<?php echo esc_attr($settings['openai_api_key']); ?>" class="regular-text" />
                                        <button type="button" class="button" id="test-api-connection">
                                            <?php _e('Test Connection', 'wp-site-advisory-pro'); ?>
                                        </button>
                                        <p class="description"><?php _e('Your OpenAI API key for AI features. Get one from OpenAI.', 'wp-site-advisory-pro'); ?></p>
                                        <div id="api-test-result" style="margin-top: 10px;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="openai_model"><?php _e('AI Model', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="openai_model" name="<?php echo $this->settings_key; ?>[openai_model]">
                                            <option value="gpt-4" <?php selected($settings['openai_model'], 'gpt-4'); ?>>GPT-4 (Recommended)</option>
                                            <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Faster)</option>
                                        </select>
                                        <p class="description"><?php _e('Choose the AI model for analysis. GPT-4 provides better quality but is slower and more expensive.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="openai_temperature"><?php _e('AI Creativity', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="range" id="openai_temperature" name="<?php echo $this->settings_key; ?>[openai_temperature]" 
                                               min="0" max="1" step="0.1" value="<?php echo esc_attr($settings['openai_temperature']); ?>" />
                                        <span id="temperature-value"><?php echo esc_attr($settings['openai_temperature']); ?></span>
                                        <p class="description"><?php _e('Lower values = more focused, higher values = more creative. 0.3 recommended for technical analysis.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- AI Features Tab -->
                        <div id="features" class="tab-content">
                            <h2><?php _e('AI Feature Controls', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Enable or disable individual AI-powered features.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Automated Optimizer', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo $this->settings_key; ?>[enable_automated_optimizer]" 
                                                   value="1" <?php checked($settings['enable_automated_optimizer'], 1); ?> />
                                            <?php _e('Enable AI-powered optimization recommendations and automation', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Predictive Analytics', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo $this->settings_key; ?>[enable_predictive_analytics]" 
                                                   value="1" <?php checked($settings['enable_predictive_analytics'], 1); ?> />
                                            <?php _e('Enable traffic and performance predictions', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Content Analyzer', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo $this->settings_key; ?>[enable_content_analyzer]" 
                                                   value="1" <?php checked($settings['enable_content_analyzer'], 1); ?> />
                                            <?php _e('Enable AI-powered SEO and accessibility analysis', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('AI Chatbot', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo $this->settings_key; ?>[enable_ai_chatbot]" 
                                                   value="1" <?php checked($settings['enable_ai_chatbot'], 1); ?> />
                                            <?php _e('Enable AI assistant for WordPress help and troubleshooting', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Site Detective', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo $this->settings_key; ?>[enable_site_detective]" 
                                                   value="1" <?php checked($settings['enable_site_detective'], 1); ?> />
                                            <?php _e('Enable admin bar AI tool for analyzing any page element', 'wp-site-advisory-pro'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Automation Tab -->
                        <div id="automation" class="tab-content">
                            <h2><?php _e('Automated Optimization Settings', 'wp-site-advisory-pro'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Auto-Run Optimizations', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo $this->settings_key; ?>[optimizer_auto_run]" 
                                                   value="1" <?php checked($settings['optimizer_auto_run'], 1); ?> />
                                            <?php _e('Automatically run safe optimizations', 'wp-site-advisory-pro'); ?>
                                        </label>
                                        <p class="description"><?php _e('Only low-risk optimizations will run automatically.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="optimizer_schedule"><?php _e('Optimization Schedule', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="optimizer_schedule" name="<?php echo $this->settings_key; ?>[optimizer_schedule]">
                                            <option value="daily" <?php selected($settings['optimizer_schedule'], 'daily'); ?>><?php _e('Daily', 'wp-site-advisory-pro'); ?></option>
                                            <option value="weekly" <?php selected($settings['optimizer_schedule'], 'weekly'); ?>><?php _e('Weekly', 'wp-site-advisory-pro'); ?></option>
                                            <option value="monthly" <?php selected($settings['optimizer_schedule'], 'monthly'); ?>><?php _e('Monthly', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Automated Tasks', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[optimizer_tasks][database_cleanup]" 
                                                       value="1" <?php checked($settings['optimizer_tasks']['database_cleanup'] ?? false, 1); ?> />
                                                <?php _e('Database Cleanup', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[optimizer_tasks][cache_optimization]" 
                                                       value="1" <?php checked($settings['optimizer_tasks']['cache_optimization'] ?? false, 1); ?> />
                                                <?php _e('Cache Optimization', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[optimizer_tasks][font_optimization]" 
                                                       value="1" <?php checked($settings['optimizer_tasks']['font_optimization'] ?? false, 1); ?> />
                                                <?php _e('Font Optimization', 'wp-site-advisory-pro'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Analytics Tab -->
                        <div id="analytics" class="tab-content">
                            <h2><?php _e('Analytics Configuration', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Configure AI analytics and tracking settings.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enable_analytics_tracking"><?php _e('Enable Analytics Tracking', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="enable_analytics_tracking" name="<?php echo $this->settings_key; ?>[enable_analytics_tracking]" 
                                               value="1" <?php checked($settings['enable_analytics_tracking'] ?? false, 1); ?> />
                                        <p class="description"><?php _e('Enable AI-powered analytics and predictive insights.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="analytics_data_retention"><?php _e('Data Retention Period', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="analytics_data_retention" name="<?php echo $this->settings_key; ?>[analytics_data_retention]">
                                            <option value="30" <?php selected($settings['analytics_data_retention'] ?? 90, 30); ?>><?php _e('30 Days', 'wp-site-advisory-pro'); ?></option>
                                            <option value="90" <?php selected($settings['analytics_data_retention'] ?? 90, 90); ?>><?php _e('90 Days', 'wp-site-advisory-pro'); ?></option>
                                            <option value="180" <?php selected($settings['analytics_data_retention'] ?? 90, 180); ?>><?php _e('180 Days', 'wp-site-advisory-pro'); ?></option>
                                            <option value="365" <?php selected($settings['analytics_data_retention'] ?? 90, 365); ?>><?php _e('1 Year', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('How long to retain analytics data for AI predictions.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="predictive_analytics_frequency"><?php _e('Prediction Frequency', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="predictive_analytics_frequency" name="<?php echo $this->settings_key; ?>[predictive_analytics_frequency]">
                                            <option value="daily" <?php selected($settings['predictive_analytics_frequency'] ?? 'weekly', 'daily'); ?>><?php _e('Daily', 'wp-site-advisory-pro'); ?></option>
                                            <option value="weekly" <?php selected($settings['predictive_analytics_frequency'] ?? 'weekly', 'weekly'); ?>><?php _e('Weekly', 'wp-site-advisory-pro'); ?></option>
                                            <option value="monthly" <?php selected($settings['predictive_analytics_frequency'] ?? 'weekly', 'monthly'); ?>><?php _e('Monthly', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('How often to generate AI predictions and trends.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Content Analysis Tab -->
                        <div id="content" class="tab-content">
                            <h2><?php _e('Content Analysis Settings', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Configure AI-powered content analysis and SEO optimization.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enable_content_audits"><?php _e('Enable Content Audits', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="enable_content_audits" name="<?php echo $this->settings_key; ?>[enable_content_audits]" 
                                               value="1" <?php checked($settings['enable_content_audits'] ?? false, 1); ?> />
                                        <p class="description"><?php _e('Automatically analyze content for SEO and accessibility issues.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="content_analysis_post_types"><?php _e('Analyze Post Types', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[content_analysis_post_types][post]" 
                                                       value="1" <?php checked($settings['content_analysis_post_types']['post'] ?? true, 1); ?> />
                                                <?php _e('Posts', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[content_analysis_post_types][page]" 
                                                       value="1" <?php checked($settings['content_analysis_post_types']['page'] ?? true, 1); ?> />
                                                <?php _e('Pages', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                        </fieldset>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="seo_analysis_depth"><?php _e('SEO Analysis Depth', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="seo_analysis_depth" name="<?php echo $this->settings_key; ?>[seo_analysis_depth]">
                                            <option value="basic" <?php selected($settings['seo_analysis_depth'] ?? 'comprehensive', 'basic'); ?>><?php _e('Basic', 'wp-site-advisory-pro'); ?></option>
                                            <option value="comprehensive" <?php selected($settings['seo_analysis_depth'] ?? 'comprehensive', 'comprehensive'); ?>><?php _e('Comprehensive', 'wp-site-advisory-pro'); ?></option>
                                            <option value="advanced" <?php selected($settings['seo_analysis_depth'] ?? 'comprehensive', 'advanced'); ?>><?php _e('Advanced', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('Depth of SEO analysis. Advanced uses more AI API calls.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- AI Behavior Tab -->
                        <div id="behavior" class="tab-content">
                            <h2><?php _e('AI Behavior Settings', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Control how AI features behave and respond.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="ai_response_style"><?php _e('Response Style', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="ai_response_style" name="<?php echo $this->settings_key; ?>[ai_response_style]">
                                            <option value="technical" <?php selected($settings['ai_response_style'] ?? 'professional', 'technical'); ?>><?php _e('Technical', 'wp-site-advisory-pro'); ?></option>
                                            <option value="professional" <?php selected($settings['ai_response_style'] ?? 'professional', 'professional'); ?>><?php _e('Professional', 'wp-site-advisory-pro'); ?></option>
                                            <option value="friendly" <?php selected($settings['ai_response_style'] ?? 'professional', 'friendly'); ?>><?php _e('Friendly', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('Tone and style of AI responses and recommendations.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="ai_confidence_threshold"><?php _e('Confidence Threshold', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="range" id="ai_confidence_threshold" name="<?php echo $this->settings_key; ?>[ai_confidence_threshold]" 
                                               min="0.5" max="1" step="0.05" value="<?php echo esc_attr($settings['ai_confidence_threshold'] ?? 0.75); ?>" />
                                        <span id="confidence-value"><?php echo esc_attr($settings['ai_confidence_threshold'] ?? 0.75); ?></span>
                                        <p class="description"><?php _e('Minimum confidence level for AI recommendations. Higher values = more conservative suggestions.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="enable_ai_learning"><?php _e('Enable AI Learning', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="enable_ai_learning" name="<?php echo $this->settings_key; ?>[enable_ai_learning]" 
                                               value="1" <?php checked($settings['enable_ai_learning'] ?? true, 1); ?> />
                                        <p class="description"><?php _e('Allow AI to learn from your site patterns to improve recommendations.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Notifications Tab -->
                        <div id="notifications" class="tab-content">
                            <h2><?php _e('AI Notifications', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Configure when and how you receive AI-generated notifications.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enable_ai_notifications"><?php _e('Enable AI Notifications', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="enable_ai_notifications" name="<?php echo $this->settings_key; ?>[enable_ai_notifications]" 
                                               value="1" <?php checked($settings['enable_ai_notifications'] ?? true, 1); ?> />
                                        <p class="description"><?php _e('Receive notifications about AI analysis results and recommendations.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="notification_email"><?php _e('Notification Email', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="email" id="notification_email" name="<?php echo $this->settings_key; ?>[notification_email]" 
                                               value="<?php echo esc_attr($settings['notification_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                                        <p class="description"><?php _e('Email address for AI notifications.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Notification Types', 'wp-site-advisory-pro'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[notification_types][security_alerts]" 
                                                       value="1" <?php checked($settings['notification_types']['security_alerts'] ?? true, 1); ?> />
                                                <?php _e('Security Alerts', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[notification_types][performance_issues]" 
                                                       value="1" <?php checked($settings['notification_types']['performance_issues'] ?? true, 1); ?> />
                                                <?php _e('Performance Issues', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[notification_types][optimization_recommendations]" 
                                                       value="1" <?php checked($settings['notification_types']['optimization_recommendations'] ?? false, 1); ?> />
                                                <?php _e('Optimization Recommendations', 'wp-site-advisory-pro'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="<?php echo $this->settings_key; ?>[notification_types][content_analysis]" 
                                                       value="1" <?php checked($settings['notification_types']['content_analysis'] ?? false, 1); ?> />
                                                <?php _e('Content Analysis Results', 'wp-site-advisory-pro'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Advanced Tab -->
                        <div id="advanced" class="tab-content">
                            <h2><?php _e('Advanced AI Settings', 'wp-site-advisory-pro'); ?></h2>
                            <p class="description"><?php _e('Advanced configuration options for AI features.', 'wp-site-advisory-pro'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="api_request_timeout"><?php _e('API Request Timeout', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="api_request_timeout" name="<?php echo $this->settings_key; ?>[api_request_timeout]" 
                                               value="<?php echo esc_attr($settings['api_request_timeout'] ?? 30); ?>" min="10" max="120" />
                                        <span><?php _e('seconds', 'wp-site-advisory-pro'); ?></span>
                                        <p class="description"><?php _e('Timeout for OpenAI API requests. Increase for complex analyses.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="enable_debug_logging"><?php _e('Enable Debug Logging', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="enable_debug_logging" name="<?php echo $this->settings_key; ?>[enable_debug_logging]" 
                                               value="1" <?php checked($settings['enable_debug_logging'] ?? false, 1); ?> />
                                        <p class="description"><?php _e('Log AI API requests and responses for debugging. May affect performance.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="cache_ai_responses"><?php _e('Cache AI Responses', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="cache_ai_responses" name="<?php echo $this->settings_key; ?>[cache_ai_responses]" 
                                               value="1" <?php checked($settings['cache_ai_responses'] ?? true, 1); ?> />
                                        <p class="description"><?php _e('Cache AI responses to reduce API calls and improve performance.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="ai_cache_duration"><?php _e('Cache Duration', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <select id="ai_cache_duration" name="<?php echo $this->settings_key; ?>[ai_cache_duration]">
                                            <option value="3600" <?php selected($settings['ai_cache_duration'] ?? 43200, 3600); ?>><?php _e('1 Hour', 'wp-site-advisory-pro'); ?></option>
                                            <option value="21600" <?php selected($settings['ai_cache_duration'] ?? 43200, 21600); ?>><?php _e('6 Hours', 'wp-site-advisory-pro'); ?></option>
                                            <option value="43200" <?php selected($settings['ai_cache_duration'] ?? 43200, 43200); ?>><?php _e('12 Hours', 'wp-site-advisory-pro'); ?></option>
                                            <option value="86400" <?php selected($settings['ai_cache_duration'] ?? 43200, 86400); ?>><?php _e('24 Hours', 'wp-site-advisory-pro'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('How long to cache AI responses.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="reset_ai_data"><?php _e('Reset AI Data', 'wp-site-advisory-pro'); ?></label>
                                    </th>
                                    <td>
                                        <button type="button" class="button button-secondary" id="clear-ai-cache">
                                            <?php _e('Clear AI Cache', 'wp-site-advisory-pro'); ?>
                                        </button>
                                        <button type="button" class="button button-secondary" id="reset-ai-learning">
                                            <?php _e('Reset AI Learning Data', 'wp-site-advisory-pro'); ?>
                                        </button>
                                        <p class="description"><?php _e('Clear cached data and reset AI learning patterns.', 'wp-site-advisory-pro'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button-primary" 
                               value="<?php esc_attr_e('Save AI Settings', 'wp-site-advisory-pro'); ?>" />
                        <button type="button" class="button" id="reset-ai-settings">
                            <?php _e('Reset to Defaults', 'wp-site-advisory-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .wsa-ai-settings-container {
            max-width: 1000px;
        }
        
        .wsa-settings-tabs .nav-tab-wrapper {
            margin-bottom: 20px;
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
        
        .form-table th {
            width: 200px;
        }
        
        #temperature-value {
            margin-left: 10px;
            font-weight: bold;
        }
        
        #api-test-result {
            padding: 8px 12px;
            border-radius: 4px;
            display: none;
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
                if (!apiKey) {
                    $('#api-test-result').removeClass('success').addClass('error')
                        .text('<?php _e('Please enter an API key first.', 'wp-site-advisory-pro'); ?>')
                        .show();
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Testing...', 'wp-site-advisory-pro'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsa_test_ai_connection',
                        api_key: apiKey,
                        nonce: $('#wsa_ai_settings_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#api-test-result').removeClass('error').addClass('success')
                                .text('<?php _e('Connection successful!', 'wp-site-advisory-pro'); ?>')
                                .show();
                        } else {
                            $('#api-test-result').removeClass('success').addClass('error')
                                .text(response.data || '<?php _e('Connection failed.', 'wp-site-advisory-pro'); ?>')
                                .show();
                        }
                    },
                    error: function() {
                        $('#api-test-result').removeClass('success').addClass('error')
                            .text('<?php _e('Connection test failed.', 'wp-site-advisory-pro'); ?>')
                            .show();
                    },
                    complete: function() {
                        $('#test-api-connection').prop('disabled', false)
                            .text('<?php _e('Test Connection', 'wp-site-advisory-pro'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get current settings with defaults
     */
    public function get_settings() {
        $settings = get_option($this->settings_key, array());
        return wp_parse_args($settings, $this->default_settings);
    }
    
    /**
     * Sanitize settings input
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (is_array($input)) {
            // Sanitize each setting based on its type
            foreach ($this->default_settings as $key => $default_value) {
                if (isset($input[$key])) {
                    switch ($key) {
                        case 'openai_api_key':
                            $sanitized[$key] = sanitize_text_field($input[$key]);
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
                        
                        case 'optimizer_tasks':
                        case 'analytics_alert_thresholds':
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
     * AJAX: Test AI connection
     */
    public function ajax_test_ai_connection() {
        check_ajax_referer('wsa_ai_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }
        
        // Test the API connection
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test connection'
                    )
                ),
                'max_tokens' => 5
            )),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Network error: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            wp_send_json_success('API connection successful');
        } elseif ($response_code === 401) {
            wp_send_json_error('Invalid API key');
        } elseif ($response_code === 429) {
            wp_send_json_error('API rate limit exceeded');
        } else {
            wp_send_json_error('API returned error code: ' . $response_code);
        }
    }
    
    /**
     * Enqueue settings page scripts
     */
    public function enqueue_ai_settings_scripts($hook) {
        if ($hook !== 'wp-siteadvisor_page_wp-site-advisory-ai-settings') {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
}