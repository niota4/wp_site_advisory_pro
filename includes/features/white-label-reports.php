<?php
/**
 * White-Label Reports
 * 
 * Generates PDF reports with custom branding for client distribution
 * 
 * @package WP_Site_Advisory_Pro
 * @subpackage Features
 */

namespace WSA_Pro\Features;

if (!defined('ABSPATH')) {
    exit;
}

class White_Label_Reports {
    
    private $branding_options;
    
    /**
     * Initialize the white-label reports system
     */
    public function __construct() {
        $this->branding_options = get_option('wsa_pro_white_label', array());
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule weekly/monthly reports
        add_action('wp', array($this, 'schedule_reports'));
        add_action('wsa_send_weekly_report', array($this, 'send_weekly_report'));
        add_action('wsa_send_monthly_report', array($this, 'send_monthly_report'));
        
        // AJAX handlers
        add_action('wp_ajax_wsa_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_wsa_preview_report', array($this, 'ajax_preview_report'));
        add_action('wp_ajax_wsa_send_test_report', array($this, 'ajax_send_test_report'));
        add_action('wp_ajax_wsa_upload_logo', array($this, 'ajax_upload_logo'));
        
        // AI-enhanced reporting AJAX handlers
        add_action('wp_ajax_wsa_generate_ai_report', array($this, 'ajax_generate_ai_report'));
        
        // Admin menu for report settings
        add_action('admin_menu', array($this, 'add_reports_menu'), 20);
    }
    
    /**
     * Schedule report sending
     */
    public function schedule_reports() {
        $report_settings = get_option('wsa_pro_report_settings', array());
        
        // Weekly reports
        if (isset($report_settings['weekly_enabled']) && $report_settings['weekly_enabled'] && 
            !wp_next_scheduled('wsa_send_weekly_report')) {
            
            $send_day = isset($report_settings['weekly_day']) ? (int) $report_settings['weekly_day'] : 1; // Monday
            $send_time = isset($report_settings['weekly_time']) ? $report_settings['weekly_time'] : '09:00';
            
            $next_send = $this->calculate_next_send_time($send_day, $send_time, 'weekly');
            wp_schedule_event($next_send, 'weekly', 'wsa_send_weekly_report');
        }
        
        // Monthly reports
        if (isset($report_settings['monthly_enabled']) && $report_settings['monthly_enabled'] && 
            !wp_next_scheduled('wsa_send_monthly_report')) {
            
            $send_day = isset($report_settings['monthly_day']) ? (int) $report_settings['monthly_day'] : 1;
            $send_time = isset($report_settings['monthly_time']) ? $report_settings['monthly_time'] : '09:00';
            
            $next_send = $this->calculate_next_send_time($send_day, $send_time, 'monthly');
            wp_schedule_event($next_send, 'monthly', 'wsa_send_monthly_report');
        }
    }
    
    /**
     * Calculate next send time for reports
     */
    private function calculate_next_send_time($day, $time, $frequency) {
        $time_parts = explode(':', $time);
        $hour = (int) $time_parts[0];
        $minute = isset($time_parts[1]) ? (int) $time_parts[1] : 0;
        
        if ($frequency === 'weekly') {
            // $day is day of week (1 = Monday, 7 = Sunday)
            $next = strtotime("next " . $this->get_day_name($day) . " {$hour}:{$minute}:00");
        } else {
            // $day is day of month
            $next = strtotime("first day of next month +{$day} days {$hour}:{$minute}:00");
        }
        
        return $next;
    }
    
    /**
     * Get day name from number
     */
    private function get_day_name($day_number) {
        $days = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 
                     5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
        return $days[$day_number] ?? 'Monday';
    }
    
    /**
     * Send weekly report
     */
    public function send_weekly_report() {
        $this->send_scheduled_report('weekly');
    }
    
    /**
     * Send monthly report
     */
    public function send_monthly_report() {
        $this->send_scheduled_report('monthly');
    }
    
