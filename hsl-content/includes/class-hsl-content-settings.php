<?php
if (!defined('ABSPATH')) {
    exit;
}

class HSL_Content_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_submenu_page(
            'hsl-content', // Parent slug
            'HSL Content Settings', // Page title
            'Settings', // Menu title
            'manage_options', // Capability
            'hsl-content-settings', // Menu slug
            [$this, 'render_settings_page'] // Callback
        );
    }

    public function register_settings() {
        register_setting('hsl_content_settings_group', 'hsl_content_openai_key');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>HSL Content Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hsl_content_settings_group');
                do_settings_sections('hsl_content_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="text" name="hsl_content_openai_key" value="<?php echo esc_attr(get_option('hsl_content_openai_key')); ?>" class="regular-text" />
                            <p class="description">Enter your OpenAI API key to enable article generation.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
