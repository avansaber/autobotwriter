<?php
/**
 * Enhanced Scheduling Manager
 *
 * @package AutoBotWriter
 * @since 1.7.0
 */

namespace AutoBotWriter\Admin;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Database\DatabaseManager;
use AutoBotWriter\Utils\Logger;

/**
 * Scheduling Manager Class
 */
class SchedulingManager
{
    /**
     * Database manager instance
     */
    private DatabaseManager $db_manager;

    /**
     * Schedules table name
     */
    private string $schedules_table;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->db_manager = new DatabaseManager();
        $this->schedules_table = $wpdb->prefix . 'autobotwriter_schedules';
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Register custom cron schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
        
        // Hook into WordPress cron
        add_action('autobotwriter_scheduled_generation', [$this, 'process_scheduled_generation']);
        add_action('autobotwriter_cleanup_schedules', [$this, 'cleanup_old_schedules']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('autobotwriter_cleanup_schedules')) {
            wp_schedule_event(time(), 'daily', 'autobotwriter_cleanup_schedules');
        }
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_cron_schedules(array $schedules): array
    {
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', Plugin::TEXT_DOMAIN)
        ];

        $schedules['every_30_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', Plugin::TEXT_DOMAIN)
        ];

        $schedules['every_2_hours'] = [
            'interval' => 2 * HOUR_IN_SECONDS,
            'display' => __('Every 2 Hours', Plugin::TEXT_DOMAIN)
        ];

        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', Plugin::TEXT_DOMAIN)
        ];

        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly', Plugin::TEXT_DOMAIN)
        ];

        return $schedules;
    }

    /**
     * Create schedules table
     *
     * @return bool True on success, false on failure
     */
    public function create_schedules_table(): bool
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->schedules_table} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `schedule_type` varchar(50) NOT NULL DEFAULT 'recurring',
            `frequency` varchar(50) NOT NULL DEFAULT 'daily',
            `custom_cron` varchar(100) DEFAULT NULL,
            `template_id` int(11) DEFAULT NULL,
            `topic_source` varchar(50) NOT NULL DEFAULT 'manual',
            `topics` longtext DEFAULT NULL,
            `topic_keywords` text DEFAULT NULL,
            `rss_feeds` text DEFAULT NULL,
            `settings` text NOT NULL,
            `post_settings` text NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `last_run` datetime DEFAULT NULL,
            `next_run` datetime DEFAULT NULL,
            `run_count` int(11) NOT NULL DEFAULT 0,
            `success_count` int(11) NOT NULL DEFAULT 0,
            `failure_count` int(11) NOT NULL DEFAULT 0,
            `created_by` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY schedule_type (schedule_type),
            KEY frequency (frequency),
            KEY next_run (next_run),
            KEY created_by (created_by)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        return !empty($result);
    }

    /**
     * Create recurring schedule
     *
     * @param array $schedule_data Schedule configuration
     * @return int|false Schedule ID on success, false on failure
     */
    public function create_schedule(array $schedule_data): int|false
    {
        global $wpdb;

        $defaults = [
            'name' => '',
            'description' => '',
            'schedule_type' => 'recurring',
            'frequency' => 'daily',
            'custom_cron' => null,
            'template_id' => null,
            'topic_source' => 'manual',
            'topics' => [],
            'topic_keywords' => '',
            'rss_feeds' => [],
            'settings' => [],
            'post_settings' => [],
            'is_active' => 1,
            'created_by' => get_current_user_id()
        ];

        $schedule_data = wp_parse_args($schedule_data, $defaults);

        // Validate required fields
        if (empty($schedule_data['name'])) {
            return false;
        }

        // Calculate next run time
        $next_run = $this->calculate_next_run($schedule_data['frequency'], $schedule_data['custom_cron']);

        $result = $wpdb->insert(
            $this->schedules_table,
            [
                'name' => sanitize_text_field($schedule_data['name']),
                'description' => sanitize_textarea_field($schedule_data['description']),
                'schedule_type' => sanitize_text_field($schedule_data['schedule_type']),
                'frequency' => sanitize_text_field($schedule_data['frequency']),
                'custom_cron' => $schedule_data['custom_cron'] ? sanitize_text_field($schedule_data['custom_cron']) : null,
                'template_id' => $schedule_data['template_id'] ? (int) $schedule_data['template_id'] : null,
                'topic_source' => sanitize_text_field($schedule_data['topic_source']),
                'topics' => wp_json_encode($schedule_data['topics']),
                'topic_keywords' => sanitize_text_field($schedule_data['topic_keywords']),
                'rss_feeds' => wp_json_encode($schedule_data['rss_feeds']),
                'settings' => wp_json_encode($schedule_data['settings']),
                'post_settings' => wp_json_encode($schedule_data['post_settings']),
                'is_active' => (int) $schedule_data['is_active'],
                'next_run' => $next_run,
                'created_by' => (int) $schedule_data['created_by']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
        );

        if ($result) {
            $schedule_id = $wpdb->insert_id;
            
            // Schedule WordPress cron event
            $this->schedule_cron_event($schedule_id, $schedule_data['frequency'], $next_run);
            
            Logger::info('Recurring schedule created', [
                'schedule_id' => $schedule_id,
                'name' => $schedule_data['name'],
                'frequency' => $schedule_data['frequency'],
                'next_run' => $next_run
            ]);

            return $schedule_id;
        }

        return false;
    }

    /**
     * Update schedule
     *
     * @param int $schedule_id Schedule ID
     * @param array $schedule_data Schedule data
     * @return bool True on success, false on failure
     */
    public function update_schedule(int $schedule_id, array $schedule_data): bool
    {
        global $wpdb;

        $current_schedule = $this->get_schedule($schedule_id);
        if (!$current_schedule) {
            return false;
        }

        $update_data = [];
        $format = [];

        // Update allowed fields
        $allowed_fields = [
            'name', 'description', 'frequency', 'custom_cron', 'template_id',
            'topic_source', 'topics', 'topic_keywords', 'rss_feeds',
            'settings', 'post_settings', 'is_active'
        ];

        foreach ($allowed_fields as $field) {
            if (isset($schedule_data[$field])) {
                switch ($field) {
                    case 'name':
                    case 'frequency':
                    case 'custom_cron':
                    case 'topic_source':
                    case 'topic_keywords':
                        $update_data[$field] = sanitize_text_field($schedule_data[$field]);
                        $format[] = '%s';
                        break;
                    case 'description':
                        $update_data[$field] = sanitize_textarea_field($schedule_data[$field]);
                        $format[] = '%s';
                        break;
                    case 'template_id':
                    case 'is_active':
                        $update_data[$field] = (int) $schedule_data[$field];
                        $format[] = '%d';
                        break;
                    case 'topics':
                    case 'rss_feeds':
                    case 'settings':
                    case 'post_settings':
                        $update_data[$field] = wp_json_encode($schedule_data[$field]);
                        $format[] = '%s';
                        break;
                }
            }
        }

        // Recalculate next run if frequency changed
        if (isset($schedule_data['frequency']) || isset($schedule_data['custom_cron'])) {
            $frequency = $schedule_data['frequency'] ?? $current_schedule->frequency;
            $custom_cron = $schedule_data['custom_cron'] ?? $current_schedule->custom_cron;
            $update_data['next_run'] = $this->calculate_next_run($frequency, $custom_cron);
            $format[] = '%s';

            // Reschedule cron event
            $this->unschedule_cron_event($schedule_id);
            $this->schedule_cron_event($schedule_id, $frequency, $update_data['next_run']);
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->schedules_table,
            $update_data,
            ['id' => $schedule_id],
            $format,
            ['%d']
        );

        if ($result !== false) {
            Logger::info('Schedule updated', ['schedule_id' => $schedule_id]);
            return true;
        }

        return false;
    }

    /**
     * Get schedule by ID
     *
     * @param int $schedule_id Schedule ID
     * @return object|null Schedule object or null if not found
     */
    public function get_schedule(int $schedule_id): ?object
    {
        global $wpdb;

        $schedule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->schedules_table} WHERE id = %d",
            $schedule_id
        ));

        if ($schedule) {
            // Decode JSON fields
            $schedule->topics = json_decode($schedule->topics, true) ?: [];
            $schedule->rss_feeds = json_decode($schedule->rss_feeds, true) ?: [];
            $schedule->settings = json_decode($schedule->settings, true) ?: [];
            $schedule->post_settings = json_decode($schedule->post_settings, true) ?: [];
        }

        return $schedule;
    }

    /**
     * Get all schedules
     *
     * @param bool $active_only Only active schedules
     * @return array Schedules array
     */
    public function get_schedules(bool $active_only = false): array
    {
        global $wpdb;

        $where_clause = $active_only ? 'WHERE is_active = 1' : '';
        
        $schedules = $wpdb->get_results(
            "SELECT * FROM {$this->schedules_table} {$where_clause} ORDER BY created_at DESC"
        );

        // Decode JSON fields for each schedule
        foreach ($schedules as &$schedule) {
            $schedule->topics = json_decode($schedule->topics, true) ?: [];
            $schedule->rss_feeds = json_decode($schedule->rss_feeds, true) ?: [];
            $schedule->settings = json_decode($schedule->settings, true) ?: [];
            $schedule->post_settings = json_decode($schedule->post_settings, true) ?: [];
        }

        return $schedules;
    }

    /**
     * Delete schedule
     *
     * @param int $schedule_id Schedule ID
     * @return bool True on success, false on failure
     */
    public function delete_schedule(int $schedule_id): bool
    {
        global $wpdb;

        // Unschedule cron event
        $this->unschedule_cron_event($schedule_id);

        $result = $wpdb->delete(
            $this->schedules_table,
            ['id' => $schedule_id],
            ['%d']
        );

        if ($result) {
            Logger::info('Schedule deleted', ['schedule_id' => $schedule_id]);
            return true;
        }

        return false;
    }

    /**
     * Toggle schedule active status
     *
     * @param int $schedule_id Schedule ID
     * @param bool $is_active Active status
     * @return bool True on success, false on failure
     */
    public function toggle_schedule(int $schedule_id, bool $is_active): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->schedules_table,
            ['is_active' => (int) $is_active],
            ['id' => $schedule_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            if ($is_active) {
                // Reschedule if activating
                $schedule = $this->get_schedule($schedule_id);
                if ($schedule) {
                    $this->schedule_cron_event($schedule_id, $schedule->frequency, $schedule->next_run);
                }
            } else {
                // Unschedule if deactivating
                $this->unschedule_cron_event($schedule_id);
            }

            Logger::info('Schedule toggled', [
                'schedule_id' => $schedule_id,
                'is_active' => $is_active
            ]);

            return true;
        }

        return false;
    }

    /**
     * Process scheduled generation
     *
     * @param int $schedule_id Schedule ID
     * @return void
     */
    public function process_scheduled_generation(int $schedule_id): void
    {
        $schedule = $this->get_schedule($schedule_id);

        if (!$schedule || !$schedule->is_active) {
            return;
        }

        try {
            global $wpdb;

            // Update run statistics
            $wpdb->update(
                $this->schedules_table,
                [
                    'last_run' => current_time('mysql'),
                    'run_count' => $schedule->run_count + 1
                ],
                ['id' => $schedule_id],
                ['%s', '%d'],
                ['%d']
            );

            // Get topic for generation
            $topic = $this->get_topic_for_schedule($schedule);

            if (!$topic) {
                throw new \Exception('No topic available for generation');
            }

            // Generate content
            if ($schedule->template_id) {
                $template_manager = new TemplateManager();
                $result = $template_manager->generate_from_template(
                    $schedule->template_id,
                    $topic,
                    $schedule->settings
                );
            } else {
                $ai_provider = Plugin::get_instance()->get_openai_service();
                $result = $ai_provider->generate_content(
                    "Write a comprehensive blog post about: {$topic}",
                    $schedule->settings
                );
            }

            if ($result['status'] === 'success') {
                // Create WordPress post
                $post_id = $this->create_scheduled_post($result, $topic, $schedule);

                if ($post_id) {
                    // Update success count
                    $wpdb->update(
                        $this->schedules_table,
                        ['success_count' => $schedule->success_count + 1],
                        ['id' => $schedule_id],
                        ['%d'],
                        ['%d']
                    );

                    Logger::info('Scheduled content generated successfully', [
                        'schedule_id' => $schedule_id,
                        'topic' => $topic,
                        'post_id' => $post_id
                    ]);
                } else {
                    throw new \Exception('Failed to create WordPress post');
                }
            } else {
                throw new \Exception($result['message']);
            }

            // Calculate and update next run
            $next_run = $this->calculate_next_run($schedule->frequency, $schedule->custom_cron);
            $wpdb->update(
                $this->schedules_table,
                ['next_run' => $next_run],
                ['id' => $schedule_id],
                ['%s'],
                ['%d']
            );

            // Reschedule for next run
            $this->schedule_cron_event($schedule_id, $schedule->frequency, $next_run);

        } catch (\Exception $e) {
            global $wpdb;

            // Update failure count
            $wpdb->update(
                $this->schedules_table,
                ['failure_count' => $schedule->failure_count + 1],
                ['id' => $schedule_id],
                ['%d'],
                ['%d']
            );

            Logger::error('Scheduled generation failed', [
                'schedule_id' => $schedule_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get topic for schedule
     *
     * @param object $schedule Schedule object
     * @return string|null Topic or null if none available
     */
    private function get_topic_for_schedule(object $schedule): ?string
    {
        switch ($schedule->topic_source) {
            case 'manual':
                if (!empty($schedule->topics)) {
                    // Return random topic from manual list
                    return $schedule->topics[array_rand($schedule->topics)];
                }
                break;

            case 'keywords':
                if (!empty($schedule->topic_keywords)) {
                    // Generate topic based on keywords
                    $keywords = explode(',', $schedule->topic_keywords);
                    $keyword = trim($keywords[array_rand($keywords)]);
                    return "How to " . $keyword; // Simple topic generation
                }
                break;

            case 'rss':
                return $this->get_topic_from_rss($schedule->rss_feeds);

            case 'trending':
                return $this->get_trending_topic($schedule->topic_keywords);
        }

        return null;
    }

    /**
     * Get topic from RSS feeds
     *
     * @param array $rss_feeds RSS feed URLs
     * @return string|null Topic or null if none found
     */
    private function get_topic_from_rss(array $rss_feeds): ?string
    {
        if (empty($rss_feeds)) {
            return null;
        }

        foreach ($rss_feeds as $feed_url) {
            try {
                $rss = fetch_feed($feed_url);
                
                if (!is_wp_error($rss)) {
                    $items = $rss->get_items(0, 5); // Get latest 5 items
                    
                    if (!empty($items)) {
                        $item = $items[array_rand($items)];
                        return $item->get_title();
                    }
                }
            } catch (\Exception $e) {
                Logger::warning('Failed to fetch RSS feed', [
                    'feed_url' => $feed_url,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return null;
    }

    /**
     * Get trending topic
     *
     * @param string $keywords Keywords for trending topics
     * @return string|null Trending topic or null if none found
     */
    private function get_trending_topic(string $keywords): ?string
    {
        // This would integrate with trending topics APIs
        // For now, return a simple generated topic
        if (!empty($keywords)) {
            $keyword_list = explode(',', $keywords);
            $keyword = trim($keyword_list[array_rand($keyword_list)]);
            return "Latest trends in " . $keyword;
        }

        return null;
    }

    /**
     * Create scheduled post
     *
     * @param array $generation_result Generation result
     * @param string $topic Topic
     * @param object $schedule Schedule object
     * @return int|false Post ID on success, false on failure
     */
    private function create_scheduled_post(array $generation_result, string $topic, object $schedule): int|false
    {
        $post_settings = $schedule->post_settings;
        
        $title = $post_settings['title_prefix'] ?? '';
        $title .= $title ? ' ' . $topic : $topic;

        $content = '';
        if (isset($generation_result['data']['content'])) {
            // Template-based content
            foreach ($generation_result['data']['content'] as $section) {
                $content .= '<h2>' . esc_html($section['title']) . '</h2>' . "\n";
                $content .= $section['content'] . "\n\n";
            }
        } else {
            // Standard content
            $content = $generation_result['data'];
        }

        $post_data = [
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($content),
            'post_status' => $post_settings['post_status'] ?? 'draft',
            'post_author' => $post_settings['author_id'] ?? $schedule->created_by,
            'post_type' => 'post'
        ];

        // Set publish date if specified
        if (!empty($post_settings['publish_date'])) {
            $post_data['post_date'] = $post_settings['publish_date'];
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return false;
        }

        // Set categories and tags
        if (!empty($post_settings['category_id'])) {
            wp_set_post_categories($post_id, [(int) $post_settings['category_id']]);
        }

        if (!empty($post_settings['tags'])) {
            wp_set_post_tags($post_id, $post_settings['tags']);
        }

        return $post_id;
    }

    /**
     * Calculate next run time
     *
     * @param string $frequency Frequency
     * @param string|null $custom_cron Custom cron expression
     * @return string Next run time
     */
    private function calculate_next_run(string $frequency, ?string $custom_cron = null): string
    {
        if ($custom_cron) {
            // Parse custom cron expression (simplified)
            // This would need a proper cron parser for production
            return date('Y-m-d H:i:s', strtotime('+1 hour'));
        }

        $intervals = [
            'every_15_minutes' => '+15 minutes',
            'every_30_minutes' => '+30 minutes',
            'hourly' => '+1 hour',
            'every_2_hours' => '+2 hours',
            'every_6_hours' => '+6 hours',
            'twicedaily' => '+12 hours',
            'daily' => '+1 day',
            'weekly' => '+1 week'
        ];

        $interval = $intervals[$frequency] ?? '+1 day';
        return date('Y-m-d H:i:s', strtotime($interval));
    }

    /**
     * Schedule WordPress cron event
     *
     * @param int $schedule_id Schedule ID
     * @param string $frequency Frequency
     * @param string $next_run Next run time
     * @return void
     */
    private function schedule_cron_event(int $schedule_id, string $frequency, string $next_run): void
    {
        $timestamp = strtotime($next_run);
        wp_schedule_single_event($timestamp, 'autobotwriter_scheduled_generation', [$schedule_id]);
    }

    /**
     * Unschedule WordPress cron event
     *
     * @param int $schedule_id Schedule ID
     * @return void
     */
    private function unschedule_cron_event(int $schedule_id): void
    {
        wp_clear_scheduled_hook('autobotwriter_scheduled_generation', [$schedule_id]);
    }

    /**
     * Clean up old schedules
     *
     * @return void
     */
    public function cleanup_old_schedules(): void
    {
        global $wpdb;

        // Delete inactive schedules older than 30 days
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->schedules_table} 
             WHERE is_active = 0 
             AND updated_at < %s",
            $cutoff_date
        ));

        if ($deleted > 0) {
            Logger::info('Old schedules cleaned up', ['deleted_count' => $deleted]);
        }
    }
}
