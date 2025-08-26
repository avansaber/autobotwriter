<?php
/**
 * Logger Utility
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Utils;

use AutoBotWriter\Core\Plugin;

/**
 * Logger Utility Class
 */
class Logger
{
    /**
     * Log levels
     */
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Log level priorities
     */
    private const LEVEL_PRIORITIES = [
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7,
    ];

    /**
     * Current log level
     */
    private static ?string $log_level = null;

    /**
     * Log file path
     */
    private static ?string $log_file = null;

    /**
     * Initialize logger
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$log_level === null) {
            self::$log_level = defined('WP_DEBUG') && WP_DEBUG ? self::DEBUG : self::ERROR;
        }

        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/autobotwriter-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                
                // Create .htaccess to protect log files
                $htaccess_content = "Order deny,allow\nDeny from all\n";
                file_put_contents($log_dir . '/.htaccess', $htaccess_content);
            }

            self::$log_file = $log_dir . '/autobotwriter-' . date('Y-m-d') . '.log';
        }
    }

    /**
     * Log emergency message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function alert(string $message, array $context = []): void
    {
        self::log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log API request
     *
     * @param string $endpoint API endpoint
     * @param array $request_data Request data
     * @param array $response_data Response data
     * @param float $duration Request duration in seconds
     * @return void
     */
    public static function log_api_request(string $endpoint, array $request_data, array $response_data, float $duration): void
    {
        $context = [
            'endpoint' => $endpoint,
            'request_size' => strlen(json_encode($request_data)),
            'response_size' => strlen(json_encode($response_data)),
            'duration' => round($duration, 3),
            'tokens_used' => $response_data['usage']['total_tokens'] ?? 0,
            'status' => $response_data['status'] ?? 'unknown'
        ];

        self::info("API Request: {$endpoint}", $context);
    }

    /**
     * Log content generation
     *
     * @param int $article_id Article ID
     * @param string $step Generation step
     * @param array $context Additional context
     * @return void
     */
    public static function log_content_generation(int $article_id, string $step, array $context = []): void
    {
        $context['article_id'] = $article_id;
        $context['step'] = $step;
        
        self::info("Content Generation: Article {$article_id} - {$step}", $context);
    }

    /**
     * Log security event
     *
     * @param string $event Security event description
     * @param array $context Additional context
     * @return void
     */
    public static function log_security_event(string $event, array $context = []): void
    {
        $context['user_id'] = get_current_user_id();
        $context['user_ip'] = self::get_client_ip();
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $context['timestamp'] = current_time('mysql');

        self::warning("Security Event: {$event}", $context);
    }

    /**
     * Log performance metrics
     *
     * @param string $operation Operation name
     * @param float $duration Duration in seconds
     * @param array $context Additional context
     * @return void
     */
    public static function log_performance(string $operation, float $duration, array $context = []): void
    {
        $context['operation'] = $operation;
        $context['duration'] = round($duration, 3);
        $context['memory_usage'] = memory_get_usage(true);
        $context['memory_peak'] = memory_get_peak_usage(true);

        if ($duration > 5.0) {
            self::warning("Slow Operation: {$operation} took {$duration}s", $context);
        } else {
            self::debug("Performance: {$operation}", $context);
        }
    }

    /**
     * Main log method
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();

        // Check if we should log this level
        if (!self::should_log($level)) {
            return;
        }

        // Format log entry
        $log_entry = self::format_log_entry($level, $message, $context);

        // Write to WordPress debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }

        // Write to plugin log file
        self::write_to_file($log_entry);

        // Send critical errors to admin email (if configured)
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL])) {
            self::send_critical_alert($level, $message, $context);
        }
    }

    /**
     * Check if we should log this level
     *
     * @param string $level Log level
     * @return bool True if should log
     */
    private static function should_log(string $level): bool
    {
        $current_priority = self::LEVEL_PRIORITIES[self::$log_level] ?? 7;
        $message_priority = self::LEVEL_PRIORITIES[$level] ?? 7;

        return $message_priority <= $current_priority;
    }

    /**
     * Format log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return string Formatted log entry
     */
    private static function format_log_entry(string $level, string $message, array $context = []): string
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        
        $log_entry = "[{$timestamp}] AutoBotWriter.{$level_upper}: {$message}";

        if (!empty($context)) {
            $log_entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return $log_entry;
    }

    /**
     * Write log entry to file
     *
     * @param string $log_entry Formatted log entry
     * @return void
     */
    private static function write_to_file(string $log_entry): void
    {
        if (self::$log_file && is_writable(dirname(self::$log_file))) {
            file_put_contents(self::$log_file, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Send critical alert email
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private static function send_critical_alert(string $level, string $message, array $context = []): void
    {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf(
            '[%s] AutoBotWriter Critical Alert: %s',
            $site_name,
            strtoupper($level)
        );

        $body = sprintf(
            "A critical error occurred in AutoBotWriter:\n\nLevel: %s\nMessage: %s\nTime: %s\nSite: %s\n\nContext:\n%s",
            strtoupper($level),
            $message,
            current_time('Y-m-d H:i:s'),
            home_url(),
            !empty($context) ? print_r($context, true) : 'None'
        );

        wp_mail($admin_email, $subject, $body);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private static function get_client_ip(): string
    {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get log file contents
     *
     * @param int $lines Number of lines to retrieve (default: 100)
     * @return array Log entries
     */
    public static function get_log_entries(int $lines = 100): array
    {
        self::init();

        if (!file_exists(self::$log_file)) {
            return [];
        }

        $content = file_get_contents(self::$log_file);
        $log_lines = explode(PHP_EOL, $content);
        $log_lines = array_filter($log_lines); // Remove empty lines
        
        return array_slice(array_reverse($log_lines), 0, $lines);
    }

    /**
     * Clear log files
     *
     * @return bool True on success, false on failure
     */
    public static function clear_logs(): bool
    {
        self::init();

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/autobotwriter-logs';

        if (!is_dir($log_dir)) {
            return true;
        }

        $files = glob($log_dir . '/*.log');
        $success = true;

        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get log statistics
     *
     * @return array Log statistics
     */
    public static function get_log_stats(): array
    {
        self::init();

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/autobotwriter-logs';

        if (!is_dir($log_dir)) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'oldest_log' => null,
                'newest_log' => null
            ];
        }

        $files = glob($log_dir . '/*.log');
        $total_size = 0;
        $oldest = null;
        $newest = null;

        foreach ($files as $file) {
            $size = filesize($file);
            $total_size += $size;
            
            $mtime = filemtime($file);
            if ($oldest === null || $mtime < $oldest) {
                $oldest = $mtime;
            }
            if ($newest === null || $mtime > $newest) {
                $newest = $mtime;
            }
        }

        return [
            'total_files' => count($files),
            'total_size' => $total_size,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'oldest_log' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
            'newest_log' => $newest ? date('Y-m-d H:i:s', $newest) : null
        ];
    }

    /**
     * Set log level
     *
     * @param string $level Log level
     * @return void
     */
    public static function set_log_level(string $level): void
    {
        if (isset(self::LEVEL_PRIORITIES[$level])) {
            self::$log_level = $level;
        }
    }

    /**
     * Get current log level
     *
     * @return string Current log level
     */
    public static function get_log_level(): string
    {
        self::init();
        return self::$log_level;
    }
}
