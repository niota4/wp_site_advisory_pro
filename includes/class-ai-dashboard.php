<?php
/**
 * AI Features Dashboard
 * 
 * Central dashboard for managing and accessing all AI-powered features
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Admin
 */

namespace WSA_Pro\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AI_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers for dashboard actions (scripts handled by main dashboard)
        add_action('wp_ajax_wsa_run_ai_analysis', array($this, 'ajax_run_ai_analysis'));
        add_action('wp_ajax_wsa_get_ai_status', array($this, 'ajax_get_ai_status'));
        add_action('wp_ajax_wsa_get_ai_recommendations', array($this, 'ajax_get_ai_recommendations'));
        add_action('wp_ajax_wsa_get_ai_results', array($this, 'ajax_get_ai_results'));
        add_action('wp_ajax_wsa_check_ai_results', array($this, 'ajax_check_ai_results'));
        add_action('wp_ajax_wsa_create_test_results', array($this, 'ajax_create_test_results'));
    }
    
    /**
     * Add AI dashboard menu
     */
    public function add_ai_dashboard_menu() {
        add_submenu_page(
            'wp-site-advisory',
            __('AI Features Dashboard', 'wp-site-advisory-pro'),
            __('AI Dashboard', 'wp-site-advisory-pro'),
            'manage_options',
            'wp-site-advisory-ai-dashboard',
            array($this, 'render_ai_dashboard_page')
        );
    }
    
    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_dashboard_scripts($hook) {
        // Load scripts on main dashboard page
        if ($hook !== 'toplevel_page_wp-site-advisory') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-util');
        
        // Enqueue dashboard CSS
        wp_enqueue_style(
            'wsa-ai-dashboard',
            plugin_dir_url(__FILE__) . '../assets/css/ai-dashboard.css',
            array(),
            WSA_PRO_VERSION
        );
        
        // Enqueue dashboard JS
        wp_enqueue_script(
            'wsa-ai-dashboard',
            plugin_dir_url(__FILE__) . '../assets/js/ai-dashboard.js',
            array('jquery', 'wp-util'),
            WSA_PRO_VERSION,
            true
        );
        
        // Localize script using main dashboard nonce
        wp_localize_script('wsa-ai-dashboard', 'wsaAIDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_admin_nonce'),
            'strings' => array(
                'analyzing' => __('Running analysis...', 'wp-site-advisory-pro'),
                'complete' => __('Analysis complete', 'wp-site-advisory-pro'),
                'error' => __('An error occurred', 'wp-site-advisory-pro'),
                'loading' => __('Loading...', 'wp-site-advisory-pro')
            )
        ));
    }
    
    /**
     * Render AI dashboard page
     */
    public function render_ai_dashboard_page() {
        ?>
        <div class="wrap wsa-ai-dashboard">
            <h1><?php echo esc_html__('AI Features Dashboard', 'wp-site-advisory-pro'); ?></h1>
            <p class="description"><?php echo esc_html__('Access and manage all AI-powered features for your WordPress site.', 'wp-site-advisory-pro'); ?></p>
            
            <div class="wsa-dashboard-grid">
                
                <!-- AI Automated Optimizer -->
                <div class="wsa-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html__('AI Automated Optimizer', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php echo esc_html__('Automatically optimize your site\'s performance with AI-powered recommendations and fixes.', 'wp-site-advisory-pro'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="optimizer-status">
                                <?php echo $this->get_optimizer_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary" id="run-optimizer" data-feature="optimizer">
                                <?php echo esc_html__('Run Optimization', 'wp-site-advisory-pro'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory&tab=performance'); ?>" class="button">
                                <?php echo esc_html__('View Settings', 'wp-site-advisory-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- AI Content Analyzer -->
                <div class="wsa-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-edit-page"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html__('AI Content Analyzer', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php echo esc_html__('Analyze your content for SEO, accessibility, and quality improvements with AI insights.', 'wp-site-advisory-pro'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="content-status">
                                <?php echo $this->get_content_analyzer_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary" id="analyze-content" data-feature="content">
                                <?php echo esc_html__('Analyze Content', 'wp-site-advisory-pro'); ?>
                            </button>
                            <a href="<?php echo admin_url('edit.php'); ?>" class="button">
                                <?php echo esc_html__('Edit Posts', 'wp-site-advisory-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- AI Predictive Analytics -->
                <div class="wsa-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html__('AI Predictive Analytics', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php echo esc_html__('Get AI-powered predictions about traffic, performance trends, and security risks.', 'wp-site-advisory-pro'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="analytics-status">
                                <?php echo $this->get_predictive_analytics_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary" id="generate-predictions" data-feature="analytics">
                                <?php echo esc_html__('Generate Predictions', 'wp-site-advisory-pro'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory&tab=analytics'); ?>" class="button">
                                <?php echo esc_html__('View Analytics', 'wp-site-advisory-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced PageSpeed Analysis -->
                <div class="wsa-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-dashboard"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html__('Enhanced PageSpeed Analysis', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php echo esc_html__('Advanced PageSpeed analysis with AI-powered optimization recommendations.', 'wp-site-advisory-pro'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="pagespeed-status">
                                <?php echo $this->get_pagespeed_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary" id="run-pagespeed" data-feature="pagespeed">
                                <?php echo esc_html__('Run Analysis', 'wp-site-advisory-pro'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory&tab=performance'); ?>" class="button">
                                <?php echo esc_html__('View Results', 'wp-site-advisory-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- AI White Label Reports -->
                <div class="wsa-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html__('AI White Label Reports', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php echo esc_html__('Generate professional reports with AI-powered insights for clients.', 'wp-site-advisory-pro'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="reports-status">
                                <?php echo $this->get_reports_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="button button-primary" id="generate-report" data-feature="reports">
                                <?php echo esc_html__('Generate Report', 'wp-site-advisory-pro'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-reports'); ?>" class="button">
                                <?php echo esc_html__('Manage Reports', 'wp-site-advisory-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- AI Settings -->
                <div class="wsa-dashboard-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html__('AI Configuration', 'wp-site-advisory-pro'); ?></h3>
                        <p><?php echo esc_html__('Configure OpenAI API settings and AI feature preferences.', 'wp-site-advisory-pro'); ?></p>
                        <div class="card-status">
                            <span class="status-indicator" id="ai-config-status">
                                <?php echo $this->get_ai_config_status(); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <a href="<?php echo admin_url('admin.php?page=wp-site-advisory-ai-settings'); ?>" class="button button-primary">
                                <?php echo esc_html__('Configure AI', 'wp-site-advisory-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Quick Stats -->
            <div class="wsa-quick-stats">
                <h2><?php echo esc_html__('AI Features Quick Stats', 'wp-site-advisory-pro'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number" id="optimizations-run"><?php echo $this->get_optimizations_count(); ?></span>
                        <span class="stat-label"><?php echo esc_html__('Optimizations Run', 'wp-site-advisory-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="content-analyzed"><?php echo $this->get_content_analyzed_count(); ?></span>
                        <span class="stat-label"><?php echo esc_html__('Content Analyzed', 'wp-site-advisory-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="reports-generated"><?php echo $this->get_reports_generated_count(); ?></span>
                        <span class="stat-label"><?php echo esc_html__('Reports Generated', 'wp-site-advisory-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="ai-api-calls"><?php echo $this->get_ai_api_calls_count(); ?></span>
                        <span class="stat-label"><?php echo esc_html__('AI API Calls', 'wp-site-advisory-pro'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Recent AI Activity -->
            <div class="wsa-recent-activity">
                <h2><?php echo esc_html__('Recent AI Activity', 'wp-site-advisory-pro'); ?></h2>
                <div id="recent-activity-list">
                    <?php echo $this->get_recent_activity(); ?>
                </div>
            </div>
            
        </div>
        
        <style>
        .wsa-ai-dashboard {
            max-width: 1200px;
        }
        
        .wsa-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .wsa-dashboard-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .wsa-dashboard-card .card-icon {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .wsa-dashboard-card .card-icon .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #2271b1;
        }
        
        .wsa-dashboard-card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .wsa-dashboard-card p {
            color: #646970;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .card-status {
            margin-bottom: 15px;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-indicator.status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-indicator.status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-indicator.status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-actions .button {
            flex: 1;
            min-width: 120px;
            text-align: center;
            justify-content: center;
        }
        
        .wsa-quick-stats {
            margin: 30px 0;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #646970;
        }
        
        .wsa-recent-activity {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            margin-right: 10px;
            color: #2271b1;
        }
        
        .activity-text {
            flex: 1;
        }
        
        .activity-time {
            font-size: 12px;
            color: #646970;
        }
        </style>
        <?php
    }
    
    /**
     * Get optimizer status
     */
    private function get_optimizer_status() {
        $automation_enabled = get_option('wsa_pro_enable_auto_optimization', false);
        if ($automation_enabled) {
            return '<span class="status-active">Active</span>';
        }
        return '<span class="status-inactive">Inactive</span>';
    }
    
    /**
     * Get content analyzer status
     */
    private function get_content_analyzer_status() {
        $last_analysis = get_option('wsa_pro_last_content_analysis', array());
        if (!empty($last_analysis) && isset($last_analysis['timestamp'])) {
            $hours_ago = (time() - $last_analysis['timestamp']) / 3600;
            if ($hours_ago < 24) {
                return '<span class="status-active">Recent analysis</span>';
            }
        }
        return '<span class="status-warning">No recent analysis</span>';
    }
    
    /**
     * Get predictive analytics status
     */
    private function get_predictive_analytics_status() {
        $analytics_enabled = get_option('wsa_pro_enable_analytics_tracking', false);
        if ($analytics_enabled) {
            return '<span class="status-active">Collecting data</span>';
        }
        return '<span class="status-inactive">Disabled</span>';
    }
    
    /**
     * Get PageSpeed status
     */
    private function get_pagespeed_status() {
        $last_results = get_option('wsa_pro_pagespeed_analysis', array());
        if (!empty($last_results) && isset($last_results['scan_time'])) {
            $days_ago = (time() - $last_results['scan_time']) / DAY_IN_SECONDS;
            if ($days_ago < 7) {
                return '<span class="status-active">Recent scan</span>';
            }
        }
        return '<span class="status-warning">Scan needed</span>';
    }
    
    /**
     * Get reports status
     */
    private function get_reports_status() {
        $last_report = get_option('wsa_pro_last_report_generated', array());
        if (!empty($last_report) && isset($last_report['timestamp'])) {
            return '<span class="status-active">Available</span>';
        }
        return '<span class="status-warning">No reports</span>';
    }
    
    /**
     * Get AI config status
     */
    private function get_ai_config_status() {
        $openai_key = get_option('wsa_pro_openai_api_key') ?: get_option('wsa_openai_api_key');
        if (!empty($openai_key)) {
            return '<span class="status-active">Configured</span>';
        }
        return '<span class="status-inactive">Not configured</span>';
    }
    
    /**
     * Get optimizations count
     */
    private function get_optimizations_count() {
        return get_option('wsa_pro_total_optimizations_run', 0);
    }
    
    /**
     * Get content analyzed count
     */
    private function get_content_analyzed_count() {
        return get_option('wsa_pro_content_analyses_count', 0);
    }
    
    /**
     * Get reports generated count
     */
    private function get_reports_generated_count() {
        return get_option('wsa_pro_reports_generated_count', 0);
    }
    
    /**
     * Get AI API calls count
     */
    private function get_ai_api_calls_count() {
        return get_option('wsa_pro_ai_api_calls_count', 0);
    }
    
    /**
     * Get recent activity
     */
    private function get_recent_activity() {
        $activities = get_option('wsa_pro_recent_ai_activity', array());
        
        if (empty($activities)) {
            return '<p>' . esc_html__('No recent AI activity.', 'wp-site-advisory-pro') . '</p>';
        }
        
        $html = '';
        foreach (array_slice($activities, -10) as $activity) {
            $time_ago = human_time_diff($activity['timestamp'], time()) . ' ago';
            $html .= sprintf(
                '<div class="activity-item">
                    <span class="activity-icon dashicons %s"></span>
                    <div class="activity-text">%s</div>
                    <div class="activity-time">%s</div>
                </div>',
                esc_attr($activity['icon']),
                esc_html($activity['message']),
                esc_html($time_ago)
            );
        }
        
        return $html;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_run_ai_analysis() {
        // Add debug logging first
        
        // Debug nonce verification
        $sent_nonce = $_POST['nonce'] ?? '';
        $expected_nonce = wp_create_nonce('wsa_admin_nonce');
        
        // Try multiple nonce types for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce') || 
                          wp_verify_nonce($_POST['nonce'], 'wsa_ai_dashboard_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $feature = sanitize_text_field($_POST['feature'] ?? '');
        
        // Test if basic AJAX is working
        if ($feature === 'test') {
            wp_send_json_success(array(
                'message' => 'AJAX is working correctly!',
                'feature' => $feature,
                'timestamp' => current_time('mysql')
            ));
        }
        
        switch ($feature) {
            case 'optimizer':
                $this->run_optimizer_analysis();
                break;
            case 'content':
                $this->run_content_analysis();
                break;
            case 'analytics':
                $this->run_analytics_analysis();
                break;
            case 'pagespeed':
                $this->run_pagespeed_analysis();
                break;
            case 'reports':
                $this->generate_report();
                break;
            default:
                wp_send_json_error('Invalid feature: ' . $feature);
        }
    }
    
    private function run_optimizer_analysis() {
        
        if (class_exists('\WSA_Pro\Features\AI_Automated_Optimizer')) {
            try {
                $optimizer = new \WSA_Pro\Features\AI_Automated_Optimizer();
                $result = $optimizer->get_optimization_recommendations();
                
                // Save results to database
                $this->save_ai_results('optimizer', $result);
                
                // Get the saved formatted data to return
                $saved_data = get_option('wsa_ai_results_optimizer');
                
                wp_send_json_success($saved_data);
            } catch (Exception $e) {
                wp_send_json_error('Optimizer error: ' . $e->getMessage());
            }
        } else {
            wp_send_json_error('AI Optimizer feature not available. Please check if the Pro license is active.');
        }
    }
    
    private function run_content_analysis() {
        
        // Get recent posts for analysis
        $recent_posts = get_posts(array('numberposts' => 5, 'post_status' => 'publish'));
        if (empty($recent_posts)) {
            wp_send_json_error('No published posts found to analyze');
        }
        
        if (class_exists('\WSA_Pro\Features\AI_Content_Analyzer')) {
            try {
                $analyzer = new \WSA_Pro\Features\AI_Content_Analyzer();
                $results = array();
                foreach ($recent_posts as $post) {
                    $results[] = $analyzer->analyze_content($post->ID);
                }
                
                // Save results to database
                $this->save_ai_results('content', $results);
                
                // Get the saved formatted data to return
                $saved_data = get_option('wsa_ai_results_content');
                $saved_data['posts_analyzed'] = count($results);
                
                wp_send_json_success($saved_data);
            } catch (Exception $e) {
                wp_send_json_error('Content analysis error: ' . $e->getMessage());
            }
        } else {
            wp_send_json_error('AI Content Analyzer feature not available. Please check if the Pro license is active.');
        }
    }
    
    private function run_analytics_analysis() {
        if (class_exists('\WSA_Pro\Features\AI_Predictive_Analytics')) {
            try {
                $analytics = new \WSA_Pro\Features\AI_Predictive_Analytics();
                $predictions = $analytics->get_traffic_prediction(30);
                
                // Save results to database
                $this->save_ai_results('analytics', $predictions);
                
                // Get the saved formatted data to return
                $saved_data = get_option('wsa_ai_results_analytics');
                
                wp_send_json_success($saved_data);
            } catch (Exception $e) {
                wp_send_json_error('Analytics error: ' . $e->getMessage());
            }
        } else {
            wp_send_json_error('AI Predictive Analytics feature not available. Please check if the Pro license is active.');
        }
    }
    
    private function run_pagespeed_analysis() {
        if (class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
            try {
                $pagespeed = new \WSA_Pro\Features\PageSpeed_Analysis();
                $results = $pagespeed->run_performance_analysis(home_url());
                
                // Save results to database
                $this->save_ai_results('pagespeed', $results);
                
                // Get the saved formatted data to return
                $saved_data = get_option('wsa_ai_results_pagespeed');
                
                wp_send_json_success($saved_data);
            } catch (Exception $e) {
                wp_send_json_error('PageSpeed analysis error: ' . $e->getMessage());
            }
        } else {
            wp_send_json_error('Enhanced PageSpeed Analysis feature not available. Please check if the Pro license is active.');
        }
    }
    
    private function generate_report() {
        if (class_exists('\WSA_Pro\Features\White_Label_Reports')) {
            try {
                $reports = new \WSA_Pro\Features\White_Label_Reports();
                $report = $reports->generate_report();
                
                // Save results to database
                $this->save_ai_results('reports', $report);
                
                // Get the saved formatted data to return
                $saved_data = get_option('wsa_ai_results_reports');
                
                wp_send_json_success($saved_data);
            } catch (Exception $e) {
                wp_send_json_error('Report generation error: ' . $e->getMessage());
            }
        } else {
            wp_send_json_error('AI White Label Reports feature not available. Please check if the Pro license is active.');
        }
    }
    
    /**
     * AJAX handler for getting AI status
     */
    public function ajax_get_ai_status() {
        // Try multiple nonce types for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce') || 
                          wp_verify_nonce($_POST['nonce'], 'wsa_ai_dashboard_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $statuses = array(
            'optimizer' => $this->get_optimizer_status(),
            'content' => $this->get_content_analyzer_status(),
            'analytics' => $this->get_predictive_analytics_status(),
            'pagespeed' => $this->get_pagespeed_status(),
            'reports' => $this->get_reports_status(),
            'ai-config' => $this->get_ai_config_status()
        );
        
        $stats = array(
            'last-optimization' => get_option('wsa_pro_last_optimization', 'Never'),
            'content-analyzed' => get_option('wsa_pro_content_analyzed', 0),
            'predictions-generated' => get_option('wsa_pro_predictions_generated', 0),
            'reports-generated' => get_option('wsa_pro_reports_generated', 0)
        );
        
        wp_send_json_success(array(
            'statuses' => $statuses,
            'stats' => $stats
        ));
    }
    
    /**
     * AJAX handler for getting AI recommendations
     */
    public function ajax_get_ai_recommendations() {
        // Try multiple nonce types for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce') || 
                          wp_verify_nonce($_POST['nonce'], 'wsa_ai_dashboard_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $recommendations = array();
        
        // Get recent recommendations from different features
        if (class_exists('\WSA_Pro\Features\AI_Automated_Optimizer')) {
            $optimizer = new \WSA_Pro\Features\AI_Automated_Optimizer();
            $opt_recs = $optimizer->get_optimization_recommendations();
            if (!empty($opt_recs) && !isset($opt_recs['error'])) {
                $recommendations['optimizer'] = $opt_recs;
            }
        }
        
        if (class_exists('\WSA_Pro\Features\AI_Predictive_Analytics')) {
            $analytics = new \WSA_Pro\Features\AI_Predictive_Analytics();
            $pred_recs = $analytics->get_traffic_prediction(7);
            if (!empty($pred_recs) && !isset($pred_recs['error'])) {
                $recommendations['analytics'] = $pred_recs;
            }
        }
        
        wp_send_json_success($recommendations);
    }

    /**
     * AJAX handler for getting stored AI results
     */
    public function ajax_get_ai_results() {
        // Try multiple nonce types for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce') || 
                          wp_verify_nonce($_POST['nonce'], 'wsa_ai_dashboard_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $feature = sanitize_text_field($_POST['feature'] ?? '');
        if (empty($feature)) {
            wp_send_json_error('No feature specified');
        }
        
        // Get stored results for the feature
        $results = get_option('wsa_ai_results_' . $feature);
        
        if (empty($results)) {
            wp_send_json_error('No results found for this feature');
        }
        
        wp_send_json_success($results);
    }

    /**
     * AJAX handler for checking if AI results exist
     */
    public function ajax_check_ai_results() {
        // Try multiple nonce types for compatibility
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce') || 
                          wp_verify_nonce($_POST['nonce'], 'wsa_ai_dashboard_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $feature = sanitize_text_field($_POST['feature'] ?? '');
        if (empty($feature)) {
            wp_send_json_error('No feature specified');
        }
        
        // Check if results exist for the feature
        $results = get_option('wsa_ai_results_' . $feature);
        $has_results = !empty($results) && (
            (isset($results['html']) && !empty($results['html'])) ||
            (isset($results['raw_data']) && !empty($results['raw_data'])) ||
            (isset($results['success']) && $results['success'] === true)
        );
        
        wp_send_json_success(array('has_results' => $has_results));
    }

    /**
     * AJAX handler for creating test AI results (for debugging)
     */
    public function ajax_create_test_results() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Create sample results for each feature
        $sample_results = array(
            array(
                'title' => 'Test Optimization Recommendation',
                'description' => 'This is a test recommendation to verify the display system is working correctly.',
                'priority' => 'high',
                'risk_level' => 'medium', 
                'impact' => 'high',
                'category' => 'performance',
                'automated' => true
            ),
            array(
                'title' => 'Another Test Recommendation',
                'description' => 'This is a second test recommendation with different priority levels.',
                'priority' => 'medium',
                'risk_level' => 'low',
                'impact' => 'medium', 
                'category' => 'security',
                'automated' => false
            )
        );
        
        $features = array('optimizer', 'content', 'analytics', 'pagespeed', 'reports');
        
        foreach ($features as $feature) {
            $this->save_ai_results($feature, $sample_results);
        }
        
        wp_send_json_success('Test results created for all features');
    }

    /**
     * Save AI analysis results to database
     */
    private function save_ai_results($feature, $results) {
        $option_name = 'wsa_ai_results_' . $feature;
        
        // Format the results for database storage with enhanced structure
        $formatted_results = array(
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'raw_data' => $results,
            'html' => $this->format_ai_results_for_display($results),
            'feature' => $feature,
            'version' => '1.0'
        );
        
        update_option($option_name, $formatted_results);
        
        // Update activity log
        $this->log_ai_activity($feature, 'Analysis completed', 'dashicons-yes-alt');
        
    }

    /**
     * Log AI activity
     */
    private function log_ai_activity($feature, $message, $icon = 'dashicons-admin-generic') {
        $activities = get_option('wsa_ai_activity_log', array());
        
        $new_activity = array(
            'timestamp' => current_time('timestamp'),
            'feature' => $feature,
            'message' => $message,
            'icon' => $icon
        );
        
        // Add to beginning of array
        array_unshift($activities, $new_activity);
        
        // Keep only last 50 activities
        $activities = array_slice($activities, 0, 50);
        
        update_option('wsa_ai_activity_log', $activities);
    }

    /**
     * Format AI results for display
     */
    private function format_ai_results_for_display($results) {
        if (empty($results)) {
            return '<div class="wsa-no-results"><p>No results available.</p></div>';
        }

        // Handle single result vs array of results
        if (!is_array($results) || !isset($results[0])) {
            // Convert single result to array format
            $results = is_array($results) ? array($results) : array(array('description' => (string)$results));
        }

        $html = '<div class="wsa-modal-results-container">';
        
        // Results summary
        $total_results = count($results);
        $html .= '<div class="wsa-results-summary">';
        $html .= '<span class="results-count">' . sprintf(__('%d recommendation(s) found', 'wp-site-advisory-pro'), $total_results) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="wsa-results-grid">';
        
        foreach ($results as $index => $result) {
            $priority_class = isset($result['priority']) ? 'priority-' . strtolower($result['priority']) : 'priority-medium';
            $risk_class = isset($result['risk_level']) ? 'risk-' . strtolower($result['risk_level']) : '';
            
            $html .= '<div class="wsa-modal-result-card ' . $priority_class . ' ' . $risk_class . '">';
            
            // Card header with title and badges
            $html .= '<div class="result-card-header">';
            
            // Title
            if (!empty($result['title'])) {
                $html .= '<h4 class="result-title">' . esc_html($result['title']) . '</h4>';
            } else {
                $html .= '<h4 class="result-title">Recommendation #' . ($index + 1) . '</h4>';
            }
            
            // Priority and Risk badges
            $html .= '<div class="result-badges">';
            if (!empty($result['priority'])) {
                $priority_color = strtolower($result['priority']) === 'high' ? 'danger' : (strtolower($result['priority']) === 'medium' ? 'warning' : 'success');
                $html .= '<span class="wsa-badge wsa-badge-' . $priority_color . '">Priority: ' . esc_html(ucfirst($result['priority'])) . '</span>';
            }
            if (!empty($result['risk_level'])) {
                $risk_color = strtolower($result['risk_level']) === 'high' ? 'danger' : (strtolower($result['risk_level']) === 'medium' ? 'warning' : 'info');
                $html .= '<span class="wsa-badge wsa-badge-' . $risk_color . '">Risk: ' . esc_html(ucfirst($result['risk_level'])) . '</span>';
            }
            if (!empty($result['impact'])) {
                $html .= '<span class="wsa-badge wsa-badge-info">Impact: ' . esc_html(ucfirst($result['impact'])) . '</span>';
            }
            $html .= '</div>';
            
            $html .= '</div>'; // Close card header
            
            // Card body
            $html .= '<div class="result-card-body">';
            
            // Description
            if (!empty($result['description'])) {
                $html .= '<div class="result-description">' . wp_kses_post(wpautop($result['description'])) . '</div>';
            }
            
            // Additional details
            $html .= '<div class="result-details">';
            
            // Category
            if (!empty($result['category'])) {
                $html .= '<div class="result-meta"><span class="meta-label">Category:</span> ' . esc_html(ucfirst(str_replace('_', ' ', $result['category']))) . '</div>';
            }
            
            // Status or automation info
            if (isset($result['automated']) && $result['automated']) {
                $html .= '<div class="result-automation"><span class="dashicons dashicons-update"></span> Can be automated</div>';
            }
            
            // Performance impact if available
            if (!empty($result['performance_impact'])) {
                $html .= '<div class="result-meta"><span class="meta-label">Performance Impact:</span> ' . esc_html($result['performance_impact']) . '</div>';
            }
            
            $html .= '</div>'; // Close result-details
            $html .= '</div>'; // Close card body
            $html .= '</div>'; // Close result card
        }
        
        $html .= '</div>'; // Close results grid
        $html .= '</div>'; // Close container
        
        return $html;
    }
}