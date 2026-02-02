<?php
/**
 * Plugin Name: RPM Booster - FIXED
 * Plugin URI: https://yoursite.com/rpm-booster
 * Description: A lightweight plugin optimized for large databases (4000+ posts)
 * Version: 1.0.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: rpm-booster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RPM_BOOSTER_VERSION', '1.0.1');
define('RPM_BOOSTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RPM_BOOSTER_PLUGIN_URL', plugin_dir_url(__FILE__));

class RPM_Booster {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load plugin files
        $this->load_includes();
        
        // Initialize admin
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            $this->init_frontend();
        }
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    private function load_includes() {
        require_once RPM_BOOSTER_PLUGIN_DIR . 'includes/query.php';
        require_once RPM_BOOSTER_PLUGIN_DIR . 'includes/display.php';
        
        if (is_admin()) {
            require_once RPM_BOOSTER_PLUGIN_DIR . 'admin/settings.php';
        }
    }
    
    private function init_admin() {
        new RPM_Booster_Admin();
    }
    
    private function init_frontend() {
        new RPM_Booster_Display();
    }
    
    public function enqueue_styles() {
        wp_enqueue_style(
            'rpm-booster-style',
            RPM_BOOSTER_PLUGIN_URL . 'assets/style.css',
            array(),
            RPM_BOOSTER_VERSION
        );
    }
    
    public function activate() {
        // Minimal activation - NO cache prewarming for large sites
        $default_options = array(
            'enabled' => 1,
            'categories' => array(),
            'num_articles' => 3, // Force max 3 for safety
            'excerpt_mode' => 'words',
            'excerpt_length' => 50, // Reduced default
            'display_mode' => 'excerpt' // Force excerpt mode
        );
        
        if (!get_option('rpm_booster_settings')) {
            add_option('rpm_booster_settings', $default_options);
        }
        
        // NO pre-warming cache during activation - causes crashes on large sites
    }
    
    public function deactivate() {
        // Clear all caches
        RPM_Booster_Query::clear_cache();
    }
    
    public static function get_settings() {
        $defaults = array(
            'enabled' => 1,
            'categories' => array(),
            'num_articles' => 3, // Max 3 for large sites
            'excerpt_mode' => 'words',
            'excerpt_length' => 50,
            'display_mode' => 'excerpt'
        );
        
        $settings = get_option('rpm_booster_settings', $defaults);
        
        // Force safety limits for large sites
        $settings['num_articles'] = min(3, max(1, intval($settings['num_articles'])));
        $settings['display_mode'] = 'excerpt'; // Force excerpt mode
        $settings['excerpt_length'] = min(100, max(10, intval($settings['excerpt_length'])));
        
        return wp_parse_args($settings, $defaults);
    }
}

// Initialize the plugin
RPM_Booster::get_instance();
