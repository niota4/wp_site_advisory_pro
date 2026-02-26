<?php
/**
 * AI Content Analyzer
 * 
 * Provides intelligent content analysis including SEO optimization, accessibility
 * scanning, content quality assessment, and duplicate content detection
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Analyzer {
    
    private $analysis_metrics = array();
    private $seo_factors = array();
    private $accessibility_checks = array();
    
    /**
     * Initialize content analyzer
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_analysis_metrics();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Content analysis hooks
        add_action('save_post', array($this, 'analyze_post_on_save'), 10, 2);
        add_action('wp_ajax_wsa_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_wsa_bulk_analyze_content', array($this, 'ajax_bulk_analyze_content'));
        add_action('wp_ajax_wsa_get_seo_suggestions', array($this, 'ajax_get_seo_suggestions'));
        add_action('wp_ajax_wsa_check_accessibility', array($this, 'ajax_check_accessibility'));
        add_action('wp_ajax_wsa_detect_duplicate_content', array($this, 'ajax_detect_duplicate_content'));
        add_action('wp_ajax_wsa_generate_content_report', array($this, 'ajax_generate_content_report'));
        
        // Schedule content audits
        add_action('wp', array($this, 'schedule_content_audits'));
        add_action('wsa_run_content_audit', array($this, 'run_scheduled_content_audit'));
        add_action('wsa_analyze_single_post', array($this, 'run_single_post_analysis'));
        
        // Add content analysis metabox to post editor
        add_action('add_meta_boxes', array($this, 'add_content_analysis_metabox'));
        
        // Enqueue scripts for content analysis in post editor
        add_action('admin_enqueue_scripts', array($this, 'enqueue_content_analysis_scripts'));
    }
    
    /**
     * Load analysis metrics and factors
     */
    private function load_analysis_metrics() {
        $this->seo_factors = array(
            'title_optimization' => array(
                'name' => 'Title Optimization',
                'weight' => 20,
                'description' => 'Title length, keyword usage, and uniqueness'
            ),
            'meta_description' => array(
                'name' => 'Meta Description',
                'weight' => 15,
                'description' => 'Meta description presence, length, and keyword usage'
            ),
            'heading_structure' => array(
                'name' => 'Heading Structure',
                'weight' => 15,
                'description' => 'Proper H1-H6 hierarchy and keyword distribution'
            ),
            'keyword_density' => array(
                'name' => 'Keyword Density',
                'weight' => 12,
                'description' => 'Target keyword frequency and distribution'
            ),
            'content_length' => array(
                'name' => 'Content Length',
                'weight' => 10,
                'description' => 'Adequate content length for topic coverage'
            ),
            'internal_linking' => array(
                'name' => 'Internal Linking',
                'weight' => 8,
                'description' => 'Internal link structure and anchor text optimization'
            ),
            'image_optimization' => array(
                'name' => 'Image SEO',
                'weight' => 8,
                'description' => 'Alt text, file names, and image optimization'
            ),
            'readability' => array(
                'name' => 'Readability',
                'weight' => 7,
                'description' => 'Content readability and user experience'
            ),
            'schema_markup' => array(
                'name' => 'Schema Markup',
                'weight' => 5,
                'description' => 'Structured data implementation'
            )
        );
        
        $this->accessibility_checks = array(
            'alt_text' => 'Image alt text compliance',
            'heading_hierarchy' => 'Proper heading structure',
            'color_contrast' => 'Color contrast ratios',
            'keyboard_navigation' => 'Keyboard accessibility',
            'aria_labels' => 'ARIA label usage',
            'form_labels' => 'Form field labeling',
            'link_context' => 'Descriptive link text',
            'page_structure' => 'Semantic HTML structure'
        );
    }
    
    /**
     * Schedule content audits
     */
    public function schedule_content_audits() {
        // Only schedule if content analysis automation is enabled
        if (!get_option('wsa_pro_enable_content_audits', false)) {
            return;
        }
        
        // Check if audits are already scheduled
        $scheduled_audits = wp_get_scheduled_event('wsa_run_content_audit');
        if ($scheduled_audits) {
            return; // Already scheduled
        }
        
        // Schedule weekly content audit
        wp_schedule_event(time(), 'weekly', 'wsa_run_content_audit');
        
        // Log scheduling
    }
    
    /**
     * Run scheduled content audit
     */
    public function run_scheduled_content_audit() {
        // Safety check - don't run if audits are disabled
        if (!get_option('wsa_pro_enable_content_audits', false)) {
            return;
        }
        
        // Get recent posts to audit (published in last 30 days)
        $recent_posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'numberposts' => 50,
            'date_query' => array(
                array(
                    'after' => '30 days ago',
                    'inclusive' => true,
                )
            )
        ));
        
        $audit_results = array();
        $issues_found = 0;
        
        foreach ($recent_posts as $post) {
            // Run content analysis
            $analysis = $this->analyze_content($post->ID, array('include_ai' => false));
            
            // Check for significant issues
            $has_issues = $this->has_content_issues($analysis);
            
            if ($has_issues) {
                $audit_results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'issues' => $this->extract_major_issues($analysis),
                    'severity' => $this->calculate_issue_severity($analysis)
                );
                $issues_found++;
            }
        }
        
        // Store audit results
        update_option('wsa_pro_last_content_audit', array(
            'timestamp' => time(),
            'posts_audited' => count($recent_posts),
            'issues_found' => $issues_found,
            'results' => $audit_results
        ));
        
        // Send notification if significant issues found
        if ($issues_found > 0) {
            $this->send_content_audit_notification($issues_found, $audit_results);
        }
        
        
        return $audit_results;
    }
    
    /**
     * Analyze content comprehensively
     */
    public function analyze_content($post_id, $options = array()) {
        $post = get_post($post_id);
        if (!$post) {
            return array('error' => 'Post not found');
        }
        
        $analysis_results = array(
            'post_id' => $post_id,
            'analysis_timestamp' => current_time('timestamp'),
            'seo_analysis' => $this->analyze_seo_factors($post, $options),
            'accessibility_analysis' => $this->analyze_accessibility($post, $options),
            'content_quality' => $this->analyze_content_quality($post, $options),
            'duplicate_content' => $this->detect_duplicate_content($post, $options),
            'ai_insights' => $this->get_ai_content_insights($post, $options),
            'overall_score' => 0,
            'recommendations' => array()
        );
        
        // Calculate overall score
        $analysis_results['overall_score'] = $this->calculate_overall_content_score($analysis_results);
        
        // Generate comprehensive recommendations
        $analysis_results['recommendations'] = $this->generate_content_recommendations($analysis_results);
        
        // Store analysis results for tracking
        $this->store_analysis_results($post_id, $analysis_results);
        
        return $analysis_results;
    }
    
    /**
     * Analyze SEO factors
     */
    private function analyze_seo_factors($post, $options = array()) {
        $seo_analysis = array();
        $content = $post->post_content;
        $title = $post->post_title;
        
        // Title analysis
        $seo_analysis['title'] = $this->analyze_title_seo($title, $content, $options);
        
        // Meta description analysis
        $seo_analysis['meta_description'] = $this->analyze_meta_description($post, $options);
        
        // Heading structure analysis
        $seo_analysis['headings'] = $this->analyze_heading_structure($content, $options);
        
        // Keyword analysis
        $seo_analysis['keywords'] = $this->analyze_keyword_usage($content, $title, $options);
        
        // Content length analysis
        $seo_analysis['content_length'] = $this->analyze_content_length($content, $options);
        
        // Internal linking analysis
        $seo_analysis['internal_links'] = $this->analyze_internal_links($content, $post->ID, $options);
        
        // Image SEO analysis
        $seo_analysis['images'] = $this->analyze_image_seo($content, $post->ID, $options);
        
        // Readability analysis
        $seo_analysis['readability'] = $this->analyze_readability($content, $options);
        
        return $seo_analysis;
    }
    
    /**
     * Analyze title for SEO optimization
     */
    private function analyze_title_seo($title, $content, $options) {
        $analysis = array(
            'length' => strlen($title),
            'word_count' => str_word_count($title),
            'score' => 0,
            'issues' => array(),
            'suggestions' => array()
        );
        
        // Length check (optimal: 50-60 characters)
        if ($analysis['length'] < 30) {
            $analysis['issues'][] = 'Title is too short';
            $analysis['suggestions'][] = 'Expand title to 50-60 characters for better SEO';
        } elseif ($analysis['length'] > 70) {
            $analysis['issues'][] = 'Title is too long';
            $analysis['suggestions'][] = 'Shorten title to under 60 characters to avoid truncation';
        } else {
            $analysis['score'] += 25;
        }
        
        // Keyword analysis
        if (isset($options['target_keyword'])) {
            $keyword = strtolower($options['target_keyword']);
            $title_lower = strtolower($title);
            
            if (strpos($title_lower, $keyword) !== false) {
                $analysis['score'] += 25;
                // Check keyword position
                $keyword_position = strpos($title_lower, $keyword);
                if ($keyword_position < strlen($title) / 3) {
                    $analysis['score'] += 10;
                } else {
                    $analysis['suggestions'][] = 'Move target keyword closer to the beginning of the title';
                }
            } else {
                $analysis['issues'][] = 'Target keyword not found in title';
                $analysis['suggestions'][] = 'Include your target keyword in the title';
            }
        }
        
        // Uniqueness check
        $similar_titles = $this->check_title_uniqueness($title);
        if (count($similar_titles) > 0) {
            $analysis['issues'][] = 'Similar titles found on other posts';
            $analysis['suggestions'][] = 'Make title more unique to stand out';
        } else {
            $analysis['score'] += 15;
        }
        
        // Engagement factors
        $engagement_words = array('how', 'what', 'why', 'guide', 'tips', 'best', 'top');
        $title_words = str_word_count($title, 1);
        $has_engagement_words = false;
        
        foreach ($engagement_words as $word) {
            if (in_array(strtolower($word), array_map('strtolower', $title_words))) {
                $has_engagement_words = true;
                break;
            }
        }
        
        if ($has_engagement_words) {
            $analysis['score'] += 10;
        } else {
            $analysis['suggestions'][] = 'Consider using engaging words like "how to", "guide", or "tips"';
        }
        
        return $analysis;
    }
    
    /**
     * Analyze heading structure
     */
    private function analyze_heading_structure($content, $options) {
        $analysis = array(
            'h1_count' => 0,
            'heading_hierarchy' => array(),
            'score' => 0,
            'issues' => array(),
            'suggestions' => array()
        );
        
        // Extract all headings
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i', $content, $matches, PREG_SET_ORDER);
        
        $heading_levels = array();
        foreach ($matches as $match) {
            $level = intval($match[1]);
            $text = strip_tags($match[2]);
            
            if ($level === 1) {
                $analysis['h1_count']++;
            }
            
            $heading_levels[] = $level;
            $analysis['heading_hierarchy'][] = array(
                'level' => $level,
                'text' => $text,
                'length' => strlen($text)
            );
        }
        
        // H1 analysis
        if ($analysis['h1_count'] === 0) {
            $analysis['issues'][] = 'No H1 heading found';
            $analysis['suggestions'][] = 'Add an H1 heading to your content';
        } elseif ($analysis['h1_count'] > 1) {
            $analysis['issues'][] = 'Multiple H1 headings found';
            $analysis['suggestions'][] = 'Use only one H1 heading per page';
        } else {
            $analysis['score'] += 30;
        }
        
        // Hierarchy check
        $hierarchy_issues = $this->check_heading_hierarchy($heading_levels);
        if (empty($hierarchy_issues)) {
            $analysis['score'] += 30;
        } else {
            $analysis['issues'] = array_merge($analysis['issues'], $hierarchy_issues);
            $analysis['suggestions'][] = 'Fix heading hierarchy to improve content structure';
        }
        
        // Keyword usage in headings
        if (isset($options['target_keyword'])) {
            $keyword = strtolower($options['target_keyword']);
            $keyword_in_headings = false;
            
            foreach ($analysis['heading_hierarchy'] as $heading) {
                if (strpos(strtolower($heading['text']), $keyword) !== false) {
                    $keyword_in_headings = true;
                    break;
                }
            }
            
            if ($keyword_in_headings) {
                $analysis['score'] += 20;
            } else {
                $analysis['suggestions'][] = 'Include target keyword in at least one heading';
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze content for accessibility issues
     */
    private function analyze_accessibility($post, $options) {
        $content = $post->post_content;
        $accessibility_score = 0;
        $issues = array();
        $suggestions = array();
        
        // Check images for alt text
        preg_match_all('/<img[^>]*>/i', $content, $images);
        $image_issues = $this->check_image_accessibility($images[0]);
        if (empty($image_issues['issues'])) {
            $accessibility_score += 20;
        } else {
            $issues = array_merge($issues, $image_issues['issues']);
            $suggestions = array_merge($suggestions, $image_issues['suggestions']);
        }
        
        // Check heading hierarchy for accessibility
        $heading_accessibility = $this->check_heading_accessibility($content);
        $accessibility_score += $heading_accessibility['score'];
        $issues = array_merge($issues, $heading_accessibility['issues']);
        $suggestions = array_merge($suggestions, $heading_accessibility['suggestions']);
        
        // Check links for descriptive text
        $link_analysis = $this->analyze_link_accessibility($content);
        $accessibility_score += $link_analysis['score'];
        $issues = array_merge($issues, $link_analysis['issues']);
        $suggestions = array_merge($suggestions, $link_analysis['suggestions']);
        
        // Check for color contrast issues (basic detection)
        $color_analysis = $this->analyze_color_accessibility($content);
        $accessibility_score += $color_analysis['score'];
        $issues = array_merge($issues, $color_analysis['issues']);
        $suggestions = array_merge($suggestions, $color_analysis['suggestions']);
        
        return array(
            'score' => min(100, $accessibility_score),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'wcag_level' => $this->determine_wcag_compliance_level($accessibility_score, $issues)
        );
    }
    
    /**
     * Detect duplicate content
     */
    private function detect_duplicate_content($post, $options) {
        $content = $post->post_content;
        $plain_content = wp_strip_all_tags($content);
        
        // Internal duplicate detection
        $internal_duplicates = $this->find_internal_duplicate_content($plain_content, $post->ID);
        
        // External duplicate detection (if API available)
        $external_duplicates = array();
        if (isset($options['check_external']) && $options['check_external']) {
            $external_duplicates = $this->check_external_duplicate_content($plain_content);
        }
        
        // Content similarity analysis
        $similarity_analysis = $this->analyze_content_similarity($plain_content, $post->ID);
        
        $duplicate_score = 100;
        if (!empty($internal_duplicates)) {
            $duplicate_score -= (count($internal_duplicates) * 20);
        }
        if (!empty($external_duplicates)) {
            $duplicate_score -= 30;
        }
        
        return array(
            'score' => max(0, $duplicate_score),
            'internal_duplicates' => $internal_duplicates,
            'external_duplicates' => $external_duplicates,
            'similarity_analysis' => $similarity_analysis,
            'recommendations' => $this->get_duplicate_content_recommendations($internal_duplicates, $external_duplicates)
        );
    }
    
    /**
     * Get AI-powered content insights
     */
    private function get_ai_content_insights($post, $options) {
        $openai_key = $this->get_openai_api_key();
        if (!$openai_key) {
            return array('error' => 'OpenAI API key not configured');
        }
        
        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;
        
        $prompt = $this->build_content_analysis_prompt($title, $content, $options);
        $ai_response = $this->query_openai_for_content_analysis($prompt, $openai_key);
        
        if ($ai_response) {
            return $this->parse_ai_content_insights($ai_response);
        }
        
        return array('error' => 'Failed to get AI insights');
    }
    
    /**
     * Build content analysis prompt for AI
     */
    private function build_content_analysis_prompt($title, $content, $options) {
        $word_count = str_word_count($content);
        $target_keyword = isset($options['target_keyword']) ? $options['target_keyword'] : '';
        
        $prompt = "Analyze the following blog post content for SEO and user experience:\n\n";
        $prompt .= "Title: {$title}\n";
        $prompt .= "Word Count: {$word_count}\n";
        if ($target_keyword) {
            $prompt .= "Target Keyword: {$target_keyword}\n";
        }
        $prompt .= "Content: " . substr($content, 0, 2000) . "...\n\n";
        
        $prompt .= "Please provide:\n";
        $prompt .= "1. Content quality assessment (1-10 scale)\n";
        $prompt .= "2. SEO optimization suggestions\n";
        $prompt .= "3. User experience improvements\n";
        $prompt .= "4. Content structure recommendations\n";
        $prompt .= "5. Keyword optimization opportunities\n";
        $prompt .= "6. Engagement improvement tips\n\n";
        $prompt .= "Format as JSON with keys: quality_score, seo_suggestions, ux_improvements, structure_tips, keyword_opportunities, engagement_tips";
        
        return $prompt;
    }
    
    /**
     * Calculate overall content score
     */
    private function calculate_overall_content_score($analysis_results) {
        $weights = array(
            'seo_analysis' => 0.4,
            'accessibility_analysis' => 0.25,
            'content_quality' => 0.25,
            'duplicate_content' => 0.1
        );
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($weights as $category => $weight) {
            if (isset($analysis_results[$category]['score'])) {
                $total_score += $analysis_results[$category]['score'] * $weight;
                $total_weight += $weight;
            }
        }
        
        return $total_weight > 0 ? round($total_score / $total_weight) : 0;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_analyze_content() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $options = isset($_POST['options']) ? $_POST['options'] : array();
        
        $analysis = $this->analyze_content($post_id, $options);
        
        wp_send_json_success($analysis);
    }
    
    public function ajax_get_seo_suggestions() {
        check_ajax_referer('wsa_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $analysis = $this->analyze_content($post_id);
        
        $seo_suggestions = array();
        if (isset($analysis['seo_analysis'])) {
            foreach ($analysis['seo_analysis'] as $factor => $data) {
                if (isset($data['suggestions']) && !empty($data['suggestions'])) {
                    $seo_suggestions[$factor] = $data['suggestions'];
                }
            }
        }
        
        wp_send_json_success($seo_suggestions);
    }
    
    /**
     * Enqueue content analysis scripts
     */
    public function enqueue_content_analysis_scripts($hook) {
        // Only load on post edit screens
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        global $post;
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Enqueue WordPress color picker
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        // Enqueue custom content analysis script
        wp_enqueue_script(
            'wsa-content-analysis',
            plugin_dir_url(__FILE__) . '../assets/js/content-analysis.js',
            array('jquery', 'wp-color-picker'),
            WSA_PRO_VERSION,
            true
        );
        
        // Enqueue content analysis styles
        wp_enqueue_style(
            'wsa-content-analysis',
            plugin_dir_url(__FILE__) . '../assets/css/content-analysis.css',
            array('wp-color-picker'),
            WSA_PRO_VERSION
        );
        
        // Localize script with AJAX data
        wp_localize_script('wsa-content-analysis', 'wsaContentAnalysis', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsa_content_analysis_nonce'),
            'post_id' => $post->ID,
            'messages' => array(
                'analyzing' => __('Analyzing content...', 'wp-site-advisor-pro'),
                'analysis_complete' => __('Analysis complete', 'wp-site-advisor-pro'),
                'analysis_failed' => __('Analysis failed. Please try again.', 'wp-site-advisor-pro'),
                'no_content' => __('Please add some content to analyze.', 'wp-site-advisor-pro')
            )
        ));
    }
    
    /**
     * Add content analysis metabox to post editor
     */
    public function add_content_analysis_metabox() {
        $post_types = array('post', 'page');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'wsa_content_analysis',
                'WP SiteAdvisor Pro - Content Analysis',
                array($this, 'render_content_analysis_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render content analysis metabox
     */
    public function render_content_analysis_metabox($post) {
        wp_nonce_field('wsa_content_analysis', 'wsa_content_analysis_nonce');
        
        echo '<div id="wsa-content-analysis-widget">';
        echo '<button type="button" class="button" id="wsa-analyze-content">Analyze Content</button>';
        echo '<div id="wsa-analysis-results" style="margin-top: 10px;"></div>';
        echo '</div>';
    }
    
    /**
     * Helper methods
     */
    private function get_openai_api_key() {
        $free_key = get_option('wsa_openai_api_key');
        if (!empty($free_key)) {
            return $free_key;
        }
        
        return get_option('wsa_pro_openai_api_key');
    }
    
    private function query_openai_for_content_analysis($prompt, $api_key) {
        // Similar to other OpenAI queries in the codebase
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
                        'content' => 'You are an SEO and content expert. Provide specific, actionable recommendations for improving content quality and search engine optimization.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 1200,
                'temperature' => 0.3
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : false;
    }
    
    /**
     * Store analysis results for historical tracking
     */
    private function store_analysis_results($post_id, $results) {
        $analysis_history = get_post_meta($post_id, '_wsa_content_analysis_history', true);
        if (!is_array($analysis_history)) {
            $analysis_history = array();
        }
        
        // Keep only the last 10 analyses
        array_unshift($analysis_history, $results);
        $analysis_history = array_slice($analysis_history, 0, 10);
        
        update_post_meta($post_id, '_wsa_content_analysis_history', $analysis_history);
        update_post_meta($post_id, '_wsa_content_analysis_latest', $results);
    }
    
    /**
     * Analyze post content when post is saved
     * This method was missing and causing fatal errors during post saves
     */
    public function analyze_post_on_save($post_id, $post = null) {
        // Prevent infinite loops
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get post object if not provided
        if (!$post) {
            $post = get_post($post_id);
        }
        
        // Only analyze published posts and pages
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Only analyze post types that should be analyzed
        $analyzable_post_types = apply_filters('wsa_pro_analyzable_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $analyzable_post_types)) {
            return;
        }
        
        // Check if auto-analysis is enabled
        $auto_analyze = get_option('wsa_pro_auto_analyze_content', false);
        if (!$auto_analyze) {
            return;
        }
        
        // Prevent analysis if this is a minor edit (no content changes)
        $previous_content = get_post_meta($post_id, '_wsa_last_analyzed_content', true);
        $current_content = $post->post_content . '|' . $post->post_title;
        
        if ($previous_content === $current_content) {
            return; // Content hasn't changed
        }
        
        // Schedule analysis to run after post save is complete
        wp_schedule_single_event(time() + 10, 'wsa_analyze_single_post', array($post_id));
        
        // Update the last analyzed content marker
        update_post_meta($post_id, '_wsa_last_analyzed_content', $current_content);
        
        // Log the analysis scheduling
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WP Site Advisory: Scheduled content analysis for post ID ' . $post_id);
        }
    }
    
    /**
     * Run analysis for a single post (scheduled callback)
     */
    public function run_single_post_analysis($post_id) {
        // Double-check the post exists and is published
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Run the full content analysis
        $analysis_results = $this->analyze_content($post_id);
        
        // Log completion
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WP Site Advisory: Completed content analysis for post ID ' . $post_id);
        }
        
        return $analysis_results;
    }
}