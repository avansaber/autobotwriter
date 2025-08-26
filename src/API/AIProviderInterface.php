<?php
/**
 * AI Provider Interface
 *
 * @package AutoBotWriter
 * @since 1.6.0
 */

namespace AutoBotWriter\API;

/**
 * AI Provider Interface
 */
interface AIProviderInterface
{
    /**
     * Get provider name
     *
     * @return string Provider name
     */
    public function get_name(): string;

    /**
     * Get provider description
     *
     * @return string Provider description
     */
    public function get_description(): string;

    /**
     * Configure the provider
     *
     * @param array $config Configuration array
     * @return void
     */
    public function configure(array $config): void;

    /**
     * Check if provider is configured
     *
     * @return bool True if configured
     */
    public function is_configured(): bool;

    /**
     * Test connection to provider
     *
     * @return array Test results
     */
    public function test_connection(): array;

    /**
     * Get available models
     *
     * @return array Available models
     */
    public function get_available_models(): array;

    /**
     * Get supported features
     *
     * @return array Supported features
     */
    public function get_supported_features(): array;

    /**
     * Get pricing information
     *
     * @return array Pricing information
     */
    public function get_pricing_info(): array;

    /**
     * Generate content
     *
     * @param string $prompt Content prompt
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generate_content(string $prompt, array $options = []): array;

    /**
     * Generate content with streaming
     *
     * @param string $prompt Content prompt
     * @param array $options Generation options
     * @param callable $callback Streaming callback
     * @return array Generation result
     */
    public function generate_content_stream(string $prompt, array $options = [], callable $callback = null): array;

    /**
     * Get usage statistics
     *
     * @return array Usage statistics
     */
    public function get_usage_stats(): array;

    /**
     * Get rate limits
     *
     * @return array Rate limit information
     */
    public function get_rate_limits(): array;
}
