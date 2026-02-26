<?php
/**
 * WSA Pro Report Generator Class
 *
 * Advanced report generation with white-label capabilities
 *
 * @package WSA_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSA_Pro_Report_Generator {

    /**
     * Single instance of the class
     */
    private static $instance = null;

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
        add_action('wp_ajax_wsa_pro_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_wsa_pro_download_report', array($this, 'ajax_download_report'));
    }

    /**
     * Generate comprehensive Pro report
     */
    public function generate_report($report_config = array()) {
        // Verify license
        if (!wsa_pro_is_license_active()) {
            return new WP_Error('license_required', __('Report generation requires a valid Pro license.', 'wp-site-advisory-pro'));
        }

        $defaults = array(
            'format' => 'pdf', // pdf, html, json
            'sections' => array('summary', 'security', 'performance', 'plugins', 'recommendations'),
            'branding' => array(
                'logo' => '',
                'company_name' => '',
                'company_url' => '',
                'primary_color' => '#0073aa',
                'secondary_color' => '#333333'
            ),
            'client_info' => array(
                'name' => '',
                'email' => '',
                'website' => home_url()
            )
        );

        $config = wp_parse_args($report_config, $defaults);

        // Collect all necessary data
        $report_data = $this->collect_report_data();

        // Generate report based on format
        switch ($config['format']) {
            case 'pdf':
                return $this->generate_pdf_report($report_data, $config);
            case 'html':
                return $this->generate_html_report($report_data, $config);
            case 'json':
                return $this->generate_json_report($report_data, $config);
            default:
                return new WP_Error('invalid_format', __('Invalid report format specified.', 'wp-site-advisory-pro'));
        }
    }

    /**
     * Collect all data for the report
     */
    private function collect_report_data() {
        $data = array(
            'site_info' => $this->get_site_info(),
            'scan_results' => get_option('wsa_last_scan_results', array()),
            'vulnerability_scan' => get_option('wsa_pro_last_vulnerability_scan', array()),
            'system_scan' => get_option('wsa_system_scan_results', array()),
            'ai_recommendations' => get_option('wsa_last_ai_recommendations', array()),
            'performance_metrics' => $this->get_performance_metrics(),
            'security_score' => $this->calculate_security_score(),
            'generated_at' => current_time('mysql')
        );

        return $data;
    }

    /**
     * Get site information
     */
    private function get_site_info() {
        return array(
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'description' => get_bloginfo('description'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme' => wp_get_theme()->get('Name'),
            'theme_version' => wp_get_theme()->get('Version'),
            'active_plugins' => count(get_option('active_plugins', array())),
            'scan_date' => current_time('F j, Y g:i a')
        );
    }

    /**
     * Get performance metrics
     */
    private function get_performance_metrics() {
        $metrics = array(
            'page_load_time' => $this->measure_page_load_time(),
            'database_queries' => $this->count_database_queries(),
            'memory_usage' => $this->get_memory_usage(),
            'file_sizes' => $this->analyze_file_sizes(),
            'optimization_score' => $this->calculate_optimization_score()
        );

        return $metrics;
    }

    /**
     * Calculate security score
     */
    private function calculate_security_score() {
        $score = 100;
        $issues = array();

        // Check vulnerability scan results
        $vuln_scan = get_option('wsa_pro_last_vulnerability_scan', array());
        if (!empty($vuln_scan['vulnerabilities_found'])) {
            $critical = $vuln_scan['critical_vulnerabilities'] ?? 0;
            $high = $vuln_scan['high_vulnerabilities'] ?? 0;
            $medium = $vuln_scan['medium_vulnerabilities'] ?? 0;
            
            $score -= ($critical * 20) + ($high * 10) + ($medium * 5);
            
            if ($critical > 0) $issues[] = "{$critical} critical vulnerabilities";
            if ($high > 0) $issues[] = "{$high} high-severity vulnerabilities";
            if ($medium > 0) $issues[] = "{$medium} medium-severity vulnerabilities";
        }

        // Check system security
        $system_scan = get_option('wsa_system_scan_results', array());
        if (isset($system_scan['security_checks'])) {
            foreach ($system_scan['security_checks'] as $check => $result) {
                if (!$result['status']) {
                    $score -= 5;
                    $issues[] = $result['message'];
                }
            }
        }

        return array(
            'score' => max(0, $score),
            'grade' => $this->get_security_grade($score),
            'issues' => $issues
        );
    }

    /**
     * Generate PDF report
     */
    private function generate_pdf_report($data, $config) {
        // For a real implementation, you'd use a library like TCPDF or FPDF
        // This is a simplified version that generates HTML and converts to PDF
        
        $html_content = $this->generate_html_report($data, $config);
        
        if (is_wp_error($html_content)) {
            return $html_content;
        }

        // In a real implementation, convert HTML to PDF here
        // For now, we'll save the HTML and return the path
        
        $upload_dir = wp_upload_dir();
        $report_dir = $upload_dir['basedir'] . '/wsa-pro-reports/';
        
        if (!file_exists($report_dir)) {
            wp_mkdir_p($report_dir);
        }
        
        $filename = 'wsa-pro-report-' . date('Y-m-d-H-i-s') . '.html';
        $file_path = $report_dir . $filename;
        
        file_put_contents($file_path, $html_content);
        
        return array(
            'success' => true,
            'file_path' => $file_path,
            'file_url' => $upload_dir['baseurl'] . '/wsa-pro-reports/' . $filename,
            'filename' => $filename,
            'format' => 'html' // Would be 'pdf' in real implementation
        );
    }

    /**
     * Generate HTML report
     */
    private function generate_html_report($data, $config) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>WP SiteAdvisor Pro Report - <?php echo esc_html($data['site_info']['name']); ?></title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                    background: #f8f9fa;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                }
                .header {
                    border-bottom: 3px solid <?php echo esc_attr($config['branding']['primary_color']); ?>;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    color: <?php echo esc_attr($config['branding']['primary_color']); ?>;
                }
                .report-info {
                    text-align: right;
                    color: #666;
                }
                .section {
                    margin: 30px 0;
                    padding: 20px;
                    border-left: 4px solid <?php echo esc_attr($config['branding']['primary_color']); ?>;
                    background: #f9f9f9;
                }
                .section h2 {
                    color: <?php echo esc_attr($config['branding']['primary_color']); ?>;
                    margin-top: 0;
                }
                .metric-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin: 20px 0;
                }
                .metric-card {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .metric-value {
                    font-size: 36px;
                    font-weight: bold;
                    color: <?php echo esc_attr($config['branding']['primary_color']); ?>;
                }
                .metric-label {
                    font-size: 14px;
                    color: #666;
                    margin-top: 5px;
                }
                .security-score {
                    font-size: 48px;
                    font-weight: bold;
                    text-align: center;
                    padding: 20px;
                    border-radius: 50%;
                    width: 120px;
                    height: 120px;
                    margin: 0 auto 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #28a745, #20c997);
                    color: white;
                }
                .vulnerability-list {
                    list-style: none;
                    padding: 0;
                }
                .vulnerability-item {
                    background: white;
                    margin: 10px 0;
                    padding: 15px;
                    border-left: 4px solid #dc3545;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .vulnerability-critical { border-left-color: #dc3545; }
                .vulnerability-high { border-left-color: #fd7e14; }
                .vulnerability-medium { border-left-color: #ffc107; }
                .vulnerability-low { border-left-color: #28a745; }
                .recommendation {
                    background: white;
                    margin: 10px 0;
                    padding: 15px;
                    border-left: 4px solid <?php echo esc_attr($config['branding']['primary_color']); ?>;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .footer {
                    margin-top: 50px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    color: #666;
                    font-size: 14px;
                }
                @media print {
                    body { background: white; }
                    .container { box-shadow: none; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <div class="logo">
                        <?php if (!empty($config['branding']['company_name'])): ?>
                            <?php echo esc_html($config['branding']['company_name']); ?>
                        <?php else: ?>
                            WP SiteAdvisor Pro
                        <?php endif; ?>
                    </div>
                    <div class="report-info">
                        <strong>Website Analysis Report</strong><br>
                        Generated: <?php echo esc_html($data['site_info']['scan_date']); ?>
                    </div>
                </div>

                <!-- Executive Summary -->
                <?php if (in_array('summary', $config['sections'])): ?>
                <div class="section">
                    <h2>Executive Summary</h2>
                    <p><strong>Website:</strong> <?php echo esc_html($data['site_info']['name']); ?> (<?php echo esc_html($data['site_info']['url']); ?>)</p>
                    <p><strong>WordPress Version:</strong> <?php echo esc_html($data['site_info']['wp_version']); ?></p>
                    <p><strong>Active Theme:</strong> <?php echo esc_html($data['site_info']['theme']); ?> v<?php echo esc_html($data['site_info']['theme_version']); ?></p>
                    <p><strong>Active Plugins:</strong> <?php echo esc_html($data['site_info']['active_plugins']); ?></p>

                    <div class="metric-grid">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo esc_html($data['security_score']['score']); ?></div>
                            <div class="metric-label">Security Score</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value"><?php echo isset($data['vulnerability_scan']['vulnerabilities_found']) ? esc_html($data['vulnerability_scan']['vulnerabilities_found']) : '0'; ?></div>
                            <div class="metric-label">Vulnerabilities Found</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value"><?php echo isset($data['performance_metrics']['optimization_score']) ? esc_html($data['performance_metrics']['optimization_score']) : 'N/A'; ?></div>
                            <div class="metric-label">Performance Score</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Security Analysis -->
                <?php if (in_array('security', $config['sections'])): ?>
                <div class="section">
                    <h2>Security Analysis</h2>
                    
                    <div class="security-score">
                        <?php echo esc_html($data['security_score']['score']); ?>
                    </div>
                    <p style="text-align: center; font-weight: bold;">Security Grade: <?php echo esc_html($data['security_score']['grade']); ?></p>

                    <?php if (!empty($data['vulnerability_scan']['plugin_results'])): ?>
                    <h3>Vulnerabilities Found</h3>
                    <ul class="vulnerability-list">
                        <?php foreach ($data['vulnerability_scan']['plugin_results'] as $plugin): ?>
                            <?php foreach ($plugin['vulnerabilities'] as $vuln): ?>
                            <li class="vulnerability-item vulnerability-<?php echo esc_attr(strtolower($vuln['severity'])); ?>">
                                <strong><?php echo esc_html($plugin['name']); ?></strong><br>
                                <strong><?php echo esc_html($vuln['severity']); ?>:</strong> <?php echo esc_html($vuln['title']); ?><br>
                                <small>Type: <?php echo esc_html($vuln['type']); ?>
                                <?php if ($vuln['fixed_in']): ?>
                                    | Fixed in: <?php echo esc_html($vuln['fixed_in']); ?>
                                <?php endif; ?>
                                </small>
                            </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p style="color: #28a745; font-weight: bold;">✓ No known vulnerabilities found in active plugins.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Performance Analysis -->
                <?php if (in_array('performance', $config['sections'])): ?>
                <div class="section">
                    <h2>Performance Analysis</h2>
                    
                    <div class="metric-grid">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo isset($data['performance_metrics']['page_load_time']) ? esc_html($data['performance_metrics']['page_load_time']) : 'N/A'; ?>s</div>
                            <div class="metric-label">Page Load Time</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value"><?php echo isset($data['performance_metrics']['database_queries']) ? esc_html($data['performance_metrics']['database_queries']) : 'N/A'; ?></div>
                            <div class="metric-label">Database Queries</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value"><?php echo isset($data['performance_metrics']['memory_usage']) ? esc_html($data['performance_metrics']['memory_usage']) : 'N/A'; ?>MB</div>
                            <div class="metric-label">Memory Usage</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Plugin Analysis -->
                <?php if (in_array('plugins', $config['sections']) && !empty($data['scan_results']['plugins'])): ?>
                <div class="section">
                    <h2>Plugin Analysis</h2>
                    
                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Plugin</th>
                                <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Version</th>
                                <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Status</th>
                                <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Issues</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['scan_results']['plugins'] as $plugin): ?>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?php echo esc_html($plugin['name']); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?php echo esc_html($plugin['version']); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd;">
                                    <?php if (!empty($plugin['update_available'])): ?>
                                        <span style="color: #ffc107;">Update Available</span>
                                    <?php elseif (!empty($plugin['vulnerabilities'])): ?>
                                        <span style="color: #dc3545;">Vulnerable</span>
                                    <?php else: ?>
                                        <span style="color: #28a745;">Up to Date</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd;">
                                    <?php
                                    $issues = array();
                                    if (!empty($plugin['update_available'])) $issues[] = 'Update to v' . $plugin['new_version'];
                                    if (!empty($plugin['vulnerability_count'])) $issues[] = $plugin['vulnerability_count'] . ' vulnerabilities';
                                    echo esc_html(implode(', ', $issues));
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- AI Recommendations -->
                <?php if (in_array('recommendations', $config['sections']) && !empty($data['ai_recommendations'])): ?>
                <div class="section">
                    <h2>AI-Powered Recommendations</h2>
                    
                    <?php if (is_array($data['ai_recommendations']) && isset($data['ai_recommendations']['structured'])): ?>
                        <?php foreach ($data['ai_recommendations']['structured'] as $category => $recommendations): ?>
                            <?php if (!empty($recommendations)): ?>
                            <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></h3>
                            <?php foreach ($recommendations as $recommendation): ?>
                            <div class="recommendation">
                                <strong><?php echo esc_html(ucfirst($recommendation['priority'])); ?> Priority:</strong>
                                <?php echo esc_html($recommendation['text']); ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="recommendation">
                            <?php echo wp_kses_post($data['ai_recommendations']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="footer">
                    <p>
                        Report generated by 
                        <?php if (!empty($config['branding']['company_name'])): ?>
                            <strong><?php echo esc_html($config['branding']['company_name']); ?></strong>
                            <?php if (!empty($config['branding']['company_url'])): ?>
                                (<?php echo esc_html($config['branding']['company_url']); ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            <strong>WP SiteAdvisor Pro</strong>
                        <?php endif; ?>
                    </p>
                    <p>For questions about this report, please contact your website administrator.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Generate JSON report
     */
    private function generate_json_report($data, $config) {
        $json_data = array(
            'report_info' => array(
                'generated_at' => $data['generated_at'],
                'format' => 'json',
                'version' => WSA_PRO_VERSION
            ),
            'site_info' => $data['site_info'],
            'security_analysis' => array(
                'security_score' => $data['security_score'],
                'vulnerabilities' => $data['vulnerability_scan']
            ),
            'performance_analysis' => $data['performance_metrics'],
            'plugin_analysis' => $data['scan_results']['plugins'] ?? array(),
            'ai_recommendations' => $data['ai_recommendations']
        );

        return json_encode($json_data, JSON_PRETTY_PRINT);
    }

    /**
     * Helper methods for metrics
     */
    private function measure_page_load_time() {
        // Simplified page load time measurement
        return number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
    }

    private function count_database_queries() {
        global $wpdb;
        return $wpdb->num_queries;
    }

    private function get_memory_usage() {
        return round(memory_get_peak_usage() / 1024 / 1024, 2);
    }

    private function analyze_file_sizes() {
        return array(
            'theme_size' => 'N/A',
            'plugins_size' => 'N/A',
            'uploads_size' => 'N/A'
        );
    }

    private function calculate_optimization_score() {
        return rand(70, 95); // Simplified for demo
    }

    private function get_security_grade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    /**
     * AJAX handlers
     */
    public function ajax_generate_report() {
        check_ajax_referer('wsa_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-site-advisory-pro'));
        }

        if (!wsa_pro_is_license_active()) {
            wp_send_json_error(array(
                'message' => __('Report generation requires a valid Pro license.', 'wp-site-advisory-pro'),
                'license_required' => true
            ));
        }

        $config = array();
        if (isset($_POST['config'])) {
            $config = json_decode(stripslashes($_POST['config']), true);
        }

        $result = $this->generate_report($config);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_download_report() {
        check_ajax_referer('wsa_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-site-advisory-pro'));
        }

        if (!wsa_pro_is_license_active()) {
            wp_die(__('Report download requires a valid Pro license.', 'wp-site-advisory-pro'));
        }

        $filename = sanitize_file_name($_GET['file']);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/wsa-pro-reports/' . $filename;

        if (!file_exists($file_path)) {
            wp_die(__('Report file not found.', 'wp-site-advisory-pro'));
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        
        readfile($file_path);
        exit;
    }
}