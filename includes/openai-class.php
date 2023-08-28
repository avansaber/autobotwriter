<?php


class OpenAi
{

    static function ai_bot_writer_sync_models($api_key)
    {
        $url = 'https://api.openai.com/v1/models';

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
        );

        $response = wp_remote_get($url, $args);

        if (!is_wp_error($response) && isset($response['body'])) {
            return json_decode($response['body'], true);
        }

        return $response;
    }

    public static function atbtwrt_blog_topics_generate($text, $numTopics)
    {
        $prompt = "Generate $numTopics SEO-optimized blog titles based on the following subject: '$text'. The titles should be succinct and directly related to the subject. Please maintain a professional tone.";

        $atbtwrt_opts = [
            "prompt" => $prompt,
            "n" => $numTopics,
        ];

        return self::atbtwrt_request_fun($atbtwrt_opts);

    }

    public static function atbtwrt_request_fun($opts)
    {
        $settings = self::getSettings();

        $selected_model = $settings['selected_model'];
        $max_tokens = $settings['tokens'];
        $temperature = $settings['temperature'];

        if (!isset($opts['model']) || empty($opts['model'])) {
            $opts['model'] = $selected_model;
        }
        if (!isset($opts['max_tokens']) || empty($opts['max_tokens'])) {
            $opts['max_tokens'] = intval($max_tokens);
        }
        if (!isset($opts['temperature']) || empty($opts['temperature'])) {
            $opts['temperature'] = floatval($temperature);
        }

        if (!isset($selected_model) || empty($selected_model)) {
            $opts['model'] = $atbtwrt_engine;
        } else {
            $opts['model'] = $selected_model;
        }


        $result = [
            "status" => "failure",
            "tokens" => 0,
            "length" => 0,
        ];

        $chat_model = false;

        if ($selected_model === "gpt-3.5-turbo" || $selected_model === "gpt-4" || $selected_model === "gpt-4-0314" || $selected_model === "gpt-4-0613") {
            $chat_model = true;
            unset($opts["best_of"]);
            $opts["messages"] = [
                ["role" => "system", "content" => "You are a helpful assistant that provides information."],
                ["role" => "user", "content" => $opts["prompt"]]
            ];
            unset($opts["prompt"]);
            $complete = self::atbtopenai_chat($opts);
        } else {
            $complete = self::atbtopenai_completion($opts);
        }

        $complete = json_decode($complete);

        if (isset($complete->error)) {
            $result["msg"] = trim($complete->error->message);
            if (empty($result["msg"]) && isset($complete->error->code) && $complete->error->code === "invalid_api_key") {
                $result["msg"] = "Incorrect API key provided. You can find your API key at https://platform.openai.com/account/api-keys.";
            }
            if (strpos($result["msg"], "exceeded your current quota") !== false) {
                $result["msg"] .= " " . esc_html__("Please note that this message is coming from OpenAI and it is not related to our plugin. It means that you do not have enough credit from OpenAI. You can check your usage here: https://platform.openai.com/account/usage", "autobotwriter");
            }
        } else {
            if (isset($complete->choices) && is_array($complete->choices)) {
                $result["status"] = "success";
                if ($chat_model) {
                    $result["tokens"] = $complete->usage->total_tokens;
                    $result["data"] = isset($complete->choices[0]->message->content) ? trim($complete->choices[0]->message->content) : "";
                } else {
                    $result["tokens"] = $complete->usage->total_tokens;
                    $result["data"] = trim($complete->choices[0]->text);
                }
                if (empty($result["data"])) {
                    $result["status"] = "failure";
                    $result["msg"] = esc_html__("The model predicted a completion that begins with a stop sequence, resulting in no output. Consider adjusting your prompt or stop sequences.", "autobotwriter");
                } else {
                    $result["length"] = self::atbtwrt_count_words($result["data"]);
                }
            } else {
                $result["msg"] = esc_html__("The model predicted a completion that begins with a stop sequence, resulting in no output. Consider adjusting your prompt or stop sequences.", "autobotwriter");
            }
        }

        return $result;
    }

    public static function getSettings()
    {
        global $wpdb;

        $autobotwriter_parameters_table = $wpdb->prefix . 'autobotwriter_parameters';

        $settings = $wpdb->get_row("SELECT * FROM $autobotwriter_parameters_table;", ARRAY_A);

        if (!$settings) {
            return [
                'openai_api_key' => '',
                'selected_model' => 'gpt-3.5-turbo',
                'tokens' => '700',
                'temperature' => '0.1',
                'headings' => '3'
            ];
        }

        $settings['openai_api_key'] = self::decrypt($settings['openai_api_key']);
        return $settings;
    }

    private static function decrypt($string)
    {
        $ciphering_value = "AES-128-CTR";
        $s = "AIBOTWriter2023";
        $options = 0;
        $iv = '1234567891011121';
        $value = openssl_decrypt($string, $ciphering_value, $s, $options, $iv);
        return $value;
    }

    private static function atbtopenai_chat($opts)
    {
        $options = self::getSettings();
        $api_key = $options['openai_api_key'];

        if (isset($opts['api_key']) && !empty($opts['api_key'])) {
            $api_key = $opts['api_key'];
        }

        $url = "https://api.openai.com/v1/chat/completions";
        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $api_key,
        ];

        $response = self::sendRequest($url, "POST", $opts, $headers);

        return $response;
    }

    private static function sendRequest(string $url, string $method, array $opts = [], array $headers = [])
    {

        if (isset($opts['api_key'])) {
            unset($opts['api_key']);
        }

        $post_fields = json_encode($opts);

        $request_options = [
            "timeout" => 1200,
            "headers" => $headers,
            "method" => $method,
            "body" => $post_fields,
        ];

        if ($post_fields === "[]") {
            unset($request_options["body"]);
        }

        $response = wp_remote_request($url, $request_options);

        if (is_wp_error($response)) {
            // Handle request error
            return json_encode([
                "failure" => [
                    "message" => $response->get_error_message(),
                ],
            ]);
        } else {
            return wp_remote_retrieve_body($response);
        }
    }

    private static function atbtopenai_completion($opts)
    {
        $options = self::getSettings();
        $api_key = $options['openai_api_key'];

        if (isset($opts['api_key']) && !empty($opts['api_key'])) {
            $api_key = $opts['api_key'];
        }

        $url = "https://api.openai.com/v1/completions";
        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $api_key,
        ];

        $response = self::sendRequest($url, "POST", $opts, $headers);

        return $response;
    }

    private static function atbtwrt_count_words($text)
    {
        $text = trim(strip_tags(html_entity_decode($text, ENT_QUOTES)));
        $text = preg_replace("/[\n]+/", " ", $text);
        $text = preg_replace("/[\s]+/", " ", $text);
        $words = explode(" ", $text);
        $count = count($words);
        return $count;
    }

    public static function generateSection()
    {
        global $wpdb;
        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        $lock_key = 'aibot_generate_section_lock';
        $lock_duration = 5; // Lock duration in seconds
        $is_locked = get_transient($lock_key);
        if ($is_locked) {
            return; // The function is already running, exit.
        }

        set_transient($lock_key, true, $lock_duration);

        $settings = self::getSettings();
        $current_article = get_option('aibot_current_article', null);
        if (!$current_article)
            $current_article = self::getNextPendingArticle();

        if (!$current_article) {
            die('OK');
        }

        $current_step = get_option('aibot_next_step', null);

        if (!$current_step) {
            $current_step = 'intro';
            update_option('aibot_next_step', 'intro');
            $wpdb->update($autobotwriter_posts_schedule_table, ['status' => 'processing'], ['id' => $current_article]);
            update_option('aibot_current_article', $current_article);
        }

        $atbtwrt_prompt = self::getArticleTitle($current_article);
        $record = self::getArticleRecord($current_article);
        $array_include = explode(',', $record->include_keywords);
        $include = implode(",", array_map(function ($v) {
            return "'$v'";
        }, $array_include));
        $array_exclude = explode(',', $record->exclude_keywords);
        $exclude = implode(",", array_map(function ($v) {
            return "'$v'";
        }, $array_exclude));

        switch ($current_step) {
            case "intro":

                update_option('aibot_next_step', "in-process");

                //error_log('Generating Introduction ?');
                if (!get_option('aibot_current_intro', null)) {
                    $intro = self::generate_intro($atbtwrt_prompt, $include, $exclude);
                    //error_log('Generated Introduction');
                    update_option('aibot_current_intro', $intro);
                    update_option('aibot_next_step', 'heading');
                }

                break;

            case "heading":

                $headings_amount = $settings['headings'];

                update_option('aibot_next_step', "in-process");

                $atbtwrt_headings = get_option('aibot_current_headings', []);

                if (count($atbtwrt_headings) <= 0) ;
                {
                    //error_log('Generating HEADINGS');
                    $atbtwrt_headings = self::generate_headings($headings_amount, $atbtwrt_prompt, null, $include, $exclude);

                    //error_log('Generated HEADINGS : ' . implode(',', $atbtwrt_headings));
                    update_option('aibot_current_headings', $atbtwrt_headings);

                    update_option('aibot_generated_headings', 0);
                }

                update_option('aibot_next_step', "content");

                break;

            case "content":

                update_option('aibot_next_step', "in-process");

                $atbtwrt_headings = get_option('aibot_current_headings', []);
                $headings_amount = count($atbtwrt_headings);
                $already_gen = get_option('aibot_generated_headings', 0);
                if ($already_gen >= $headings_amount) {

                    //Get entire content
                    $conclusion_list = get_option('aibot_current_conclusions', []);
                    $conclusion_content = "";
                    foreach ($conclusion_list as $key => $value) {
                        $conclusion_content .= $value;
                    }
                    //error_log('Generating conclusion');
                    $conclusion = self::generate_final_conclusion($conclusion_content, $include, $exclude);
                    update_option('aibot_final_conclusion', $conclusion);

                    //error_log('Generated conclusion ' . $conclusion);

                    update_option('aibot_next_step', "wrapup");

                } else {

                    if (!get_option('aibot_section_generated', false)) {

                        update_option('aibot_section_generated', true);
                        //error_log('Generating section for ' . $atbtwrt_headings[$already_gen]);
                        $text_object = self::generate_content($atbtwrt_headings[$already_gen], $include, $exclude);
                        $contents_list = get_option('aibot_current_contents', []);
                        $conclusion_list = get_option('aibot_current_conclusions', []);

                        //error_log('Generated section for ' . $atbtwrt_headings[$already_gen]);
                        $contents_list[$atbtwrt_headings[$already_gen]] = $text_object["content"];
                        $conclusion_list[$atbtwrt_headings[$already_gen]] = $text_object["conclusion"];

                        update_option('aibot_current_contents', $contents_list);
                        update_option('aibot_current_conclusions', $conclusion_list);


                    } else {

                        /*
                        $faq = self::generate_faq_section($atbtwrt_headings[$already_gen], $include, $exclude);
                        $faq_list = get_option('aibot_current_faq', []);
                        $faq_list[$atbtwrt_headings[$already_gen]] = $faq;
                        ////error_log('Generating FAQ for ' . $atbtwrt_headings[$already_gen]);
                        update_option('aibot_current_faq', $faq_list);
                        */
                        //error_log('Generating FAQ for ' . $atbtwrt_headings[$already_gen]);
                        delete_option('aibot_section_generated');
                        update_option('aibot_generated_headings', $already_gen + 1);
                    }

                    update_option('aibot_next_step', "content");

                }
                break;

            case "wrapup":

                $final_content = get_option('aibot_current_intro', '');

                $contents_list = get_option('aibot_current_contents', []);

                foreach ($contents_list as $key => $value) {
                    $final_content .= '<br/><br/>' . $key . '<br/><br/>' . $value;
                }
                $final_content .= get_option('aibot_final_conclusion', '');

                /*
                $final_content .= "\nFinal FAQ:\n\n";

                $faq_list = get_option('aibot_current_faq', []);

                foreach ($faq_list as $key => $value) {
                    $final_content .= $value . '<br/>';
                }
                */

                $final_content = str_replace("\n", '<br/>', $final_content);

                self::createPost($current_article, $atbtwrt_prompt, $final_content);
                delete_option('aibot_current_article');
                delete_option('aibot_next_step');
                delete_option('aibot_current_intro');
                delete_option('aibot_headings');
                delete_option('aibot_generated_headings');
                delete_option('aibot_current_headings');
                delete_option('aibot_current_contents');
                delete_option('aibot_current_conclusions');
                delete_option('aibot_final_conclusion');
                //delete_option('aibot_current_faq');
                break;

            case "in-process":

                //error_log('AutoBotWriter.com is taking a break!');

                break;

            default:
                break;

        }

        delete_transient($lock_key);
    }

    public static function getNextPendingArticle()
    {
        global $wpdb;

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        $query = "SELECT id FROM $autobotwriter_posts_schedule_table WHERE status = 'pending' ORDER BY creation_date DESC, id ASC";

        $article_id = $wpdb->get_var($query);

        return $article_id ? $article_id : NULL;
    }

    public static function getArticleTitle($id)
    {
        global $wpdb;

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        $query = $wpdb->prepare("SELECT post_title FROM $autobotwriter_posts_schedule_table WHERE id = %d", $id);

        $article_title = $wpdb->get_var($query);

        return $article_title ? $article_title : NULL;
    }

    public static function getArticleRecord($id)
    {
        global $wpdb;

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        $query = $wpdb->prepare("SELECT include_keywords, exclude_keywords FROM $autobotwriter_posts_schedule_table WHERE id = %d", $id);

        $article_record = $wpdb->get_row($query);

        return $article_record ? $article_record : NULL;
    }

    public static function generate_intro($prompt, $include = '', $exclude = '')
    {
        $atbtwrt_intro = "Compose a SEO optimized engaging introduction on the topic below. Ensure that each paragraph begins with a capitalized letter. Please do not include a conclusion in this introduction:\n\n'$prompt'.";

        if ($exclude != '') {
            $atbtwrt_intro .= " Make sure you ignore the keywords: $exclude.";
        }

        if ($include != '') {
            $atbtwrt_intro .= " Make sure you include the keywords: $include.";
        }

        $atbtwrt_opts = [
            "prompt" => $atbtwrt_intro
        ];

        $atbtwrt_result = self::atbtwrt_request_fun($atbtwrt_opts);

        if ($atbtwrt_result["status"] === "success") {
            return $atbtwrt_result["data"];
        } else {
            // Handle error in generating introduction
            return "";
        }
    }

    public static function generate_headings($number_of_headings, $prompt, $modify_headings, $include = '', $exclude = '')
    {
        $heading_prompt_turbo = "Do not converse as an AI assistant. Simply answer the question without any conversation or extra comments. No description. Present the answer as a numbered list and ensure that the first letter of each heading is capitalized. Generate $number_of_headings headings based on the following input:\n\n'.$prompt'.";

        if ($exclude != '') {
            $heading_prompt_turbo .= " Make sure you ignore the keywords: $exclude.";
        }

        if ($include != '') {
            $heading_prompt_turbo .= " Make sure you include the keywords: $include.";
        }

        $atbtwrt_opts = [
            "prompt" => $heading_prompt_turbo//,
            //"n" => $number_of_headings,
        ];

        $atbtwrt_result = self::atbtwrt_request_fun($atbtwrt_opts);

        if ($atbtwrt_result["status"] === "success") {
            $headings = preg_split("/\r\n|\n|\r/", $atbtwrt_result["data"]);
            $headings = array_map(function ($heading) {
                return str_replace('"', "", $heading);
            }, $headings);
            $headings = array_splice($headings, 0, $number_of_headings);

            if ($modify_headings) {
                $atbtwrt_headings = [$modify_headings];
            } else {
                $atbtwrt_headings = $headings;
            }
        } else {
            // Handle error in generating headings
            $atbtwrt_headings = [];
        }

        return $atbtwrt_headings;
    }

    public static function generate_final_conclusion($prompt, $include = '', $exclude = '')
    {
        //$atbtwrt_intro = "Compose a concise conclusion, without engaging in a conversational style or providing additional commentary, for the following topic: \n\n'$prompt'. \n\nEnsure the first letter of each paragraph is capitalized.";

        $atbtwrt_intro = "Compose a concise conclusion by synthesizing the key takeaways from the following blog sections:\n\n $prompt.\n\nEnsure the first letter of each paragraph is capitalized. Do not engage in a conversational style or provide additional commentary.";

        if ($exclude != '') {
            $atbtwrt_intro .= " Make sure you ignore the keywords: $exclude.";
        }

        if ($include != '') {
            $atbtwrt_intro .= " Make sure you include the keywords: $include.";
        }
        $atbtwrt_opts = [
            "prompt" => $atbtwrt_intro
        ];

        $atbtwrt_result = self::atbtwrt_request_fun($atbtwrt_opts);

        if ($atbtwrt_result["status"] === "success") {
            return $atbtwrt_result["data"];
        } else {
            // Handle error in generating introduction
            return "";
        }
    }

    public static function generate_content($heading, $include = '', $exclude = '')
    {

        //$prompt = "Write an SEO-friendly blog section for '$heading'. The section should provide detailed information about '$heading'. End the section with the conclusion by adding text 'Final Conclusion' before it. Make sure to utilize appropriate SEO tags as applicable. It is not necessary to rewrite the heading.";
        //$prompt = "Compose an SEO-friendly blog section centered around the subject '$heading'. This section should provide in-depth information on '$heading', subtly integrating appropriate SEO tags, pertinent keywords and phrases. Reminder: The resulting text should seamlessly integrate within a broader article. Do not replicate the subject '$heading' as a headline or subheading in your output.";
        $prompt = "Compose an SEO-friendly blog section centered around the subject '$heading'. This section should provide in-depth information on '$heading', subtly integrating appropriate SEO tags, pertinent keywords and phrases. Reminder: The resulting text should seamlessly integrate within a broader article. Do not replicate the subject '$heading' as a headline or subheading in your output. Additionally, after the main content, please provide a conclusion that summarizes the key points discussed in the section. Start the conclusion by adding the marker '[CONCLUSION]'";

        if ($exclude != '') {
            $prompt .= " Make sure you ignore the keywords: $exclude.";
        }

        if ($include != '') {
            $prompt .= " Make sure you include the keywords: $include.";
        }

        $atbtwrt_opts = [
            "prompt" => $prompt
        ];

        $atbtwrt_result = self::atbtwrt_request_fun($atbtwrt_opts);

        if ($atbtwrt_result["status"] === "success") {
            //return $atbtwrt_result["data"];
            $content_with_conclusion = $atbtwrt_result["data"];
            $content_and_conclusion = explode("[CONCLUSION]", $content_with_conclusion, 2);

            $content = $content_and_conclusion[0]; // The content before the [CONCLUSION] marker
            $conclusion = isset($content_and_conclusion[1]) ? $content_and_conclusion[1] : ""; // The conclusion after the [CONCLUSION] marker

            return [
                "content" => $content,
                "conclusion" => $conclusion
            ];

        } else {
            // Handle error in generating content
            return [
                "content" => "",
                "conclusion" => ""
            ];
        }
    }

    public static function generate_faq_section($section, $include = '', $exclude = '')
    {

        $n = rand(1, 4);
        $atbtwrt_faq = "Compose, without engaging in a conversational manner or providing additional commentary, $n FAQ-style questions and answers related to the following blog section:\n\n'$section'.\n\nEnsure the first letter of each entry is capitalized.";

        if ($exclude != '') {
            $atbtwrt_faq .= " Make sure you ignore the keywords: $exclude.";
        }

        if ($include != '') {
            $atbtwrt_faq .= " Make sure you include the keywords: $include.";
        }

        $atbtwrt_opts = [
            "prompt" => $atbtwrt_faq,
            "n" => $n
        ];

        $atbtwrt_result = self::atbtwrt_request_fun($atbtwrt_opts);

        if ($atbtwrt_result["status"] === "success") {
            return $atbtwrt_result["data"];
        } else {
            die(var_dump($atbtwrt_result));
            // Handle error in generating introduction
            return "";
        }

        //are we using this variable anywhere? nik- Aug 3, 2023
        $faq_text = "Write (FAQ) questions and answers about following blog section - %s";

    }

    public static function createPost($id, $title, $content)
    {
        global $wpdb;

        $autobotwriter_posts_schedule_table = $wpdb->prefix . 'autobotwriter_posts_schedule';

        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $autobotwriter_posts_schedule_table WHERE id = %d", $id));

        if (!$record || trim($title) === '') {
            return;
        }

        $status = $record->creation_date === '0000-00-00 00:00:00' ? 'publish' : 'draft';

        $post_id = wp_insert_post(array(
            'post_type' => 'post',
            'post_author' => $record->author_id,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_date' => $record->publish_date,
        ));

        if ($post_id) {
            wp_set_post_categories($post_id, array($record->category));
            wp_set_post_tags($post_id, explode(',', $record->tags));

            $now = current_datetime();
            $wpdb->update(
                $autobotwriter_posts_schedule_table,
                array(
                    'status' => 'completed',
                    'update_date' => $now->format('Y-m-d H:i:s'),
                    'post_id' => $post_id,
                ),
                array('id' => $id)
            );
        }

        list($y,$m) = explode('-', date('Y-m'));
        $option_index = 'autobotwriter_gen_'.$m.'-'.$y;
        $opt = get_option($option_index,0);
        update_option($option_index,$opt+1);
    }

    public static function setSettings($key, $selected_model, $tokens, $temperature, $headings)
    {
        global $wpdb;

        $autobotwriter_parameters_table = $wpdb->prefix . 'autobotwriter_parameters';

        $settings = $wpdb->get_row("SELECT * FROM $autobotwriter_parameters_table;");
        $encrypted_key = self::encrypt($key);

        if (!$settings) {
            $wpdb->insert(
                $autobotwriter_parameters_table,
                array(
                    'openai_api_key' => $encrypted_key,
                    'selected_model' => $selected_model,
                    'tokens' => $tokens,
                    'temperature' => $temperature,
                    'headings' => $headings,
                )
            );
        } else {
            $wpdb->update(
                $autobotwriter_parameters_table,
                array(
                    'openai_api_key' => $encrypted_key,
                    'selected_model' => $selected_model,
                    'tokens' => $tokens,
                    'temperature' => $temperature,
                    'headings' => $headings,
                ),
                array('id' => $settings->id) // Add your WHERE condition based on a unique identifier
            );
        }
    }

    private static function encrypt($string)
    {
        $ciphering_value = "AES-128-CTR";
        $s = "AIBOTWriter2023";
        $options = 0;
        $iv = '1234567891011121';
        $encryption_value = openssl_encrypt($string, $ciphering_value, $s, $options, $iv);
        return $encryption_value;
    }
}



//1. Causes of the French Revolution" [1]=> string(34) "2. Impact of the French Revolution" [2]=> string(39) "3. Key Figures of the French Revolution" [3]=> string(34) "4. Legacy of the French Revolution