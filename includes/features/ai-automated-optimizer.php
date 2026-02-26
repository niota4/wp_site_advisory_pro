<?php
/**
 * AI Automated Optimizer
 * 
 * Provides intelligent automation for WordPress optimization tasks including
 * database cleanup, image optimization, cache management, and performance fixes
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class AI_Automated_Optimizer {
    
    private $optimization_tasks = array();
    private $scheduled_optimizations = array();
    private $optimization_history = array();
    
    /**
     * Initialize the automated optimizer
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_optimization_tasks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule automated optimizations
        add_action('wp', array($this, 'schedule_automated_optimizations'));
        add_action('wsa_run_automated_optimization', array($this, 'run_scheduled_optimization'), 10, 2);
        
        // AJAX handlers for manual optimizations
        add_action('wp_ajax_wsa_run_optimization', array($this, 'ajax_run_optimization'));
        add_action('wp_ajax_wsa_get_optimization_status', array($this, 'ajax_get_optimization_status'));
        add_action('wp_ajax_wsa_get_optimization_recommendations', array($this, 'ajax_get_optimization_recommendations'));
        add_action('wp_ajax_wsa_apply_optimization_fix', array($this, 'ajax_apply_optimization_fix'));
        
        // Hook into existing PageSpeed analysis for automated fixes
        add_action('wsa_pagespeed_analysis_complete', array($this, 'analyze_pagespeed_for_automation'), 10, 2);
        
        // Database cleanup scheduling
        add_action('wp_loaded', array($this, 'maybe_schedule_database_cleanup'));
        add_action('wsa_automated_database_cleanup', array($this, 'run_database_cleanup'));
    }
    
    /**
     * Load available optimization tasks
     */
    private function load_optimization_tasks() {
        $this->optimization_tasks = array(
            'database_cleanup' => array(
                'name' => 'Database Cleanup',
                'description' => 'Remove spam comments, revisions, transients, and optimize database tables',
                'category' => 'database',
                'impact' => 'medium',
                'automated' => true,
                'risk_level' => 'low',
                'estimated_time' => 30
            ),
            'image_optimization' => array(
                'name' => 'Image Optimization',
                'description' => 'Compress and optimize images for faster loading',
                'category' => 'media',
                'impact' => 'high',
                'automated' => true,
                'risk_level' => 'low',
                'estimated_time' => 60
            ),
            'cache_optimization' => array(
                'name' => 'Cache Configuration',
                'description' => 'Optimize caching settings for better performance',
                'category' => 'caching',
                'impact' => 'high',
                'automated' => true,
                'risk_level' => 'medium',
                'estimated_time' => 15
            ),
            'plugin_optimization' => array(
                'name' => 'Plugin Optimization',
                'description' => 'Identify and disable unnecessary plugins or features',
                'category' => 'plugins',
                'impact' => 'medium',
                'automated' => false,
                'risk_level' => 'high',
                'estimated_time' => 45
            ),
            'css_js_optimization' => array(
                'name' => 'CSS/JS Optimization',
                'description' => 'Minify and combine CSS/JS files for faster loading',
                'category' => 'assets',
                'impact' => 'medium',
                'automated' => true,
                'risk_level' => 'medium',
                'estimated_time' => 20
            ),
            'font_optimization' => array(
                'name' => 'Font Optimization',
                'description' => 'Optimize web font loading and reduce font-related CLS',
                'category' => 'fonts',
                'impact' => 'medium',
                'automated' => true,
                'risk_level' => 'low',
                'estimated_time' => 10
            )
        );
    }
    
    /**
     * Schedule automated optimizations
     */
    public function schedule_automated_optimizations() {
        // Only schedule if automation is enabled
        if (!get_option('wsa_pro_enable_auto_optimization', false)) {
            return;
        }
        
        // Check if optimizations are already scheduled
        $scheduled_optimizations = wp_get_scheduled_event('wsa_run_automated_optimization');
        if ($scheduled_optimizations) {
            return; // Already scheduled
        }
        
        // Schedule daily optimization check
        wp_schedule_event(time(), 'daily', 'wsa_run_automated_optimization');
        
        // Log scheduling
    }
    
    /**
     * Run scheduled optimization
     */
    public function run_scheduled_optimization($optimization_type = null, $options = array()) {
        // Safety check - don't run if automation is disabled
        if (!get_option('wsa_pro_enable_auto_optimization', false)) {
            return;
        }
        
        // Get current site performance
        $performance_data = $this->get_performance_baseline();
        
        // Run automated optimizations based on performance issues
        $optimizations_run = array();
        
        // Database cleanup (if enabled and needed)
        if ($this->should_run_database_cleanup($performance_data)) {
            $result = $this->run_database_cleanup();
            $optimizations_run['database_cleanup'] = $result;
        }
        
        // Image optimization (if enabled and needed)
        if ($this->should_run_image_optimization($performance_data)) {
            $result = $this->run_image_optimization();
            $optimizations_run['image_optimization'] = $result;
        }
        
        // Cache optimization (if enabled and needed)
        if ($this->should_run_cache_optimization($performance_data)) {
            $result = $this->run_cache_optimization();
            $optimizations_run['cache_optimization'] = $result;
        }
        
        // Log the results
        if (!empty($optimizations_run)) {
        }
        
        // Store results for reporting
        update_option('wsa_pro_last_auto_optimization', array(
            'timestamp' => time(),
            'optimizations' => $optimizations_run,
            'performance_before' => $performance_data
        ));
        
        return $optimizations_run;
    }
    
    /**
     * Get AI-powered optimization recommendations
     */
    public function get_optimization_recommendations() {
        $recommendations = array();
        
        // Analyze site performance metrics
        $performance_data = $this->get_performance_baseline();
        
        // Get OpenAI API key
        $openai_key = $this->get_openai_api_key();
        if (!$openai_key) {
            return array('error' => 'OpenAI API key not configured');
        }
        
        // Prepare AI prompt with site data
        $prompt = $this->build_optimization_prompt($performance_data);
        
        // Get AI recommendations
        $ai_recommendations = $this->get_ai_recommendations($prompt, $openai_key);
        
        if ($ai_recommendations) {
            $recommendations = $this->parse_ai_recommendations($ai_recommendations);
        }
        
        // Add automatic task recommendations
        $recommendations = array_merge($recommendations, $this->get_automatic_recommendations($performance_data));
        
        return $recommendations;
    }
    
    /**
     * Get performance baseline for analysis
     */
    private function get_performance_baseline() {
        $data = array(
            'page_load_time' => $this->measure_page_load_time(),
            'database_size' => $this->get_database_size(),
            'plugin_count' => count(get_option('active_plugins', array())),
            'theme_data' => $this->get_theme_performance_data(),
            'media_stats' => $this->get_media_statistics(),
            'cache_status' => $this->get_cache_status(),
            'recent_errors' => $this->get_recent_performance_errors()
        );
        
        return $data;
    }
    
    /**
     * Build AI prompt for optimization recommendations
     */
    private function build_optimization_prompt($performance_data) {
        $site_url = get_site_url();
        $wp_version = get_bloginfo('version');
        
        $prompt = "As a WordPress performance expert, analyze this site and provide specific optimization recommendations:\n\n";
        $prompt .= "Site: {$site_url}\n";
        $prompt .= "WordPress Version: {$wp_version}\n";
        $prompt .= "Current Performance Data:\n";
        $prompt .= "- Page Load Time: {$performance_data['page_load_time']}s\n";
        $prompt .= "- Database Size: " . size_format($performance_data['database_size']) . "\n";
        $prompt .= "- Active Plugins: {$performance_data['plugin_count']}\n";
        $prompt .= "- Cache Status: {$performance_data['cache_status']}\n";
        
        if (!empty($performance_data['recent_errors'])) {
            $prompt .= "- Recent Performance Issues: " . implode(', ', $performance_data['recent_errors']) . "\n";
        }
        
        $prompt .= "\nPlease provide:\n";
        $prompt .= "1. Top 3 priority optimizations with impact assessment\n";
        $prompt .= "2. Specific implementation steps for each recommendation\n";
        $prompt .= "3. Risk level (low/medium/high) for each optimization\n";
        $prompt .= "4. Expected performance improvement percentage\n";
        $prompt .= "5. Any potential compatibility concerns\n\n";
        $prompt .= "Focus on actionable, specific recommendations that can be automated or easily implemented.";
        
        return $prompt;
    }
    
    /**
     * Get AI recommendations from OpenAI
     */
    private function get_ai_recommendations($prompt, $api_key) {
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
                        'content' => 'You are a WordPress performance optimization expert. Provide specific, actionable recommendations in a structured format.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 1500,
                'temperature' => 0.7
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
     * Parse AI recommendations into structured format
     */
    private function parse_ai_recommendations($ai_response) {
        $recommendations = array();
        
        // Simple parsing - in production, you might want more sophisticated parsing
        $lines = explode("\n", $ai_response);
        $current_recommendation = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for numbered recommendations
            if (preg_match('/^(\d+)\.?\s*(.+)/', $line, $matches)) {
                if ($current_recommendation) {
                    $recommendations[] = $current_recommendation;
                }
                
                $current_recommendation = array(
                    'title' => $matches[2],
                    'description' => '',
                    'priority' => $matches[1],
                    'category' => 'ai_recommendation',
                    'automated' => false,
                    'risk_level' => 'medium',
                    'impact' => 'medium'
                );
            } elseif ($current_recommendation && !empty($line)) {
                $current_recommendation['description'] .= $line . ' ';
            }
        }
        
        if ($current_recommendation) {
            $recommendations[] = $current_recommendation;
        }
        
        return $recommendations;
    }
    
    /**
     * Get automatic recommendations based on performance data
     */
    private function get_automatic_recommendations($performance_data) {
        $recommendations = array();
        
        // Database cleanup recommendation
        if ($performance_data['database_size'] > 100 * 1024 * 1024) { // 100MB
            $recommendations[] = array(
                'title' => 'Database Cleanup Required',
                'description' => 'Your database is large and could benefit from cleanup. Remove spam, revisions, and optimize tables.',
                'category' => 'database',
                'automated' => true,
                'risk_level' => 'low',
                'impact' => 'medium',
                'task_id' => 'database_cleanup'
            );
        }
        
        // Plugin optimization
        if ($performance_data['plugin_count'] > 20) {
            $recommendations[] = array(
                'title' => 'Review Plugin Usage',
                'description' => 'You have many active plugins. Consider deactivating unused ones.',
                'category' => 'plugins',
                'automated' => false,
                'risk_level' => 'medium',
                'impact' => 'medium',
                'task_id' => 'plugin_optimization'
            );
        }
        
        // Cache optimization
        if ($performance_data['cache_status'] !== 'optimized') {
            $recommendations[] = array(
                'title' => 'Optimize Caching',
                'description' => 'Improve your caching configuration for better performance.',
                'category' => 'caching',
                'automated' => true,
                'risk_level' => 'low',
                'impact' => 'high',
                'task_id' => 'cache_optimization'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Run optimization task
     */
    public function run_optimization_task($task_id, $options = array()) {
        if (!isset($this->optimization_tasks[$task_id])) {
            return array('success' => false, 'message' => 'Unknown optimization task');
        }
        
        $task = $this->optimization_tasks[$task_id];
        $result = array('success' => false, 'message' => 'Task not implemented');
        
        switch ($task_id) {
            case 'database_cleanup':
                $result = $this->run_database_cleanup($options);
                break;
                
            case 'image_optimization':
                $result = $this->run_image_optimization($options);
                break;
                
            case 'cache_optimization':
                $result = $this->run_cache_optimization($options);
                break;
                
            case 'css_js_optimization':
                $result = $this->run_asset_optimization($options);
                break;
                
            case 'font_optimization':
                $result = $this->run_font_optimization($options);
                break;
        }
        
        // Log optimization result
        $this->log_optimization_result($task_id, $result, $options);
        
        return $result;
    }
    
    /**
     * Maybe schedule database cleanup
     */
    public function maybe_schedule_database_cleanup() {
        // Only schedule if automation is enabled and event isn't already scheduled
        if (get_option('wsa_pro_enable_auto_optimization', false) && !wp_next_scheduled('wsa_automated_database_cleanup')) {
            // Schedule weekly database cleanup
            wp_schedule_event(time(), 'weekly', 'wsa_automated_database_cleanup');
        }
    }

    /**
     * Run database cleanup
     */
    public function run_database_cleanup($options = array()) {
        global $wpdb;
        
        $cleaned_items = array();
        $errors = array();
        
        try {
            // Clean spam comments
            if (!isset($options['skip_spam']) || !$options['skip_spam']) {
                $spam_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                if ($spam_count > 0) {
                    $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                    $cleaned_items[] = "Removed {$spam_count} spam comments";
                }
            }
            
            // Clean post revisions (keep last 3)
            if (!isset($options['skip_revisions']) || !$options['skip_revisions']) {
                $revisions_query = "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision' AND ID NOT IN (
                    SELECT * FROM (
                        SELECT ID FROM {$wpdb->posts} 
                        WHERE post_type = 'revision' 
                        ORDER BY post_date DESC 
                        LIMIT " . (isset($options['keep_revisions']) ? intval($options['keep_revisions']) : 3) . "
                    ) as keep_revisions
                )";
                
                $revision_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
                $wpdb->query($revisions_query);
                $cleaned_items[] = "Cleaned old post revisions";
            }
            
            // Clean expired transients
            if (!isset($options['skip_transients']) || !$options['skip_transients']) {
                $transient_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
                if ($transient_count > 0) {
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'");
                    $cleaned_items[] = "Removed {$transient_count} expired transients";
                }
            }
            
            // Optimize database tables
            if (!isset($options['skip_optimize']) || !$options['skip_optimize']) {
                $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
                $optimized_tables = 0;
                
                foreach ($tables as $table) {
                    $table_name = $table[0];
                    if (strpos($table_name, $wpdb->prefix) === 0) {
                        $wpdb->query("OPTIMIZE TABLE `{$table_name}`");
                        $optimized_tables++;
                    }
                }
                
                $cleaned_items[] = "Optimized {$optimized_tables} database tables";
            }
            
            // Clear WordPress object cache
            wp_cache_flush();
            
            return array(
                'success' => true,
                'message' => 'Database cleanup completed successfully',
                'details' => $cleaned_items,
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Database cleanup failed: ' . $e->getMessage(),
                'details' => $cleaned_items,
                'errors' => array($e->getMessage())
            );
        }
    }
    
    /**
     * Run image optimization
     */
    private function run_image_optimization($options = array()) {
        $results = array(
            'success' => true,
            'message' => 'Image optimization completed',
            'details' => array(),
            'errors' => array()
        );
        
        // Get unoptimized images
        $images = $this->get_unoptimized_images($options);
        
        if (empty($images)) {
            $results['message'] = 'No images found that need optimization';
            return $results;
        }
        
        $optimized_count = 0;
        $total_savings = 0;
        
        foreach ($images as $image_id) {
            $optimization_result = $this->optimize_single_image($image_id, $options);
            
            if ($optimization_result['success']) {
                $optimized_count++;
                $total_savings += $optimization_result['size_saved'];
                $results['details'][] = "Optimized image ID {$image_id}: " . size_format($optimization_result['size_saved']) . " saved";
            } else {
                $results['errors'][] = "Failed to optimize image ID {$image_id}: " . $optimization_result['message'];
            }
            
            // Prevent timeout on large batches
            if ($optimized_count >= 50) {
                $results['details'][] = "Batch limit reached. Additional images may need separate optimization.";
                break;
            }
        }
        
        $results['message'] = "Optimized {$optimized_count} images, saved " . size_format($total_savings);
        
        return $results;
    }
    
    /**
     * Helper methods for performance measurement and data gathering
     */
    private function measure_page_load_time() {
        // Simulate page load time measurement
        // In a real implementation, you might use actual performance monitoring
        return rand(15, 45) / 10; // 1.5-4.5 seconds
    }
    
    private function get_database_size() {
        global $wpdb;
        
        $result = $wpdb->get_row("
            SELECT 
                SUM(data_length + index_length) as size 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        
        return $result ? $result->size : 0;
    }
    
    private function get_theme_performance_data() {
        $theme = wp_get_theme();
        return array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template_files' => count(glob(get_template_directory() . '/*.php'))
        );
    }
    
    private function get_media_statistics() {
        $upload_dir = wp_upload_dir();
        $total_size = 0;
        
        if (is_dir($upload_dir['basedir'])) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($upload_dir['basedir'])
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total_size += $file->getSize();
                }
            }
        }
        
        return array(
            'total_size' => $total_size,
            'upload_path' => $upload_dir['basedir']
        );
    }
    
    private function get_cache_status() {
        // Check for common caching plugins
        $caching_plugins = array(
            'wp-rocket/wp-rocket.php',
            'w3-total-cache/w3-total-cache.php',
            'wp-super-cache/wp-cache.php',
            'litespeed-cache/litespeed-cache.php'
        );
        
        foreach ($caching_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                return 'active';
            }
        }
        
        return 'none';
    }
    
    private function get_recent_performance_errors() {
        // In a real implementation, you'd check error logs
        return array();
    }
    
    private function get_openai_api_key() {
        // Check free plugin first, then Pro plugin
        $free_key = get_option('wsa_openai_api_key');
        if (!empty($free_key)) {
            return $free_key;
        }
        
        return get_option('wsa_pro_openai_api_key');
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_get_optimization_recommendations() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $recommendations = $this->get_optimization_recommendations();
        
        wp_send_json_success($recommendations);
    }
    
    public function ajax_run_optimization() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $task_id = sanitize_text_field($_POST['task_id']);
        $options = isset($_POST['options']) ? $_POST['options'] : array();
        
        $result = $this->run_optimization_task($task_id, $options);
        
        wp_send_json($result);
    }
    
    /**
     * Log optimization results for tracking
     */
    private function log_optimization_result($task_id, $result, $options) {
        $log_entry = array(
            'timestamp' => current_time('timestamp'),
            'task_id' => $task_id,
            'success' => $result['success'],
            'message' => $result['message'],
            'options' => $options
        );
        
        $history = get_option('wsa_pro_optimization_history', array());
        array_unshift($history, $log_entry);
        
        // Keep only last 50 entries
        $history = array_slice($history, 0, 50);
        
        update_option('wsa_pro_optimization_history', $history);
    }
}