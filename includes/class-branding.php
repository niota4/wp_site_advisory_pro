<?php
/**
 * WP Site Advisory Pro Branding Helper Class
 * 
 * Manages consistent branding across the Pro plugin interface including logos,
 * icons, colors, and admin interface elements.
 * 
 * @package WP_Site_Advisory_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Pro_Branding {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Base URL for plugin assets
     */
    private $assets_url;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->assets_url = WSA_PRO_PLUGIN_URL . 'assets/images/';
        add_action('admin_head', array($this, 'add_admin_favicon'));
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
     * Get plugin icon URL
     */
    public function get_icon_url($size = 'default') {
        $icons = array(
            'small' => 'wsa-icon.svg',
            'medium' => 'wsa-icon.svg',
            'large' => 'wsa-icon.svg',
            'default' => 'wsa-icon.svg'
        );
        
        $icon_file = isset($icons[$size]) ? $icons[$size] : $icons['default'];
        return $this->assets_url . $icon_file;
    }
    
    /**
     * Get plugin logo URL
     */
    public function get_logo_url($size = 'default') {
        $logos = array(
            'small' => 'wsa-logo.svg',
            'medium' => 'wsa-logo.svg',
            'large' => 'wsa-logo.svg',
            'default' => 'wsa-logo.svg'
        );
        
        $logo_file = isset($logos[$size]) ? $logos[$size] : $logos['default'];
        return $this->assets_url . $logo_file;
    }
    
    /**
     * Render plugin icon
     */
    public static function render_icon($size = 'default', $echo = true) {
        $instance = self::get_instance();
        $icon_url = $instance->get_icon_url($size);
        $alt_text = __('WP Site Advisory Pro Icon', 'wp-site-advisory-pro');
        
        $html = '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($alt_text) . '" class="wsa-icon wsa-icon-' . esc_attr($size) . '">';
        
        if ($echo) {
            echo $html;
        }
        return $html;
    }
    
    /**
     * Render plugin logo
     */
    public static function render_logo($size = 'default', $echo = true) {
        $instance = self::get_instance();
        $logo_url = $instance->get_logo_url($size);
        $alt_text = __('WP Site Advisory Pro', 'wp-site-advisory-pro');
        
        $html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($alt_text) . '" class="wsa-logo wsa-logo-' . esc_attr($size) . '">';
        
        if ($echo) {
            echo $html;
        }
        return $html;
    }
    
    /**
     * Get brand colors
     */
    public static function get_brand_colors() {
        return array(
            'primary' => '#0073aa',     // WordPress blue - trust, reliability
            'secondary' => '#666666',    // Neutral gray - sophistication
            'accent' => '#00c6d7',      // Light teal - innovation
            'success' => '#46b450',     // WordPress green
            'warning' => '#ffb900',     // WordPress orange
            'error' => '#dc3232'        // WordPress red
        );
    }
    
    /**
     * Render admin page header with branding
     */
    public static function render_admin_header($page_title = '', $show_pro_badge = true) {
        $instance = self::get_instance();
        $logo_url = $instance->get_logo_url('medium');
        $colors = self::get_brand_colors();
        
        echo '<div class="wsa-branded-header">';
        echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr__('WP Site Advisory Pro', 'wp-site-advisory-pro') . '" class="wsa-logo">';
        echo '<h1>' . esc_html($page_title ? $page_title : get_admin_page_title());
        if ($show_pro_badge) {
            echo '<span class="wsa-pro-badge" style="background: linear-gradient(135deg, ' . $colors['primary'] . ', #005177);">PRO</span>';
        }
        echo '</h1>';
        echo '</div>';
        
        // Add dynamic CSS for branding
        echo self::get_branding_css();
    }
    
    /**
     * Add admin favicon for branding
     */
    public function add_admin_favicon() {
        global $pagenow;
        
        // Only add on plugin pages
        if (isset($_GET['page']) && strpos($_GET['page'], 'wp-site-advisory') !== false) {
            $icon_url = $this->get_icon_url('small');
            echo '<link rel="shortcut icon" type="image/x-icon" href="' . esc_url($icon_url) . '" />';
        }
    }
    
    /**
     * Render Pro badge
     */
    public static function render_pro_badge($echo = true) {
        $colors = self::get_brand_colors();
        $html = '<span class="wsa-pro-badge" style="background: linear-gradient(135deg, ' . $colors['primary'] . ', #005177); color: white; font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 3px 8px; border-radius: 12px; margin-left: 8px;">PRO</span>';
        
        if ($echo) {
            echo $html;
        }
        return $html;
    }
    
    /**
     * Generate CSS for consistent branding
     */
    public static function get_branding_css() {
        $colors = self::get_brand_colors();
        
        ob_start();
        ?>
        <style type="text/css">
        .wsa-branded-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px 0;
            border-bottom: 2px solid <?php echo $colors['primary']; ?>;
        }
        .wsa-branded-header .wsa-logo {
            height: 32px;
            width: auto;
        }
        .wsa-branded-header h1 {
            margin: 0;
            font-size: 23px;
            font-weight: 400;
            color: #23282d;
            line-height: 1.3;
        }
        .wsa-pro-badge {
            background: linear-gradient(135deg, <?php echo $colors['primary']; ?>, #005177) !important;
            color: white !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            padding: 3px 8px !important;
            border-radius: 12px !important;
            margin-left: 8px !important;
        }
        .wsa-brand-primary { color: <?php echo $colors['primary']; ?> !important; }
        .wsa-brand-secondary { color: <?php echo $colors['secondary']; ?> !important; }
        .wsa-brand-bg-primary { background-color: <?php echo $colors['primary']; ?> !important; }
        .wsa-brand-bg-secondary { background-color: <?php echo $colors['secondary']; ?> !important; }
        </style>
        <?php
        return ob_get_clean();
    }
}

/**
 * Global helper functions for Pro branding
 */

/**
 * Get Pro branding instance
 */
function wsa_pro_branding() {
    return WP_Site_Advisory_Pro_Branding::get_instance();
}

/**
 * Render Pro icon
 */
function wsa_pro_render_icon($size = 'default', $echo = true) {
    return WP_Site_Advisory_Pro_Branding::render_icon($size, $echo);
}

/**
 * Render Pro logo
 */
function wsa_pro_render_logo($size = 'default', $echo = true) {
    return WP_Site_Advisory_Pro_Branding::render_logo($size, $echo);
}

/**
 * Get Pro brand colors
 */
function wsa_pro_get_brand_colors() {
    return WP_Site_Advisory_Pro_Branding::get_brand_colors();
}

/**
 * Render Pro badge
 */
function wsa_pro_render_badge($echo = true) {
    return WP_Site_Advisory_Pro_Branding::render_pro_badge($echo);
}