<?php
/**
 * Bulk Operations Manager
 *
 * @package AutoBotWriter
 * @since 1.7.0
 */

namespace AutoBotWriter\Admin;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Database\DatabaseManager;
use AutoBotWriter\Utils\Logger;
use AutoBotWriter\Utils\Cache;

/**
 * Bulk Operations Manager Class
 */
class BulkOperationsManager
{
    /**
     * Database manager instance
     */
    private DatabaseManager $db_manager;

    /**
     * Template manager instance
     */
    private TemplateManager $template_manager;

    /**
     * Bulk jobs table name
     */
    private string $jobs_table;

    /**
     * Maximum batch size
     */
    private const MAX_BATCH_SIZE = 10;

    /**
     * Job statuses
     */
    private const STATUS_PENDING = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_CANCELLED = 'cancelled';

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->db_manager = new DatabaseManager();
        $this->template_manager = new TemplateManager();
        $this->jobs_table = $wpdb->prefix . 'autobotwriter_bulk_jobs';
    }

    /**
     * Create bulk jobs table
     *
     * @return bool True on success, false on failure
     */
    public function create_jobs_table(): bool
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->jobs_table} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `job_name` varchar(255) NOT NULL,
            `job_type` varchar(50) NOT NULL DEFAULT 'bulk_generate',
            `template_id` int(11) DEFAULT NULL,
            `topics` longtext NOT NULL,
            `settings` text NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `progress` int(3) NOT NULL DEFAULT 0,
            `total_items` int(11) NOT NULL DEFAULT 0,
            `completed_items` int(11) NOT NULL DEFAULT 0,
            `failed_items` int(11) NOT NULL DEFAULT 0,
            `results` longtext DEFAULT NULL,
            `error_log` longtext DEFAULT NULL,
            `estimated_tokens` int(11) NOT NULL DEFAULT 0,
            `actual_tokens` int(11) NOT NULL DEFAULT 0,
            `estimated_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `actual_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `started_at` datetime DEFAULT NULL,
            `completed_at` datetime DEFAULT NULL,
            `created_by` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY job_type (job_type),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        return !empty($result);
    }

    /**
     * Create bulk generation job
     *
     * @param array $job_data Job configuration
     * @return int|false Job ID on success, false on failure
     */
    public function create_bulk_job(array $job_data): int|false
    {
        global $wpdb;

        $defaults = [
            'job_name' => '',
            'job_type' => 'bulk_generate',
            'template_id' => null,
            'topics' => [],
            'settings' => [],
            'created_by' => get_current_user_id()
        ];

        $job_data = wp_parse_args($job_data, $defaults);

        // Validate required fields
        if (empty($job_data['job_name']) || empty($job_data['topics'])) {
            return false;
        }

        // Limit batch size
        if (count($job_data['topics']) > self::MAX_BATCH_SIZE) {
            $job_data['topics'] = array_slice($job_data['topics'], 0, self::MAX_BATCH_SIZE);
        }

        $total_items = count($job_data['topics']);
        $estimated_tokens = $this->estimate_tokens($job_data);
        $estimated_cost = $this->estimate_cost($estimated_tokens);

        $result = $wpdb->insert(
            $this->jobs_table,
            [
                'job_name' => sanitize_text_field($job_data['job_name']),
                'job_type' => sanitize_text_field($job_data['job_type']),
                'template_id' => $job_data['template_id'] ? (int) $job_data['template_id'] : null,
                'topics' => wp_json_encode($job_data['topics']),
                'settings' => wp_json_encode($job_data['settings']),
                'status' => self::STATUS_PENDING,
                'total_items' => $total_items,
                'estimated_tokens' => $estimated_tokens,
                'estimated_cost' => $estimated_cost,
                'created_by' => (int) $job_data['created_by']
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%d']
        );

        if ($result) {
            $job_id = $wpdb->insert_id;
            Logger::info('Bulk job created', [
                'job_id' => $job_id,
                'job_name' => $job_data['job_name'],
                'total_items' => $total_items,
                'estimated_tokens' => $estimated_tokens
            ]);

            // Schedule job processing
            $this->schedule_job_processing($job_id);

            return $job_id;
        }

        return false;
    }

    /**
     * Process bulk job
     *
     * @param int $job_id Job ID
     * @return array Processing result
     */
    public function process_job(int $job_id): array
    {
        global $wpdb;

        $job = $this->get_job($job_id);

        if (!$job) {
            return [
                'status' => 'error',
                'message' => __('Job not found', Plugin::TEXT_DOMAIN)
            ];
        }

        if ($job->status !== self::STATUS_PENDING) {
            return [
                'status' => 'error',
                'message' => __('Job is not in pending status', Plugin::TEXT_DOMAIN)
            ];
        }

        // Update job status to processing
        $this->update_job_status($job_id, self::STATUS_PROCESSING);

        try {
            $topics = json_decode($job->topics, true);
            $settings = json_decode($job->settings, true);
            $results = [];
            $errors = [];
            $total_tokens = 0;
            $completed_items = 0;
            $failed_items = 0;

            foreach ($topics as $index => $topic) {
                try {
                    // Generate content for this topic
                    if ($job->template_id) {
                        $result = $this->template_manager->generate_from_template(
                            $job->template_id,
                            $topic,
                            $settings['variables'] ?? []
                        );
                    } else {
                        $result = $this->generate_standard_content($topic, $settings);
                    }

                    if ($result['status'] === 'success') {
                        // Create WordPress post
                        $post_id = $this->create_post_from_result($result['data'], $topic, $settings);
                        
                        if ($post_id) {
                            $results[] = [
                                'topic' => $topic,
                                'post_id' => $post_id,
                                'tokens_used' => $result['data']['total_tokens'] ?? 0,
                                'status' => 'success'
                            ];
                            $total_tokens += $result['data']['total_tokens'] ?? 0;
                            $completed_items++;
                        } else {
                            throw new \Exception('Failed to create WordPress post');
                        }
                    } else {
                        throw new \Exception($result['message']);
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'topic' => $topic,
                        'error' => $e->getMessage(),
                        'index' => $index
                    ];
                    $failed_items++;
                }

                // Update progress
                $progress = (int) (($completed_items + $failed_items) / count($topics) * 100);
                $this->update_job_progress($job_id, $progress, $completed_items, $failed_items);

                // Add delay to avoid rate limiting
                if ($index < count($topics) - 1) {
                    sleep(2); // 2 second delay between requests
                }
            }

            // Calculate actual cost
            $actual_cost = $this->calculate_actual_cost($total_tokens);

            // Update job completion
            $wpdb->update(
                $this->jobs_table,
                [
                    'status' => $failed_items === 0 ? self::STATUS_COMPLETED : 
                               ($completed_items === 0 ? self::STATUS_FAILED : self::STATUS_COMPLETED),
                    'progress' => 100,
                    'completed_items' => $completed_items,
                    'failed_items' => $failed_items,
                    'results' => wp_json_encode($results),
                    'error_log' => !empty($errors) ? wp_json_encode($errors) : null,
                    'actual_tokens' => $total_tokens,
                    'actual_cost' => $actual_cost,
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $job_id],
                ['%s', '%d', '%d', '%d', '%s', '%s', '%d', '%f', '%s'],
                ['%d']
            );

            Logger::info('Bulk job completed', [
                'job_id' => $job_id,
                'completed_items' => $completed_items,
                'failed_items' => $failed_items,
                'total_tokens' => $total_tokens,
                'actual_cost' => $actual_cost
            ]);

            return [
                'status' => 'success',
                'data' => [
                    'completed_items' => $completed_items,
                    'failed_items' => $failed_items,
                    'total_tokens' => $total_tokens,
                    'actual_cost' => $actual_cost,
                    'results' => $results,
                    'errors' => $errors
                ]
            ];

        } catch (\Exception $e) {
            // Mark job as failed
            $this->update_job_status($job_id, self::STATUS_FAILED, $e->getMessage());

            Logger::error('Bulk job processing failed', [
                'job_id' => $job_id,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get job by ID
     *
     * @param int $job_id Job ID
     * @return object|null Job object or null if not found
     */
    public function get_job(int $job_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->jobs_table} WHERE id = %d",
            $job_id
        ));
    }

    /**
     * Get all jobs
     *
     * @param int $page Page number
     * @param int $per_page Items per page
     * @param string $status Filter by status
     * @return array Jobs with pagination info
     */
    public function get_jobs(int $page = 1, int $per_page = 20, string $status = ''): array
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        $where_clause = '';
        $params = [];

        if (!empty($status)) {
            $where_clause = 'WHERE status = %s';
            $params[] = $status;
        }

        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->jobs_table} {$where_clause}";
        $total = (int) $wpdb->get_var(
            empty($params) ? $total_query : $wpdb->prepare($total_query, ...$params)
        );

        // Get jobs
        $jobs_query = "SELECT * FROM {$this->jobs_table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $jobs = $wpdb->get_results($wpdb->prepare($jobs_query, ...$params));

        return [
            'jobs' => $jobs ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Cancel job
     *
     * @param int $job_id Job ID
     * @return bool True on success, false on failure
     */
    public function cancel_job(int $job_id): bool
    {
        $job = $this->get_job($job_id);

        if (!$job || in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED])) {
            return false;
        }

        return $this->update_job_status($job_id, self::STATUS_CANCELLED);
    }

    /**
     * Delete job
     *
     * @param int $job_id Job ID
     * @return bool True on success, false on failure
     */
    public function delete_job(int $job_id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->jobs_table,
            ['id' => $job_id],
            ['%d']
        );

        if ($result) {
            Logger::info('Bulk job deleted', ['job_id' => $job_id]);
            return true;
        }

        return false;
    }

    /**
     * Get job statistics
     *
     * @return array Job statistics
     */
    public function get_job_statistics(): array
    {
        global $wpdb;

        $cache_key = 'bulk_job_stats';
        $cached_stats = Cache::get($cache_key);

        if ($cached_stats !== null) {
            return $cached_stats;
        }

        $stats = $wpdb->get_results(
            "SELECT 
                status,
                COUNT(*) as count,
                SUM(total_items) as total_items,
                SUM(completed_items) as completed_items,
                SUM(failed_items) as failed_items,
                SUM(actual_tokens) as total_tokens,
                SUM(actual_cost) as total_cost
             FROM {$this->jobs_table} 
             GROUP BY status"
        );

        $result = [
            'total_jobs' => 0,
            'total_items' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'by_status' => []
        ];

        foreach ($stats as $stat) {
            $result['total_jobs'] += $stat->count;
            $result['total_items'] += $stat->total_items;
            $result['total_tokens'] += $stat->total_tokens;
            $result['total_cost'] += $stat->total_cost;

            $result['by_status'][$stat->status] = [
                'count' => (int) $stat->count,
                'total_items' => (int) $stat->total_items,
                'completed_items' => (int) $stat->completed_items,
                'failed_items' => (int) $stat->failed_items,
                'total_tokens' => (int) $stat->total_tokens,
                'total_cost' => (float) $stat->total_cost
            ];
        }

        Cache::set($cache_key, $result, 300); // Cache for 5 minutes

        return $result;
    }

    /**
     * Schedule job processing
     *
     * @param int $job_id Job ID
     * @return void
     */
    private function schedule_job_processing(int $job_id): void
    {
        // Schedule immediate processing using WordPress cron
        wp_schedule_single_event(time(), 'autobotwriter_process_bulk_job', [$job_id]);
    }

    /**
     * Update job status
     *
     * @param int $job_id Job ID
     * @param string $status New status
     * @param string $error_message Error message (optional)
     * @return bool True on success, false on failure
     */
    private function update_job_status(int $job_id, string $status, string $error_message = ''): bool
    {
        global $wpdb;

        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];

        if ($status === self::STATUS_PROCESSING && !$wpdb->get_var($wpdb->prepare("SELECT started_at FROM {$this->jobs_table} WHERE id = %d", $job_id))) {
            $update_data['started_at'] = current_time('mysql');
        }

        if (!empty($error_message)) {
            $update_data['error_log'] = wp_json_encode([['error' => $error_message, 'timestamp' => current_time('mysql')]]);
        }

        $format = array_fill(0, count($update_data), '%s');

        return $wpdb->update(
            $this->jobs_table,
            $update_data,
            ['id' => $job_id],
            $format,
            ['%d']
        ) !== false;
    }

    /**
     * Update job progress
     *
     * @param int $job_id Job ID
     * @param int $progress Progress percentage
     * @param int $completed_items Completed items count
     * @param int $failed_items Failed items count
     * @return void
     */
    private function update_job_progress(int $job_id, int $progress, int $completed_items, int $failed_items): void
    {
        global $wpdb;

        $wpdb->update(
            $this->jobs_table,
            [
                'progress' => $progress,
                'completed_items' => $completed_items,
                'failed_items' => $failed_items,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $job_id],
            ['%d', '%d', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * Estimate tokens for job
     *
     * @param array $job_data Job data
     * @return int Estimated tokens
     */
    private function estimate_tokens(array $job_data): int
    {
        $topics = $job_data['topics'];
        $template_id = $job_data['template_id'];

        if ($template_id) {
            $template = $this->template_manager->get_template($template_id);
            if ($template) {
                $tokens_per_item = 0;
                foreach ($template->structure as $section) {
                    $tokens_per_item += $section['max_tokens'] ?? 200;
                }
                return count($topics) * $tokens_per_item;
            }
        }

        // Default estimation: 800 tokens per topic
        return count($topics) * 800;
    }

    /**
     * Estimate cost for tokens
     *
     * @param int $tokens Number of tokens
     * @return float Estimated cost
     */
    private function estimate_cost(int $tokens): float
    {
        // Default to GPT-3.5 Turbo pricing: $0.0015 per 1K tokens
        return ($tokens / 1000) * 0.0015;
    }

    /**
     * Calculate actual cost
     *
     * @param int $tokens Actual tokens used
     * @return float Actual cost
     */
    private function calculate_actual_cost(int $tokens): float
    {
        return $this->estimate_cost($tokens);
    }

    /**
     * Generate standard content (without template)
     *
     * @param string $topic Topic
     * @param array $settings Settings
     * @return array Generation result
     */
    private function generate_standard_content(string $topic, array $settings): array
    {
        $ai_provider = Plugin::get_instance()->get_openai_service();

        $prompt = sprintf(
            "Write a comprehensive blog post about '%s'. Include an introduction, %d main sections, and a conclusion.",
            $topic,
            $settings['headings'] ?? 3
        );

        return $ai_provider->generate_content($prompt, [
            'max_tokens' => $settings['max_tokens'] ?? 800,
            'temperature' => $settings['temperature'] ?? 0.7
        ]);
    }

    /**
     * Create WordPress post from generation result
     *
     * @param array $content_data Generated content data
     * @param string $topic Topic
     * @param array $settings Settings
     * @return int|false Post ID on success, false on failure
     */
    private function create_post_from_result(array $content_data, string $topic, array $settings): int|false
    {
        $title = $settings['title_prefix'] ? $settings['title_prefix'] . ' ' . $topic : $topic;
        
        if (isset($content_data['content'])) {
            // Template-based content
            $content = '';
            foreach ($content_data['content'] as $section) {
                $content .= '<h2>' . esc_html($section['title']) . '</h2>' . "\n";
                $content .= $section['content'] . "\n\n";
            }
        } else {
            // Standard content
            $content = $content_data['data'] ?? $content_data;
        }

        $post_data = [
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($content),
            'post_status' => $settings['post_status'] ?? 'draft',
            'post_author' => $settings['author_id'] ?? get_current_user_id(),
            'post_type' => 'post'
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return false;
        }

        // Set categories and tags if specified
        if (!empty($settings['category_id'])) {
            wp_set_post_categories($post_id, [(int) $settings['category_id']]);
        }

        if (!empty($settings['tags'])) {
            wp_set_post_tags($post_id, $settings['tags']);
        }

        return $post_id;
    }
}
