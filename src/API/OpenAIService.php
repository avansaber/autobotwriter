<?php
/**
 * OpenAI Service
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\API;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Database\DatabaseManager;
use AutoBotWriter\Utils\Encryption;

/**
 * OpenAI Service Class
 */
class OpenAIService
{
    /**
     * OpenAI API base URL
     */
    private const API_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Request timeout in seconds
     */
    private const REQUEST_TIMEOUT = 120;

    /**
     * Database manager instance
     */
    private DatabaseManager $db_manager;

    /**
     * Encryption utility instance
     */
    private Encryption $encryption;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db_manager = new DatabaseManager();
        $this->encryption = new Encryption();
    }

    /**
     * Generate blog topics
     *
     * @param string $subject Subject for blog topics
     * @param int $num_topics Number of topics to generate
     * @return array Response array with status and data
     */
    public function generate_blog_topics(string $subject, int $num_topics): array
    {
        $prompt = sprintf(
            "Generate %d SEO-optimized blog titles based on the following subject: '%s'. " .
            "The titles should be succinct and directly related to the subject. " .
            "Please maintain a professional tone. " .
            "Format as a numbered list.",
            $num_topics,
            $subject
        );

        return $this->make_request([
            'prompt' => $prompt,
            'max_tokens' => 200,
            'temperature' => 0.7
        ]);
    }

    /**
     * Generate introduction
     *
     * @param string $topic Topic for introduction
     * @param string $include_keywords Keywords to include
     * @param string $exclude_keywords Keywords to exclude
     * @return array Response array with status and data
     */
    public function generate_introduction(string $topic, string $include_keywords = '', string $exclude_keywords = ''): array
    {
        $prompt = sprintf(
            "Compose a SEO optimized engaging introduction on the topic: '%s'. " .
            "Ensure that each paragraph begins with a capitalized letter. " .
            "Please do not include a conclusion in this introduction.",
            $topic
        );

        if (!empty($exclude_keywords)) {
            $prompt .= sprintf(" Make sure you ignore the keywords: %s.", $exclude_keywords);
        }

        if (!empty($include_keywords)) {
            $prompt .= sprintf(" Make sure you include the keywords: %s.", $include_keywords);
        }

        return $this->make_request(['prompt' => $prompt]);
    }

    /**
     * Generate headings
     *
     * @param int $num_headings Number of headings to generate
     * @param string $topic Main topic
     * @param string $include_keywords Keywords to include
     * @param string $exclude_keywords Keywords to exclude
     * @return array Array of headings
     */
    public function generate_headings(int $num_headings, string $topic, string $include_keywords = '', string $exclude_keywords = ''): array
    {
        $prompt = sprintf(
            "Generate %d headings based on the following topic: '%s'. " .
            "Present the answer as a numbered list and ensure that the first letter of each heading is capitalized. " .
            "Do not include any conversation or extra comments.",
            $num_headings,
            $topic
        );

        if (!empty($exclude_keywords)) {
            $prompt .= sprintf(" Make sure you ignore the keywords: %s.", $exclude_keywords);
        }

        if (!empty($include_keywords)) {
            $prompt .= sprintf(" Make sure you include the keywords: %s.", $include_keywords);
        }

        $result = $this->make_request(['prompt' => $prompt]);

        if ($result['status'] === 'success') {
            $headings = preg_split("/\r\n|\n|\r/", $result['data']);
            $headings = array_map(function ($heading) {
                return trim(str_replace('"', '', $heading));
            }, $headings);
            $headings = array_filter($headings); // Remove empty lines
            return array_slice($headings, 0, $num_headings);
        }

        return [];
    }

    /**
     * Generate content section
     *
     * @param string $heading Section heading
     * @param string $include_keywords Keywords to include
     * @param string $exclude_keywords Keywords to exclude
     * @return array Array with content and conclusion
     */
    public function generate_content_section(string $heading, string $include_keywords = '', string $exclude_keywords = ''): array
    {
        $prompt = sprintf(
            "Compose an SEO-friendly blog section centered around the subject '%s'. " .
            "This section should provide in-depth information on '%s', subtly integrating appropriate SEO tags, " .
            "pertinent keywords and phrases. The resulting text should seamlessly integrate within a broader article. " .
            "Do not replicate the subject '%s' as a headline or subheading in your output. " .
            "Additionally, after the main content, please provide a conclusion that summarizes the key points " .
            "discussed in the section. Start the conclusion by adding the marker '[CONCLUSION]'",
            $heading,
            $heading,
            $heading
        );

        if (!empty($exclude_keywords)) {
            $prompt .= sprintf(" Make sure you ignore the keywords: %s.", $exclude_keywords);
        }

        if (!empty($include_keywords)) {
            $prompt .= sprintf(" Make sure you include the keywords: %s.", $include_keywords);
        }

        $result = $this->make_request(['prompt' => $prompt]);

        if ($result['status'] === 'success') {
            $content_parts = explode('[CONCLUSION]', $result['data'], 2);
            return [
                'content' => trim($content_parts[0]),
                'conclusion' => isset($content_parts[1]) ? trim($content_parts[1]) : ''
            ];
        }

        return ['content' => '', 'conclusion' => ''];
    }

    /**
     * Generate final conclusion
     *
     * @param string $content_summary Summary of all content sections
     * @param string $include_keywords Keywords to include
     * @param string $exclude_keywords Keywords to exclude
     * @return array Response array with status and data
     */
    public function generate_final_conclusion(string $content_summary, string $include_keywords = '', string $exclude_keywords = ''): array
    {
        $prompt = sprintf(
            "Compose a concise conclusion by synthesizing the key takeaways from the following blog sections:\n\n%s\n\n" .
            "Ensure the first letter of each paragraph is capitalized. " .
            "Do not engage in a conversational style or provide additional commentary.",
            $content_summary
        );

        if (!empty($exclude_keywords)) {
            $prompt .= sprintf(" Make sure you ignore the keywords: %s.", $exclude_keywords);
        }

        if (!empty($include_keywords)) {
            $prompt .= sprintf(" Make sure you include the keywords: %s.", $include_keywords);
        }

        return $this->make_request(['prompt' => $prompt]);
    }

    /**
     * Validate API key
     *
     * @param string $api_key OpenAI API key
     * @return array Response array with status and available models
     */
    public function validate_api_key(string $api_key): array
    {
        $response = $this->send_request('/models', 'GET', [], [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['data'])) {
            return [
                'status' => 'success',
                'data' => $data['data']
            ];
        }

        return [
            'status' => 'error',
            'message' => $data['error']['message'] ?? 'Invalid API key'
        ];
    }

    /**
     * Make OpenAI API request
     *
     * @param array $options Request options
     * @return array Response array with status and data
     */
    private function make_request(array $options): array
    {
        $settings = $this->get_settings();
        
        if (empty($settings['openai_api_key'])) {
            return [
                'status' => 'error',
                'message' => __('OpenAI API key is not configured', Plugin::TEXT_DOMAIN)
            ];
        }

        // Set defaults
        $defaults = [
            'model' => $settings['selected_model'],
            'max_tokens' => (int) $settings['tokens'],
            'temperature' => (float) $settings['temperature']
        ];

        $options = wp_parse_args($options, $defaults);

        // Determine if this is a chat model
        $is_chat_model = $this->is_chat_model($options['model']);

        if ($is_chat_model) {
            $endpoint = '/chat/completions';
            $options['messages'] = [
                ['role' => 'system', 'content' => 'You are a helpful assistant that provides information.'],
                ['role' => 'user', 'content' => $options['prompt']]
            ];
            unset($options['prompt']);
        } else {
            $endpoint = '/completions';
        }

        $headers = [
            'Authorization' => 'Bearer ' . $settings['openai_api_key'],
            'Content-Type' => 'application/json'
        ];

        $response = $this->send_request($endpoint, 'POST', $options, $headers);

        return $this->process_response($response, $is_chat_model);
    }

    /**
     * Send HTTP request
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param array $headers Request headers
     * @return array|\WP_Error Response or WP_Error
     */
    private function send_request(string $endpoint, string $method, array $data = [], array $headers = [])
    {
        $url = self::API_BASE_URL . $endpoint;

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => self::REQUEST_TIMEOUT,
            'body' => !empty($data) ? wp_json_encode($data) : null
        ];

        return wp_remote_request($url, $args);
    }

    /**
     * Process API response
     *
     * @param array|\WP_Error $response HTTP response
     * @param bool $is_chat_model Whether this is a chat model
     * @return array Processed response
     */
    private function process_response($response, bool $is_chat_model): array
    {
        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $error_message = $data['error']['message'] ?? 'Unknown API error';
            
            if (strpos($error_message, 'exceeded your current quota') !== false) {
                $error_message .= ' ' . __('Please check your OpenAI account usage and billing.', Plugin::TEXT_DOMAIN);
            }

            return [
                'status' => 'error',
                'message' => $error_message
            ];
        }

        if (!isset($data['choices']) || empty($data['choices'])) {
            return [
                'status' => 'error',
                'message' => __('No response generated. Please try again.', Plugin::TEXT_DOMAIN)
            ];
        }

        $content = $is_chat_model 
            ? ($data['choices'][0]['message']['content'] ?? '')
            : ($data['choices'][0]['text'] ?? '');

        $content = trim($content);

        if (empty($content)) {
            return [
                'status' => 'error',
                'message' => __('Empty response generated. Please adjust your prompt or settings.', Plugin::TEXT_DOMAIN)
            ];
        }

        return [
            'status' => 'success',
            'data' => $content,
            'tokens' => $data['usage']['total_tokens'] ?? 0,
            'length' => $this->count_words($content)
        ];
    }

    /**
     * Check if model is a chat model
     *
     * @param string $model Model name
     * @return bool True if chat model
     */
    private function is_chat_model(string $model): bool
    {
        $chat_models = ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4o'];
        
        foreach ($chat_models as $chat_model) {
            if (strpos($model, $chat_model) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count words in text
     *
     * @param string $text Text to count
     * @return int Word count
     */
    private function count_words(string $text): int
    {
        $text = trim(strip_tags(html_entity_decode($text, ENT_QUOTES)));
        $text = preg_replace("/[\n]+/", " ", $text);
        $text = preg_replace("/[\s]+/", " ", $text);
        $words = explode(" ", $text);
        return count(array_filter($words));
    }

    /**
     * Get settings from database
     *
     * @return array Settings array
     */
    private function get_settings(): array
    {
        $settings = $this->db_manager->get_settings();
        
        if (!empty($settings['openai_api_key'])) {
            $settings['openai_api_key'] = $this->encryption->decrypt($settings['openai_api_key']);
        }

        return $settings;
    }
}