    /**
     * Send scheduled report
     */
    private function send_scheduled_report($frequency) {
        $report_settings = get_option('wsa_pro_report_settings', array());
        $recipients = isset($report_settings[$frequency . '_recipients']) ? 
                     $report_settings[$frequency . '_recipients'] : array();
        
        if (empty($recipients)) {
            return;
        }
        
        // Generate report
        $report_data = $this->gather_report_data($frequency);
        $pdf_content = $this->generate_pdf_report($report_data);
        
        if (!$pdf_content) {
            return;
        }
        
        // Send to each recipient
        foreach ($recipients as $recipient) {
            $this->send_report_email($recipient, $pdf_content, $frequency, $report_data);
        }
    }
    
    /**
     * Gather data for report
     */
    private function gather_report_data($period = 'weekly') {
        $data = array(
            'site_info' => array(
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'admin_email' => get_option('admin_email'),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'report_period' => $period,
                'report_date' => current_time('Y-m-d'),
                'report_time' => current_time('H:i:s')
            ),
            'security' => $this->get_security_data(),
            'performance' => $this->get_performance_data(),
            'vulnerabilities' => $this->get_vulnerability_data(),
            'conflicts' => $this->get_conflicts_data(),
            'system_health' => $this->get_system_health_data(),
            'branding' => $this->branding_options
        );
        
        return $data;
    }
    
    /**
     * Get security data for report
     */
    private function get_security_data() {
        $system_scanner = WP_Site_Advisory_System_Scanner::get_instance();
        $scan_results = get_option('wsa_system_scan_results', array());
        
        $security_data = array(
            'overall_score' => 0,
            'checks_passed' => 0,
            'checks_failed' => 0,
            'recommendations' => array()
        );
        
        if (isset($scan_results['security_checks'])) {
            $security_checks = $scan_results['security_checks'];
            $security_data['overall_score'] = $security_checks['security_score'] ?? 0;
            $security_data['checks_passed'] = count($security_checks['checks']) - ($security_checks['issues_found'] ?? 0);
            $security_data['checks_failed'] = $security_checks['issues_found'] ?? 0;
            $security_data['recommendations'] = $security_checks['recommendations'] ?? array();
        }
        
        return $security_data;
    }
    
    /**
     * Get performance data for report
     */
    private function get_performance_data() {
        if (!class_exists('\WSA_Pro\Features\PageSpeed_Analysis')) {
            return array('error' => 'PageSpeed Analysis not available');
        }
        
        $pagespeed = new \WSA_Pro\Features\PageSpeed_Analysis();
        $performance_summary = $pagespeed->get_performance_summary();
        
        return $performance_summary;
    }
    
    /**
     * Get vulnerability data for report
     */
    private function get_vulnerability_data() {
        if (!class_exists('\WSA_Pro\Features\WPScan_Vulnerabilities')) {
            return array('error' => 'WPScan Vulnerabilities not available');
        }
        
        $wpscan = new \WSA_Pro\Features\WPScan_Vulnerabilities();
        $vulnerability_summary = $wpscan->get_vulnerability_summary();
        
        return $vulnerability_summary;
    }
    
    /**
     * Get conflicts data for report
     */
    private function get_conflicts_data() {
        if (!class_exists('\WSA_Pro\Features\AI_Conflict_Detector')) {
            return array('error' => 'AI Conflict Detector not available');
        }
        
        $conflict_detector = new \WSA_Pro\Features\AI_Conflict_Detector();
        $conflicts = $conflict_detector->get_conflicts();
        
        return array(
            'total_conflicts' => count($conflicts),
            'critical_conflicts' => count(array_filter($conflicts, function($c) { 
                return $c['severity'] === 'critical'; 
            })),
            'new_conflicts' => count(array_filter($conflicts, function($c) { 
                return $c['status'] === 'new'; 
            })),
            'recent_conflicts' => array_slice($conflicts, 0, 5)
        );
    }
    
