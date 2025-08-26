=== AutoBotWriter Free ===
Contributors: AvanSaber.com
Tags: ai writer, blog generation, openai, content automation, gpt
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

AutoBotWriter Free is a powerful WordPress plugin that leverages OpenAI's advanced AI models to automatically generate high-quality blog posts. Create engaging, SEO-optimized content with just a few clicks.

**Key Features:**
* AI-powered content generation using OpenAI GPT models
* Automated blog post scheduling and publishing
* SEO-optimized content with customizable keywords
* Multi-step wizard for easy content creation
* Support for categories, tags, and custom authors
* Bulk content generation capabilities
* Content history and management

**Supported AI Models:**
* GPT-4 (recommended)
* GPT-3.5 Turbo
* Legacy GPT-3 models

**Modern Architecture:**
Version 1.6.0 features a complete architectural overhaul with modern PHP practices, enhanced performance, comprehensive logging, and advanced caching systems.

**Security First:**
Built with security as a priority, featuring SQL injection prevention, CSRF protection, enhanced input validation, and comprehensive security monitoring.

== Installation ==

1. Upload the `autobotwriter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'AutoBotWriter' in your WordPress admin menu
4. Go to Settings tab and enter your OpenAI API key
5. Configure your preferred AI model and generation settings
6. Start creating AI-generated content!

**Getting Your OpenAI API Key:**
1. Visit [OpenAI Platform](https://platform.openai.com/account/api-keys)
2. Create an account or sign in
3. Generate a new API key
4. Copy and paste it into the plugin settings

== Frequently Asked Questions ==

= How does this plugin work? =
AutoBotWriter uses OpenAI's powerful language models to generate blog content. You provide topics and keywords, and the AI creates comprehensive, engaging blog posts with introductions, structured content sections, and conclusions.

= Is this plugin secure? =
Yes! Version 1.5.0 includes comprehensive security enhancements including SQL injection prevention, CSRF protection, proper input sanitization, and encrypted API key storage.

= What AI models are supported? =
The plugin supports GPT-4, GPT-3.5 Turbo, and legacy GPT-3 models. We recommend GPT-4 for the best content quality.

= How much does it cost to use? =
The plugin is free, but you need an OpenAI API account. OpenAI charges based on token usage. Typical blog posts cost $0.01-$0.10 depending on length and model used.

= Can I customize the generated content? =
Yes! You can specify include/exclude keywords, set the number of headings, adjust creativity levels (temperature), and control content length (tokens).

= Is there a limit on content generation? =
The free version allows up to 5 posts per month. The limit resets monthly and is designed to let you test the plugin's capabilities.

= Can I schedule posts for future publication? =
Yes! The plugin includes a scheduling system that allows you to set specific publication dates and times for your generated content.

== Screenshots ==

1. Main dashboard with content generation wizard
2. Settings page with OpenAI configuration
3. Content history and management interface
4. Generated blog post example

== Changelog ==

= 1.6.0 - 2024-12-19 =
**MAJOR ARCHITECTURE UPDATE**
* NEW: Modern PHP architecture with PSR-4 autoloading and namespaces
* NEW: Advanced caching system with object cache support
* NEW: Comprehensive logging system with multiple log levels
* NEW: Performance monitoring and optimization tools
* NEW: Enhanced database layer with query optimization
* NEW: Modern admin interface with template-based rendering
* NEW: Advanced encryption utilities with WordPress integration
* NEW: Content processor with improved workflow management
* ENHANCED: Developer experience with modern coding patterns
* MAINTAINED: Full backward compatibility with existing installations

= 1.5.0 - 2024-12-19 =
**SECURITY UPDATE - CRITICAL**
* FIXED: SQL injection vulnerabilities in database queries
* FIXED: CSRF vulnerabilities in AJAX requests  
* FIXED: Input sanitization and validation issues
* ENHANCED: API key encryption using WordPress security keys
* ADDED: Comprehensive capability checks and user permissions
* IMPROVED: Error handling and logging
* OPTIMIZED: Database queries with proper indexing
* UPDATED: Code to WordPress coding standards

= 1.4.29 =
* Basic AI content generation functionality
* OpenAI API integration
* WordPress post creation and scheduling

== Upgrade Notice ==

= 1.6.0 =
**MAJOR ARCHITECTURE UPDATE** - This version introduces modern PHP architecture, advanced caching, comprehensive logging, and performance optimizations. Fully backward compatible. Recommended for all users.

= 1.5.0 =
**CRITICAL SECURITY UPDATE** - This version fixes multiple security vulnerabilities including SQL injection and CSRF attacks. All users must update immediately. Backup your site before updating.

== Security ==

This plugin takes security seriously. Version 1.5.0 addresses all known security vulnerabilities:

* SQL injection prevention with prepared statements
* CSRF protection with WordPress nonces
* Input sanitization and validation
* Encrypted API key storage
* Proper user capability checks

For security issues, please email security@autobotwriter.com

== Support ==

* Documentation: [Plugin Documentation](https://autobotwriter.com/docs)
* Support Forum: [WordPress.org Support](https://wordpress.org/support/plugin/autobotwriter)
* GitHub: [Report Issues](https://github.com/avansaber/autobotwriter/issues)

== Privacy ==

This plugin:
* Sends content prompts to OpenAI's API for processing
* Stores encrypted API keys in your WordPress database
* Does not collect or transmit personal user data
* Follows WordPress privacy best practices

Please review OpenAI's privacy policy for information about how they handle data sent to their API.
