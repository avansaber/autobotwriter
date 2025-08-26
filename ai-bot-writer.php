<?php
/**
 * Plugin Name: AutoBotWriter Free
 * Plugin URI:  https://autobotwriter.com
 * Description: WordPress plugin for generating and managing AI-powered blog posts with modern architecture and enhanced security.
 * Version:     1.6.0
 * Author:      autobotwriter.com
 * Author URI:  https://www.avansaber.com
 * License:     GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: auto-bot-writer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple initializations
if (defined('AUTOBOTWRITER_VERSION')) {
    return;
}

// Define plugin constants
define('AUTOBOTWRITER_VERSION', '1.6.0');
define('AUTOBOTWRITER_PLUGIN_FILE', __FILE__);
define('AUTOBOTWRITER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTOBOTWRITER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTOBOTWRITER_TEXT_DOMAIN', 'auto-bot-writer');

// Legacy constants for backward compatibility
define('AIBOT_VERSION', AUTOBOTWRITER_VERSION);
define('AIBOT_URL', AUTOBOTWRITER_PLUGIN_URL);

// Load autoloader
require_once AUTOBOTWRITER_PLUGIN_DIR . 'src/Core/Autoloader.php';

// Register autoloader
$autoloader = \AutoBotWriter\Core\Autoloader::register_autoloader(AUTOBOTWRITER_PLUGIN_DIR . 'src/');

// Load legacy classes for backward compatibility
require_once AUTOBOTWRITER_PLUGIN_DIR . 'includes/openai-class.php';
require_once AUTOBOTWRITER_PLUGIN_DIR . 'includes/ajax-class.php';
require_once AUTOBOTWRITER_PLUGIN_DIR . 'includes/admin-class.php';

function ai_bot_writer_activate() {
    global $wpdb;
    
    // Check if user has proper capabilities
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create posts schedule table
    $table_name = $wpdb->prefix . 'autobotwriter_posts_schedule';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,  
                `post_title` varchar(255) NOT NULL DEFAULT '', 
                `blog_title` varchar(255) NOT NULL DEFAULT '',  
                `category` int(11) NOT NULL DEFAULT 0, 
                `author_id` int(11) NOT NULL DEFAULT 1,
                `tags` TEXT NOT NULL,
                `post_id` int(11) NOT NULL DEFAULT 0, 
                `creation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
                `update_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                `publish_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                `include_keywords` varchar(255) NOT NULL DEFAULT '', 
                `exclude_keywords` varchar(255) NOT NULL DEFAULT '',  
                `status` varchar(16) NOT NULL DEFAULT 'pending',
                `published` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY status (status),
                KEY creation_date (creation_date),
                KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    if (empty($result)) {
        error_log('AutoBotWriter: Failed to create posts schedule table');
    }

    // Create parameters table
    $table_name = $wpdb->prefix . 'autobotwriter_parameters';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,  
                `openai_api_key` TEXT NOT NULL, 
                `selected_model` varchar(50) NOT NULL DEFAULT 'gpt-3.5-turbo',
                `tokens` int(11) NOT NULL DEFAULT 800,
                `temperature` decimal(3,2) NOT NULL DEFAULT 0.10,
                `headings` int(11) NOT NULL DEFAULT 3,
                PRIMARY KEY (id)
    ) $charset_collate;";

    $result = dbDelta($sql);
    
    if (empty($result)) {
        error_log('AutoBotWriter: Failed to create parameters table');
    }
    
    // Set default options
    add_option('autobotwriter_version', AIBOT_VERSION);
    add_option('autobotwriter_activation_date', current_time('mysql'));
}

// Deactivation hook
function ai_bot_writer_deactivate() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('aibot_heartbeat_event');
    
    // Clear any transients
    delete_transient('aibot_generate_section_lock');
    
    // Clean up temporary options
    delete_option('aibot_current_article');
    delete_option('aibot_next_step');
    delete_option('aibot_current_intro');
    delete_option('aibot_current_headings');
    delete_option('aibot_current_contents');
    delete_option('aibot_current_conclusions');
    delete_option('aibot_final_conclusion');
    delete_option('aibot_generated_headings');
    delete_option('aibot_section_generated');
}

register_activation_hook(__FILE__, 'ai_bot_writer_activate');
register_deactivation_hook(__FILE__, 'ai_bot_writer_deactivate');

/**
 * Initialize the plugin
 */
function autobotwriter_init() {
    try {
        // Initialize modern plugin architecture
        $plugin = \AutoBotWriter\Core\Plugin::get_instance(AUTOBOTWRITER_PLUGIN_FILE);
        $plugin->init();

        // Keep legacy initialization for backward compatibility
        if (class_exists('AIBotAdmin')) {
            AIBotAdmin::start();
        }

    } catch (\Exception $e) {
        error_log('AutoBotWriter initialization failed: ' . $e->getMessage());
        
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: error message */
                    esc_html__('AutoBotWriter initialization failed: %s', AUTOBOTWRITER_TEXT_DOMAIN),
                    esc_html($e->getMessage())
                )
            );
        });
    }
}

// Initialize plugin
autobotwriter_init();

/**
 * Get plugin instance (for external access)
 *
 * @return \AutoBotWriter\Core\Plugin|null
 */
function autobotwriter() {
    try {
        return \AutoBotWriter\Core\Plugin::get_instance();
    } catch (\Exception $e) {
        return null;
    }
}