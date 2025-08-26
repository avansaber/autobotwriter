<?php
/**
 * Content Processor
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Admin;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Database\DatabaseManager;
use AutoBotWriter\API\OpenAIService;

/**
 * Content Processor Class
 */
class ContentProcessor
{
    /**
     * Plugin instance
     */
    private Plugin $plugin;

    /**
     * Database manager instance
     */
    private DatabaseManager $db_manager;

    /**
     * OpenAI service instance
     */
    private OpenAIService $openai_service;

    /**
     * Lock key for preventing concurrent processing
     */
    private const LOCK_KEY = 'aibot_generate_section_lock';

    /**
     * Lock duration in seconds
     */
    private const LOCK_DURATION = 30;

    /**
     * Constructor
     *
     * @param Plugin $plugin Plugin instance
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->db_manager = new DatabaseManager();
        $this->openai_service = new OpenAIService();
    }

    /**
     * Process next content section
     *
     * @return array Processing result
     */
    public function process_next_section(): array
    {
        // Check if already processing
        if (get_transient(self::LOCK_KEY)) {
            return [
                'status' => 'processing',
                'message' => __('Content generation is already in progress.', Plugin::TEXT_DOMAIN)
            ];
        }

        // Set processing lock
        set_transient(self::LOCK_KEY, true, self::LOCK_DURATION);

        try {
            $result = $this->process_content_generation();
            delete_transient(self::LOCK_KEY);
            return $result;
        } catch (\Exception $e) {
            delete_transient(self::LOCK_KEY);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Process content generation workflow
     *
     * @return array Processing result
     * @throws \Exception If processing fails
     */
    private function process_content_generation(): array
    {
        $current_article = get_option('aibot_current_article');
        
        if (!$current_article) {
            $current_article = $this->get_next_pending_article();
            if (!$current_article) {
                return [
                    'status' => 'idle',
                    'message' => __('No pending articles to process.', Plugin::TEXT_DOMAIN)
                ];
            }
        }

        $current_step = get_option('aibot_next_step', 'intro');

        switch ($current_step) {
            case 'intro':
                return $this->process_introduction($current_article);
            
            case 'headings':
                return $this->process_headings($current_article);
            
            case 'content':
                return $this->process_content_sections($current_article);
            
            case 'conclusion':
                return $this->process_conclusion($current_article);
            
            case 'finalize':
                return $this->finalize_article($current_article);
            
            default:
                throw new \Exception('Unknown processing step: ' . $current_step);
        }
    }

    /**
     * Process introduction generation
     *
     * @param int $article_id Article ID
     * @return array Processing result
     */
    private function process_introduction(int $article_id): array
    {
        $article = $this->db_manager->get_post_schedule($article_id);
        if (!$article) {
            throw new \Exception('Article not found');
        }

        // Mark as processing
        $this->db_manager->update_post_schedule($article_id, ['status' => 'processing']);
        update_option('aibot_current_article', $article_id);

        // Generate introduction
        $result = $this->openai_service->generate_introduction(
            $article->post_title,
            $article->include_keywords,
            $article->exclude_keywords
        );

        if ($result['status'] === 'success') {
            update_option('aibot_current_intro', $result['data']);
            update_option('aibot_next_step', 'headings');
            
            return [
                'status' => 'success',
                'message' => __('Introduction generated successfully.', Plugin::TEXT_DOMAIN),
                'step' => 'headings'
            ];
        }

        throw new \Exception($result['message'] ?? 'Failed to generate introduction');
    }

    /**
     * Process headings generation
     *
     * @param int $article_id Article ID
     * @return array Processing result
     */
    private function process_headings(int $article_id): array
    {
        $article = $this->db_manager->get_post_schedule($article_id);
        $settings = $this->db_manager->get_settings();
        
        $num_headings = (int) ($settings['headings'] ?? 3);

        // Generate headings
        $headings = $this->openai_service->generate_headings(
            $num_headings,
            $article->post_title,
            $article->include_keywords,
            $article->exclude_keywords
        );

        if (!empty($headings)) {
            update_option('aibot_current_headings', $headings);
            update_option('aibot_generated_sections', 0);
            update_option('aibot_next_step', 'content');
            
            return [
                'status' => 'success',
                'message' => sprintf(
                    /* translators: %d: number of headings */
                    __('%d headings generated successfully.', Plugin::TEXT_DOMAIN),
                    count($headings)
                ),
                'step' => 'content'
            ];
        }

        throw new \Exception('Failed to generate headings');
    }

    /**
     * Process content sections generation
     *
     * @param int $article_id Article ID
     * @return array Processing result
     */
    private function process_content_sections(int $article_id): array
    {
        $article = $this->db_manager->get_post_schedule($article_id);
        $headings = get_option('aibot_current_headings', []);
        $generated_sections = (int) get_option('aibot_generated_sections', 0);

        if ($generated_sections >= count($headings)) {
            update_option('aibot_next_step', 'conclusion');
            return [
                'status' => 'success',
                'message' => __('All content sections completed.', Plugin::TEXT_DOMAIN),
                'step' => 'conclusion'
            ];
        }

        $current_heading = $headings[$generated_sections];

        // Generate content section
        $result = $this->openai_service->generate_content_section(
            $current_heading,
            $article->include_keywords,
            $article->exclude_keywords
        );

        if (!empty($result['content'])) {
            // Store content and conclusion
            $contents = get_option('aibot_current_contents', []);
            $conclusions = get_option('aibot_current_conclusions', []);
            
            $contents[$current_heading] = $result['content'];
            $conclusions[$current_heading] = $result['conclusion'];
            
            update_option('aibot_current_contents', $contents);
            update_option('aibot_current_conclusions', $conclusions);
            update_option('aibot_generated_sections', $generated_sections + 1);

            return [
                'status' => 'success',
                'message' => sprintf(
                    /* translators: %s: heading name */
                    __('Section "%s" generated successfully.', Plugin::TEXT_DOMAIN),
                    $current_heading
                ),
                'step' => 'content',
                'progress' => ($generated_sections + 1) / count($headings) * 100
            ];
        }

        throw new \Exception('Failed to generate content section');
    }

    /**
     * Process final conclusion generation
     *
     * @param int $article_id Article ID
     * @return array Processing result
     */
    private function process_conclusion(int $article_id): array
    {
        $article = $this->db_manager->get_post_schedule($article_id);
        $conclusions = get_option('aibot_current_conclusions', []);
        
        $conclusion_summary = implode("\n\n", $conclusions);

        // Generate final conclusion
        $result = $this->openai_service->generate_final_conclusion(
            $conclusion_summary,
            $article->include_keywords,
            $article->exclude_keywords
        );

        if ($result['status'] === 'success') {
            update_option('aibot_final_conclusion', $result['data']);
            update_option('aibot_next_step', 'finalize');
            
            return [
                'status' => 'success',
                'message' => __('Final conclusion generated successfully.', Plugin::TEXT_DOMAIN),
                'step' => 'finalize'
            ];
        }

        throw new \Exception($result['message'] ?? 'Failed to generate conclusion');
    }

    /**
     * Finalize article and create WordPress post
     *
     * @param int $article_id Article ID
     * @return array Processing result
     */
    private function finalize_article(int $article_id): array
    {
        $article = $this->db_manager->get_post_schedule($article_id);
        
        // Compile final content
        $final_content = $this->compile_final_content();
        
        // Create WordPress post
        $post_id = $this->create_wordpress_post($article, $final_content);
        
        if ($post_id) {
            // Update schedule record
            $this->db_manager->update_post_schedule($article_id, [
                'status' => 'completed',
                'post_id' => $post_id
            ]);

            // Clean up temporary options
            $this->cleanup_generation_options();

            // Update generation counter
            $this->increment_generation_counter();

            return [
                'status' => 'completed',
                'message' => __('Article created successfully.', Plugin::TEXT_DOMAIN),
                'post_id' => $post_id
            ];
        }

        throw new \Exception('Failed to create WordPress post');
    }

    /**
     * Get next pending article
     *
     * @return int|null Article ID or null if none found
     */
    private function get_next_pending_article(): ?int
    {
        $pending_posts = $this->db_manager->get_pending_posts(1);
        return !empty($pending_posts) ? (int) $pending_posts[0]->id : null;
    }

    /**
     * Compile final content from all sections
     *
     * @return string Final compiled content
     */
    private function compile_final_content(): string
    {
        $intro = get_option('aibot_current_intro', '');
        $contents = get_option('aibot_current_contents', []);
        $final_conclusion = get_option('aibot_final_conclusion', '');

        $final_content = $intro;

        foreach ($contents as $heading => $content) {
            $final_content .= "\n\n<h2>" . esc_html($heading) . "</h2>\n\n" . $content;
        }

        if (!empty($final_conclusion)) {
            $final_content .= "\n\n<h2>" . __('Conclusion', Plugin::TEXT_DOMAIN) . "</h2>\n\n" . $final_conclusion;
        }

        return $final_content;
    }

    /**
     * Create WordPress post
     *
     * @param object $article Article data
     * @param string $content Post content
     * @return int|false Post ID on success, false on failure
     */
    private function create_wordpress_post(object $article, string $content): int|false
    {
        $post_status = ($article->publish_date === '0000-00-00 00:00:00') ? 'publish' : 'draft';

        $post_data = [
            'post_type' => 'post',
            'post_author' => (int) $article->author_id,
            'post_title' => sanitize_text_field($article->post_title),
            'post_content' => wp_kses_post($content),
            'post_status' => $post_status,
            'post_date' => $article->publish_date !== '0000-00-00 00:00:00' ? $article->publish_date : '',
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            error_log('AutoBotWriter: Failed to create post - ' . $post_id->get_error_message());
            return false;
        }

        // Set categories and tags
        if ($article->category > 0) {
            wp_set_post_categories($post_id, [(int) $article->category]);
        }

        if (!empty($article->tags)) {
            $tags = array_map('trim', explode(',', $article->tags));
            wp_set_post_tags($post_id, $tags);
        }

        return $post_id;
    }

    /**
     * Clean up generation options
     *
     * @return void
     */
    private function cleanup_generation_options(): void
    {
        $options_to_delete = [
            'aibot_current_article',
            'aibot_next_step',
            'aibot_current_intro',
            'aibot_current_headings',
            'aibot_current_contents',
            'aibot_current_conclusions',
            'aibot_final_conclusion',
            'aibot_generated_sections'
        ];

        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
    }

    /**
     * Increment generation counter
     *
     * @return void
     */
    private function increment_generation_counter(): void
    {
        $current_month = date('Y-m');
        $option_key = 'autobotwriter_gen_' . $current_month;
        $current_count = (int) get_option($option_key, 0);
        update_option($option_key, $current_count + 1);
    }
}
