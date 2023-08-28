<?php
/**
 *  The class that hooks to WordPress AJAX endpoints all the pertinent functions.
 */

final class AIBotAjax{
    private static  $instance = null;

    function aibot_schedule_posts() {
        global $wpdb;

        parse_str($_POST['parameters'], $data);
        if (!is_array($data)) {
            die(__('There has been an error with your request.', 'aibotw'));
        }

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        foreach ($data['title'] as $k => $v) {

            $data['title'][$k] = trim($data['title'][$k]);
            $data['broaddescription'] = trim($data['broaddescription']);
            $data['category'][$k] = trim($data['category'][$k]);
            $data['author'][$k] = trim($data['author'][$k]);

            if (isset($data['tags']) && isset($data['tags'][$k]) && is_array($data['tags'][$k]) && count($data['tags'][$k])) {
                $data['tags'][$k] = implode(',', array_map('trim', $data['tags'][$k]));
            } else {
                $data['tags'][$k] = '';
            }

            $data['include'][$k] = trim($data['include'][$k]);

            if (trim($data['date'][$k]) == '') {
                $data['date'][$k] = '0000-00-00';
            }

            $data['exclude'][$k] = trim($data['exclude'][$k]);
            $now = current_datetime();

            $wpdb->insert($autobotwriter_posts_schedule_table, [
                'post_title' => $data['title'][$k],
                'blog_title' => $data['broaddescription'],
                'category' => intval($data['category'][$k]),
                'author_id' => $data['author'][$k],
                'tags' => $data['tags'][$k],
                'post_id' => 0,
                'status' => 'pending',
                'creation_date' => $now->format('Y-m-d H:i:s'),
                'update_date' => '0000-00-00',
                'publish_date' => $data['date'][$k],
                'include_keywords' => $data['include'][$k],
                'exclude_keywords' => $data['exclude'][$k],
            ]);
        }

        die('OK');
    }

    /**
     * @method This requests the posts titles when the corresponding option is selected during the wizard.
     *
     * */
    function aibot_get_titles(){
        $text = sanitize_text_field($_POST['broaddescription']);
        $number = sanitize_text_field($_POST['numberofposts']);

        $result = OpenAi::atbtwrt_blog_topics_generate($text,intval($number));

        die(json_encode($result));

    }

    /**
     * @method This makes a simple request to OpenAi to check the key is valid.
     *
     * */
    function aibot_validate_openai_key(){
        $key = sanitize_text_field($_POST['key']);

        $result = OpenAi::ai_bot_writer_sync_models( $key );

        if(is_array($result) && isset($result['data']) ){
            update_option('autobotwriter_models',$result['data']);
            die(json_encode($result));
        }
        else{
            die($result['msg']);
        }

    }

    /**
     * @method This uses the OpenAi class to save the settings input by user.
     *
     * */
    function aibot_save_settings(){

        $openai_api_key = sanitize_text_field($_POST['openai_api_key']);
        $selected_model = sanitize_text_field($_POST['ai_bot_writer_preferred_model']);
        if(!isset($_POST['tokens']) || trim($_POST['tokens'])=='' || !is_numeric($_POST['tokens'])){
            $_POST['tokens']='800';
        }
        if(!isset($_POST['temperature']) || trim($_POST['temperature'])=='' || !is_numeric($_POST['temperature'])){
            $_POST['temperature']='0.1';
        }
        if(!isset($_POST['headings']) || trim($_POST['headings'])=='' || !is_numeric($_POST['headings'])){
            $_POST['headings']='3';
        }
        if(!isset($_POST['ai_bot_writer_preferred_model']) || trim($_POST['ai_bot_writer_preferred_model'])==''){
            $_POST['ai_bot_writer_preferred_model']='gpt-3.5-turbo';
        }

        $openai_api_key = sanitize_text_field($_POST['openai_api_key']);
        $selected_model = sanitize_text_field($_POST['ai_bot_writer_preferred_model']);
        $tokens = sanitize_text_field($_POST['tokens']);
        $headings = sanitize_text_field($_POST['headings']);
        $temperature = sanitize_text_field($_POST['temperature']);
        OpenAi::setSettings($openai_api_key,$selected_model,$tokens,$temperature,$headings);
        die('OK');
    }

    function aibot_heartbeat(){

        list($y,$m) = explode('-', date('Y-m'));
        $option_index = 'autobotwriter_gen_'.$m.'-'.$y;
        $opt = get_option($option_index,0);
        if($opt>=5)
            die('OK');

        OpenAi::generateSection();

        die('OK');
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