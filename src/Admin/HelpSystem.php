<?php
/**
 * Help and Documentation System
 *
 * @package AutoBotWriter
 * @since 1.7.0
 */

namespace AutoBotWriter\Admin;

use AutoBotWriter\Core\Plugin;
use AutoBotWriter\Utils\Cache;

/**
 * Help System Class
 */
class HelpSystem
{
    /**
     * Help content cache duration
     */
    private const CACHE_DURATION = 3600; // 1 hour

    /**
     * Help topics
     */
    private array $help_topics = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_help_topics();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        add_action('wp_ajax_autobotwriter_get_help', [$this, 'ajax_get_help']);
        add_action('wp_ajax_autobotwriter_search_help', [$this, 'ajax_search_help']);
        add_action('current_screen', [$this, 'add_contextual_help']);
    }

    /**
     * Initialize help topics
     *
     * @return void
     */
    private function init_help_topics(): void
    {
        $this->help_topics = [
            'getting_started' => [
                'title' => __('Getting Started', Plugin::TEXT_DOMAIN),
                'category' => 'basics',
                'content' => $this->get_getting_started_content(),
                'tags' => ['setup', 'configuration', 'first-time', 'api-key']
            ],
            'api_configuration' => [
                'title' => __('API Configuration', Plugin::TEXT_DOMAIN),
                'category' => 'configuration',
                'content' => $this->get_api_configuration_content(),
                'tags' => ['api', 'openai', 'configuration', 'settings']
            ],
            'content_generation' => [
                'title' => __('Content Generation', Plugin::TEXT_DOMAIN),
                'category' => 'usage',
                'content' => $this->get_content_generation_content(),
                'tags' => ['generate', 'content', 'blog', 'posts']
            ],
            'templates' => [
                'title' => __('Using Templates', Plugin::TEXT_DOMAIN),
                'category' => 'advanced',
                'content' => $this->get_templates_content(),
                'tags' => ['templates', 'custom', 'structure', 'reusable']
            ],
            'bulk_operations' => [
                'title' => __('Bulk Operations', Plugin::TEXT_DOMAIN),
                'category' => 'advanced',
                'content' => $this->get_bulk_operations_content(),
                'tags' => ['bulk', 'batch', 'multiple', 'automation']
            ],
            'scheduling' => [
                'title' => __('Scheduling Content', Plugin::TEXT_DOMAIN),
                'category' => 'advanced',
                'content' => $this->get_scheduling_content(),
                'tags' => ['schedule', 'automation', 'recurring', 'cron']
            ],
            'ai_providers' => [
                'title' => __('AI Providers', Plugin::TEXT_DOMAIN),
                'category' => 'configuration',
                'content' => $this->get_ai_providers_content(),
                'tags' => ['providers', 'openai', 'claude', 'gemini', 'local']
            ],
            'troubleshooting' => [
                'title' => __('Troubleshooting', Plugin::TEXT_DOMAIN),
                'category' => 'support',
                'content' => $this->get_troubleshooting_content(),
                'tags' => ['problems', 'errors', 'issues', 'debug']
            ],
            'faq' => [
                'title' => __('Frequently Asked Questions', Plugin::TEXT_DOMAIN),
                'category' => 'support',
                'content' => $this->get_faq_content(),
                'tags' => ['faq', 'questions', 'common', 'answers']
            ],
            'security' => [
                'title' => __('Security Best Practices', Plugin::TEXT_DOMAIN),
                'category' => 'security',
                'content' => $this->get_security_content(),
                'tags' => ['security', 'privacy', 'api-key', 'safety']
            ]
        ];
    }

    /**
     * Get help topic by ID
     *
     * @param string $topic_id Topic ID
     * @return array|null Help topic or null if not found
     */
    public function get_help_topic(string $topic_id): ?array
    {
        return $this->help_topics[$topic_id] ?? null;
    }

    /**
     * Get all help topics
     *
     * @param string $category Filter by category
     * @return array Help topics
     */
    public function get_help_topics(string $category = ''): array
    {
        if (empty($category)) {
            return $this->help_topics;
        }

        return array_filter($this->help_topics, function($topic) use ($category) {
            return $topic['category'] === $category;
        });
    }

    /**
     * Search help topics
     *
     * @param string $query Search query
     * @return array Matching help topics
     */
    public function search_help_topics(string $query): array
    {
        $query = strtolower(trim($query));
        $results = [];

        foreach ($this->help_topics as $id => $topic) {
            $score = 0;

            // Check title
            if (strpos(strtolower($topic['title']), $query) !== false) {
                $score += 10;
            }

            // Check tags
            foreach ($topic['tags'] as $tag) {
                if (strpos(strtolower($tag), $query) !== false) {
                    $score += 5;
                }
            }

            // Check content
            if (strpos(strtolower($topic['content']), $query) !== false) {
                $score += 3;
            }

            if ($score > 0) {
                $results[$id] = $topic;
                $results[$id]['relevance_score'] = $score;
            }
        }

        // Sort by relevance score
        uasort($results, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return $results;
    }

    /**
     * Get help categories
     *
     * @return array Categories with counts
     */
    public function get_help_categories(): array
    {
        $categories = [];

        foreach ($this->help_topics as $topic) {
            $category = $topic['category'];
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'name' => ucfirst($category),
                    'count' => 0
                ];
            }
            $categories[$category]['count']++;
        }

        return $categories;
    }

    /**
     * AJAX: Get help topic
     *
     * @return void
     */
    public function ajax_get_help(): void
    {
        check_ajax_referer('autobotwriter_help', 'nonce');

        $topic_id = sanitize_text_field($_POST['topic_id'] ?? '');
        $topic = $this->get_help_topic($topic_id);

        if ($topic) {
            wp_send_json_success($topic);
        } else {
            wp_send_json_error(__('Help topic not found', Plugin::TEXT_DOMAIN));
        }
    }

    /**
     * AJAX: Search help topics
     *
     * @return void
     */
    public function ajax_search_help(): void
    {
        check_ajax_referer('autobotwriter_help', 'nonce');

        $query = sanitize_text_field($_POST['query'] ?? '');
        $results = $this->search_help_topics($query);

        wp_send_json_success($results);
    }

    /**
     * Add contextual help to admin screens
     *
     * @return void
     */
    public function add_contextual_help(): void
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'autobotwriter') === false) {
            return;
        }

        // Add help tabs based on current screen
        $this->add_help_tabs($screen);
    }

    /**
     * Add help tabs to screen
     *
     * @param \WP_Screen $screen Current screen
     * @return void
     */
    private function add_help_tabs(\WP_Screen $screen): void
    {
        // Overview tab
        $screen->add_help_tab([
            'id' => 'autobotwriter_overview',
            'title' => __('Overview', Plugin::TEXT_DOMAIN),
            'content' => $this->get_overview_help()
        ]);

        // Quick Start tab
        $screen->add_help_tab([
            'id' => 'autobotwriter_quickstart',
            'title' => __('Quick Start', Plugin::TEXT_DOMAIN),
            'content' => $this->get_quickstart_help()
        ]);

        // Troubleshooting tab
        $screen->add_help_tab([
            'id' => 'autobotwriter_troubleshooting',
            'title' => __('Troubleshooting', Plugin::TEXT_DOMAIN),
            'content' => $this->get_contextual_troubleshooting_help()
        ]);

        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar());
    }

    /**
     * Get overview help content
     *
     * @return string Help content
     */
    private function get_overview_help(): string
    {
        return '<p>' . __('AutoBotWriter is an AI-powered content generation plugin that helps you create high-quality blog posts automatically.', Plugin::TEXT_DOMAIN) . '</p>' .
               '<p>' . __('Key features:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('AI-powered content generation', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Multiple AI provider support', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Content templates', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Bulk operations', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Scheduled content generation', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    /**
     * Get quick start help content
     *
     * @return string Help content
     */
    private function get_quickstart_help(): string
    {
        return '<p>' . __('To get started with AutoBotWriter:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ol>' .
               '<li>' . __('Configure your AI provider API key in Settings', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Go to Content Writer to generate your first post', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Enter a topic and click Generate', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Review and publish your generated content', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<p>' . __('For more detailed instructions, visit the full documentation.', Plugin::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Get contextual troubleshooting help
     *
     * @return string Help content
     */
    private function get_contextual_troubleshooting_help(): string
    {
        return '<p>' . __('Common issues and solutions:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li><strong>' . __('API key not working:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Check that your API key is valid and has sufficient credits.', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Content not generating:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Verify your internet connection and API provider status.', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Plugin not loading:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Check for plugin conflicts and ensure WordPress requirements are met.', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    /**
     * Get help sidebar content
     *
     * @return string Sidebar content
     */
    private function get_help_sidebar(): string
    {
        return '<p><strong>' . __('For more help:', Plugin::TEXT_DOMAIN) . '</strong></p>' .
               '<p><a href="https://autobotwriter.com/docs" target="_blank">' . __('Documentation', Plugin::TEXT_DOMAIN) . '</a></p>' .
               '<p><a href="https://autobotwriter.com/support" target="_blank">' . __('Support Forum', Plugin::TEXT_DOMAIN) . '</a></p>' .
               '<p><a href="mailto:support@autobotwriter.com">' . __('Email Support', Plugin::TEXT_DOMAIN) . '</a></p>';
    }

    // Help content methods
    private function get_getting_started_content(): string
    {
        return '<h3>' . __('Welcome to AutoBotWriter!', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('AutoBotWriter is a powerful AI content generation plugin that helps you create high-quality blog posts automatically. Follow these steps to get started:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('Step 1: Configure Your API Key', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Before you can generate content, you need to configure an AI provider API key:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ol>' .
               '<li>' . __('Go to Settings → AI Providers', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Choose your preferred AI provider (OpenAI recommended)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Enter your API key and test the connection', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Save your settings', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Step 2: Generate Your First Post', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Once your API key is configured:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ol>' .
               '<li>' . __('Navigate to Content Writer', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Enter a topic or subject for your blog post', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Choose your content settings (length, style, etc.)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Click "Generate Content"', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Review the generated content and make any edits', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Publish or save as draft', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Step 3: Explore Advanced Features', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Once you\'re comfortable with basic content generation, explore these advanced features:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li><strong>' . __('Templates:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Use pre-built content structures for consistent results', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Bulk Operations:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Generate multiple posts at once', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Scheduling:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Automate content generation on a schedule', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    private function get_api_configuration_content(): string
    {
        return '<h3>' . __('AI Provider Configuration', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('AutoBotWriter supports multiple AI providers. Here\'s how to configure each one:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('OpenAI (Recommended)', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('OpenAI provides the most reliable and high-quality content generation:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ol>' .
               '<li>' . __('Sign up at openai.com and create an API key', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Copy your API key (starts with "sk-")', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Paste it in the OpenAI API Key field', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Choose your preferred model (GPT-4 for best quality, GPT-3.5 for speed)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Test the connection to verify it works', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Other Providers', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('AutoBotWriter also supports:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li><strong>' . __('Anthropic Claude:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Great for safety-focused content generation', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Google Gemini:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Multimodal capabilities and long context', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Local AI:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Self-hosted models for privacy', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('API Key Security', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Your API keys are encrypted and stored securely. AutoBotWriter uses WordPress security keys for encryption, ensuring your credentials are protected.', Plugin::TEXT_DOMAIN) . '</p>';
    }

    private function get_content_generation_content(): string
    {
        return '<h3>' . __('Content Generation Guide', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('Learn how to create high-quality content with AutoBotWriter:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('Basic Content Generation', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ol>' .
               '<li><strong>' . __('Choose a Topic:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Be specific and descriptive. Instead of "cars", try "electric vehicle maintenance tips"', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Set Parameters:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Adjust content length, creativity level, and number of headings', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Add Keywords:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Include keywords you want to focus on or exclude', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Generate:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Click generate and wait for the AI to create your content', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Review & Edit:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Always review generated content for accuracy and brand voice', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Content Quality Tips', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li>' . __('Use specific, detailed prompts for better results', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Lower temperature (0.3-0.5) for factual content, higher (0.7-0.9) for creative content', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Include target audience information in your prompts', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Specify the desired tone and style', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Always fact-check generated content before publishing', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Content Structure', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('AutoBotWriter generates well-structured content with:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('Engaging introductions that hook readers', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Clear headings and subheadings for easy scanning', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Detailed body content with examples and explanations', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Compelling conclusions with calls to action', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    private function get_templates_content(): string
    {
        return '<h3>' . __('Using Content Templates', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('Templates provide consistent structure and formatting for your content. AutoBotWriter includes several built-in templates and allows you to create custom ones.', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('Built-in Templates', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li><strong>' . __('Blog Post:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Standard blog post with intro, main content, and conclusion', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('How-To Guide:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Step-by-step tutorial format', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Product Review:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Comprehensive review with pros, cons, and rating', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('News Article:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Professional news format with inverted pyramid structure', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Listicle:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Engaging list-based content', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Using Templates', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ol>' .
               '<li>' . __('Go to Content Writer', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Select "Use Template" option', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Choose your desired template', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Enter your topic and any custom variables', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Generate content using the template structure', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Creating Custom Templates', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('You can create your own templates for specific content types:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ol>' .
               '<li>' . __('Go to Templates section', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Click "Create New Template"', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Define sections and prompts for each part', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Set default parameters and settings', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Save and test your template', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>';
    }

    private function get_bulk_operations_content(): string
    {
        return '<h3>' . __('Bulk Content Operations', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('Generate multiple pieces of content efficiently with bulk operations.', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('Creating Bulk Jobs', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ol>' .
               '<li>' . __('Navigate to Bulk Operations', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Create a new bulk job', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Add your list of topics (up to 10 at once)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Choose template and settings', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Review estimated cost and tokens', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Start the bulk generation process', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Monitoring Progress', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Track your bulk jobs with real-time progress updates:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('Progress percentage and completion status', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Success and failure counts', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Token usage and actual costs', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Error logs for failed generations', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Best Practices', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li>' . __('Start with smaller batches to test settings', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Use templates for consistent formatting', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Monitor API usage to avoid rate limits', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Review generated content before bulk publishing', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    private function get_scheduling_content(): string
    {
        return '<h3>' . __('Content Scheduling', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('Automate your content creation with intelligent scheduling.', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('Creating Schedules', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ol>' .
               '<li>' . __('Go to Scheduling section', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Create a new schedule', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Set frequency (daily, weekly, etc.)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Choose content source (manual topics, RSS, trending)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Configure post settings (author, category, status)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Activate the schedule', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ol>' .
               '<h4>' . __('Content Sources', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li><strong>' . __('Manual Topics:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Predefined list of topics to cycle through', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Keywords:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Generate topics based on keyword themes', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('RSS Feeds:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Pull topics from RSS feeds', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li><strong>' . __('Trending Topics:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Generate content on trending subjects', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Schedule Management', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Monitor and manage your schedules:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('View run history and success rates', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Pause or modify active schedules', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Check next run times', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Review generated content', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    private function get_ai_providers_content(): string
    {
        return '<h3>' . __('AI Provider Comparison', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<p>' . __('Choose the best AI provider for your needs:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<h4>' . __('OpenAI', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p><strong>' . __('Best for:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('General content, reliability, and quality', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('GPT-4o: Latest and most capable model', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('GPT-4 Turbo: High quality with better speed', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('GPT-3.5 Turbo: Fast and cost-effective', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Anthropic Claude', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p><strong>' . __('Best for:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Safety-focused content, long-form writing', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('Claude 3 Opus: Most powerful for complex tasks', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Claude 3 Sonnet: Balanced performance and cost', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Claude 3 Haiku: Fast and efficient', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Google Gemini', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p><strong>' . __('Best for:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Multimodal content, large context windows', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('Gemini 1.5 Pro: Advanced reasoning and long context', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Gemini 1.5 Flash: Speed-optimized version', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Local AI', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p><strong>' . __('Best for:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Privacy, cost control, offline usage', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('Llama 2: Open-source, privacy-focused', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Mistral: Efficient and capable', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Code Llama: Specialized for technical content', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    private function get_troubleshooting_content(): string
    {
        return '<h3>' . __('Troubleshooting Guide', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<h4>' . __('Common Issues', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<div class="troubleshooting-item">' .
               '<h5>' . __('API Key Not Working', Plugin::TEXT_DOMAIN) . '</h5>' .
               '<p><strong>' . __('Symptoms:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Error messages about invalid API key', Plugin::TEXT_DOMAIN) . '</p>' .
               '<p><strong>' . __('Solutions:', Plugin::TEXT_DOMAIN) . '</strong></p>' .
               '<ul>' .
               '<li>' . __('Verify the API key is correct and complete', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Check that your API account has sufficient credits', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Ensure the API key has the necessary permissions', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Try regenerating the API key from your provider', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '</div>' .
               '<div class="troubleshooting-item">' .
               '<h5>' . __('Content Not Generating', Plugin::TEXT_DOMAIN) . '</h5>' .
               '<p><strong>' . __('Symptoms:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Generation process starts but no content is produced', Plugin::TEXT_DOMAIN) . '</p>' .
               '<p><strong>' . __('Solutions:', Plugin::TEXT_DOMAIN) . '</strong></p>' .
               '<ul>' .
               '<li>' . __('Check your internet connection', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Verify the AI provider service is operational', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Try reducing the content length or complexity', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Check for rate limiting or quota exceeded errors', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '</div>' .
               '<div class="troubleshooting-item">' .
               '<h5>' . __('Slow Performance', Plugin::TEXT_DOMAIN) . '</h5>' .
               '<p><strong>' . __('Symptoms:', Plugin::TEXT_DOMAIN) . '</strong> ' . __('Content generation takes a very long time', Plugin::TEXT_DOMAIN) . '</p>' .
               '<p><strong>' . __('Solutions:', Plugin::TEXT_DOMAIN) . '</strong></p>' .
               '<ul>' .
               '<li>' . __('Switch to a faster AI model (e.g., GPT-3.5 instead of GPT-4)', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Reduce the maximum token count', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Check your server\'s internet connection speed', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Consider using a different AI provider', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '</div>' .
               '<h4>' . __('Getting Help', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('If you continue to experience issues:', Plugin::TEXT_DOMAIN) . '</p>' .
               '<ul>' .
               '<li>' . __('Check the plugin logs for detailed error messages', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Visit our support forum for community help', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Contact our support team with specific error details', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }

    private function get_faq_content(): string
    {
        return '<h3>' . __('Frequently Asked Questions', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<div class="faq-item">' .
               '<h4>' . __('Is AutoBotWriter free to use?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('AutoBotWriter is free to download and use, but you need to pay for API usage from your chosen AI provider (OpenAI, Anthropic, etc.). The plugin itself doesn\'t charge any fees.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>' .
               '<div class="faq-item">' .
               '<h4>' . __('How much does it cost to generate content?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Costs vary by AI provider and model. For example, OpenAI GPT-3.5 Turbo costs about $0.001-0.002 per 1000 tokens. A typical blog post might cost $0.01-0.05 to generate.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>' .
               '<div class="faq-item">' .
               '<h4>' . __('Can I edit the generated content?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Yes! Generated content is fully editable. We recommend reviewing and editing all AI-generated content before publishing to ensure accuracy and match your brand voice.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>' .
               '<div class="faq-item">' .
               '<h4>' . __('Is the generated content unique?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Yes, AI-generated content is unique each time. However, we recommend using plagiarism checkers and adding your own insights to ensure originality and value.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>' .
               '<div class="faq-item">' .
               '<h4>' . __('Can I use this for commercial purposes?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Yes, you can use AutoBotWriter for commercial content creation. Check your AI provider\'s terms of service for any specific restrictions on commercial use.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>' .
               '<div class="faq-item">' .
               '<h4>' . __('What languages are supported?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('AutoBotWriter can generate content in any language supported by your chosen AI provider. Most providers support dozens of languages with varying quality levels.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>' .
               '<div class="faq-item">' .
               '<h4>' . __('How do I ensure content quality?', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<p>' . __('Use specific prompts, choose appropriate AI models, review and edit generated content, fact-check information, and add your own expertise and insights.', Plugin::TEXT_DOMAIN) . '</p>' .
               '</div>';
    }

    private function get_security_content(): string
    {
        return '<h3>' . __('Security Best Practices', Plugin::TEXT_DOMAIN) . '</h3>' .
               '<h4>' . __('API Key Security', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li>' . __('Never share your API keys with others', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Use environment variables or secure storage for API keys', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Regularly rotate your API keys', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Monitor API usage for unauthorized access', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Set usage limits and alerts in your AI provider dashboard', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('Content Security', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li>' . __('Always review generated content before publishing', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Don\'t include sensitive information in prompts', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Be aware that AI providers may log API requests', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Use local AI providers for sensitive content', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('WordPress Security', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li>' . __('Keep WordPress and plugins updated', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Use strong passwords and two-factor authentication', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Limit user permissions appropriately', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Regular backups of your WordPress site', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>' .
               '<h4>' . __('AutoBotWriter Security Features', Plugin::TEXT_DOMAIN) . '</h4>' .
               '<ul>' .
               '<li>' . __('API keys are encrypted using WordPress security keys', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('All database queries use prepared statements', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('CSRF protection on all admin actions', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('Input sanitization and validation', Plugin::TEXT_DOMAIN) . '</li>' .
               '<li>' . __('User capability checks for all operations', Plugin::TEXT_DOMAIN) . '</li>' .
               '</ul>';
    }
}
