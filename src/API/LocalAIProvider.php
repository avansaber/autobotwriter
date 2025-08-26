<?php
/**
 * Local AI Provider Implementation
 *
 * @package AutoBotWriter
 * @since 1.6.0
 */

namespace AutoBotWriter\API;

use AutoBotWriter\Core\Plugin;

/**
 * Local AI Provider Class
 */
class LocalAIProvider implements AIProviderInterface
{
    /**
     * Provider configuration
     */
    private array $config = [];

    /**
     * Available models (local/self-hosted)
     */
    private array $models = [
        'llama-2-7b' => [
            'name' => 'Llama 2 7B',
            'description' => 'Meta Llama 2 7B parameter model',
            'max_tokens' => 4096,
            'cost_per_1k_tokens' => 0 // Free for local hosting
        ],
        'llama-2-13b' => [
            'name' => 'Llama 2 13B',
            'description' => 'Meta Llama 2 13B parameter model',
            'max_tokens' => 4096,
            'cost_per_1k_tokens' => 0
        ],
        'mistral-7b' => [
            'name' => 'Mistral 7B',
            'description' => 'Mistral 7B Instruct model',
            'max_tokens' => 8192,
            'cost_per_1k_tokens' => 0
        ],
        'codellama-7b' => [
            'name' => 'Code Llama 7B',
            'description' => 'Code-specialized Llama model',
            'max_tokens' => 4096,
            'cost_per_1k_tokens' => 0
        ]
    ];

    public function get_name(): string
    {
        return 'Local AI';
    }

    public function get_description(): string
    {
        return __('Self-hosted AI models for privacy-focused content generation', Plugin::TEXT_DOMAIN);
    }

    public function configure(array $config): void
    {
        $this->config = wp_parse_args($config, [
            'endpoint_url' => 'http://localhost:8080',
            'api_key' => '', // Optional for local models
            'model' => 'llama-2-7b',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 300 // Longer timeout for local processing
        ]);
    }

    public function is_configured(): bool
    {
        return !empty($this->config['endpoint_url']);
    }

    public function test_connection(): array
    {
        if (!$this->is_configured()) {
            return [
                'status' => 'error',
                'message' => __('Endpoint URL not configured', Plugin::TEXT_DOMAIN)
            ];
        }

        try {
            // Test connection to local AI server
            $response = wp_remote_get($this->config['endpoint_url'] . '/health', [
                'timeout' => 10
            ]);

            if (is_wp_error($response)) {
                return [
                    'status' => 'error',
                    'message' => sprintf(
                        __('Cannot connect to local AI server: %s', Plugin::TEXT_DOMAIN),
                        $response->get_error_message()
                    )
                ];
            }

            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200) {
                return [
                    'status' => 'success',
                    'message' => __('Local AI server is running', Plugin::TEXT_DOMAIN)
                ];
            }

            return [
                'status' => 'error',
                'message' => sprintf(
                    __('Local AI server returned status code: %d', Plugin::TEXT_DOMAIN),
                    $status_code
                )
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function get_available_models(): array
    {
        return $this->models;
    }

    public function get_supported_features(): array
    {
        return [
            'text_generation',
            'code_generation',
            'privacy_focused',
            'offline_capable',
            'customizable'
        ];
    }

    public function get_pricing_info(): array
    {
        return [
            'billing_model' => 'self_hosted',
            'currency' => 'USD',
            'cost_note' => __('Free when self-hosted, but requires computational resources', Plugin::TEXT_DOMAIN),
            'models' => array_map(function($model) {
                return [
                    'name' => $model['name'],
                    'cost_per_1k_tokens' => 0
                ];
            }, $this->models)
        ];
    }

    public function generate_content(string $prompt, array $options = []): array
    {
        if (!$this->is_configured()) {
            return [
                'status' => 'error',
                'message' => __('Local AI provider not configured', Plugin::TEXT_DOMAIN)
            ];
        }

        $options = wp_parse_args($options, $this->config);

        try {
            $request_data = [
                'model' => $options['model'],
                'prompt' => $prompt,
                'max_tokens' => (int) $options['max_tokens'],
                'temperature' => (float) $options['temperature'],
                'stream' => false
            ];

            $response = wp_remote_post($this->config['endpoint_url'] . '/v1/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => !empty($this->config['api_key']) ? 'Bearer ' . $this->config['api_key'] : ''
                ],
                'body' => wp_json_encode($request_data),
                'timeout' => $this->config['timeout']
            ]);

            if (is_wp_error($response)) {
                return [
                    'status' => 'error',
                    'message' => $response->get_error_message()
                ];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'status' => 'error',
                    'message' => __('Invalid response from local AI server', Plugin::TEXT_DOMAIN)
                ];
            }

            if (isset($data['error'])) {
                return [
                    'status' => 'error',
                    'message' => $data['error']['message'] ?? __('Local AI generation failed', Plugin::TEXT_DOMAIN)
                ];
            }

            if (!isset($data['choices'][0]['text'])) {
                return [
                    'status' => 'error',
                    'message' => __('No content generated by local AI', Plugin::TEXT_DOMAIN)
                ];
            }

            $content = trim($data['choices'][0]['text']);
            $usage = $data['usage'] ?? [];

            return [
                'status' => 'success',
                'data' => $content,
                'tokens_used' => $usage['total_tokens'] ?? 0,
                'model_used' => $options['model'],
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
                'usage' => $usage
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function generate_content_stream(string $prompt, array $options = [], callable $callback = null): array
    {
        // TODO: Implement local AI streaming
        return [
            'status' => 'error',
            'message' => __('Local AI streaming not yet implemented', Plugin::TEXT_DOMAIN)
        ];
    }

    public function get_usage_stats(): array
    {
        return [
            'requests_today' => (int) get_option('local_ai_requests_today', 0),
            'requests_month' => (int) get_option('local_ai_requests_month', 0),
            'tokens_used' => (int) get_option('local_ai_tokens_used', 0),
            'estimated_cost' => 0 // Always free for local
        ];
    }

    public function get_rate_limits(): array
    {
        return [
            'requests_per_minute' => 60, // Depends on local hardware
            'tokens_per_minute' => 10000, // Depends on local hardware
            'requests_per_day' => -1 // Unlimited for local
        ];
    }
}
