<?php
/**
 * Admin AI Chatbot
 * 
 * Provides AI-powered assistance for WordPress troubleshooting and optimization
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_AI_Chatbot {
    
    private $chat_history_option = 'wsa_pro_chat_history';
    private $max_history_length = 50;
    
    /**
     * Initialize the AI chatbot
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_wsa_ai_chat', array($this, 'ajax_ai_chat'));
        add_action('wp_ajax_wsa_get_chat_history', array($this, 'ajax_get_chat_history'));
        add_action('wp_ajax_wsa_clear_chat_history', array($this, 'ajax_clear_chat_history'));
        add_action('wp_ajax_wsa_get_suggested_questions', array($this, 'ajax_get_suggested_questions'));
        
        // Add chatbot to admin footer
        add_action('admin_footer', array($this, 'render_chatbot_widget'));
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
     * Process AI chat request
     */
    public function process_chat_message($message) {
        $openai_api_key = $this->get_openai_api_key();
        
        if (empty($openai_api_key)) {
            return array(
                'error' => 'OpenAI API key not configured. Please configure it in the free plugin Settings or Pro Settings.',
                'type' => 'configuration_error'
            );
        }
        
        // Prepare context about the WordPress site
        $site_context = $this->gather_site_context();
        
        // Get recent chat history for context
        $recent_history = $this->get_recent_chat_history(5);
        
        // Build conversation messages
        $messages = array();
        
        // System message with context
        $messages[] = array(
            'role' => 'system',
            'content' => $this->build_system_prompt($site_context)
        );
        
        // Add recent chat history
        foreach ($recent_history as $chat_item) {
            $messages[] = array(
                'role' => 'user',
                'content' => $chat_item['message']
            );
            $messages[] = array(
                'role' => 'assistant',
                'content' => $chat_item['response']
            );
        }
        
        // Add current user message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Make API call to OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $openai_api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.4,
                'frequency_penalty' => 0.3,
                'presence_penalty' => 0.1
            ))
        ));
        
        if (is_wp_error($response)) {
            return array(
                'error' => 'Failed to connect to AI service: ' . $response->get_error_message(),
                'type' => 'api_error'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($error_body, true);
            
            if (isset($error_data['error']['message'])) {
                return array(
                    'error' => 'AI service error: ' . $error_data['error']['message'],
                    'type' => 'api_error'
                );
            }
            
            return array(
                'error' => 'AI service returned status code: ' . $response_code,
                'type' => 'api_error'
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return array(
                'error' => 'Invalid response from AI service',
                'type' => 'api_error'
            );
        }
        
        $ai_response = sanitize_textarea_field($data['choices'][0]['message']['content']);
        
        // Store conversation in history
        $this->store_chat_history($message, $ai_response);
        
        return array(
            'response' => $ai_response,
            'timestamp' => current_time('timestamp'),
            'tokens_used' => $data['usage']['total_tokens'] ?? 0
        );
    }
    
    /**
     * Gather site context for AI
     */
    private function gather_site_context() {
        $context = array(
            'site_info' => array(
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'theme' => wp_get_theme()->get('Name'),
                'theme_version' => wp_get_theme()->get('Version'),
                'admin_email' => get_option('admin_email'),
                'users_count' => count_users()['total_users'] ?? 0
            ),
            'plugins' => $this->get_plugin_context(),
            'system_health' => $this->get_system_health_context(),
            'security_status' => $this->get_security_context(),
            'performance_status' => $this->get_performance_context()
        );
        
        return $context;
    }
    
    /**
     * Get plugin context
     */
    private function get_plugin_context() {
        $active_plugins = get_option('active_plugins', array());
        $all_plugins = get_plugins();
        
        $plugin_info = array(
            'active_count' => count($active_plugins),
            'total_installed' => count($all_plugins),
            'active_plugins' => array()
        );
        
        // Get info about active plugins
        foreach ($active_plugins as $plugin_file) {
            if (isset($all_plugins[$plugin_file])) {
                $plugin_data = $all_plugins[$plugin_file];
                $plugin_info['active_plugins'][] = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'author' => $plugin_data['Author']
                );
            }
        }
        
        // Limit to top 10 plugins to avoid token limit
        $plugin_info['active_plugins'] = array_slice($plugin_info['active_plugins'], 0, 10);
        
        return $plugin_info;
    }
    
    /**
     * Get system health context
     */
    private function get_system_health_context() {
        $system_scan = get_option('wsa_system_scan_results', array());
        
        $health = array(
            'last_scan' => null,
            'inactive_plugins' => 0,
            'inactive_themes' => 0,
            'performance_issues' => 0,
            'security_issues' => 0
        );
        
        if (!empty($system_scan)) {
            $health['last_scan'] = $system_scan['last_scanned'] ?? null;
            $health['inactive_plugins'] = $system_scan['inactive_plugins']['count'] ?? 0;
            $health['inactive_themes'] = $system_scan['inactive_themes']['count'] ?? 0;
            $health['performance_issues'] = $system_scan['performance_checks']['issues_found'] ?? 0;
            $health['security_issues'] = $system_scan['security_checks']['issues_found'] ?? 0;
        }
        
        return $health;
    }
    
    /**
     * Get security context
     */
    private function get_security_context() {
        $security = array(
            'overall_score' => 'unknown',
            'vulnerabilities' => 0,
            'conflicts' => 0
        );
        
        // Get security score from system scan
        $system_scan = get_option('wsa_system_scan_results', array());
        if (isset($system_scan['security_checks']['security_score'])) {
            $security['overall_score'] = $system_scan['security_checks']['security_score'];
        }
        
        // Get vulnerability count if WPScan is available
        if (class_exists('\WSA_Pro\Features\WPScan_Vulnerabilities')) {
            $wpscan = new \WSA_Pro\Features\WPScan_Vulnerabilities();
            $vuln_summary = $wpscan->get_vulnerability_summary();
            $security['vulnerabilities'] = $vuln_summary['total_vulnerabilities'];
        }
        
        // Get conflicts count if AI Conflict Detector is available
        if (class_exists('\WSA_Pro\Features\AI_Conflict_Detector')) {
            $conflict_detector = new \WSA_Pro\Features\AI_Conflict_Detector();
            $conflicts = $conflict_detector->get_conflicts();
            $security['conflicts'] = count($conflicts);
        }
        
        return $security;
    }
    
    /**
     * Get performance context
     */
    private function get_performance_context() {
        $performance = array(
            'status' => 'unknown',
            'last_analysis' => null,
            'desktop_score' => null,
            'mobile_score' => null
        );
        
        // Get PageSpeed data if available
        if (class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
            $pagespeed = new \WSA_Pro\Features\PageSpeed_Analysis();
            $perf_summary = $pagespeed->get_performance_summary();
            
            $performance['status'] = $perf_summary['status'];
            $performance['last_analysis'] = $perf_summary['last_scan'];
            
            if (isset($perf_summary['scores']['desktop']['performance'])) {
                $performance['desktop_score'] = $perf_summary['scores']['desktop']['performance']['score'];
            }
            
            if (isset($perf_summary['scores']['mobile']['performance'])) {
                $performance['mobile_score'] = $perf_summary['scores']['mobile']['performance']['score'];
            }
        }
        
        return $performance;
    }
    
    /**
     * Build system prompt for AI
     */
    private function build_system_prompt($site_context) {
        $prompt = "You are a WordPress expert assistant helping with website optimization, security, and troubleshooting. ";
        $prompt .= "You have access to detailed information about this WordPress site and should provide specific, actionable advice.\n\n";
        
        $prompt .= "CURRENT SITE INFORMATION:\n";
        $prompt .= "- Site: {$site_context['site_info']['name']} ({$site_context['site_info']['url']})\n";
        $prompt .= "- WordPress Version: {$site_context['site_info']['wordpress_version']}\n";
        $prompt .= "- PHP Version: {$site_context['site_info']['php_version']}\n";
        $prompt .= "- Theme: {$site_context['site_info']['theme']} v{$site_context['site_info']['theme_version']}\n";
        $prompt .= "- Active Plugins: {$site_context['plugins']['active_count']} of {$site_context['plugins']['total_installed']} installed\n";
        
        if (!empty($site_context['plugins']['active_plugins'])) {
            $prompt .= "- Key Active Plugins: ";
            $plugin_names = array_column($site_context['plugins']['active_plugins'], 'name');
            $prompt .= implode(', ', array_slice($plugin_names, 0, 5));
            if (count($plugin_names) > 5) {
                $prompt .= " (and " . (count($plugin_names) - 5) . " more)";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "\nCURRENT STATUS:\n";
        $prompt .= "- Security Score: {$site_context['security_status']['overall_score']}";
        if (is_numeric($site_context['security_status']['overall_score'])) {
            $prompt .= "%";
        }
        $prompt .= "\n";
        $prompt .= "- Vulnerabilities: {$site_context['security_status']['vulnerabilities']}\n";
        $prompt .= "- Plugin/Theme Conflicts: {$site_context['security_status']['conflicts']}\n";
        $prompt .= "- Performance Status: {$site_context['performance_status']['status']}\n";
        
        if ($site_context['performance_status']['desktop_score']) {
            $prompt .= "- Desktop Performance: {$site_context['performance_status']['desktop_score']}%\n";
        }
        if ($site_context['performance_status']['mobile_score']) {
            $prompt .= "- Mobile Performance: {$site_context['performance_status']['mobile_score']}%\n";
        }
        
        $prompt .= "- Inactive Plugins: {$site_context['system_health']['inactive_plugins']}\n";
        $prompt .= "- Inactive Themes: {$site_context['system_health']['inactive_themes']}\n";
        $prompt .= "- Performance Issues: {$site_context['system_health']['performance_issues']}\n";
        $prompt .= "- Security Issues: {$site_context['system_health']['security_issues']}\n";
        
        $prompt .= "\nINSTRUCTIONS:\n";
        $prompt .= "- Provide specific, actionable advice based on the site's current state\n";
        $prompt .= "- Reference specific plugins, themes, or issues when relevant\n";
        $prompt .= "- Include step-by-step instructions when appropriate\n";
        $prompt .= "- Prioritize security and performance recommendations\n";
        $prompt .= "- Keep responses concise but comprehensive (aim for 200-500 words)\n";
        $prompt .= "- Use WordPress-specific terminology and best practices\n";
        $prompt .= "- If you need more information to give a complete answer, ask specific questions\n\n";
        
        return $prompt;
    }
    
    /**
     * Store chat history
     */
    private function store_chat_history($message, $response) {
        $history = get_option($this->chat_history_option, array());
        
        // Add new conversation
        $history[] = array(
            'message' => sanitize_textarea_field($message),
            'response' => sanitize_textarea_field($response),
            'timestamp' => current_time('timestamp'),
            'user_id' => get_current_user_id()
        );
        
        // Keep only recent history to manage database size
        if (count($history) > $this->max_history_length) {
            $history = array_slice($history, -$this->max_history_length);
        }
        
        update_option($this->chat_history_option, $history);
    }
    
    /**
     * Get recent chat history
     */
    private function get_recent_chat_history($limit = 10) {
        $history = get_option($this->chat_history_option, array());
        
        // Filter by current user and return recent items
        $user_history = array_filter($history, function($item) {
            return $item['user_id'] == get_current_user_id();
        });
        
        return array_slice($user_history, -$limit);
    }
    
    /**
     * Get full chat history
     */
    public function get_chat_history() {
        return $this->get_recent_chat_history(20);
    }
    
    /**
     * Clear chat history
     */
    public function clear_chat_history() {
        $history = get_option($this->chat_history_option, array());
        
        // Remove only current user's history
        $filtered_history = array_filter($history, function($item) {
            return $item['user_id'] != get_current_user_id();
        });
        
        update_option($this->chat_history_option, array_values($filtered_history));
    }
    
    /**
     * Generate suggested questions based on site context
     */
    public function get_suggested_questions() {
        $site_context = $this->gather_site_context();
        $suggestions = array();
        
        // Security-related suggestions
        if ($site_context['security_status']['overall_score'] < 80) {
            $suggestions[] = "How can I improve my website's security score?";
        }
        
        if ($site_context['security_status']['vulnerabilities'] > 0) {
            $suggestions[] = "What should I do about the vulnerabilities found on my site?";
        }
        
        // Performance-related suggestions
        if (in_array($site_context['performance_status']['status'], array('poor', 'needs_improvement'))) {
            $suggestions[] = "How can I make my website load faster?";
            $suggestions[] = "What's causing my site's slow performance?";
        }
        
        // Plugin-related suggestions
        if ($site_context['system_health']['inactive_plugins'] > 0) {
            $suggestions[] = "Should I remove inactive plugins from my site?";
        }
        
        if ($site_context['plugins']['active_count'] > 20) {
            $suggestions[] = "Do I have too many plugins installed?";
        }
        
        // Conflicts-related suggestions
        if ($site_context['security_status']['conflicts'] > 0) {
            $suggestions[] = "How do I resolve plugin conflicts on my site?";
        }
        
        // General maintenance suggestions
        $suggestions[] = "What regular maintenance should I perform on my WordPress site?";
        $suggestions[] = "How do I optimize my WordPress database?";
        $suggestions[] = "What are the best practices for WordPress security?";
        $suggestions[] = "How do I backup my WordPress site properly?";
        
        // SEO and content suggestions
        $suggestions[] = "How can I improve my site's SEO performance?";
        $suggestions[] = "What's the best way to optimize images for WordPress?";
        
        return array_slice($suggestions, 0, 8); // Return up to 8 suggestions
    }
    
    /**
     * Render chatbot widget in admin
     */
    public function render_chatbot_widget() {
        // Only show on WP SiteAdvisor pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wp-site-advisory') === false) {
            return;
        }
        
        ?>
        <div id="wsa-ai-chatbot" class="wsa-chatbot-widget" style="display: none;">
            <div class="wsa-chatbot-header">
                <h4>AI Assistant</h4>
                <button id="wsa-chatbot-close" class="wsa-chatbot-close">×</button>
            </div>
            <div class="wsa-chatbot-messages" id="wsa-chatbot-messages">
                <div class="wsa-chatbot-welcome">
                    <p>Hi! I'm your WordPress AI assistant. I can help you with:</p>
                    <ul>
                        <li>Security optimization</li>
                        <li>Performance improvements</li>
                        <li>Plugin conflicts</li>
                        <li>General WordPress troubleshooting</li>
                    </ul>
                    <p>What would you like to know?</p>
                </div>
            </div>
            <div class="wsa-chatbot-input">
                <div class="wsa-suggested-questions" id="wsa-suggested-questions" style="display: none;">
                    <!-- Suggested questions will be loaded here -->
                </div>
                <div class="wsa-chatbot-input-area">
                    <textarea id="wsa-chatbot-message" placeholder="Ask me anything about your WordPress site..." rows="2"></textarea>
                    <button id="wsa-chatbot-send" class="button button-primary">Send</button>
                </div>
            </div>
            <div class="wsa-chatbot-actions">
                <button id="wsa-chatbot-suggestions" class="button button-small">Show Suggestions</button>
                <button id="wsa-chatbot-clear" class="button button-small">Clear Chat</button>
            </div>
        </div>
        
        <button id="wsa-chatbot-toggle" class="wsa-chatbot-toggle">
            <span class="dashicons dashicons-admin-comments"></span>
            AI Assistant
        </button>
        
        <style>
        .wsa-chatbot-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            max-width: 90vw;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .wsa-chatbot-header {
            background: #2c5aa0;
            color: white;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .wsa-chatbot-header h4 {
            margin: 0;
            font-size: 14px;
        }
        
        .wsa-chatbot-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
        }
        
        .wsa-chatbot-messages {
            height: 300px;
            overflow-y: auto;
            padding: 16px;
            background: #f9f9f9;
        }
        
        .wsa-chatbot-welcome {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #2c5aa0;
        }
        
        .wsa-chatbot-welcome ul {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        .wsa-chat-message {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 6px;
            max-width: 85%;
            word-wrap: break-word;
        }
        
        .wsa-chat-message.user {
            background: #2c5aa0;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        
        .wsa-chat-message.assistant {
            background: white;
            border: 1px solid #ddd;
            margin-right: auto;
        }
        
        .wsa-chat-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .wsa-chatbot-input {
            border-top: 1px solid #ddd;
            background: white;
        }
        
        .wsa-suggested-questions {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .wsa-suggested-questions .suggestion {
            display: inline-block;
            background: #f1f1f1;
            padding: 6px 10px;
            margin: 3px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid #ddd;
        }
        
        .wsa-suggested-questions .suggestion:hover {
            background: #e1e1e1;
        }
        
        .wsa-chatbot-input-area {
            display: flex;
            padding: 12px;
            gap: 8px;
        }
        
        .wsa-chatbot-input-area textarea {
            flex: 1;
            resize: none;
            border: 1px solid #ddd;
            border-radius: 0px;
            padding: 8px;
            font-size: 13px;
        }
        
        .wsa-chatbot-actions {
            padding: 8px 12px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 8px;
            border-radius: 0 0 8px 8px;
        }
        
        .wsa-chatbot-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 25px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 9998;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        
        .wsa-chatbot-toggle:hover {
            background: #1e3f73;
            color: white;
        }
        
        .wsa-chat-typing {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 10px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 12px;
            max-width: 85%;
        }
        
        .wsa-typing-dots {
            display: flex;
            gap: 2px;
        }
        
        .wsa-typing-dots span {
            width: 6px;
            height: 6px;
            background: #999;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .wsa-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .wsa-typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-10px); opacity: 1; }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for AI chat
     */
    public function ajax_ai_chat() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error('Message cannot be empty');
        }
        
        $result = $this->process_chat_message($message);
        
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for chat history
     */
    public function ajax_get_chat_history() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $history = $this->get_chat_history();
        
        wp_send_json_success($history);
    }
    
    /**
     * AJAX handler for clearing chat history
     */
    public function ajax_clear_chat_history() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $this->clear_chat_history();
        
        wp_send_json_success('Chat history cleared');
    }
    
    /**
     * AJAX handler for suggested questions
     */
    public function ajax_get_suggested_questions() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $suggestions = $this->get_suggested_questions();
        
        wp_send_json_success($suggestions);
    }
}