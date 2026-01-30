<?php
/**
 * Google Gemini Provider Class
 *
 * @package AIAPG
 */

namespace AIAPG\AIProviders;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/abstract-ai-provider.php';

/**
 * Google Gemini API provider implementation
 */
class Gemini extends AbstractAIProvider {

    /**
     * API base URL
     */
    const API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Default model
     */
    const DEFAULT_MODEL = 'gemini-pro';

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
        return 'Google Gemini';
    }

    /**
     * Check if supports image generation
     *
     * @return bool
     */
    public function supports_image_generation() {
        return false; // Gemini text model doesn't generate images
    }

    /**
     * Generate text content
     *
     * @param string $prompt Prompt to send
     * @return string|WP_Error Generated text or error
     */
    public function generate_text($prompt) {
        $url = self::API_URL . '/models/' . $this->model . ':generateContent?key=' . $this->api_key;

        $system_instruction = 'You are a professional content writer. Write high-quality, SEO-optimized blog posts in the requested language. Use proper HTML formatting with headings (h2, h3), paragraphs, lists, and emphasis where appropriate.';

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_instruction . "\n\n" . $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 4096,
            ],
        ];

        // Gemini uses API key in URL, not header
        $response = $this->make_request($url, $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract text from Gemini response format
        $candidates = $response['candidates'] ?? [];

        if (!empty($candidates[0]['content']['parts'][0]['text'])) {
            return $candidates[0]['content']['parts'][0]['text'];
        }

        return '';
    }

    /**
     * Generate image (not supported by text model)
     *
     * @param string $prompt Image description
     * @return WP_Error Always returns error
     */
    public function generate_image($prompt) {
        return new \WP_Error(
            'not_supported',
            'Google Gemini text model does not support image generation'
        );
    }
}
