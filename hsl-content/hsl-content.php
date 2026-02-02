<?php
/*
Plugin Name: HSL Content
Description: A plugin to generate AI-written articles based on keywords and manage them efficiently.
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once plugin_dir_path(__FILE__) . 'includes/class-hsl-content-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-hsl-content-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-hsl-content-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-hsl-content-settings.php';

// Initialize the plugin
function hsl_content_init() {
    $generator = new HSL_Content_Generator();
    $manager = new HSL_Content_Manager();
    $admin = new HSL_Content_Admin($generator, $manager);
    $settings = new HSL_Content_Settings();
}
add_action('plugins_loaded', 'hsl_content_init');
