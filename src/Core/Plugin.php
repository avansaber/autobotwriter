<?php
/**
 * Main Plugin Class
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Core;

use AutoBotWriter\Admin\AdminManager;
use AutoBotWriter\API\OpenAIService;
use AutoBotWriter\Database\DatabaseManager;

/**
 * Main Plugin Class
 */
final class Plugin
{
    /**
     * Plugin version
     */
    public const VERSION = '1.6.0';

    /**
     * Plugin text domain
     */
    public const TEXT_DOMAIN = 'auto-bot-writer';

    /**
     * Plugin instance
     */
    private static ?self $instance = null;

    /**
     * Plugin file path
     */
    private string $plugin_file;

    /**
     * Plugin directory path
     */
    private string $plugin_dir;

    /**
     * Plugin URL
     */
    private string $plugin_url;

    /**
     * Admin manager instance
     */
    private ?AdminManager $admin_manager = null;

    /**
     * Database manager instance
     */
    private ?DatabaseManager $database_manager = null;

    /**
     * OpenAI service instance
     */
    private ?OpenAIService $openai_service = null;

    /**
     * Constructor
     *
     * @param string $plugin_file Main plugin file path
     */
    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_dir = plugin_dir_path($plugin_file);
        $this->plugin_url = plugin_dir_url($plugin_file);
    }

    /**
     * Get plugin instance
     *
     * @param string|null $plugin_file Main plugin file path
     * @return self
     */
    public static function get_instance(?string $plugin_file = null): self
    {
        if (null === self::$instance) {
            if (null === $plugin_file) {
                throw new \InvalidArgumentException('Plugin file path is required for first instantiation');
            }
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init(): void
    {
        // Load text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Initialize components
        add_action('init', [$this, 'init_components']);

        // Register activation/deactivation hooks
        register_activation_hook($this->plugin_file, [$this, 'activate']);
        register_deactivation_hook($this->plugin_file, [$this, 'deactivate']);
    }

    /**
     * Load plugin text domain
     *
     * @return void
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    public function init_components(): void
    {
        try {
            // Initialize database manager
            $this->database_manager = new DatabaseManager();

            // Initialize OpenAI service
            $this->openai_service = new OpenAIService();

            // Initialize admin manager (only in admin)
            if (is_admin()) {
                $this->admin_manager = new AdminManager($this);
            }

        } catch (\Exception $e) {
            error_log('AutoBotWriter: Failed to initialize components - ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        /* translators: %s: error message */
                        esc_html__('AutoBotWriter initialization failed: %s', self::TEXT_DOMAIN),
                        esc_html($e->getMessage())
                    )
                );
            });
        }
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate(): void
    {
        try {
            // Check requirements
            $this->check_requirements();

            // Initialize database
            if (null === $this->database_manager) {
                $this->database_manager = new DatabaseManager();
            }
            $this->database_manager->create_tables();

            // Set activation options
            add_option('autobotwriter_version', self::VERSION);
            add_option('autobotwriter_activation_date', current_time('mysql'));

            // Flush rewrite rules
            flush_rewrite_rules();

        } catch (\Exception $e) {
            error_log('AutoBotWriter: Activation failed - ' . $e->getMessage());
            wp_die(
                sprintf(
                    /* translators: %s: error message */
                    esc_html__('AutoBotWriter activation failed: %s', self::TEXT_DOMAIN),
                    esc_html($e->getMessage())
                )
            );
        }
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate(): void
    {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('aibot_heartbeat_event');

            // Clear transients
            delete_transient('aibot_generate_section_lock');

            // Clean up temporary options
            $temp_options = [
                'aibot_current_article',
                'aibot_next_step',
                'aibot_current_intro',
                'aibot_current_headings',
                'aibot_current_contents',
                'aibot_current_conclusions',
                'aibot_final_conclusion',
                'aibot_generated_headings',
                'aibot_section_generated'
            ];

            foreach ($temp_options as $option) {
                delete_option($option);
            }

            // Flush rewrite rules
            flush_rewrite_rules();

        } catch (\Exception $e) {
            error_log('AutoBotWriter: Deactivation cleanup failed - ' . $e->getMessage());
        }
    }

    /**
     * Check plugin requirements
     *
     * @return void
     * @throws \Exception If requirements are not met
     */
    private function check_requirements(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            throw new \Exception('PHP 7.4 or higher is required');
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            throw new \Exception('WordPress 5.8 or higher is required');
        }

        // Check required extensions
        $required_extensions = ['openssl', 'curl', 'json'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new \Exception("PHP extension '{$extension}' is required");
            }
        }

        // Check user capabilities
        if (!current_user_can('activate_plugins')) {
            throw new \Exception('Insufficient permissions to activate plugin');
        }
    }

    /**
     * Get plugin file path
     *
     * @return string
     */
    public function get_plugin_file(): string
    {
        return $this->plugin_file;
    }

    /**
     * Get plugin directory path
     *
     * @return string
     */
    public function get_plugin_dir(): string
    {
        return $this->plugin_dir;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function get_plugin_url(): string
    {
        return $this->plugin_url;
    }

    /**
     * Get admin manager instance
     *
     * @return AdminManager|null
     */
    public function get_admin_manager(): ?AdminManager
    {
        return $this->admin_manager;
    }

    /**
     * Get database manager instance
     *
     * @return DatabaseManager|null
     */
    public function get_database_manager(): ?DatabaseManager
    {
        return $this->database_manager;
    }

    /**
     * Get OpenAI service instance
     *
     * @return OpenAIService|null
     */
    public function get_openai_service(): ?OpenAIService
    {
        return $this->openai_service;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
