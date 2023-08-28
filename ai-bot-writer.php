<?php
/**
 * Plugin Name: AutoBotWriter Free
 * Plugin URI:  https://autobotwriter.com
 * Description: WordPress plugin for generating and managing AI-powered blog posts.
 * Version:     1.4.29
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
DEFINE('AIBOT_VERSION', '1.4.29');
DEFINE('AIBOT_URL',plugin_dir_url(__FILE__));

require_once 'includes/openai-class.php';
require_once 'includes/ajax-class.php';
require_once 'includes/admin-class.php';

function ai_bot_writer_activate() {

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'autobotwriter_posts_schedule';

    if($wpdb->get_var( "show tables like '$table_name'" ) != $table_name) {

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` int NOT NULL AUTO_INCREMENT,  
                    `post_title` varchar(255) NOT NULL, 
                    `blog_title` varchar(255) NOT NULL,  
                    `category` varchar(255) NOT NULL, 
                    `author_id` int(11) NOT NULL,
                    `tags` TEXT NOT NULL,
                    `post_id` int(11) NOT NULL, 
                    `creation_date` DATETIME NOT NULL, 
                    `update_date` DATETIME NOT NULL, 
                    `publish_date` DATETIME NOT NULL, 
                    `include_keywords` varchar(255) NOT NULL, 
                    `exclude_keywords` varchar(255) NOT NULL,  
                    `status` varchar(16) NOT NULL,
                    `published` int(11) NOT NULL,

                    PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }

    $table_name = $wpdb->prefix . 'autobotwriter_parameters';

    if($wpdb->get_var( "show tables like '$table_name'" ) != $table_name) {


        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` int NOT NULL AUTO_INCREMENT,  
                    `openai_api_key` varchar(255) NOT NULL, 
                    `selected_model` varchar(255) NOT NULL,
                    `tokens` varchar(255) NOT NULL,
                    `temperature` varchar(255) NOT NULL,
                    `headings` varchar(255) NOT NULL,

                    PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'ai_bot_writer_activate');

AIBotAdmin::start();