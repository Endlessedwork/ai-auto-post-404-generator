<?php
/**
 * Abstract AI Provider Class
 *
 * Base class for all AI providers
 *
 * @package AIAPG
 */

namespace AIAPG\AIProviders;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for AI providers
 */
abstract class AbstractAIProvider {

    /**
     * API Key
     *
     * @var string
     */
    protected $api_key;

    /**
     * Model to use
     *
     * @var string
     */
    protected $model;

    /**
     * Constructor
     *
     * @param string $api_key API key
     * @param string $model   Model name
     */
    public function __construct($api_key, $model = '') {
        $this->api_key = $api_key;
        $this->model = $model;
    }

    /**
     * Generate text content
     *
     * @param string $prompt Prompt to send
     * @return string|WP_Error Generated text or error
     */
    abstract public function generate_text($prompt);

    /**
     * Generate image
     *
     * @param string $prompt Image description prompt
     * @return string|WP_Error Image URL or error
     */
    abstract public function generate_image($prompt);

    /**
     * Make HTTP request
     *
     * @param string $url     API URL
     * @param array  $body    Request body
     * @param array  $headers Request headers
     * @return array|WP_Error Response or error
     */
    protected function make_request($url, $body, $headers = []) {
        $default_headers = [
            'Content-Type' => 'application/json',
        ];

        $headers = array_merge($default_headers, $headers);

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 120, // AI requests can be slow
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? 'Unknown API error';
            return new \WP_Error('api_error', $error_message, ['status' => $status_code]);
        }

        return $data;
    }

    /**
     * Get provider name
     *
     * @return string
     */
    abstract public function get_name();

    /**
     * Check if provider supports image generation
     *
     * @return bool
     */
    public function supports_image_generation() {
        return false;
    }
}
