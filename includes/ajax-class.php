<?php
/**
 *  The class that hooks to WordPress AJAX endpoints all the pertinent functions.
 */

final class AIBotAjax{
    private static  $instance = null;

    function aibot_schedule_posts() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'auto-bot-writer'));
        }
        
        check_ajax_referer('aibot_schedule_posts', 'nonce');
        
        global $wpdb;

        $parameters = sanitize_text_field($_POST['parameters']);
        parse_str($parameters, $data);
        if (!is_array($data)) {
            wp_die(__('There has been an error with your request.', 'auto-bot-writer'));
        }

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        foreach ($data['title'] as $k => $v) {
            // Sanitize all inputs
            $title = sanitize_text_field(trim($data['title'][$k]));
            $broad_description = sanitize_textarea_field(trim($data['broaddescription']));
            $category = intval($data['category'][$k]);
            $author = intval($data['author'][$k]);

            if (isset($data['tags']) && isset($data['tags'][$k]) && is_array($data['tags'][$k]) && count($data['tags'][$k])) {
                $tags = implode(',', array_map('sanitize_text_field', array_map('trim', $data['tags'][$k])));
            } else {
                $tags = '';
            }

            $include_keywords = sanitize_text_field(trim($data['include'][$k]));
            $exclude_keywords = sanitize_text_field(trim($data['exclude'][$k]));

            $publish_date = sanitize_text_field(trim($data['date'][$k]));
            if (empty($publish_date)) {
                $publish_date = '0000-00-00';
            }

            $now = current_datetime();

            $wpdb->insert(
                $autobotwriter_posts_schedule_table, 
                [
                    'post_title' => $title,
                    'blog_title' => $broad_description,
                    'category' => $category,
                    'author_id' => $author,
                    'tags' => $tags,
                    'post_id' => 0,
                    'status' => 'pending',
                    'creation_date' => $now->format('Y-m-d H:i:s'),
                    'update_date' => '0000-00-00',
                    'publish_date' => $publish_date,
                    'include_keywords' => $include_keywords,
                    'exclude_keywords' => $exclude_keywords,
                ],
                [
                    '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
        }

        die('OK');
    }

    /**
     * @method This requests the posts titles when the corresponding option is selected during the wizard.
     *
     * */
    function aibot_get_titles(){
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'auto-bot-writer'));
        }
        
        check_ajax_referer('aibot_get_titles', 'nonce');
        
        $text = sanitize_textarea_field($_POST['broaddescription']);
        $number = intval($_POST['numberofposts']);
        
        // Validate inputs
        if (empty($text) || $number <= 0 || $number > 5) {
            wp_die(json_encode(['status' => 'error', 'message' => __('Invalid input parameters.', 'auto-bot-writer')]));
        }

        $result = OpenAi::atbtwrt_blog_topics_generate($text, $number);

        wp_die(json_encode($result));
    }

    /**
     * @method This makes a simple request to OpenAi to check the key is valid.
     *
     * */
    function aibot_validate_openai_key(){
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'auto-bot-writer'));
        }
        
        check_ajax_referer('aibot_validate_key', 'nonce');
        
        $key = sanitize_text_field($_POST['key']);
        
        // Validate API key format
        if (empty($key) || !preg_match('/^sk-[a-zA-Z0-9]{48}$/', $key)) {
            wp_die(json_encode(['status' => 'error', 'message' => __('Invalid API key format.', 'auto-bot-writer')]));
        }

        $result = OpenAi::ai_bot_writer_sync_models($key);

        if(is_array($result) && isset($result['data'])){
            update_option('autobotwriter_models', $result['data']);
            wp_die(json_encode($result));
        } else {
            wp_die(json_encode(['status' => 'error', 'message' => isset($result['msg']) ? $result['msg'] : __('API validation failed.', 'auto-bot-writer')]));
        }
    }

    /**
     * @method This uses the OpenAi class to save the settings input by user.
     *
     * */
    function aibot_save_settings(){
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'auto-bot-writer'));
        }
        
        check_ajax_referer('aibot_save_settings', 'nonce');

        // Sanitize and validate inputs
        $openai_api_key = sanitize_text_field($_POST['openai_api_key']);
        $selected_model = sanitize_text_field($_POST['ai_bot_writer_preferred_model']);
        
        // Validate and set defaults for numeric fields
        $tokens = isset($_POST['tokens']) && is_numeric($_POST['tokens']) ? intval($_POST['tokens']) : 800;
        $temperature = isset($_POST['temperature']) && is_numeric($_POST['temperature']) ? floatval($_POST['temperature']) : 0.1;
        $headings = isset($_POST['headings']) && is_numeric($_POST['headings']) ? intval($_POST['headings']) : 3;
        
        // Validate ranges
        $tokens = max(100, min(4000, $tokens)); // Limit tokens between 100-4000
        $temperature = max(0, min(2, $temperature)); // Limit temperature between 0-2
        $headings = max(1, min(10, $headings)); // Limit headings between 1-10
        
        // Set default model if empty
        if (empty($selected_model)) {
            $selected_model = 'gpt-3.5-turbo';
        }
        
        // Validate API key format
        if (!empty($openai_api_key) && !preg_match('/^sk-[a-zA-Z0-9]{48}$/', $openai_api_key)) {
            wp_die(json_encode(['status' => 'error', 'message' => __('Invalid API key format.', 'auto-bot-writer')]));
        }

        try {
            OpenAi::setSettings($openai_api_key, $selected_model, $tokens, $temperature, $headings);
            wp_die(json_encode(['status' => 'success', 'message' => __('Settings saved successfully.', 'auto-bot-writer')]));
        } catch (Exception $e) {
            wp_die(json_encode(['status' => 'error', 'message' => __('Failed to save settings.', 'auto-bot-writer')]));
        }
    }

    function aibot_heartbeat(){
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'auto-bot-writer'));
        }
        
        check_ajax_referer('aibot_heartbeat', 'nonce');

        list($y, $m) = explode('-', date('Y-m'));
        $option_index = 'autobotwriter_gen_' . $m . '-' . $y;
        $opt = get_option($option_index, 0);
        
        if ($opt >= 5) {
            wp_die(json_encode(['status' => 'limit_reached', 'message' => __('Monthly limit reached.', 'auto-bot-writer')]));
        }

        try {
            OpenAi::generateSection();
            wp_die(json_encode(['status' => 'success']));
        } catch (Exception $e) {
            wp_die(json_encode(['status' => 'error', 'message' => __('Generation failed.', 'auto-bot-writer')]));
        }
    }


    function __construct(){
        add_action('wp_ajax_aibot_validate_openai_key', [$this,'aibot_validate_openai_key']);
        add_action('wp_ajax_aibot_save_settings', [$this,'aibot_save_settings']);
        add_action('wp_ajax_aibot_get_titles', [$this,'aibot_get_titles']);
        add_action('wp_ajax_aibot_heartbeat', [$this,'aibot_heartbeat']);
        add_action('wp_ajax_aibot_schedule_posts', [$this,'aibot_schedule_posts']);
    }

    public static function getInstance(){
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}

AIBotAjax::getInstance();