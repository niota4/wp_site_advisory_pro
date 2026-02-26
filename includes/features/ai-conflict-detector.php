<?php
/**
 * AI Conflict Detector
 * 
 * Detects plugin/theme conflicts and provides AI-powered fix suggestions
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class AI_Conflict_Detector {
    
    private $conflicts = array();
    private $monitored_errors = array();
    
    /**
     * Initialize the conflict detector
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Monitor plugin activation/deactivation
        add_action('activated_plugin', array($this, 'monitor_plugin_activation'), 10, 2);
        add_action('deactivated_plugin', array($this, 'monitor_plugin_deactivation'), 10, 2);
        
        // Monitor theme switches
        add_action('after_switch_theme', array($this, 'monitor_theme_switch'));
        
        // Monitor plugin updates
        add_action('upgrader_process_complete', array($this, 'monitor_plugin_updates'), 10, 2);
        
        // Check for PHP errors and fatal errors
        add_action('wp_loaded', array($this, 'check_recent_errors'));
        
        // AJAX handlers
        add_action('wp_ajax_wsa_get_conflict_analysis', array($this, 'ajax_get_conflict_analysis'));
        add_action('wp_ajax_wsa_run_conflict_check', array($this, 'ajax_run_conflict_check'));
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
     * Monitor plugin activation for conflicts
     */
    public function monitor_plugin_activation($plugin, $network_wide) {
        $this->log_event('plugin_activated', array(
            'plugin' => $plugin,
            'network_wide' => $network_wide,
            'timestamp' => current_time('timestamp')
        ));
        
        // Schedule conflict check
        wp_schedule_single_event(time() + 30, 'wsa_check_conflicts_after_activation', array($plugin));
    }
    
    /**
     * Monitor plugin deactivation
     */
    public function monitor_plugin_deactivation($plugin, $network_wide) {
        $this->log_event('plugin_deactivated', array(
            'plugin' => $plugin,
            'network_wide' => $network_wide,
            'timestamp' => current_time('timestamp')
        ));
    }
    
    /**
     * Monitor theme switches
     */
    public function monitor_theme_switch($theme_name) {
        $this->log_event('theme_switched', array(
            'theme' => $theme_name,
            'timestamp' => current_time('timestamp')
        ));
        
        // Schedule conflict check
        wp_schedule_single_event(time() + 30, 'wsa_check_conflicts_after_theme_switch', array($theme_name));
    }
    
    /**
     * Monitor plugin updates
     */
    public function monitor_plugin_updates($upgrader, $options) {
        if ($options['type'] === 'plugin') {
            $this->log_event('plugins_updated', array(
                'plugins' => isset($options['plugins']) ? $options['plugins'] : array(),
                'timestamp' => current_time('timestamp')
            ));
            
            // Schedule conflict check
            wp_schedule_single_event(time() + 60, 'wsa_check_conflicts_after_update');
        }
    }
    
    /**
     * Check for recent PHP errors
     */
    public function check_recent_errors() {
        $error_log_path = ini_get('error_log');
        
        if (empty($error_log_path) || !file_exists($error_log_path)) {
            return;
        }
        
        // Check if we can read the error log
        if (!is_readable($error_log_path)) {
            return;
        }
        
        $last_check = get_option('wsa_last_error_check', time() - 3600);
        
        // Read recent errors (last hour)
        $recent_errors = $this->parse_error_log($error_log_path, $last_check);
        
        if (!empty($recent_errors)) {
            $this->analyze_errors($recent_errors);
        }
        
        update_option('wsa_last_error_check', time());
    }
    
    /**
     * Parse error log for recent entries
     */
    private function parse_error_log($log_path, $since_timestamp) {
        $errors = array();
        
        try {
            $handle = fopen($log_path, 'r');
            if (!$handle) {
                return $errors;
            }
            
            // Read last 1000 lines to avoid memory issues
            $lines = array();
            $buffer = '';
            
            fseek($handle, -8192, SEEK_END); // Start from end
            while (!feof($handle)) {
                $buffer = fread($handle, 8192) . $buffer;
            }
            fclose($handle);
            
            $lines = explode("\n", $buffer);
            $lines = array_slice($lines, -1000); // Last 1000 lines
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                // Parse PHP error format
                if (preg_match('/\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                    $error_time = strtotime($matches[1]);
                    
                    if ($error_time >= $since_timestamp) {
                        // Check if it's a WordPress/plugin related error
                        if (strpos($line, 'wp-content/plugins/') !== false || 
                            strpos($line, 'wp-content/themes/') !== false ||
                            strpos($line, 'Fatal error') !== false) {
                            
                            $errors[] = array(
                                'timestamp' => $error_time,
                                'message' => $line,
                                'type' => $this->classify_error($line)
                            );
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
        }
        
        return $errors;
    }
    
    /**
     * Classify error type
     */
    private function classify_error($error_message) {
        if (strpos($error_message, 'Fatal error') !== false) {
            return 'fatal';
        } elseif (strpos($error_message, 'Parse error') !== false) {
            return 'parse';
        } elseif (strpos($error_message, 'Warning') !== false) {
            return 'warning';
        } elseif (strpos($error_message, 'Notice') !== false) {
            return 'notice';
        }
        
        return 'unknown';
    }
    
    /**
     * Analyze errors using AI
     */
    private function analyze_errors($errors) {
        $critical_errors = array_filter($errors, function($error) {
            return in_array($error['type'], array('fatal', 'parse'));
        });
        
        if (empty($critical_errors)) {
            return;
        }
        
        // Group errors by plugin/theme
        $grouped_errors = $this->group_errors_by_source($critical_errors);
        
        foreach ($grouped_errors as $source => $source_errors) {
            $analysis = $this->get_ai_conflict_analysis($source, $source_errors);
            
            if ($analysis) {
                $this->store_conflict_analysis($source, $analysis, $source_errors);
            }
        }
    }
    
    /**
     * Group errors by their source (plugin/theme)
     */
    private function group_errors_by_source($errors) {
        $grouped = array();
        
        foreach ($errors as $error) {
            $source = $this->extract_error_source($error['message']);
            
            if ($source) {
                if (!isset($grouped[$source])) {
                    $grouped[$source] = array();
                }
                $grouped[$source][] = $error;
            }
        }
        
        return $grouped;
    }
    
    /**
     * Extract the source plugin/theme from error message
     */
    private function extract_error_source($error_message) {
        // Extract plugin name
        if (preg_match('/wp-content\/plugins\/([^\/]+)/', $error_message, $matches)) {
            return 'plugin:' . $matches[1];
        }
        
        // Extract theme name
        if (preg_match('/wp-content\/themes\/([^\/]+)/', $error_message, $matches)) {
            return 'theme:' . $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get AI analysis for conflicts
     */
    private function get_ai_conflict_analysis($source, $errors) {
        $openai_api_key = $this->get_openai_api_key();
        
        if (empty($openai_api_key)) {
            return null;
        }
        
        // Prepare context for AI
        $context = $this->prepare_ai_context($source, $errors);
        
        $prompt = "You are a WordPress expert. Analyze the following error(s) and provide:\n\n";
        $prompt .= "1. A clear explanation of what's causing the conflict\n";
        $prompt .= "2. Step-by-step instructions to fix it\n";
        $prompt .= "3. Preventive measures for the future\n\n";
        $prompt .= "Context: {$context}\n\n";
        $prompt .= "Errors:\n";
        
        foreach ($errors as $error) {
            $prompt .= "- " . sanitize_text_field($error['message']) . "\n";
        }
        
        $prompt .= "\nProvide a practical, actionable response suitable for a WordPress administrator.";
        
        // Make API call to OpenAI
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
                        'content' => 'You are a WordPress technical expert specializing in plugin and theme conflicts.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 800,
                'temperature' => 0.3
            ))
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return sanitize_textarea_field($data['choices'][0]['message']['content']);
        }
        
        return null;
    }
    
    /**
     * Prepare context for AI analysis
     */
    private function prepare_ai_context($source, $errors) {
        $context = "WordPress Site Information:\n";
        $context .= "- WordPress Version: " . get_bloginfo('version') . "\n";
        $context .= "- PHP Version: " . PHP_VERSION . "\n";
        $context .= "- Active Theme: " . wp_get_theme()->get('Name') . "\n";
        
        // Get active plugins
        $active_plugins = get_option('active_plugins', array());
        $context .= "- Active Plugins: " . count($active_plugins) . " plugins\n";
        
        // Source information
        if (strpos($source, 'plugin:') === 0) {
            $plugin_name = str_replace('plugin:', '', $source);
            $context .= "- Problematic Plugin: {$plugin_name}\n";
            
            // Try to get plugin info
            $plugin_data = $this->get_plugin_data($plugin_name);
            if ($plugin_data) {
                $context .= "- Plugin Version: {$plugin_data['Version']}\n";
                $context .= "- Plugin Author: {$plugin_data['Author']}\n";
            }
        } elseif (strpos($source, 'theme:') === 0) {
            $theme_name = str_replace('theme:', '', $source);
            $context .= "- Problematic Theme: {$theme_name}\n";
        }
        
        $context .= "- Error Count: " . count($errors) . "\n";
        $context .= "- Error Types: " . implode(', ', array_unique(array_column($errors, 'type')));
        
        return $context;
    }
    
    /**
     * Get plugin data
     */
    private function get_plugin_data($plugin_name) {
        $plugins = get_plugins();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $plugin_name . '/') === 0) {
                return $plugin_data;
            }
        }
        
        return null;
    }
    
    /**
     * Store conflict analysis
     */
    private function store_conflict_analysis($source, $analysis, $errors) {
        $conflicts = get_option('wsa_pro_conflicts', array());
        
        $conflict_id = md5($source . serialize($errors));
        
        $conflicts[$conflict_id] = array(
            'source' => $source,
            'analysis' => $analysis,
            'errors' => $errors,
            'detected_at' => current_time('timestamp'),
            'status' => 'new',
            'severity' => $this->calculate_severity($errors)
        );
        
        update_option('wsa_pro_conflicts', $conflicts);
        
        // Send admin notification for critical conflicts
        if ($this->calculate_severity($errors) === 'critical') {
            $this->send_conflict_notification($conflict_id, $conflicts[$conflict_id]);
        }
    }
    
    /**
     * Calculate conflict severity
     */
    private function calculate_severity($errors) {
        foreach ($errors as $error) {
            if (in_array($error['type'], array('fatal', 'parse'))) {
                return 'critical';
            }
        }
        
        return count($errors) > 5 ? 'high' : 'medium';
    }
    
    /**
     * Send conflict notification to admin
     */
    private function send_conflict_notification($conflict_id, $conflict_data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Critical Conflict Detected - WP SiteAdvisor Pro', $site_name);
        
        $message = "A critical conflict has been detected on your WordPress site:\n\n";
        $message .= "Source: " . $conflict_data['source'] . "\n";
        $message .= "Severity: " . $conflict_data['severity'] . "\n";
        $message .= "Detected: " . date('Y-m-d H:i:s', $conflict_data['detected_at']) . "\n\n";
        $message .= "AI Analysis:\n" . $conflict_data['analysis'] . "\n\n";
        $message .= "View full details in your WordPress admin: " . admin_url('admin.php?page=wp-site-advisory&tab=conflicts');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get all detected conflicts
     */
    public function get_conflicts($status = null) {
        $conflicts = get_option('wsa_pro_conflicts', array());
        
        if ($status) {
            $conflicts = array_filter($conflicts, function($conflict) use ($status) {
                return $conflict['status'] === $status;
            });
        }
        
        // Sort by severity and date
        uasort($conflicts, function($a, $b) {
            $severity_order = array('critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3);
            
            if ($severity_order[$a['severity']] !== $severity_order[$b['severity']]) {
                return $severity_order[$a['severity']] - $severity_order[$b['severity']];
            }
            
            return $b['detected_at'] - $a['detected_at'];
        });
        
        return $conflicts;
    }
    
    /**
     * Mark conflict as resolved
     */
    public function resolve_conflict($conflict_id) {
        $conflicts = get_option('wsa_pro_conflicts', array());
        
        if (isset($conflicts[$conflict_id])) {
            $conflicts[$conflict_id]['status'] = 'resolved';
            $conflicts[$conflict_id]['resolved_at'] = current_time('timestamp');
            update_option('wsa_pro_conflicts', $conflicts);
            return true;
        }
        
        return false;
    }
    
    /**
     * AJAX handler for conflict analysis
     */
    public function ajax_get_conflict_analysis() {
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
        
        $conflicts = $this->get_conflicts();
        
        wp_send_json_success(array(
            'conflicts' => $conflicts,
            'summary' => array(
                'total' => count($conflicts),
                'critical' => count(array_filter($conflicts, function($c) { return $c['severity'] === 'critical'; })),
                'new' => count(array_filter($conflicts, function($c) { return $c['status'] === 'new'; }))
            )
        ));
    }
    
    /**
     * AJAX handler for running conflict check
     */
    public function ajax_run_conflict_check() {
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
        
        // Force a fresh check of recent errors
        $this->check_recent_errors();
        
        $conflicts = $this->get_conflicts();
        
        wp_send_json_success(array(
            'message' => sprintf(__('Conflict check completed. Found %d potential conflicts.', 'wp-site-advisory-pro'), count($conflicts)),
            'conflicts' => $conflicts,
            'count' => count($conflicts)
        ));
    }
    
    /**
     * Log events for debugging
     */
    private function log_event($event_type, $data) {
        $events = get_option('wsa_pro_events', array());
        
        // Keep only last 100 events
        if (count($events) >= 100) {
            $events = array_slice($events, -50, 50, true);
        }
        
        $events[] = array(
            'type' => $event_type,
            'data' => $data,
            'timestamp' => current_time('timestamp')
        );
        
        update_option('wsa_pro_events', $events);
    }
}