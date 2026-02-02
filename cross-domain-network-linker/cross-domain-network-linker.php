<?php
/**
 * Plugin Name: Cross Domain Network Linker
 * Plugin URI: https://onlineapplications.co.za
 * Description: Smart cross-domain linking system for your network of sites with contextual matching and sidebar widget.
 * Version: 1.2.0
 * Author: Your Network
 * Text Domain: cross-domain-network
 * Domain Path: /languages
 * 
 * Security: All inputs are sanitized and validated
 * Performance: Lightweight with caching support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
class CrossDomainNetworkLinker {
    
    private $version = '1.2.0';
    private $plugin_name = 'cross-domain-network';
    
    // Network domains configuration
    private $domains = [
        'onlineapplications.co.za' => [
            'name' => 'Online Applications Guide',
            'description' => 'Comprehensive guides for online applications in South Africa',
            'categories' => ['applications', 'government', 'forms', 'online'],
            'icon' => '📋'
        ],
        'bursary24.co.za' => [
            'name' => 'Bursary Applications 2024',
            'description' => 'Latest bursary opportunities and application guides',
            'categories' => ['education', 'funding', 'students', 'bursary'],
            'icon' => '🎓'
        ],
        'psiraguide.co.za' => [
            'name' => 'PSIRA Registration Guide',
            'description' => 'Step-by-step PSIRA registration and renewal process',
            'categories' => ['security', 'registration', 'professional', 'psira'],
            'icon' => '🛡️'
        ],
        'uni1.co.za' => [
            'name' => 'University Applications',
            'description' => 'University admission requirements and application tips',
            'categories' => ['education', 'university', 'students', 'admission'],
            'icon' => '🏛️'
        ],
        'ufilingguide.co.za' => [
            'name' => 'UIF Claims Guide',
            'description' => 'Complete guide to UIF applications and claims',
            'categories' => ['employment', 'benefits', 'government', 'uif'],
            'icon' => '💼'
        ],
        'sarsguide.co.za' => [
            'name' => 'SARS Tax Guide',
            'description' => 'Tax filing, eFiling, and SARS-related assistance',
            'categories' => ['tax', 'government', 'finance', 'sars'],
            'icon' => '💰'
        ],
        'forms24.co.za' => [
            'name' => 'Government Forms 2024',
            'description' => 'Download and guidance for South African government forms',
            'categories' => ['forms', 'government', 'documents', 'download'],
            'icon' => '📄'
        ],
        'nsfasonlineapplications.co.za' => [
            'name' => 'NSFAS Applications',
            'description' => 'NSFAS funding applications and student finance guides',
            'categories' => ['education', 'funding', 'students', 'nsfas'],
            'icon' => '🏦'
        ],
        'driverslicenserenewals.co.za' => [
            'name' => 'Driver\'s License Renewals',
            'description' => 'Guide to renewing your South African driver\'s license',
            'categories' => ['transport', 'licenses', 'government', 'driving'],
            'icon' => '🚗'
        ],
        'golearnership.co.za' => [
            'name' => 'Learnership Opportunities',
            'description' => 'Find and apply for learnerships and skills development',
            'categories' => ['education', 'skills', 'employment', 'learnership'],
            'icon' => '🔧'
        ],
        'bestbrainz.com' => [
            'name' => 'Best Brainz Education',
            'description' => 'Educational resources and academic support services',
            'categories' => ['education', 'tutoring', 'academic', 'learning'],
            'icon' => '🧠'
        ]
    ];

    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('widgets_init', [$this, 'register_widgets']);
        add_filter('the_content', [$this, 'add_contextual_links']);
        add_shortcode('network_sites', [$this, 'network_sites_shortcode']);
        add_action('wp_head', [$this, 'add_structured_data']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    /**
     * Plugin initialization
     */
    public function init() {
        load_plugin_textdomain($this->plugin_name, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on pages where needed
        if (!is_admin()) {
            wp_add_inline_style('wp-block-library', $this->get_css());
        }
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget('Cross_Domain_Network_Widget');
    }

    /**
     * Add contextual links to post content
     */
    public function add_contextual_links($content) {
        // Only on single posts and if enabled
        if (!is_single() || !$this->get_option('enable_contextual_links', true)) {
            return $content;
        }
        
        $current_domain = $this->get_current_domain();
        $post_id = get_the_ID();
        
        // Check cache first
        $cache_key = 'cdn_contextual_' . $current_domain . '_' . $post_id;
        $cached_links = wp_cache_get($cache_key);
        
        if ($cached_links === false) {
            $post_categories = $this->get_post_keywords($post_id);
            $related_links = $this->get_contextual_links($current_domain, $post_categories);
            wp_cache_set($cache_key, $related_links, '', HOUR_IN_SECONDS);
        } else {
            $related_links = $cached_links;
        }
        
        if (!empty($related_links)) {
            $content .= $this->render_contextual_links($related_links);
        }
        
        return $content;
    }

    /**
     * Get current domain safely
     */
    private function get_current_domain() {
        return sanitize_text_field($_SERVER['HTTP_HOST']);
    }

    /**
     * Get post keywords from categories, tags, and content
     */
    private function get_post_keywords($post_id) {
        $keywords = [];
        
        // Get categories
        $categories = wp_get_post_categories($post_id);
        foreach($categories as $cat_id) {
            $cat = get_category($cat_id);
            if ($cat) {
                $keywords[] = strtolower(sanitize_text_field($cat->name));
            }
        }
        
        // Get tags
        $tags = wp_get_post_tags($post_id);
        foreach($tags as $tag) {
            $keywords[] = strtolower(sanitize_text_field($tag->name));
        }
        
        // Get keywords from title
        $title = get_the_title($post_id);
        $title_words = explode(' ', strtolower($title));
        $keywords = array_merge($keywords, $title_words);
        
        return array_unique($keywords);
    }

    /**
     * Get contextually relevant links
     */
    private function get_contextual_links($current_domain, $post_keywords, $limit = 3) {
        $relevant_links = [];
        
        foreach($this->domains as $domain => $info) {
            if ($domain === $current_domain) continue;
            
            $relevance_score = 0;
            foreach($info['categories'] as $domain_category) {
                foreach($post_keywords as $keyword) {
                    if (strpos($keyword, $domain_category) !== false || 
                        strpos($domain_category, $keyword) !== false) {
                        $relevance_score++;
                    }
                }
            }
            
            if ($relevance_score > 0) {
                $relevant_links[$domain] = $info;
                $relevant_links[$domain]['score'] = $relevance_score;
            }
        }
        
        // Sort by relevance
        uasort($relevant_links, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return array_slice($relevant_links, 0, $limit, true);
    }

    /**
     * Render contextual links HTML
     */
    private function render_contextual_links($links) {
        if (empty($links)) return '';
        
        $output = '<div class="cdn-contextual-links">';
        $output .= '<h4 class="cdn-title">' . esc_html__('Related Resources', 'cross-domain-network') . '</h4>';
        $output .= '<div class="cdn-links-grid">';
        
        foreach($links as $domain => $info) {
            $output .= sprintf(
                '<div class="cdn-link-item">
                    <span class="cdn-icon">%s</span>
                    <a href="https://%s" rel="nofollow" target="_blank">%s</a>
                    <span class="cdn-description">%s</span>
                </div>',
                esc_html($info['icon']),
                esc_url($domain),
                esc_html($info['name']),
                esc_html($info['description'])
            );
        }
        
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Network sites shortcode
     */
    public function network_sites_shortcode($atts) {
        $atts = shortcode_atts([
            'exclude_current' => 'true',
            'limit' => '6',
            'style' => 'grid'
        ], $atts);
        
        $current_domain = $this->get_current_domain();
        $output = '<div class="cdn-shortcode cdn-style-' . esc_attr($atts['style']) . '">';
        $output .= '<h3 class="cdn-network-title">' . esc_html__('Explore Our Network', 'cross-domain-network') . '</h3>';
        $output .= '<div class="cdn-network-grid">';
        
        $count = 0;
        foreach($this->domains as $domain => $info) {
            if ($atts['exclude_current'] === 'true' && $domain === $current_domain) continue;
            if ($count >= intval($atts['limit'])) break;
            
            $output .= sprintf(
                '<div class="cdn-network-item">
                    <span class="cdn-icon">%s</span>
                    <a href="https://%s" rel="nofollow" target="_blank">%s</a>
                    <div class="cdn-description">%s</div>
                </div>',
                esc_html($info['icon']),
                esc_url($domain),
                esc_html($info['name']),
                esc_html($info['description'])
            );
            $count++;
        }
        
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Add structured data for SEO
     */
    public function add_structured_data() {
        if (!is_single()) return;
        
        $current_domain = $this->get_current_domain();
        if (!isset($this->domains[$current_domain])) return;
        
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => $this->domains[$current_domain]['name'],
            "description" => $this->domains[$current_domain]['description'],
            "url" => "https://" . $current_domain
        ];
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    /**
     * Plugin CSS
     */
    private function get_css() {
        return '
        .cdn-contextual-links, .cdn-shortcode {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .cdn-title, .cdn-network-title {
            color: #212529;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .cdn-title:before {
            content: "📚";
        }
        
        .cdn-network-title:before {
            content: "🌐";
        }
        
        .cdn-links-grid, .cdn-network-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .cdn-link-item, .cdn-network-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .cdn-link-item:hover, .cdn-network-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            border-left-color: #0056b3;
        }
        
        .cdn-icon {
            font-size: 20px;
            align-self: flex-start;
        }
        
        .cdn-link-item a, .cdn-network-item a {
            text-decoration: none;
            color: #212529;
            font-weight: 600;
            font-size: 15px;
            transition: color 0.2s ease;
        }
        
        .cdn-link-item a:hover, .cdn-network-item a:hover {
            color: #007bff;
        }
        
        .cdn-description {
            font-size: 13px;
            color: #6c757d;
            line-height: 1.4;
        }
        
        .cdn-widget {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .cdn-widget-title {
            color: #212529;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }
        
        .cdn-widget-item {
            margin-bottom: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #007bff;
        }
        
        .cdn-widget-item:last-child {
            margin-bottom: 0;
        }
        
        .cdn-widget-item a {
            text-decoration: none;
            color: #212529;
            font-weight: 500;
            display: block;
            margin-bottom: 4px;
        }
        
        .cdn-widget-item a:hover {
            color: #007bff;
        }
        
        .cdn-widget-desc {
            font-size: 12px;
            color: #6c757d;
            line-height: 1.3;
        }
        
        @media (max-width: 768px) {
            .cdn-links-grid, .cdn-network-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .cdn-contextual-links, .cdn-shortcode {
                padding: 16px;
                margin: 16px 0;
            }
        }';
    }

    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Network Links Settings', 'cross-domain-network'),
            __('Network Links', 'cross-domain-network'),
            'manage_options',
            'cross-domain-network',
            [$this, 'admin_page']
        );
    }

    /**
     * Admin settings initialization
     */
    public function admin_init() {
        register_setting('cdn_settings', 'cdn_options');
        
        add_settings_section(
            'cdn_main_section',
            __('General Settings', 'cross-domain-network'),
            null,
            'cross-domain-network'
        );
        
        add_settings_field(
            'enable_contextual_links',
            __('Enable Contextual Links', 'cross-domain-network'),
            [$this, 'checkbox_field'],
            'cross-domain-network',
            'cdn_main_section',
            ['name' => 'enable_contextual_links', 'default' => true]
        );
    }

    /**
     * Checkbox field callback
     */
    public function checkbox_field($args) {
        $options = get_option('cdn_options', []);
        $value = isset($options[$args['name']]) ? $options[$args['name']] : $args['default'];
        
        printf(
            '<input type="checkbox" name="cdn_options[%s]" value="1" %s />',
            esc_attr($args['name']),
            checked(1, $value, false)
        );
    }

    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cross Domain Network Settings', 'cross-domain-network'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cdn_settings');
                do_settings_sections('cross-domain-network');
                submit_button();
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Usage Instructions', 'cross-domain-network'); ?></h2>
                <p><strong><?php esc_html_e('Shortcode:', 'cross-domain-network'); ?></strong> <code>[network_sites]</code></p>
                <p><strong><?php esc_html_e('Widget:', 'cross-domain-network'); ?></strong> <?php esc_html_e('Go to Appearance > Widgets and add "Network Sites Links"', 'cross-domain-network'); ?></p>
                <p><strong><?php esc_html_e('Contextual Links:', 'cross-domain-network'); ?></strong> <?php esc_html_e('Automatically added to single posts when enabled', 'cross-domain-network'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Get plugin option
     */
    private function get_option($key, $default = null) {
        $options = get_option('cdn_options', []);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        if (!get_option('cdn_options')) {
            update_option('cdn_options', [
                'enable_contextual_links' => true
            ]);
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_cache_flush();
    }
}

/**
 * Widget Class
 */
class Cross_Domain_Network_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'cross_domain_network_widget',
            __('Network Sites Links', 'cross-domain-network'),
            ['description' => __('Display links to related network sites', 'cross-domain-network')]
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Our Network', 'cross-domain-network');
        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 5;
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        echo '<div class="cdn-widget">';
        echo do_shortcode('[network_sites limit="' . $limit . '"]');
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Our Network', 'cross-domain-network');
        $limit = !empty($instance['limit']) ? $instance['limit'] : 5;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'cross-domain-network'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>"><?php esc_html_e('Number of sites to show:', 'cross-domain-network'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" value="<?php echo esc_attr($limit); ?>" min="1" max="10">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? intval($new_instance['limit']) : 5;
        return $instance;
    }
}

// Initialize the plugin
new CrossDomainNetworkLinker();
?>
