<?php
/**
 * OpenAI Provider Implementation
 *
 * @package AutoBotWriter
 * @since 1.6.0
 */

namespace AutoBotWriter\API;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Utils\Logger;

/**
 * OpenAI Provider Class
 */
class OpenAIProvider implements AIProviderInterface
{
    /**
     * API base URL
     */
    private const API_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Provider configuration
     */
    private array $config = [];

    /**
     * Available models
     */
    private array $models = [
        'gpt-4o' => [
            'name' => 'GPT-4o',
            'description' => 'Most advanced multimodal model',
            'max_tokens' => 128000,
            'cost_per_1k_tokens' => 0.005
        ],
        'gpt-4-turbo' => [
            'name' => 'GPT-4 Turbo',
            'description' => 'Latest GPT-4 model with improved performance',
            'max_tokens' => 128000,
            'cost_per_1k_tokens' => 0.01
        ],
        'gpt-4' => [
            'name' => 'GPT-4',
            'description' => 'Most capable model for complex tasks',
            'max_tokens' => 8192,
            'cost_per_1k_tokens' => 0.03
        ],
        'gpt-3.5-turbo' => [
            'name' => 'GPT-3.5 Turbo',
            'description' => 'Fast and efficient for most tasks',
            'max_tokens' => 16385,
            'cost_per_1k_tokens' => 0.0015
        ]
    ];

    /**
     * Get provider name
     *
     * @return string Provider name
     */
    public function get_name(): string
    {
        return 'OpenAI';
    }

    /**
     * Get provider description
     *
     * @return string Provider description
     */
    public function get_description(): string
    {
        return __('OpenAI GPT models for high-quality content generation', Plugin::TEXT_DOMAIN);
    }

    /**
     * Configure the provider
     *
     * @param array $config Configuration array
     * @return void
     */
    public function configure(array $config): void
    {
        $this->config = wp_parse_args($config, [
            'api_key' => '',
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'timeout' => 120
        ]);
    }

    /**
     * Check if provider is configured
     *
     * @return bool True if configured
     */
    public function is_configured(): bool
    {
        return !empty($this->config['api_key']) && 
               preg_match('/^sk-[a-zA-Z0-9]{48,}$/', $this->config['api_key']);
    }

