<?php
/**
 * Plugin Name: AutoBotWriter Free
 * Plugin URI:  https://autobotwriter.com
 * Description: WordPress plugin for generating and managing AI-powered blog posts.
 * Version:     1.5.0
 * Author:      autobotwriter.com
 * Author URI:  https://www.avansaber.com
 * License:     GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: auto-bot-writer
 * Domain Path: /languages
 */


if (!defined('ABSPATH')) {
    exit;  
}

if(defined('AIBOT_VERSION')) return;

DEFINE('AIBOT_TEST',true);
DEFINE('AIBOT_VERSION', '1.5.0');
DEFINE('AIBOT_URL',plugin_dir_url(__FILE__));

require_once 'includes/openai-class.php';
require_once 'includes/ajax-class.php';
require_once 'includes/admin-class.php';

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

// Initialize the plugin
if (class_exists('AIBotAdmin')) {
    AIBotAdmin::start();
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . __('AutoBotWriter: Required classes not found. Please reinstall the plugin.', 'auto-bot-writer') . '</p></div>';
    });
}