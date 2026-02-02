<?php
/**
 * RPM Booster Query Handler - FIXED VERSION
 * Optimized for large databases (4000+ posts)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
        
        if ($current_post_id) {
            $where_conditions[] = $wpdb->prepare("ID != %d", $current_post_id);
        }
        
        // Category filter - simplified
        $join_category = '';
        if (!empty($settings['categories']) && is_array($settings['categories'])) {
            $category_ids = array_map('intval', array_slice($settings['categories'], 0, 5)); // Max 5 categories
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            
            $join_category = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $where_conditions[] = "tt.taxonomy = 'category'";
            $where_conditions[] = $wpdb->prepare("tt.term_id IN ({$placeholders})", $category_ids);
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
        
        $results = $wpdb->get_results($sql);
        
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
