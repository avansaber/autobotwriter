<?php
/**
 * Template Manager
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
 * Template Manager Class
 */
class TemplateManager
{
    /**
     * Database manager instance
     */
    private DatabaseManager $db_manager;

    /**
     * Templates table name
     */
    private string $templates_table;

    /**
     * Built-in templates
     */
    private array $builtin_templates = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->db_manager = new DatabaseManager();
        $this->templates_table = $wpdb->prefix . 'autobotwriter_templates';
        $this->init_builtin_templates();
    }

    /**
     * Initialize built-in templates
     *
     * @return void
     */
    private function init_builtin_templates(): void
    {
        $this->builtin_templates = [
            'blog_post' => [
                'name' => __('Blog Post', Plugin::TEXT_DOMAIN),
                'description' => __('Standard blog post template with introduction, main content, and conclusion', Plugin::TEXT_DOMAIN),
                'category' => 'general',
                'structure' => [
                    'introduction' => [
                        'prompt' => 'Write an engaging introduction for a blog post about "{topic}". Hook the reader and provide context.',
                        'max_tokens' => 200
                    ],
                    'main_content' => [
                        'prompt' => 'Write the main content for a blog post about "{topic}". Include {headings} main sections with detailed information.',
                        'max_tokens' => 800
                    ],
                    'conclusion' => [
                        'prompt' => 'Write a compelling conclusion for a blog post about "{topic}". Summarize key points and include a call to action.',
                        'max_tokens' => 150
                    ]
                ],
                'settings' => [
                    'temperature' => 0.7,
                    'headings' => 3,
                    'include_keywords' => true,
                    'seo_optimized' => true
                ]
            ],
            'how_to_guide' => [
                'name' => __('How-To Guide', Plugin::TEXT_DOMAIN),
                'description' => __('Step-by-step tutorial template with clear instructions', Plugin::TEXT_DOMAIN),
                'category' => 'tutorial',
                'structure' => [
                    'introduction' => [
                        'prompt' => 'Write an introduction for a how-to guide about "{topic}". Explain what readers will learn and why it\'s useful.',
                        'max_tokens' => 150
                    ],
                    'prerequisites' => [
                        'prompt' => 'List the prerequisites and requirements for "{topic}". What do readers need before starting?',
                        'max_tokens' => 100
                    ],
                    'steps' => [
                        'prompt' => 'Write detailed step-by-step instructions for "{topic}". Use numbered steps and be specific.',
                        'max_tokens' => 600
                    ],
                    'troubleshooting' => [
                        'prompt' => 'Provide common troubleshooting tips and solutions for "{topic}".',
                        'max_tokens' => 200
                    ],
                    'conclusion' => [
                        'prompt' => 'Write a conclusion for the how-to guide about "{topic}". Summarize what was accomplished.',
                        'max_tokens' => 100
                    ]
                ],
                'settings' => [
                    'temperature' => 0.3,
                    'headings' => 5,
                    'include_keywords' => true,
                    'structured_format' => true
                ]
            ],
            'product_review' => [
                'name' => __('Product Review', Plugin::TEXT_DOMAIN),
                'description' => __('Comprehensive product review template with pros, cons, and rating', Plugin::TEXT_DOMAIN),
                'category' => 'review',
                'structure' => [
                    'introduction' => [
                        'prompt' => 'Write an introduction for a product review of "{topic}". Provide context and first impressions.',
                        'max_tokens' => 150
                    ],
                    'features' => [
                        'prompt' => 'Describe the key features and specifications of "{topic}". Be detailed and informative.',
                        'max_tokens' => 300
                    ],
                    'pros_cons' => [
                        'prompt' => 'List the pros and cons of "{topic}". Be balanced and honest in your assessment.',
                        'max_tokens' => 250
                    ],
                    'comparison' => [
                        'prompt' => 'Compare "{topic}" with similar products in the market. Highlight unique selling points.',
                        'max_tokens' => 200
                    ],
                    'verdict' => [
                        'prompt' => 'Provide a final verdict and rating for "{topic}". Include who should buy it and why.',
                        'max_tokens' => 150
                    ]
                ],
                'settings' => [
                    'temperature' => 0.5,
                    'headings' => 5,
                    'include_keywords' => true,
                    'rating_system' => true
                ]
            ],
            'news_article' => [
                'name' => __('News Article', Plugin::TEXT_DOMAIN),
                'description' => __('Professional news article template with inverted pyramid structure', Plugin::TEXT_DOMAIN),
                'category' => 'news',
                'structure' => [
                    'headline' => [
                        'prompt' => 'Write a compelling headline for a news article about "{topic}". Make it attention-grabbing and informative.',
                        'max_tokens' => 50
                    ],
                    'lead' => [
                        'prompt' => 'Write the lead paragraph for a news article about "{topic}". Include who, what, when, where, why.',
                        'max_tokens' => 100
                    ],
                    'body' => [
                        'prompt' => 'Write the body of a news article about "{topic}". Provide details, quotes, and context.',
                        'max_tokens' => 500
                    ],
                    'background' => [
                        'prompt' => 'Provide background information and context for the news story about "{topic}".',
                        'max_tokens' => 200
                    ]
                ],
                'settings' => [
                    'temperature' => 0.2,
                    'headings' => 2,
                    'factual_tone' => true,
                    'objective_style' => true
                ]
            ],
            'listicle' => [
                'name' => __('Listicle', Plugin::TEXT_DOMAIN),
                'description' => __('Engaging list-based article template with numbered points', Plugin::TEXT_DOMAIN),
                'category' => 'general',
                'structure' => [
                    'introduction' => [
                        'prompt' => 'Write an introduction for a listicle about "{topic}". Explain what the list covers and why it\'s valuable.',
                        'max_tokens' => 150
                    ],
                    'list_items' => [
                        'prompt' => 'Create a numbered list of {headings} items about "{topic}". Each item should be detailed and valuable.',
                        'max_tokens' => 600
                    ],
                    'conclusion' => [
                        'prompt' => 'Write a conclusion for the listicle about "{topic}". Summarize the key takeaways.',
                        'max_tokens' => 100
                    ]
                ],
                'settings' => [
                    'temperature' => 0.6,
                    'headings' => 10,
                    'include_keywords' => true,
                    'engaging_tone' => true
                ]
            ]
        ];
    }

    /**
     * Create templates table
     *
     * @return bool True on success, false on failure
     */
    public function create_templates_table(): bool
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->templates_table} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `description` text NOT NULL,
            `category` varchar(100) NOT NULL DEFAULT 'general',
            `structure` longtext NOT NULL,
            `settings` text NOT NULL,
            `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `usage_count` int(11) NOT NULL DEFAULT 0,
            `created_by` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active),
            KEY created_by (created_by)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if (!empty($result)) {
            $this->install_builtin_templates();
            return true;
        }

        return false;
    }

    /**
     * Install built-in templates
     *
     * @return void
     */
    private function install_builtin_templates(): void
    {
        global $wpdb;

        foreach ($this->builtin_templates as $key => $template) {
            // Check if template already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->templates_table} WHERE name = %s AND is_builtin = 1",
                $template['name']
            ));

            if (!$exists) {
                $wpdb->insert(
                    $this->templates_table,
                    [
                        'name' => $template['name'],
                        'description' => $template['description'],
                        'category' => $template['category'],
                        'structure' => wp_json_encode($template['structure']),
                        'settings' => wp_json_encode($template['settings']),
                        'is_builtin' => 1,
                        'is_active' => 1
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%d']
                );
            }
        }
    }

    /**
     * Get all templates
     *
     * @param string $category Filter by category
     * @param bool $active_only Only active templates
     * @return array Templates array
     */
    public function get_templates(string $category = '', bool $active_only = true): array
    {
        global $wpdb;

        $cache_key = 'templates_' . md5($category . ($active_only ? '1' : '0'));
        $cached_templates = Cache::get($cache_key);

        if ($cached_templates !== null) {
            return $cached_templates;
        }

        $where_clauses = [];
        $params = [];

        if ($active_only) {
            $where_clauses[] = 'is_active = %d';
            $params[] = 1;
        }

        if (!empty($category)) {
            $where_clauses[] = 'category = %s';
            $params[] = $category;
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $sql = "SELECT * FROM {$this->templates_table} {$where_sql} ORDER BY is_builtin DESC, usage_count DESC, name ASC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $templates = $wpdb->get_results($sql);

        // Decode JSON fields
        foreach ($templates as &$template) {
            $template->structure = json_decode($template->structure, true);
            $template->settings = json_decode($template->settings, true);
        }

        Cache::set($cache_key, $templates, 1800); // Cache for 30 minutes

        return $templates;
    }

    /**
     * Get template by ID
     *
     * @param int $template_id Template ID
     * @return object|null Template object or null if not found
     */
    public function get_template(int $template_id): ?object
    {
        global $wpdb;

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->templates_table} WHERE id = %d AND is_active = 1",
            $template_id
        ));

        if ($template) {
            $template->structure = json_decode($template->structure, true);
            $template->settings = json_decode($template->settings, true);
        }

        return $template;
    }

    /**
     * Create custom template
     *
     * @param array $template_data Template data
     * @return int|false Template ID on success, false on failure
     */
    public function create_template(array $template_data): int|false
    {
        global $wpdb;

        $defaults = [
            'name' => '',
            'description' => '',
            'category' => 'general',
            'structure' => [],
            'settings' => [],
            'is_active' => 1,
            'created_by' => get_current_user_id()
        ];

        $template_data = wp_parse_args($template_data, $defaults);

        // Validate required fields
        if (empty($template_data['name']) || empty($template_data['structure'])) {
            return false;
        }

        $result = $wpdb->insert(
            $this->templates_table,
            [
                'name' => sanitize_text_field($template_data['name']),
                'description' => sanitize_textarea_field($template_data['description']),
                'category' => sanitize_text_field($template_data['category']),
                'structure' => wp_json_encode($template_data['structure']),
                'settings' => wp_json_encode($template_data['settings']),
                'is_builtin' => 0,
                'is_active' => (int) $template_data['is_active'],
                'created_by' => (int) $template_data['created_by']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
        );

        if ($result) {
            Cache::delete('templates_*'); // Clear template cache
            Logger::info('Custom template created', ['template_id' => $wpdb->insert_id]);
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update template
     *
     * @param int $template_id Template ID
     * @param array $template_data Template data
     * @return bool True on success, false on failure
     */
    public function update_template(int $template_id, array $template_data): bool
    {
        global $wpdb;

        // Don't allow updating built-in templates
        $is_builtin = $wpdb->get_var($wpdb->prepare(
            "SELECT is_builtin FROM {$this->templates_table} WHERE id = %d",
            $template_id
        ));

        if ($is_builtin) {
            return false;
        }

        $update_data = [];
        $format = [];

        if (isset($template_data['name'])) {
            $update_data['name'] = sanitize_text_field($template_data['name']);
            $format[] = '%s';
        }

        if (isset($template_data['description'])) {
            $update_data['description'] = sanitize_textarea_field($template_data['description']);
            $format[] = '%s';
        }

        if (isset($template_data['category'])) {
            $update_data['category'] = sanitize_text_field($template_data['category']);
            $format[] = '%s';
        }

        if (isset($template_data['structure'])) {
            $update_data['structure'] = wp_json_encode($template_data['structure']);
            $format[] = '%s';
        }

        if (isset($template_data['settings'])) {
            $update_data['settings'] = wp_json_encode($template_data['settings']);
            $format[] = '%s';
        }

        if (isset($template_data['is_active'])) {
            $update_data['is_active'] = (int) $template_data['is_active'];
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->templates_table,
            $update_data,
            ['id' => $template_id],
            $format,
            ['%d']
        );

        if ($result !== false) {
            Cache::delete('templates_*'); // Clear template cache
            Logger::info('Template updated', ['template_id' => $template_id]);
            return true;
        }

        return false;
    }

    /**
     * Delete template
     *
     * @param int $template_id Template ID
     * @return bool True on success, false on failure
     */
    public function delete_template(int $template_id): bool
    {
        global $wpdb;

        // Don't allow deleting built-in templates
        $is_builtin = $wpdb->get_var($wpdb->prepare(
            "SELECT is_builtin FROM {$this->templates_table} WHERE id = %d",
            $template_id
        ));

        if ($is_builtin) {
            return false;
        }

        $result = $wpdb->delete(
            $this->templates_table,
            ['id' => $template_id],
            ['%d']
        );

        if ($result) {
            Cache::delete('templates_*'); // Clear template cache
            Logger::info('Template deleted', ['template_id' => $template_id]);
            return true;
        }

        return false;
    }

    /**
     * Increment template usage count
     *
     * @param int $template_id Template ID
     * @return void
     */
    public function increment_usage(int $template_id): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->templates_table} SET usage_count = usage_count + 1 WHERE id = %d",
            $template_id
        ));
    }

    /**
     * Get template categories
     *
     * @return array Categories with counts
     */
    public function get_categories(): array
    {
        global $wpdb;

        $cache_key = 'template_categories';
        $cached_categories = Cache::get($cache_key);

        if ($cached_categories !== null) {
            return $cached_categories;
        }

        $categories = $wpdb->get_results(
            "SELECT category, COUNT(*) as count 
             FROM {$this->templates_table} 
             WHERE is_active = 1 
             GROUP BY category 
             ORDER BY count DESC, category ASC"
        );

        $result = [];
        foreach ($categories as $category) {
            $result[$category->category] = [
                'name' => ucfirst($category->category),
                'count' => (int) $category->count
            ];
        }

        Cache::set($cache_key, $result, 3600); // Cache for 1 hour

        return $result;
    }

    /**
     * Generate content using template
     *
     * @param int $template_id Template ID
     * @param string $topic Content topic
     * @param array $variables Template variables
     * @return array Generation result
     */
    public function generate_from_template(int $template_id, string $topic, array $variables = []): array
    {
        $template = $this->get_template($template_id);

        if (!$template) {
            return [
                'status' => 'error',
                'message' => __('Template not found', Plugin::TEXT_DOMAIN)
            ];
        }

        try {
            $ai_provider = Plugin::get_instance()->get_openai_service();
            $generated_content = [];
            $total_tokens = 0;

            // Replace variables in topic and prompts
            $variables['topic'] = $topic;
            $variables['headings'] = $template->settings['headings'] ?? 3;

            foreach ($template->structure as $section_key => $section) {
                $prompt = $this->replace_variables($section['prompt'], $variables);
                $max_tokens = $section['max_tokens'] ?? 200;

                $result = $ai_provider->generate_content($prompt, [
                    'max_tokens' => $max_tokens,
                    'temperature' => $template->settings['temperature'] ?? 0.7
                ]);

                if ($result['status'] === 'success') {
                    $generated_content[$section_key] = [
                        'title' => ucfirst(str_replace('_', ' ', $section_key)),
                        'content' => $result['data'],
                        'tokens' => $result['tokens_used'] ?? 0
                    ];
                    $total_tokens += $result['tokens_used'] ?? 0;
                } else {
                    return [
                        'status' => 'error',
                        'message' => sprintf(
                            __('Failed to generate %s section: %s', Plugin::TEXT_DOMAIN),
                            $section_key,
                            $result['message']
                        )
                    ];
                }

                // Small delay between sections to avoid rate limiting
                usleep(100000); // 100ms
            }

            // Increment usage count
            $this->increment_usage($template_id);

            // Log template usage
            Logger::info('Template used for content generation', [
                'template_id' => $template_id,
                'template_name' => $template->name,
                'topic' => $topic,
                'sections_generated' => count($generated_content),
                'total_tokens' => $total_tokens
            ]);

            return [
                'status' => 'success',
                'data' => [
                    'template' => $template,
                    'content' => $generated_content,
                    'total_tokens' => $total_tokens,
                    'topic' => $topic
                ]
            ];

        } catch (\Exception $e) {
            Logger::error('Template generation failed', [
                'template_id' => $template_id,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Replace variables in text
     *
     * @param string $text Text with variables
     * @param array $variables Variables to replace
     * @return string Text with replaced variables
     */
    private function replace_variables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }

        return $text;
    }

    /**
     * Export template
     *
     * @param int $template_id Template ID
     * @return array|false Template data or false on failure
     */
    public function export_template(int $template_id): array|false
    {
        $template = $this->get_template($template_id);

        if (!$template) {
            return false;
        }

        return [
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category,
            'structure' => $template->structure,
            'settings' => $template->settings,
            'version' => Plugin::VERSION,
            'exported_at' => current_time('mysql')
        ];
    }

    /**
     * Import template
     *
     * @param array $template_data Template data
     * @return int|false Template ID on success, false on failure
     */
    public function import_template(array $template_data): int|false
    {
        // Validate template data
        $required_fields = ['name', 'structure'];
        foreach ($required_fields as $field) {
            if (!isset($template_data[$field])) {
                return false;
            }
        }

        // Add import suffix to avoid conflicts
        $template_data['name'] .= ' (Imported)';

        return $this->create_template($template_data);
    }
}
