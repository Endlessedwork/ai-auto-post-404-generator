<?php
/**
 * Main Plugin Class
 *
 * @package AIAPG
 */

namespace AIAPG;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core plugin class that initializes all components
 */
class Plugin {

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Interceptor instance
     *
     * @var Interceptor
     */
    private $interceptor;

    /**
     * Admin settings instance
     *
     * @var AdminSettings
     */
    private $admin_settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('aiapg_settings', []);
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize admin settings (only in admin)
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize frontend interceptor
        $this->init_interceptor();

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Initialize admin components
     */
    private function init_admin() {
        require_once AIAPG_PLUGIN_DIR . 'admin/class-admin-settings.php';
        $this->admin_settings = new AdminSettings();
        $this->admin_settings->init();
    }

    /**
     * Initialize the 404 interceptor
     */
    private function init_interceptor() {
        $this->interceptor = new Interceptor($this->settings);
        $this->interceptor->init();
    }

    /**
     * Register plugin hooks
     */
    private function register_hooks() {
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . AIAPG_PLUGIN_BASENAME, [$this, 'add_settings_link']);

        // AJAX handler for generating posts (both logged in and not logged in)
        add_action('wp_ajax_aiapg_generate_post', [$this, 'ajax_generate_post']);
        add_action('wp_ajax_nopriv_aiapg_generate_post', [$this, 'ajax_generate_post']);
    }

    /**
     * AJAX handler for generating post content
     */
    public function ajax_generate_post() {
        // Prevent any output before JSON
        @ini_set('display_errors', 0);
        error_reporting(0);

        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }

        // Set proper headers
        header('Content-Type: application/json; charset=utf-8');

        // Increase time limit for AI generation
        @set_time_limit(300);

        // Increase memory limit
        @ini_set('memory_limit', '256M');

        try {
            $token = sanitize_text_field($_POST['token'] ?? '');

            if (empty($token)) {
                wp_send_json_error(['message' => 'Invalid token']);
                exit;
            }

            // Get keyword from transient
            $keyword = get_transient('aiapg_pending_' . $token);

            if (empty($keyword)) {
                wp_send_json_error(['message' => 'Token expired or invalid. Please try again.']);
                exit;
            }

            // Delete transient to prevent duplicate processing
            delete_transient('aiapg_pending_' . $token);

            // Reload settings
            $settings = get_option('aiapg_settings', []);

            // Generate content
            $content_generator = new ContentGenerator($settings);
            $content = $content_generator->generate($keyword);

            if (empty($content) || is_wp_error($content)) {
                $error_msg = is_wp_error($content) ? $content->get_error_message() : 'Failed to generate content';
                wp_send_json_error(['message' => $error_msg]);
                exit;
            }

            // Create post
            $post_creator = new PostCreator($settings);
            $post_id = $post_creator->create($keyword, $content);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => $post_id->get_error_message()]);
                exit;
            }

            // Increment rate limit
            $today = date('Y-m-d');
            $count_key = 'aiapg_count_' . $today;
            $current_count = get_transient($count_key) ?: 0;
            set_transient($count_key, $current_count + 1, DAY_IN_SECONDS);

            // Return success with post URL
            wp_send_json_success([
                'post_id' => $post_id,
                'url' => get_permalink($post_id),
            ]);

        } catch (\Exception $e) {
            if (function_exists('aiapg_debug_log')) {
                aiapg_debug_log(__FILE__ . ':ajax_generate_post', 'exception', [
                    'error' => $e->getMessage(),
                ], 'H4');
            }
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }

        exit;
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_ai-auto-post-generator') {
            return;
        }

        wp_enqueue_style(
            'aiapg-admin',
            AIAPG_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AIAPG_VERSION
        );

        wp_enqueue_script(
            'aiapg-admin',
            AIAPG_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AIAPG_VERSION,
            true
        );

        wp_localize_script('aiapg-admin', 'aiapg', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiapg_nonce'),
        ]);
    }

    /**
     * Add settings link to plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=ai-auto-post-generator'),
            __('Settings', 'ai-auto-post-generator')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get plugin settings
     *
     * @param string|null $key Optional specific setting key
     * @return mixed Settings array or specific value
     */
    public function get_settings($key = null) {
        if ($key !== null) {
            return $this->settings[$key] ?? null;
        }
        return $this->settings;
    }
}
