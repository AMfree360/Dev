<?php
if (!defined('ABSPATH')) {
    exit;
}

class HSL_Content_Admin {
    private $generator;
    private $manager;

    public function __construct($generator, $manager) {
        $this->generator = $generator;
        $this->manager = $manager;
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'HSL Content',
            'HSL Content',
            'manage_options',
            'hsl-content',
            [$this, 'render_admin_page'],
            'dashicons-edit',
            6
        );
    }

    public function render_admin_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_form_submission();
        }

        // Render the admin page
        ?>
        <div class="wrap">
            <h1>HSL Content</h1>
            <form method="post">
                <label for="keywords">Enter up to 10 keywords (comma-separated):</label><br>
                <textarea id="keywords" name="keywords" rows="5" cols="50"></textarea><br><br>
                <input type="submit" name="generate" value="Generate Articles" class="button button-primary">
            </form>
        </div>
        <?php
    }

    private function handle_form_submission() {
        if (isset($_POST['keywords'])) {
            $keywords = explode(',', sanitize_text_field($_POST['keywords']));
            $articles = [];

            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $content = $this->generator->generate_article($keyword);
                    if ($content) {
                        $post_id = $this->manager->save_article($keyword, $content);
                        $articles[$keyword] = $post_id;
                    }
                }
            }

            // Generate JSON file
            $json = $this->manager->generate_json($articles);
            file_put_contents(plugin_dir_path(__FILE__) . '../assets/articles.json', $json);

            echo '<div class="notice notice-success"><p>Articles generated successfully!</p></div>';
        }
    }
}
