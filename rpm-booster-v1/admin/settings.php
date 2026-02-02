<?php
/**
 * RPM Booster Admin Settings
 * Handles admin interface and settings management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RPM_Booster_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    private function get_preview_articles($settings) {
        global $wpdb;
        
        // Lightweight preview query - get max 3 articles
        $limit = min(3, $settings['num_articles']);
        
        $where_conditions = array("post_type = 'post'", "post_status = 'publish'");
        $where_values = array();
        
        // Add category filter if specified
        $join_category = '';
        if (!empty($settings['categories']) && is_array($settings['categories'])) {
            $category_placeholders = implode(',', array_fill(0, count($settings['categories']), '%d'));
            $join_category = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $where_conditions[] = "tt.taxonomy = 'category'";
            $where_conditions[] = "tt.term_id IN ({$category_placeholders})";
            $where_values = array_merge($where_values, array_map('intval', $settings['categories']));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT DISTINCT p.ID, p.post_title, p.post_excerpt, 
                       SUBSTRING(p.post_content, 1, 500) as content_preview
                FROM {$wpdb->posts} p 
                {$join_category}
                WHERE {$where_clause} 
                ORDER BY RAND() 
                LIMIT {$limit}";
        
        $results = empty($where_values) ? 
            $wpdb->get_results($sql) : 
            $wpdb->get_results($wpdb->prepare($sql, $where_values));
        
        $articles = array();
        if ($results) {
            foreach ($results as $row) {
                $excerpt_content = !empty($row->post_excerpt) ? 
                    $row->post_excerpt : 
                    $row->content_preview;
                
                $articles[] = array(
                    'ID' => $row->ID,
                    'title' => $row->post_title,
                    'permalink' => get_permalink($row->ID),
                    'content' => $row->content_preview,
                    'excerpt' => wp_trim_words(strip_shortcodes(wp_strip_all_tags($excerpt_content)), 
                               $settings['excerpt_length'], '...')
                );
            }
        }
        
        return $articles;
    }
    
    private function show_system_info() {
        global $wpdb;
        
        // Get post count
        $post_count = wp_count_posts('post')->publish;
        
        // Get memory info
        $memory_limit = ini_get('memory_limit');
        $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
        
        $show_warning = false;
        $memory_limit_bytes = $this->get_memory_limit_in_bytes();
        
        if ($post_count > 1000 && $memory_limit_bytes < (128 * 1024 * 1024) && $memory_limit_bytes !== -1) {
            $show_warning = true;
        }
        
        ?>
        <div class="notice notice-info">
            <p><strong><?php echo esc_html__('System Info:', 'rpm-booster'); ?></strong></p>
            <ul>
                <li><?php printf(esc_html__('Published Posts: %s', 'rpm-booster'), number_format($post_count)); ?></li>
                <li><?php printf(esc_html__('Memory Limit: %s', 'rpm-booster'), $memory_limit); ?></li>
                <li><?php printf(esc_html__('Current Memory Usage: %s MB', 'rpm-booster'), $memory_usage); ?></li>
            </ul>
        </div>
        
        <?php if ($show_warning) : ?>
        <div class="notice notice-warning">
            <p><strong><?php echo esc_html__('Performance Notice:', 'rpm-booster'); ?></strong></p>
            <p><?php echo esc_html__('You have a large number of posts. For optimal performance:', 'rpm-booster'); ?></p>
            <ul>
                <li><?php echo esc_html__('• Limit to 3-5 sponsored articles', 'rpm-booster'); ?></li>
                <li><?php echo esc_html__('• Select specific categories instead of all categories', 'rpm-booster'); ?></li>
                <li><?php echo esc_html__('• Use excerpt mode instead of full content', 'rpm-booster'); ?></li>
                <li><?php echo esc_html__('• Consider increasing memory limit to 128MB or higher', 'rpm-booster'); ?></li>
            </ul>
        </div>
        <?php endif;
    }
    
    private function get_memory_limit_in_bytes() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return -1;
        }
        
        $memory_limit = trim($memory_limit);
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
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
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_rpm-booster') {
            return;
        }
        
        wp_enqueue_style(
            'rpm-booster-admin',
            RPM_BOOSTER_PLUGIN_URL . 'assets/style.css',
            array(),
            RPM_BOOSTER_VERSION
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
        
        // Handle preview request
        $preview_articles = array();
        if (isset($_POST['preview']) && wp_verify_nonce($_POST['rpm_booster_nonce'], 'rpm_booster_save')) {
            $this->save_settings();
            
            // For preview, limit to max 3 articles to avoid memory issues
            $preview_settings = RPM_Booster::get_settings();
            $preview_settings['num_articles'] = min(3, $preview_settings['num_articles']);
            
            // Use a lighter preview query
            $preview_articles = $this->get_preview_articles($preview_settings);
        }
        
        $settings = RPM_Booster::get_settings();
        $categories = get_categories(array('hide_empty' => false));
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('RPM Booster Settings', 'rpm-booster'); ?></h1>
            
            <?php $this->show_system_info(); ?>
            
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
                        <th scope="row"><?php echo esc_html__('Categories', 'rpm-booster'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php echo esc_html__('Select categories for sponsored content', 'rpm-booster'); ?>
                                </legend>
                                <?php if (empty($categories)) : ?>
                                    <p><?php echo esc_html__('No categories found. Please create some categories first.', 'rpm-booster'); ?></p>
                                <?php else : ?>
                                    <?php foreach ($categories as $category) : ?>
                                        <label>
                                            <input type="checkbox" 
                                                   name="rpm_booster_settings[categories][]" 
                                                   value="<?php echo esc_attr($category->term_id); ?>"
                                                   <?php checked(in_array($category->term_id, (array) $settings['categories'])); ?> />
                                            <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?>)
                                        </label><br>
                                    <?php endforeach; ?>
                                    <p class="description">
                                        <?php echo esc_html__('Leave empty to select from all categories. Selected categories will be used for sponsored content.', 'rpm-booster'); ?>
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Number of Articles', 'rpm-booster'); ?></th>
                        <td>
                            <select name="rpm_booster_settings[num_articles]">
                                <?php for ($i = 1; $i <= 10; $i++) : ?>
                                    <option value="<?php echo $i; ?>" <?php selected($settings['num_articles'], $i); ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Number of sponsored articles to display per post.', 'rpm-booster'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excerpt Mode', 'rpm-booster'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php echo esc_html__('Choose excerpt mode', 'rpm-booster'); ?>
                                </legend>
                                <label>
                                    <input type="radio" name="rpm_booster_settings[excerpt_mode]" value="words" 
                                           <?php checked($settings['excerpt_mode'], 'words'); ?> />
                                    <?php echo esc_html__('Words', 'rpm-booster'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="rpm_booster_settings[excerpt_mode]" value="sentences" 
                                           <?php checked($settings['excerpt_mode'], 'sentences'); ?> />
                                    <?php echo esc_html__('Sentences', 'rpm-booster'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excerpt Length', 'rpm-booster'); ?></th>
                        <td>
                            <input type="number" 
                                   name="rpm_booster_settings[excerpt_length]" 
                                   value="<?php echo esc_attr($settings['excerpt_length']); ?>"
                                   min="1" 
                                   max="<?php echo $settings['excerpt_mode'] === 'words' ? '500' : '10'; ?>" />
                            <p class="description">
                                <?php 
                                if ($settings['excerpt_mode'] === 'words') {
                                    echo esc_html__('Number of words (1-500)', 'rpm-booster');
                                } else {
                                    echo esc_html__('Number of sentences (1-10)', 'rpm-booster');
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Display Mode', 'rpm-booster'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php echo esc_html__('Choose display mode', 'rpm-booster'); ?>
                                </legend>
                                <label>
                                    <input type="radio" name="rpm_booster_settings[display_mode]" value="excerpt" 
                                           <?php checked($settings['display_mode'], 'excerpt'); ?> />
                                    <?php echo esc_html__('Excerpt only', 'rpm-booster'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="rpm_booster_settings[display_mode]" value="full" 
                                           <?php checked($settings['display_mode'], 'full'); ?> />
                                    <?php echo esc_html__('Full article content', 'rpm-booster'); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Choose whether to show excerpts or full content of sponsored articles.', 'rpm-booster'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'rpm-booster'), 'primary', 'submit'); ?>
                <?php submit_button(__('Preview', 'rpm-booster'), 'secondary', 'preview'); ?>
                
                <p>
                    <a href="<?php echo add_query_arg('clear_cache', '1', admin_url('options-general.php?page=rpm-booster')); ?>" 
                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear the cache?', 'rpm-booster')); ?>')">
                        <?php echo esc_html__('Clear Cache', 'rpm-booster'); ?>
                    </a>
                </p>
            </form>
            
            <?php if (!empty($preview_articles)) : ?>
                <div class="rpm-booster-preview-section">
                    <h2><?php echo esc_html__('Preview', 'rpm-booster'); ?></h2>
                    <div class="rpm-preview-container">
                        <?php RPM_Booster_Display::render_preview($preview_articles); ?>
                    </div>
                </div>
            <?php endif; ?>
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
            'categories' => isset($_POST['rpm_booster_settings']['categories']) 
                ? array_map('intval', $_POST['rpm_booster_settings']['categories']) 
                : array(),
            'num_articles' => intval($_POST['rpm_booster_settings']['num_articles']),
            'excerpt_mode' => sanitize_text_field($_POST['rpm_booster_settings']['excerpt_mode']),
            'excerpt_length' => intval($_POST['rpm_booster_settings']['excerpt_length']),
            'display_mode' => sanitize_text_field($_POST['rpm_booster_settings']['display_mode'])
        );
        
        update_option('rpm_booster_settings', $settings);
        RPM_Booster_Query::clear_cache();
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'rpm-booster') . '</p></div>';
    }
    
    public function validate_settings($input) {
        $validated = array();
        
        $validated['enabled'] = isset($input['enabled']) ? 1 : 0;
        $validated['categories'] = isset($input['categories']) && is_array($input['categories']) 
            ? array_map('intval', $input['categories']) 
            : array();
        $validated['num_articles'] = max(1, min(10, intval($input['num_articles'])));
        $validated['excerpt_mode'] = in_array($input['excerpt_mode'], array('words', 'sentences')) 
            ? $input['excerpt_mode'] 
            : 'words';
        
        if ($validated['excerpt_mode'] === 'words') {
            $validated['excerpt_length'] = max(1, min(500, intval($input['excerpt_length'])));
        } else {
            $validated['excerpt_length'] = max(1, min(10, intval($input['excerpt_length'])));
        }
        
        $validated['display_mode'] = in_array($input['display_mode'], array('excerpt', 'full')) 
            ? $input['display_mode'] 
            : 'excerpt';
        
        return $validated;
    }
}
