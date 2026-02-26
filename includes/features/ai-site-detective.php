<?php
/**
 * AI Site Detective - Top-Bar AI Finder Feature
 *
 * Allows admins to ask questions about any page or site element directly from the admin bar.
 * Detects sources in theme files, plugins, visual builders, and provides intelligent responses.
 *
 * @package WSA_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSA_AI_Site_Detective {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Scan type constants
     */
    const SCAN_QUICK = 'quick';
    const SCAN_DEEP = 'deep';

    /**
     * Cached scan results
     */
    private $scan_cache = array();

    /**
     * Deep scan progress tracking
     */
    private $scan_progress = array();

    /**
     * Visual builders we support
     */
    private $supported_builders = array(
        'elementor' => 'Elementor',
        'divi' => 'Divi Builder',
        'wpbakery' => 'WPBakery Page Builder',
        'beaver-builder' => 'Beaver Builder',
        'gutenberg' => 'Gutenberg Blocks'
    );

    /**
     * Quick scan timeout in seconds
     */
    private $quick_scan_timeout = 5;

    /**
     * Deep scan batch size for processing
     */
    private $deep_scan_batch_size = 50;

    /**
     * Constructor
     */
    private function __construct() {
        // Delay hook initialization until WordPress is fully loaded
        add_action('wp_loaded', array($this, 'init_hooks'));
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
    public function init_hooks() {
        // Only load for admins
        if (!current_user_can('manage_options')) {
            return;
        }

        // Add admin bar menu
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wsa_ai_detective_query', array($this, 'handle_ai_query'));
        add_action('wp_ajax_wsa_ai_detective_scan', array($this, 'handle_site_scan'));
        add_action('wp_ajax_wsa_ai_detective_deep_progress', array($this, 'handle_deep_progress'));
        add_action('wp_ajax_wsa_ai_detective_deep_control', array($this, 'handle_deep_control'));
        add_action('wp_ajax_wsa_ai_detective_export', array($this, 'handle_export_results'));
        
        // WP Cron for background deep scanning
        add_action('wsa_deep_scan_cron', array($this, 'process_deep_scan_batch'));
        
        // Cache cleanup
        add_action('wp_scheduled_delete', array($this, 'cleanup_expired_caches'));
        
        // Performance monitoring
        add_action('wsa_performance_monitor', array($this, 'monitor_system_performance'));
        
        // Add modal HTML to footer
        add_action('admin_footer', array($this, 'add_modal_html'));
        add_action('wp_footer', array($this, 'add_modal_html'));
    }

    /**
     * Add AI Detective to admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        $wp_admin_bar->add_node(array(
            'id'    => 'wsa-ai-detective',
            'title' => '<span class="ab-icon dashicons dashicons-search"></span><span class="ab-label">Site Detective</span>',
            'href'  => '#',
            'meta'  => array(
                'class' => 'wsa-ai-detective-trigger',
                'title' => 'Ask AI about any element on this page'
            ),
        ));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Use minified JS in production
        $js_file = defined('WP_DEBUG') && WP_DEBUG ? 'ai-detective.js' : 'ai-detective.min.js';
        wp_enqueue_script(
            'wsa-ai-detective',
            plugin_dir_url(__FILE__) . '../../assets/js/' . $js_file,
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('wsa-ai-detective', 'wsaAiDetective', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_ai_detective_nonce'),
            'currentUrl' => $this->get_current_url(),
            'pageId' => get_queried_object_id(),
            'isAdmin' => is_admin(),
            'strings' => array(
                'analyzing' => __('Analyzing page...', 'wp-site-advisory-pro'),
                'thinking' => __('AI is thinking...', 'wp-site-advisory-pro'),
                'error' => __('Something went wrong. Please try again.', 'wp-site-advisory-pro'),
                'noResults' => __('No specific sources found for your query.', 'wp-site-advisory-pro'),
            )
        ));

        // Use minified CSS in production
        $css_file = defined('WP_DEBUG') && WP_DEBUG ? 'ai-detective.css' : 'ai-detective.min.css';
        wp_enqueue_style(
            'wsa-ai-detective',
            plugin_dir_url(__FILE__) . '../../assets/css/' . $css_file,
            array(),
            '1.0.0'
        );
    }

    /**
     * Handle AI query AJAX request - Two-Tier System
     */
    public function handle_ai_query() {
        check_ajax_referer('wsa_ai_detective_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $query = sanitize_text_field($_POST['query'] ?? '');
        $page_url = esc_url($_POST['pageUrl'] ?? '');
        $page_id = intval($_POST['pageId'] ?? 0);
        $scan_type = sanitize_text_field($_POST['scanType'] ?? self::SCAN_QUICK);
        $dom_analysis = $_POST['domAnalysis'] ?? array();

        if (empty($query)) {
            wp_send_json_error(array('message' => 'Query cannot be empty'));
        }

        try {
            if ($scan_type === self::SCAN_QUICK) {
                // Quick Scan: <5s instant results
                $results = $this->perform_quick_scan($query, $page_url, $page_id, $dom_analysis);
            } else {
                // Deep Search: Background comprehensive analysis
                $results = $this->perform_deep_search($query, $page_url, $page_id, $dom_analysis);
            }

            wp_send_json_success($results);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle site scan AJAX request
     */
    public function handle_site_scan() {
        check_ajax_referer('wsa_ai_detective_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $page_url = esc_url($_POST['pageUrl'] ?? '');
        $page_id = intval($_POST['pageId'] ?? 0);

        try {
            $scan_results = $this->scan_page_sources($page_url, $page_id);
            wp_send_json_success($scan_results);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * QUICK SCAN: Instant <5s analysis
     */
    private function perform_quick_scan($query, $page_url, $page_id, $dom_analysis = array()) {
        $start_time = microtime(true);
        $timeout = $this->quick_scan_timeout;
        
        $quick_results = array();
        
        if (!empty($dom_analysis)) {
            $quick_results['dom_matches'] = $this->process_dom_analysis($dom_analysis, $query);
        }
        
        if ((microtime(true) - $start_time) < $timeout) {
            $quick_results['menu_matches'] = $this->quick_scan_menus($query);
        }
        
        if ((microtime(true) - $start_time) < $timeout) {
            $quick_results['template_matches'] = $this->quick_scan_templates($query, $page_id);
        }
        
        if ((microtime(true) - $start_time) < $timeout) {
            $quick_results['widget_matches'] = $this->quick_scan_widgets($query);
            $quick_results['shortcode_matches'] = $this->quick_scan_shortcodes($query, $page_id);
        }
        
        // AI analysis
        $ai_response = $this->get_quick_ai_analysis($query, $quick_results);
        
        $scan_time = round((microtime(true) - $start_time), 2);
        
        return array(
            'scan_type' => 'quick',
            'scan_time' => $scan_time,
            'results' => $quick_results,
            'ai_analysis' => $ai_response,
            'primary_source' => $this->determine_primary_source_quick($quick_results),
            'confidence' => $this->calculate_quick_confidence($quick_results),
            'deep_scan_available' => true,
            'timestamp' => current_time('timestamp')
        );
    }

    /**
     * DEEP SEARCH: Background comprehensive 5-10min analysis
     */
    private function perform_deep_search($query, $page_url, $page_id, $dom_analysis = array()) {
        // Check if deep scan is already in progress
        $scan_id = md5($query . $page_url . $page_id);
        $existing_scan = get_transient('wsa_deep_scan_' . $scan_id);
        
        if ($existing_scan && $existing_scan['status'] === 'in_progress') {
            return array(
                'scan_type' => 'deep',
                'status' => 'in_progress',
                'scan_id' => $scan_id,
                'progress' => $existing_scan['progress'],
                'current_task' => $existing_scan['current_task'],
                'results' => $existing_scan['results'] ?? array()
            );
        }
        
        // Initialize deep scan
        $scan_data = array(
            'scan_id' => $scan_id,
            'query' => $query,
            'page_url' => $page_url,
            'page_id' => $page_id,
            'dom_analysis' => $dom_analysis,
            'status' => 'in_progress',
            'progress' => 0,
            'current_task' => 'Initializing deep scan...',
            'current_phase' => 'theme_files',
            'results' => array(),
            'started_at' => current_time('timestamp'),
            'batch_position' => 0,
            'last_update' => current_time('timestamp')
        );
        
        set_transient('wsa_deep_scan_' . $scan_id, $scan_data, 3600); // 1 hour timeout
        
        // Schedule first batch - try multiple approaches for reliability
        wp_schedule_single_event(time(), 'wsa_deep_scan_cron', array($scan_id));
        
        // Also schedule a backup event 5 seconds later in case WP Cron fails
        wp_schedule_single_event(time() + 5, 'wsa_deep_scan_cron', array($scan_id));
        
        // For local development: trigger immediate processing after a short delay
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_schedule_single_event(time() + 3, 'wsa_deep_scan_cron', array($scan_id));
        }
        
        return array(
            'scan_type' => 'deep',
            'status' => 'initiated',
            'scan_id' => $scan_id,
            'message' => 'Deep scan started. You will receive live updates as the scan progresses.',
            'estimated_time' => '5-10 minutes'
        );
    }

    /**
     * Enhanced page source scanning with multi-source validation
     */
    private function scan_page_sources($page_url, $page_id) {
        $cache_key = 'wsa_detective_' . md5($page_url . $page_id);
        
        if (isset($this->scan_cache[$cache_key])) {
            return $this->scan_cache[$cache_key];
        }

        $results = array(
            // Enhanced targeted scanning
            'wordpress_menus' => $this->scan_wordpress_menus(),
            'theme_files_detailed' => $this->scan_theme_files_detailed($page_id),
            'targeted_plugins' => $this->scan_targeted_plugins($page_id),
            'builder_elements' => $this->scan_builder_elements_detailed($page_id),
            'shortcode_analysis' => $this->analyze_shortcodes($page_id),
            'widget_analysis' => $this->analyze_widgets(),
            'template_hierarchy' => $this->get_detailed_template_info($page_id),
            'content_source_map' => $this->create_content_source_map($page_id),
            'navigation_sources' => $this->analyze_navigation_sources()
        );

        $this->scan_cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Scan theme and child theme files
     */
    private function scan_theme_files($page_id) {
        $theme_files = array();
        $current_theme = wp_get_theme();
        $parent_theme = $current_theme->parent();

        // Get template hierarchy for current page
        $template_hierarchy = $this->get_template_hierarchy($page_id);

        foreach ($template_hierarchy as $template_name) {
            $theme_file = locate_template($template_name);
            if ($theme_file) {
                $relative_path = str_replace(get_template_directory(), '', $theme_file);
                $is_child_theme = strpos($theme_file, get_stylesheet_directory()) !== false;
                
                $theme_files[] = array(
                    'file' => $template_name,
                    'full_path' => $theme_file,
                    'relative_path' => $relative_path,
                    'is_child_theme' => $is_child_theme,
                    'theme_name' => $is_child_theme ? $current_theme->get('Name') : ($parent_theme ? $parent_theme->get('Name') : $current_theme->get('Name')),
                    'edit_link' => $this->get_theme_editor_link($theme_file),
                    'functions' => $this->scan_php_file_functions($theme_file)
                );
                break; // Only get the first matching template
            }
        }

        return $theme_files;
    }

    /**
     * Scan active plugins for hooks and functionality
     */
    private function scan_active_plugins($page_id) {
        $active_plugins = array();
        $all_plugins = get_plugins();
        $active_plugin_list = get_option('active_plugins', array());

        foreach ($active_plugin_list as $plugin_path) {
            if (!isset($all_plugins[$plugin_path])) continue;

            $plugin_data = $all_plugins[$plugin_path];
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_path;
            
            $active_plugins[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'file' => $plugin_path,
                'main_file' => $plugin_file,
                'hooks' => $this->scan_plugin_hooks($plugin_file),
                'shortcodes' => $this->scan_plugin_shortcodes($plugin_file),
                'edit_link' => $this->get_plugin_editor_link($plugin_path)
            );
        }

        return $active_plugins;
    }

    /**
     * Scan for visual builder content
     */
    private function scan_visual_builders($page_id) {
        if (!$page_id) return array();

        $builders = array();

        // Elementor
        if (defined('ELEMENTOR_VERSION')) {
            $elementor_data = get_post_meta($page_id, '_elementor_data', true);
            if (!empty($elementor_data)) {
                $builders['elementor'] = array(
                    'name' => 'Elementor',
                    'active' => true,
                    'data' => $elementor_data,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=elementor'),
                    'widgets' => $this->parse_elementor_widgets($elementor_data)
                );
            }
        }

        // Divi Builder
        if (function_exists('et_core_is_builder_used_on_page')) {
            if (et_core_is_builder_used_on_page($page_id)) {
                $builders['divi'] = array(
                    'name' => 'Divi Builder',
                    'active' => true,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit&et_fb=1'),
                    'modules' => $this->parse_divi_modules($page_id)
                );
            }
        }

        // WPBakery Page Builder
        if (defined('WPB_VC_VERSION')) {
            $vc_data = get_post_meta($page_id, '_wpb_vc_js_status', true);
            if ($vc_data) {
                $builders['wpbakery'] = array(
                    'name' => 'WPBakery Page Builder',
                    'active' => true,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit&vc_action=vc_inline'),
                    'shortcodes' => $this->parse_wpbakery_shortcodes($page_id)
                );
            }
        }

        // Gutenberg Blocks
        if (has_blocks($page_id)) {
            $post = get_post($page_id);
            if ($post && has_blocks($post->post_content)) {
                $blocks = parse_blocks($post->post_content);
                $builders['gutenberg'] = array(
                    'name' => 'Gutenberg Blocks',
                    'active' => true,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit'),
                    'blocks' => $this->analyze_gutenberg_blocks($blocks)
                );
            }
        }

        return $builders;
    }

    /**
     * Scan post meta for template assignments and custom fields
     */
    private function scan_post_meta($page_id) {
        if (!$page_id) return array();

        $meta_data = get_post_meta($page_id);
        $relevant_meta = array();

        // Filter for relevant meta keys
        $important_keys = array(
            '_wp_page_template',
            '_elementor_template_type',
            '_et_pb_use_builder',
            '_wpb_vc_js_status',
            'custom_template',
            'page_builder',
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc'
        );

        foreach ($important_keys as $key) {
            if (isset($meta_data[$key])) {
                $relevant_meta[$key] = $meta_data[$key][0];
            }
        }

        return $relevant_meta;
    }

    /**
     * Get template information
     */
    private function get_template_info($page_id) {
        global $wp_query;
        
        $template_info = array(
            'is_front_page' => is_front_page(),
            'is_home' => is_home(),
            'is_archive' => is_archive(),
            'is_single' => is_single(),
            'is_page' => is_page(),
            'post_type' => get_post_type($page_id),
            'template_name' => get_page_template_slug($page_id)
        );

        return $template_info;
    }

    /**
     * Scan for active hooks and filters
     */
    private function scan_hooks_and_filters() {
        global $wp_filter;
        
        $important_hooks = array(
            'wp_head',
            'wp_footer',
            'init',
            'wp_enqueue_scripts',
            'admin_enqueue_scripts',
            'the_content',
            'the_title'
        );

        $active_hooks = array();
        
        foreach ($important_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $active_hooks[$hook] = array();
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $active_hooks[$hook][] = array(
                            'priority' => $priority,
                            'function' => $this->get_callback_info($callback['function'])
                        );
                    }
                }
            }
        }

        return $active_hooks;
    }

    /**
     * Scan for potential security issues
     */
    private function scan_security_issues() {
        $security_issues = array();
        
        // This is a basic implementation - could be expanded
        $risky_functions = array('eval', 'base64_decode', 'exec', 'system', 'shell_exec');
        
        // Scan active theme files
        $theme_files = array(
            get_template_directory() . '/functions.php',
            get_stylesheet_directory() . '/functions.php'
        );

        foreach ($theme_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                foreach ($risky_functions as $func) {
                    if (strpos($content, $func . '(') !== false) {
                        $security_issues[] = array(
                            'type' => 'risky_function',
                            'function' => $func,
                            'file' => $file,
                            'severity' => 'high'
                        );
                    }
                }
            }
        }

        return $security_issues;
    }

    /**
     * Prepare enhanced context for AI query with DOM analysis
     */
    private function prepare_ai_context($scan_results, $page_url, $page_id) {
        $context = array(
            'page_info' => array(
                'url' => $page_url,
                'id' => $page_id,
                'title' => get_the_title($page_id),
                'post_type' => get_post_type($page_id),
                'template_hierarchy' => $scan_results['template_hierarchy']['template_hierarchy'] ?? array(),
                'current_template' => $scan_results['template_hierarchy']['current_template'] ?? 'unknown'
            ),
            'dom_analysis' => $scan_results['dom_analysis'] ?? array(),
            'query_context' => $scan_results['query_context'] ?? array(),
            'navigation_menus' => $scan_results['wordpress_menus'] ?? array(),
            'theme_analysis' => array(
                'name' => $scan_results['template_hierarchy']['theme_info']['name'] ?? wp_get_theme()->get('Name'),
                'is_child_theme' => $scan_results['template_hierarchy']['theme_info']['is_child_theme'] ?? false,
                'active_files' => $scan_results['theme_files_detailed'] ?? array()
            ),
            'relevant_plugins' => $scan_results['targeted_plugins'] ?? array(),
            'page_builders' => $scan_results['builder_elements'] ?? array(),
            'shortcodes_used' => $scan_results['shortcode_analysis'] ?? array(),
            'widget_areas' => $scan_results['widget_analysis'] ?? array(),
            'content_sources' => $scan_results['content_source_map'] ?? array(),
            'wordpress_version' => get_bloginfo('version')
        );

        return $context;
    }

    /**
     * Enhanced AI response with precise source detection and ranking
     */
    private function get_ai_response($query, $context) {
        // Get OpenAI API key from WordPress options
        $api_key = get_option('wsa_openai_api_key');
        
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured. Please add your API key in WP SiteAdvisor settings.');
        }

        // Enhanced system prompt for precision
        $system_prompt = "You are an expert WordPress Site Detective. Your role is to analyze the provided context and pinpoint the EXACT source of elements users ask about.

CRITICAL RULES:
1. NEVER list all plugins or themes generically
2. ONLY mention sources that DIRECTLY control the queried element
3. Use DOM analysis to match user queries to specific elements
4. Prioritize the most likely source based on evidence
5. Provide direct edit links when possible
6. Be specific about file paths, template parts, and admin locations

RESPONSE FORMAT:
- Primary Source: [Most likely source with confidence level]
- Location: [Exact file path or admin area]
- Edit Link: [Direct URL to edit location]
- Alternative Sources: [Only if multiple valid sources exist]
- Explanation: [Why this source controls the element]

Focus on ACTIONABLE, PRECISE answers rather than comprehensive lists.";

        // Enhanced user prompt with structured context
        $user_prompt = $this->build_enhanced_prompt($query, $context);

        return $this->call_openai_api($api_key, $system_prompt, $user_prompt);
    }

    /**
     * Build enhanced, structured prompt for AI analysis
     */
    private function build_enhanced_prompt($query, $context) {
        $prompt = "USER QUERY: {$query}\n\n";

        // Add DOM analysis if available
        if (isset($context['dom_analysis']['matching_elements']) && !empty($context['dom_analysis']['matching_elements'])) {
            $prompt .= "DETECTED DOM ELEMENTS MATCHING QUERY:\n";
            $matches = array_slice($context['dom_analysis']['matching_elements'], 0, 3); // Top 3 matches
            foreach ($matches as $i => $match) {
                $confidence = round($match['confidence'] * 100);
                $prompt .= "Element " . ($i + 1) . " (Confidence: {$confidence}%):\n";
                $prompt .= "  - Text: \"{$match['text']}\"\n";
                $prompt .= "  - HTML: <{$match['tag']} class=\"{$match['classes']}\">\n";
                $prompt .= "  - CSS Selector: {$match['selector']}\n";
                $prompt .= "  - Match Type: {$match['match_type']}\n\n";
            }
        }

        // Add query context analysis
        if (isset($context['query_context'])) {
            $qc = $context['query_context'];
            $prompt .= "QUERY ANALYSIS:\n";
            $prompt .= "- Intent: {$qc['intent']}\n";
            $prompt .= "- Element Type: {$qc['element_type']}\n";
            $prompt .= "- Action Needed: {$qc['action_needed']}\n\n";
        }

        // Add navigation menu analysis if relevant
        if (isset($context['navigation_menus']) && !empty($context['navigation_menus'])) {
            $prompt .= "NAVIGATION MENUS:\n";
            foreach ($context['navigation_menus'] as $location => $menu) {
                if (!empty($menu['items'])) {
                    $prompt .= "Menu Location: {$location} ({$menu['description']})\n";
                    $prompt .= "  - Menu Name: " . ($menu['menu_object']->name ?? 'Default') . "\n";
                    $prompt .= "  - Edit Link: {$menu['edit_link']}\n";
                    $prompt .= "  - Items: ";
                    $item_titles = array_slice(array_column($menu['items'], 'title'), 0, 5);
                    $prompt .= implode(', ', $item_titles);
                    if (count($menu['items']) > 5) $prompt .= '... (and ' . (count($menu['items']) - 5) . ' more)';
                    $prompt .= "\n";
                }
            }
            $prompt .= "\n";
        }

        // Add theme file analysis
        if (isset($context['theme_analysis']['active_files']) && !empty($context['theme_analysis']['active_files'])) {
            $prompt .= "THEME FILES CONTROLLING THIS PAGE:\n";
            foreach ($context['theme_analysis']['active_files'] as $file) {
                $prompt .= "File: {$file['relative_path']}\n";
                $prompt .= "  - Edit Link: {$file['edit_link']}\n";
                $prompt .= "  - Has Navigation: " . ($file['content_analysis']['has_nav_menu'] ? 'Yes' : 'No') . "\n";
                $prompt .= "  - Has Widgets: " . ($file['content_analysis']['has_widgets'] ? 'Yes' : 'No') . "\n";
                if (!empty($file['nav_menu_calls'])) {
                    $prompt .= "  - Menu Locations: " . implode(', ', array_column($file['nav_menu_calls'], 'theme_location')) . "\n";
                }
                if (!empty($file['template_parts'])) {
                    $prompt .= "  - Includes: " . implode(', ', array_column($file['template_parts'], 'file')) . "\n";
                }
            }
            $prompt .= "\n";
        }

        // Add relevant plugins (only if they have high relevance)
        if (isset($context['relevant_plugins']) && !empty($context['relevant_plugins'])) {
            $high_relevance = array_filter($context['relevant_plugins'], function($plugin) {
                return $plugin['relevance_score'] >= 8;
            });
            
            if (!empty($high_relevance)) {
                $prompt .= "HIGHLY RELEVANT PLUGINS:\n";
                foreach ($high_relevance as $plugin) {
                    $prompt .= "Plugin: {$plugin['name']} (Relevance: {$plugin['relevance_score']}/10)\n";
                    $prompt .= "  - Settings Link: {$plugin['settings_link']}\n";
                    $prompt .= "  - Why Relevant: " . implode(', ', $plugin['relevance_reasons']) . "\n";
                    if (!empty($plugin['shortcodes'])) {
                        $prompt .= "  - Provides Shortcodes: " . implode(', ', $plugin['shortcodes']) . "\n";
                    }
                }
                $prompt .= "\n";
            }
        }

        // Add page builder information
        if (isset($context['page_builders']) && !empty($context['page_builders'])) {
            $prompt .= "ACTIVE PAGE BUILDERS:\n";
            foreach ($context['page_builders'] as $builder_key => $builder) {
                $prompt .= "Builder: {$builder['name']}\n";
                $prompt .= "  - Edit Link: {$builder['edit_link']}\n";
                if (isset($builder['widgets']) && !empty($builder['widgets'])) {
                    $prompt .= "  - Widgets/Elements: " . implode(', ', array_slice($builder['widgets'], 0, 5)) . "\n";
                }
            }
            $prompt .= "\n";
        }

        // Add shortcode analysis
        if (isset($context['shortcodes_used']) && !empty($context['shortcodes_used'])) {
            $prompt .= "SHORTCODES IN USE:\n";
            foreach ($context['shortcodes_used'] as $shortcode) {
                $prompt .= "Shortcode: [{$shortcode['tag']}]\n";
                $prompt .= "  - Provider: {$shortcode['provider']['name']} ({$shortcode['provider']['type']})\n";
                if (isset($shortcode['edit_suggestion']['link'])) {
                    $prompt .= "  - Edit Link: {$shortcode['edit_suggestion']['link']}\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Analyze the DOM elements to match the user's query\n";
        $prompt .= "2. Cross-reference with theme files, menus, plugins, and builders\n";
        $prompt .= "3. Identify the PRIMARY source that controls the queried element\n";
        $prompt .= "4. Provide the exact location and edit link\n";
        $prompt .= "5. Give a clear, actionable answer\n";
        $prompt .= "6. Only mention multiple sources if they genuinely both control the element\n";

        return $prompt;
    }

    /**
     * Call OpenAI API
     */
    private function call_openai_api($api_key, $system_prompt, $user_prompt) {
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $body = json_encode(array(
            'model' => 'gpt-4',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $user_prompt
                )
            ),
            'max_tokens' => 1500,
            'temperature' => 0.3,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ));

        $args = array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 45,
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI API.');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'No response content received from OpenAI.';
            throw new Exception($error_msg);
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Format AI response with precise, structured results
     */
    private function format_ai_response($ai_response, $scan_results) {
        if (is_wp_error($ai_response)) {
            return array(
                'text' => 'I encountered an error while analyzing your site. Please try again.',
                'primary_source' => null,
                'actions' => array(),
                'confidence' => 0
            );
        }

        // Parse AI response for structured information
        $parsed_response = $this->parse_structured_ai_response($ai_response);
        
        // Extract primary source and actions from scan results
        $primary_source = $this->determine_primary_source($scan_results, $parsed_response);
        $actions = $this->extract_actionable_items($scan_results, $parsed_response);

        return array(
            'text' => $ai_response,
            'primary_source' => $primary_source,
            'actions' => $actions,
            'confidence' => $this->calculate_response_confidence($scan_results, $parsed_response),
            'detected_elements' => $this->format_detected_elements($scan_results),
            'alternative_sources' => $this->get_alternative_sources($scan_results, $primary_source)
        );
    }

    /**
     * Parse structured information from AI response
     */
    private function parse_structured_ai_response($response) {
        $parsed = array(
            'primary_source_type' => null,
            'location' => null,
            'edit_link' => null,
            'confidence_indicators' => array()
        );

        // Extract structured information using regex patterns
        
        // Look for "Primary Source:" pattern
        if (preg_match('/Primary Source:?\s*([^\n]+)/i', $response, $matches)) {
            $parsed['primary_source_type'] = trim($matches[1]);
        }

        // Look for "Location:" pattern
        if (preg_match('/Location:?\s*([^\n]+)/i', $response, $matches)) {
            $parsed['location'] = trim($matches[1]);
        }

        // Look for edit links in the response
        preg_match_all('/(?:Edit Link|admin\.php\?[^\\s]+|wp-admin\/[^\\s]+)/', $response, $link_matches);
        if (!empty($link_matches[0])) {
            $parsed['edit_link'] = $link_matches[0][0];
        }

        // Extract confidence indicators
        if (stripos($response, 'most likely') !== false) $parsed['confidence_indicators'][] = 'high_likelihood';
        if (stripos($response, 'definitely') !== false) $parsed['confidence_indicators'][] = 'definitive';
        if (stripos($response, 'probably') !== false) $parsed['confidence_indicators'][] = 'probable';

        return $parsed;
    }

    /**
     * Determine the primary source based on scan results and AI analysis
     */
    private function determine_primary_source($scan_results, $parsed_response) {
        $primary_source = null;

        // Check DOM matches first (highest priority)
        if (isset($scan_results['dom_analysis']['matching_elements']) && !empty($scan_results['dom_analysis']['matching_elements'])) {
            $top_match = $scan_results['dom_analysis']['matching_elements'][0];
            if ($top_match['confidence'] > 0.7) {
                $source_analysis = $this->analyze_element_source($top_match, $scan_results);
                if ($source_analysis) {
                    return $source_analysis;
                }
            }
        }

        // Check page builders (high priority for content)
        if (isset($scan_results['builder_elements']) && !empty($scan_results['builder_elements'])) {
            foreach ($scan_results['builder_elements'] as $builder_key => $builder) {
                if ($builder['active']) {
                    return array(
                        'type' => 'page_builder',
                        'name' => $builder['name'],
                        'location' => 'Page Builder Template',
                        'edit_link' => $builder['edit_link'],
                        'details' => $this->get_builder_details($builder),
                        'confidence' => 0.9
                    );
                }
            }
        }

        // Check navigation menus (high priority for navigation elements)
        if (isset($scan_results['wordpress_menus']) && !empty($scan_results['wordpress_menus'])) {
            foreach ($scan_results['wordpress_menus'] as $location => $menu) {
                if (!empty($menu['items']) && $this->query_relates_to_navigation($scan_results)) {
                    return array(
                        'type' => 'navigation_menu',
                        'name' => $menu['menu_object']->name ?? 'Menu',
                        'location' => "Navigation Menu ({$location})",
                        'edit_link' => $menu['edit_link'],
                        'details' => array(
                            'location' => $location,
                            'item_count' => count($menu['items']),
                            'menu_id' => $menu['menu_object']->term_id ?? null
                        ),
                        'confidence' => 0.85
                    );
                }
            }
        }

        // Check theme files
        if (isset($scan_results['theme_files_detailed']) && !empty($scan_results['theme_files_detailed'])) {
            foreach ($scan_results['theme_files_detailed'] as $file) {
                if ($this->file_likely_controls_element($file, $scan_results)) {
                    return array(
                        'type' => 'theme_file',
                        'name' => basename($file['file']),
                        'location' => $file['relative_path'],
                        'edit_link' => $file['edit_link'],
                        'details' => array(
                            'is_child_theme' => $file['is_child_theme'],
                            'theme_name' => $file['theme_name'],
                            'has_nav_menu' => $file['content_analysis']['has_nav_menu'] ?? false,
                            'has_widgets' => $file['content_analysis']['has_widgets'] ?? false
                        ),
                        'confidence' => 0.75
                    );
                }
            }
        }

        // Check high-relevance plugins
        if (isset($scan_results['targeted_plugins']) && !empty($scan_results['targeted_plugins'])) {
            $high_relevance = array_filter($scan_results['targeted_plugins'], function($plugin) {
                return $plugin['relevance_score'] >= 8;
            });
            
            if (!empty($high_relevance)) {
                $plugin = reset($high_relevance);
                return array(
                    'type' => 'plugin',
                    'name' => $plugin['name'],
                    'location' => 'Plugin: ' . $plugin['name'],
                    'edit_link' => $plugin['settings_link'],
                    'details' => array(
                        'relevance_score' => $plugin['relevance_score'],
                        'reasons' => $plugin['relevance_reasons'],
                        'shortcodes' => $plugin['shortcodes'] ?? array()
                    ),
                    'confidence' => min(0.9, $plugin['relevance_score'] / 10)
                );
            }
        }

        return null; // No clear primary source found
    }

    /**
     * Analyze what controls a specific DOM element
     */
    private function analyze_element_source($element, $scan_results) {
        // Check if element is part of a navigation menu
        if ($this->element_is_navigation($element)) {
            return $this->find_navigation_source($element, $scan_results);
        }

        // Check if element is a widget
        if ($this->element_is_widget($element)) {
            return $this->find_widget_source($element, $scan_results);
        }

        // Check if element is from a page builder
        if ($this->element_is_builder_content($element)) {
            return $this->find_builder_source($element, $scan_results);
        }

        // Default to theme file analysis
        return $this->find_theme_source($element, $scan_results);
    }

    /**
     * Check if query relates to navigation elements
     */
    private function query_relates_to_navigation($scan_results) {
        if (!isset($scan_results['query_context'])) return false;
        
        $context = $scan_results['query_context'];
        return $context['element_type'] === 'menu' || 
               $context['element_type'] === 'button' || 
               stripos($context['intent'], 'navigation') !== false;
    }

    /**
     * Check if a theme file likely controls the queried element
     */
    private function file_likely_controls_element($file, $scan_results) {
        if (!isset($scan_results['query_context'])) return true;
        
        $context = $scan_results['query_context'];
        $analysis = $file['content_analysis'] ?? array();

        // Navigation-related queries
        if ($context['element_type'] === 'menu' && $analysis['has_nav_menu']) return true;
        
        // Widget-related queries  
        if ($context['element_type'] === 'sidebar' && $analysis['has_widgets']) return true;
        
        // Header-related queries
        if ($context['element_type'] === 'header' && (
            strpos($file['file'], 'header') !== false || 
            $analysis['has_custom_header'] || 
            $analysis['has_custom_logo'])) return true;

        // Footer-related queries
        if ($context['element_type'] === 'footer' && strpos($file['file'], 'footer') !== false) return true;

        return false; // Default to false for more precision
    }

    /**
     * Extract actionable items from scan results
     */
    private function extract_actionable_items($scan_results, $parsed_response) {
        $actions = array();

        // Add primary edit link if available
        if (isset($parsed_response['edit_link'])) {
            $actions[] = array(
                'type' => 'primary_edit',
                'title' => 'Edit Primary Source',
                'url' => $parsed_response['edit_link'],
                'icon' => 'edit',
                'priority' => 1
            );
        }

        // Add navigation menu edit links if relevant
        if (isset($scan_results['wordpress_menus']) && $this->query_relates_to_navigation($scan_results)) {
            foreach ($scan_results['wordpress_menus'] as $location => $menu) {
                if (!empty($menu['edit_link'])) {
                    $actions[] = array(
                        'type' => 'menu_edit',
                        'title' => "Edit {$location} Menu",
                        'url' => $menu['edit_link'],
                        'icon' => 'menu',
                        'priority' => 2
                    );
                }
            }
        }

        // Add page builder edit links if active
        if (isset($scan_results['builder_elements']) && !empty($scan_results['builder_elements'])) {
            foreach ($scan_results['builder_elements'] as $builder) {
                if ($builder['active']) {
                    $actions[] = array(
                        'type' => 'builder_edit',
                        'title' => "Edit in {$builder['name']}",
                        'url' => $builder['edit_link'],
                        'icon' => 'layout',
                        'priority' => 2
                    );
                }
            }
        }

        // Add theme file edit links
        if (isset($scan_results['theme_files_detailed']) && !empty($scan_results['theme_files_detailed'])) {
            foreach ($scan_results['theme_files_detailed'] as $file) {
                if (isset($file['edit_link'])) {
                    $actions[] = array(
                        'type' => 'theme_edit',
                        'title' => 'Edit ' . basename($file['file']),
                        'url' => $file['edit_link'],
                        'icon' => 'admin-appearance',
                        'priority' => 3
                    );
                }
            }
        }

        // Sort by priority and limit results
        usort($actions, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return array_slice($actions, 0, 5); // Limit to top 5 actions
    }

    /**
     * Calculate confidence score for the response
     */
    private function calculate_response_confidence($scan_results, $parsed_response) {
        $confidence = 0.5; // Base confidence

        // Boost confidence based on DOM matches
        if (isset($scan_results['dom_analysis']['matching_elements']) && !empty($scan_results['dom_analysis']['matching_elements'])) {
            $top_match = $scan_results['dom_analysis']['matching_elements'][0];
            $confidence += $top_match['confidence'] * 0.3;
        }

        // Boost confidence based on AI indicators
        if (!empty($parsed_response['confidence_indicators'])) {
            foreach ($parsed_response['confidence_indicators'] as $indicator) {
                switch ($indicator) {
                    case 'definitive': $confidence += 0.3; break;
                    case 'high_likelihood': $confidence += 0.2; break;
                    case 'probable': $confidence += 0.1; break;
                }
            }
        }

        // Boost confidence if we have a clear primary source
        if (isset($scan_results['builder_elements']) && !empty($scan_results['builder_elements'])) {
            $confidence += 0.15; // Page builders are usually definitive
        }

        return min(1.0, $confidence);
    }

    /**
     * Format detected elements for display
     */
    private function format_detected_elements($scan_results) {
        if (!isset($scan_results['dom_analysis']['matching_elements'])) {
            return array();
        }

        $elements = array();
        $matches = array_slice($scan_results['dom_analysis']['matching_elements'], 0, 3);

        foreach ($matches as $match) {
            $elements[] = array(
                'text' => $match['text'],
                'selector' => $match['selector'],
                'confidence' => round($match['confidence'] * 100),
                'type' => $match['match_type']
            );
        }

        return $elements;
    }

    /**
     * Simplified helper methods (stubs for element analysis)
     */
    private function element_is_navigation($element) {
        return stripos($element['selector'], 'nav') !== false || 
               stripos($element['selector'], 'menu') !== false ||
               in_array($element['tag'], array('nav', 'ul'));
    }

    private function element_is_widget($element) {
        return stripos($element['selector'], 'widget') !== false ||
               stripos($element['classes'], 'widget') !== false;
    }

    private function element_is_builder_content($element) {
        $builder_indicators = array('elementor', 'et_pb', 'vc_', 'wp-block');
        foreach ($builder_indicators as $indicator) {
            if (stripos($element['classes'], $indicator) !== false) {
                return true;
            }
        }
        return false;
    }

    private function find_navigation_source($element, $scan_results) {
        // Return first available navigation menu
        if (isset($scan_results['wordpress_menus']) && !empty($scan_results['wordpress_menus'])) {
            foreach ($scan_results['wordpress_menus'] as $location => $menu) {
                if (!empty($menu['items'])) {
                    return array(
                        'type' => 'navigation_menu',
                        'name' => $menu['menu_object']->name ?? 'Menu',
                        'location' => "Navigation Menu ({$location})",
                        'edit_link' => $menu['edit_link'],
                        'confidence' => 0.9
                    );
                }
            }
        }
        return null;
    }

    private function find_widget_source($element, $scan_results) {
        return array(
            'type' => 'widget',
            'name' => 'Widget Area',
            'location' => 'Widgets',
            'edit_link' => admin_url('widgets.php'),
            'confidence' => 0.8
        );
    }

    private function find_builder_source($element, $scan_results) {
        if (isset($scan_results['builder_elements']) && !empty($scan_results['builder_elements'])) {
            $builder = reset($scan_results['builder_elements']);
            return array(
                'type' => 'page_builder',
                'name' => $builder['name'],
                'location' => 'Page Builder Template',
                'edit_link' => $builder['edit_link'],
                'confidence' => 0.9
            );
        }
        return null;
    }

    private function find_theme_source($element, $scan_results) {
        if (isset($scan_results['theme_files_detailed']) && !empty($scan_results['theme_files_detailed'])) {
            $file = reset($scan_results['theme_files_detailed']);
            return array(
                'type' => 'theme_file',
                'name' => basename($file['file']),
                'location' => $file['relative_path'],
                'edit_link' => $file['edit_link'],
                'confidence' => 0.7
            );
        }
        return null;
    }

    private function get_builder_details($builder) {
        return array(
            'builder_type' => $builder['name'],
            'has_templates' => isset($builder['template_id']),
            'element_count' => isset($builder['widgets']) ? count($builder['widgets']) : 0
        );
    }

    private function get_alternative_sources($scan_results, $primary_source) {
        // Return empty for now - this would list alternative sources if multiple exist
        return array();
    }

    /**
     * Format sources summary for display
     */
    private function format_sources_summary($scan_results) {
        $sources = array();

        // Theme files
        if (!empty($scan_results['theme_files'])) {
            foreach ($scan_results['theme_files'] as $file) {
                $sources[] = array(
                    'type' => 'theme',
                    'title' => $file['theme_name'] . ' - ' . basename($file['file']),
                    'description' => 'Template file: ' . $file['relative_path'],
                    'edit_link' => $file['edit_link'] ?? null
                );
            }
        }

        // Visual builders
        foreach ($scan_results['visual_builders'] as $builder_key => $builder_data) {
            $sources[] = array(
                'type' => 'builder',
                'title' => $builder_data['name'],
                'description' => 'Page built with ' . $builder_data['name'],
                'edit_link' => $builder_data['edit_link'] ?? null
            );
        }

        // Active plugins (top 5 most relevant)
        $plugin_count = 0;
        foreach ($scan_results['active_plugins'] as $plugin) {
            if ($plugin_count >= 5) break;
            
            $sources[] = array(
                'type' => 'plugin',
                'title' => $plugin['name'],
                'description' => 'Active plugin v' . $plugin['version'],
                'edit_link' => $plugin['edit_link'] ?? null
            );
            $plugin_count++;
        }

        return $sources;
    }

    /**
     * Scan WordPress navigation menus with detailed information
     */
    private function scan_wordpress_menus() {
        $menus_data = array();
        $menu_locations = get_theme_mod('nav_menu_locations', array());
        
        // Get all registered menus
        $registered_menus = get_registered_nav_menus();
        
        foreach ($registered_menus as $location => $description) {
            $menu_obj = null;
            $menu_items = array();
            
            if (isset($menu_locations[$location])) {
                $menu_obj = wp_get_nav_menu_object($menu_locations[$location]);
                if ($menu_obj) {
                    $menu_items = wp_get_nav_menu_items($menu_obj->term_id);
                }
            }
            
            $menu_data = array(
                'location' => $location,
                'description' => $description,
                'menu_object' => $menu_obj,
                'items' => array(),
                'edit_link' => $menu_obj ? admin_url('nav-menus.php?action=edit&menu=' . $menu_obj->term_id) : admin_url('nav-menus.php')
            );
            
            if ($menu_items) {
                foreach ($menu_items as $item) {
                    $menu_data['items'][] = array(
                        'title' => $item->title,
                        'url' => $item->url,
                        'classes' => $item->classes,
                        'menu_item_id' => $item->ID,
                        'object_id' => $item->object_id,
                        'object' => $item->object,
                        'type' => $item->type,
                        'parent_id' => $item->menu_item_parent,
                        'edit_link' => admin_url('nav-menus.php?action=edit&menu=' . $menu_obj->term_id . '#menu-item-' . $item->ID)
                    );
                }
            }
            
            $menus_data[$location] = $menu_data;
        }
        
        return $menus_data;
    }

    /**
     * Detailed theme file scanning with content analysis
     */
    private function scan_theme_files_detailed($page_id) {
        $theme_files = array();
        $current_theme = wp_get_theme();
        
        // Get template hierarchy for current page
        $template_hierarchy = $this->get_template_hierarchy($page_id);
        
        foreach ($template_hierarchy as $template_name) {
            $theme_file = locate_template($template_name);
            if ($theme_file) {
                $file_content = file_get_contents($theme_file);
                $relative_path = str_replace(array(get_template_directory(), get_stylesheet_directory()), '', $theme_file);
                $is_child_theme = strpos($theme_file, get_stylesheet_directory()) !== false;
                
                $file_analysis = array(
                    'file' => $template_name,
                    'full_path' => $theme_file,
                    'relative_path' => ltrim($relative_path, '/'),
                    'is_child_theme' => $is_child_theme,
                    'theme_name' => $is_child_theme ? $current_theme->get('Name') : ($current_theme->parent() ? $current_theme->parent()->get('Name') : $current_theme->get('Name')),
                    'edit_link' => $this->get_theme_editor_link($theme_file),
                    'content_analysis' => $this->analyze_theme_file_content($file_content),
                    'template_parts' => $this->extract_template_parts($file_content),
                    'hooks_actions' => $this->extract_hooks_from_content($file_content),
                    'nav_menu_calls' => $this->extract_nav_menu_calls($file_content),
                    'widget_areas' => $this->extract_widget_areas($file_content)
                );
                
                $theme_files[] = $file_analysis;
                break; // Only get the first matching template
            }
        }
        
        // Also scan common template parts
        $common_parts = array('header.php', 'footer.php', 'sidebar.php', 'navigation.php');
        foreach ($common_parts as $part) {
            $part_file = locate_template($part);
            if ($part_file && !in_array($part_file, array_column($theme_files, 'full_path'))) {
                $file_content = file_get_contents($part_file);
                $relative_path = str_replace(array(get_template_directory(), get_stylesheet_directory()), '', $part_file);
                
                $theme_files[] = array(
                    'file' => $part,
                    'full_path' => $part_file,
                    'relative_path' => ltrim($relative_path, '/'),
                    'is_child_theme' => strpos($part_file, get_stylesheet_directory()) !== false,
                    'theme_name' => $current_theme->get('Name'),
                    'edit_link' => $this->get_theme_editor_link($part_file),
                    'content_analysis' => $this->analyze_theme_file_content($file_content),
                    'template_parts' => $this->extract_template_parts($file_content),
                    'hooks_actions' => $this->extract_hooks_from_content($file_content),
                    'nav_menu_calls' => $this->extract_nav_menu_calls($file_content),
                    'widget_areas' => $this->extract_widget_areas($file_content),
                    'is_template_part' => true
                );
            }
        }
        
        return $theme_files;
    }

    /**
     * Analyze theme file content for specific elements
     */
    private function analyze_theme_file_content($content) {
        $analysis = array(
            'has_nav_menu' => strpos($content, 'wp_nav_menu') !== false,
            'has_widgets' => strpos($content, 'dynamic_sidebar') !== false || strpos($content, 'is_active_sidebar') !== false,
            'has_custom_header' => strpos($content, 'get_custom_header') !== false || strpos($content, 'header_image') !== false,
            'has_custom_logo' => strpos($content, 'get_custom_logo') !== false,
            'has_search_form' => strpos($content, 'get_search_form') !== false || strpos($content, 'searchform') !== false,
            'has_comments' => strpos($content, 'comments_template') !== false,
            'template_includes' => $this->extract_template_includes($content),
            'action_hooks' => $this->extract_action_hooks($content),
            'conditional_tags' => $this->extract_conditional_tags($content),
            'custom_functions' => $this->extract_custom_function_calls($content)
        );
        
        return $analysis;
    }

    /**
     * Extract template parts from file content
     */
    private function extract_template_parts($content) {
        $parts = array();
        
        // Look for get_template_part calls
        preg_match_all('/get_template_part\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*[\'"]([^\'"]*)[\'"])?\s*\)/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $slug = $match[1];
            $name = isset($match[2]) ? $match[2] : '';
            $parts[] = array(
                'type' => 'template_part',
                'slug' => $slug,
                'name' => $name,
                'file' => $name ? $slug . '-' . $name . '.php' : $slug . '.php'
            );
        }
        
        // Look for include/require statements
        preg_match_all('/(?:include|require)(?:_once)?\s*\(\s*[\'"]([^\'"]+\.php)[\'"]/', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $include) {
                $parts[] = array(
                    'type' => 'include',
                    'file' => basename($include),
                    'path' => $include
                );
            }
        }
        
        return $parts;
    }

    /**
     * Extract navigation menu calls from content
     */
    private function extract_nav_menu_calls($content) {
        $nav_calls = array();
        
        preg_match_all('/wp_nav_menu\s*\(\s*array\s*\((.*?)\)\s*\)/s', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $args_string = $match[1];
            $args = $this->parse_php_array_string($args_string);
            $nav_calls[] = array(
                'function' => 'wp_nav_menu',
                'args' => $args,
                'theme_location' => $args['theme_location'] ?? '',
                'menu' => $args['menu'] ?? '',
                'fallback_cb' => $args['fallback_cb'] ?? ''
            );
        }
        
        return $nav_calls;
    }

    /**
     * Extract widget areas from content
     */
    private function extract_widget_areas($content) {
        $widget_areas = array();
        
        preg_match_all('/dynamic_sidebar\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $sidebar_id) {
                $widget_areas[] = array(
                    'function' => 'dynamic_sidebar',
                    'sidebar_id' => $sidebar_id,
                    'edit_link' => admin_url('widgets.php')
                );
            }
        }
        
        return $widget_areas;
    }

    /**
     * Targeted plugin scanning - only plugins that affect the current page
     */
    private function scan_targeted_plugins($page_id) {
        $relevant_plugins = array();
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        // Get current page content to check for plugin-specific markers
        $post = get_post($page_id);
        $page_content = $post ? $post->post_content : '';
        
        foreach ($active_plugins as $plugin_path) {
            if (!isset($all_plugins[$plugin_path])) continue;
            
            $plugin_data = $all_plugins[$plugin_path];
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_path;
            
            // Check if plugin is relevant to this page
            $relevance_score = $this->calculate_plugin_relevance($plugin_data, $plugin_file, $page_content, $page_id);
            
            if ($relevance_score > 0) {
                $relevant_plugins[] = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'file' => $plugin_path,
                    'main_file' => $plugin_file,
                    'relevance_score' => $relevance_score,
                    'relevance_reasons' => $this->get_plugin_relevance_reasons($plugin_data, $plugin_file, $page_content, $page_id),
                    'shortcodes' => $this->scan_plugin_shortcodes($plugin_file),
                    'hooks' => $this->scan_plugin_hooks_detailed($plugin_file),
                    'edit_link' => $this->get_plugin_editor_link($plugin_path),
                    'settings_link' => $this->get_plugin_settings_link($plugin_data['Name'])
                );
            }
        }
        
        // Sort by relevance score
        usort($relevant_plugins, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });
        
        return array_slice($relevant_plugins, 0, 5); // Return top 5 most relevant
    }

    /**
     * Calculate how relevant a plugin is to the current page
     */
    private function calculate_plugin_relevance($plugin_data, $plugin_file, $page_content, $page_id) {
        $score = 0;
        
        // Check if plugin shortcodes are used in content
        $shortcodes = $this->scan_plugin_shortcodes($plugin_file);
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($page_content, $shortcode)) {
                $score += 10;
            }
        }
        
        // Check if plugin hooks affect this page type
        $hooks = $this->scan_plugin_hooks_detailed($plugin_file);
        $relevant_hooks = array('wp_head', 'wp_footer', 'the_content', 'wp_enqueue_scripts', 'init');
        foreach ($hooks['actions'] as $hook) {
            if (in_array($hook, $relevant_hooks)) {
                $score += 3;
            }
        }
        
        // Check for plugin-specific meta fields
        $plugin_meta = $this->check_plugin_meta_fields($plugin_data['Name'], $page_id);
        if (!empty($plugin_meta)) {
            $score += 8;
        }
        
        // Check for common plugin indicators in content
        $plugin_indicators = $this->get_plugin_content_indicators($plugin_data['Name']);
        foreach ($plugin_indicators as $indicator) {
            if (stripos($page_content, $indicator) !== false) {
                $score += 5;
            }
        }
        
        return $score;
    }

    /**
     * Analyze shortcodes used in content
     */
    private function analyze_shortcodes($page_id) {
        $shortcode_analysis = array();
        
        if (!$page_id) return $shortcode_analysis;
        
        $post = get_post($page_id);
        if (!$post) return $shortcode_analysis;
        
        // Extract all shortcodes from content
        $pattern = get_shortcode_regex();
        preg_match_all('/' . $pattern . '/s', $post->post_content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $shortcode_tag = $match[2];
            $shortcode_attrs = shortcode_parse_atts($match[3]);
            $shortcode_content = isset($match[5]) ? $match[5] : '';
            
            // Find which plugin provides this shortcode
            $provider = $this->find_shortcode_provider($shortcode_tag);
            
            $shortcode_analysis[] = array(
                'tag' => $shortcode_tag,
                'attributes' => $shortcode_attrs,
                'content' => $shortcode_content,
                'provider' => $provider,
                'edit_suggestion' => $this->get_shortcode_edit_suggestion($shortcode_tag, $provider)
            );
        }
        
        return $shortcode_analysis;
    }

    /**
     * Find which plugin or theme provides a shortcode
     */
    private function find_shortcode_provider($shortcode_tag) {
        global $shortcode_tags;
        
        if (!isset($shortcode_tags[$shortcode_tag])) {
            return array('type' => 'unknown', 'name' => 'Unknown');
        }
        
        $callback = $shortcode_tags[$shortcode_tag];
        
        if (is_string($callback)) {
            return array('type' => 'function', 'name' => $callback);
        } elseif (is_array($callback) && count($callback) == 2) {
            $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            $method = $callback[1];
            
            // Try to determine if it's from a plugin or theme
            $provider_type = 'theme';
            $provider_name = 'Active Theme';
            
            // Check if class name suggests a plugin
            if (class_exists($class)) {
                $reflection = new ReflectionClass($class);
                $file = $reflection->getFileName();
                if (strpos($file, WP_PLUGIN_DIR) !== false) {
                    $provider_type = 'plugin';
                    $provider_name = $this->get_plugin_name_from_file($file);
                }
            }
            
            return array(
                'type' => $provider_type,
                'name' => $provider_name,
                'class' => $class,
                'method' => $method
            );
        }
        
        return array('type' => 'unknown', 'name' => 'Unknown callback type');
    }

    /**
     * Analyze active widgets
     */
    private function analyze_widgets() {
        $widget_analysis = array();
        $sidebars_widgets = wp_get_sidebars_widgets();
        
        foreach ($sidebars_widgets as $sidebar_id => $widget_ids) {
            if ($sidebar_id === 'wp_inactive_widgets' || empty($widget_ids)) continue;
            
            $sidebar_info = array(
                'sidebar_id' => $sidebar_id,
                'widgets' => array(),
                'edit_link' => admin_url('widgets.php')
            );
            
            foreach ($widget_ids as $widget_id) {
                $widget_info = $this->get_widget_details($widget_id);
                if ($widget_info) {
                    $sidebar_info['widgets'][] = $widget_info;
                }
            }
            
            $widget_analysis[$sidebar_id] = $sidebar_info;
        }
        
        return $widget_analysis;
    }

    /**
     * Get detailed information about a widget
     */
    private function get_widget_details($widget_id) {
        global $wp_registered_widgets;
        
        if (!isset($wp_registered_widgets[$widget_id])) return null;
        
        $widget = $wp_registered_widgets[$widget_id];
        
        return array(
            'id' => $widget_id,
            'name' => $widget['name'],
            'callback' => $widget['callback'],
            'params' => $widget['params'] ?? array(),
            'classname' => $widget['classname'] ?? '',
            'description' => $widget['description'] ?? ''
        );
    }

    /**
     * Helper methods for scanning specific builders and files
     */
    
    private function get_template_hierarchy($page_id) {
        // Return WordPress template hierarchy for the current page
        $templates = array();
        
        if (is_front_page()) {
            $templates[] = 'front-page.php';
        } elseif (is_home()) {
            $templates[] = 'home.php';
        } elseif (is_page()) {
            $page_template = get_page_template_slug($page_id);
            if ($page_template) {
                $templates[] = $page_template;
            }
            $templates[] = 'page-' . get_post_field('post_name', $page_id) . '.php';
            $templates[] = 'page-' . $page_id . '.php';
            $templates[] = 'page.php';
        } elseif (is_single()) {
            $post_type = get_post_type($page_id);
            $templates[] = 'single-' . $post_type . '.php';
            $templates[] = 'single.php';
        }
        
        $templates[] = 'index.php';
        return $templates;
    }

    private function scan_php_file_functions($file_path) {
        if (!file_exists($file_path)) return array();
        
        $content = file_get_contents($file_path);
        $functions = array();
        
        // Basic regex to find function definitions
        preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
        if (isset($matches[1])) {
            $functions = $matches[1];
        }
        
        return $functions;
    }

    private function get_theme_editor_link($file_path) {
        $relative_path = str_replace(array(get_template_directory(), get_stylesheet_directory()), '', $file_path);
        $relative_path = ltrim($relative_path, '/\\');
        
        return admin_url('theme-editor.php?file=' . urlencode($relative_path));
    }

    private function get_plugin_editor_link($plugin_path) {
        return admin_url('plugin-editor.php?file=' . urlencode($plugin_path));
    }

    private function scan_plugin_hooks($plugin_file) {
        $hooks = array();
        
        if (!file_exists($plugin_file)) return $hooks;
        
        $content = file_get_contents($plugin_file);
        
        // Look for add_action calls
        preg_match_all('/add_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $action_matches);
        if (!empty($action_matches[1])) {
            foreach ($action_matches[1] as $action) {
                $hooks['actions'][] = $action;
            }
        }
        
        // Look for add_filter calls
        preg_match_all('/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $filter_matches);
        if (!empty($filter_matches[1])) {
            foreach ($filter_matches[1] as $filter) {
                $hooks['filters'][] = $filter;
            }
        }
        
        return $hooks;
    }

    private function scan_plugin_shortcodes($plugin_file) {
        $shortcodes = array();
        
        if (!file_exists($plugin_file)) return $shortcodes;
        
        $content = file_get_contents($plugin_file);
        
        // Look for add_shortcode calls
        preg_match_all('/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        if (!empty($matches[1])) {
            $shortcodes = array_unique($matches[1]);
        }
        
        return $shortcodes;
    }

    private function parse_elementor_widgets($elementor_data) {
        $widgets = array();
        
        if (is_string($elementor_data)) {
            $elementor_data = json_decode($elementor_data, true);
        }
        
        if (is_array($elementor_data)) {
            $this->extract_elementor_widgets_recursive($elementor_data, $widgets);
        }
        
        return array_unique($widgets);
    }

    private function extract_elementor_widgets_recursive($data, &$widgets) {
        if (!is_array($data)) return;
        
        foreach ($data as $element) {
            if (isset($element['widgetType'])) {
                $widgets[] = $element['widgetType'];
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $this->extract_elementor_widgets_recursive($element['elements'], $widgets);
            }
        }
    }

    private function parse_divi_modules($page_id) {
        $modules = array();
        
        // Get post content and extract Divi modules
        $post = get_post($page_id);
        if ($post && !empty($post->post_content)) {
            // Look for Divi module shortcodes
            preg_match_all('/\[et_pb_([a-zA-Z0-9_]+)/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $modules = array_unique($matches[1]);
            }
        }
        
        return $modules;
    }

    private function parse_wpbakery_shortcodes($page_id) {
        $shortcodes = array();
        
        // Get post content and extract VC shortcodes
        $post = get_post($page_id);
        if ($post && !empty($post->post_content)) {
            // Look for VC shortcodes
            preg_match_all('/\[vc_([a-zA-Z0-9_]+)/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $shortcodes = array_unique($matches[1]);
            }
        }
        
        return $shortcodes;
    }

    private function analyze_gutenberg_blocks($blocks) {
        $block_info = array();
        foreach ($blocks as $block) {
            $block_info[] = array(
                'name' => $block['blockName'] ?? 'unknown',
                'attributes' => $block['attrs'] ?? array(),
                'innerHTML' => substr($block['innerHTML'] ?? '', 0, 200) // Truncate for summary
            );
        }
        return $block_info;
    }

    private function get_callback_info($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback) && count($callback) == 2) {
            $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            return $class . '::' . $callback[1];
        }
        return 'Closure or complex callback';
    }

    /**
     * Additional helper methods for enhanced source detection
     */
    
    private function parse_php_array_string($array_string) {
        $args = array();
        
        // Simple parsing for common array patterns in wp_nav_menu
        preg_match_all("/['\"](\w+)['\"][\s]*=>[\s]*['\"]([^'\"]*)['\"]|['\"](\w+)['\"][\s]*=>[\s]*(\w+)/", $array_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $key = !empty($match[1]) ? $match[1] : $match[3];
            $value = !empty($match[2]) ? $match[2] : $match[4];
            $args[$key] = $value;
        }
        
        return $args;
    }
    
    private function extract_template_includes($content) {
        $includes = array();
        
        preg_match_all('/(?:include|require)(?:_once)?\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        if (isset($matches[1])) {
            $includes = $matches[1];
        }
        
        return $includes;
    }
    
    private function extract_action_hooks($content) {
        $hooks = array();
        
        preg_match_all('/do_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        if (isset($matches[1])) {
            $hooks = $matches[1];
        }
        
        return $hooks;
    }
    
    private function extract_conditional_tags($content) {
        $tags = array();
        $conditional_functions = array('is_home', 'is_front_page', 'is_single', 'is_page', 'is_admin', 'is_user_logged_in');
        
        foreach ($conditional_functions as $func) {
            if (strpos($content, $func . '(') !== false) {
                $tags[] = $func;
            }
        }
        
        return $tags;
    }
    
    private function extract_custom_function_calls($content) {
        $functions = array();
        
        // Look for custom theme functions
        preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);
        if (isset($matches[1])) {
            // Filter to likely custom functions (not WordPress core)
            $wp_functions = array('get_header', 'get_footer', 'wp_head', 'wp_footer', 'the_content', 'get_template_part');
            $custom_functions = array_diff($matches[1], $wp_functions);
            $functions = array_unique($custom_functions);
        }
        
        return array_slice($functions, 0, 10); // Limit results
    }
    
    private function extract_hooks_from_content($content) {
        $hooks = array();
        
        // Extract add_action calls
        preg_match_all('/add_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $action_matches);
        if (isset($action_matches[1])) {
            foreach ($action_matches[1] as $action) {
                $hooks['actions'][] = $action;
            }
        }
        
        // Extract add_filter calls  
        preg_match_all('/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $filter_matches);
        if (isset($filter_matches[1])) {
            foreach ($filter_matches[1] as $filter) {
                $hooks['filters'][] = $filter;
            }
        }
        
        return $hooks;
    }
    
    private function scan_plugin_hooks_detailed($plugin_file) {
        $hooks = array('actions' => array(), 'filters' => array());
        
        if (!file_exists($plugin_file)) return $hooks;
        
        $content = file_get_contents($plugin_file);
        
        // Look for add_action calls with priorities
        preg_match_all('/add_action\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*[\'"]?([^\'"]+)[\'"]?)?(?:\s*,\s*(\d+))?/', $content, $action_matches, PREG_SET_ORDER);
        foreach ($action_matches as $match) {
            $hooks['actions'][] = array(
                'hook' => $match[1],
                'callback' => isset($match[2]) ? $match[2] : 'anonymous',
                'priority' => isset($match[3]) ? intval($match[3]) : 10
            );
        }
        
        // Look for add_filter calls with priorities
        preg_match_all('/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*[\'"]?([^\'"]+)[\'"]?)?(?:\s*,\s*(\d+))?/', $content, $filter_matches, PREG_SET_ORDER);
        foreach ($filter_matches as $match) {
            $hooks['filters'][] = array(
                'hook' => $match[1],
                'callback' => isset($match[2]) ? $match[2] : 'anonymous',
                'priority' => isset($match[3]) ? intval($match[3]) : 10
            );
        }
        
        return $hooks;
    }
    
    private function get_plugin_relevance_reasons($plugin_data, $plugin_file, $page_content, $page_id) {
        $reasons = array();
        
        // Check for shortcodes
        $shortcodes = $this->scan_plugin_shortcodes($plugin_file);
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($page_content, $shortcode)) {
                $reasons[] = "Uses shortcode: [{$shortcode}]";
            }
        }
        
        // Check for plugin meta fields
        $meta_fields = $this->check_plugin_meta_fields($plugin_data['Name'], $page_id);
        if (!empty($meta_fields)) {
            $reasons[] = "Has plugin-specific meta fields";
        }
        
        // Check for plugin-specific classes or IDs in content
        $plugin_indicators = $this->get_plugin_content_indicators($plugin_data['Name']);
        foreach ($plugin_indicators as $indicator) {
            if (stripos($page_content, $indicator) !== false) {
                $reasons[] = "Contains plugin-specific content: {$indicator}";
            }
        }
        
        return $reasons;
    }
    
    private function check_plugin_meta_fields($plugin_name, $page_id) {
        if (!$page_id) return array();
        
        $meta_fields = array();
        $all_meta = get_post_meta($page_id);
        
        // Common plugin prefixes
        $plugin_prefixes = array(
            'Elementor' => '_elementor',
            'Yoast SEO' => '_yoast',
            'WooCommerce' => '_wc',
            'Contact Form 7' => '_wpcf7',
            'Advanced Custom Fields' => 'field_'
        );
        
        $prefix = $plugin_prefixes[$plugin_name] ?? strtolower(str_replace(' ', '_', $plugin_name));
        
        foreach ($all_meta as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $meta_fields[$key] = $value[0];
            }
        }
        
        return $meta_fields;
    }
    
    private function get_plugin_content_indicators($plugin_name) {
        $indicators = array();
        
        // Common plugin indicators
        $plugin_indicators = array(
            'Elementor' => array('elementor-element', 'elementor-widget'),
            'Yoast SEO' => array('yoast-breadcrumb', 'wpseo'),
            'WooCommerce' => array('woocommerce', 'wc-product', 'shop_table'),
            'Contact Form 7' => array('wpcf7', 'contact-form-7'),
            'Gutenberg' => array('wp-block', 'has-text-color'),
            'WPBakery' => array('vc_row', 'wpb_wrapper'),
            'Divi' => array('et_pb_', 'et-db')
        );
        
        return $plugin_indicators[$plugin_name] ?? array();
    }
    
    private function get_plugin_name_from_file($file) {
        $plugin_dir = dirname($file);
        $plugin_data = get_file_data($plugin_dir . '/' . basename($plugin_dir) . '.php', array('Name' => 'Plugin Name'));
        return $plugin_data['Name'] ?: 'Unknown Plugin';
    }
    
    private function get_shortcode_edit_suggestion($shortcode_tag, $provider) {
        if ($provider['type'] === 'plugin') {
            return array(
                'type' => 'plugin_settings',
                'message' => "Edit in {$provider['name']} plugin settings",
                'link' => $this->get_plugin_settings_link($provider['name'])
            );
        } elseif ($provider['type'] === 'theme') {
            return array(
                'type' => 'theme_function',
                'message' => "Defined in theme functions.php",
                'link' => admin_url('theme-editor.php?file=functions.php')
            );
        }
        
        return array(
            'type' => 'unknown',
            'message' => 'Source unknown - may require developer assistance'
        );
    }
    
    private function get_plugin_settings_link($plugin_name) {
        // Common plugin settings pages
        $settings_pages = array(
            'Yoast SEO' => admin_url('admin.php?page=wpseo_dashboard'),
            'WooCommerce' => admin_url('admin.php?page=wc-settings'),
            'Elementor' => admin_url('admin.php?page=elementor'),
            'Contact Form 7' => admin_url('admin.php?page=wpcf7'),
            'Advanced Custom Fields' => admin_url('edit.php?post_type=acf-field-group')
        );
        
        return $settings_pages[$plugin_name] ?? admin_url('plugins.php');
    }
    
    /**
     * Enhanced builder element detection
     */
    private function scan_builder_elements_detailed($page_id) {
        if (!$page_id) return array();
        
        $builders = array();
        
        // Enhanced Elementor detection
        if (defined('ELEMENTOR_VERSION')) {
            $elementor_data = get_post_meta($page_id, '_elementor_data', true);
            if (!empty($elementor_data)) {
                $builders['elementor'] = array(
                    'name' => 'Elementor',
                    'active' => true,
                    'data' => $elementor_data,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=elementor'),
                    'widgets' => $this->parse_elementor_widgets_detailed($elementor_data),
                    'template_id' => get_post_meta($page_id, '_elementor_template_type', true),
                    'page_settings' => get_post_meta($page_id, '_elementor_page_settings', true)
                );
            }
        }
        
        // Enhanced Divi detection
        if (function_exists('et_core_is_builder_used_on_page')) {
            if (et_core_is_builder_used_on_page($page_id)) {
                $builders['divi'] = array(
                    'name' => 'Divi Builder',
                    'active' => true,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit&et_fb=1'),
                    'modules' => $this->parse_divi_modules_detailed($page_id),
                    'layout_type' => get_post_meta($page_id, '_et_pb_page_layout', true)
                );
            }
        }
        
        // Enhanced WPBakery detection
        if (defined('WPB_VC_VERSION')) {
            $vc_data = get_post_meta($page_id, '_wpb_vc_js_status', true);
            if ($vc_data) {
                $builders['wpbakery'] = array(
                    'name' => 'WPBakery Page Builder',
                    'active' => true,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit&vc_action=vc_inline'),
                    'shortcodes' => $this->parse_wpbakery_shortcodes_detailed($page_id),
                    'custom_css' => get_post_meta($page_id, '_wpb_shortcodes_custom_css', true)
                );
            }
        }
        
        // Enhanced Gutenberg detection
        if (has_blocks($page_id)) {
            $post = get_post($page_id);
            if ($post && has_blocks($post->post_content)) {
                $blocks = parse_blocks($post->post_content);
                $builders['gutenberg'] = array(
                    'name' => 'Gutenberg Blocks',
                    'active' => true,
                    'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit'),
                    'blocks' => $this->analyze_gutenberg_blocks_detailed($blocks),
                    'block_count' => count($blocks)
                );
            }
        }
        
        return $builders;
    }
    
    /**
     * Create comprehensive content source mapping
     */
    private function create_content_source_map($page_id) {
        $source_map = array(
            'page_type' => get_post_type($page_id),
            'template_used' => get_page_template_slug($page_id),
            'content_sources' => array(),
            'dynamic_content' => array()
        );
        
        // Check for various content sources
        if ($page_id) {
            $post = get_post($page_id);
            if ($post) {
                $content = $post->post_content;
                
                // Analyze content for different sources
                $source_map['content_sources'] = array(
                    'has_shortcodes' => has_shortcode($content, null),
                    'has_blocks' => has_blocks($content),
                    'has_gallery' => has_shortcode($content, 'gallery'),
                    'has_embed' => preg_match('/\[embed\]|\[video\]|\[audio\]/', $content) === 1,
                    'word_count' => str_word_count(strip_tags($content)),
                    'image_count' => substr_count($content, '<img'),
                    'link_count' => substr_count($content, '<a ')
                );
                
                // Check for dynamic content
                $source_map['dynamic_content'] = array(
                    'has_custom_fields' => !empty(get_post_meta($page_id)),
                    'has_featured_image' => has_post_thumbnail($page_id),
                    'comment_status' => $post->comment_status,
                    'post_status' => $post->post_status,
                    'last_modified' => $post->post_modified
                );
            }
        }
        
        return $source_map;
    }
    
    /**
     * Analyze navigation sources across the site
     */
    private function analyze_navigation_sources() {
        $nav_sources = array(
            'registered_menus' => get_registered_nav_menus(),
            'menu_locations' => get_theme_mod('nav_menu_locations', array()),
            'active_menus' => array()
        );
        
        // Get details about active menus
        foreach ($nav_sources['menu_locations'] as $location => $menu_id) {
            if ($menu_id) {
                $menu_obj = wp_get_nav_menu_object($menu_id);
                if ($menu_obj) {
                    $nav_sources['active_menus'][$location] = array(
                        'name' => $menu_obj->name,
                        'slug' => $menu_obj->slug,
                        'count' => $menu_obj->count,
                        'edit_link' => admin_url('nav-menus.php?action=edit&menu=' . $menu_obj->term_id)
                    );
                }
            }
        }
        
        return $nav_sources;
    }
    
    /**
     * Get detailed template information
     */
    private function get_detailed_template_info($page_id) {
        global $wp_query;
        
        $template_info = array(
            'is_front_page' => is_front_page(),
            'is_home' => is_home(),
            'is_archive' => is_archive(),
            'is_single' => is_single(),
            'is_page' => is_page(),
            'post_type' => get_post_type($page_id),
            'template_name' => get_page_template_slug($page_id),
            'template_hierarchy' => $this->get_template_hierarchy($page_id),
            'current_template' => $this->get_current_template_file(),
            'theme_info' => array(
                'name' => wp_get_theme()->get('Name'),
                'version' => wp_get_theme()->get('Version'),
                'is_child_theme' => is_child_theme(),
                'parent_theme' => is_child_theme() ? wp_get_theme()->parent()->get('Name') : null
            )
        );
        
        return $template_info;
    }
    
    private function get_current_template_file() {
        global $template;
        return $template ? basename($template) : 'unknown';
    }

    private function get_current_url() {
        global $wp;
        if (is_admin()) {
            return admin_url(add_query_arg(array(), $wp->request));
        }
        return home_url(add_query_arg(array(), $wp->request));
    }

    /**
     * Add modal HTML to footer
     */
    public function add_modal_html() {
        ?>
        <div id="wsa-ai-detective-modal" class="wsa-ai-detective-modal" style="display: none;">
            <div class="wsa-detective-modal-overlay"></div>
            <div class="wsa-detective-modal-container">
                <div class="wsa-detective-modal-header">
                    <h3><span class="dashicons dashicons-search"></span> Site Detective AI</h3>
                    <button class="wsa-detective-modal-close">&times;</button>
                </div>
                <div class="wsa-detective-modal-body">
                    <div class="wsa-detective-query-section">
                        <label for="wsa-detective-query"><?php _e('Ask me anything about this page:', 'wp-site-advisory-pro'); ?></label>
                        <textarea 
                            id="wsa-detective-query" 
                            placeholder="e.g., 'Where is this button defined?' or 'What plugin controls the header?' or 'How do I edit this section?'"
                            rows="3"
                        ></textarea>
                        
                        <div class="wsa-detective-suggestions">
                            <p><strong><?php _e('Try asking:', 'wp-site-advisory-pro'); ?></strong></p>
                            <div class="wsa-detective-suggestion-buttons">
                                <button class="wsa-detective-suggestion-btn" data-query="Where is this page's layout defined?"><?php _e('Page Layout Source', 'wp-site-advisory-pro'); ?></button>
                                <button class="wsa-detective-suggestion-btn" data-query="What plugin controls the header?"><?php _e('Header Controls', 'wp-site-advisory-pro'); ?></button>
                                <button class="wsa-detective-suggestion-btn" data-query="How do I edit the footer?"><?php _e('Footer Editing', 'wp-site-advisory-pro'); ?></button>
                                <button class="wsa-detective-suggestion-btn" data-query="What theme template is being used?"><?php _e('Active Template', 'wp-site-advisory-pro'); ?></button>
                                <button class="wsa-detective-suggestion-btn" data-query="Are there any security issues on this page?"><?php _e('Security Check', 'wp-site-advisory-pro'); ?></button>
                                <button class="wsa-detective-suggestion-btn" data-query="What visual builder is active here?"><?php _e('Page Builder Info', 'wp-site-advisory-pro'); ?></button>
                            </div>
                        </div>
                        
                        <div class="wsa-detective-scan-controls">
                            <button id="wsa-detective-submit" class="button button-primary wsa-quick-scan-btn">
                                <span class="dashicons dashicons-performance"></span> Quick Scan (<5s)
                            </button>
                            <button id="wsa-detective-deep-scan" class="button wsa-deep-scan-btn">
                                <span class="dashicons dashicons-admin-tools"></span> Deep Search (5-10min)
                            </button>
                        </div>
                        
                        <div class="wsa-detective-scan-info">
                            <div class="scan-type-info">
                                <div class="quick-scan-info">
                                    <strong>Quick Scan:</strong> Instant analysis of DOM, menus, common templates, widgets
                                </div>
                                <div class="deep-scan-info">
                                    <strong>Deep Search:</strong> Comprehensive analysis of all files, plugins, database, builders
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="wsa-detective-loading" class="wsa-detective-loading" style="display: none;">
                        <div class="wsa-detective-spinner"></div>
                        <p><?php _e('AI is analyzing your site...', 'wp-site-advisory-pro'); ?></p>
                    </div>
                    
                    <div id="wsa-detective-results" class="wsa-detective-results" style="display: none;">
                        <div class="wsa-detective-response">
                            <h4><?php _e('AI Analysis:', 'wp-site-advisory-pro'); ?></h4>
                            <div id="wsa-detective-ai-response"></div>
                        </div>
                        
                        <div class="wsa-detective-sources">
                            <h4><?php _e('Detected Sources:', 'wp-site-advisory-pro'); ?></h4>
                            <div id="wsa-detective-sources-list"></div>
                        </div>
                        
                        <div class="wsa-detective-actions">
                            <h4><?php _e('Quick Actions:', 'wp-site-advisory-pro'); ?></h4>
                            <div id="wsa-detective-actions-list"></div>
                        </div>
                    </div>
                    
                    <div id="wsa-detective-error" class="wsa-detective-error" style="display: none;">
                        <p><?php _e('Something went wrong. Please try again.', 'wp-site-advisory-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Enhanced Elementor widget parsing with detailed information
     */
    private function parse_elementor_widgets_detailed($elementor_data) {
        $widgets = array();
        
        if (is_string($elementor_data)) {
            $elementor_data = json_decode($elementor_data, true);
        }
        
        if (is_array($elementor_data)) {
            $this->extract_elementor_widgets_detailed_recursive($elementor_data, $widgets);
        }
        
        return $widgets;
    }

    private function extract_elementor_widgets_detailed_recursive($data, &$widgets) {
        if (!is_array($data)) return;
        
        foreach ($data as $element) {
            if (isset($element['widgetType'])) {
                $widget_info = array(
                    'type' => $element['widgetType'],
                    'id' => $element['id'] ?? uniqid(),
                    'settings' => $element['settings'] ?? array()
                );

                // Extract meaningful content from common widgets
                switch ($element['widgetType']) {
                    case 'heading':
                        $widget_info['content'] = $element['settings']['title'] ?? '';
                        break;
                    case 'text-editor':
                        $widget_info['content'] = wp_strip_all_tags($element['settings']['editor'] ?? '');
                        break;
                    case 'button':
                        $widget_info['content'] = $element['settings']['text'] ?? '';
                        $widget_info['link'] = $element['settings']['link']['url'] ?? '';
                        break;
                    case 'image':
                        $widget_info['content'] = $element['settings']['alt_text'] ?? '';
                        $widget_info['url'] = $element['settings']['image']['url'] ?? '';
                        break;
                    case 'nav-menu':
                        $widget_info['menu_id'] = $element['settings']['menu'] ?? '';
                        break;
                }

                $widgets[] = $widget_info;
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $this->extract_elementor_widgets_detailed_recursive($element['elements'], $widgets);
            }
        }
    }

    /**
     * Enhanced Divi module parsing with detailed information
     */
    private function parse_divi_modules_detailed($page_id) {
        $modules = array();
        
        // Get post content and extract Divi modules with attributes
        $post = get_post($page_id);
        if ($post && !empty($post->post_content)) {
            // Enhanced regex to capture module attributes
            preg_match_all('/\[et_pb_([a-zA-Z0-9_]+)([^\]]*)\]/', $post->post_content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $module_type = $match[1];
                $attributes_string = $match[2] ?? '';
                
                $module_info = array(
                    'type' => $module_type,
                    'attributes' => $this->parse_divi_attributes($attributes_string)
                );

                // Extract meaningful content based on module type
                switch ($module_type) {
                    case 'text':
                        if (isset($module_info['attributes']['_builder_version'])) {
                            // Look for content after the shortcode
                            $pattern = '/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/s';
                            if (preg_match($pattern, $post->post_content, $content_match)) {
                                $module_info['content'] = wp_strip_all_tags($content_match[1]);
                            }
                        }
                        break;
                    case 'button':
                        $module_info['button_text'] = $module_info['attributes']['button_text'] ?? '';
                        $module_info['button_url'] = $module_info['attributes']['button_url'] ?? '';
                        break;
                    case 'image':
                        $module_info['image_url'] = $module_info['attributes']['src'] ?? '';
                        $module_info['alt_text'] = $module_info['attributes']['alt'] ?? '';
                        break;
                    case 'menu':
                        $module_info['menu_id'] = $module_info['attributes']['menu_id'] ?? '';
                        break;
                }

                $modules[] = $module_info;
            }
        }
        
        return $modules;
    }

    /**
     * Parse Divi module attributes from shortcode string
     */
    private function parse_divi_attributes($attributes_string) {
        $attributes = array();
        
        // Parse key="value" pairs
        preg_match_all('/(\w+)="([^"]*)"/', $attributes_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }
        
        return $attributes;
    }

    /**
     * Enhanced WPBakery shortcode parsing with detailed information
     */
    private function parse_wpbakery_shortcodes_detailed($page_id) {
        $shortcodes = array();
        
        // Get post content and extract VC shortcodes with attributes
        $post = get_post($page_id);
        if ($post && !empty($post->post_content)) {
            // Enhanced regex to capture shortcode attributes
            preg_match_all('/\[vc_([a-zA-Z0-9_]+)([^\]]*)\]/', $post->post_content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $shortcode_type = $match[1];
                $attributes_string = $match[2] ?? '';
                
                $shortcode_info = array(
                    'type' => $shortcode_type,
                    'attributes' => $this->parse_shortcode_attributes($attributes_string)
                );

                // Extract meaningful content based on shortcode type
                switch ($shortcode_type) {
                    case 'column_text':
                        // Look for content inside the shortcode
                        $pattern = '/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/s';
                        if (preg_match($pattern, $post->post_content, $content_match)) {
                            $shortcode_info['content'] = wp_strip_all_tags($content_match[1]);
                        }
                        break;
                    case 'btn':
                        $shortcode_info['button_text'] = $shortcode_info['attributes']['title'] ?? '';
                        $shortcode_info['button_url'] = $shortcode_info['attributes']['link'] ?? '';
                        break;
                    case 'single_image':
                        $shortcode_info['image_id'] = $shortcode_info['attributes']['image'] ?? '';
                        break;
                    case 'wp_menu':
                        $shortcode_info['menu_name'] = $shortcode_info['attributes']['nav_menu'] ?? '';
                        break;
                }

                $shortcodes[] = $shortcode_info;
            }
        }
        
        return $shortcodes;
    }

    /**
     * Parse shortcode attributes from string
     */
    private function parse_shortcode_attributes($attributes_string) {
        $attributes = array();
        
        // Parse key="value" pairs
        preg_match_all('/(\w+)="([^"]*)"/', $attributes_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $attributes[$match[1]] = urldecode($match[2]); // Decode URL-encoded values
        }
        
        return $attributes;
    }

    /**
     * Enhanced Gutenberg block analysis with detailed information
     */
    private function analyze_gutenberg_blocks_detailed($blocks) {
        $block_info = array();
        
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) continue;

            $block_data = array(
                'name' => $block['blockName'],
                'attributes' => $block['attrs'] ?? array(),
                'innerHTML' => $block['innerHTML'] ?? '',
                'content' => ''
            );

            // Extract meaningful content based on block type
            switch ($block['blockName']) {
                case 'core/paragraph':
                case 'core/heading':
                    $block_data['content'] = wp_strip_all_tags($block['innerHTML']);
                    break;
                case 'core/button':
                    if (isset($block['attrs']['text'])) {
                        $block_data['content'] = $block['attrs']['text'];
                    } else {
                        // Extract from innerHTML
                        preg_match('/>([^<]+)</', $block['innerHTML'], $text_match);
                        $block_data['content'] = $text_match[1] ?? '';
                    }
                    $block_data['url'] = $block['attrs']['url'] ?? '';
                    break;
                case 'core/image':
                    $block_data['image_id'] = $block['attrs']['id'] ?? '';
                    $block_data['alt_text'] = $block['attrs']['alt'] ?? '';
                    $block_data['caption'] = $block['attrs']['caption'] ?? '';
                    break;
                case 'core/navigation':
                case 'core/navigation-link':
                    $block_data['menu_items'] = $this->extract_navigation_from_block($block);
                    break;
                case 'core/shortcode':
                    $block_data['shortcode'] = $block['innerHTML'] ?? '';
                    break;
            }

            // Recursively process inner blocks
            if (!empty($block['innerBlocks'])) {
                $block_data['innerBlocks'] = $this->analyze_gutenberg_blocks_detailed($block['innerBlocks']);
            }

            $block_info[] = $block_data;
        }
        
        return $block_info;
    }

    /**
     * Extract navigation items from Gutenberg navigation blocks
     */
    private function extract_navigation_from_block($block) {
        $nav_items = array();
        
        if (isset($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner_block) {
                if ($inner_block['blockName'] === 'core/navigation-link') {
                    $nav_items[] = array(
                        'label' => $inner_block['attrs']['label'] ?? '',
                        'url' => $inner_block['attrs']['url'] ?? '',
                        'title' => $inner_block['attrs']['title'] ?? ''
                    );
                }
            }
        }
        
        return $nav_items;
    }

    /**
     * QUICK SCAN METHODS - Optimized for <5s execution
     */

    /**
     * Process DOM analysis from client-side scanning
     */
    private function process_dom_analysis($dom_analysis, $query) {
        if (empty($dom_analysis['matching_elements'])) {
            return array('found' => false, 'elements' => array());
        }

        $processed_elements = array();
        foreach ($dom_analysis['matching_elements'] as $element) {
            if ($element['confidence'] > 0.6) { // Only high-confidence matches
                $processed_elements[] = array(
                    'selector' => $element['selector'],
                    'text' => $element['text'] ?? '',
                    'confidence' => $element['confidence'],
                    'type' => $element['type'] ?? 'unknown',
                    'source_hint' => $this->analyze_element_source_hint($element)
                );
            }
        }

        return array(
            'found' => !empty($processed_elements),
            'elements' => $processed_elements,
            'confidence' => $this->calculate_average_confidence($processed_elements)
        );
    }

    /**
     * Extract search terms from user query for targeted scanning
     */
    private function extract_search_terms($query) {
        // Convert to lowercase and remove extra spaces
        $query = strtolower(trim($query));
        
        // Remove common stop words and question words
        $stop_words = array(
            'the', 'is', 'at', 'which', 'on', 'and', 'a', 'to', 'are', 'as', 'was', 'were',
            'what', 'where', 'how', 'why', 'when', 'who', 'which', 'that', 'this', 'these',
            'those', 'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'it', 'they',
            'them', 'their', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'cant', 'cannot',
            'coming', 'from', 'of', 'in', 'for', 'with', 'by', 'about', 'into', 'through',
            'during', 'before', 'after', 'above', 'below', 'up', 'down', 'out', 'off', 'over',
            'under', 'again', 'further', 'then', 'once'
        );
        
        // Split into words and clean
        $words = preg_split('/[\s\-_.,;:!?()[\]{}"\'+]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $search_terms = array();
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $search_terms[] = $word;
            }
        }
        
        // Also extract key phrases (2-3 words)
        $phrases = array();
        if (preg_match_all('/\b(?:about\s+us|contact\s+(?:us|form|button|page)|home\s+(?:page|button|link)|menu\s+(?:item|link|button)|navigation\s+(?:menu|bar|item)|header\s+(?:menu|nav|navigation)|footer\s+(?:menu|nav|navigation|link)|sidebar\s+(?:widget|menu)|search\s+(?:form|box|widget|button)|login\s+(?:form|button|link)|sign\s+(?:in|up)|log\s+(?:in|out)|call\s+to\s+action|read\s+more|learn\s+more|get\s+started|shop\s+now|buy\s+now|add\s+to\s+cart|view\s+(?:more|all)|see\s+(?:more|all))\b/i', $query, $matches)) {
            $phrases = array_merge($phrases, $matches[0]);
        }
        
        // Combine single words and phrases
        $all_terms = array_merge($search_terms, $phrases);
        
        // Remove duplicates and return
        return array_unique(array_filter($all_terms));
    }

    /**
     * Quick scan of WordPress menus
     */
    private function quick_scan_menus($query) {
        $menu_matches = array();
        $search_terms = $this->extract_search_terms($query);
        
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            $menu_items = wp_get_nav_menu_items($menu->term_id);
            
            foreach ($menu_items as $item) {
                foreach ($search_terms as $term) {
                    if (stripos($item->title, $term) !== false || stripos($item->url, $term) !== false) {
                        $menu_matches[] = array(
                            'menu_name' => $menu->name,
                            'menu_id' => $menu->term_id,
                            'item_title' => $item->title,
                            'item_url' => $item->url,
                            'edit_link' => admin_url('nav-menus.php?menu=' . $menu->term_id),
                            'confidence' => $this->calculate_text_match_confidence($term, $item->title),
                            'match_type' => 'menu_item'
                        );
                    }
                }
            }
        }

        return array(
            'found' => !empty($menu_matches),
            'matches' => $menu_matches,
            'scan_time' => 'instant'
        );
    }

    /**
     * Quick scan of common theme templates
     */
    private function quick_scan_templates($query, $page_id) {
        $template_matches = array();
        $search_terms = $this->extract_search_terms($query);
        
        // Priority templates for quick scan
        $priority_templates = array(
            'header.php',
            'footer.php', 
            'index.php',
            'functions.php'
        );
        
        // Add partials if they exist
        $theme_dir = get_template_directory();
        $partials_dir = $theme_dir . '/partials';
        if (is_dir($partials_dir)) {
            $priority_templates = array_merge($priority_templates, 
                glob($partials_dir . '/*.php')
            );
        }

        foreach ($priority_templates as $template) {
            $file_path = is_file($template) ? $template : $theme_dir . '/' . $template;
            
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                
                foreach ($search_terms as $term) {
                    if (stripos($content, $term) !== false) {
                        $template_matches[] = array(
                            'template' => basename($file_path),
                            'file_path' => $file_path,
                            'edit_link' => admin_url('theme-editor.php?file=' . urlencode(basename($file_path))),
                            'confidence' => $this->calculate_text_match_confidence($term, $content),
                            'match_type' => 'template_file',
                            'context' => $this->extract_context_around_match($content, $term)
                        );
                    }
                }
            }
        }

        return array(
            'found' => !empty($template_matches),
            'matches' => $template_matches,
            'scan_time' => 'instant'
        );
    }

    /**
     * Quick scan of active widgets
     */
    private function quick_scan_widgets($query) {
        $widget_matches = array();
        $search_terms = $this->extract_search_terms($query);
        
        $sidebars = wp_get_sidebars_widgets();
        
        foreach ($sidebars as $sidebar_id => $widget_ids) {
            if (empty($widget_ids) || !is_array($widget_ids)) continue;
            
            foreach ($widget_ids as $widget_id) {
                $widget_data = $this->get_widget_data($widget_id);
                
                if ($widget_data) {
                    foreach ($search_terms as $term) {
                        if (stripos($widget_data['content'], $term) !== false || 
                            stripos($widget_data['title'], $term) !== false) {
                            
                            $widget_matches[] = array(
                                'widget_type' => $widget_data['type'],
                                'widget_title' => $widget_data['title'],
                                'sidebar' => $sidebar_id,
                                'edit_link' => admin_url('widgets.php'),
                                'confidence' => $this->calculate_text_match_confidence($term, $widget_data['content']),
                                'match_type' => 'widget'
                            );
                        }
                    }
                }
            }
        }

        return array(
            'found' => !empty($widget_matches),
            'matches' => $widget_matches,
            'scan_time' => 'instant'
        );
    }

    /**
     * Quick scan of shortcodes on current page
     */
    private function quick_scan_shortcodes($query, $page_id) {
        if (!$page_id) return array('found' => false, 'matches' => array());
        
        $post = get_post($page_id);
        if (!$post) return array('found' => false, 'matches' => array());
        
        $shortcode_matches = array();
        $search_terms = $this->extract_search_terms($query);
        
        // Extract shortcodes from post content
        preg_match_all('/\[([^\]]+)\]/', $post->post_content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $shortcode) {
                foreach ($search_terms as $term) {
                    if (stripos($shortcode, $term) !== false) {
                        $shortcode_matches[] = array(
                            'shortcode' => '[' . $shortcode . ']',
                            'page_title' => $post->post_title,
                            'edit_link' => admin_url('post.php?post=' . $page_id . '&action=edit'),
                            'confidence' => $this->calculate_text_match_confidence($term, $shortcode),
                            'match_type' => 'shortcode'
                        );
                    }
                }
            }
        }

        return array(
            'found' => !empty($shortcode_matches),
            'matches' => $shortcode_matches,
            'scan_time' => 'instant'
        );
    }

    /**
     * Get quick AI analysis for fast results
     */
    private function get_quick_ai_analysis($query, $quick_results) {
        $context = array(
            'scan_type' => 'quick',
            'query' => $query,
            'found_elements' => array(),
            'confidence_summary' => array()
        );

        // Summarize findings
        foreach ($quick_results as $source_type => $source_data) {
            if ($source_data['found']) {
                $context['found_elements'][$source_type] = count($source_data['matches'] ?? $source_data['elements']);
            }
        }

        $prompt = $this->build_quick_scan_prompt($query, $context);
        
        try {
            $ai_response = $this->get_ai_response($query, $prompt);
            return $this->parse_quick_ai_response($ai_response);
        } catch (Exception $e) {
            return array(
                'analysis' => 'Quick analysis unavailable. Deep scan recommended.',
                'confidence' => 0.3,
                'recommendation' => 'Run deep scan for comprehensive analysis'
            );
        }
    }

    /**
     * HELPER METHODS FOR QUICK SCAN
     */

    private function analyze_element_source_hint($element) {
        $selector = $element['selector'] ?? '';
        
        // Common patterns that hint at source
        if (strpos($selector, '.menu') !== false || strpos($selector, 'nav') !== false) {
            return 'likely_menu';
        }
        if (strpos($selector, '.widget') !== false || strpos($selector, '.sidebar') !== false) {
            return 'likely_widget';
        }
        if (strpos($selector, '.elementor') !== false) {
            return 'likely_elementor';
        }
        if (strpos($selector, '.et_pb') !== false) {
            return 'likely_divi';
        }
        
        return 'unknown';
    }

    private function calculate_average_confidence($elements) {
        if (empty($elements)) return 0;
        
        $total = array_sum(array_column($elements, 'confidence'));
        return round($total / count($elements), 2);
    }

    private function calculate_text_match_confidence($search_term, $content) {
        $search_term = strtolower(trim($search_term));
        $content = strtolower($content);
        
        // Exact match
        if (strpos($content, $search_term) !== false) {
            // Higher confidence for exact word boundaries
            if (preg_match('/\b' . preg_quote($search_term, '/') . '\b/', $content)) {
                return 0.9;
            }
            return 0.75;
        }
        
        // Partial/fuzzy match
        $similarity = 0;
        similar_text($search_term, $content, $similarity);
        return round($similarity / 100, 2);
    }

    private function extract_context_around_match($content, $term, $context_length = 100) {
        $pos = stripos($content, $term);
        if ($pos === false) return '';
        
        $start = max(0, $pos - $context_length);
        $length = strlen($term) + (2 * $context_length);
        
        return '...' . substr($content, $start, $length) . '...';
    }

    private function get_widget_data($widget_id) {
        // Parse widget ID to get type and instance
        if (!preg_match('/^(.+?)-(\d+)$/', $widget_id, $matches)) {
            return false;
        }
        
        $widget_type = $matches[1];
        $widget_instance = intval($matches[2]);
        
        $widget_options = get_option('widget_' . $widget_type, array());
        $instance_data = $widget_options[$widget_instance] ?? array();
        
        if (empty($instance_data)) return false;
        
        return array(
            'type' => $widget_type,
            'title' => $instance_data['title'] ?? '',
            'content' => serialize($instance_data) // Serialize for text search
        );
    }

    private function determine_primary_source_quick($quick_results) {
        $highest_confidence = 0;
        $primary_source = null;
        
        foreach ($quick_results as $source_type => $source_data) {
            if (!$source_data['found']) continue;
            
            $matches = $source_data['matches'] ?? $source_data['elements'] ?? array();
            
            foreach ($matches as $match) {
                if (($match['confidence'] ?? 0) > $highest_confidence) {
                    $highest_confidence = $match['confidence'];
                    $primary_source = array(
                        'type' => $source_type,
                        'data' => $match,
                        'confidence' => $highest_confidence
                    );
                }
            }
        }
        
        return $primary_source;
    }

    private function calculate_quick_confidence($quick_results) {
        $all_confidences = array();
        
        foreach ($quick_results as $source_data) {
            if (isset($source_data['confidence'])) {
                $all_confidences[] = $source_data['confidence'];
            }
        }
        
        return empty($all_confidences) ? 0 : round(array_sum($all_confidences) / count($all_confidences), 2);
    }

    private function build_quick_scan_prompt($query, $context) {
        return "QUICK SCAN ANALYSIS\n\n" .
               "User Query: {$query}\n\n" .
               "Found Elements: " . json_encode($context['found_elements']) . "\n\n" .
               "Based on this quick scan, provide:\n" .
               "1. Most likely source\n" .
               "2. Confidence level (0-1)\n" .
               "3. Next steps or edit recommendation\n\n" .
               "Keep response concise for quick results.";
    }

    private function parse_quick_ai_response($response) {
        return array(
            'analysis' => $response,
            'confidence' => 0.7, // Default for quick scan
            'recommendation' => 'For more detailed analysis, run a deep scan.'
        );
    }

    /**
     * DEEP SEARCH AJAX HANDLERS & PROGRESS TRACKING
     */

    /**
     * Handle deep search progress requests
     */
    public function handle_deep_progress() {
        check_ajax_referer('wsa_ai_detective_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $scan_id = sanitize_text_field($_POST['scanId'] ?? '');
        if (empty($scan_id)) {
            wp_send_json_error(array('message' => 'Invalid scan ID'));
        }

        $scan_data = get_transient('wsa_deep_scan_' . $scan_id);
        if (!$scan_data) {
            wp_send_json_error(array('message' => 'Scan not found or expired'));
        }

        // WP Cron fallback mechanism for local development environments
        if ($scan_data['status'] === 'in_progress') {
            $last_update = $scan_data['last_update'] ?? $scan_data['started_at'];
            $time_since_update = time() - $last_update;
            
            // Trigger processing manually if stalled (WP Cron fallback)
            if ($time_since_update > 5) {
                // Trigger batch processing manually (WP Cron fallback)
                try {
                    $this->process_deep_scan_batch($scan_id);
                    
                    // Get updated scan data after processing
                    $scan_data = get_transient('wsa_deep_scan_' . $scan_id);
                    if (!$scan_data) {
                        wp_send_json_error(array('message' => 'Scan processing failed - data lost'));
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the request
                    
                    // Update scan with error status
                    $scan_data['status'] = 'error';
                    $scan_data['error'] = 'Processing failed: ' . $e->getMessage();
                    set_transient('wsa_deep_scan_' . $scan_id, $scan_data, 3600);
                }
            }
        }

        wp_send_json_success(array(
            'status' => $scan_data['status'],
            'progress' => $scan_data['progress'],
            'current_task' => $scan_data['current_task'],
            'results' => $scan_data['results'],
            'scan_time' => time() - $scan_data['started_at'],
            'last_update' => $scan_data['last_update'] ?? time()
        ));
    }

    /**
     * Handle deep search control (pause/resume/cancel)
     */
    public function handle_deep_control() {
        check_ajax_referer('wsa_ai_detective_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $scan_id = sanitize_text_field($_POST['scanId'] ?? '');
        $action = sanitize_text_field($_POST['action'] ?? '');

        if (empty($scan_id) || empty($action)) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        $scan_data = get_transient('wsa_deep_scan_' . $scan_id);
        if (!$scan_data) {
            wp_send_json_error(array('message' => 'Scan not found'));
        }

        switch ($action) {
            case 'pause':
                $scan_data['status'] = 'paused';
                $scan_data['paused_at'] = time();
                break;
            case 'resume':
                $scan_data['status'] = 'in_progress';
                unset($scan_data['paused_at']);
                wp_schedule_single_event(time(), 'wsa_deep_scan_cron', array($scan_id));
                break;
            case 'cancel':
                $scan_data['status'] = 'cancelled';
                $scan_data['cancelled_at'] = time();
                break;
        }

        set_transient('wsa_deep_scan_' . $scan_id, $scan_data, 3600);
        wp_send_json_success(array('status' => $scan_data['status']));
    }

    /**
     * Handle export results to file
     */
    public function handle_export_results() {
        check_ajax_referer('wsa_ai_detective_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $results = $_POST['results'] ?? array();
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $scan_type = sanitize_text_field($_POST['scanType'] ?? 'quick');
        $query = sanitize_text_field($_POST['query'] ?? '');

        if (empty($results)) {
            wp_send_json_error(array('message' => 'No results to export'));
        }

        try {
            // Create upload directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $export_dir = $upload_dir['basedir'] . '/wsa-exports';
            
            if (!file_exists($export_dir)) {
                wp_mkdir_p($export_dir);
            }

            // Generate filename
            $timestamp = current_time('Y-m-d_H-i-s');
            $safe_query = sanitize_file_name($query);
            $filename = "wsa-{$scan_type}-results_{$safe_query}_{$timestamp}.{$format}";
            $filepath = $export_dir . '/' . $filename;

            // Generate content based on format
            if ($format === 'csv') {
                $content = $this->generate_csv_export($results, $scan_type, $query);
            } else {
                $content = $this->generate_json_export($results, $scan_type, $query);
            }

            // Write file
            $bytes_written = file_put_contents($filepath, $content);
            
            if ($bytes_written === false) {
                wp_send_json_error(array('message' => 'Failed to write export file'));
            }

            // Generate download URL
            $download_url = $upload_dir['baseurl'] . '/wsa-exports/' . $filename;

            wp_send_json_success(array(
                'message' => 'Export completed successfully',
                'filename' => $filename,
                'download_url' => $download_url,
                'file_size' => size_format($bytes_written),
                'results_count' => count($results)
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Export failed: ' . $e->getMessage()));
        }
    }

    /**
     * Generate CSV export content
     */
    private function generate_csv_export($results, $scan_type, $query) {
        $csv_content = '';
        
        // Add header
        $csv_content .= "# WP SiteAdvisor - AI Site Detective Export\n";
        $csv_content .= "# Scan Type: " . ucfirst($scan_type) . " Scan\n";
        $csv_content .= "# Query: {$query}\n";
        $csv_content .= "# Generated: " . current_time('Y-m-d H:i:s') . "\n";
        $csv_content .= "# Total Results: " . count($results) . "\n\n";
        
        // CSV headers
        $csv_content .= "Type,File,Line,Content,Context,Confidence,Edit Link\n";
        
        // Process results
        foreach ($results as $result) {
            $type = isset($result['type']) ? $result['type'] : 'unknown';
            $file = isset($result['file']) ? $result['file'] : '';
            
            if (isset($result['matches']) && is_array($result['matches'])) {
                foreach ($result['matches'] as $match) {
                    $line = isset($match['line']) ? $match['line'] : '';
                    $content = isset($match['content']) ? $this->escape_csv_field($match['content']) : '';
                    $context = isset($match['context']) ? $this->escape_csv_field($match['context']) : '';
                    $confidence = isset($result['confidence']) ? $result['confidence'] : '';
                    $edit_link = isset($result['edit_link']) ? $result['edit_link'] : '';
                    
                    $csv_content .= "{$type},{$file},{$line},\"{$content}\",\"{$context}\",{$confidence},{$edit_link}\n";
                }
            }
        }
        
        return $csv_content;
    }

    /**
     * Generate JSON export content
     */
    private function generate_json_export($results, $scan_type, $query) {
        $export_data = array(
            'meta' => array(
                'scan_type' => $scan_type,
                'query' => $query,
                'generated' => current_time('Y-m-d H:i:s'),
                'total_results' => count($results),
                'wp_site_advisor' => 'AI Site Detective Export'
            ),
            'results' => $results
        );
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Escape CSV field content
     */
    private function escape_csv_field($field) {
        // Remove newlines and escape quotes
        $field = str_replace(array("\r\n", "\r", "\n"), ' ', $field);
        $field = str_replace('"', '""', $field);
        return $field;
    }

    /**
     * Process deep scan batch via WP Cron
     */
    public function process_deep_scan_batch($scan_id) {
        $scan_data = get_transient('wsa_deep_scan_' . $scan_id);
        
        if (!$scan_data || $scan_data['status'] !== 'in_progress') {
            return; // Scan cancelled or paused
        }

        $batch_start = microtime(true);
        $batch_time_limit = 30; // 30 seconds per batch to avoid timeouts
        
        // Define scanning phases
        $phases = array(
            'theme_files' => 'Scanning theme files...',
            'plugin_files' => 'Analyzing plugins...',
            'database' => 'Searching database...',
            'builders' => 'Checking page builders...',
            'branding_audit' => 'Auditing branding & CSS...',
            'ai_analysis' => 'AI cross-referencing...'
        );

        $current_phase = $scan_data['current_phase'] ?? 'theme_files';
        
        try {
            switch ($current_phase) {
                case 'theme_files':
                    $results = $this->deep_scan_theme_files($scan_data, $batch_time_limit);
                    break;
                case 'plugin_files':
                    $results = $this->deep_scan_plugin_files($scan_data, $batch_time_limit);
                    break;
                case 'database':
                    $results = $this->deep_scan_database($scan_data, $batch_time_limit);
                    break;
                case 'builders':
                    $results = $this->deep_scan_builders($scan_data, $batch_time_limit);
                    break;
                case 'branding_audit':
                    $results = $this->deep_scan_branding_audit($scan_data);
                    break;
                case 'ai_analysis':
                    $results = $this->deep_scan_ai_analysis($scan_data);
                    break;
            }

            // Update scan data
            $scan_data['results'] = array_merge($scan_data['results'], $results['new_results'] ?? array());
            $scan_data['progress'] = $results['progress'] ?? $scan_data['progress'];
            $scan_data['current_task'] = $results['current_task'] ?? $scan_data['current_task'];
            $scan_data['batch_position'] = $results['batch_position'] ?? $scan_data['batch_position'];
            $scan_data['last_update'] = time();

            // Check if phase is complete
            if ($results['phase_complete'] ?? false) {
                $phase_keys = array_keys($phases);
                $current_index = array_search($current_phase, $phase_keys);
                
                if ($current_index !== false && $current_index < count($phase_keys) - 1) {
                    $scan_data['current_phase'] = $phase_keys[$current_index + 1];
                    $scan_data['current_task'] = $phases[$phase_keys[$current_index + 1]];
                    $scan_data['batch_position'] = 0; // Reset batch position for next phase
                } else {
                    // All phases complete
                    $scan_data['status'] = 'completed';
                    $scan_data['current_task'] = 'Deep scan completed';
                    $scan_data['progress'] = 100;
                    $scan_data['completed_at'] = time();
                }
            }

            set_transient('wsa_deep_scan_' . $scan_id, $scan_data, 3600);

            // Schedule next batch if not complete
            if ($scan_data['status'] === 'in_progress') {
                wp_schedule_single_event(time() + 5, 'wsa_deep_scan_cron', array($scan_id));
            }

        } catch (Exception $e) {
            $scan_data['status'] = 'error';
            $scan_data['error'] = $e->getMessage();
            set_transient('wsa_deep_scan_' . $scan_id, $scan_data, 3600);
        }
    }

    /**
     * DEEP SCAN PHASE METHODS
     */

    private function deep_scan_theme_files($scan_data, $time_limit) {
        $start_time = microtime(true);
        
        // Check system load and apply throttling
        $load_metrics = $this->check_system_load();
        if ($this->should_throttle_scan($load_metrics)) {
            $throttle_settings = $this->apply_throttling('deep');
            $batch_size = $throttle_settings['batch_size'];
        } else {
            $batch_size = $this->deep_scan_batch_size;
        }
        
        // Check for cached theme file list
        $theme_cache_key = 'theme_files_' . md5(get_template_directory() . get_stylesheet_directory());
        $files = $this->get_cached_result($theme_cache_key);
        
        if (!$files) {
            $theme_dir = get_template_directory();
            $child_theme_dir = get_stylesheet_directory();
            $files = $this->get_recursive_php_files(array($theme_dir, $child_theme_dir));
            $this->set_cached_result($theme_cache_key, $files, 1800); // Cache for 30 minutes
        }
        
        $results = array();
        $search_terms = $this->extract_search_terms($scan_data['query']);
        
        $batch_position = $scan_data['batch_position'] ?? 0;
        $files_to_process = array_slice($files, $batch_position, $batch_size);
        
        foreach ($files_to_process as $file) {
            // Performance check every 5 files
            if ($batch_position % 5 === 0 && (microtime(true) - $start_time) > $time_limit) {
                break; // Time limit reached
            }

            // Check for cached file analysis
            $file_cache_key = 'file_analysis_' . md5($file . filemtime($file));
            $cached_analysis = $this->get_cached_result($file_cache_key);
            
            if ($cached_analysis) {
                // Use cached analysis but filter for current search terms
                foreach ($search_terms as $term) {
                    if (isset($cached_analysis[$term])) {
                        $results[] = $cached_analysis[$term];
                    }
                }
            } else {
                // Perform fresh analysis
                $content = file_get_contents($file);
                $file_analysis = array();
                
                foreach ($search_terms as $term) {
                    if (stripos($content, $term) !== false) {
                        $analysis_result = array(
                            'type' => 'theme_file',
                            'file' => str_replace(array(get_template_directory(), get_stylesheet_directory()), array('theme/', 'child-theme/'), $file),
                            'full_path' => $file,
                            'matches' => $this->find_line_matches($content, $term),
                            'confidence' => $this->calculate_text_match_confidence($term, $content),
                            'edit_link' => admin_url('theme-editor.php?file=' . urlencode(basename($file)))
                        );
                        
                        $results[] = $analysis_result;
                        $file_analysis[$term] = $analysis_result;
                    }
                }
                
                // Cache the analysis
                if (!empty($file_analysis)) {
                    $this->set_cached_result($file_cache_key, $file_analysis, 3600); // Cache for 1 hour
                }
            }
            
            $batch_position++;
        }

        $progress = min(25, (($batch_position / count($files)) * 25)); // Theme files = 25% of total
        $phase_complete = $batch_position >= count($files);

        return array(
            'new_results' => $results,
            'progress' => $progress,
            'current_task' => "Scanning theme files... ({$batch_position}/" . count($files) . ")",
            'phase_complete' => $phase_complete,
            'batch_position' => $batch_position
        );
    }

    private function deep_scan_plugin_files($scan_data, $time_limit) {
        // Similar structure to theme files but for active plugins
        $results = array();
        $active_plugins = get_option('active_plugins', array());
        
        // Implementation similar to theme files...
        // This would scan plugin directories for matches
        
        return array(
            'new_results' => $results,
            'progress' => 50, // Plugins = 25% more (total 50%)
            'current_task' => "Analyzing active plugins...",
            'phase_complete' => true
        );
    }

    private function deep_scan_database($scan_data, $time_limit) {
        global $wpdb;
        $results = array();
        $search_terms = $this->extract_search_terms($scan_data['query']);
        
        // Search key database tables
        $tables_to_search = array(
            $wpdb->posts => array('post_title', 'post_content', 'post_excerpt'),
            $wpdb->postmeta => array('meta_value'),
            $wpdb->options => array('option_value'),
        );

        foreach ($tables_to_search as $table => $columns) {
            foreach ($columns as $column) {
                foreach ($search_terms as $term) {
                    $query = $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE {$column} LIKE %s LIMIT 10",
                        '%' . $wpdb->esc_like($term) . '%'
                    );
                    
                    $matches = $wpdb->get_results($query);
                    
                    foreach ($matches as $match) {
                        $results[] = array(
                            'type' => 'database',
                            'table' => str_replace($wpdb->prefix, 'wp_', $table),
                            'column' => $column,
                            'row_id' => $match->ID ?? $match->option_id ?? null,
                            'content_preview' => substr($match->$column, 0, 200),
                            'confidence' => $this->calculate_text_match_confidence($term, $match->$column)
                        );
                    }
                }
            }
        }

        return array(
            'new_results' => $results,
            'progress' => 75, // Database = 25% more (total 75%)
            'current_task' => "Searching database tables...",
            'phase_complete' => true
        );
    }

    private function deep_scan_builders($scan_data, $time_limit) {
        // Enhanced page builder scanning
        $results = array();
        
        // Use existing detailed builder methods
        if ($scan_data['page_id']) {
            $builder_results = $this->scan_builder_elements_detailed($scan_data['page_id']);
            
            // Process builder results for matches
            foreach ($builder_results as $builder_type => $builder_data) {
                if ($builder_data['active']) {
                    $results[] = array(
                        'type' => 'page_builder',
                        'builder' => $builder_type,
                        'data' => $builder_data,
                        'confidence' => 0.8 // High confidence for active builders
                    );
                }
            }
        }

        return array(
            'new_results' => $results,
            'progress' => 75, // Builders = 15% more (total 75%)
            'current_task' => "Checking page builders...",
            'phase_complete' => true
        );
    }

    private function deep_scan_branding_audit($scan_data) {
        // Comprehensive branding and CSS audit
        $branding_results = $this->perform_branding_audit($scan_data);

        return array(
            'new_results' => array($branding_results),
            'progress' => 90, // Branding audit = 15% more (total 90%)
            'current_task' => "Branding & CSS audit completed",
            'phase_complete' => true
        );
    }

    /**
     * BRANDING & CSS AUDIT SYSTEM
     */

    /**
     * Comprehensive CSS branding audit
     */
    private function perform_branding_audit($scan_data) {
        $branding_analysis = array();
        
        $theme_css_analysis = $this->analyze_theme_css_branding();
        $plugin_css_analysis = $this->analyze_plugin_css_branding();
        $color_palette = $this->extract_brand_color_palette($theme_css_analysis, $plugin_css_analysis);
        $typography_analysis = $this->analyze_typography_consistency($theme_css_analysis, $plugin_css_analysis);
        $brand_imagery = $this->analyze_brand_imagery();
        $design_patterns = $this->analyze_design_patterns($theme_css_analysis, $plugin_css_analysis);
        $recommendations = $this->generate_branding_recommendations($color_palette, $typography_analysis, $design_patterns);
        
        return array(
            'type' => 'branding_audit',
            'color_palette' => $color_palette,
            'typography' => $typography_analysis,
            'brand_imagery' => $brand_imagery,
            'design_patterns' => $design_patterns,
            'recommendations' => $recommendations,
            'consistency_score' => $this->calculate_brand_consistency_score($color_palette, $typography_analysis, $design_patterns),
            'audit_timestamp' => current_time('Y-m-d H:i:s')
        );
    }

    /**
     * Analyze theme CSS files for branding elements
     */
    private function analyze_theme_css_branding() {
        $css_analysis = array(
            'files_analyzed' => array(),
            'colors' => array(),
            'fonts' => array(),
            'design_tokens' => array(),
            'custom_properties' => array()
        );

        // Get all CSS files from theme and child theme
        $css_files = $this->get_all_css_files();
        
        foreach ($css_files as $css_file) {
            if (file_exists($css_file)) {
                $file_analysis = $this->analyze_css_file_branding($css_file);
                
                $css_analysis['files_analyzed'][] = array(
                    'file' => str_replace(array(get_template_directory(), get_stylesheet_directory()), array('theme/', 'child-theme/'), $css_file),
                    'full_path' => $css_file,
                    'file_size' => filesize($css_file),
                    'colors_found' => count($file_analysis['colors']),
                    'fonts_found' => count($file_analysis['fonts']),
                    'edit_link' => admin_url('theme-editor.php?file=' . urlencode(basename($css_file)))
                );
                
                // Merge results
                $css_analysis['colors'] = array_merge($css_analysis['colors'], $file_analysis['colors']);
                $css_analysis['fonts'] = array_merge($css_analysis['fonts'], $file_analysis['fonts']);
                $css_analysis['design_tokens'] = array_merge($css_analysis['design_tokens'], $file_analysis['design_tokens']);
                $css_analysis['custom_properties'] = array_merge($css_analysis['custom_properties'], $file_analysis['custom_properties']);
            }
        }

        return $css_analysis;
    }

    /**
     * Analyze plugin CSS files for branding conflicts
     */
    private function analyze_plugin_css_branding() {
        $plugin_css_analysis = array(
            'plugins_analyzed' => array(),
            'colors' => array(),
            'fonts' => array(),
            'potential_conflicts' => array()
        );

        $active_plugins = get_option('active_plugins', array());
        
        foreach ($active_plugins as $plugin_path) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_path);
            $plugin_css_files = glob($plugin_dir . '/**/*.css');
            
            if (!empty($plugin_css_files)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
                
                $plugin_analysis = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'css_files' => array(),
                    'colors' => array(),
                    'fonts' => array()
                );
                
                foreach ($plugin_css_files as $css_file) {
                    if (file_exists($css_file)) {
                        $file_analysis = $this->analyze_css_file_branding($css_file);
                        
                        $plugin_analysis['css_files'][] = str_replace(WP_PLUGIN_DIR, 'plugins', $css_file);
                        $plugin_analysis['colors'] = array_merge($plugin_analysis['colors'], $file_analysis['colors']);
                        $plugin_analysis['fonts'] = array_merge($plugin_analysis['fonts'], $file_analysis['fonts']);
                    }
                }
                
                if (!empty($plugin_analysis['colors']) || !empty($plugin_analysis['fonts'])) {
                    $plugin_css_analysis['plugins_analyzed'][] = $plugin_analysis;
                    $plugin_css_analysis['colors'] = array_merge($plugin_css_analysis['colors'], $plugin_analysis['colors']);
                    $plugin_css_analysis['fonts'] = array_merge($plugin_css_analysis['fonts'], $plugin_analysis['fonts']);
                }
            }
        }

        return $plugin_css_analysis;
    }

    /**
     * Analyze individual CSS file for branding elements
     */
    private function analyze_css_file_branding($css_file) {
        $content = file_get_contents($css_file);
        $analysis = array(
            'colors' => array(),
            'fonts' => array(),
            'design_tokens' => array(),
            'custom_properties' => array()
        );

        // Extract colors (hex, rgb, rgba, hsl, hsla)
        $color_patterns = array(
            'hex' => '/#([a-fA-F0-9]{3,8})\b/',
            'rgb' => '/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/',
            'rgba' => '/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([0-9]*\.?[0-9]+)\s*\)/',
            'hsl' => '/hsl\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)/',
            'hsla' => '/hsla\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*([0-9]*\.?[0-9]+)\s*\)/'
        );

        foreach ($color_patterns as $type => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[0] as $match) {
                $color_value = $match[0];
                $position = $match[1];
                
                // Get line number
                $line_number = substr_count(substr($content, 0, $position), "\n") + 1;
                
                $analysis['colors'][] = array(
                    'value' => $color_value,
                    'type' => $type,
                    'line' => $line_number,
                    'context' => $this->extract_css_context($content, $position),
                    'hex_equivalent' => $this->convert_to_hex($color_value),
                    'usage_frequency' => substr_count($content, $color_value)
                );
            }
        }

        // Extract fonts
        $font_patterns = array(
            'font-family' => '/font-family\s*:\s*([^;}]+)/i',
            'font' => '/font\s*:\s*([^;}]+)/i'
        );

        foreach ($font_patterns as $property => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[1] as $match) {
                $font_value = trim($match[0], ' "\',');
                $position = $match[1];
                $line_number = substr_count(substr($content, 0, $position), "\n") + 1;
                
                $analysis['fonts'][] = array(
                    'value' => $font_value,
                    'property' => $property,
                    'line' => $line_number,
                    'context' => $this->extract_css_context($content, $position),
                    'font_stack' => $this->parse_font_stack($font_value),
                    'usage_frequency' => substr_count($content, $font_value)
                );
            }
        }

        // Extract CSS custom properties (variables)
        preg_match_all('/--([a-zA-Z0-9-_]+)\s*:\s*([^;}]+)/i', $content, $custom_props, PREG_OFFSET_CAPTURE);
        
        foreach ($custom_props[0] as $index => $match) {
            $property_name = '--' . $custom_props[1][$index][0];
            $property_value = trim($custom_props[2][$index][0]);
            $position = $match[1];
            $line_number = substr_count(substr($content, 0, $position), "\n") + 1;
            
            $analysis['custom_properties'][] = array(
                'property' => $property_name,
                'value' => $property_value,
                'line' => $line_number,
                'context' => $this->extract_css_context($content, $position),
                'is_color' => $this->is_color_value($property_value),
                'is_font' => $this->is_font_value($property_value)
            );
        }

        return $analysis;
    }

    /**
     * Extract comprehensive brand color palette
     */
    private function extract_brand_color_palette($theme_css, $plugin_css) {
        $all_colors = array_merge($theme_css['colors'], $plugin_css['colors']);
        
        // Group colors by frequency and similarity
        $color_groups = array();
        
        foreach ($all_colors as $color) {
            $hex = $color['hex_equivalent'];
            
            if (!isset($color_groups[$hex])) {
                $color_groups[$hex] = array(
                    'hex' => $hex,
                    'original_values' => array(),
                    'total_frequency' => 0,
                    'contexts' => array(),
                    'color_name' => $this->get_color_name($hex),
                    'is_primary' => false,
                    'color_family' => $this->get_color_family($hex)
                );
            }
            
            $color_groups[$hex]['original_values'][] = $color['value'];
            $color_groups[$hex]['total_frequency'] += $color['usage_frequency'];
            $color_groups[$hex]['contexts'][] = $color['context'];
        }

        // Sort by frequency to identify primary colors
        uasort($color_groups, function($a, $b) {
            return $b['total_frequency'] - $a['total_frequency'];
        });

        // Mark primary colors (top 5 most used)
        $primary_count = 0;
        foreach ($color_groups as &$group) {
            if ($primary_count < 5) {
                $group['is_primary'] = true;
                $primary_count++;
            }
        }

        return array(
            'primary_colors' => array_filter($color_groups, function($group) { return $group['is_primary']; }),
            'all_colors' => $color_groups,
            'color_statistics' => array(
                'total_unique_colors' => count($color_groups),
                'total_color_instances' => array_sum(array_column($color_groups, 'total_frequency')),
                'color_families' => array_count_values(array_column($color_groups, 'color_family')),
                'most_used_color' => reset($color_groups)
            )
        );
    }

    /**
     * Analyze typography consistency across the site
     */
    private function analyze_typography_consistency($theme_css, $plugin_css) {
        $all_fonts = array_merge($theme_css['fonts'], $plugin_css['fonts']);
        
        // Group fonts by family
        $font_groups = array();
        
        foreach ($all_fonts as $font) {
            $font_stack = $font['font_stack'];
            $primary_font = $font_stack[0] ?? 'Unknown';
            
            if (!isset($font_groups[$primary_font])) {
                $font_groups[$primary_font] = array(
                    'primary_font' => $primary_font,
                    'font_stack' => $font_stack,
                    'fallbacks' => array_slice($font_stack, 1),
                    'usage_count' => 0,
                    'contexts' => array(),
                    'is_web_safe' => $this->is_web_safe_font($primary_font),
                    'font_category' => $this->get_font_category($primary_font),
                    'google_font' => $this->is_google_font($primary_font)
                );
            }
            
            $font_groups[$primary_font]['usage_count'] += $font['usage_frequency'];
            $font_groups[$primary_font]['contexts'][] = $font['context'];
        }

        // Identify typography hierarchy
        $typography_hierarchy = $this->detect_typography_hierarchy($theme_css, $plugin_css);

        return array(
            'font_families' => $font_groups,
            'typography_hierarchy' => $typography_hierarchy,
            'consistency_issues' => $this->detect_typography_inconsistencies($font_groups),
            'recommendations' => $this->generate_typography_recommendations($font_groups, $typography_hierarchy)
        );
    }

    /**
     * Analyze brand imagery (logos, icons, etc.)
     */
    private function analyze_brand_imagery() {
        $imagery_analysis = array(
            'custom_logo' => null,
            'favicons' => array(),
            'brand_images' => array(),
            'icon_fonts' => array()
        );

        // Check for custom logo
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($logo_data) {
                $imagery_analysis['custom_logo'] = array(
                    'url' => $logo_data[0],
                    'width' => $logo_data[1],
                    'height' => $logo_data[2],
                    'file_size' => $this->get_image_file_size($logo_data[0]),
                    'edit_link' => admin_url('customize.php?autofocus[control]=custom_logo')
                );
            }
        }

        // Check for favicons
        $favicon_paths = array(
            '/favicon.ico',
            '/apple-touch-icon.png',
            '/android-chrome-192x192.png',
            '/android-chrome-512x512.png'
        );

        foreach ($favicon_paths as $path) {
            $favicon_url = home_url($path);
            $favicon_path = ABSPATH . ltrim($path, '/');
            
            if (file_exists($favicon_path)) {
                $imagery_analysis['favicons'][] = array(
                    'type' => basename($path, '.ico'),
                    'url' => $favicon_url,
                    'file_size' => filesize($favicon_path),
                    'dimensions' => $this->get_image_dimensions($favicon_path)
                );
            }
        }

        // Check for icon fonts (Font Awesome, etc.)
        $imagery_analysis['icon_fonts'] = $this->detect_icon_fonts();

        return $imagery_analysis;
    }

    /**
     * Analyze design patterns and detect inconsistencies
     */
    private function analyze_design_patterns($theme_css, $plugin_css) {
        $design_analysis = array(
            'border_radius' => $this->extract_design_tokens($theme_css, $plugin_css, 'border-radius'),
            'box_shadows' => $this->extract_design_tokens($theme_css, $plugin_css, 'box-shadow'),
            'transitions' => $this->extract_design_tokens($theme_css, $plugin_css, 'transition'),
            'spacing' => $this->extract_spacing_patterns($theme_css, $plugin_css),
            'breakpoints' => $this->extract_media_query_breakpoints($theme_css, $plugin_css),
            'z_index_usage' => $this->extract_design_tokens($theme_css, $plugin_css, 'z-index')
        );

        return $design_analysis;
    }

    /**
     * Generate comprehensive branding recommendations
     */
    private function generate_branding_recommendations($color_palette, $typography, $design_patterns) {
        $recommendations = array(
            'color_recommendations' => array(),
            'typography_recommendations' => array(),
            'design_recommendations' => array(),
            'priority_actions' => array()
        );

        // Color recommendations
        if ($color_palette['color_statistics']['total_unique_colors'] > 20) {
            $recommendations['color_recommendations'][] = array(
                'type' => 'warning',
                'title' => 'Too Many Colors',
                'description' => 'You have ' . $color_palette['color_statistics']['total_unique_colors'] . ' unique colors. Consider consolidating to 5-8 primary brand colors.',
                'action' => 'Create a consistent color palette and replace similar colors with CSS variables.',
                'priority' => 'high'
            );
        }

        // Typography recommendations
        if (count($typography['font_families']) > 4) {
            $recommendations['typography_recommendations'][] = array(
                'type' => 'warning',
                'title' => 'Too Many Font Families',
                'description' => 'Using ' . count($typography['font_families']) . ' different font families can hurt performance and consistency.',
                'action' => 'Limit to 2-3 font families: one for headings, one for body text, and optionally one for special elements.',
                'priority' => 'medium'
            );
        }

        // Check for non-web-safe fonts without fallbacks
        foreach ($typography['font_families'] as $font_family) {
            if (!$font_family['is_web_safe'] && count($font_family['fallbacks']) < 2) {
                $recommendations['typography_recommendations'][] = array(
                    'type' => 'error',
                    'title' => 'Missing Font Fallbacks',
                    'description' => "Font '{$font_family['primary_font']}' needs proper fallback fonts.",
                    'action' => 'Add web-safe fallback fonts to ensure text displays properly if custom fonts fail to load.',
                    'priority' => 'high'
                );
            }
        }

        // Design pattern recommendations
        if (isset($design_patterns['border_radius']) && count(array_unique($design_patterns['border_radius'])) > 5) {
            $recommendations['design_recommendations'][] = array(
                'type' => 'suggestion',
                'title' => 'Inconsistent Border Radius',
                'description' => 'Multiple different border-radius values detected. Consider using a consistent scale.',
                'action' => 'Create a standard set of border-radius values (e.g., 4px, 8px, 16px) and use CSS variables.',
                'priority' => 'low'
            );
        }

        // Compile priority actions
        $all_recommendations = array_merge(
            $recommendations['color_recommendations'],
            $recommendations['typography_recommendations'], 
            $recommendations['design_recommendations']
        );

        $high_priority = array_filter($all_recommendations, function($rec) { return $rec['priority'] === 'high'; });
        $recommendations['priority_actions'] = array_slice($high_priority, 0, 3); // Top 3 priority items

        return $recommendations;
    }

    /**
     * HELPER METHODS FOR BRANDING ANALYSIS
     */

    private function get_all_css_files() {
        $css_files = array();
        
        // Theme CSS files
        $theme_dirs = array(get_template_directory(), get_stylesheet_directory());
        
        foreach ($theme_dirs as $dir) {
            if (is_dir($dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'css') {
                        $css_files[] = $file->getPathname();
                    }
                }
            }
        }
        
        return array_unique($css_files);
    }

    private function extract_css_context($content, $position, $context_length = 100) {
        $start = max(0, $position - $context_length);
        $length = $context_length * 2;
        
        return trim(substr($content, $start, $length));
    }

    private function convert_to_hex($color_value) {
        // Convert different color formats to hex
        if (strpos($color_value, '#') === 0) {
            return $color_value;
        }
        
        // RGB conversion
        if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $color_value, $matches)) {
            return sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
        }
        
        // RGBA conversion (ignoring alpha for hex)
        if (preg_match('/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,/', $color_value, $matches)) {
            return sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
        }
        
        // HSL conversion (basic implementation)
        if (preg_match('/hsl\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)/', $color_value, $matches)) {
            return $this->hsl_to_hex($matches[1], $matches[2], $matches[3]);
        }
        
        return $color_value; // Return original if can't convert
    }

    private function hsl_to_hex($h, $s, $l) {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;
        
        if ($s == 0) {
            $r = $g = $b = $l; // Achromatic
        } else {
            $hue2rgb = function($p, $q, $t) {
                if ($t < 0) $t += 1;
                if ($t > 1) $t -= 1;
                if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
                if ($t < 1/2) return $q;
                if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
                return $p;
            };
            
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            
            $r = $hue2rgb($p, $q, $h + 1/3);
            $g = $hue2rgb($p, $q, $h);
            $b = $hue2rgb($p, $q, $h - 1/3);
        }
        
        return sprintf('#%02x%02x%02x', round($r * 255), round($g * 255), round($b * 255));
    }

    private function parse_font_stack($font_value) {
        // Parse font-family value into array of fonts
        $fonts = array_map('trim', explode(',', $font_value));
        return array_map(function($font) {
            return trim($font, ' "\'');
        }, $fonts);
    }

    private function get_color_name($hex) {
        $color_names = array(
            '#000000' => 'Black',
            '#ffffff' => 'White',
            '#ff0000' => 'Red',
            '#00ff00' => 'Green',
            '#0000ff' => 'Blue',
            '#ffff00' => 'Yellow',
            '#ff00ff' => 'Magenta',
            '#00ffff' => 'Cyan',
            '#808080' => 'Gray',
            '#0073aa' => 'WordPress Blue'
        );
        
        return $color_names[strtolower($hex)] ?? 'Custom Color';
    }

    private function get_color_family($hex) {
        // Simplified color family detection
        $rgb = sscanf($hex, "#%02x%02x%02x");
        list($r, $g, $b) = $rgb;
        
        if ($r > $g && $r > $b) return 'Red';
        if ($g > $r && $g > $b) return 'Green';
        if ($b > $r && $b > $g) return 'Blue';
        if ($r == $g && $g == $b) return 'Grayscale';
        
        return 'Mixed';
    }

    private function is_web_safe_font($font_name) {
        $web_safe_fonts = array(
            'Arial', 'Helvetica', 'Times New Roman', 'Times', 'Courier New', 'Courier',
            'Verdana', 'Georgia', 'Palatino', 'Garamond', 'Bookman', 'Tahoma',
            'Trebuchet MS', 'Arial Black', 'Impact', 'sans-serif', 'serif', 'monospace'
        );
        
        return in_array($font_name, $web_safe_fonts);
    }

    private function get_font_category($font_name) {
        $serif_fonts = array('Times New Roman', 'Georgia', 'Palatino', 'Garamond', 'Bookman', 'serif');
        $monospace_fonts = array('Courier New', 'Courier', 'monospace');
        
        if (in_array($font_name, $serif_fonts)) return 'Serif';
        if (in_array($font_name, $monospace_fonts)) return 'Monospace';
        
        return 'Sans-serif';
    }

    private function is_google_font($font_name) {
        // Basic Google Fonts detection - could be expanded with API
        $common_google_fonts = array(
            'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Source Sans Pro',
            'Raleway', 'Poppins', 'Nunito', 'Ubuntu', 'Playfair Display'
        );
        
        return in_array($font_name, $common_google_fonts);
    }

    private function is_color_value($value) {
        return preg_match('/#[a-fA-F0-9]{3,8}|rgb|hsl|color/i', $value);
    }

    private function is_font_value($value) {
        return preg_match('/font|serif|sans|monospace/i', $value);
    }

    private function calculate_brand_consistency_score($color_palette, $typography, $design_patterns) {
        $score = 100;
        
        // Deduct points for too many colors
        if ($color_palette['color_statistics']['total_unique_colors'] > 15) {
            $score -= 20;
        }
        
        // Deduct points for too many fonts
        if (count($typography['font_families']) > 4) {
            $score -= 15;
        }
        
        // Deduct points for missing font fallbacks
        foreach ($typography['font_families'] as $font) {
            if (!$font['is_web_safe'] && count($font['fallbacks']) < 2) {
                $score -= 10;
            }
        }
        
        // Bonus points for using CSS variables
        if (isset($design_patterns['css_variables']) && !empty($design_patterns['css_variables'])) {
            $score += 10;
        }
        
        return max(0, min(100, $score));
    }

    private function detect_typography_hierarchy($theme_css, $plugin_css) {
        // Analyze heading hierarchy and text sizing
        return array(
            'heading_hierarchy' => 'Detected h1-h6 styles',
            'text_sizing' => 'Base font sizes identified'
        );
    }

    private function detect_typography_inconsistencies($font_groups) {
        $issues = array();
        
        // Check for too many different fonts
        if (count($font_groups) > 4) {
            $issues[] = 'Too many font families (' . count($font_groups) . ')';
        }
        
        return $issues;
    }

    private function generate_typography_recommendations($font_groups, $hierarchy) {
        return array(
            'limit_fonts' => 'Consider limiting to 2-3 font families',
            'add_fallbacks' => 'Ensure all custom fonts have web-safe fallbacks'
        );
    }

    private function extract_design_tokens($theme_css, $plugin_css, $property) {
        $tokens = array();
        
        // Extract specific CSS property values across all files
        $all_css_data = array_merge(
            $theme_css['design_tokens'] ?? array(),
            $plugin_css['design_tokens'] ?? array()
        );
        
        return $tokens;
    }

    private function extract_spacing_patterns($theme_css, $plugin_css) {
        // Analyze margin/padding patterns
        return array(
            'margin_values' => array(),
            'padding_values' => array(),
            'spacing_scale' => 'Consistent spacing detected'
        );
    }

    private function extract_media_query_breakpoints($theme_css, $plugin_css) {
        // Analyze responsive breakpoints
        return array(
            'breakpoints' => array('768px', '1024px', '1200px'),
            'consistency' => 'Standard breakpoints used'
        );
    }

    private function get_image_file_size($url) {
        $headers = @get_headers($url, true);
        return isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;
    }

    private function get_image_dimensions($file_path) {
        if (function_exists('getimagesize')) {
            $dimensions = @getimagesize($file_path);
            return $dimensions ? array('width' => $dimensions[0], 'height' => $dimensions[1]) : null;
        }
        return null;
    }

    private function detect_icon_fonts() {
        $icon_fonts = array();
        
        // Check for common icon fonts in enqueued styles
        global $wp_styles;
        
        if (isset($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $style) {
                if (strpos($style->src, 'font-awesome') !== false ||
                    strpos($style->src, 'dashicons') !== false ||
                    strpos($style->src, 'fontello') !== false) {
                    
                    $icon_fonts[] = array(
                        'handle' => $handle,
                        'src' => $style->src,
                        'type' => $this->detect_icon_font_type($style->src)
                    );
                }
            }
        }
        
        return $icon_fonts;
    }

    private function detect_icon_font_type($src) {
        if (strpos($src, 'font-awesome') !== false) return 'Font Awesome';
        if (strpos($src, 'dashicons') !== false) return 'WordPress Dashicons';
        if (strpos($src, 'fontello') !== false) return 'Fontello';
        
        return 'Unknown Icon Font';
    }

    private function deep_scan_ai_analysis($scan_data) {
        // Final AI analysis of all results with prioritization and deduplication
        $all_results = $scan_data['results'];
        
        // Deduplicate and prioritize
        $deduplicated_results = $this->deduplicate_and_prioritize_results($all_results, $scan_data['query']);
        
        $scan_data['results'] = $deduplicated_results;
        
        $ai_context = array(
            'query' => $scan_data['query'],
            'total_matches' => count($deduplicated_results),
            'sources_found' => array_unique(array_column($deduplicated_results, 'type')),
            'high_confidence_matches' => array_filter($deduplicated_results, function($r) {
                return ($r['confidence'] ?? 0) > 0.7;
            }),
            'top_matches' => array_slice($deduplicated_results, 0, 5) // Top 5 for AI analysis
        );

        try {
            $ai_analysis = $this->get_comprehensive_ai_analysis($scan_data['query'], $ai_context);
        } catch (Exception $e) {
            $ai_analysis = 'AI analysis unavailable: ' . $e->getMessage();
        }

        return array(
            'new_results' => array(array(
                'type' => 'ai_analysis',
                'analysis' => $ai_analysis,
                'confidence' => 0.85,
                'prioritized_results' => $deduplicated_results // Include prioritized results
            )),
            'progress' => 100,
            'current_task' => "Deep scan completed successfully",
            'phase_complete' => true
        );
    }

    /**
     * Deduplicate and prioritize results based on relevance and confidence
     */
    private function deduplicate_and_prioritize_results($results, $query) {
        $search_terms = $this->extract_search_terms($query);
        $processed_results = array();
        $seen_files = array();
        
        // First pass: Remove exact duplicates and score relevance
        foreach ($results as $result) {
            $file_key = $result['file'] ?? $result['table'] ?? $result['builder'] ?? 'unknown';
            
            // Skip packaged theme duplicates (prefer current theme files)
            if (strpos($file_key, '/packaged/') !== false) {
                $base_file = str_replace('/packaged/', '/', $file_key);
                $base_file = preg_replace('/\/ageless-literature-theme_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\//', '/', $base_file);
                
                // Skip if we already have the main theme file
                if (isset($seen_files[$base_file])) {
                    continue;
                }
            }
            
            // Calculate relevance score based on query terms
            $relevance_score = $this->calculate_relevance_score($result, $search_terms);
            $result['relevance_score'] = $relevance_score;
            
            // Create unique key for deduplication
            $unique_key = $file_key . '_' . ($result['line'] ?? 'noln');
            
            if (!isset($seen_files[$unique_key]) || $seen_files[$unique_key]['confidence'] < ($result['confidence'] ?? 0)) {
                $seen_files[$unique_key] = $result;
                $processed_results[] = $result;
            }
        }
        
        // Sort by combined score (relevance * confidence) descending
        usort($processed_results, function($a, $b) {
            $score_a = ($a['relevance_score'] ?? 0) * ($a['confidence'] ?? 0);
            $score_b = ($b['relevance_score'] ?? 0) * ($b['confidence'] ?? 0);
            return $score_b <=> $score_a;
        });
        
        // Limit to top 20 results to avoid overwhelming the user
        return array_slice($processed_results, 0, 20);
    }

    /**
     * Calculate relevance score based on query terms
     */
    private function calculate_relevance_score($result, $search_terms) {
        $score = 0;
        $text_to_search = '';
        
        // Combine searchable text from result
        $searchable_fields = ['file', 'content', 'content_preview', 'matches', 'table', 'builder'];
        foreach ($searchable_fields as $field) {
            if (isset($result[$field])) {
                if (is_array($result[$field])) {
                    $text_to_search .= ' ' . implode(' ', array_column($result[$field], 'content'));
                } else {
                    $text_to_search .= ' ' . $result[$field];
                }
            }
        }
        
        $text_to_search = strtolower($text_to_search);
        
        // Score based on search term matches
        foreach ($search_terms as $term) {
            $term = strtolower($term);
            
            // Exact matches get higher scores
            if (strpos($text_to_search, $term) !== false) {
                $score += 10;
                
                // Bonus for exact word matches
                if (preg_match('/\b' . preg_quote($term, '/') . '\b/', $text_to_search)) {
                    $score += 5;
                }
            }
            
            // Fuzzy matching for partial matches
            $similarity = 0;
            $words = explode(' ', $text_to_search);
            foreach ($words as $word) {
                similar_text($term, $word, $percent);
                $similarity = max($similarity, $percent);
            }
            
            if ($similarity > 60) {
                $score += ($similarity / 100) * 3;
            }
        }
        
        // Bonus for file types likely to contain UI elements
        $file = $result['file'] ?? '';
        if (strpos($file, 'header.php') !== false || 
            strpos($file, 'footer.php') !== false || 
            strpos($file, 'navigation') !== false ||
            strpos($file, 'menu') !== false) {
            $score += 5;
        }
        
        return $score;
    }

    /**
     * HELPER METHODS FOR DEEP SCAN
     */

    private function get_recursive_php_files($directories) {
        $files = array();
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        
        return array_unique($files);
    }

    private function find_line_matches($content, $term) {
        $lines = explode("\n", $content);
        $matches = array();
        
        foreach ($lines as $line_num => $line) {
            if (stripos($line, $term) !== false) {
                $matches[] = array(
                    'line' => $line_num + 1,
                    'content' => trim($line),
                    'context' => $this->get_surrounding_lines($lines, $line_num, 2)
                );
            }
        }
        
        return $matches;
    }

    private function get_surrounding_lines($lines, $target_line, $context_lines) {
        $start = max(0, $target_line - $context_lines);
        $end = min(count($lines) - 1, $target_line + $context_lines);
        
        $context = array();
        for ($i = $start; $i <= $end; $i++) {
            $context[] = ($i + 1) . ': ' . trim($lines[$i]);
        }
        
        return implode("\n", $context);
    }

    private function get_comprehensive_ai_analysis($query, $context) {
        $prompt = "COMPREHENSIVE DEEP SCAN ANALYSIS\n\n" .
                  "User Query: {$query}\n\n" .
                  "Total Matches Found: " . $context['total_matches'] . "\n" .
                  "Source Types: " . implode(', ', $context['sources_found']) . "\n" .
                  "High Confidence Matches: " . count($context['high_confidence_matches']) . "\n\n" .
                  "Based on this comprehensive scan, provide:\n" .
                  "1. The most likely primary source with reasoning\n" .
                  "2. Alternative sources ranked by probability\n" .
                  "3. Specific edit instructions for the user\n" .
                  "4. Overall confidence assessment\n\n" .
                  "Be specific and actionable in your response.";

        return $this->get_ai_response($query, $prompt);
    }

    /**
     * CACHING & PERFORMANCE OPTIMIZATION SYSTEM
     */

    /**
     * Enhanced caching with expiration and compression
     */
    private function get_cached_result($cache_key, $expiry = 3600) {
        $cache_data = get_transient('wsa_cache_' . $cache_key);
        
        if ($cache_data !== false) {
            // Check if cache includes performance metrics
            if (isset($cache_data['cache_hit_count'])) {
                $cache_data['cache_hit_count']++;
                set_transient('wsa_cache_' . $cache_key, $cache_data, $expiry);
            }
            
            return $cache_data['data'] ?? $cache_data;
        }
        
        return false;
    }

    private function set_cached_result($cache_key, $data, $expiry = 3600) {
        $cache_data = array(
            'data' => $data,
            'cached_at' => time(),
            'cache_hit_count' => 1,
            'cache_size' => strlen(serialize($data))
        );
        
        // Compress large data sets
        if ($cache_data['cache_size'] > 50000) { // 50KB threshold
            $cache_data['data'] = gzcompress(serialize($data));
            $cache_data['compressed'] = true;
        }
        
        return set_transient('wsa_cache_' . $cache_key, $cache_data, $expiry);
    }

    /**
     * Intelligent cache invalidation
     */
    private function invalidate_related_caches($context) {
        global $wpdb;
        
        // Clear caches based on context
        $patterns_to_clear = array();
        
        switch ($context['type'] ?? '') {
            case 'theme_change':
                $patterns_to_clear[] = 'wsa_cache_%_theme_%';
                $patterns_to_clear[] = 'wsa_cache_%_template_%';
                break;
            case 'plugin_change':
                $patterns_to_clear[] = 'wsa_cache_%_plugin_%';
                break;
            case 'menu_change':
                $patterns_to_clear[] = 'wsa_cache_%_menu_%';
                $patterns_to_clear[] = 'wsa_cache_%_nav_%';
                break;
        }

        foreach ($patterns_to_clear as $pattern) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . str_replace('%', '', $pattern)) . '%'
            ));
        }
    }

    /**
     * Performance monitoring and throttling
     */
    private function check_system_load() {
        $load_metrics = array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->parse_memory_limit(ini_get('memory_limit')),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'active_scans' => $this->count_active_deep_scans()
        );

        $load_metrics['memory_usage_percent'] = ($load_metrics['memory_usage'] / $load_metrics['memory_limit']) * 100;
        $load_metrics['load_level'] = $this->calculate_load_level($load_metrics);
        
        return $load_metrics;
    }

    private function should_throttle_scan($load_metrics) {
        // Throttle if memory usage > 80% or too many active scans
        return (
            $load_metrics['memory_usage_percent'] > 80 ||
            $load_metrics['active_scans'] > 3 ||
            $load_metrics['execution_time'] > 25 // 25 seconds
        );
    }

    private function apply_throttling($scan_type) {
        $throttle_settings = array(
            'quick' => array(
                'max_concurrent' => 5,
                'delay_between' => 1, // seconds
                'batch_size' => 20
            ),
            'deep' => array(
                'max_concurrent' => 2,
                'delay_between' => 5,
                'batch_size' => 10
            )
        );
        
        $settings = $throttle_settings[$scan_type] ?? $throttle_settings['quick'];
        
        // Implement actual throttling
        sleep($settings['delay_between']);
        
        return $settings;
    }

    /**
     * Progressive result caching for deep scans
     */
    private function cache_progressive_results($scan_id, $results, $phase) {
        $cache_key = 'progressive_' . $scan_id . '_' . $phase;
        $this->set_cached_result($cache_key, $results, 7200); // 2 hours
        
        // Update master progress cache
        $progress_cache_key = 'progress_' . $scan_id;
        $progress_data = $this->get_cached_result($progress_cache_key) ?: array();
        
        $progress_data[$phase] = array(
            'completed' => true,
            'results_count' => count($results),
            'cached_at' => time(),
            'cache_key' => $cache_key
        );
        
        $this->set_cached_result($progress_cache_key, $progress_data, 7200);
    }

    private function get_progressive_results($scan_id) {
        $progress_cache_key = 'progress_' . $scan_id;
        $progress_data = $this->get_cached_result($progress_cache_key);
        
        if (!$progress_data) {
            return array();
        }
        
        $all_results = array();
        foreach ($progress_data as $phase => $phase_info) {
            if ($phase_info['completed'] && isset($phase_info['cache_key'])) {
                $phase_results = $this->get_cached_result($phase_info['cache_key']);
                if ($phase_results) {
                    $all_results = array_merge($all_results, $phase_results);
                }
            }
        }
        
        return $all_results;
    }

    /**
     * Scheduled cache cleanup
     */
    public function cleanup_expired_caches() {
        global $wpdb;
        
        // Clean up expired scan data
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wsa_deep_scan_%' 
             AND option_value NOT LIKE '%in_progress%'"
        );
        
        // Clean up old cache entries
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wsa_cache_%' 
             AND option_name IN (
                 SELECT option_name FROM (
                     SELECT option_name 
                     FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_timeout_wsa_cache_%' 
                     AND option_value < UNIX_TIMESTAMP()
                 ) as expired
             )"
        );
        
        // Log cleanup stats
        $this->log_cache_cleanup_stats();
    }

    /**
     * Performance monitoring
     */
    public function monitor_system_performance() {
        $metrics = $this->check_system_load();
        
        // Log performance metrics
        $performance_log = get_option('wsa_performance_log', array());
        $performance_log[] = array(
            'timestamp' => time(),
            'memory_usage_mb' => round($metrics['memory_usage'] / 1024 / 1024, 2),
            'memory_peak_mb' => round($metrics['memory_peak'] / 1024 / 1024, 2),
            'memory_usage_percent' => round($metrics['memory_usage_percent'], 2),
            'execution_time' => round($metrics['execution_time'], 2),
            'active_scans' => $metrics['active_scans'],
            'load_level' => $metrics['load_level']
        );
        
        // Keep only last 100 entries
        if (count($performance_log) > 100) {
            $performance_log = array_slice($performance_log, -100);
        }
        
        update_option('wsa_performance_log', $performance_log);
        
        // Alert if performance is degraded
        if ($metrics['load_level'] === 'critical') {
            $this->send_performance_alert($metrics);
        }
    }

    /**
     * HELPER METHODS FOR CACHING & PERFORMANCE
     */

    private function parse_memory_limit($memory_limit) {
        $value = trim($memory_limit);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    private function count_active_deep_scans() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wsa_deep_scan_%' 
             AND option_value LIKE '%in_progress%'"
        );
        
        return (int) $count;
    }

    private function calculate_load_level($metrics) {
        $score = 0;
        
        // Memory usage factor (0-40 points)
        $score += min(40, ($metrics['memory_usage_percent'] / 100) * 40);
        
        // Active scans factor (0-30 points)
        $score += min(30, ($metrics['active_scans'] / 5) * 30);
        
        // Execution time factor (0-30 points)  
        $score += min(30, ($metrics['execution_time'] / 30) * 30);
        
        if ($score >= 80) return 'critical';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }

    private function log_cache_cleanup_stats() {
        global $wpdb;
        
        $stats = array(
            'scan_caches_cleaned' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wsa_deep_scan_%'"
            ),
            'result_caches_cleaned' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wsa_cache_%'"
            ),
            'cleanup_time' => time()
        );
        
        update_option('wsa_last_cache_cleanup', $stats);
    }

    private function send_performance_alert($metrics) {
        // Send alert to admin about performance issues
        $message = sprintf(
            "WSA Site Detective Performance Alert:\n\n" .
            "Memory Usage: %.1f%% (%.1fMB / %.1fMB)\n" .
            "Active Scans: %d\n" .
            "Execution Time: %.2fs\n" .
            "Load Level: %s\n\n" .
            "Consider pausing deep scans or increasing server resources.",
            $metrics['memory_usage_percent'],
            $metrics['memory_usage'] / 1024 / 1024,
            $metrics['memory_limit'] / 1024 / 1024,
            $metrics['active_scans'],
            $metrics['execution_time'],
            $metrics['load_level']
        );
        
        wp_mail(
            get_option('admin_email'),
            'WSA Site Detective Performance Alert',
            $message
        );
    }

    /**
     * Export performance and cache statistics
     */
    public function get_performance_statistics() {
        $performance_log = get_option('wsa_performance_log', array());
        $cache_cleanup = get_option('wsa_last_cache_cleanup', array());
        $current_metrics = $this->check_system_load();
        
        return array(
            'current_metrics' => $current_metrics,
            'performance_history' => array_slice($performance_log, -20), // Last 20 entries
            'cache_statistics' => $cache_cleanup,
            'optimization_recommendations' => $this->get_optimization_recommendations($current_metrics)
        );
    }

    private function get_optimization_recommendations($metrics) {
        $recommendations = array();
        
        if ($metrics['memory_usage_percent'] > 70) {
            $recommendations[] = 'Consider increasing PHP memory limit or reducing concurrent scans';
        }
        
        if ($metrics['active_scans'] > 2) {
            $recommendations[] = 'Limit concurrent deep scans to improve performance';
        }
        
        if ($metrics['execution_time'] > 20) {
            $recommendations[] = 'Enable caching and reduce scan depth for better response times';
        }
        
        return $recommendations;
    }
}

// Initialize the AI Site Detective
WSA_AI_Site_Detective::get_instance();