    /**
     * Test connection to provider
     *
     * @return array Test results
     */
    public function test_connection(): array
    {
        if (!$this->is_configured()) {
            return [
                'status' => 'error',
                'message' => __('API key not configured', Plugin::TEXT_DOMAIN)
            ];
        }

        try {
            $response = $this->make_request('/models', 'GET');
            
            if (isset($response['data'])) {
                return [
                    'status' => 'success',
                    'message' => __('Connection successful', Plugin::TEXT_DOMAIN),
                    'models_available' => count($response['data'])
                ];
            }

            return [
                'status' => 'error',
                'message' => $response['error']['message'] ?? __('Unknown error', Plugin::TEXT_DOMAIN)
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available models
     *
     * @return array Available models
     */
    public function get_available_models(): array
    {
        return $this->models;
    }

    /**
     * Get supported features
     *
     * @return array Supported features
     */
    public function get_supported_features(): array
    {
        return [
            'text_generation',
            'chat_completion',
            'streaming',
            'function_calling',
            'json_mode',
            'vision' // For GPT-4V models
        ];
    }

    /**
     * Get pricing information
     *
     * @return array Pricing information
     */
    public function get_pricing_info(): array
    {
        return [
            'billing_model' => 'token_based',
            'currency' => 'USD',
            'models' => array_map(function($model) {
                return [
                    'name' => $model['name'],
                    'cost_per_1k_tokens' => $model['cost_per_1k_tokens']
                ];
            }, $this->models)
        ];
    }

    /**
     * Generate content
     *
     * @param string $prompt Content prompt
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generate_content(string $prompt, array $options = []): array
    {
        if (!$this->is_configured()) {
            return [
                'status' => 'error',
                'message' => __('Provider not configured', Plugin::TEXT_DOMAIN)
            ];
        }

        $options = wp_parse_args($options, $this->config);

        try {
            $request_data = [
                'model' => $options['model'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $options['system_prompt'] ?? 'You are a helpful assistant that creates high-quality content.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => (int) $options['max_tokens'],
                'temperature' => (float) $options['temperature'],
                'top_p' => (float) $options['top_p'],
                'frequency_penalty' => (float) $options['frequency_penalty'],
                'presence_penalty' => (float) $options['presence_penalty']
            ];

            // Add JSON mode if requested
            if (!empty($options['response_format']) && $options['response_format'] === 'json') {
                $request_data['response_format'] = ['type' => 'json_object'];
            }

            $response = $this->make_request('/chat/completions', 'POST', $request_data);

            if (isset($response['error'])) {
                return [
                    'status' => 'error',
                    'message' => $response['error']['message'] ?? __('API request failed', Plugin::TEXT_DOMAIN)
                ];
            }

            if (!isset($response['choices'][0]['message']['content'])) {
                return [
                    'status' => 'error',
                    'message' => __('No content generated', Plugin::TEXT_DOMAIN)
                ];
            }

            $content = trim($response['choices'][0]['message']['content']);
            $usage = $response['usage'] ?? [];

            // Update usage statistics
            $this->update_usage_stats($usage);

            return [
                'status' => 'success',
                'data' => $content,
                'tokens_used' => $usage['total_tokens'] ?? 0,
                'model_used' => $options['model'],
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? 'unknown',
                'usage' => $usage
            ];

        } catch (\Exception $e) {
            Logger::error('OpenAI content generation failed: ' . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate content with streaming
     *
     * @param string $prompt Content prompt
     * @param array $options Generation options
     * @param callable $callback Streaming callback
     * @return array Generation result
     */
    public function generate_content_stream(string $prompt, array $options = [], callable $callback = null): array
    {
        if (!$this->is_configured()) {
            return [
                'status' => 'error',
                'message' => __('Provider not configured', Plugin::TEXT_DOMAIN)
            ];
        }

        $options = wp_parse_args($options, $this->config);
        $options['stream'] = true;

        try {
            $request_data = [
                'model' => $options['model'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $options['system_prompt'] ?? 'You are a helpful assistant that creates high-quality content.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => (int) $options['max_tokens'],
                'temperature' => (float) $options['temperature'],
                'stream' => true
            ];

            $full_content = '';
            $total_tokens = 0;

            $response = $this->make_streaming_request('/chat/completions', $request_data, function($chunk) use (&$full_content, &$total_tokens, $callback) {
                if (strpos($chunk, 'data: ') === 0) {
                    $json_str = substr($chunk, 6);
                    
                    if ($json_str === '[DONE]') {
                        return;
                    }

                    $data = json_decode($json_str, true);
                    
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $content_chunk = $data['choices'][0]['delta']['content'];
                        $full_content .= $content_chunk;
                        
                        if ($callback) {
                            $callback($content_chunk, $full_content);
                        }
                    }

                    if (isset($data['usage']['total_tokens'])) {
                        $total_tokens = $data['usage']['total_tokens'];
                    }
                }
            });

            return [
                'status' => 'success',
                'data' => $full_content,
                'tokens_used' => $total_tokens,
                'model_used' => $options['model']
            ];

        } catch (\Exception $e) {
            Logger::error('OpenAI streaming generation failed: ' . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get usage statistics
     *
     * @return array Usage statistics
     */
    public function get_usage_stats(): array
    {
        return [
            'requests_today' => (int) get_option('openai_requests_today', 0),
            'requests_month' => (int) get_option('openai_requests_month', 0),
            'tokens_used' => (int) get_option('openai_tokens_used', 0),
            'estimated_cost' => (float) get_option('openai_estimated_cost', 0)
        ];
    }

    /**
     * Get rate limits
     *
     * @return array Rate limit information
     */
    public function get_rate_limits(): array
    {
        return [
            'requests_per_minute' => 3500, // Varies by model and tier
            'tokens_per_minute' => 90000,  // Varies by model and tier
            'requests_per_day' => 10000    // Estimated
        ];
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array Response data
     * @throws \Exception If request fails
     */
    private function make_request(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = self::API_BASE_URL . $endpoint;
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
            'User-Agent' => 'AutoBotWriter/' . Plugin::VERSION
        ];

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->config['timeout'],
            'body' => !empty($data) ? wp_json_encode($data) : null
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response');
        }

        return $decoded;
    }

    /**
     * Make streaming API request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param callable $callback Chunk callback
     * @return array Response data
     * @throws \Exception If request fails
     */
    private function make_streaming_request(string $endpoint, array $data, callable $callback): array
    {
        $url = self::API_BASE_URL . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json',
            'User-Agent: AutoBotWriter/' . Plugin::VERSION
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => wp_json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use ($callback) {
                $callback($chunk);
                return strlen($chunk);
            },
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \Exception('cURL error: ' . $error);
        }

        if ($http_code >= 400) {
            throw new \Exception('HTTP error: ' . $http_code);
        }

        return ['status' => 'success'];
    }

    /**
     * Update usage statistics
     *
     * @param array $usage Usage data from API response
     * @return void
     */
    private function update_usage_stats(array $usage): void
    {
        if (empty($usage)) {
            return;
        }

        $today = date('Y-m-d');
        $month = date('Y-m');

        // Update daily requests
        $daily_key = 'openai_requests_' . $today;
        $daily_count = (int) get_option($daily_key, 0);
        update_option($daily_key, $daily_count + 1);

        // Update monthly requests
        $monthly_key = 'openai_requests_' . $month;
        $monthly_count = (int) get_option($monthly_key, 0);
        update_option($monthly_key, $monthly_count + 1);

        // Update token usage
        if (isset($usage['total_tokens'])) {
            $total_tokens = (int) get_option('openai_tokens_used', 0);
            update_option('openai_tokens_used', $total_tokens + $usage['total_tokens']);

            // Estimate cost
            $model = $this->config['model'];
            $cost_per_token = ($this->models[$model]['cost_per_1k_tokens'] ?? 0.002) / 1000;
            $estimated_cost = (float) get_option('openai_estimated_cost', 0);
            update_option('openai_estimated_cost', $estimated_cost + ($usage['total_tokens'] * $cost_per_token));
        }

        // Update last used timestamp
        update_option('openai_last_used', time());
    }
}
