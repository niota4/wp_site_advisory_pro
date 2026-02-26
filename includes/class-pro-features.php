<?php
/**
 * WSA Pro Features Manager Class
 *
 * Manages Pro feature availability and integration with the free version
 *
 * @package WSA_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSA_Pro_Features {

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
        // Override free version Pro helpers if license is active
        if (wsa_pro_is_license_active()) {
            add_filter('wsa_is_pro_active', array($this, 'override_pro_status'), 10, 1);
            add_filter('wsa_pro_feature_available', array($this, 'check_feature_availability'), 10, 2);
        }

        // Add Pro feature enhancements
        add_action('wsa_before_plugin_scan', array($this, 'enhance_plugin_scan'), 10, 1);
        
        // Add license check to Pro features
        add_action('wsa_pro_feature_request', array($this, 'verify_license_for_feature'), 10, 2);
    }

    /**
     * Override Pro status for the free version
     */
    public function override_pro_status($is_pro) {
        return wsa_pro_is_license_active();
    }

    /**
     * Check if specific Pro feature is available
     */
    public function check_feature_availability($available, $feature) {
        if (!wsa_pro_is_license_active()) {
            return false;
        }

        // Check feature-specific availability (for future tiered licensing)
        $licensed_features = $this->get_licensed_features();
        
        return in_array($feature, $licensed_features);
    }

    /**
     * Get list of features available with current license
     */
    private function get_licensed_features() {
        // For now, all Pro features are available with any valid license
        // This can be expanded for tiered licensing in the future
        return array(
            'ai_analysis',
            'vulnerability_scan',
            'one_click_fixes',
            'advanced_scheduling',
            'white_label',
            'priority_support'
        );
    }

    /**
     * Enhance plugin scan with Pro features
     */
    public function enhance_plugin_scan($scan_data) {
        if (!wsa_pro_is_license_active()) {
            return $scan_data;
        }

        // Add Pro enhancements to scan data
        $scan_data['pro_features'] = array(
            'advanced_vulnerability_scan' => true,
            'ai_recommendations' => true,
            'one_click_fixes' => true
        );

        return $scan_data;
    }

    /**
     * Verify license for specific feature request
     */
    public function verify_license_for_feature($feature, $data) {
        if (!wsa_pro_is_license_active()) {
            return new WP_Error('license_required', 
                sprintf(__('%s requires a valid Pro license.', 'wp-site-advisory-pro'), 
                ucwords(str_replace('_', ' ', $feature)))
            );
        }

        // Check if specific feature is available
        if (!$this->check_feature_availability(true, $feature)) {
            return new WP_Error('feature_not_available', 
                sprintf(__('%s is not available with your current license.', 'wp-site-advisory-pro'), 
                ucwords(str_replace('_', ' ', $feature)))
            );
        }

        return true;
    }

    /**
     * Add Pro status indicator to dashboard
     */
    public function add_pro_status_indicator() {
        if (!wsa_pro_is_license_active()) {
            return;
        }

        ?>
        <div class="wsa-pro-status-indicator">
            <span class="wsa-pro-badge">
                <span class="dashicons dashicons-star-filled"></span>
                <?php _e('PRO ACTIVATED', 'wp-site-advisory-pro'); ?>
            </span>
        </div>
        
        <style>
        .wsa-pro-status-indicator {
            position: fixed;
            top: 32px;
            right: 20px;
            z-index: 9999;
        }
        
        .wsa-pro-badge {
            background: linear-gradient(135deg, #0073aa, #005a87);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 8px rgba(0, 115, 170, 0.3);
            display: inline-block;
        }
        
        .wsa-pro-badge .dashicons {
            font-size: 14px;
            margin-right: 4px;
            vertical-align: middle;
        }
        </style>
        <?php
    }

    /**
     * Get Pro feature status for free version integration
     */
    public function get_pro_integration_status() {
        $license_manager = WSA_Pro_License_Manager::get_instance();
        $license_status = $license_manager->get_license_status();

        return array(
            'pro_active' => wsa_pro_is_license_active(),
            'license_status' => $license_status['status'],
            'license_expires' => $license_status['expires'],
            'features_available' => $this->get_licensed_features(),
            'license_url' => admin_url('admin.php?page=wp-site-advisory-license')
        );
    }
}