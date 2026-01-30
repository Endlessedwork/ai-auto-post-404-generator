<?php
/**
 * OpenAI Provider Class
 *
 * @package AIAPG
 */

namespace AIAPG\AIProviders;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/abstract-ai-provider.php';

/**
 * OpenAI API provider implementation
 */
class OpenAI extends AbstractAIProvider {

    /**
     * API base URL
     */
    const API_URL = 'https://api.openai.com/v1';

    /**
     * Default model
     */
    const DEFAULT_MODEL = 'gpt-4';

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
        return 'OpenAI';
    }

    /**
     * Check if supports image generation
     *
     * @return bool
     */
    public function supports_image_generation() {
        return true;
    }

    /**
     * Generate text content
     *
     * @param string $prompt Prompt to send
     * @return string|WP_Error Generated text or error
     */
    public function generate_text($prompt) {
        $url = self::API_URL . '/chat/completions';

        $body = [
            'model'    => $this->model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You are a professional content writer. Write high-quality, SEO-optimized blog posts in the requested language. Use proper HTML formatting with headings (h2, h3), paragraphs, lists, and emphasis where appropriate.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens'  => 4000,
            'temperature' => 0.7,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        $response = $this->make_request($url, $body, $headers);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Generate image using DALL-E
     *
     * @param string $prompt Image description
     * @return string|WP_Error Image URL or error
     */
    public function generate_image($prompt) {
        $url = self::API_URL . '/images/generations';

        $body = [
            'model'           => 'dall-e-3',
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => '1792x1024',
            'quality'         => 'standard',
            'response_format' => 'url',
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        $response = $this->make_request($url, $body, $headers);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['data'][0]['url'] ?? '';
    }
}
