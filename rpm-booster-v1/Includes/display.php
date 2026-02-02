<?php
/**
 * RPM Booster Display Handler
 * Handles frontend output and display logic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
                        
                        <?php if ($settings['display_mode'] === 'full') : ?>
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
                        
                        <?php if ($settings['display_mode'] === 'full') : ?>
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
