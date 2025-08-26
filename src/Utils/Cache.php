<?php
/**
 * Cache Utility
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Utils;

use AutoBotWriter\Core\Plugin;

/**
 * Cache Utility Class
 */
class Cache
{
    /**
     * Cache prefix
     */
    private const CACHE_PREFIX = 'autobotwriter_';

    /**
     * Default cache expiration (1 hour)
     */
    private const DEFAULT_EXPIRATION = 3600;

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached data or default value
     */
    public static function get(string $key, $default = null)
    {
        $cache_key = self::get_cache_key($key);
        
        // Try object cache first (if available)
        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($cache_key, 'autobotwriter');
            if ($value !== false) {
                return $value;
            }
        }

        // Fall back to transients
        $value = get_transient($cache_key);
        return $value !== false ? $value : $default;
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Expiration time in seconds
     * @return bool True on success, false on failure
     */
    public static function set(string $key, $value, int $expiration = self::DEFAULT_EXPIRATION): bool
    {
        $cache_key = self::get_cache_key($key);

        // Set in object cache (if available)
        if (function_exists('wp_cache_set')) {
            wp_cache_set($cache_key, $value, 'autobotwriter', $expiration);
        }

        // Set in transients as fallback
        return set_transient($cache_key, $value, $expiration);
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public static function delete(string $key): bool
    {
        $cache_key = self::get_cache_key($key);

        // Delete from object cache (if available)
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($cache_key, 'autobotwriter');
        }

        // Delete from transients
        return delete_transient($cache_key);
    }

    /**
     * Clear all plugin caches
     *
     * @return void
     */
    public static function clear_all(): void
    {
        global $wpdb;

        // Clear transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );

        // Clear object cache group (if available)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('autobotwriter');
        }
    }

    /**
     * Get or set cached data with callback
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate data if not cached
     * @param int $expiration Expiration time in seconds
     * @return mixed Cached or generated data
     */
    public static function remember(string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION)
    {
        $value = self::get($key);

        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $expiration);
        }

        return $value;
    }

    /**
     * Cache API responses
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param mixed $response Response data
     * @param int $expiration Expiration time in seconds
     * @return bool True on success, false on failure
     */
    public static function cache_api_response(string $endpoint, array $params, $response, int $expiration = 300): bool
    {
        $key = 'api_' . md5($endpoint . serialize($params));
        return self::set($key, $response, $expiration);
    }

    /**
     * Get cached API response
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return mixed Cached response or null if not found
     */
    public static function get_api_response(string $endpoint, array $params)
    {
        $key = 'api_' . md5($endpoint . serialize($params));
        return self::get($key);
    }

    /**
     * Cache database query results
     *
     * @param string $query_hash Query hash
     * @param mixed $results Query results
     * @param int $expiration Expiration time in seconds
     * @return bool True on success, false on failure
     */
    public static function cache_query_results(string $query_hash, $results, int $expiration = 600): bool
    {
        $key = 'query_' . $query_hash;
        return self::set($key, $results, $expiration);
    }

    /**
     * Get cached database query results
     *
     * @param string $query_hash Query hash
     * @return mixed Cached results or null if not found
     */
    public static function get_query_results(string $query_hash)
    {
        $key = 'query_' . $query_hash;
        return self::get($key);
    }

    /**
     * Cache settings
     *
     * @param array $settings Settings array
     * @return bool True on success, false on failure
     */
    public static function cache_settings(array $settings): bool
    {
        return self::set('settings', $settings, 1800); // 30 minutes
    }

    /**
     * Get cached settings
     *
     * @return array|null Cached settings or null if not found
     */
    public static function get_settings(): ?array
    {
        return self::get('settings');
    }

    /**
     * Invalidate settings cache
     *
     * @return bool True on success, false on failure
     */
    public static function invalidate_settings(): bool
    {
        return self::delete('settings');
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public static function get_stats(): array
    {
        global $wpdb;

        $transient_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        $cache_size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        return [
            'transient_count' => (int) $transient_count,
            'cache_size_bytes' => (int) $cache_size,
            'cache_size_mb' => round($cache_size / 1024 / 1024, 2),
            'object_cache_available' => function_exists('wp_cache_get'),
            'persistent_cache_available' => wp_using_ext_object_cache()
        ];
    }

    /**
     * Generate cache key with prefix
     *
     * @param string $key Original key
     * @return string Prefixed cache key
     */
    private static function get_cache_key(string $key): string
    {
        return self::CACHE_PREFIX . $key;
    }

    /**
     * Clean expired transients
     *
     * @return int Number of expired transients cleaned
     */
    public static function cleanup_expired(): int
    {
        global $wpdb;

        $expired_count = $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
            WHERE a.option_name LIKE '_transient_%'
            AND a.option_name NOT LIKE '_transient_timeout_%'
            AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
            AND b.option_value < UNIX_TIMESTAMP()"
        );

        return (int) $expired_count;
    }
}
