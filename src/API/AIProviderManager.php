<?php
/**
 * AI Provider Manager
 *
 * @package AutoBotWriter
 * @since 1.6.0
 */

namespace AutoBotWriter\API;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Utils\Logger;
use AutoBotWriter\Utils\Cache;

/**
 * AI Provider Manager Class
 */
class AIProviderManager
{
    /**
     * Available AI providers
     */
    private array $providers = [];

    /**
     * Active provider
     */
    private ?AIProviderInterface $active_provider = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->register_providers();
        $this->set_active_provider();
    }

    /**
     * Register available AI providers
     *
     * @return void
     */
    private function register_providers(): void
    {
        $this->providers = [
            'openai' => new OpenAIProvider(),
            'anthropic' => new AnthropicProvider(),
            'google' => new GoogleAIProvider(),
            'local' => new LocalAIProvider(),
        ];

        // Allow plugins to register custom providers
        $this->providers = apply_filters('autobotwriter_ai_providers', $this->providers);
    }

    /**
     * Set active provider based on settings
     *
     * @return void
     */
    private function set_active_provider(): void
    {
        $settings = get_option('autobotwriter_ai_settings', []);
        $provider_name = $settings['active_provider'] ?? 'openai';

        if (isset($this->providers[$provider_name])) {
            $this->active_provider = $this->providers[$provider_name];
            $this->active_provider->configure($settings);
        } else {
            Logger::warning("Unknown AI provider: {$provider_name}, falling back to OpenAI");
            $this->active_provider = $this->providers['openai'];
        }
    }

    /**
     * Get available providers
     *
     * @return array Provider information
     */
    public function get_available_providers(): array
    {
        $provider_info = [];

        foreach ($this->providers as $key => $provider) {
            $provider_info[$key] = [
                'name' => $provider->get_name(),
                'description' => $provider->get_description(),
                'models' => $provider->get_available_models(),
                'features' => $provider->get_supported_features(),
                'pricing' => $provider->get_pricing_info(),
                'status' => $provider->is_configured() ? 'configured' : 'not_configured'
            ];
        }

        return $provider_info;
    }

    /**
     * Switch to a different provider
     *
     * @param string $provider_name Provider name
     * @param array $config Provider configuration
     * @return bool True on success, false on failure
     */
    public function switch_provider(string $provider_name, array $config = []): bool
    {
        if (!isset($this->providers[$provider_name])) {
            Logger::error("Cannot switch to unknown provider: {$provider_name}");
            return false;
        }

        try {
            $provider = $this->providers[$provider_name];
            $provider->configure($config);

            if (!$provider->is_configured()) {
                Logger::error("Provider {$provider_name} is not properly configured");
                return false;
            }

            $this->active_provider = $provider;
            
            // Update settings
            $settings = get_option('autobotwriter_ai_settings', []);
            $settings['active_provider'] = $provider_name;
            update_option('autobotwriter_ai_settings', $settings);

            Logger::info("Switched to AI provider: {$provider_name}");
            return true;

        } catch (\Exception $e) {
            Logger::error("Failed to switch to provider {$provider_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate content using active provider
     *
     * @param string $prompt Content prompt
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generate_content(string $prompt, array $options = []): array
    {
        if (!$this->active_provider) {
            return [
                'status' => 'error',
                'message' => __('No AI provider configured', Plugin::TEXT_DOMAIN)
            ];
        }

        // Check cache first
        $cache_key = 'content_' . md5($prompt . serialize($options));
        $cached_result = Cache::get($cache_key);
        
        if ($cached_result !== null) {
            Logger::debug('Using cached content generation result');
            return $cached_result;
        }

        $start_time = microtime(true);

        try {
            $result = $this->active_provider->generate_content($prompt, $options);
            
            $duration = microtime(true) - $start_time;
            
            // Log the request
            Logger::log_api_request(
                $this->active_provider->get_name(),
                ['prompt_length' => strlen($prompt), 'options' => $options],
                $result,
                $duration
            );

            // Cache successful results
            if ($result['status'] === 'success') {
                Cache::set($cache_key, $result, 300); // Cache for 5 minutes
            }

            return $result;

        } catch (\Exception $e) {
            Logger::error("Content generation failed: " . $e->getMessage(), [
                'provider' => $this->active_provider->get_name(),
                'prompt_length' => strlen($prompt)
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate multiple content variations
     *
     * @param string $prompt Content prompt
     * @param int $variations Number of variations
     * @param array $options Generation options
     * @return array Generation results
     */
    public function generate_variations(string $prompt, int $variations = 3, array $options = []): array
    {
        $results = [];
        $options['temperature'] = $options['temperature'] ?? 0.8; // Higher temperature for variations

        for ($i = 0; $i < $variations; $i++) {
            $variation_options = $options;
            $variation_options['seed'] = $i; // Different seed for each variation
            
            $result = $this->generate_content($prompt, $variation_options);
            $results[] = $result;

            // Add small delay to avoid rate limiting
            if ($i < $variations - 1) {
                usleep(100000); // 100ms delay
            }
        }

        return $results;
    }

    /**
     * Get content suggestions based on topic
     *
     * @param string $topic Content topic
     * @param int $count Number of suggestions
     * @return array Content suggestions
     */
    public function get_content_suggestions(string $topic, int $count = 5): array
    {
        $cache_key = 'suggestions_' . md5($topic . $count);
        $cached_suggestions = Cache::get($cache_key);
        
        if ($cached_suggestions !== null) {
            return $cached_suggestions;
        }

        $prompt = sprintf(
            "Generate %d creative and engaging blog post ideas about '%s'. " .
            "Each idea should be unique, specific, and appealing to readers. " .
            "Format as a numbered list with brief descriptions.",
            $count,
            $topic
        );

        $result = $this->generate_content($prompt, [
            'max_tokens' => 300,
            'temperature' => 0.9
        ]);

        if ($result['status'] === 'success') {
            $suggestions = $this->parse_suggestions($result['data']);
            Cache::set($cache_key, $suggestions, 1800); // Cache for 30 minutes
            return $suggestions;
        }

        return [];
    }

    /**
     * Analyze content quality and provide suggestions
     *
     * @param string $content Content to analyze
     * @return array Analysis results
     */
    public function analyze_content(string $content): array
    {
        $prompt = sprintf(
            "Analyze the following content for quality, readability, SEO potential, and engagement. " .
            "Provide specific suggestions for improvement:\n\n%s",
            $content
        );

        $result = $this->generate_content($prompt, [
            'max_tokens' => 500,
            'temperature' => 0.3
        ]);

        if ($result['status'] === 'success') {
            return [
                'status' => 'success',
                'analysis' => $result['data'],
                'metrics' => $this->calculate_content_metrics($content)
            ];
        }

        return $result;
    }

    /**
     * Get active provider instance
     *
     * @return AIProviderInterface|null Active provider
     */
    public function get_active_provider(): ?AIProviderInterface
    {
        return $this->active_provider;
    }

    /**
     * Get provider by name
     *
     * @param string $name Provider name
     * @return AIProviderInterface|null Provider instance
     */
    public function get_provider(string $name): ?AIProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Test provider connection
     *
     * @param string $provider_name Provider name
     * @param array $config Provider configuration
     * @return array Test results
     */
    public function test_provider(string $provider_name, array $config = []): array
    {
        if (!isset($this->providers[$provider_name])) {
            return [
                'status' => 'error',
                'message' => 'Unknown provider'
            ];
        }

        try {
            $provider = clone $this->providers[$provider_name];
            $provider->configure($config);

            $test_result = $provider->test_connection();
            
            Logger::info("Provider test completed", [
                'provider' => $provider_name,
                'status' => $test_result['status']
            ]);

            return $test_result;

        } catch (\Exception $e) {
            Logger::error("Provider test failed: " . $e->getMessage(), [
                'provider' => $provider_name
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get usage statistics for all providers
     *
     * @return array Usage statistics
     */
    public function get_usage_statistics(): array
    {
        $stats = [];

        foreach ($this->providers as $name => $provider) {
            $stats[$name] = [
                'name' => $provider->get_name(),
                'requests_today' => $this->get_provider_requests($name, 'today'),
                'requests_month' => $this->get_provider_requests($name, 'month'),
                'tokens_used' => $this->get_provider_tokens($name),
                'estimated_cost' => $this->get_provider_cost($name),
                'last_used' => $this->get_provider_last_used($name)
            ];
        }

        return $stats;
    }

    /**
     * Parse content suggestions from AI response
     *
     * @param string $response AI response
     * @return array Parsed suggestions
     */
    private function parse_suggestions(string $response): array
    {
        $lines = explode("\n", $response);
        $suggestions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s*(.+)/', $line, $matches)) {
                $suggestions[] = trim($matches[1]);
            }
        }

        return $suggestions;
    }

    /**
     * Calculate content metrics
     *
     * @param string $content Content to analyze
     * @return array Content metrics
     */
    private function calculate_content_metrics(string $content): array
    {
        $word_count = str_word_count($content);
        $char_count = strlen($content);
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        $avg_words_per_sentence = $sentence_count > 0 ? round($word_count / $sentence_count, 1) : 0;
        $reading_time = ceil($word_count / 200); // Assuming 200 words per minute

        return [
            'word_count' => $word_count,
            'character_count' => $char_count,
            'sentence_count' => $sentence_count,
            'average_words_per_sentence' => $avg_words_per_sentence,
            'estimated_reading_time' => $reading_time,
            'readability_score' => $this->calculate_readability_score($content)
        ];
    }

    /**
     * Calculate readability score (simplified Flesch Reading Ease)
     *
     * @param string $content Content to analyze
     * @return float Readability score
     */
    private function calculate_readability_score(string $content): float
    {
        $word_count = str_word_count($content);
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        // Count syllables (simplified)
        $syllable_count = 0;
        $words = str_word_count($content, 1);
        
        foreach ($words as $word) {
            $syllable_count += max(1, preg_match_all('/[aeiouy]+/i', $word));
        }

        if ($sentence_count === 0 || $word_count === 0) {
            return 0;
        }

        // Flesch Reading Ease formula
        $score = 206.835 - (1.015 * ($word_count / $sentence_count)) - (84.6 * ($syllable_count / $word_count));
        
        return max(0, min(100, round($score, 1)));
    }

    /**
     * Get provider request count
     *
     * @param string $provider_name Provider name
     * @param string $period Time period
     * @return int Request count
     */
    private function get_provider_requests(string $provider_name, string $period): int
    {
        $option_key = "autobotwriter_requests_{$provider_name}_{$period}";
        return (int) get_option($option_key, 0);
    }

    /**
     * Get provider token usage
     *
     * @param string $provider_name Provider name
     * @return int Token count
     */
    private function get_provider_tokens(string $provider_name): int
    {
        $option_key = "autobotwriter_tokens_{$provider_name}";
        return (int) get_option($option_key, 0);
    }

    /**
     * Get provider estimated cost
     *
     * @param string $provider_name Provider name
     * @return float Estimated cost
     */
    private function get_provider_cost(string $provider_name): float
    {
        $option_key = "autobotwriter_cost_{$provider_name}";
        return (float) get_option($option_key, 0);
    }

    /**
     * Get provider last used timestamp
     *
     * @param string $provider_name Provider name
     * @return string|null Last used timestamp
     */
    private function get_provider_last_used(string $provider_name): ?string
    {
        $option_key = "autobotwriter_last_used_{$provider_name}";
        $timestamp = get_option($option_key);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}
