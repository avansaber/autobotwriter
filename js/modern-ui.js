/**
 * AutoBotWriter Modern UI Framework
 * 
 * @package AutoBotWriter
 * @since 1.6.0
 */

(function($) {
    'use strict';

    // AutoBotWriter UI Namespace
    window.AutoBotWriterUI = window.AutoBotWriterUI || {};

    /**
     * Main UI Controller
     */
    class UIController {
        constructor() {
            this.components = new Map();
            this.eventBus = new EventBus();
            this.init();
        }

        init() {
            this.initializeComponents();
            this.bindGlobalEvents();
            this.setupAccessibility();
        }

        initializeComponents() {
            // Initialize all UI components
            this.components.set('modal', new ModalManager());
            this.components.set('notification', new NotificationManager());
            this.components.set('progress', new ProgressManager());
            this.components.set('form', new FormManager());
            this.components.set('tabs', new TabManager());
            this.components.set('wizard', new WizardManager());
        }

        bindGlobalEvents() {
            // Global keyboard shortcuts
            $(document).on('keydown', this.handleGlobalKeyboard.bind(this));
            
            // Global click handlers
            $(document).on('click', '[data-abw-action]', this.handleActionClick.bind(this));
            
            // Form submission handlers
            $(document).on('submit', '[data-abw-form]', this.handleFormSubmit.bind(this));
        }

        setupAccessibility() {
            // Add ARIA attributes and keyboard navigation
            $('[data-abw-component]').each(function() {
                const $element = $(this);
                const component = $element.data('abw-component');
                
                if (!$element.attr('role')) {
                    $element.attr('role', component);
                }
            });
        }

        handleGlobalKeyboard(event) {
            // ESC key closes modals
            if (event.key === 'Escape') {
                this.components.get('modal').closeAll();
            }
        }

        handleActionClick(event) {
            event.preventDefault();
            const $target = $(event.currentTarget);
            const action = $target.data('abw-action');
            const params = $target.data('abw-params') || {};

            this.executeAction(action, params, $target);
        }

        handleFormSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            const formType = $form.data('abw-form');

            this.components.get('form').handleSubmit($form, formType);
        }

        executeAction(action, params, $element) {
            switch (action) {
                case 'show-modal':
                    this.components.get('modal').show(params.target);
                    break;
                case 'hide-modal':
                    this.components.get('modal').hide(params.target);
                    break;
                case 'show-notification':
                    this.components.get('notification').show(params.message, params.type);
                    break;
                case 'toggle-section':
                    this.toggleSection(params.target);
                    break;
                case 'copy-text':
                    this.copyToClipboard(params.text || $element.data('copy-text'));
                    break;
                default:
                    console.warn('Unknown action:', action);
            }
        }

        toggleSection(target) {
            const $target = $(target);
            $target.slideToggle(300);
            
            const $trigger = $(`[data-abw-action="toggle-section"][data-abw-params*="${target}"]`);
            $trigger.toggleClass('active');
        }

        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.components.get('notification').show('Copied to clipboard!', 'success');
            } catch (err) {
                console.error('Failed to copy text: ', err);
                this.components.get('notification').show('Failed to copy text', 'error');
            }
        }

        getComponent(name) {
            return this.components.get(name);
        }
    }

    /**
     * Event Bus for component communication
     */
    class EventBus {
        constructor() {
            this.events = {};
        }

        on(event, callback) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            this.events[event].push(callback);
        }

        off(event, callback) {
            if (this.events[event]) {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            }
        }

        emit(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(callback => callback(data));
            }
        }
    }

    /**
     * Modal Manager
     */
    class ModalManager {
        constructor() {
            this.activeModals = new Set();
            this.init();
        }

        init() {
            this.createOverlay();
            this.bindEvents();
        }

        createOverlay() {
            if (!$('#abw-modal-overlay').length) {
                $('body').append('<div id="abw-modal-overlay" class="abw-modal-overlay abw-hidden"></div>');
            }
        }

        bindEvents() {
            // Close modal on overlay click
            $(document).on('click', '#abw-modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeAll();
                }
            });

            // Close modal on close button click
            $(document).on('click', '[data-modal-close]', () => {
                this.closeAll();
            });
        }

        show(modalId) {
            const $modal = $(modalId);
            if (!$modal.length) return;

            // Add modal to overlay
            $('#abw-modal-overlay').removeClass('abw-hidden').append($modal);
            $modal.addClass('abw-fade-in');
            
            this.activeModals.add(modalId);
            $('body').addClass('modal-open');

            // Focus management
            const $firstFocusable = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').first();
            $firstFocusable.focus();
        }

        hide(modalId) {
            const $modal = $(modalId);
            if (!$modal.length) return;

            $modal.removeClass('abw-fade-in');
            
            setTimeout(() => {
                $modal.appendTo('body');
                this.activeModals.delete(modalId);
                
                if (this.activeModals.size === 0) {
                    $('#abw-modal-overlay').addClass('abw-hidden');
                    $('body').removeClass('modal-open');
                }
            }, 300);
        }

        closeAll() {
            this.activeModals.forEach(modalId => this.hide(modalId));
        }
    }

    /**
     * Notification Manager
     */
    class NotificationManager {
        constructor() {
            this.container = null;
            this.notifications = new Map();
            this.init();
        }

        init() {
            this.createContainer();
        }

        createContainer() {
            if (!$('#abw-notifications').length) {
                $('body').append('<div id="abw-notifications" class="abw-notifications-container"></div>');
            }
            this.container = $('#abw-notifications');
        }

        show(message, type = 'info', duration = 5000) {
            const id = 'notification-' + Date.now();
            const $notification = this.createNotification(id, message, type);
            
            this.container.append($notification);
            $notification.addClass('abw-slide-up');
            
            this.notifications.set(id, $notification);

            if (duration > 0) {
                setTimeout(() => this.hide(id), duration);
            }

            return id;
        }

        createNotification(id, message, type) {
            const iconMap = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };

            return $(`
                <div id="${id}" class="abw-notification abw-alert abw-alert-${type}">
                    <div class="abw-flex abw-items-center abw-gap-2">
                        <span class="abw-notification-icon">${iconMap[type] || iconMap.info}</span>
                        <span class="abw-notification-message">${message}</span>
                        <button class="abw-notification-close" data-notification-id="${id}" aria-label="Close">×</button>
                    </div>
                </div>
            `);
        }

        hide(id) {
            const $notification = this.notifications.get(id);
            if (!$notification) return;

            $notification.fadeOut(300, () => {
                $notification.remove();
                this.notifications.delete(id);
            });
        }

        clear() {
            this.notifications.forEach((notification, id) => this.hide(id));
        }
    }

    /**
     * Progress Manager
     */
    class ProgressManager {
        constructor() {
            this.progressBars = new Map();
        }

        create(id, container) {
            const $progressBar = $(`
                <div class="abw-progress-container">
                    <div class="abw-progress">
                        <div class="abw-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="abw-progress-text">0%</div>
                </div>
            `);

            $(container).append($progressBar);
            this.progressBars.set(id, $progressBar);
            
            return $progressBar;
        }

        update(id, percentage, text = null) {
            const $progressBar = this.progressBars.get(id);
            if (!$progressBar) return;

            const $bar = $progressBar.find('.abw-progress-bar');
            const $text = $progressBar.find('.abw-progress-text');

            $bar.css('width', `${Math.min(100, Math.max(0, percentage))}%`);
            $text.text(text || `${Math.round(percentage)}%`);
        }

        remove(id) {
            const $progressBar = this.progressBars.get(id);
            if ($progressBar) {
                $progressBar.fadeOut(300, () => $progressBar.remove());
                this.progressBars.delete(id);
            }
        }
    }

    /**
     * Form Manager
     */
    class FormManager {
        constructor() {
            this.validators = new Map();
            this.init();
        }

        init() {
            this.setupValidation();
            this.bindEvents();
        }

        setupValidation() {
            // Common validators
            this.validators.set('required', (value) => {
                return value.trim() !== '' ? null : 'This field is required';
            });

            this.validators.set('email', (value) => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(value) ? null : 'Please enter a valid email address';
            });

            this.validators.set('api-key', (value) => {
                const apiKeyRegex = /^sk-[a-zA-Z0-9]{48,}$/;
                return apiKeyRegex.test(value) ? null : 'Please enter a valid OpenAI API key';
            });

            this.validators.set('number', (value, min = null, max = null) => {
                const num = parseFloat(value);
                if (isNaN(num)) return 'Please enter a valid number';
                if (min !== null && num < min) return `Value must be at least ${min}`;
                if (max !== null && num > max) return `Value must be at most ${max}`;
                return null;
            });
        }

        bindEvents() {
            // Real-time validation
            $(document).on('blur', '[data-validate]', this.validateField.bind(this));
            $(document).on('input', '[data-validate]', this.clearFieldError.bind(this));
        }

        validateField(event) {
            const $field = $(event.target);
            const validators = $field.data('validate').split('|');
            const value = $field.val();

            for (const validatorString of validators) {
                const [validatorName, ...params] = validatorString.split(':');
                const validator = this.validators.get(validatorName);

                if (validator) {
                    const error = validator(value, ...params);
                    if (error) {
                        this.showFieldError($field, error);
                        return false;
                    }
                }
            }

            this.clearFieldError($field);
            return true;
        }

        showFieldError($field, message) {
            $field.addClass('abw-error');
            
            let $errorElement = $field.siblings('.abw-error-text');
            if (!$errorElement.length) {
                $errorElement = $('<div class="abw-error-text"></div>');
                $field.after($errorElement);
            }
            
            $errorElement.text(message);
        }

        clearFieldError($field) {
            $field.removeClass('abw-error');
            $field.siblings('.abw-error-text').remove();
        }

        validateForm($form) {
            let isValid = true;
            
            $form.find('[data-validate]').each((index, field) => {
                if (!this.validateField({ target: field })) {
                    isValid = false;
                }
            });

            return isValid;
        }

        async handleSubmit($form, formType) {
            if (!this.validateForm($form)) {
                return;
            }

            const submitButton = $form.find('[type="submit"]');
            const originalText = submitButton.text();
            
            try {
                submitButton.prop('disabled', true).html('<span class="abw-spinner"></span> Processing...');
                
                const formData = new FormData($form[0]);
                const data = Object.fromEntries(formData.entries());
                
                // Add nonce
                data.nonce = autobotwriter.nonces[formType] || autobotwriter.nonces.save_settings;
                data.action = `aibot_${formType}`;

                const response = await this.submitForm(data);
                
                if (response.success) {
                    AutoBotWriterUI.controller.getComponent('notification').show(
                        response.data.message || 'Form submitted successfully!',
                        'success'
                    );
                    
                    // Reset form if specified
                    if ($form.data('reset-on-success')) {
                        $form[0].reset();
                    }
                } else {
                    throw new Error(response.data || 'Form submission failed');
                }
                
            } catch (error) {
                AutoBotWriterUI.controller.getComponent('notification').show(
                    error.message || 'An error occurred while submitting the form',
                    'error'
                );
            } finally {
                submitButton.prop('disabled', false).text(originalText);
            }
        }

        async submitForm(data) {
            const response = await fetch(autobotwriter.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            });

            return await response.json();
        }
    }

    /**
     * Tab Manager
     */
    class TabManager {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeFromHash();
        }

        bindEvents() {
            $(document).on('click', '.abw-tab', this.handleTabClick.bind(this));
            $(window).on('hashchange', this.handleHashChange.bind(this));
        }

        handleTabClick(event) {
            event.preventDefault();
            const $tab = $(event.currentTarget);
            const target = $tab.attr('href') || $tab.data('target');
            
            this.activateTab($tab, target);
            
            // Update URL hash
            if (target.startsWith('#')) {
                history.pushState(null, null, target);
            }
        }

        handleHashChange() {
            this.initializeFromHash();
        }

        initializeFromHash() {
            const hash = window.location.hash || '#general';
            const $tab = $(`.abw-tab[href="${hash}"], .abw-tab[data-target="${hash}"]`);
            
            if ($tab.length) {
                this.activateTab($tab, hash);
            }
        }

        activateTab($tab, target) {
            const $tabContainer = $tab.closest('.abw-tabs');
            const $panelContainer = $tabContainer.siblings('.abw-tab-panels');

            // Deactivate all tabs and panels
            $tabContainer.find('.abw-tab').removeClass('active');
            $panelContainer.find('.abw-tab-panel').removeClass('active').hide();

            // Activate selected tab and panel
            $tab.addClass('active');
            $(target).addClass('active').show();
        }
    }

    /**
     * Wizard Manager
     */
    class WizardManager {
        constructor() {
            this.currentStep = 0;
            this.steps = [];
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeWizards();
        }

        bindEvents() {
            $(document).on('click', '[data-wizard-next]', this.nextStep.bind(this));
            $(document).on('click', '[data-wizard-prev]', this.prevStep.bind(this));
            $(document).on('click', '[data-wizard-goto]', this.gotoStep.bind(this));
        }

        initializeWizards() {
            $('.abw-wizard').each((index, wizard) => {
                this.initializeWizard($(wizard));
            });
        }

        initializeWizard($wizard) {
            const $steps = $wizard.find('.abw-wizard-step');
            const $indicators = $wizard.find('.abw-wizard-indicator');
            
            // Hide all steps except first
            $steps.hide().first().show();
            
            // Update indicators
            $indicators.first().addClass('active');
        }

        nextStep(event) {
            const $wizard = $(event.target).closest('.abw-wizard');
            const $currentStep = $wizard.find('.abw-wizard-step:visible');
            const currentIndex = $wizard.find('.abw-wizard-step').index($currentStep);
            
            if (this.validateStep($currentStep)) {
                this.gotoStepByIndex($wizard, currentIndex + 1);
            }
        }

        prevStep(event) {
            const $wizard = $(event.target).closest('.abw-wizard');
            const $currentStep = $wizard.find('.abw-wizard-step:visible');
            const currentIndex = $wizard.find('.abw-wizard-step').index($currentStep);
            
            this.gotoStepByIndex($wizard, currentIndex - 1);
        }

        gotoStep(event) {
            const $wizard = $(event.target).closest('.abw-wizard');
            const stepIndex = parseInt($(event.target).data('wizard-goto'));
            
            this.gotoStepByIndex($wizard, stepIndex);
        }

        gotoStepByIndex($wizard, stepIndex) {
            const $steps = $wizard.find('.abw-wizard-step');
            const $indicators = $wizard.find('.abw-wizard-indicator');
            
            if (stepIndex < 0 || stepIndex >= $steps.length) return;
            
            // Hide all steps and show target
            $steps.hide();
            $steps.eq(stepIndex).show();
            
            // Update indicators
            $indicators.removeClass('active completed');
            $indicators.slice(0, stepIndex).addClass('completed');
            $indicators.eq(stepIndex).addClass('active');
            
            // Update progress bar
            const progress = ((stepIndex + 1) / $steps.length) * 100;
            $wizard.find('.abw-wizard-progress-bar').css('width', `${progress}%`);
            
            // Update navigation buttons
            const $prevBtn = $wizard.find('[data-wizard-prev]');
            const $nextBtn = $wizard.find('[data-wizard-next]');
            
            $prevBtn.toggle(stepIndex > 0);
            $nextBtn.toggle(stepIndex < $steps.length - 1);
        }

        validateStep($step) {
            const $form = $step.find('form');
            if ($form.length) {
                return AutoBotWriterUI.controller.getComponent('form').validateForm($form);
            }
            return true;
        }
    }

    /**
     * Utility Functions
     */
    const Utils = {
        debounce(func, wait, immediate) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    timeout = null;
                    if (!immediate) func(...args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func(...args);
            };
        },

        throttle(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },

        formatDuration(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);
            
            if (hours > 0) {
                return `${hours}h ${minutes}m ${secs}s`;
            } else if (minutes > 0) {
                return `${minutes}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        }
    };

    // Initialize UI when document is ready
    $(document).ready(() => {
        AutoBotWriterUI.controller = new UIController();
        AutoBotWriterUI.Utils = Utils;
        
        // Global accessibility improvements
        $('body').addClass('autobotwriter-ui-loaded');
        
        console.log('AutoBotWriter UI Framework loaded successfully');
    });

    // Export for external use
    window.AutoBotWriterUI.UIController = UIController;
    window.AutoBotWriterUI.EventBus = EventBus;

})(jQuery);
