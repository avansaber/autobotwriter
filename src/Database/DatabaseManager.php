<?php
/**
 * Database Manager
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Database;

use AutoBotWriter\Core\Plugin;

/**
 * Database Manager Class
 */
class DatabaseManager
{
    /**
     * Posts schedule table name
     */
    private string $posts_table;

    /**
     * Parameters table name
     */
    private string $parameters_table;

    /**
     * WordPress database instance
     */
    private \wpdb $wpdb;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->posts_table = $wpdb->prefix . 'autobotwriter_posts_schedule';
        $this->parameters_table = $wpdb->prefix . 'autobotwriter_parameters';
    }

    /**
     * Create database tables
     *
     * @return bool True on success, false on failure
     */
    public function create_tables(): bool
    {
        try {
            $charset_collate = $this->wpdb->get_charset_collate();

            // Create posts schedule table
            $posts_table_sql = $this->get_posts_table_sql($charset_collate);
            $this->execute_table_creation($posts_table_sql);

            // Create parameters table
            $parameters_table_sql = $this->get_parameters_table_sql($charset_collate);
            $this->execute_table_creation($parameters_table_sql);

            return true;

        } catch (\Exception $e) {
            error_log('AutoBotWriter: Failed to create tables - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get posts table SQL
     *
     * @param string $charset_collate Charset collation
     * @return string
     */
    private function get_posts_table_sql(string $charset_collate): string
    {
        return "CREATE TABLE IF NOT EXISTS {$this->posts_table} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `post_title` varchar(255) NOT NULL DEFAULT '',
            `blog_title` varchar(255) NOT NULL DEFAULT '',
            `category` int(11) NOT NULL DEFAULT 0,
            `author_id` int(11) NOT NULL DEFAULT 1,
            `tags` TEXT NOT NULL,
            `post_id` int(11) NOT NULL DEFAULT 0,
            `creation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `update_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            `publish_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            `include_keywords` varchar(255) NOT NULL DEFAULT '',
            `exclude_keywords` varchar(255) NOT NULL DEFAULT '',
            `status` varchar(16) NOT NULL DEFAULT 'pending',
            `published` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY status (status),
            KEY creation_date (creation_date),
            KEY post_id (post_id),
            KEY author_id (author_id),
            KEY category (category)
        ) {$charset_collate};";
    }

    /**
     * Get parameters table SQL
     *
     * @param string $charset_collate Charset collation
     * @return string
     */
    private function get_parameters_table_sql(string $charset_collate): string
    {
        return "CREATE TABLE IF NOT EXISTS {$this->parameters_table} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `openai_api_key` TEXT NOT NULL,
            `selected_model` varchar(50) NOT NULL DEFAULT 'gpt-3.5-turbo',
            `tokens` int(11) NOT NULL DEFAULT 800,
            `temperature` decimal(3,2) NOT NULL DEFAULT 0.10,
            `headings` int(11) NOT NULL DEFAULT 3,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
    }

    /**
     * Execute table creation
     *
     * @param string $sql SQL statement
     * @return void
     * @throws \Exception If table creation fails
     */
    private function execute_table_creation(string $sql): void
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if (empty($result)) {
            throw new \Exception('Failed to create database table');
        }
    }

    /**
     * Get posts table name
     *
     * @return string
     */
    public function get_posts_table(): string
    {
        return $this->posts_table;
    }

    /**
     * Get parameters table name
     *
     * @return string
     */
    public function get_parameters_table(): string
    {
        return $this->parameters_table;
    }

    /**
     * Insert post schedule
     *
     * @param array $data Post data
     * @return int|false Post ID on success, false on failure
     */
    public function insert_post_schedule(array $data): int|false
    {
        $defaults = [
            'post_title' => '',
            'blog_title' => '',
            'category' => 0,
            'author_id' => 1,
            'tags' => '',
            'post_id' => 0,
            'creation_date' => current_time('mysql'),
            'update_date' => '0000-00-00 00:00:00',
            'publish_date' => '0000-00-00 00:00:00',
            'include_keywords' => '',
            'exclude_keywords' => '',
            'status' => 'pending',
            'published' => 0
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->posts_table,
            $data,
            ['%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update post schedule
     *
     * @param int $id Post schedule ID
     * @param array $data Update data
     * @return bool True on success, false on failure
     */
    public function update_post_schedule(int $id, array $data): bool
    {
        $data['update_date'] = current_time('mysql');

        $format = [];
        foreach ($data as $key => $value) {
            $format[] = is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s');
        }

        $result = $this->wpdb->update(
            $this->posts_table,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get post schedule by ID
     *
     * @param int $id Post schedule ID
     * @return object|null Post schedule object or null if not found
     */
    public function get_post_schedule(int $id): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->posts_table} WHERE id = %d",
                $id
            )
        );

        return $result ?: null;
    }

    /**
     * Get pending post schedules
     *
     * @param int $limit Number of posts to retrieve
     * @return array Array of post schedule objects
     */
    public function get_pending_posts(int $limit = 10): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->posts_table} 
                WHERE status = %s 
                ORDER BY creation_date ASC, id ASC 
                LIMIT %d",
                'pending',
                $limit
            )
        );

        return $results ?: [];
    }

    /**
     * Get all post schedules with pagination
     *
     * @param int $page Page number (1-based)
     * @param int $per_page Posts per page
     * @param string $status Filter by status (optional)
     * @return array Array with 'posts' and 'total' keys
     */
    public function get_post_schedules(int $page = 1, int $per_page = 20, string $status = ''): array
    {
        $offset = ($page - 1) * $per_page;
        
        $where_clause = "WHERE status != 'deleted'";
        $params = [];

        if (!empty($status)) {
            $where_clause .= " AND status = %s";
            $params[] = $status;
        }

        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->posts_table} {$where_clause}";
        $total = (int) $this->wpdb->get_var(
            empty($params) ? $total_query : $this->wpdb->prepare($total_query, ...$params)
        );

        // Get posts
        $posts_query = "SELECT * FROM {$this->posts_table} {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $posts = $this->wpdb->get_results(
            $this->wpdb->prepare($posts_query, ...$params)
        );

        return [
            'posts' => $posts ?: [],
            'total' => $total
        ];
    }

    /**
     * Delete post schedule
     *
     * @param int $id Post schedule ID
     * @return bool True on success, false on failure
     */
    public function delete_post_schedule(int $id): bool
    {
        return $this->update_post_schedule($id, ['status' => 'deleted']);
    }

    /**
     * Get settings
     *
     * @return array Settings array
     */
    public function get_settings(): array
    {
        $settings = $this->wpdb->get_row(
            "SELECT * FROM {$this->parameters_table} ORDER BY id DESC LIMIT 1",
            ARRAY_A
        );

        if (!$settings) {
            return [
                'openai_api_key' => '',
                'selected_model' => 'gpt-3.5-turbo',
                'tokens' => 800,
                'temperature' => 0.1,
                'headings' => 3
            ];
        }

        return $settings;
    }

    /**
     * Save settings
     *
     * @param array $settings Settings data
     * @return bool True on success, false on failure
     */
    public function save_settings(array $settings): bool
    {
        $existing = $this->wpdb->get_row("SELECT id FROM {$this->parameters_table} LIMIT 1");

        $data = [
            'openai_api_key' => $settings['openai_api_key'] ?? '',
            'selected_model' => $settings['selected_model'] ?? 'gpt-3.5-turbo',
            'tokens' => (int) ($settings['tokens'] ?? 800),
            'temperature' => (float) ($settings['temperature'] ?? 0.1),
            'headings' => (int) ($settings['headings'] ?? 3)
        ];

        if ($existing) {
            $result = $this->wpdb->update(
                $this->parameters_table,
                $data,
                ['id' => $existing->id],
                ['%s', '%s', '%d', '%f', '%d'],
                ['%d']
            );
        } else {
            $result = $this->wpdb->insert(
                $this->parameters_table,
                $data,
                ['%s', '%s', '%d', '%f', '%d']
            );
        }

        return $result !== false;
    }

    /**
     * Clean up old records
     *
     * @param int $days Number of days to keep
     * @return int Number of records cleaned up
     */
    public function cleanup_old_records(int $days = 30): int
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->posts_table} 
                WHERE status = 'deleted' 
                AND update_date < %s 
                AND update_date != '0000-00-00 00:00:00'",
                $cutoff_date
            )
        );

        return (int) $result;
    }
}
