<?php
/**
 * PageSpeed Analysis with AI
 * 
 * Integrates with Google PageSpeed Insights API and provides AI-powered performance recommendations
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class PageSpeed_Analysis {
    
    const PAGESPEED_API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    const CACHE_DURATION = 12 * HOUR_IN_SECONDS; // 12 hours
    
    private $api_key;
    
    /**
     * Initialize the PageSpeed analyzer
     */
    public function __construct() {
        $this->api_key = $this->get_pagespeed_api_key();
        $this->init_hooks();
    }
    
    /**
     * Get PageSpeed API key (check free plugin first, then Pro)
     */
    private function get_pagespeed_api_key() {
        // First check if the free plugin has a PageSpeed API key
        $free_key = get_option('wsa_pagespeed_api_key');
        if (!empty($free_key)) {
            return $free_key;
        }
        
        // Fall back to Pro plugin key
        return get_option('wsa_pro_pagespeed_api_key');
    }
    
    /**
     * Get OpenAI API key (check free plugin first, then Pro)
     */
    private function get_openai_api_key() {
        // First check if the free plugin has an OpenAI API key
        $free_key = get_option('wsa_openai_api_key');
        if (!empty($free_key)) {
            return $free_key;
        }
        
        // Fall back to Pro plugin key
        return get_option('wsa_pro_openai_api_key');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule weekly performance checks
        add_action('wp', array($this, 'schedule_performance_check'));
        add_action('wsa_weekly_performance_check', array($this, 'run_performance_analysis'));
        
        // AJAX handlers
        add_action('wp_ajax_wsa_run_pagespeed_analysis', array($this, 'ajax_run_pagespeed_analysis'));
        add_action('wp_ajax_wsa_get_performance_report', array($this, 'ajax_get_performance_report'));
        add_action('wp_ajax_wsa_get_ai_performance_insights', array($this, 'ajax_get_ai_performance_insights'));
        
        // New automation AJAX handlers
        add_action('wp_ajax_wsa_apply_pagespeed_fixes', array($this, 'ajax_apply_pagespeed_fixes'));
        add_action('wp_ajax_wsa_get_automated_optimizations', array($this, 'ajax_get_automated_optimizations'));
        add_action('wp_ajax_wsa_optimize_images_bulk', array($this, 'ajax_optimize_images_bulk'));
        add_action('wp_ajax_wsa_setup_caching', array($this, 'ajax_setup_caching'));
    }
    
    /**
     * ===========================
     * NEW AUTOMATION METHODS
     * ===========================
     */
    
    /**
     * Get automated optimization recommendations with implementation options
     */
    public function get_automated_optimizations($performance_data) {
        $optimizations = array();
        
        // Analyze performance data and suggest automated fixes
        if (isset($performance_data['desktop']['score']) && $performance_data['desktop']['score'] < 70) {
            $optimizations[] = array(
                'type' => 'caching',
                'priority' => 'high',
                'title' => 'Enable Advanced Caching',
                'description' => 'Set up optimized caching to reduce server response times',
                'automated' => true,
                'estimated_improvement' => '20-40%',
                'implementation' => 'setup_caching'
            );
        }
        
        // Image optimization recommendation
        $large_images = $this->detect_unoptimized_images($performance_data);
        if (!empty($large_images)) {
            $optimizations[] = array(
                'type' => 'images',
                'priority' => 'medium',
                'title' => 'Optimize Images',
                'description' => 'Compress and resize images for faster loading',
                'automated' => true,
                'estimated_improvement' => '15-25%',
                'implementation' => 'optimize_images',
                'affected_images' => count($large_images)
            );
        }
        
        // CSS/JS minification
        if ($this->has_unminified_assets($performance_data)) {
            $optimizations[] = array(
                'type' => 'assets',
                'priority' => 'medium',
                'title' => 'Minify CSS/JS Files',
                'description' => 'Reduce file sizes by removing unnecessary characters',
                'automated' => true,
                'estimated_improvement' => '5-15%',
                'implementation' => 'minify_assets'
            );
        }
        
        // Font optimization
        if ($this->has_font_loading_issues($performance_data)) {
            $optimizations[] = array(
                'type' => 'fonts',
                'priority' => 'low',
                'title' => 'Optimize Font Loading',
                'description' => 'Implement font-display: swap and preload critical fonts',
                'automated' => true,
                'estimated_improvement' => '3-8%',
                'implementation' => 'optimize_fonts'
            );
        }
        
        // Database optimization
        $optimizations[] = array(
            'type' => 'database',
            'priority' => 'low',
            'title' => 'Database Cleanup',
            'description' => 'Remove spam, revisions, and optimize database tables',
            'automated' => true,
            'estimated_improvement' => '5-10%',
            'implementation' => 'cleanup_database'
        );
        
        return $optimizations;
    }
    
    /**
     * Apply automated performance fixes
     */
    public function apply_automated_fixes($fixes_to_apply) {
        $results = array();
        
        foreach ($fixes_to_apply as $fix) {
            switch ($fix) {
                case 'setup_caching':
                    $results['caching'] = $this->setup_automated_caching();
                    break;
                
                case 'optimize_images':
                    $results['images'] = $this->optimize_images_automatically();
                    break;
                
                case 'minify_assets':
                    $results['assets'] = $this->minify_assets_automatically();
                    break;
                
                case 'optimize_fonts':
                    $results['fonts'] = $this->optimize_fonts_automatically();
                    break;
                
                case 'cleanup_database':
                    $results['database'] = $this->cleanup_database_automatically();
                    break;
            }
        }
        
        // Trigger a new performance check after fixes
        if (!empty($results)) {
            wp_schedule_single_event(time() + 300, 'wsa_post_optimization_check', array(array_keys($results)));
        }
        
        return $results;
    }
    
    /**
     * Setup automated caching configuration
     */
    private function setup_automated_caching() {
        $result = array(
            'success' => false,
            'message' => 'Caching setup failed',
            'details' => array()
        );
        
        try {
            // Check if a caching plugin is already active
            $active_cache_plugin = $this->detect_active_cache_plugin();
            
            if ($active_cache_plugin) {
                $result['message'] = 'Caching plugin already active: ' . $active_cache_plugin;
                $result['success'] = true;
                return $result;
            }
            
            // Configure WordPress built-in caching
            $this->enable_wp_cache();
            
            // Add cache headers via .htaccess
            $this->add_cache_headers();
            
            // Enable Gzip compression
            $this->enable_gzip_compression();
            
            $result['success'] = true;
            $result['message'] = 'Basic caching configuration applied';
            $result['details'] = array(
                'wp_cache_enabled' => defined('WP_CACHE') && WP_CACHE,
                'cache_headers_added' => true,
                'gzip_enabled' => true
            );
            
        } catch (Exception $e) {
            $result['message'] = 'Caching setup failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Automatically optimize images
     */
    private function optimize_images_automatically() {
        $result = array(
            'success' => false,
            'message' => 'Image optimization failed',
            'optimized_count' => 0,
            'size_saved' => 0
        );
        
        // Get recent uploaded images
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 50,
            'meta_query' => array(
                array(
                    'key' => '_wsa_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        $optimized_count = 0;
        $total_size_saved = 0;
        
        foreach ($images as $image) {
            $optimization_result = $this->optimize_single_image($image->ID);
            
            if ($optimization_result['success']) {
                $optimized_count++;
                $total_size_saved += $optimization_result['size_saved'] ?? 0;
                
                // Mark as optimized
                update_post_meta($image->ID, '_wsa_optimized', current_time('timestamp'));
            }
            
            // Prevent timeout
            if ($optimized_count >= 20) {
                break;
            }
        }
        
        $result['success'] = $optimized_count > 0;
        $result['message'] = "Optimized {$optimized_count} images";
        $result['optimized_count'] = $optimized_count;
        $result['size_saved'] = $total_size_saved;
        
        return $result;
    }
    
    /**
     * Optimize a single image
     */
    private function optimize_single_image($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return array('success' => false, 'message' => 'File not found');
        }
        
        $original_size = filesize($file_path);
        
        // Basic image optimization using WordPress built-in functions
        $editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($editor)) {
            return array('success' => false, 'message' => $editor->get_error_message());
        }
        
        // Set quality to 85% for JPEG compression
        $editor->set_quality(85);
        
        // Save optimized version
        $saved = $editor->save($file_path);
        
        if (is_wp_error($saved)) {
            return array('success' => false, 'message' => $saved->get_error_message());
        }
        
        $new_size = filesize($file_path);
        $size_saved = $original_size - $new_size;
        
        return array(
            'success' => true,
            'size_saved' => max(0, $size_saved),
            'original_size' => $original_size,
            'new_size' => $new_size
        );
    }
    
    /**
     * Enable WordPress cache
     */
    private function enable_wp_cache() {
        $wp_config_path = ABSPATH . 'wp-config.php';
        
        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            return false;
        }
        
        $wp_config_content = file_get_contents($wp_config_path);
        
        // Check if WP_CACHE is already defined
        if (strpos($wp_config_content, 'WP_CACHE') === false) {
            // Add WP_CACHE definition after opening <?php tag
            $new_line = "define('WP_CACHE', true); // Added by WP SiteAdvisor Pro\n";
            $wp_config_content = preg_replace(
                '/(<\?php\s*)/i',
                "$1\n" . $new_line,
                $wp_config_content,
                1
            );
            
            file_put_contents($wp_config_path, $wp_config_content);
        }
        
        return true;
    }
    
    /**
     * Add cache headers via .htaccess
     */
    private function add_cache_headers() {
        $htaccess_path = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_path) || !is_writable($htaccess_path)) {
            return false;
        }
        
        $cache_rules = "\n# BEGIN WP SiteAdvisor Pro Cache Headers\n";
        $cache_rules .= "<IfModule mod_expires.c>\n";
        $cache_rules .= "    ExpiresActive On\n";
        $cache_rules .= "    ExpiresByType text/css \"access plus 1 year\"\n";
        $cache_rules .= "    ExpiresByType application/javascript \"access plus 1 year\"\n";
        $cache_rules .= "    ExpiresByType image/png \"access plus 1 year\"\n";
        $cache_rules .= "    ExpiresByType image/jpg \"access plus 1 year\"\n";
        $cache_rules .= "    ExpiresByType image/jpeg \"access plus 1 year\"\n";
        $cache_rules .= "    ExpiresByType image/gif \"access plus 1 year\"\n";
        $cache_rules .= "    ExpiresByType image/svg+xml \"access plus 1 year\"\n";
        $cache_rules .= "</IfModule>\n";
        $cache_rules .= "# END WP SiteAdvisor Pro Cache Headers\n\n";
        
        $current_content = file_get_contents($htaccess_path);
        
        // Check if rules already exist
        if (strpos($current_content, 'WP SiteAdvisor Pro Cache Headers') === false) {
            file_put_contents($htaccess_path, $cache_rules . $current_content);
        }
        
        return true;
    }
    
    /**
     * AJAX Handlers for automation
     */
    public function ajax_apply_pagespeed_fixes() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $fixes_to_apply = isset($_POST['fixes']) ? $_POST['fixes'] : array();
        
        if (empty($fixes_to_apply)) {
            wp_send_json_error('No fixes specified');
        }
        
        $results = $this->apply_automated_fixes($fixes_to_apply);
        
        wp_send_json_success($results);
    }
    
    public function ajax_get_automated_optimizations() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Get latest performance data
        $performance_data = get_transient('wsa_latest_pagespeed_results');
        
        if (!$performance_data) {
            wp_send_json_error('No recent performance data available. Please run a PageSpeed analysis first.');
        }
        
        $optimizations = $this->get_automated_optimizations($performance_data);
        
        wp_send_json_success($optimizations);
    }
    
    /**
     * Helper methods for optimization detection
     */
    private function detect_unoptimized_images($performance_data) {
        // Analyze PageSpeed data for image opportunities
        $images = array();
        
        if (isset($performance_data['desktop']['audits']['uses-optimized-images']['details']['items'])) {
            $images = $performance_data['desktop']['audits']['uses-optimized-images']['details']['items'];
        }
        
        return $images;
    }
    
    private function has_unminified_assets($performance_data) {
        $has_css_issues = isset($performance_data['desktop']['audits']['unused-css-rules']['score']) && 
                         $performance_data['desktop']['audits']['unused-css-rules']['score'] < 0.9;
        
        $has_js_issues = isset($performance_data['desktop']['audits']['unused-javascript']['score']) && 
                        $performance_data['desktop']['audits']['unused-javascript']['score'] < 0.9;
        
        return $has_css_issues || $has_js_issues;
    }
    
    private function has_font_loading_issues($performance_data) {
        return isset($performance_data['desktop']['audits']['font-display']['score']) && 
               $performance_data['desktop']['audits']['font-display']['score'] < 0.9;
    }
    
    private function detect_active_cache_plugin() {
        $cache_plugins = array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache'
        );
        
        foreach ($cache_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                return $plugin_name;
            }
        }
        
        return false;
    }
    
    /**
     * Schedule weekly performance checks
     */
    public function schedule_performance_check() {
        if (!wp_next_scheduled('wsa_weekly_performance_check')) {
            wp_schedule_event(time(), 'weekly', 'wsa_weekly_performance_check');
        }
    }
    
    /**
     * Run comprehensive performance analysis
     */
    public function run_performance_analysis($url = null) {
        if (empty($this->api_key)) {
            return array('error' => 'PageSpeed API key not configured');
        }
        
        if (!$url) {
            $url = home_url();
        }
        
        $results = array(
            'url' => $url,
            'desktop' => $this->analyze_url($url, 'desktop'),
            'mobile' => $this->analyze_url($url, 'mobile'),
            'scan_time' => current_time('timestamp'),
        );
        
        // Store results for future reference
        update_option('wsa_pro_pagespeed_last_results', $results);
        set_transient('wsa_latest_pagespeed_results', $results, self::CACHE_DURATION);
        
        // Fire action for other components
        do_action('wsa_pagespeed_analysis_complete', $results, $url);
        
        return $results;
    }
    

    
    /**
     * Analyze URL with PageSpeed Insights
     */
    private function analyze_url($url, $strategy = 'desktop') {
        $cache_key = 'wsa_pagespeed_' . md5($url . $strategy);
        
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $api_url = add_query_arg(array(
            'url' => urlencode($url),
            'key' => $this->api_key,
            'strategy' => $strategy,
            'category' => 'performance'
        ), self::PAGESPEED_API_URL);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 60, // PageSpeed can take a while
            'user-agent' => 'WP SiteAdvisor Pro/' . WSA_PRO_VERSION
        ));
        
        if (is_wp_error($response)) {
            $error_result = array(
                'error' => $response->get_error_message(),
                'strategy' => $strategy
            );
            
            // Cache error for shorter duration
            set_transient($cache_key, $error_result, HOUR_IN_SECONDS);
            return $error_result;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $error_result = array(
                'error' => 'PageSpeed API returned status code: ' . $response_code,
                'strategy' => $strategy
            );
            
            set_transient($cache_key, $error_result, HOUR_IN_SECONDS);
            return $error_result;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_result = array(
                'error' => 'Invalid JSON response from PageSpeed API',
                'strategy' => $strategy
            );
            
            set_transient($cache_key, $error_result, HOUR_IN_SECONDS);
            return $error_result;
        }
        
        // Parse and format results
        $formatted_result = $this->format_pagespeed_results($data, $strategy);
        
        // Cache for 12 hours
        set_transient($cache_key, $formatted_result, self::CACHE_DURATION);
        
        return $formatted_result;
    }
    
    /**
     * Format PageSpeed results
     */
    private function format_pagespeed_results($data, $strategy) {
        $result = array(
            'strategy' => $strategy,
            'scores' => array(),
            'metrics' => array(),
            'opportunities' => array(),
            'diagnostics' => array(),
            'passed_audits' => array()
        );
        
        // Extract scores
        if (isset($data['lighthouseResult']['categories'])) {
            foreach ($data['lighthouseResult']['categories'] as $category => $details) {
                $result['scores'][$category] = array(
                    'score' => round($details['score'] * 100),
                    'title' => $details['title']
                );
            }
        }
        
        // Extract key metrics
        if (isset($data['lighthouseResult']['audits'])) {
            $key_metrics = array(
                'first-contentful-paint' => 'First Contentful Paint',
                'largest-contentful-paint' => 'Largest Contentful Paint',
                'speed-index' => 'Speed Index',
                'cumulative-layout-shift' => 'Cumulative Layout Shift',
                'first-input-delay' => 'First Input Delay',
                'total-blocking-time' => 'Total Blocking Time'
            );
            
            foreach ($key_metrics as $metric_id => $metric_name) {
                if (isset($data['lighthouseResult']['audits'][$metric_id])) {
                    $audit = $data['lighthouseResult']['audits'][$metric_id];
                    $result['metrics'][$metric_id] = array(
                        'title' => $metric_name,
                        'displayValue' => $audit['displayValue'] ?? 'N/A',
                        'score' => isset($audit['score']) ? round($audit['score'] * 100) : null,
                        'numericValue' => $audit['numericValue'] ?? null
                    );
                }
            }
        }
        
        // Extract opportunities (performance improvements)
        if (isset($data['lighthouseResult']['audits'])) {
            foreach ($data['lighthouseResult']['audits'] as $audit_id => $audit) {
                if (isset($audit['details']['type']) && $audit['score'] < 1) {
                    $savings = 0;
                    
                    if (isset($audit['details']['overallSavingsMs'])) {
                        $savings = $audit['details']['overallSavingsMs'];
                    } elseif (isset($audit['numericValue'])) {
                        $savings = $audit['numericValue'];
                    }
                    
                    if ($savings > 100) { // Only include significant opportunities
                        $result['opportunities'][] = array(
                            'id' => $audit_id,
                            'title' => $audit['title'],
                            'description' => $audit['description'],
                            'displayValue' => $audit['displayValue'] ?? '',
                            'savings_ms' => $savings,
                            'score' => $audit['score'] ?? 0
                        );
                    }
                }
            }
        }
        
        // Extract diagnostics
        if (isset($data['lighthouseResult']['audits'])) {
            $diagnostic_audits = array(
                'uses-text-compression',
                'render-blocking-resources',
                'unused-css-rules',
                'unused-javascript',
                'modern-image-formats',
                'offscreen-images',
                'minify-css',
                'minify-javascript',
                'eliminate-render-blocking-resources'
            );
            
            foreach ($diagnostic_audits as $audit_id) {
                if (isset($data['lighthouseResult']['audits'][$audit_id]) && 
                    $data['lighthouseResult']['audits'][$audit_id]['score'] < 1) {
                    
                    $audit = $data['lighthouseResult']['audits'][$audit_id];
                    $result['diagnostics'][] = array(
                        'id' => $audit_id,
                        'title' => $audit['title'],
                        'description' => $audit['description'],
                        'displayValue' => $audit['displayValue'] ?? '',
                        'score' => $audit['score']
                    );
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Generate AI insights from PageSpeed results
     */
    private function generate_ai_insights($results) {
        $openai_api_key = $this->get_openai_api_key();
        
        if (empty($openai_api_key)) {
            return array('error' => 'OpenAI API key not configured');
        }
        
        $context = $this->prepare_performance_context($results);
        
        $prompt = "Analyze the following website performance data and provide actionable recommendations:\n\n";
        $prompt .= $context . "\n\n";
        $prompt .= "Please provide:\n";
        $prompt .= "1. A summary of the current performance state\n";
        $prompt .= "2. Top 3 priority improvements with specific implementation steps\n";
        $prompt .= "3. Expected performance impact of each recommendation\n";
        $prompt .= "4. WordPress-specific optimization tips\n\n";
        $prompt .= "Focus on practical, implementable solutions suitable for a WordPress administrator.";
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $openai_api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a WordPress performance optimization expert. Provide clear, actionable advice for improving website speed and Core Web Vitals.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 1200,
                'temperature' => 0.3
            ))
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'content' => sanitize_textarea_field($data['choices'][0]['message']['content']),
                'generated_at' => current_time('timestamp')
            );
        }
        
        return array('error' => 'Failed to generate AI insights');
    }
    
    /**
     * Prepare performance context for AI analysis
     */
    private function prepare_performance_context($results) {
        $context = "Website Performance Analysis Results:\n\n";
        $context .= "URL: " . $results['url'] . "\n";
        $context .= "Scan Date: " . date('Y-m-d H:i:s', $results['scan_time']) . "\n\n";
        
        // Desktop results
        if (isset($results['desktop']) && !isset($results['desktop']['error'])) {
            $desktop = $results['desktop'];
            $context .= "DESKTOP RESULTS:\n";
            
            foreach ($desktop['scores'] as $category => $score_data) {
                $context .= "- {$score_data['title']}: {$score_data['score']}/100\n";
            }
            
            $context .= "\nKey Metrics (Desktop):\n";
            foreach ($desktop['metrics'] as $metric_id => $metric) {
                $context .= "- {$metric['title']}: {$metric['displayValue']}\n";
            }
            
            if (!empty($desktop['opportunities'])) {
                $context .= "\nTop Opportunities (Desktop):\n";
                $top_opportunities = array_slice($desktop['opportunities'], 0, 5);
                foreach ($top_opportunities as $opp) {
                    $context .= "- {$opp['title']} (Potential savings: " . round($opp['savings_ms']/1000, 2) . "s)\n";
                }
            }
        }
        
        // Mobile results
        if (isset($results['mobile']) && !isset($results['mobile']['error'])) {
            $mobile = $results['mobile'];
            $context .= "\n\nMOBILE RESULTS:\n";
            
            foreach ($mobile['scores'] as $category => $score_data) {
                $context .= "- {$score_data['title']}: {$score_data['score']}/100\n";
            }
            
            $context .= "\nKey Metrics (Mobile):\n";
            foreach ($mobile['metrics'] as $metric_id => $metric) {
                $context .= "- {$metric['title']}: {$metric['displayValue']}\n";
            }
            
            if (!empty($mobile['opportunities'])) {
                $context .= "\nTop Opportunities (Mobile):\n";
                $top_opportunities = array_slice($mobile['opportunities'], 0, 5);
                foreach ($top_opportunities as $opp) {
                    $context .= "- {$opp['title']} (Potential savings: " . round($opp['savings_ms']/1000, 2) . "s)\n";
                }
            }
        }
        
        // WordPress context
        $context .= "\n\nWORDPRESS CONTEXT:\n";
        $context .= "- WordPress Version: " . get_bloginfo('version') . "\n";
        $context .= "- Active Theme: " . wp_get_theme()->get('Name') . "\n";
        $context .= "- Active Plugins: " . count(get_option('active_plugins', array())) . "\n";
        
        return $context;
    }
    
    /**
     * Get performance summary
     */
    public function get_performance_summary() {
        $results = get_option('wsa_pro_pagespeed_analysis', array());
        
        if (empty($results)) {
            return array(
                'needs_scan' => true,
                'last_scan' => null,
                'scores' => array(),
                'status' => 'no_data'
            );
        }
        
        $summary = array(
            'needs_scan' => false,
            'last_scan' => $results['scan_time'],
            'url' => $results['url'],
            'scores' => array(),
            'status' => 'good'
        );
        
        // Extract scores
        if (isset($results['desktop']['scores'])) {
            $summary['scores']['desktop'] = $results['desktop']['scores'];
        }
        
        if (isset($results['mobile']['scores'])) {
            $summary['scores']['mobile'] = $results['mobile']['scores'];
        }
        
        // Determine overall status
        $performance_scores = array();
        
        if (isset($results['desktop']['scores']['performance'])) {
            $performance_scores[] = $results['desktop']['scores']['performance']['score'];
        }
        
        if (isset($results['mobile']['scores']['performance'])) {
            $performance_scores[] = $results['mobile']['scores']['performance']['score'];
        }
        
        if (!empty($performance_scores)) {
            $avg_score = array_sum($performance_scores) / count($performance_scores);
            
            if ($avg_score >= 90) {
                $summary['status'] = 'excellent';
            } elseif ($avg_score >= 70) {
                $summary['status'] = 'good';
            } elseif ($avg_score >= 50) {
                $summary['status'] = 'needs_improvement';
            } else {
                $summary['status'] = 'poor';
            }
        }
        
        // Check if scan is older than 7 days
        if ($results['scan_time'] < (current_time('timestamp') - 7 * DAY_IN_SECONDS)) {
            $summary['needs_scan'] = true;
        }
        
        return $summary;
    }
    
    /**
     * Get detailed performance report
     */
    public function get_detailed_report() {
        return get_option('wsa_pro_pagespeed_analysis', array());
    }
    
    /**
     * Get AI performance recommendations
     */
    public function get_ai_recommendations() {
        $results = get_option('wsa_pro_pagespeed_analysis', array());
        
        if (empty($results) || !isset($results['ai_insights'])) {
            return null;
        }
        
        return $results['ai_insights'];
    }
    
    /**
     * AJAX handler for PageSpeed analysis
     */
    public function ajax_run_pagespeed_analysis() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check both possible nonce names (Pro and free plugin compatibility)
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce') || wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        // Refresh API key in case it was updated
        $this->api_key = $this->get_pagespeed_api_key();
        
        if (empty($this->api_key)) {
            wp_send_json_error('PageSpeed Insights API key not configured. Please configure it in the free plugin Settings or Pro Settings.');
        }
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : home_url();
        
        $results = $this->run_performance_analysis_with_fallback($url);
        
        if (isset($results['error'])) {
            wp_send_json_error($results['error']);
        }
        
        wp_send_json_success(array(
            'summary' => $this->get_performance_summary(),
            'has_ai_insights' => isset($results['ai_insights']) && !isset($results['ai_insights']['error'])
        ));
    }
    
    /**
     * AJAX handler for performance report
     */
    public function ajax_get_performance_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check both possible nonce names (Pro and free plugin compatibility)
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce') || wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        $report = $this->get_detailed_report();
        $summary = $this->get_performance_summary();
        
        wp_send_json_success(array(
            'report' => $report,
            'summary' => $summary
        ));
    }
    
    /**
     * AJAX handler for AI performance insights
     */
    public function ajax_get_ai_performance_insights() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check both possible nonce names (Pro and free plugin compatibility)
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'wsa_pro_nonce') || wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce');
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        $insights = $this->get_ai_recommendations();
        
        if (!$insights) {
            wp_send_json_error('No AI insights available. Run a performance analysis first.');
        }
        
        wp_send_json_success($insights);
    }
    
    /**
     * Check if URL is localhost/local development
     */
    private function is_localhost_url($url) {
        $parsed_url = parse_url($url);
        $host = $parsed_url['host'] ?? '';
        
        return in_array($host, array('localhost', '127.0.0.1', '::1')) || 
               strpos($host, '.local') !== false ||
               preg_match('/^192\.168\./', $host) ||
               preg_match('/^10\./', $host) ||
               preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host);
    }
    
    /**
     * Run performance analysis with Lighthouse fallback for local URLs
     */
    public function run_performance_analysis_with_fallback($url = null) {
        if (!$url) {
            $url = home_url();
        }
        
        // If it's a localhost URL or PageSpeed API fails, use Lighthouse simulation
        if ($this->is_localhost_url($url) || empty($this->api_key)) {
            return $this->run_lighthouse_simulation($url);
        }
        
        // Try PageSpeed Insights first
        $results = $this->run_performance_analysis($url);
        
        // If PageSpeed fails and it's a local URL, fallback to Lighthouse simulation
        if ((isset($results['desktop']['error']) || isset($results['mobile']['error'])) && 
            $this->is_localhost_url($url)) {
            return $this->run_lighthouse_simulation($url);
        }
        
        return $results;
    }
    
    /**
     * Simulate Lighthouse analysis for local development
     */
    private function run_lighthouse_simulation($url) {
        // Get basic site information for simulation
        $site_info = $this->get_local_site_info($url);
        
        // Generate simulated Lighthouse scores based on site analysis
        $desktop_score = $this->calculate_simulated_score($site_info, 'desktop');
        $mobile_score = $this->calculate_simulated_score($site_info, 'mobile');
        
        $results = array(
            'url' => $url,
            'desktop' => array(
                'strategy' => 'desktop',
                'scores' => array(
                    'performance' => array('score' => $desktop_score['performance']),
                    'accessibility' => array('score' => $desktop_score['accessibility']),
                    'best-practices' => array('score' => $desktop_score['best_practices']),
                    'seo' => array('score' => $desktop_score['seo'])
                ),
                'metrics' => $desktop_score['metrics'],
                'opportunities' => $this->get_simulated_opportunities($site_info, 'desktop'),
                'source' => 'lighthouse_simulation'
            ),
            'mobile' => array(
                'strategy' => 'mobile',
                'scores' => array(
                    'performance' => array('score' => $mobile_score['performance']),
                    'accessibility' => array('score' => $mobile_score['accessibility']),
                    'best-practices' => array('score' => $mobile_score['best_practices']),
                    'seo' => array('score' => $mobile_score['seo'])
                ),
                'metrics' => $mobile_score['metrics'],
                'opportunities' => $this->get_simulated_opportunities($site_info, 'mobile'),
                'source' => 'lighthouse_simulation'
            ),
            'scan_time' => current_time('timestamp'),
            'ai_insights' => null,
            'source' => 'lighthouse_simulation'
        );
        
        // Generate AI insights for simulated data
        if (!empty($this->get_openai_api_key())) {
            $results['ai_insights'] = $this->generate_lighthouse_ai_insights($site_info, $results);
        }
        
        // Store results
        update_option('wsa_pro_pagespeed_analysis', $results);
        
        return $results;
    }
    
    /**
     * Get basic site information for Lighthouse simulation
     */
    private function get_local_site_info($url) {
        // Make a request to the local site to analyze it
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false // For local development
        ));
        
        $info = array(
            'url' => $url,
            'response_time' => 0,
            'page_size' => 0,
            'html_content' => '',
            'has_jquery' => false,
            'has_css_files' => 0,
            'has_js_files' => 0,
            'has_images' => 0,
            'wordpress_version' => get_bloginfo('version'),
            'active_plugins' => count(get_option('active_plugins', array())),
            'theme_name' => wp_get_theme()->get('Name')
        );
        
        if (!is_wp_error($response)) {
            $start_time = microtime(true);
            $body = wp_remote_retrieve_body($response);
            $info['response_time'] = (microtime(true) - $start_time) * 1000; // Convert to ms
            $info['page_size'] = strlen($body);
            $info['html_content'] = $body;
            
            // Analyze content
            $info['has_jquery'] = strpos($body, 'jquery') !== false;
            $info['has_css_files'] = substr_count($body, '.css');
            $info['has_js_files'] = substr_count($body, '.js');
            $info['has_images'] = substr_count($body, '<img');
        }
        
        return $info;
    }
    
    /**
     * Calculate simulated Lighthouse scores based on site analysis
     */
    private function calculate_simulated_score($site_info, $strategy) {
        // Base scores - realistic for WordPress sites
        $base_scores = array(
            'desktop' => array('performance' => 75, 'accessibility' => 85, 'best_practices' => 80, 'seo' => 90),
            'mobile' => array('performance' => 65, 'accessibility' => 85, 'best_practices' => 80, 'seo' => 88)
        );
        
        $scores = $base_scores[$strategy];
        
        // Adjust based on site characteristics
        if ($site_info['page_size'] > 500000) { // Large page
            $scores['performance'] -= 10;
        }
        
        if ($site_info['response_time'] > 500) { // Slow response
            $scores['performance'] -= 15;
        }
        
        if ($site_info['has_css_files'] > 5) { // Too many CSS files
            $scores['performance'] -= 5;
        }
        
        if ($site_info['has_js_files'] > 10) { // Too many JS files
            $scores['performance'] -= 10;
        }
        
        if ($site_info['active_plugins'] > 20) { // Too many plugins
            $scores['performance'] -= 8;
        }
        
        // Ensure scores don't go below 0 or above 100
        foreach ($scores as $key => $score) {
            $scores[$key] = max(0, min(100, $score));
        }
        
        // Add simulated metrics
        $metrics = array(
            'first-contentful-paint' => array('displayValue' => $this->estimate_fcp($site_info, $strategy)),
            'largest-contentful-paint' => array('displayValue' => $this->estimate_lcp($site_info, $strategy)),
            'total-blocking-time' => array('displayValue' => $this->estimate_tbt($site_info, $strategy)),
            'cumulative-layout-shift' => array('displayValue' => $this->estimate_cls($site_info, $strategy)),
            'speed-index' => array('displayValue' => $this->estimate_speed_index($site_info, $strategy))
        );
        
        return array_merge($scores, array('metrics' => $metrics));
    }
    
    /**
     * Estimate Core Web Vitals for local analysis
     */
    private function estimate_fcp($site_info, $strategy) {
        $base_time = $strategy === 'mobile' ? 2.0 : 1.5;
        $adjustment = ($site_info['response_time'] / 1000) + ($site_info['page_size'] / 100000);
        return number_format($base_time + $adjustment, 1) . ' s';
    }
    
    private function estimate_lcp($site_info, $strategy) {
        $base_time = $strategy === 'mobile' ? 3.5 : 2.5;
        $adjustment = ($site_info['response_time'] / 1000) + ($site_info['has_images'] * 0.2);
        return number_format($base_time + $adjustment, 1) . ' s';
    }
    
    private function estimate_tbt($site_info, $strategy) {
        $base_time = $strategy === 'mobile' ? 300 : 200;
        $adjustment = ($site_info['has_js_files'] * 50) + ($site_info['active_plugins'] * 10);
        return number_format($base_time + $adjustment, 0) . ' ms';
    }
    
    private function estimate_cls($site_info, $strategy) {
        // CLS is typically low for WordPress sites unless there are layout issues
        $base_cls = 0.1;
        if ($site_info['has_images'] > 10) $base_cls += 0.05;
        return number_format($base_cls, 2);
    }
    
    private function estimate_speed_index($site_info, $strategy) {
        $base_time = $strategy === 'mobile' ? 4.0 : 3.0;
        $adjustment = ($site_info['page_size'] / 50000) + ($site_info['has_css_files'] * 0.2);
        return number_format($base_time + $adjustment, 1) . ' s';
    }
    
    /**
     * Get simulated optimization opportunities
     */
    private function get_simulated_opportunities($site_info, $strategy) {
        $opportunities = array();
        
        if ($site_info['has_js_files'] > 5) {
            $opportunities[] = array(
                'id' => 'unused-javascript',
                'title' => 'Remove unused JavaScript',
                'description' => 'Reduce unused JavaScript and defer loading scripts until they are required.',
                'savings_ms' => 500 + ($site_info['has_js_files'] * 100),
                'score' => 0.3
            );
        }
        
        if ($site_info['has_css_files'] > 3) {
            $opportunities[] = array(
                'id' => 'unused-css-rules',
                'title' => 'Remove unused CSS',
                'description' => 'Reduce unused rules from stylesheets to decrease bytes consumed by network activity.',
                'savings_ms' => 300 + ($site_info['has_css_files'] * 50),
                'score' => 0.4
            );
        }
        
        if ($site_info['has_images'] > 5) {
            $opportunities[] = array(
                'id' => 'modern-image-formats',
                'title' => 'Serve images in next-gen formats',
                'description' => 'Image formats like WebP often provide better compression than PNG or JPEG.',
                'savings_ms' => 200 + ($site_info['has_images'] * 30),
                'score' => 0.5
            );
        }
        
        if ($site_info['page_size'] > 300000) {
            $opportunities[] = array(
                'id' => 'uses-text-compression',
                'title' => 'Enable text compression',
                'description' => 'Text-based resources should be served with compression to minimize total network bytes.',
                'savings_ms' => 400,
                'score' => 0.2
            );
        }
        
        return $opportunities;
    }
    
    /**
     * Generate AI insights for Lighthouse simulation
     */
    private function generate_lighthouse_ai_insights($site_info, $results) {
        $openai_api_key = $this->get_openai_api_key();
        
        if (empty($openai_api_key)) {
            return array('error' => 'OpenAI API key not configured');
        }
        
        // Build context for AI analysis
        $context = "LIGHTHOUSE SIMULATION ANALYSIS FOR LOCAL DEVELOPMENT\n\n";
        $context .= "URL: {$site_info['url']}\n";
        $context .= "Analysis Method: Local Lighthouse Simulation\n\n";
        
        $context .= "PERFORMANCE SCORES:\n";
        $context .= "Desktop Performance: {$results['desktop']['scores']['performance']['score']}/100\n";
        $context .= "Mobile Performance: {$results['mobile']['scores']['performance']['score']}/100\n\n";
        
        $context .= "SITE CHARACTERISTICS:\n";
        $context .= "- Page Size: " . number_format($site_info['page_size'] / 1024, 2) . " KB\n";
        $context .= "- Response Time: {$site_info['response_time']} ms\n";
        $context .= "- CSS Files: {$site_info['has_css_files']}\n";
        $context .= "- JS Files: {$site_info['has_js_files']}\n";
        $context .= "- Images: {$site_info['has_images']}\n";
        $context .= "- Active Plugins: {$site_info['active_plugins']}\n";
        $context .= "- WordPress Version: {$site_info['wordpress_version']}\n";
        $context .= "- Theme: {$site_info['theme_name']}\n\n";
        
        $context .= "Note: This is a simulated analysis for local development. Deploy to production for accurate PageSpeed Insights data.\n";
        
        // Use the same AI generation method
        return $this->call_openai_api($context);
    }
    
    /**
     * Make API call to OpenAI for AI insights generation
     */
    private function call_openai_api($context) {
        $openai_api_key = $this->get_openai_api_key();
        
        if (empty($openai_api_key)) {
            return array('error' => 'OpenAI API key not configured');
        }
        
        $prompt = "Analyze the following website performance data and provide actionable recommendations:\n\n";
        $prompt .= $context . "\n\n";
        $prompt .= "Please provide:\n";
        $prompt .= "1. A summary of the current performance state\n";
        $prompt .= "2. Top 3 priority improvements with specific implementation steps\n";
        $prompt .= "3. Expected performance impact of each recommendation\n";
        $prompt .= "4. WordPress-specific optimization tips\n\n";
        $prompt .= "Focus on practical, implementable solutions suitable for a WordPress administrator.";
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $openai_api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a WordPress performance optimization expert. Provide clear, actionable advice for improving website speed and Core Web Vitals.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 1200,
                'temperature' => 0.3
            ))
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'content' => sanitize_textarea_field($data['choices'][0]['message']['content']),
                'generated_at' => current_time('timestamp')
            );
        }
        
        return array('error' => 'Failed to generate AI insights');
    }
}