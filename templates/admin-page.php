<?php
/**
 * Admin Page Template
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use AutoBotWriter\Core\Plugin;

$text_domain = Plugin::TEXT_DOMAIN;
?>

<div class="wrap autobotwriter-admin">
    <h1><?php esc_html_e('AutoBotWriter', $text_domain); ?></h1>
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'erased'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('All records have been deleted successfully.', $text_domain); ?></p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="#general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('General', $text_domain); ?>
        </a>
        <a href="#writer" class="nav-tab <?php echo $current_tab === 'writer' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Content Writer', $text_domain); ?>
        </a>
        <a href="#history" class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('History', $text_domain); ?>
        </a>
        <a href="#settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Settings', $text_domain); ?>
        </a>
    </nav>

    <div class="autobotwriter-tab-content">
        <!-- General Tab -->
        <div id="general" class="autobotwriter-tab-panel <?php echo $current_tab === 'general' ? 'active' : ''; ?>">
            <?php include plugin_dir_path(__FILE__) . '../includes/general-tab.php'; ?>
        </div>

        <!-- Content Writer Tab -->
        <div id="writer" class="autobotwriter-tab-panel <?php echo $current_tab === 'writer' ? 'active' : ''; ?>">
            <?php include plugin_dir_path(__FILE__) . '../includes/abot-writer-tab.php'; ?>
        </div>

        <!-- History Tab -->
        <div id="history" class="autobotwriter-tab-panel <?php echo $current_tab === 'history' ? 'active' : ''; ?>">
            <?php include plugin_dir_path(__FILE__) . '../includes/history-tab.php'; ?>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="autobotwriter-tab-panel <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
            <?php include plugin_dir_path(__FILE__) . '../includes/settings-tab.php'; ?>
        </div>
    </div>
</div>

<style>
.autobotwriter-admin .autobotwriter-tab-panel {
    display: none;
    margin-top: 20px;
}

.autobotwriter-admin .autobotwriter-tab-panel.active {
    display: block;
}

.autobotwriter-admin .nav-tab-wrapper {
    margin-bottom: 0;
}

.autobotwriter-admin .notice {
    margin: 20px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Update active panel
        $('.autobotwriter-tab-panel').removeClass('active');
        $(target).addClass('active');
        
        // Update URL hash
        window.location.hash = target;
    });
    
    // Handle initial hash
    var hash = window.location.hash || '#general';
    $('.nav-tab[href="' + hash + '"]').trigger('click');
});
</script>
