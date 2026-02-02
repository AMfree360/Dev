<?php
if (!defined('ABSPATH')) {
    exit;
}

class HSL_Content_Manager {
    public function save_article($keyword, $content) {
        $post_id = wp_insert_post([
            'post_title'   => $keyword,
            'post_content' => $content,
            'post_status'  => 'draft', // Save as draft
            'post_author'  => get_current_user_id(),
        ]);
        return $post_id;
    }

    public function publish_article($post_id) {
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'publish',
        ]);
    }

    public function generate_json($articles) {
        $data = [];
        foreach ($articles as $keyword => $post_id) {
            $data[] = [
                'keyword' => $keyword,
                'url'     => get_permalink($post_id),
            ];
        }
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
