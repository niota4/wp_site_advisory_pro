<?php
/**
 * WPScan Vulnerability Scanner
 * 
 * Integrates with WPScan API to check for vulnerabilities in plugins, themes, and WordPress core
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class WPScan_Vulnerabilities {
    
    const WPSCAN_API_URL = 'https://wpscan.com/api/v3';
    const CACHE_DURATION = DAY_IN_SECONDS; // 24 hours
    
    private $api_token;
    
    /**
     * Initialize the vulnerability scanner
     */
    public function __construct() {
        $this->api_token = get_option('wsa_pro_wpscan_api_token');
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule daily vulnerability checks
        add_action('wp', array($this, 'schedule_vulnerability_check'));
        add_action('wsa_daily_vulnerability_check', array($this, 'run_vulnerability_scan'));
        
        // AJAX handlers
        add_action('wp_ajax_wsa_scan_vulnerabilities', array($this, 'ajax_scan_vulnerabilities'));
        add_action('wp_ajax_wsa_get_vulnerability_report', array($this, 'ajax_get_vulnerability_report'));
        
        // Admin notices for critical vulnerabilities
        add_action('admin_notices', array($this, 'show_vulnerability_notices'));
    }
    
    /**
     * Schedule daily vulnerability checks
     */
    public function schedule_vulnerability_check() {
        if (!wp_next_scheduled('wsa_daily_vulnerability_check')) {
            wp_schedule_event(time(), 'daily', 'wsa_daily_vulnerability_check');
        }
    }
    
    /**
     * Run comprehensive vulnerability scan
     */
    public function run_vulnerability_scan() {
        if (empty($this->api_token)) {
            return;
        }
        
        $results = array(
            'wordpress_core' => $this->check_wordpress_vulnerabilities(),
            'plugins' => $this->check_plugin_vulnerabilities(),
            'themes' => $this->check_theme_vulnerabilities(),
            'scan_time' => current_time('timestamp'),
            'api_requests_used' => 0
        );
        
        // Calculate total API requests used
        $results['api_requests_used'] = 
            (isset($results['wordpress_core']['api_requests']) ? $results['wordpress_core']['api_requests'] : 0) +
            (isset($results['plugins']['api_requests']) ? $results['plugins']['api_requests'] : 0) +
            (isset($results['themes']['api_requests']) ? $results['themes']['api_requests'] : 0);
        
        // Store results
        update_option('wsa_pro_vulnerability_scan', $results);
        
        // Send notifications for critical vulnerabilities
        $this->check_and_notify_critical_vulnerabilities($results);
        
        return $results;
    }
    
    /**
     * Check WordPress core vulnerabilities
     */
    private function check_wordpress_vulnerabilities() {
        $wp_version = get_bloginfo('version');
        $cache_key = 'wsa_wp_vulns_' . md5($wp_version);
        
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $endpoint = self::WPSCAN_API_URL . '/wordpresses/' . urlencode($wp_version);
        
        $response = $this->make_api_request($endpoint);
        
        if (is_wp_error($response)) {
            return array(
                'error' => $response->get_error_message(),
                'version' => $wp_version,
                'vulnerabilities' => array(),
                'api_requests' => 1
            );
        }
        
        $vulnerabilities = array();
        
        if (isset($response[$wp_version]['vulnerabilities'])) {
            foreach ($response[$wp_version]['vulnerabilities'] as $vuln) {
                $vulnerabilities[] = array(
                    'id' => isset($vuln['id']) ? $vuln['id'] : '',
                    'title' => isset($vuln['title']) ? $vuln['title'] : 'Unknown Vulnerability',
                    'fixed_in' => isset($vuln['fixed_in']) ? $vuln['fixed_in'] : null,
                    'references' => isset($vuln['references']) ? $vuln['references'] : array(),
                    'severity' => $this->determine_severity($vuln),
                    'published_date' => isset($vuln['published_date']) ? $vuln['published_date'] : null
                );
            }
        }
        
        $result = array(
            'version' => $wp_version,
            'vulnerabilities' => $vulnerabilities,
            'total_vulnerabilities' => count($vulnerabilities),
            'api_requests' => 1
        );
        
        // Cache for 24 hours
        set_transient($cache_key, $result, self::CACHE_DURATION);
        
        return $result;
    }
    
    /**
     * Check plugin vulnerabilities
     */
    private function check_plugin_vulnerabilities() {
        $active_plugins = get_option('active_plugins', array());
        $all_plugins = get_plugins();
        
        $results = array(
            'scanned_plugins' => array(),
            'total_vulnerabilities' => 0,
            'api_requests' => 0
        );
        
        foreach ($active_plugins as $plugin_file) {
            if (!isset($all_plugins[$plugin_file])) {
                continue;
            }
            
            $plugin_data = $all_plugins[$plugin_file];
            $plugin_slug = dirname($plugin_file);
            
            if (empty($plugin_slug) || $plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }
            
            $vulnerabilities = $this->check_plugin_vulnerability($plugin_slug, $plugin_data['Version']);
            
            $results['scanned_plugins'][$plugin_slug] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'file' => $plugin_file,
                'vulnerabilities' => $vulnerabilities,
                'vulnerability_count' => count($vulnerabilities)
            );
            
            $results['total_vulnerabilities'] += count($vulnerabilities);
            $results['api_requests']++;
            
            // Rate limiting - sleep between requests
            usleep(500000); // 0.5 seconds
        }
        
        return $results;
    }
    
    /**
     * Check individual plugin vulnerability
     */
    private function check_plugin_vulnerability($plugin_slug, $version) {
        $cache_key = 'wsa_plugin_vulns_' . md5($plugin_slug . $version);
        
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $endpoint = self::WPSCAN_API_URL . '/plugins/' . urlencode($plugin_slug);
        
        $response = $this->make_api_request($endpoint);
        
        if (is_wp_error($response)) {
            // Cache empty result to avoid repeated failed requests
            set_transient($cache_key, array(), HOUR_IN_SECONDS * 6);
            return array();
        }
        
        $vulnerabilities = array();
        
        if (isset($response[$plugin_slug]['vulnerabilities'])) {
            foreach ($response[$plugin_slug]['vulnerabilities'] as $vuln) {
                // Check if this vulnerability affects the current version
                if ($this->version_is_vulnerable($version, $vuln)) {
                    $vulnerabilities[] = array(
                        'id' => isset($vuln['id']) ? $vuln['id'] : '',
                        'title' => isset($vuln['title']) ? $vuln['title'] : 'Unknown Vulnerability',
                        'fixed_in' => isset($vuln['fixed_in']) ? $vuln['fixed_in'] : null,
                        'references' => isset($vuln['references']) ? $vuln['references'] : array(),
                        'severity' => $this->determine_severity($vuln),
                        'published_date' => isset($vuln['published_date']) ? $vuln['published_date'] : null,
                        'affected_versions' => isset($vuln['affected_versions']) ? $vuln['affected_versions'] : array()
                    );
                }
            }
        }
        
        // Cache for 24 hours
        set_transient($cache_key, $vulnerabilities, self::CACHE_DURATION);
        
        return $vulnerabilities;
    }
    
    /**
     * Check theme vulnerabilities
     */
    private function check_theme_vulnerabilities() {
        $current_theme = wp_get_theme();
        $theme_slug = $current_theme->get_stylesheet();
        $theme_version = $current_theme->get('Version');
        
        $results = array(
            'scanned_themes' => array(),
            'total_vulnerabilities' => 0,
            'api_requests' => 0
        );
        
        $vulnerabilities = $this->check_theme_vulnerability($theme_slug, $theme_version);
        
        $results['scanned_themes'][$theme_slug] = array(
            'name' => $current_theme->get('Name'),
            'version' => $theme_version,
            'vulnerabilities' => $vulnerabilities,
            'vulnerability_count' => count($vulnerabilities)
        );
        
        $results['total_vulnerabilities'] = count($vulnerabilities);
        $results['api_requests'] = 1;
        
        return $results;
    }
    
    /**
     * Check individual theme vulnerability
     */
    private function check_theme_vulnerability($theme_slug, $version) {
        $cache_key = 'wsa_theme_vulns_' . md5($theme_slug . $version);
        
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $endpoint = self::WPSCAN_API_URL . '/themes/' . urlencode($theme_slug);
        
        $response = $this->make_api_request($endpoint);
        
        if (is_wp_error($response)) {
            // Cache empty result
            set_transient($cache_key, array(), HOUR_IN_SECONDS * 6);
            return array();
        }
        
        $vulnerabilities = array();
        
        if (isset($response[$theme_slug]['vulnerabilities'])) {
            foreach ($response[$theme_slug]['vulnerabilities'] as $vuln) {
                // Check if this vulnerability affects the current version
                if ($this->version_is_vulnerable($version, $vuln)) {
                    $vulnerabilities[] = array(
                        'id' => isset($vuln['id']) ? $vuln['id'] : '',
                        'title' => isset($vuln['title']) ? $vuln['title'] : 'Unknown Vulnerability',
                        'fixed_in' => isset($vuln['fixed_in']) ? $vuln['fixed_in'] : null,
                        'references' => isset($vuln['references']) ? $vuln['references'] : array(),
                        'severity' => $this->determine_severity($vuln),
                        'published_date' => isset($vuln['published_date']) ? $vuln['published_date'] : null
                    );
                }
            }
        }
        
        // Cache for 24 hours
        set_transient($cache_key, $vulnerabilities, self::CACHE_DURATION);
        
        return $vulnerabilities;
    }
    
    /**
     * Make API request to WPScan
     */
    private function make_api_request($endpoint) {
        $response = wp_remote_get($endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Token token=' . $this->api_token,
                'User-Agent' => 'WP SiteAdvisor Pro/' . WSA_PRO_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'WPScan API returned status code: ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from WPScan API');
        }
        
        return $data;
    }
    
    /**
     * Check if a version is vulnerable
     */
    private function version_is_vulnerable($current_version, $vulnerability) {
        // If no fixed version is specified, assume it affects all versions
        if (!isset($vulnerability['fixed_in']) || empty($vulnerability['fixed_in'])) {
            return true;
        }
        
        $fixed_version = $vulnerability['fixed_in'];
        
        // If current version is less than fixed version, it's vulnerable
        return version_compare($current_version, $fixed_version, '<');
    }
    
    /**
     * Determine vulnerability severity
     */
    private function determine_severity($vulnerability) {
        // Check for severity indicators in title or description
        $text = strtolower($vulnerability['title'] ?? '');
        
        if (strpos($text, 'rce') !== false || 
            strpos($text, 'remote code execution') !== false ||
            strpos($text, 'sql injection') !== false ||
            strpos($text, 'privilege escalation') !== false) {
            return 'critical';
        }
        
        if (strpos($text, 'xss') !== false ||
            strpos($text, 'cross-site scripting') !== false ||
            strpos($text, 'csrf') !== false ||
            strpos($text, 'authentication bypass') !== false) {
            return 'high';
        }
        
        if (strpos($text, 'information disclosure') !== false ||
            strpos($text, 'directory traversal') !== false) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Get vulnerability summary
     */
    public function get_vulnerability_summary() {
        $scan_results = get_option('wsa_pro_vulnerability_scan', array());
        
        if (empty($scan_results)) {
            return array(
                'total_vulnerabilities' => 0,
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'last_scan' => null,
                'needs_scan' => true
            );
        }
        
        $summary = array(
            'total_vulnerabilities' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'last_scan' => $scan_results['scan_time'],
            'needs_scan' => false
        );
        
        // Count WordPress core vulnerabilities
        if (isset($scan_results['wordpress_core']['vulnerabilities'])) {
            foreach ($scan_results['wordpress_core']['vulnerabilities'] as $vuln) {
                $summary['total_vulnerabilities']++;
                $summary[$vuln['severity']]++;
            }
        }
        
        // Count plugin vulnerabilities
        if (isset($scan_results['plugins']['scanned_plugins'])) {
            foreach ($scan_results['plugins']['scanned_plugins'] as $plugin) {
                foreach ($plugin['vulnerabilities'] as $vuln) {
                    $summary['total_vulnerabilities']++;
                    $summary[$vuln['severity']]++;
                }
            }
        }
        
        // Count theme vulnerabilities
        if (isset($scan_results['themes']['scanned_themes'])) {
            foreach ($scan_results['themes']['scanned_themes'] as $theme) {
                foreach ($theme['vulnerabilities'] as $vuln) {
                    $summary['total_vulnerabilities']++;
                    $summary[$vuln['severity']]++;
                }
            }
        }
        
        // Check if scan is older than 24 hours
        if ($scan_results['scan_time'] < (current_time('timestamp') - DAY_IN_SECONDS)) {
            $summary['needs_scan'] = true;
        }
        
        return $summary;
    }
    
    /**
     * Get detailed vulnerability report
     */
    public function get_detailed_report() {
        return get_option('wsa_pro_vulnerability_scan', array());
    }
    
    /**
     * Check and notify about critical vulnerabilities
     */
    private function check_and_notify_critical_vulnerabilities($results) {
        $critical_vulns = array();
        
        // Check WordPress core
        if (isset($results['wordpress_core']['vulnerabilities'])) {
            foreach ($results['wordpress_core']['vulnerabilities'] as $vuln) {
                if ($vuln['severity'] === 'critical') {
                    $critical_vulns[] = array(
                        'type' => 'WordPress Core',
                        'name' => 'WordPress ' . $results['wordpress_core']['version'],
                        'vulnerability' => $vuln
                    );
                }
            }
        }
        
        // Check plugins
        if (isset($results['plugins']['scanned_plugins'])) {
            foreach ($results['plugins']['scanned_plugins'] as $plugin) {
                foreach ($plugin['vulnerabilities'] as $vuln) {
                    if ($vuln['severity'] === 'critical') {
                        $critical_vulns[] = array(
                            'type' => 'Plugin',
                            'name' => $plugin['name'],
                            'vulnerability' => $vuln
                        );
                    }
                }
            }
        }
        
        // Check themes
        if (isset($results['themes']['scanned_themes'])) {
            foreach ($results['themes']['scanned_themes'] as $theme) {
                foreach ($theme['vulnerabilities'] as $vuln) {
                    if ($vuln['severity'] === 'critical') {
                        $critical_vulns[] = array(
                            'type' => 'Theme',
                            'name' => $theme['name'],
                            'vulnerability' => $vuln
                        );
                    }
                }
            }
        }
        
        if (!empty($critical_vulns)) {
            $this->send_vulnerability_notification($critical_vulns);
        }
    }
    
    /**
     * Send vulnerability notification
     */
    private function send_vulnerability_notification($critical_vulnerabilities) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] CRITICAL Security Vulnerabilities Detected', $site_name);
        
        $message = "CRITICAL security vulnerabilities have been detected on your WordPress site.\n\n";
        $message .= "Please take immediate action to secure your website:\n\n";
        
        foreach ($critical_vulnerabilities as $vuln_data) {
            $message .= "• {$vuln_data['type']}: {$vuln_data['name']}\n";
            $message .= "  Vulnerability: {$vuln_data['vulnerability']['title']}\n";
            
            if ($vuln_data['vulnerability']['fixed_in']) {
                $message .= "  Fixed in version: {$vuln_data['vulnerability']['fixed_in']}\n";
            }
            
            $message .= "\n";
        }
        
        $message .= "View detailed vulnerability report: " . admin_url('admin.php?page=wp-site-advisory&tab=vulnerabilities');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Show admin notices for vulnerabilities
     */
    public function show_vulnerability_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $summary = $this->get_vulnerability_summary();
        
        if ($summary['critical'] > 0 || $summary['high'] > 0) {
            $class = 'notice notice-error is-dismissible';
            $message = sprintf(
                'WP SiteAdvisor Pro detected %d critical and %d high-severity vulnerabilities. <a href="%s">View details</a>',
                $summary['critical'],
                $summary['high'],
                admin_url('admin.php?page=wp-site-advisory&tab=vulnerabilities')
            );
            
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        }
    }
    
    /**
     * AJAX handler for vulnerability scan
     */
    public function ajax_scan_vulnerabilities() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (empty($this->api_token)) {
            wp_send_json_error('WPScan API token not configured. Please configure it in Pro Settings.');
        }
        
        $results = $this->run_vulnerability_scan();
        
        wp_send_json_success(array(
            'summary' => $this->get_vulnerability_summary(),
            'api_requests_used' => $results['api_requests_used']
        ));
    }
    
    /**
     * AJAX handler for vulnerability report
     */
    public function ajax_get_vulnerability_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $report = $this->get_detailed_report();
        $summary = $this->get_vulnerability_summary();
        
        wp_send_json_success(array(
            'report' => $report,
            'summary' => $summary
        ));
    }
}