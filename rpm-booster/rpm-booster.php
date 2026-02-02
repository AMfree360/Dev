<?php
/**
 * Plugin Name: RPM Booster - FIXED
 * Plugin URI: https://yoursite.com/rpm-booster
 * Description: A lightweight plugin optimized for large databases (4000+ posts)
 * Version: 1.0.2
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: rpm-booster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RPM_BOOSTER_VERSION', '1.0.2');
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
        // Load plugin files with error handling
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
        $includes = array(
            'query' => RPM_BOOSTER_PLUGIN_DIR . 'includes/query.php',
            'display' => RPM_BOOSTER_PLUGIN_DIR . 'includes/display.php'
        );
        
        foreach ($includes as $name => $file) {
            if (file_exists($file)) {
                require_once $file;
            } else {
                // Log error and deactivate plugin to prevent fatal errors
                error_log("RPM Booster: Missing file - {$file}");
                add_action('admin_notices', array($this, 'show_missing_files_notice'));
                return false;
            }
        }
        
        // Load admin files if in admin
        if (is_admin()) {
            $admin_file = RPM_BOOSTER_PLUGIN_DIR . 'admin/settings.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;
            } else {
                error_log("RPM Booster: Missing admin file - {$admin_file}");
            }
        }
        
        return true;
    }
    
    public function show_missing_files_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>RPM Booster Error:</strong> Plugin files are missing. Please reinstall the plugin.</p>
        </div>
        <?php
    }
    
    private function init_admin() {
        if (class_exists('RPM_Booster_Admin')) {
            new RPM_Booster_Admin();
        }
    }
    
    private function init_frontend() {
        if (class_exists('RPM_Booster_Display')) {
            new RPM_Booster_Display();
        }
    }
    
    public function enqueue_styles() {
        $css_file = RPM_BOOSTER_PLUGIN_URL . 'assets/style.css';
        if (file_exists(RPM_BOOSTER_PLUGIN_DIR . 'assets/style.css')) {
            wp_enqueue_style(
                'rpm-booster-style',
                $css_file,
                array(),
                RPM_BOOSTER_VERSION
            );
        }
    }
    
    public function activate() {
        // Check if required directories exist, create if needed
        $directories = array(
            RPM_BOOSTER_PLUGIN_DIR . 'includes',
            RPM_BOOSTER_PLUGIN_DIR . 'admin',
            RPM_BOOSTER_PLUGIN_DIR . 'assets'
        );
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
        
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
    }
    
    public function deactivate() {
        // Clear all caches
        if (class_exists('RPM_Booster_Query')) {
            RPM_Booster_Query::clear_cache();
        }
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

// Query Handler Class - Inline to prevent missing file errors
class RPM_Booster_Query {
    
    public static function get_sponsored_articles($current_post_id = null) {
        $settings = RPM_Booster::get_settings();
        
        // Check if plugin is enabled
        if (!$settings['enabled']) {
            return array();
        }
        
        // Simplified cache key
        $cache_key = 'rpm_booster_simple_' . md5($current_post_id . serialize($settings));
        $cached_articles = get_transient($cache_key);
        
        if ($cached_articles !== false) {
            return $cached_articles;
        }
        
        // Use ultra-lightweight query for large datasets
        $articles = self::get_articles_lightweight($settings, $current_post_id);
        
        // Cache for 6 hours (reduced from 12)
        if (!empty($articles)) {
            set_transient($cache_key, $articles, 6 * HOUR_IN_SECONDS);
        }
        
        return $articles;
    }
    
    private static function get_articles_lightweight($settings, $current_post_id = null) {
        global $wpdb;
        
        // Force small number for safety
        $limit = min(3, $settings['num_articles']);
        
        // Build simple WHERE clause
        $where_conditions = array(
            "post_type = 'post'",
            "post_status = 'publish'"
        );
        
        $where_values = array();
        
        if ($current_post_id) {
            $where_conditions[] = "ID != %d";
            $where_values[] = $current_post_id;
        }
        
        // Category filter - simplified
        $join_category = '';
        if (!empty($settings['categories']) && is_array($settings['categories'])) {
            $category_ids = array_map('intval', array_slice($settings['categories'], 0, 5)); // Max 5 categories
            if (!empty($category_ids)) {
                $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
                
                $join_category = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
                $where_conditions[] = "tt.taxonomy = 'category'";
                $where_conditions[] = "tt.term_id IN ({$placeholders})";
                $where_values = array_merge($where_values, $category_ids);
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Ultra-lightweight query - get minimal data only
        $sql = "SELECT DISTINCT p.ID, p.post_title, 
                       COALESCE(p.post_excerpt, SUBSTRING(p.post_content, 1, 300)) as excerpt_content
                FROM {$wpdb->posts} p 
                {$join_category}
                WHERE {$where_clause} 
                ORDER BY RAND() 
                LIMIT {$limit}";
        
        if (!empty($where_values)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        } else {
            $results = $wpdb->get_results($sql);
        }
        
        if (empty($results)) {
            return array();
        }
        
        // Process results without loading full post objects
        $articles = array();
        foreach ($results as $row) {
            $articles[] = array(
                'ID' => $row->ID,
                'title' => $row->post_title,
                'permalink' => get_permalink($row->ID),
                'content' => '', // Never load full content to save memory
                'excerpt' => self::get_safe_excerpt($row->excerpt_content, $settings)
            );
        }
        
        return $articles;
    }
    
    private static function get_safe_excerpt($content, $settings) {
        // Strip tags and shortcodes safely
        $content = wp_strip_all_tags(strip_shortcodes($content));
        $content = trim(preg_replace('/\s+/', ' ', $content));
        
        if (empty($content)) {
            return '';
        }
        
        // Limit processing to max 500 chars for safety
        if (strlen($content) > 500) {
            $content = substr($content, 0, 500);
        }
        
        // Simple word-based excerpt only (avoid complex sentence parsing)
        $word_limit = min(50, max(10, intval($settings['excerpt_length']))); // Safety limits
        $words = explode(' ', $content);
        
        if (count($words) <= $word_limit) {
            return $content;
        }
        
        return implode(' ', array_slice($words, 0, $word_limit)) . '...';
    }
    
    public static function clear_cache() {
        global $wpdb;
        
        // More efficient cache clearing
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_rpm_booster_%' 
             OR option_name LIKE '_transient_timeout_rpm_booster_%'"
        );
        
        // Clear object cache too
        wp_cache_flush();
    }
}

// Display Handler Class - Inline to prevent missing file errors
class RPM_Booster_Display {
    
    public function __construct() {
        add_filter('the_content', array($this, 'add_sponsored_content'));
    }
    
    public function add_sponsored_content($content) {
        // Only display on single posts
        if (!is_single() || !is_main_query()) {
            return $content;
        }
        
        global $post;
        
        // Get sponsored articles
        $articles = RPM_Booster_Query::get_sponsored_articles($post->ID);
        
        if (empty($articles)) {
            return $content;
        }
        
        // Generate sponsored content HTML
        $sponsored_html = $this->generate_sponsored_html($articles);
        
        // Append to content
        return $content . $sponsored_html;
    }
    
    private function generate_sponsored_html($articles) {
        $settings = RPM_Booster::get_settings();
        
        ob_start();
        ?>
        <div class="rpm-booster-sponsored">
            <h3 class="sponsored-heading"><?php echo esc_html__('Sponsored Content', 'rpm-booster'); ?></h3>
            <div class="sponsored-articles">
                <?php foreach ($articles as $article) : ?>
                    <article class="sponsored-item">
                        <h4 class="sponsored-title">
                            <a href="<?php echo esc_url($article['permalink']); ?>">
                                <?php echo esc_html($article['title']); ?>
                            </a>
                        </h4>
                        
                        <?php if ($settings['display_mode'] === 'full' && !empty($article['content'])) : ?>
                            <div class="sponsored-content">
                                <?php echo wp_kses_post($article['content']); ?>
                            </div>
                        <?php else : ?>
                            <p class="sponsored-excerpt">
                                <?php echo esc_html($article['excerpt']); ?>
                            </p>
                            <a href="<?php echo esc_url($article['permalink']); ?>" class="sponsored-read-more">
                                <?php echo esc_html__('Read More', 'rpm-booster'); ?>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
        .rpm-booster-sponsored {
            margin: 30px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #e1e1e1;
            border-radius: 5px;
            clear: both;
        }
        .rpm-booster-sponsored .sponsored-heading {
            margin: 0 0 20px 0;
            padding: 0 0 10px 0;
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #e1e1e1;
            text-align: left;
        }
        .rpm-booster-sponsored .sponsored-articles {
            display: grid;
            gap: 20px;
            grid-template-columns: 1fr;
        }
        @media (min-width: 768px) {
            .rpm-booster-sponsored .sponsored-articles {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .rpm-booster-sponsored .sponsored-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            transition: box-shadow 0.3s ease;
        }
        .rpm-booster-sponsored .sponsored-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .rpm-booster-sponsored .sponsored-title {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            line-height: 1.3;
        }
        .rpm-booster-sponsored .sponsored-title a {
            text-decoration: none;
            color: #2c3e50;
            font-weight: 600;
        }
        .rpm-booster-sponsored .sponsored-title a:hover {
            color: #3498db;
            text-decoration: underline;
        }
        .rpm-booster-sponsored .sponsored-excerpt {
            margin: 0 0 10px 0;
            line-height: 1.5;
            color: #666;
            font-size: 0.95em;
        }
        .rpm-booster-sponsored .sponsored-read-more {
            display: inline-block;
            padding: 8px 16px;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        .rpm-booster-sponsored .sponsored-read-more:hover {
            background-color: #2980b9;
            color: #fff;
            text-decoration: none;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    public static function render_preview($articles) {
        $settings = RPM_Booster::get_settings();
        
        if (empty($articles)) {
            echo '<p>' . esc_html__('No articles found. Try selecting different categories or check if you have published posts in the selected categories.', 'rpm-booster') . '</p>';
            return;
        }
        
        ?>
        <div class="rpm-booster-sponsored rpm-preview">
            <h3 class="sponsored-heading"><?php echo esc_html__('Sponsored Content', 'rpm-booster'); ?></h3>
            <div class="sponsored-articles">
                <?php foreach ($articles as $article) : ?>
                    <article class="sponsored-item">
                        <h4 class="sponsored-title">
                            <span><?php echo esc_html($article['title']); ?></span>
                        </h4>
                        
                        <?php if ($settings['display_mode'] === 'full' && !empty($article['content'])) : ?>
                            <div class="sponsored-content">
                                <?php echo wp_kses_post(wp_trim_words($article['content'], 50, '...')); ?>
                            </div>
                        <?php else : ?>
                            <p class="sponsored-excerpt">
                                <?php echo esc_html($article['excerpt']); ?>
                            </p>
                            <span class="sponsored-read-more">
                                <?php echo esc_html__('Read More', 'rpm-booster'); ?>
                            </span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

// Basic Admin Settings - Inline to prevent missing file errors
if (is_admin()) {
    class RPM_Booster_Admin {
        
        public function __construct() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'init_settings'));
        }
        
        public function add_admin_menu() {
            add_options_page(
                __('RPM Booster Settings', 'rpm-booster'),
                __('RPM Booster', 'rpm-booster'),
                'manage_options',
                'rpm-booster',
                array($this, 'settings_page')
            );
        }
        
        public function init_settings() {
            register_setting(
                'rpm_booster_settings_group',
                'rpm_booster_settings',
                array($this, 'validate_settings')
            );
        }
        
        public function settings_page() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            
            // Handle form submission
            if (isset($_POST['submit']) && wp_verify_nonce($_POST['rpm_booster_nonce'], 'rpm_booster_save')) {
                $this->save_settings();
            }
            
            $settings = RPM_Booster::get_settings();
            $categories = get_categories(array('hide_empty' => false));
            
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__('RPM Booster Settings', 'rpm-booster'); ?></h1>
                
                <form method="post" action="">
                    <?php wp_nonce_field('rpm_booster_save', 'rpm_booster_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Enable Plugin', 'rpm-booster'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rpm_booster_settings[enabled]" value="1" 
                                           <?php checked($settings['enabled'], 1); ?> />
                                    <?php echo esc_html__('Enable sponsored content display', 'rpm-booster'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php echo esc_html__('Number of Articles', 'rpm-booster'); ?></th>
                            <td>
                                <select name="rpm_booster_settings[num_articles]">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($settings['num_articles'], $i); ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__('Number of sponsored articles to display per post (max 5 for performance).', 'rpm-booster'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'rpm-booster')); ?>
                    
                    <p>
                        <a href="<?php echo add_query_arg('clear_cache', '1', admin_url('options-general.php?page=rpm-booster')); ?>" 
                           onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear the cache?', 'rpm-booster')); ?>')">
                            <?php echo esc_html__('Clear Cache', 'rpm-booster'); ?>
                        </a>
                    </p>
                </form>
            </div>
            <?php
            
            // Handle cache clearing
            if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1') {
                RPM_Booster_Query::clear_cache();
                echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared successfully.', 'rpm-booster') . '</p></div>';
            }
        }
        
        private function save_settings() {
            $settings = array(
                'enabled' => isset($_POST['rpm_booster_settings']['enabled']) ? 1 : 0,
                'categories' => array(),
                'num_articles' => intval($_POST['rpm_booster_settings']['num_articles']),
                'excerpt_mode' => 'words',
                'excerpt_length' => 50,
                'display_mode' => 'excerpt'
            );
            
            update_option('rpm_booster_settings', $settings);
            RPM_Booster_Query::clear_cache();
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'rpm-booster') . '</p></div>';
        }
        
        public function validate_settings($input) {
            $validated = array();
            
            $validated['enabled'] = isset($input['enabled']) ? 1 : 0;
            $validated['categories'] = array();
            $validated['num_articles'] = max(1, min(5, intval($input['num_articles'])));
            $validated['excerpt_mode'] = 'words';
            $validated['excerpt_length'] = 50;
            $validated['display_mode'] = 'excerpt';
            
            return $validated;
        }
    }
}

// Initialize the plugin
RPM_Booster::get_instance();
