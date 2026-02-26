<?php
/**
 * AI Predictive Analytics
 * 
 * Provides predictive insights for WordPress sites including traffic forecasting,
 * performance trend analysis, security risk predictions, and plugin compatibility
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class AI_Predictive_Analytics {
    
    private $analytics_data = array();
    private $prediction_models = array();
    private $trend_analysis = array();
    
    /**
     * Initialize predictive analytics
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_prediction_models();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule analytics collection
        add_action('wp', array($this, 'schedule_analytics_collection'));
        add_action('wsa_collect_analytics_data', array($this, 'collect_analytics_data'));
        
        // AJAX handlers
        add_action('wp_ajax_wsa_get_traffic_prediction', array($this, 'ajax_get_traffic_prediction'));
        add_action('wp_ajax_wsa_get_performance_trends', array($this, 'ajax_get_performance_trends'));
        add_action('wp_ajax_wsa_get_security_forecast', array($this, 'ajax_get_security_forecast'));
        add_action('wp_ajax_wsa_get_plugin_compatibility_prediction', array($this, 'ajax_get_plugin_compatibility_prediction'));
        add_action('wp_ajax_wsa_generate_predictive_report', array($this, 'ajax_generate_predictive_report'));
        
        // Hook into existing features for data collection
        add_action('wsa_pagespeed_analysis_complete', array($this, 'record_performance_data'), 10, 2);
        add_action('wsa_vulnerability_scan_complete', array($this, 'record_security_data'), 10, 2);
        
        // Track user behavior and site metrics
        add_action('wp_loaded', array($this, 'track_site_metrics'));
    }
    
    /**
     * Load prediction models and algorithms
     */
    private function load_prediction_models() {
        $this->prediction_models = array(
            'traffic' => array(
                'name' => 'Traffic Prediction',
                'algorithm' => 'linear_regression',
                'confidence_threshold' => 0.75,
                'prediction_window' => 30 // days
            ),
            'performance' => array(
                'name' => 'Performance Trends',
                'algorithm' => 'moving_average',
                'confidence_threshold' => 0.80,
                'prediction_window' => 14 // days
            ),
            'security' => array(
                'name' => 'Security Risk Assessment',
                'algorithm' => 'risk_scoring',
                'confidence_threshold' => 0.70,
                'prediction_window' => 7 // days
            ),
            'compatibility' => array(
                'name' => 'Plugin Compatibility',
                'algorithm' => 'compatibility_matrix',
                'confidence_threshold' => 0.85,
                'prediction_window' => 90 // days
            )
        );
    }
    
    /**
     * Get traffic predictions using AI analysis
     */
    public function get_traffic_prediction($days = 30) {
        $historical_data = $this->get_historical_traffic_data($days * 2); // Get double the period for analysis
        
        if (empty($historical_data)) {
            return array('error' => 'Insufficient historical data for prediction');
        }
        
        // Use AI to analyze patterns and predict traffic
        $ai_analysis = $this->get_ai_traffic_analysis($historical_data, $days);
        
        // Combine AI analysis with statistical methods
        $statistical_prediction = $this->calculate_statistical_trend($historical_data, $days);
        
        // Generate final prediction
        $prediction = $this->combine_predictions($ai_analysis, $statistical_prediction);
        
        return array(
            'success' => true,
            'prediction_period' => $days,
            'confidence_level' => $prediction['confidence'],
            'predicted_traffic' => $prediction['traffic_data'],
            'trend_direction' => $prediction['trend'],
            'key_insights' => $prediction['insights'],
            'recommendations' => $prediction['recommendations']
        );
    }
    
    /**
     * Get performance trend analysis and predictions
     */
    public function get_performance_trends($metric = 'all') {
        $performance_history = $this->get_performance_history();
        
        if (empty($performance_history)) {
            return array('error' => 'No performance data available');
        }
        
        $trends = array();
        $metrics_to_analyze = ($metric === 'all') ? 
            array('load_time', 'database_queries', 'memory_usage', 'error_rate') : 
            array($metric);
        
        foreach ($metrics_to_analyze as $current_metric) {
            $trend_data = $this->analyze_metric_trend($performance_history, $current_metric);
            $ai_insights = $this->get_ai_performance_insights($trend_data, $current_metric);
            
            $trends[$current_metric] = array(
                'current_value' => $trend_data['current'],
                'trend_direction' => $trend_data['direction'],
                'change_percentage' => $trend_data['change_percent'],
                'prediction_7_days' => $trend_data['prediction_7d'],
                'prediction_30_days' => $trend_data['prediction_30d'],
                'ai_insights' => $ai_insights,
                'recommended_actions' => $this->get_performance_recommendations($current_metric, $trend_data)
            );
        }
        
        return array(
            'success' => true,
            'analysis_period' => '30 days',
            'trends' => $trends,
            'overall_health_score' => $this->calculate_performance_health_score($trends),
            'priority_actions' => $this->get_priority_performance_actions($trends)
        );
    }
    
    /**
     * Get security risk forecast
     */
    public function get_security_forecast() {
        $security_data = $this->get_security_baseline();
        $vulnerability_history = $this->get_vulnerability_history();
        
        // Analyze current security posture
        $current_risks = $this->analyze_current_security_risks($security_data);
        
        // Predict future risks using AI
        $ai_forecast = $this->get_ai_security_forecast($security_data, $vulnerability_history);
        
        // Calculate risk scores for different categories
        $risk_categories = array(
            'plugin_vulnerabilities' => $this->calculate_plugin_vulnerability_risk(),
            'theme_security' => $this->calculate_theme_security_risk(),
            'user_access' => $this->calculate_user_access_risk(),
            'server_security' => $this->calculate_server_security_risk(),
            'data_protection' => $this->calculate_data_protection_risk()
        );
        
        return array(
            'success' => true,
            'overall_risk_score' => $this->calculate_overall_risk_score($risk_categories),
            'risk_categories' => $risk_categories,
            'predicted_threats' => $ai_forecast['threats'],
            'threat_timeline' => $ai_forecast['timeline'],
            'mitigation_strategies' => $ai_forecast['mitigations'],
            'monitoring_recommendations' => $this->get_security_monitoring_recommendations($risk_categories)
        );
    }
    
    /**
     * Get plugin compatibility predictions
     */
    public function get_plugin_compatibility_prediction($target_plugin = null) {
        $active_plugins = get_option('active_plugins', array());
        $plugin_data = $this->get_detailed_plugin_data($active_plugins);
        
        // Get compatibility matrix from historical data and AI analysis
        $compatibility_matrix = $this->build_compatibility_matrix($plugin_data);
        
        if ($target_plugin) {
            // Specific plugin compatibility check
            return $this->analyze_specific_plugin_compatibility($target_plugin, $plugin_data, $compatibility_matrix);
        }
        
        // Overall compatibility analysis
        $compatibility_analysis = array();
        
        foreach ($active_plugins as $plugin_file) {
            $plugin_slug = dirname($plugin_file);
            $compatibility_score = $this->calculate_plugin_compatibility_score($plugin_file, $plugin_data, $compatibility_matrix);
            
            $compatibility_analysis[$plugin_slug] = array(
                'name' => $plugin_data[$plugin_file]['Name'] ?? $plugin_slug,
                'compatibility_score' => $compatibility_score,
                'risk_level' => $this->get_risk_level_from_score($compatibility_score),
                'potential_conflicts' => $this->identify_potential_conflicts($plugin_file, $plugin_data),
                'update_recommendations' => $this->get_update_recommendations($plugin_file, $plugin_data)
            );
        }
        
        // AI-powered future compatibility predictions
        $future_predictions = $this->get_ai_compatibility_predictions($plugin_data);
        
        return array(
            'success' => true,
            'analysis_date' => current_time('Y-m-d H:i:s'),
            'total_plugins' => count($active_plugins),
            'overall_compatibility_score' => $this->calculate_overall_compatibility_score($compatibility_analysis),
            'plugin_analysis' => $compatibility_analysis,
            'future_predictions' => $future_predictions,
            'recommended_actions' => $this->get_compatibility_recommendations($compatibility_analysis)
        );
    }
    
    /**
     * Get AI analysis for traffic patterns
     */
    private function get_ai_traffic_analysis($historical_data, $prediction_days) {
        $openai_key = $this->get_openai_api_key();
        if (!$openai_key) {
            return $this->fallback_traffic_analysis($historical_data, $prediction_days);
        }
        
        $prompt = $this->build_traffic_analysis_prompt($historical_data, $prediction_days);
        $ai_response = $this->query_openai($prompt, $openai_key);
        
        if ($ai_response) {
            return $this->parse_traffic_analysis_response($ai_response);
        }
        
        return $this->fallback_traffic_analysis($historical_data, $prediction_days);
    }
    
    /**
     * Build traffic analysis prompt for AI
     */
    private function build_traffic_analysis_prompt($historical_data, $prediction_days) {
        $data_summary = $this->summarize_traffic_data($historical_data);
        
        $prompt = "Analyze the following website traffic data and provide predictions:\n\n";
        $prompt .= "Historical Traffic Data (last " . count($historical_data) . " days):\n";
        $prompt .= "- Average daily visitors: " . $data_summary['avg_visitors'] . "\n";
        $prompt .= "- Peak day visitors: " . $data_summary['peak_visitors'] . "\n";
        $prompt .= "- Lowest day visitors: " . $data_summary['min_visitors'] . "\n";
        $prompt .= "- Overall trend: " . $data_summary['trend'] . "\n";
        $prompt .= "- Seasonal patterns: " . $data_summary['patterns'] . "\n";
        
        $prompt .= "\nPlease provide:\n";
        $prompt .= "1. Traffic prediction for the next {$prediction_days} days\n";
        $prompt .= "2. Confidence level (0-100%)\n";
        $prompt .= "3. Key factors influencing the prediction\n";
        $prompt .= "4. Recommended actions to improve traffic\n";
        $prompt .= "5. Potential risks or opportunities\n\n";
        $prompt .= "Format your response as JSON with keys: prediction, confidence, factors, recommendations, insights";
        
        return $prompt;
    }
    
    /**
     * Query OpenAI for analysis
     */
    private function query_openai($prompt, $api_key) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a web analytics expert specializing in traffic prediction and performance analysis. Provide accurate, data-driven insights.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 1000,
                'temperature' => 0.3
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return false;
    }
    
    /**
     * Data collection and analysis methods
     */
    public function collect_analytics_data() {
        $current_data = array(
            'timestamp' => current_time('timestamp'),
            'visitors' => $this->get_current_visitor_count(),
            'page_views' => $this->get_current_pageview_count(),
            'bounce_rate' => $this->calculate_current_bounce_rate(),
            'load_time' => $this->measure_current_load_time(),
            'memory_usage' => memory_get_peak_usage(true),
            'active_plugins' => count(get_option('active_plugins', array())),
            'database_queries' => get_num_queries(),
            'error_count' => $this->count_recent_errors()
        );
        
        // Store data for trend analysis
        $analytics_history = get_option('wsa_pro_analytics_history', array());
        $analytics_history[] = $current_data;
        
        // Keep only last 90 days of data
        $cutoff_time = current_time('timestamp') - (90 * DAY_IN_SECONDS);
        $analytics_history = array_filter($analytics_history, function($data) use ($cutoff_time) {
            return $data['timestamp'] > $cutoff_time;
        });
        
        update_option('wsa_pro_analytics_history', $analytics_history);
        
        return $current_data;
    }
    
    /**
     * Schedule analytics collection
     */
    public function schedule_analytics_collection() {
        if (!wp_next_scheduled('wsa_collect_analytics_data')) {
            wp_schedule_event(time(), 'hourly', 'wsa_collect_analytics_data');
        }
    }
    
    /**
     * Helper methods for data gathering and analysis
     */
    private function get_historical_traffic_data($days) {
        $analytics_history = get_option('wsa_pro_analytics_history', array());
        $cutoff_time = current_time('timestamp') - ($days * DAY_IN_SECONDS);
        
        return array_filter($analytics_history, function($data) use ($cutoff_time) {
            return $data['timestamp'] > $cutoff_time;
        });
    }
    
    private function get_performance_history() {
        $analytics_history = get_option('wsa_pro_analytics_history', array());
        
        // Extract performance metrics
        return array_map(function($data) {
            return array(
                'timestamp' => $data['timestamp'],
                'load_time' => $data['load_time'] ?? 0,
                'memory_usage' => $data['memory_usage'] ?? 0,
                'database_queries' => $data['database_queries'] ?? 0,
                'error_count' => $data['error_count'] ?? 0
            );
        }, $analytics_history);
    }
    
    private function calculate_statistical_trend($data, $prediction_days) {
        if (count($data) < 7) {
            return array('confidence' => 0.3, 'trend' => 'insufficient_data');
        }
        
        // Simple linear regression for trend analysis
        $x_values = array();
        $y_values = array();
        
        foreach ($data as $index => $point) {
            $x_values[] = $index;
            $y_values[] = $point['visitors'] ?? 0;
        }
        
        $slope = $this->calculate_linear_regression_slope($x_values, $y_values);
        $intercept = $this->calculate_linear_regression_intercept($x_values, $y_values, $slope);
        
        // Generate predictions
        $predictions = array();
        $start_x = count($data);
        
        for ($i = 0; $i < $prediction_days; $i++) {
            $predicted_value = $slope * ($start_x + $i) + $intercept;
            $predictions[] = max(0, round($predicted_value)); // Ensure non-negative
        }
        
        $trend_direction = $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable');
        
        return array(
            'confidence' => min(0.8, max(0.4, count($data) / 30)), // Higher confidence with more data
            'trend' => $trend_direction,
            'predictions' => $predictions,
            'slope' => $slope
        );
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_get_traffic_prediction() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        $prediction = $this->get_traffic_prediction($days);
        
        wp_send_json_success($prediction);
    }
    
    public function ajax_get_performance_trends() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $metric = isset($_POST['metric']) ? sanitize_text_field($_POST['metric']) : 'all';
        $trends = $this->get_performance_trends($metric);
        
        wp_send_json_success($trends);
    }
    
    public function ajax_get_security_forecast() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $forecast = $this->get_security_forecast();
        
        wp_send_json_success($forecast);
    }
    
    public function ajax_get_plugin_compatibility_prediction() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $target_plugin = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : null;
        $prediction = $this->get_plugin_compatibility_prediction($target_plugin);
        
        wp_send_json_success($prediction);
    }
    
    /**
     * Utility methods
     */
    private function get_openai_api_key() {
        $free_key = get_option('wsa_openai_api_key');
        if (!empty($free_key)) {
            return $free_key;
        }
        
        return get_option('wsa_pro_openai_api_key');
    }
    
    private function calculate_linear_regression_slope($x_values, $y_values) {
        $n = count($x_values);
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_x_squared = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_x_squared += $x_values[$i] * $x_values[$i];
        }
        
        return ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x_squared - $sum_x * $sum_x);
    }
    
    private function calculate_linear_regression_intercept($x_values, $y_values, $slope) {
        $n = count($x_values);
        $mean_x = array_sum($x_values) / $n;
        $mean_y = array_sum($y_values) / $n;
        
        return $mean_y - $slope * $mean_x;
    }
    
    /**
     * Placeholder methods for data gathering (to be implemented based on specific requirements)
     */
    private function get_current_visitor_count() {
        // Integration with analytics plugins or custom tracking
        return rand(50, 500); // Placeholder
    }
    
    private function get_current_pageview_count() {
        // Integration with analytics
        return rand(100, 1000); // Placeholder
    }
    
    /**
     * Track site metrics
     */
    public function track_site_metrics() {
        // Only track if analytics is enabled
        if (!get_option('wsa_pro_enable_analytics_tracking', false)) {
            return;
        }
        
        // Store basic metrics for trend analysis
        $metrics = array(
            'timestamp' => time(),
            'active_users' => $this->get_active_users_count(),
            'page_views' => $this->get_recent_page_views(),
            'bounce_rate' => $this->calculate_current_bounce_rate(),
            'load_time' => $this->measure_current_load_time(),
            'errors' => $this->count_recent_errors()
        );
        
        // Store in transient for processing
        $stored_metrics = get_transient('wsa_site_metrics_buffer') ?: array();
        $stored_metrics[] = $metrics;
        
        // Keep only last 100 entries
        if (count($stored_metrics) > 100) {
            $stored_metrics = array_slice($stored_metrics, -100);
        }
        
        set_transient('wsa_site_metrics_buffer', $stored_metrics, DAY_IN_SECONDS);
    }
    
    /**
     * Record performance data from PageSpeed analysis
     */
    public function record_performance_data($results, $url) {
        if (empty($results) || isset($results['error'])) {
            return;
        }
        
        $performance_data = array(
            'timestamp' => time(),
            'url' => $url,
            'desktop_score' => $results['desktop']['scores']['performance']['score'] ?? 0,
            'mobile_score' => $results['mobile']['scores']['performance']['score'] ?? 0,
            'load_time' => $this->extract_load_time($results),
            'cls_score' => $this->extract_cls_score($results)
        );
        
        // Store historical performance data
        $historical = get_option('wsa_performance_history', array());
        $historical[] = $performance_data;
        
        // Keep only last 90 days of data
        $cutoff = time() - (90 * DAY_IN_SECONDS);
        $historical = array_filter($historical, function($entry) use ($cutoff) {
            return $entry['timestamp'] > $cutoff;
        });
        
        update_option('wsa_performance_history', $historical);
    }
    
    /**
     * Record security data from vulnerability scans
     */
    public function record_security_data($results, $scan_type) {
        if (empty($results)) {
            return;
        }
        
        $security_data = array(
            'timestamp' => time(),
            'scan_type' => $scan_type,
            'vulnerabilities_found' => count($results['vulnerabilities'] ?? array()),
            'severity_breakdown' => $this->analyze_vulnerability_severity($results),
            'plugins_affected' => count($results['plugins'] ?? array()),
            'themes_affected' => count($results['themes'] ?? array())
        );
        
        // Store historical security data
        $historical = get_option('wsa_security_history', array());
        $historical[] = $security_data;
        
        // Keep only last 180 days of data
        $cutoff = time() - (180 * DAY_IN_SECONDS);
        $historical = array_filter($historical, function($entry) use ($cutoff) {
            return $entry['timestamp'] > $cutoff;
        });
        
        update_option('wsa_security_history', $historical);
    }
    
    private function extract_load_time($results) {
        // Extract load time from PageSpeed results
        if (isset($results['mobile']['metrics']['first-contentful-paint']['numericValue'])) {
            return $results['mobile']['metrics']['first-contentful-paint']['numericValue'] / 1000;
        }
        return 0;
    }
    
    private function extract_cls_score($results) {
        // Extract Cumulative Layout Shift score
        if (isset($results['mobile']['metrics']['cumulative-layout-shift']['numericValue'])) {
            return $results['mobile']['metrics']['cumulative-layout-shift']['numericValue'];
        }
        return 0;
    }
    
    private function analyze_vulnerability_severity($results) {
        $severity_count = array('low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0);
        
        if (isset($results['vulnerabilities'])) {
            foreach ($results['vulnerabilities'] as $vuln) {
                $severity = $vuln['severity'] ?? 'medium';
                if (isset($severity_count[$severity])) {
                    $severity_count[$severity]++;
                }
            }
        }
        
        return $severity_count;
    }

    private function calculate_current_bounce_rate() {
        return rand(30, 70) / 100; // Placeholder
    }
    
    private function measure_current_load_time() {
        return rand(15, 40) / 10; // Placeholder: 1.5-4.0 seconds
    }
    
    private function count_recent_errors() {
        // Check error logs for recent errors
        return rand(0, 5); // Placeholder
    }
}