    /**
     * Get system health data
     */
    private function get_system_health_data() {
        $system_scan = get_option('wsa_system_scan_results', array());
        
        $health_data = array(
            'inactive_plugins' => 0,
            'inactive_themes' => 0,
            'performance_score' => 0,
            'last_scan' => null
        );
        
        if (!empty($system_scan)) {
            $health_data['inactive_plugins'] = $system_scan['inactive_plugins']['count'] ?? 0;
            $health_data['inactive_themes'] = $system_scan['inactive_themes']['count'] ?? 0;
            $health_data['last_scan'] = $system_scan['last_scanned'] ?? null;
            
            if (isset($system_scan['performance_checks'])) {
                // Calculate performance score from checks
                $performance_checks = $system_scan['performance_checks']['checks'];
                $passed = count($performance_checks) - ($system_scan['performance_checks']['issues_found'] ?? 0);
                $total = count($performance_checks);
                $health_data['performance_score'] = $total > 0 ? round(($passed / $total) * 100) : 0;
            }
        }
        
        return $health_data;
    }
    
    /**
     * Generate PDF report
     */
    public function generate_pdf_report($data) {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Try to load TCPDF from WordPress or include it
            $tcpdf_path = ABSPATH . 'wp-includes/class-tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once $tcpdf_path;
            } else {
                // Use simple HTML to PDF conversion as fallback
                return $this->generate_html_report($data);
            }
        }
        
        try {
            // Initialize TCPDF
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
            
            // Set document information
            $pdf->SetCreator('WP SiteAdvisor Pro');
            $pdf->SetAuthor($this->get_branding_option('agency_name', 'WP SiteAdvisor Pro'));
            $pdf->SetTitle('Website Analysis Report - ' . $data['site_info']['name']);
            $pdf->SetSubject('WordPress Security and Performance Report');
            
            // Set header and footer
            $this->set_pdf_header_footer($pdf, $data);
            
            // Add page
            $pdf->AddPage();
            
            // Generate content
            $this->add_pdf_cover_page($pdf, $data);
            $pdf->AddPage();
            $this->add_pdf_executive_summary($pdf, $data);
            $pdf->AddPage();
            $this->add_pdf_security_section($pdf, $data);
            $pdf->AddPage();
            $this->add_pdf_performance_section($pdf, $data);
            $pdf->AddPage();
            $this->add_pdf_recommendations($pdf, $data);
            
            // Output PDF
            return $pdf->Output('', 'S'); // Return as string
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Set PDF header and footer
     */
    private function set_pdf_header_footer($pdf, $data) {
        // Custom header
        $pdf->SetHeaderData(
            $this->get_logo_path(), // Logo
            30, // Logo width
            $this->get_branding_option('agency_name', 'WP SiteAdvisor Pro'),
            'Website Analysis Report'
        );
        
        // Header font
        $pdf->setHeaderFont(Array('helvetica', '', 12));
        
        // Footer
        $pdf->setFooterData(
            array(0, 64, 0), // Footer text color
            array(0, 64, 128) // Footer line color
        );
        
        // Footer font
        $pdf->setFooterFont(Array('helvetica', '', 10));
        
        // Margins
        $pdf->SetMargins(20, 30, 20);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Font
        $pdf->SetFont('helvetica', '', 11);
    }
    
    /**
     * Add PDF cover page
     */
    private function add_pdf_cover_page($pdf, $data) {
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->Cell(0, 20, 'Website Analysis Report', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 16);
        $pdf->Ln(10);
        $pdf->Cell(0, 10, $data['site_info']['name'], 0, 1, 'C');
        $pdf->Cell(0, 10, $data['site_info']['url'], 0, 1, 'C');
        
        $pdf->Ln(20);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Report Period: ' . ucfirst($data['site_info']['report_period']), 0, 1, 'C');
        $pdf->Cell(0, 10, 'Generated: ' . $data['site_info']['report_date'], 0, 1, 'C');
        
        $pdf->Ln(30);
        
        // Quick stats
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Quick Overview', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, 'Security Score: ' . ($data['security']['overall_score'] ?? 'N/A') . '%', 0, 1, 'L');
        $pdf->Cell(0, 8, 'Performance Status: ' . ($data['performance']['status'] ?? 'Unknown'), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Vulnerabilities Found: ' . ($data['vulnerabilities']['total_vulnerabilities'] ?? 0), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Conflicts Detected: ' . ($data['conflicts']['total_conflicts'] ?? 0), 0, 1, 'L');
    }
    
    /**
     * Add executive summary
     */
    private function add_pdf_executive_summary($pdf, $data) {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, 'Executive Summary', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Ln(5);
        
        $summary = $this->generate_executive_summary($data);
        $pdf->MultiCell(0, 6, $summary, 0, 'J');
    }
    
    /**
     * Generate executive summary text
     */
    private function generate_executive_summary($data) {
        $summary = "This report provides a comprehensive analysis of {$data['site_info']['name']} ";
        $summary .= "covering security, performance, vulnerabilities, and overall system health.\n\n";
        
        // Security summary
        $security_score = $data['security']['overall_score'] ?? 0;
        if ($security_score >= 80) {
            $summary .= "Security: Your website shows strong security practices with a score of {$security_score}%. ";
        } elseif ($security_score >= 60) {
            $summary .= "Security: Your website has moderate security with a score of {$security_score}%. Some improvements are recommended. ";
        } else {
            $summary .= "Security: Your website requires immediate attention with a low security score of {$security_score}%. ";
        }
        
        // Vulnerability summary
        $total_vulns = $data['vulnerabilities']['total_vulnerabilities'] ?? 0;
        $critical_vulns = $data['vulnerabilities']['critical'] ?? 0;
        
        if ($total_vulns === 0) {
            $summary .= "No vulnerabilities were detected in your WordPress installation, plugins, or themes. ";
        } else {
            $summary .= "{$total_vulns} vulnerabilities were detected, including {$critical_vulns} critical issues that require immediate attention. ";
        }
        
        // Performance summary
        $performance_status = $data['performance']['status'] ?? 'unknown';
        switch ($performance_status) {
            case 'excellent':
                $summary .= "Performance is excellent with fast loading times and good user experience. ";
                break;
            case 'good':
                $summary .= "Performance is good but has room for improvement. ";
                break;
            case 'needs_improvement':
                $summary .= "Performance needs improvement to provide better user experience. ";
                break;
            case 'poor':
                $summary .= "Performance is poor and significantly impacts user experience. Immediate optimization is recommended. ";
                break;
            default:
                $summary .= "Performance data is not available. ";
        }
        
        $summary .= "\n\nDetailed analysis and recommendations are provided in the following sections.";
        
        return $summary;
    }
    
    /**
     * Add security section to PDF
     */
    private function add_pdf_security_section($pdf, $data) {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, 'Security Analysis', 0, 1, 'L');
        
        $security = $data['security'];
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Overall Security Score: ' . ($security['overall_score'] ?? 'N/A') . '%', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, 'Checks Passed: ' . ($security['checks_passed'] ?? 0), 0, 1, 'L');
        $pdf->Cell(0, 6, 'Issues Found: ' . ($security['checks_failed'] ?? 0), 0, 1, 'L');
        
        $pdf->Ln(5);
        
        if (!empty($security['recommendations'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Security Recommendations:', 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 10);
            foreach ($security['recommendations'] as $recommendation) {
                $pdf->Cell(5, 6, '•', 0, 0, 'L');
                $pdf->MultiCell(0, 6, $recommendation, 0, 'L');
            }
        }
    }
    
    /**
     * Add performance section to PDF
     */
    private function add_pdf_performance_section($pdf, $data) {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, 'Performance Analysis', 0, 1, 'L');
        
        $performance = $data['performance'];
        
        if (isset($performance['error'])) {
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 8, 'Performance data not available: ' . $performance['error'], 0, 1, 'L');
            return;
        }
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Performance Status: ' . ucfirst($performance['status'] ?? 'Unknown'), 0, 1, 'L');
        
        if (isset($performance['scores'])) {
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'Performance Scores:', 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 10);
            
            if (isset($performance['scores']['desktop']['performance'])) {
                $desktop_score = $performance['scores']['desktop']['performance']['score'];
                $pdf->Cell(0, 6, 'Desktop Performance: ' . $desktop_score . '%', 0, 1, 'L');
            }
            
            if (isset($performance['scores']['mobile']['performance'])) {
                $mobile_score = $performance['scores']['mobile']['performance']['score'];
                $pdf->Cell(0, 6, 'Mobile Performance: ' . $mobile_score . '%', 0, 1, 'L');
            }
        }
    }
    
    /**
     * Add recommendations section
     */
    private function add_pdf_recommendations($pdf, $data) {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, 'Recommendations', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 11);
        
        $recommendations = $this->generate_prioritized_recommendations($data);
        
        foreach ($recommendations as $category => $recs) {
            if (empty($recs)) continue;
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, ucfirst(str_replace('_', ' ', $category)), 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 10);
            
            foreach ($recs as $rec) {
                $pdf->Cell(5, 6, '•', 0, 0, 'L');
                $pdf->MultiCell(0, 6, $rec, 0, 'L');
            }
            
            $pdf->Ln(3);
        }
    }
    
    /**
     * Generate prioritized recommendations
     */
    private function generate_prioritized_recommendations($data) {
        $recommendations = array(
            'critical_security' => array(),
            'performance_improvements' => array(),
            'maintenance' => array()
        );
        
        // Critical security recommendations
        if (($data['vulnerabilities']['critical'] ?? 0) > 0) {
            $recommendations['critical_security'][] = 'Update plugins and themes with critical vulnerabilities immediately';
        }
        
        if (($data['security']['overall_score'] ?? 100) < 70) {
            $recommendations['critical_security'][] = 'Implement additional security measures to improve overall security score';
        }
        
        // Performance recommendations
        $performance_status = $data['performance']['status'] ?? 'unknown';
        if (in_array($performance_status, array('poor', 'needs_improvement'))) {
            $recommendations['performance_improvements'][] = 'Optimize website performance to improve user experience and SEO rankings';
            $recommendations['performance_improvements'][] = 'Consider implementing caching and image optimization';
        }
        
        // Maintenance recommendations
        if (($data['system_health']['inactive_plugins'] ?? 0) > 0) {
            $recommendations['maintenance'][] = 'Remove unused plugins to reduce security attack surface';
        }
        
        if (($data['conflicts']['total_conflicts'] ?? 0) > 0) {
            $recommendations['maintenance'][] = 'Resolve detected plugin and theme conflicts';
        }
        
        return $recommendations;
    }
    
    /**
     * Generate HTML report as fallback
     */
    private function generate_html_report($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Website Analysis Report - <?php echo esc_html($data['site_info']['name']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .section { margin: 20px 0; }
                .score { font-size: 24px; font-weight: bold; color: #2c5aa0; }
                .critical { color: #d63384; }
                .good { color: #198754; }
                ul { padding-left: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Website Analysis Report</h1>
                <h2><?php echo esc_html($data['site_info']['name']); ?></h2>
                <p><?php echo esc_html($data['site_info']['url']); ?></p>
                <p>Generated: <?php echo esc_html($data['site_info']['report_date']); ?></p>
            </div>
            
            <div class="section">
                <h2>Executive Summary</h2>
                <p><?php echo esc_html($this->generate_executive_summary($data)); ?></p>
            </div>
            
            <div class="section">
                <h2>Security Analysis</h2>
                <p>Overall Score: <span class="score"><?php echo esc_html($data['security']['overall_score'] ?? 'N/A'); ?>%</span></p>
                <p>Checks Passed: <?php echo esc_html($data['security']['checks_passed'] ?? 0); ?></p>
                <p>Issues Found: <?php echo esc_html($data['security']['checks_failed'] ?? 0); ?></p>
            </div>
            
            <div class="section">
                <h2>Vulnerabilities</h2>
                <p>Total Vulnerabilities: <?php echo esc_html($data['vulnerabilities']['total_vulnerabilities'] ?? 0); ?></p>
                <p>Critical: <?php echo esc_html($data['vulnerabilities']['critical'] ?? 0); ?></p>
                <p>High: <?php echo esc_html($data['vulnerabilities']['high'] ?? 0); ?></p>
            </div>
            
            <div class="section">
                <h2>Performance</h2>
                <p>Status: <?php echo esc_html(ucfirst($data['performance']['status'] ?? 'Unknown')); ?></p>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Send report via email
     */
    private function send_report_email($recipient, $pdf_content, $frequency, $data) {
        $site_name = $data['site_info']['name'];
        $agency_name = $this->get_branding_option('agency_name', 'WP SiteAdvisor Pro');
        
        $subject = sprintf('%s - %s Website Analysis Report for %s', 
                          $agency_name, 
                          ucfirst($frequency), 
                          $site_name);
        
        $message = $this->get_email_template($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $agency_name . ' <' . get_option('admin_email') . '>'
        );
        
        // Prepare attachment
        $attachments = array();
        if ($pdf_content) {
            $upload_dir = wp_upload_dir();
            $temp_file = $upload_dir['path'] . '/wsa-report-' . time() . '.pdf';
            file_put_contents($temp_file, $pdf_content);
            $attachments[] = $temp_file;
        }
        
        $sent = wp_mail($recipient, $subject, $message, $headers, $attachments);
        
        // Clean up temporary file
        if (!empty($attachments)) {
            unlink($attachments[0]);
        }
        
        if (!$sent) {
        }
        
        return $sent;
    }
    
    /**
     * Get email template
     */
    private function get_email_template($data) {
        $agency_name = $this->get_branding_option('agency_name', 'WP SiteAdvisor Pro');
        $site_name = $data['site_info']['name'];
        
        ob_start();
        ?>
        <html>
        <body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;">
            <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;">
                <h1 style="color: #2c5aa0; margin-bottom: 20px;"><?php echo esc_html($agency_name); ?></h1>
                
                <h2 style="color: #333;">Website Analysis Report</h2>
                <p style="font-size: 16px; color: #666;">Your <?php echo esc_html($data['site_info']['report_period']); ?> website analysis report for <strong><?php echo esc_html($site_name); ?></strong> is ready.</p>
                
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #2c5aa0;">Report Summary</h3>
                    <ul style="color: #333; line-height: 1.6;">
                        <li>Security Score: <strong><?php echo esc_html($data['security']['overall_score'] ?? 'N/A'); ?>%</strong></li>
                        <li>Vulnerabilities Found: <strong><?php echo esc_html($data['vulnerabilities']['total_vulnerabilities'] ?? 0); ?></strong></li>
                        <li>Performance Status: <strong><?php echo esc_html(ucfirst($data['performance']['status'] ?? 'Unknown')); ?></strong></li>
                        <li>Conflicts Detected: <strong><?php echo esc_html($data['conflicts']['total_conflicts'] ?? 0); ?></strong></li>
                    </ul>
                </div>
                
                <p style="color: #666;">Please find the detailed PDF report attached to this email.</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <p style="color: #888; font-size: 14px;">
                        This report was generated by <?php echo esc_html($agency_name); ?> using WP SiteAdvisor Pro.<br>
                        If you have any questions, please contact us.
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get branding option
     */
    private function get_branding_option($key, $default = '') {
        return isset($this->branding_options[$key]) ? $this->branding_options[$key] : $default;
    }
    
    /**
     * Get logo path
     */
    private function get_logo_path() {
        $logo_id = $this->get_branding_option('logo_id');
        
        if ($logo_id) {
            $logo_path = get_attached_file($logo_id);
            if ($logo_path && file_exists($logo_path)) {
                return $logo_path;
            }
        }
        
        // Return default logo or empty string
        return '';
    }
    
    /**
     * Add reports admin menu
     */
    public function add_reports_menu() {
        add_submenu_page(
            'wp-site-advisory',
            'White-Label Reports',
            'Reports',
            'manage_options',
            'wsa-reports',
            array($this, 'render_reports_page')
        );
    }
    
    /**
     * Render reports admin page
     */
    public function render_reports_page() {
        ?>
        <div class="wrap">
            <h1>White-Label Reports</h1>
            <div id="wsa-reports-content">
                <!-- Reports interface will be loaded here via JavaScript -->
                <div class="wsa-loading">Loading reports interface...</div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for generating reports
     */
    public function ajax_generate_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'weekly');
        
        $data = $this->gather_report_data($period);
        $pdf_content = $this->generate_pdf_report($data);
        
        if (!$pdf_content) {
            wp_send_json_error('Failed to generate PDF report');
        }
        
        // Save to uploads directory
        $upload_dir = wp_upload_dir();
        $filename = 'wsa-report-' . $data['site_info']['name'] . '-' . date('Y-m-d') . '.pdf';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        file_put_contents($filepath, $pdf_content);
        
        wp_send_json_success(array(
            'download_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ));
    }
    
    /**
     * AJAX handler for report preview
     */
    public function ajax_preview_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'weekly');
        $data = $this->gather_report_data($period);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler for sending test report
     */
    public function ajax_send_test_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        $data = $this->gather_report_data('weekly');
        $pdf_content = $this->generate_pdf_report($data);
        
        if (!$pdf_content) {
            wp_send_json_error('Failed to generate PDF report');
        }
        
        $sent = $this->send_report_email($email, $pdf_content, 'test', $data);
        
        if ($sent) {
            wp_send_json_success('Test report sent successfully');
        } else {
            wp_send_json_error('Failed to send test report');
        }
    }
    
    /**
     * AJAX handler for logo upload
     */
    public function ajax_upload_logo() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wsa_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!isset($_FILES['logo'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['logo'];
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload a JPG, PNG, or GIF image.');
        }
        
        // Upload file
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $file['type'],
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error('Failed to create attachment');
        }
        
        // Update branding options
        $branding_options = get_option('wsa_pro_white_label', array());
        $branding_options['logo_id'] = $attachment_id;
        $branding_options['logo_url'] = $upload['url'];
        update_option('wsa_pro_white_label', $branding_options);
        
        wp_send_json_success(array(
            'logo_id' => $attachment_id,
            'logo_url' => $upload['url']
        ));
    }
    
    /**
     * ===========================
     * AI-ENHANCED REPORTING METHODS
     * ===========================
     */
    
    /**
     * Generate AI-powered executive summary
     */
    public function generate_ai_executive_summary($report_data) {
        $openai_key = $this->get_openai_api_key();
        
        if (!$openai_key) {
            return array('error' => 'OpenAI API key not configured');
        }
        
        // Prepare context for AI analysis
        $context = $this->prepare_report_context($report_data);
        $prompt = $this->build_executive_summary_prompt($context);
        
        $ai_response = $this->query_openai($prompt, $openai_key);
        
        if ($ai_response) {
            return $this->parse_executive_summary($ai_response);
        }
        
        return array('error' => 'Failed to generate AI summary');
    }
    
    /**
     * Get AI-powered trend analysis
     */
    public function get_ai_trend_analysis($historical_data) {
        $openai_key = $this->get_openai_api_key();
        
        if (!$openai_key) {
            return $this->fallback_trend_analysis($historical_data);
        }
        
        $trend_context = $this->prepare_trend_context($historical_data);
        $prompt = $this->build_trend_analysis_prompt($trend_context);
        
        $ai_response = $this->query_openai($prompt, $openai_key);
        
        if ($ai_response) {
            return $this->parse_trend_analysis($ai_response);
        }
        
        return $this->fallback_trend_analysis($historical_data);
    }
    
    /**
     * Generate predictive recommendations
     */
    public function generate_predictive_recommendations($report_data) {
        $openai_key = $this->get_openai_api_key();
        
        if (!$openai_key) {
            return $this->get_basic_recommendations($report_data);
        }
        
        $context = $this->prepare_prediction_context($report_data);
        $prompt = $this->build_prediction_prompt($context);
        
        $ai_response = $this->query_openai($prompt, $openai_key);
        
        if ($ai_response) {
            return $this->parse_predictive_recommendations($ai_response);
        }
        
        return $this->get_basic_recommendations($report_data);
    }
    
    /**
     * Prepare report context for AI analysis
     */
    private function prepare_report_context($report_data) {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        
        $context = array(
            'site_info' => array(
                'name' => $site_name,
                'url' => $site_url,
                'report_period' => $report_data['period'] ?? '30 days',
                'generated_date' => current_time('Y-m-d')
            ),
            'performance_metrics' => array(
                'page_speed_score' => $report_data['performance']['pagespeed_score'] ?? 'N/A',
                'load_time' => $report_data['performance']['avg_load_time'] ?? 'N/A',
                'uptime_percentage' => $report_data['uptime']['percentage'] ?? 'N/A',
                'total_visitors' => $report_data['traffic']['total_visitors'] ?? 'N/A',
                'bounce_rate' => $report_data['traffic']['bounce_rate'] ?? 'N/A'
            ),
            'security_status' => array(
                'vulnerabilities_found' => $report_data['security']['vulnerabilities'] ?? 0,
                'malware_scans_clean' => $report_data['security']['malware_clean'] ?? true,
                'ssl_status' => $report_data['security']['ssl_active'] ?? true,
                'plugin_updates_needed' => $report_data['security']['outdated_plugins'] ?? 0
            ),
            'seo_metrics' => array(
                'indexed_pages' => $report_data['seo']['indexed_pages'] ?? 'N/A',
                'broken_links' => $report_data['seo']['broken_links'] ?? 0,
                'meta_issues' => $report_data['seo']['meta_issues'] ?? 0,
                'content_quality_score' => $report_data['seo']['content_quality'] ?? 'N/A'
            )
        );
        
        return $context;
    }
    
    /**
     * Build executive summary prompt
     */
    private function build_executive_summary_prompt($context) {
        $site_name = $context['site_info']['name'];
        $report_period = $context['site_info']['report_period'];
        
        $prompt = "Generate a professional executive summary for {$site_name} website report covering {$report_period}.\n\n";
        
        $prompt .= "Performance Metrics:\n";
        $prompt .= "- PageSpeed Score: {$context['performance_metrics']['page_speed_score']}\n";
        $prompt .= "- Average Load Time: {$context['performance_metrics']['load_time']}\n";
        $prompt .= "- Uptime: {$context['performance_metrics']['uptime_percentage']}\n";
        $prompt .= "- Total Visitors: {$context['performance_metrics']['total_visitors']}\n\n";
        
        $prompt .= "Security Status:\n";
        $prompt .= "- Vulnerabilities Found: {$context['security_status']['vulnerabilities_found']}\n";
        $prompt .= "- Malware Scans: " . ($context['security_status']['malware_scans_clean'] ? 'Clean' : 'Issues Found') . "\n";
        $prompt .= "- SSL Status: " . ($context['security_status']['ssl_status'] ? 'Active' : 'Inactive') . "\n\n";
        
        $prompt .= "SEO Health:\n";
        $prompt .= "- Indexed Pages: {$context['seo_metrics']['indexed_pages']}\n";
        $prompt .= "- Broken Links: {$context['seo_metrics']['broken_links']}\n";
        $prompt .= "- Content Quality Score: {$context['seo_metrics']['content_quality_score']}\n\n";
        
        $prompt .= "Please provide:\n";
        $prompt .= "1. A concise 2-3 sentence overview of the website's current health\n";
        $prompt .= "2. Key achievements and positive metrics\n";
        $prompt .= "3. Areas requiring immediate attention (if any)\n";
        $prompt .= "4. Overall website health grade (A-F)\n";
        $prompt .= "5. Next period priorities (2-3 key focus areas)\n\n";
        $prompt .= "Keep it professional, client-friendly, and actionable. Format as JSON with keys: overview, achievements, concerns, grade, priorities";
        
        return $prompt;
    }
    
    /**
     * Query OpenAI API
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
                        'content' => 'You are a professional web consultant creating client reports. Provide clear, actionable insights in a business-appropriate tone.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 1500,
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
     * Parse executive summary from AI response
     */
    private function parse_executive_summary($ai_response) {
        // Try to parse as JSON first
        $json_data = json_decode($ai_response, true);
        
        if ($json_data && is_array($json_data)) {
            return array(
                'overview' => $json_data['overview'] ?? '',
                'achievements' => $json_data['achievements'] ?? '',
                'concerns' => $json_data['concerns'] ?? '',
                'grade' => $json_data['grade'] ?? 'B',
                'priorities' => $json_data['priorities'] ?? '',
                'generated_by_ai' => true
            );
        }
        
        // Fallback: parse text response
        return array(
            'overview' => $this->extract_section($ai_response, 'overview'),
            'achievements' => $this->extract_section($ai_response, 'achievements'),
            'concerns' => $this->extract_section($ai_response, 'concerns'),
            'grade' => $this->extract_grade($ai_response),
            'priorities' => $this->extract_section($ai_response, 'priorities'),
            'generated_by_ai' => true
        );
    }
}
