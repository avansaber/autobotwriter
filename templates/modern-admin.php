<?php
/**
 * Modern Admin Dashboard Template
 *
 * @package AutoBotWriter
 * @since 1.6.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use AutoBotWriter\Core\Plugin;

$text_domain = Plugin::TEXT_DOMAIN;
$current_tab = $_GET['tab'] ?? 'dashboard';

// Get dashboard statistics
$stats = $this->get_dashboard_stats();
?>

<div class="wrap autobotwriter-admin">
    <!-- Header -->
    <div class="abw-card abw-mb-6">
        <div class="abw-card-header">
            <div class="abw-flex abw-items-center abw-justify-between">
                <div>
                    <h1 class="abw-text-2xl abw-font-bold abw-mb-0">
                        <?php esc_html_e('AutoBotWriter Dashboard', $text_domain); ?>
                    </h1>
                    <p class="abw-text-sm abw-text-gray-500 abw-mb-0">
                        <?php esc_html_e('AI-powered content generation made simple', $text_domain); ?>
                    </p>
                </div>
                <div class="abw-flex abw-gap-2">
                    <button class="abw-btn abw-btn-secondary" data-abw-action="show-modal" data-abw-params='{"target": "#help-modal"}'>
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php esc_html_e('Help', $text_domain); ?>
                    </button>
                    <button class="abw-btn abw-btn-primary" data-abw-action="show-modal" data-abw-params='{"target": "#quick-generate-modal"}'>
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Quick Generate', $text_domain); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <nav class="abw-tabs abw-mb-6">
        <ul class="abw-tab-list">
            <li>
                <a href="#dashboard" class="abw-tab <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php esc_html_e('Dashboard', $text_domain); ?>
                </a>
            </li>
            <li>
                <a href="#content-writer" class="abw-tab <?php echo $current_tab === 'content-writer' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Content Writer', $text_domain); ?>
                </a>
            </li>
            <li>
                <a href="#history" class="abw-tab <?php echo $current_tab === 'history' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('History', $text_domain); ?>
                </a>
            </li>
            <li>
                <a href="#templates" class="abw-tab <?php echo $current_tab === 'templates' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-layout"></span>
                    <?php esc_html_e('Templates', $text_domain); ?>
                </a>
            </li>
            <li>
                <a href="#analytics" class="abw-tab <?php echo $current_tab === 'analytics' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php esc_html_e('Analytics', $text_domain); ?>
                </a>
            </li>
            <li>
                <a href="#settings" class="abw-tab <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Settings', $text_domain); ?>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Tab Panels -->
    <div class="abw-tab-panels">
        <!-- Dashboard Panel -->
        <div id="dashboard" class="abw-tab-panel <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">
            <?php $this->render_dashboard_panel($stats); ?>
        </div>

        <!-- Content Writer Panel -->
        <div id="content-writer" class="abw-tab-panel <?php echo $current_tab === 'content-writer' ? 'active' : ''; ?>">
            <?php $this->render_content_writer_panel(); ?>
        </div>

        <!-- History Panel -->
        <div id="history" class="abw-tab-panel <?php echo $current_tab === 'history' ? 'active' : ''; ?>">
            <?php $this->render_history_panel(); ?>
        </div>

        <!-- Templates Panel -->
        <div id="templates" class="abw-tab-panel <?php echo $current_tab === 'templates' ? 'active' : ''; ?>">
            <?php $this->render_templates_panel(); ?>
        </div>

        <!-- Analytics Panel -->
        <div id="analytics" class="abw-tab-panel <?php echo $current_tab === 'analytics' ? 'active' : ''; ?>">
            <?php $this->render_analytics_panel(); ?>
        </div>

        <!-- Settings Panel -->
        <div id="settings" class="abw-tab-panel <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
            <?php $this->render_settings_panel(); ?>
        </div>
    </div>
</div>

<!-- Modals -->
<?php $this->render_modals(); ?>

<script>
// Initialize modern UI components
jQuery(document).ready(function($) {
    // Initialize dashboard
    if (typeof AutoBotWriterUI !== 'undefined') {
        AutoBotWriterUI.dashboard = new AutoBotWriterDashboard();
    }
});
</script>

<?php
// Dashboard Panel Content
function render_dashboard_panel($stats) {
    $text_domain = Plugin::TEXT_DOMAIN;
    ?>
    <div class="abw-grid abw-grid-cols-4 abw-gap-6 abw-mb-8">
        <!-- Stats Cards -->
        <div class="abw-card">
            <div class="abw-card-body abw-text-center">
                <div class="abw-text-3xl abw-font-bold abw-text-primary abw-mb-2">
                    <?php echo esc_html($stats['total_posts']); ?>
                </div>
                <div class="abw-text-sm abw-text-gray-500">
                    <?php esc_html_e('Total Posts Generated', $text_domain); ?>
                </div>
            </div>
        </div>

        <div class="abw-card">
            <div class="abw-card-body abw-text-center">
                <div class="abw-text-3xl abw-font-bold abw-text-success abw-mb-2">
                    <?php echo esc_html($stats['this_month']); ?>
                </div>
                <div class="abw-text-sm abw-text-gray-500">
                    <?php esc_html_e('This Month', $text_domain); ?>
                </div>
            </div>
        </div>

        <div class="abw-card">
            <div class="abw-card-body abw-text-center">
                <div class="abw-text-3xl abw-font-bold abw-text-warning abw-mb-2">
                    <?php echo esc_html($stats['pending']); ?>
                </div>
                <div class="abw-text-sm abw-text-gray-500">
                    <?php esc_html_e('Pending Generation', $text_domain); ?>
                </div>
            </div>
        </div>

        <div class="abw-card">
            <div class="abw-card-body abw-text-center">
                <div class="abw-text-3xl abw-font-bold abw-text-info abw-mb-2">
                    <?php echo esc_html($stats['api_usage']); ?>%
                </div>
                <div class="abw-text-sm abw-text-gray-500">
                    <?php esc_html_e('API Usage', $text_domain); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="abw-grid abw-grid-cols-2 abw-gap-6">
        <!-- Recent Activity -->
        <div class="abw-card">
            <div class="abw-card-header">
                <h3 class="abw-text-lg abw-font-semibold abw-mb-0">
                    <?php esc_html_e('Recent Activity', $text_domain); ?>
                </h3>
            </div>
            <div class="abw-card-body">
                <div class="abw-space-y-4">
                    <?php foreach ($stats['recent_activity'] as $activity): ?>
                        <div class="abw-flex abw-items-center abw-gap-3">
                            <div class="abw-w-2 abw-h-2 abw-bg-primary abw-rounded-full"></div>
                            <div class="abw-flex-1">
                                <div class="abw-text-sm abw-font-medium">
                                    <?php echo esc_html($activity['title']); ?>
                                </div>
                                <div class="abw-text-xs abw-text-gray-500">
                                    <?php echo esc_html($activity['time']); ?>
                                </div>
                            </div>
                            <div class="abw-badge abw-badge-<?php echo esc_attr($activity['status']); ?>">
                                <?php echo esc_html(ucfirst($activity['status'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="abw-card">
            <div class="abw-card-header">
                <h3 class="abw-text-lg abw-font-semibold abw-mb-0">
                    <?php esc_html_e('Quick Actions', $text_domain); ?>
                </h3>
            </div>
            <div class="abw-card-body">
                <div class="abw-grid abw-grid-cols-2 abw-gap-4">
                    <button class="abw-btn abw-btn-primary abw-btn-lg" data-abw-action="show-modal" data-abw-params='{"target": "#quick-generate-modal"}'>
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Generate Post', $text_domain); ?>
                    </button>
                    
                    <button class="abw-btn abw-btn-secondary abw-btn-lg" onclick="location.href='#content-writer'">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Content Writer', $text_domain); ?>
                    </button>
                    
                    <button class="abw-btn abw-btn-secondary abw-btn-lg" onclick="location.href='#templates'">
                        <span class="dashicons dashicons-layout"></span>
                        <?php esc_html_e('Templates', $text_domain); ?>
                    </button>
                    
                    <button class="abw-btn abw-btn-secondary abw-btn-lg" onclick="location.href='#settings'">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Settings', $text_domain); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Content Writer Panel
function render_content_writer_panel() {
    $text_domain = Plugin::TEXT_DOMAIN;
    ?>
    <div class="abw-card">
        <div class="abw-card-header">
            <h2 class="abw-text-xl abw-font-semibold abw-mb-0">
                <?php esc_html_e('AI Content Writer', $text_domain); ?>
            </h2>
            <p class="abw-text-sm abw-text-gray-500 abw-mb-0">
                <?php esc_html_e('Create high-quality content with AI assistance', $text_domain); ?>
            </p>
        </div>
        <div class="abw-card-body">
            <!-- Enhanced Wizard Component -->
            <div class="abw-wizard" data-abw-component="wizard">
                <!-- Progress Bar -->
                <div class="abw-wizard-progress abw-mb-6">
                    <div class="abw-progress">
                        <div class="abw-wizard-progress-bar abw-progress-bar" style="width: 25%"></div>
                    </div>
                </div>

                <!-- Step Indicators -->
                <div class="abw-wizard-indicators abw-flex abw-justify-center abw-gap-4 abw-mb-8">
                    <div class="abw-wizard-indicator active">
                        <span class="abw-wizard-step-number">1</span>
                        <span class="abw-wizard-step-label"><?php esc_html_e('Topic', $text_domain); ?></span>
                    </div>
                    <div class="abw-wizard-indicator">
                        <span class="abw-wizard-step-number">2</span>
                        <span class="abw-wizard-step-label"><?php esc_html_e('Details', $text_domain); ?></span>
                    </div>
                    <div class="abw-wizard-indicator">
                        <span class="abw-wizard-step-number">3</span>
                        <span class="abw-wizard-step-label"><?php esc_html_e('Settings', $text_domain); ?></span>
                    </div>
                    <div class="abw-wizard-indicator">
                        <span class="abw-wizard-step-number">4</span>
                        <span class="abw-wizard-step-label"><?php esc_html_e('Review', $text_domain); ?></span>
                    </div>
                </div>

                <!-- Step 1: Topic Selection -->
                <div class="abw-wizard-step">
                    <h3 class="abw-text-lg abw-font-semibold abw-mb-4">
                        <?php esc_html_e('What would you like to write about?', $text_domain); ?>
                    </h3>
                    
                    <form data-abw-form="content-topic">
                        <div class="abw-form-group">
                            <label class="abw-label" for="content-topic">
                                <?php esc_html_e('Topic or Subject', $text_domain); ?>
                            </label>
                            <textarea 
                                id="content-topic" 
                                name="topic" 
                                class="abw-textarea" 
                                rows="4" 
                                placeholder="<?php esc_attr_e('Describe what you want to write about...', $text_domain); ?>"
                                data-validate="required"
                            ></textarea>
                            <div class="abw-help-text">
                                <?php esc_html_e('Be specific about your topic to get better results', $text_domain); ?>
                            </div>
                        </div>

                        <div class="abw-form-group">
                            <label class="abw-label" for="content-type">
                                <?php esc_html_e('Content Type', $text_domain); ?>
                            </label>
                            <select id="content-type" name="content_type" class="abw-select">
                                <option value="blog-post"><?php esc_html_e('Blog Post', $text_domain); ?></option>
                                <option value="article"><?php esc_html_e('Article', $text_domain); ?></option>
                                <option value="tutorial"><?php esc_html_e('Tutorial', $text_domain); ?></option>
                                <option value="review"><?php esc_html_e('Review', $text_domain); ?></option>
                                <option value="news"><?php esc_html_e('News', $text_domain); ?></option>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Navigation -->
                <div class="abw-wizard-navigation abw-flex abw-justify-between abw-mt-8">
                    <button class="abw-btn abw-btn-secondary" data-wizard-prev style="display: none;">
                        <?php esc_html_e('Previous', $text_domain); ?>
                    </button>
                    <button class="abw-btn abw-btn-primary" data-wizard-next>
                        <?php esc_html_e('Next', $text_domain); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Additional panel rendering functions would go here...
?>

<style>
/* Wizard-specific styles */
.abw-wizard-indicators {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
}

.abw-wizard-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.5;
    transition: var(--abw-transition);
}

.abw-wizard-indicator.active,
.abw-wizard-indicator.completed {
    opacity: 1;
}

.abw-wizard-step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background-color: var(--abw-gray-300);
    color: var(--abw-gray-600);
    font-weight: 600;
    font-size: 0.875rem;
}

.abw-wizard-indicator.active .abw-wizard-step-number {
    background-color: var(--abw-primary);
    color: var(--abw-white);
}

.abw-wizard-indicator.completed .abw-wizard-step-number {
    background-color: var(--abw-success);
    color: var(--abw-white);
}

.abw-wizard-step-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--abw-gray-600);
}

.abw-wizard-step {
    display: none;
}

.abw-wizard-step:first-child {
    display: block;
}

/* Dashboard stats animation */
@keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.abw-card-body .abw-text-3xl {
    animation: countUp 0.6s ease-out;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .abw-wizard-indicators {
        gap: 1rem;
    }
    
    .abw-wizard-step-label {
        display: none;
    }
}
</style>
