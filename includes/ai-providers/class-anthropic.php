<?php
/**
 * Anthropic (Claude) Provider Class
 *
 * @package AIAPG
 */

namespace AIAPG\AIProviders;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/abstract-ai-provider.php';

/**
 * Anthropic Claude API provider implementation
 */
class Anthropic extends AbstractAIProvider {

    /**
     * API base URL
     */
    const API_URL = 'https://api.anthropic.com/v1';

    /**
     * Default model
     */
    const DEFAULT_MODEL = 'claude-3-sonnet-20240229';

    /**
     * API version
     */
    const API_VERSION = '2023-06-01';

    /**
     * Constructor
     *
     * @param string $api_key API key
     * @param string $model   Model name
     */
    public function __construct($api_key, $model = '') {
        parent::__construct($api_key, $model ?: self::DEFAULT_MODEL);
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return 'Anthropic Claude';
    }

    /**
     * Check if supports image generation
     *
     * @return bool
     */
    public function supports_image_generation() {
        return false; // Claude doesn't generate images
    }

    /**
     * Generate text content
     *
     * @param string $prompt Prompt to send
     * @return string|WP_Error Generated text or error
     */
    public function generate_text($prompt) {
        $url = self::API_URL . '/messages';

        $body = [
            'model'      => $this->model,
            'max_tokens' => 4096,
            'system'     => 'You are a professional content writer. Write high-quality, SEO-optimized blog posts in the requested language. Use proper HTML formatting with headings (h2, h3), paragraphs, lists, and emphasis where appropriate.',
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $headers = [
            'x-api-key'         => $this->api_key,
            'anthropic-version' => self::API_VERSION,
        ];

        $response = $this->make_request($url, $body, $headers);

        if (is_wp_error($response)) {
            return $response;
        }

        // Claude returns content in a different format
        $content = $response['content'] ?? [];

        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                return $block['text'];
            }
        }

        return '';
    }

    /**
     * Generate image (not supported)
     *
     * @param string $prompt Image description
     * @return WP_Error Always returns error
     */
    public function generate_image($prompt) {
        return new \WP_Error(
            'not_supported',
            'Anthropic Claude does not support image generation'
        );
    }
}
