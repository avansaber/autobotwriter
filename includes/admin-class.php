<?php
/**
 *  The class that hooks to WordPress AJAX endpoints all the pertinent functions.
 */

class AIBotAdmin{
    private static  $instance = null;

    /**
     * @method This adds the AutoBotWriter menu to Dashboard.
     *
     * */

    function enqueue(){

        wp_enqueue_style('ai-bot-writer-style', AIBOT_URL.'css/autobotwriter.css',[],AIBOT_VERSION);
        wp_enqueue_script('ai-bot-writer-script', AIBOT_URL.'js/autobotwriter.js', array('jquery'),AIBOT_VERSION);

        wp_enqueue_style('dt', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css',[],AIBOT_VERSION);
        wp_enqueue_script('dt', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js', array('jquery'),AIBOT_VERSION);

        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',[],AIBOT_VERSION);
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'),AIBOT_VERSION);

        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',[],AIBOT_VERSION);
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'),AIBOT_VERSION);

        wp_localize_script('ai-bot-writer-script', 'aibot', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'users' =>   get_users(
                [
                    'role__not_in' => 'subscriber'
                ]
            ),
            'tags' => $tags = get_tags(array( 'hide_empty' => false )),
            'categories' => $tags = get_categories(array( 'hide_empty' => false )),
            'nonces' => array(
                'schedule_posts' => wp_create_nonce('aibot_schedule_posts'),
                'get_titles' => wp_create_nonce('aibot_get_titles'),
                'validate_key' => wp_create_nonce('aibot_validate_key'),
                'save_settings' => wp_create_nonce('aibot_save_settings'),
                'heartbeat' => wp_create_nonce('aibot_heartbeat'),
                'erase_all' => wp_create_nonce('aibot_erase_all'),
            )
        ));
    }

    function ai_bot_writer_add_menu_pages() {
        add_menu_page(
            esc_html__('AutoBotWriter', 'ai-bot-writer'), // Page Title
            esc_html__('AutoBotWriter', 'ai-bot-writer'), // Menu Title
            'manage_options', // Capability
            'ai-bot-writer', // Menu Slug
            [$this,'ai_bot_writer_render_menu_page'], // Callback Function
            'dashicons-editor-paste-text', // Icon
            20 // Position
        );
    }

    /**
     * @method This renders our admin page.
     *
     * */

    function ai_bot_writer_render_menu_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autobotwriter_posts_schedule';
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE status != %s ORDER BY ID DESC", $table_name, 'deleted'));
        $options = OpenAi::getSettings();

        $autobotwriter_email = get_option('autobotwriter_email','');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AutoBotWriter', 'ai-bot-writer'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'ai-bot-writer'); ?></a>
                <a href="#abot-writer" class="nav-tab"><?php esc_html_e('AutoBotWriter', 'ai-bot-writer'); ?></a>
                <a href="#history" class="nav-tab"><?php esc_html_e('History', 'ai-bot-writer'); ?></a>
                <a href="#settings" class="nav-tab"><?php esc_html_e('Settings', 'ai-bot-writer'); ?></a>

            </h2>

            <div class="ai-bot-writer-tab-content">
                <div id="general">
                    <?php require_once plugin_dir_path(__FILE__) . 'general-tab.php'; ?>
                </div>
                <div id="abot-writer" style="display: none;">
                    <?php require_once plugin_dir_path(__FILE__) . 'abot-writer-tab.php'; ?>
                </div>
                <div id="history" style="display: none;">
                    <?php require_once plugin_dir_path(__FILE__) . 'history-tab.php'; ?>
                </div>
                <div id="settings" style="display: none;">
                    <?php require_once plugin_dir_path(__FILE__) . 'settings-tab.php'; ?>
                </div>
            </div>
        </div>
        <?php
    }

    function publish_any_pending(){
        global $wpdb;

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        if(isset($_GET['eraseall'])){
            // Security: Check nonce and user capabilities
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'auto-bot-writer'));
            }
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'aibot_erase_all')) {
                wp_die(__('Security check failed.', 'auto-bot-writer'));
            }

            // Delete posts linked with autobotwriter_posts_schedule records
            $post_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM %i", $autobotwriter_posts_schedule_table));
            if (!empty($post_ids)) {
                $post_ids_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->posts WHERE ID IN ($post_ids_placeholders)", ...$post_ids));
            }

            // Delete autobotwriter_posts_schedule records
            $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE 1", $autobotwriter_posts_schedule_table));

            wp_die(__('All records deleted successfully.', 'auto-bot-writer'));
        }
    }

    function update_record_on_deletion($id, $post) {
        global $wpdb;

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        $wpdb->update(
            $autobotwriter_posts_schedule_table,
            ['status' => 'deleted'],
            ['post_id' => intval($id)],
            ['%s'],
            ['%d']
        );
    }

    function __construct(){
        add_action('init',[$this,'publish_any_pending']);
        add_action('delete_post',[$this,'update_record_on_deletion'],10,2);
        add_action('admin_menu', [$this,'ai_bot_writer_add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this,'enqueue']);
    }

    public static function start(){
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}