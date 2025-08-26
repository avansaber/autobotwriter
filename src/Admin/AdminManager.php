<?php
/**
 * Admin Manager
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Admin;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Database\DatabaseManager;

/**
 * Admin Manager Class
 */
class AdminManager
{
    /**
     * Plugin instance
     */
    private Plugin $plugin;

    /**
     * Database manager instance
     */
    private DatabaseManager $db_manager;

    /**
     * Constructor
     *
     * @param Plugin $plugin Plugin instance
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->db_manager = new DatabaseManager();

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        add_action('delete_post', [$this, 'handle_post_deletion'], 10, 2);

        // AJAX handlers
        add_action('wp_ajax_aibot_validate_openai_key', [$this, 'ajax_validate_api_key']);
        add_action('wp_ajax_aibot_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_aibot_get_titles', [$this, 'ajax_get_titles']);
        add_action('wp_ajax_aibot_heartbeat', [$this, 'ajax_heartbeat']);
        add_action('wp_ajax_aibot_schedule_posts', [$this, 'ajax_schedule_posts']);
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            __('AutoBotWriter', Plugin::TEXT_DOMAIN),
            __('AutoBotWriter', Plugin::TEXT_DOMAIN),
            'manage_options',
            'autobotwriter',
            [$this, 'render_admin_page'],
            'dashicons-editor-paste-text',
            20
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueue_admin_assets(string $hook_suffix): void
    {
        // Only load on our admin page
        if (strpos($hook_suffix, 'autobotwriter') === false) {
            return;
        }

        $plugin_url = $this->plugin->get_plugin_url();
        $version = Plugin::VERSION;

        // Styles
        wp_enqueue_style(
            'autobotwriter-admin',
            $plugin_url . 'css/autobotwriter.css',
            [],
            $version
        );

        // External dependencies
        wp_enqueue_style('datatables', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css', [], $version);
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], $version);
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', [], $version);

        // Scripts
        wp_enqueue_script(
            'autobotwriter-admin',
            $plugin_url . 'js/autobotwriter.js',
            ['jquery'],
            $version,
            true
        );

        // External dependencies
        wp_enqueue_script('datatables', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js', ['jquery'], $version, true);
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', ['jquery'], $version, true);
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], $version, true);

        // Localize script
        wp_localize_script('autobotwriter-admin', 'autobotwriter', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => [
                'validate_key' => wp_create_nonce('aibot_validate_key'),
                'save_settings' => wp_create_nonce('aibot_save_settings'),
                'get_titles' => wp_create_nonce('aibot_get_titles'),
                'heartbeat' => wp_create_nonce('aibot_heartbeat'),
                'schedule_posts' => wp_create_nonce('aibot_schedule_posts'),
                'erase_all' => wp_create_nonce('aibot_erase_all'),
            ],
            'users' => $this->get_users_for_js(),
            'categories' => $this->get_categories_for_js(),
            'tags' => $this->get_tags_for_js(),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', Plugin::TEXT_DOMAIN),
                'processing' => __('Processing...', Plugin::TEXT_DOMAIN),
                'error' => __('An error occurred. Please try again.', Plugin::TEXT_DOMAIN),
            ]
        ]);
    }

    /**
     * Handle admin actions
     *
     * @return void
     */
    public function handle_admin_actions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle erase all action
        if (isset($_GET['action']) && $_GET['action'] === 'erase_all' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'aibot_erase_all')) {
                $this->handle_erase_all();
            } else {
                wp_die(__('Security check failed.', Plugin::TEXT_DOMAIN));
            }
        }
    }

    /**
     * Handle post deletion
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function handle_post_deletion(int $post_id, \WP_Post $post): void
    {
        // Update corresponding schedule record
        global $wpdb;
        $table = $this->db_manager->get_posts_table();
        
        $wpdb->update(
            $table,
            ['status' => 'deleted'],
            ['post_id' => $post_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Render admin page
     *
     * @return void
     */
    public function render_admin_page(): void
    {
        $current_tab = $_GET['tab'] ?? 'general';
        $results = $this->db_manager->get_post_schedules();
        $settings = $this->get_decrypted_settings();

        include $this->plugin->get_plugin_dir() . 'templates/admin-page.php';
    }

    /**
     * AJAX: Validate OpenAI API key
     *
     * @return void
     */
    public function ajax_validate_api_key(): void
    {
        $this->verify_ajax_request('aibot_validate_key');

        $api_key = sanitize_text_field($_POST['key'] ?? '');

        if (empty($api_key) || !$this->is_valid_api_key_format($api_key)) {
            wp_send_json_error(__('Invalid API key format.', Plugin::TEXT_DOMAIN));
        }

        $openai_service = $this->plugin->get_openai_service();
        $result = $openai_service->validate_api_key($api_key);

        if ($result['status'] === 'success') {
            // Store available models
            update_option('autobotwriter_models', $result['data']);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message'] ?? __('API validation failed.', Plugin::TEXT_DOMAIN));
        }
    }

    /**
     * AJAX: Save settings
     *
     * @return void
     */
    public function ajax_save_settings(): void
    {
        $this->verify_ajax_request('aibot_save_settings');

        $settings = [
            'openai_api_key' => sanitize_text_field($_POST['openai_api_key'] ?? ''),
            'selected_model' => sanitize_text_field($_POST['ai_bot_writer_preferred_model'] ?? 'gpt-3.5-turbo'),
            'tokens' => $this->validate_range((int) ($_POST['tokens'] ?? 800), 100, 4000),
            'temperature' => $this->validate_range((float) ($_POST['temperature'] ?? 0.1), 0, 2),
            'headings' => $this->validate_range((int) ($_POST['headings'] ?? 3), 1, 10)
        ];

        // Validate API key format if provided
        if (!empty($settings['openai_api_key']) && !$this->is_valid_api_key_format($settings['openai_api_key'])) {
            wp_send_json_error(__('Invalid API key format.', Plugin::TEXT_DOMAIN));
        }

        // Encrypt API key
        if (!empty($settings['openai_api_key'])) {
            $encryption = new \AutoBotWriter\Utils\Encryption();
            $settings['openai_api_key'] = $encryption->encrypt($settings['openai_api_key']);
        }

        if ($this->db_manager->save_settings($settings)) {
            wp_send_json_success(__('Settings saved successfully.', Plugin::TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to save settings.', Plugin::TEXT_DOMAIN));
        }
    }

    /**
     * AJAX: Get blog titles
     *
     * @return void
     */
    public function ajax_get_titles(): void
    {
        $this->verify_ajax_request('aibot_get_titles');

        $description = sanitize_textarea_field($_POST['broaddescription'] ?? '');
        $num_posts = (int) ($_POST['numberofposts'] ?? 1);

        if (empty($description) || $num_posts <= 0 || $num_posts > 5) {
            wp_send_json_error(__('Invalid input parameters.', Plugin::TEXT_DOMAIN));
        }

        $openai_service = $this->plugin->get_openai_service();
        $result = $openai_service->generate_blog_topics($description, $num_posts);

        wp_send_json($result);
    }

    /**
     * AJAX: Heartbeat for content generation
     *
     * @return void
     */
    public function ajax_heartbeat(): void
    {
        $this->verify_ajax_request('aibot_heartbeat');

        // Check monthly limit
        $current_month = date('Y-m');
        $limit_option = 'autobotwriter_gen_' . $current_month;
        $current_count = (int) get_option($limit_option, 0);

        if ($current_count >= 5) {
            wp_send_json([
                'status' => 'limit_reached',
                'message' => __('Monthly limit reached.', Plugin::TEXT_DOMAIN)
            ]);
        }

        // Process content generation
        try {
            $content_processor = new ContentProcessor($this->plugin);
            $result = $content_processor->process_next_section();
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Schedule posts
     *
     * @return void
     */
    public function ajax_schedule_posts(): void
    {
        $this->verify_ajax_request('aibot_schedule_posts');

        $parameters = sanitize_text_field($_POST['parameters'] ?? '');
        parse_str($parameters, $data);

        if (!is_array($data) || empty($data['title'])) {
            wp_send_json_error(__('Invalid request data.', Plugin::TEXT_DOMAIN));
        }

        $scheduled_count = 0;

        foreach ($data['title'] as $index => $title) {
            $post_data = [
                'post_title' => sanitize_text_field($title),
                'blog_title' => sanitize_textarea_field($data['broaddescription'] ?? ''),
                'category' => (int) ($data['category'][$index] ?? 0),
                'author_id' => (int) ($data['author'][$index] ?? 1),
                'tags' => $this->sanitize_tags($data['tags'][$index] ?? []),
                'publish_date' => $this->sanitize_date($data['date'][$index] ?? ''),
                'include_keywords' => sanitize_text_field($data['include'][$index] ?? ''),
                'exclude_keywords' => sanitize_text_field($data['exclude'][$index] ?? ''),
            ];

            if ($this->db_manager->insert_post_schedule($post_data)) {
                $scheduled_count++;
            }
        }

        if ($scheduled_count > 0) {
            wp_send_json_success(
                sprintf(
                    /* translators: %d: number of posts scheduled */
                    _n('%d post scheduled successfully.', '%d posts scheduled successfully.', $scheduled_count, Plugin::TEXT_DOMAIN),
                    $scheduled_count
                )
            );
        } else {
            wp_send_json_error(__('Failed to schedule posts.', Plugin::TEXT_DOMAIN));
        }
    }

    /**
     * Handle erase all action
     *
     * @return void
     */
    private function handle_erase_all(): void
    {
        global $wpdb;
        
        $posts_table = $this->db_manager->get_posts_table();
        
        // Get all post IDs to delete
        $post_ids = $wpdb->get_col("SELECT post_id FROM {$posts_table} WHERE post_id > 0");
        
        // Delete WordPress posts
        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        // Clear schedule table
        $wpdb->query("TRUNCATE TABLE {$posts_table}");
        
        wp_redirect(add_query_arg(['message' => 'erased'], admin_url('admin.php?page=autobotwriter')));
        exit;
    }

    /**
     * Verify AJAX request
     *
     * @param string $action Nonce action
     * @return void
     */
    private function verify_ajax_request(string $action): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', Plugin::TEXT_DOMAIN));
        }

        if (!check_ajax_referer($action, 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', Plugin::TEXT_DOMAIN));
        }
    }

    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @return bool True if valid format
     */
    private function is_valid_api_key_format(string $api_key): bool
    {
        return preg_match('/^sk-[a-zA-Z0-9]{48,}$/', $api_key);
    }

    /**
     * Validate numeric value within range
     *
     * @param int|float $value Value to validate
     * @param int|float $min Minimum value
     * @param int|float $max Maximum value
     * @return int|float Validated value
     */
    private function validate_range($value, $min, $max)
    {
        return max($min, min($max, $value));
    }

    /**
     * Sanitize tags array
     *
     * @param array $tags Tags array
     * @return string Comma-separated sanitized tags
     */
    private function sanitize_tags(array $tags): string
    {
        if (empty($tags)) {
            return '';
        }

        $sanitized = array_map('sanitize_text_field', $tags);
        return implode(',', array_filter($sanitized));
    }

    /**
     * Sanitize date string
     *
     * @param string $date Date string
     * @return string Sanitized date
     */
    private function sanitize_date(string $date): string
    {
        $date = sanitize_text_field($date);
        return empty($date) ? '0000-00-00 00:00:00' : $date;
    }

    /**
     * Get users for JavaScript
     *
     * @return array Users array
     */
    private function get_users_for_js(): array
    {
        return get_users([
            'role__not_in' => ['subscriber'],
            'fields' => ['ID', 'display_name']
        ]);
    }

    /**
     * Get categories for JavaScript
     *
     * @return array Categories array
     */
    private function get_categories_for_js(): array
    {
        return get_categories(['hide_empty' => false]);
    }

    /**
     * Get tags for JavaScript
     *
     * @return array Tags array
     */
    private function get_tags_for_js(): array
    {
        return get_tags(['hide_empty' => false]);
    }

    /**
     * Get decrypted settings
     *
     * @return array Settings with decrypted API key
     */
    private function get_decrypted_settings(): array
    {
        $settings = $this->db_manager->get_settings();
        
        if (!empty($settings['openai_api_key'])) {
            try {
                $encryption = new \AutoBotWriter\Utils\Encryption();
                $settings['openai_api_key'] = $encryption->decrypt($settings['openai_api_key']);
            } catch (\Exception $e) {
                $settings['openai_api_key'] = '';
                error_log('AutoBotWriter: Failed to decrypt API key - ' . $e->getMessage());
            }
        }

        return $settings;
    }
}
