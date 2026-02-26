<?php
/**
 * WSA Pro AI Analyzer Class
 *
 * Enhanced AI analysis with Pro features
 *
 * @package WSA_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSA_Pro_AI_Analyzer {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * OpenAI API endpoint
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

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
     * Initialize hooks
     */
    private function init_hooks() {
        // Override free version AI analysis
        add_filter('wsa_ai_recommendations', array($this, 'enhanced_ai_analysis'), 10, 2);
        add_action('wp_ajax_wsa_get_ai_recommendations', array($this, 'handle_ajax_analysis'), 1);
    }

    /**
     * Perform enhanced AI analysis
     */
    public function analyze($scan_data) {
        // Verify license
        if (!wsa_pro_is_license_active()) {
            return new WP_Error('license_required', __('AI analysis requires a valid Pro license.', 'wp-site-advisory-pro'));
        }

        $api_key = get_option('wsa_openai_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is not configured.', 'wp-site-advisory-pro'));
        }

        // Enhanced prompt for Pro version
        $enhanced_prompt = $this->build_enhanced_prompt($scan_data);

        return $this->call_openai_api($api_key, $enhanced_prompt, $scan_data);
    }

    /**
     * Build enhanced AI prompt with Pro features
     */
    private function build_enhanced_prompt($scan_data) {
        $site_url = home_url();
        $site_name = get_bloginfo('name');
        
        $prompt = "You are a WordPress security and performance expert analyzing a website. Provide comprehensive, actionable recommendations.\n\n";
        
        $prompt .= "Website: {$site_name} ({$site_url})\n";
        $prompt .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $prompt .= "PHP Version: " . PHP_VERSION . "\n\n";

        // Add plugin analysis
        if (!empty($scan_data['plugins'])) {
            $prompt .= "ACTIVE PLUGINS ANALYSIS:\n";
            
            foreach ($scan_data['plugins'] as $plugin) {
                $prompt .= "- {$plugin['name']} (v{$plugin['version']})";
                
                if (!empty($plugin['vulnerabilities'])) {
                    $prompt .= " - SECURITY RISK: " . count($plugin['vulnerabilities']) . " vulnerabilities found";
                }
                
                if (!empty($plugin['update_available'])) {
                    $prompt .= " - UPDATE AVAILABLE: v{$plugin['new_version']}";
                }
                
                if (!empty($plugin['inactive_time'])) {
                    $prompt .= " - INACTIVE: {$plugin['inactive_time']} days";
                }
                
                $prompt .= "\n";
            }
        }

        // Add theme analysis
        if (!empty($scan_data['theme_analysis'])) {
            $theme = $scan_data['theme_analysis'];
            $prompt .= "\nTHEME ANALYSIS:\n";
            $prompt .= "- Active Theme: {$theme['name']} (v{$theme['version']})\n";
            
            if (!empty($theme['security_scan'])) {
                $security = $theme['security_scan'];
                $prompt .= "- Security Issues: {$security['issues_found']}\n";
                $prompt .= "- Critical Issues: {$security['critical_issues']}\n";
            }
        }

        // Add Google integrations
        if (!empty($scan_data['google_integrations'])) {
            $google = $scan_data['google_integrations'];
            $prompt .= "\nGOOGLE INTEGRATIONS:\n";
            $prompt .= "- Analytics: " . ($google['analytics']['detected'] ? 'Detected' : 'Not Found') . "\n";
            $prompt .= "- Search Console: " . ($google['search_console']['detected'] ? 'Detected' : 'Not Found') . "\n";
            $prompt .= "- Tag Manager: " . ($google['tag_manager']['detected'] ? 'Detected' : 'Not Found') . "\n";
        }

        // Add system information for Pro analysis
        if (!empty($scan_data['system_info'])) {
            $system = $scan_data['system_info'];
            $prompt .= "\nSYSTEM INFORMATION:\n";
            $prompt .= "- Memory Limit: {$system['memory_limit']}\n";
            $prompt .= "- Database Size: {$system['database_size']}\n";
            $prompt .= "- Active Plugins Count: {$system['active_plugins_count']}\n";
        }

        $prompt .= "\nPRO ANALYSIS REQUIREMENTS:\n";
        $prompt .= "1. SECURITY ASSESSMENT: Prioritize security vulnerabilities and provide specific remediation steps\n";
        $prompt .= "2. PERFORMANCE OPTIMIZATION: Identify performance bottlenecks and optimization opportunities\n";
        $prompt .= "3. SEO IMPROVEMENTS: Suggest SEO enhancements based on current setup\n";
        $prompt .= "4. MAINTENANCE RECOMMENDATIONS: Provide ongoing maintenance tasks and schedules\n";
        $prompt .= "5. PLUGIN OPTIMIZATION: Recommend plugin alternatives or configuration improvements\n";
        $prompt .= "6. MONITORING SETUP: Suggest monitoring and alerting configurations\n\n";

        $prompt .= "Provide specific, actionable recommendations with priority levels (Critical, High, Medium, Low). ";
        $prompt .= "Include implementation steps and expected impact for each recommendation. ";
        $prompt .= "Focus on security, performance, and maintainability.";

        return $prompt;
    }

    /**
     * Call OpenAI API with enhanced configuration
     */
    private function call_openai_api($api_key, $prompt, $scan_data) {
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        // Enhanced configuration for Pro version
        $body = json_encode(array(
            'model' => 'gpt-4', // Use GPT-4 for Pro version
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a senior WordPress security and performance consultant with 15+ years of experience. Provide expert-level analysis and recommendations.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2000, // Increased token limit for Pro
            'temperature' => 0.3,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ));

        $args = array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 60, // Increased timeout for Pro
        );

        $response = wp_remote_post($this->api_endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Invalid JSON response from OpenAI.', 'wp-site-advisory-pro'));
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('no_content', __('No recommendations received from AI.', 'wp-site-advisory-pro'));
        }

        $recommendations = $data['choices'][0]['message']['content'];

        // Parse and structure the recommendations
        $structured_recommendations = $this->parse_recommendations($recommendations);

        // Store the enhanced results
        $this->store_analysis_results($structured_recommendations, $scan_data);

        return $structured_recommendations;
    }

    /**
     * Parse AI recommendations into structured format
     */
    private function parse_recommendations($recommendations) {
        $sections = array(
            'security' => array(),
            'performance' => array(),
            'seo' => array(),
            'maintenance' => array(),
            'plugins' => array(),
            'monitoring' => array()
        );

        // Parse the recommendations text into structured data
        $lines = explode("\n", $recommendations);
        $current_section = 'general';
        $current_priority = 'medium';

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }

            // Detect section headers
            if (stripos($line, 'SECURITY') !== false) {
                $current_section = 'security';
                continue;
            } elseif (stripos($line, 'PERFORMANCE') !== false) {
                $current_section = 'performance';
                continue;
            } elseif (stripos($line, 'SEO') !== false) {
                $current_section = 'seo';
                continue;
            } elseif (stripos($line, 'MAINTENANCE') !== false) {
                $current_section = 'maintenance';
                continue;
            } elseif (stripos($line, 'PLUGIN') !== false) {
                $current_section = 'plugins';
                continue;
            } elseif (stripos($line, 'MONITORING') !== false) {
                $current_section = 'monitoring';
                continue;
            }

            // Detect priority levels
            if (stripos($line, 'CRITICAL') !== false) {
                $current_priority = 'critical';
            } elseif (stripos($line, 'HIGH') !== false) {
                $current_priority = 'high';
            } elseif (stripos($line, 'MEDIUM') !== false) {
                $current_priority = 'medium';
            } elseif (stripos($line, 'LOW') !== false) {
                $current_priority = 'low';
            }

            // Add recommendation to current section
            if (strpos($line, '-') === 0 || strpos($line, '•') === 0) {
                $recommendation = substr($line, 1);
                $recommendation = trim($recommendation);
                
                if (!empty($recommendation)) {
                    $sections[$current_section][] = array(
                        'text' => $recommendation,
                        'priority' => $current_priority,
                        'category' => $current_section
                    );
                }
            }
        }

        return array(
            'raw_text' => $recommendations,
            'structured' => $sections,
            'analysis_type' => 'pro_enhanced',
            'generated_at' => current_time('mysql'),
            'model_used' => 'gpt-4'
        );
    }

    /**
     * Store analysis results for future reference
     */
    private function store_analysis_results($recommendations, $scan_data) {
        $analysis_data = array(
            'recommendations' => $recommendations,
            'scan_data' => $scan_data,
            'timestamp' => current_time('mysql'),
            'site_url' => home_url(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => WSA_PRO_VERSION
        );

        // Store in database
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wsa_pro_analysis_history';
        
        // Create table if it doesn't exist
        $this->create_analysis_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'analysis_data' => json_encode($analysis_data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s')
        );

        // Keep only last 50 analyses
        $wpdb->query("DELETE FROM {$table_name} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$table_name} ORDER BY created_at DESC LIMIT 50) AS subquery)");
    }

    /**
     * Create analysis history table
     */
    private function create_analysis_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wsa_pro_analysis_history';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            analysis_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Handle AJAX analysis request
     */
    public function handle_ajax_analysis() {
        // Verify license first
        if (!wsa_pro_is_license_active()) {
            wp_send_json_error(array(
                'message' => __('AI analysis requires a valid Pro license.', 'wp-site-advisory-pro'),
                'license_required' => true,
                'license_url' => admin_url('admin.php?page=wp-site-advisory-license')
            ));
        }

        // Let the request continue to be handled by the free version,
        // but with Pro enhancements
    }

    /**
     * Enhanced AI analysis filter
     */
    public function enhanced_ai_analysis($recommendations, $scan_data) {
        if (!wsa_pro_is_license_active()) {
            return $recommendations;
        }

        // If this is already a Pro analysis, return as-is
        if (is_array($recommendations) && isset($recommendations['analysis_type']) && $recommendations['analysis_type'] === 'pro_enhanced') {
            return $recommendations;
        }

        // Perform Pro analysis
        return $this->analyze($scan_data);
    }
}