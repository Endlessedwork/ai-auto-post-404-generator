<?php
/**
 * Plugin Name: AI Auto Post Generator
 * Plugin URI: https://endlessedwork.com
 * Description: Automatically generates blog posts from 404 URL keywords using AI (OpenAI GPT, Anthropic Claude, Google Gemini). Supports custom prompts, blocked keywords and URL patterns, rate limiting, AI featured images, SEO meta, and internal linking.
 * Version: 1.0.0
 * Author: Endlessedwork
 * Author URI: https://endlessedwork.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-auto-post-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AIAPG_VERSION', '1.0.0');
define('AIAPG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIAPG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIAPG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'AIAPG\\';
    $base_dir = AIAPG_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    // Convert namespace to file path
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Convert class name format (e.g., ContentGenerator -> class-content-generator.php)
    $parts = explode('\\', $relative_class);
    $class_name = array_pop($parts);
    $class_file = 'class-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name)) . '.php';

    if (!empty($parts)) {
        $sub_dir = strtolower(implode('/', $parts)) . '/';
    } else {
        $sub_dir = '';
    }

    $file = $base_dir . $sub_dir . $class_file;

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Initialize the plugin
 */
function aiapg_init() {
    // Load text domain for translations
    load_plugin_textdomain('ai-auto-post-generator', false, dirname(AIAPG_PLUGIN_BASENAME) . '/languages');

    // Initialize main plugin class
    $plugin = new AIAPG\Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'aiapg_init');

/**
 * Activation hook
 */
function aiapg_activate() {
    // Set default options
    $default_options = [
        'plugin_enabled' => false, // Disabled by default for safety
        'ai_provider' => 'openai',
        'openai_api_key' => '',
        'anthropic_api_key' => '',
        'gemini_api_key' => '',
        'openai_model' => 'gpt-4o',
        'anthropic_model' => 'claude-sonnet-4-20250514',
        'gemini_model' => 'gemini-1.5-flash',
        'max_tokens' => 8000,
        'post_status' => 'publish',
        'post_author' => 1,
        'default_category' => 1,
        'enable_featured_image' => true,
        'enable_seo' => true,
        'enable_cache' => true,
        'rate_limit_per_day' => 50,
        'content_language' => 'th',
        'min_word_count' => 500,
    ];

    if (!get_option('aiapg_settings')) {
        add_option('aiapg_settings', $default_options);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'aiapg_activate');

/**
 * Deactivation hook
 */
function aiapg_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'aiapg_deactivate');

/**
 * Uninstall hook (optional - for cleanup)
 */
function aiapg_uninstall() {
    // Uncomment to delete options on uninstall
    // delete_option('aiapg_settings');
}
// register_uninstall_hook(__FILE__, 'aiapg_uninstall');
