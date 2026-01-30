<?php
/**
 * Post Creator Class
 *
 * Creates WordPress posts from generated content
 *
 * @package AIAPG
 */

namespace AIAPG;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles WordPress post creation
 */
class PostCreator {

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     *
     * @param array $settings Plugin settings
     */
    public function __construct($settings) {
        $this->settings = $settings;
    }

    /**
     * Create a new post from generated content
     *
     * @param string $keyword Keyword used for slug
     * @param array  $content Generated content array
     * @return int|WP_Error Post ID or error
     */
    public function create($keyword, $content) {
        // Prepare post data
        $post_data = [
            'post_title'   => $content['title'] ?: $this->generate_title($keyword),
            'post_content' => $content['content'],
            'post_excerpt' => $content['excerpt'] ?? '',
            'post_status'  => $this->settings['post_status'] ?? 'publish',
            'post_author'  => $this->settings['post_author'] ?? 1,
            'post_type'    => 'post',
            'post_name'    => sanitize_title($keyword), // Slug
        ];

        // Add category if set
        $category = $this->settings['default_category'] ?? 1;
        if ($category) {
            $post_data['post_category'] = [$category];
        }

        // Insert the post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set featured image if available
        if (!empty($content['image_url'])) {
            $this->set_featured_image($post_id, $content['image_url'], $keyword);
        }

        // Set SEO meta data
        if (!empty($this->settings['enable_seo'])) {
            $this->set_seo_meta($post_id, $content);
        }

        // Add custom meta to track AI-generated posts
        update_post_meta($post_id, '_aiapg_generated', true);
        update_post_meta($post_id, '_aiapg_keyword', $keyword);
        update_post_meta($post_id, '_aiapg_generated_at', current_time('mysql'));
        update_post_meta($post_id, '_aiapg_provider', $this->settings['ai_provider'] ?? 'openai');

        /**
         * Action after AI post is created
         *
         * @param int    $post_id Post ID
         * @param string $keyword Original keyword
         * @param array  $content Generated content
         */
        do_action('aiapg_post_created', $post_id, $keyword, $content);

        return $post_id;
    }

    /**
     * Generate basic title from keyword
     *
     * @param string $keyword Keyword
     * @return string Title
     */
    private function generate_title($keyword) {
        return mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Set featured image from URL
     *
     * @param int    $post_id   Post ID
     * @param string $image_url Image URL
     * @param string $keyword   Keyword for alt text
     * @return int|false Attachment ID or false
     */
    private function set_featured_image($post_id, $image_url, $keyword) {
        // Include required files
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download image to temp file
        $temp_file = download_url($image_url);

        if (is_wp_error($temp_file)) {
            error_log('AIAPG: Failed to download image: ' . $temp_file->get_error_message());
            return false;
        }

        // Prepare file array
        $file_array = [
            'name'     => sanitize_title($keyword) . '-' . time() . '.png',
            'tmp_name' => $temp_file,
        ];

        // Upload and attach to post
        $attachment_id = media_handle_sideload($file_array, $post_id, $keyword);

        // Clean up temp file
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }

        if (is_wp_error($attachment_id)) {
            error_log('AIAPG: Failed to sideload image: ' . $attachment_id->get_error_message());
            return false;
        }

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        // Update alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $keyword);

        return $attachment_id;
    }

    /**
     * Set SEO meta data
     *
     * @param int   $post_id Post ID
     * @param array $content Content array with SEO data
     */
    private function set_seo_meta($post_id, $content) {
        $meta_title = $content['meta_title'] ?? '';
        $meta_desc = $content['meta_desc'] ?? '';
        $focus_keyphrase = $content['focus_keyphrase'] ?? '';
        $secondary_keywords = $content['secondary_keywords'] ?? [];

        // Store in generic meta fields
        if ($meta_title) {
            update_post_meta($post_id, '_aiapg_meta_title', $meta_title);
        }
        if ($meta_desc) {
            update_post_meta($post_id, '_aiapg_meta_description', $meta_desc);
        }
        if ($focus_keyphrase) {
            update_post_meta($post_id, '_aiapg_focus_keyphrase', $focus_keyphrase);
        }
        if (!empty($secondary_keywords)) {
            update_post_meta($post_id, '_aiapg_secondary_keywords', $secondary_keywords);
        }

        // Yoast SEO compatibility
        if ($this->is_plugin_active('wordpress-seo/wp-seo.php')) {
            if ($meta_title) {
                update_post_meta($post_id, '_yoast_wpseo_title', $meta_title);
            }
            if ($meta_desc) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
            }
            if ($focus_keyphrase) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyphrase);
            }
        }

        // Rank Math compatibility
        if ($this->is_plugin_active('seo-by-rank-math/rank-math.php')) {
            if ($meta_title) {
                update_post_meta($post_id, 'rank_math_title', $meta_title);
            }
            if ($meta_desc) {
                update_post_meta($post_id, 'rank_math_description', $meta_desc);
            }
            if ($focus_keyphrase) {
                update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyphrase);
            }
            // Rank Math robots - use array format properly
            update_post_meta($post_id, 'rank_math_robots', ['index']);
        }

        // All in One SEO compatibility
        if ($this->is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php')) {
            if ($meta_title) {
                update_post_meta($post_id, '_aioseo_title', $meta_title);
            }
            if ($meta_desc) {
                update_post_meta($post_id, '_aioseo_description', $meta_desc);
            }
            if ($focus_keyphrase) {
                update_post_meta($post_id, '_aioseo_keyphrases', json_encode([
                    'focus' => ['keyphrase' => $focus_keyphrase],
                    'additional' => array_map(function($kw) {
                        return ['keyphrase' => $kw];
                    }, $secondary_keywords)
                ]));
            }
        }

        // Add Schema.org Article markup data
        $this->set_schema_meta($post_id, $content);
    }

    /**
     * Set Schema.org metadata
     *
     * @param int   $post_id Post ID
     * @param array $content Content array
     */
    private function set_schema_meta($post_id, $content) {
        $schema_data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $content['title'] ?? '',
            'description' => $content['meta_desc'] ?? '',
            'keywords' => $content['focus_keyphrase'] ?? '',
            'articleSection' => 'Blog',
            'inLanguage' => $this->settings['content_language'] ?? 'th',
        ];

        update_post_meta($post_id, '_aiapg_schema_data', $schema_data);

        // Note: Rank Math handles schema automatically, don't override it
        // The plugin will generate proper schema based on meta title/description
    }

    /**
     * Check if a plugin is active
     *
     * @param string $plugin Plugin path
     * @return bool
     */
    private function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }
}
