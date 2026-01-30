<?php
/**
 * Admin Settings Class
 *
 * Handles the plugin settings page in WordPress admin
 *
 * @package AIAPG
 */

namespace AIAPG;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings page handler
 */
class AdminSettings {

    /**
     * Option name
     */
    const OPTION_NAME = 'aiapg_settings';

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'ai-auto-post-generator';

    /**
     * Initialize admin settings
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_aiapg_test_api', [$this, 'ajax_test_api']);
        add_action('wp_ajax_aiapg_test_intercept', [$this, 'ajax_test_intercept']);
    }

    /**
     * Add settings menu page
     */
    public function add_menu_page() {
        add_options_page(
            __('AI Auto Post Generator', 'ai-auto-post-generator'),
            __('AI Auto Post', 'ai-auto-post-generator'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'aiapg_settings_group',
            self::OPTION_NAME,
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Sanitize settings before save
     *
     * @param array $input Raw input
     * @return array Sanitized input
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Plugin enabled/disabled
        $sanitized['plugin_enabled'] = !empty($input['plugin_enabled']);

        // AI Provider
        $sanitized['ai_provider'] = sanitize_text_field($input['ai_provider'] ?? 'openai');

        // API Keys (encrypt in production)
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
        $sanitized['anthropic_api_key'] = sanitize_text_field($input['anthropic_api_key'] ?? '');
        $sanitized['gemini_api_key'] = sanitize_text_field($input['gemini_api_key'] ?? '');

        // Models
        $sanitized['openai_model'] = sanitize_text_field($input['openai_model'] ?? 'gpt-4');
        $sanitized['anthropic_model'] = sanitize_text_field($input['anthropic_model'] ?? 'claude-3-sonnet-20240229');
        $sanitized['gemini_model'] = sanitize_text_field($input['gemini_model'] ?? 'gemini-pro');

        // Post settings
        $sanitized['post_status'] = sanitize_text_field($input['post_status'] ?? 'publish');
        $sanitized['post_author'] = absint($input['post_author'] ?? 1);
        $sanitized['default_category'] = absint($input['default_category'] ?? 1);

        // Features
        $sanitized['enable_featured_image'] = !empty($input['enable_featured_image']);
        $sanitized['enable_seo'] = !empty($input['enable_seo']);

        // Content settings
        $sanitized['content_language'] = sanitize_text_field($input['content_language'] ?? 'th');
        $sanitized['min_word_count'] = absint($input['min_word_count'] ?? 500);

        // Custom prompt settings
        $sanitized['use_custom_prompt'] = !empty($input['use_custom_prompt']);
        $sanitized['custom_prompt'] = wp_kses_post($input['custom_prompt'] ?? '');

        // Image settings
        $sanitized['image_style'] = sanitize_text_field($input['image_style'] ?? 'professional');

        // Internal linking settings
        $sanitized['enable_internal_links'] = !empty($input['enable_internal_links']);
        $sanitized['max_internal_links'] = absint($input['max_internal_links'] ?? 5);
        $sanitized['internal_links_new_tab'] = !empty($input['internal_links_new_tab']);
        $sanitized['custom_link_keywords'] = sanitize_textarea_field($input['custom_link_keywords'] ?? '');

        // Blocked keywords (sanitize each line)
        $sanitized['blocked_keywords'] = sanitize_textarea_field($input['blocked_keywords'] ?? '');
        $sanitized['blocked_patterns'] = sanitize_textarea_field($input['blocked_patterns'] ?? '');

        // Rate limiting
        $sanitized['rate_limit_per_day'] = absint($input['rate_limit_per_day'] ?? 50);

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option(self::OPTION_NAME, []);

        include AIAPG_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('aiapg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(['message' => 'Missing provider or API key']);
        }

        require_once AIAPG_PLUGIN_DIR . 'includes/ai-providers/class-openai.php';
        require_once AIAPG_PLUGIN_DIR . 'includes/ai-providers/class-anthropic.php';
        require_once AIAPG_PLUGIN_DIR . 'includes/ai-providers/class-gemini.php';

        try {
            switch ($provider) {
                case 'openai':
                    $ai = new AIProviders\OpenAI($api_key);
                    break;
                case 'anthropic':
                    $ai = new AIProviders\Anthropic($api_key);
                    break;
                case 'gemini':
                    $ai = new AIProviders\Gemini($api_key);
                    break;
                default:
                    wp_send_json_error(['message' => 'Invalid provider']);
                    return;
            }

            $result = $ai->generate_text('Say "API connection successful!" in exactly 5 words.');

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => 'Connection successful!', 'response' => substr($result, 0, 100)]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for testing intercept simulation
     */
    public function ajax_test_intercept() {
        check_ajax_referer('aiapg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $keyword = sanitize_text_field($_POST['keyword'] ?? 'test-keyword');
        $settings = get_option(self::OPTION_NAME, []);

        $debug = [];

        // Check 1: Plugin enabled
        $debug[] = [
            'check' => 'Plugin Enabled',
            'status' => !empty($settings['plugin_enabled']),
            'value' => $settings['plugin_enabled'] ?? false,
        ];

        // Check 2: API configured
        $provider = $settings['ai_provider'] ?? 'openai';
        $api_key = '';
        switch ($provider) {
            case 'openai':
                $api_key = $settings['openai_api_key'] ?? '';
                break;
            case 'anthropic':
                $api_key = $settings['anthropic_api_key'] ?? '';
                break;
            case 'gemini':
                $api_key = $settings['gemini_api_key'] ?? '';
                break;
        }
        $debug[] = [
            'check' => 'API Key Configured (' . $provider . ')',
            'status' => !empty($api_key),
            'value' => !empty($api_key) ? 'Yes (hidden)' : 'No',
        ];

        // Check 3: Rate limit
        $today = date('Y-m-d');
        $count_key = 'aiapg_count_' . $today;
        $current_count = get_transient($count_key) ?: 0;
        $limit = $settings['rate_limit_per_day'] ?? 50;
        $debug[] = [
            'check' => 'Rate Limit',
            'status' => $current_count < $limit,
            'value' => $current_count . ' / ' . $limit,
        ];

        // Check 4: Keyword validation
        $keyword_clean = $this->clean_keyword_for_test($keyword);
        $keyword_valid = mb_strlen($keyword_clean) >= 3 && mb_strlen($keyword_clean) <= 200;
        $debug[] = [
            'check' => 'Keyword Valid',
            'status' => $keyword_valid,
            'value' => '"' . $keyword_clean . '" (length: ' . mb_strlen($keyword_clean) . ')',
        ];

        // Check 5: Post exists
        $slug = sanitize_title($keyword_clean);
        $existing_post = get_page_by_path($slug, OBJECT, 'post');
        $debug[] = [
            'check' => 'Post Does Not Exist',
            'status' => $existing_post === null,
            'value' => $existing_post ? 'Post ID: ' . $existing_post->ID : 'No existing post',
        ];

        // Overall status
        $all_passed = true;
        foreach ($debug as $item) {
            if (!$item['status']) {
                $all_passed = false;
                break;
            }
        }

        wp_send_json_success([
            'all_passed' => $all_passed,
            'debug' => $debug,
            'test_url' => home_url('/' . $keyword),
        ]);
    }

    /**
     * Clean keyword for testing
     */
    private function clean_keyword_for_test($keyword) {
        $keyword = urldecode($keyword);
        $keyword = str_replace(['-', '_'], ' ', $keyword);
        $keyword = preg_replace('/[^\p{Thai}\p{Latin}\p{N}\s]/u', '', $keyword);
        $keyword = preg_replace('/\s+/', ' ', trim($keyword));
        return $keyword;
    }

    /**
     * Get default content prompt
     *
     * @return string Default prompt template
     */
    public function get_default_prompt() {
        return <<<'PROMPT'
You are an expert SEO content writer. Write a comprehensive, fully SEO-optimized blog post about "{keyword}" in {language}.

## SEO REQUIREMENTS (MUST FOLLOW):

### 1. KEYWORD OPTIMIZATION
- Primary keyword "{keyword}" must appear in:
  - First paragraph (within first 100 words)
  - At least 2-3 H2 headings
  - Naturally throughout content (keyword density 1-2%)
  - Last paragraph/conclusion
- Include LSI keywords (related terms) naturally
- Use keyword variations and synonyms

### 2. CONTENT STRUCTURE (Critical for SEO)
- Start with a compelling hook that includes the keyword
- Use Table of Contents friendly structure:
  - H2 for main sections (5-8 sections minimum)
  - H3 for subsections under each H2
- Include these section types:
  - "What is [keyword]" or definition section
  - "Benefits/Advantages" section
  - "How to" or step-by-step guide
  - "Tips" or "Best Practices" section
  - FAQ section with 3-5 common questions
  - Conclusion with call-to-action

### 3. READABILITY & ENGAGEMENT
- Short paragraphs (2-3 sentences max)
- Use bullet points and numbered lists frequently
- Include statistics or data points (use realistic examples)
- Add transition words between sections
- Flesch reading score target: 60-70 (easy to read)

### 4. E-E-A-T SIGNALS (Experience, Expertise, Authority, Trust)
- Write from expert perspective
- Include specific examples and case studies
- Mention best practices from the industry
- Add cautionary notes where appropriate

### 5. CONTENT LENGTH & DEPTH
- Minimum {min_words} words
- Cover topic comprehensively
- Answer user intent completely

### 6. HTML FORMAT
- Use semantic HTML: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>
- Use <strong> for important keywords (but don't overdo)
- Do NOT include <html>, <head>, <body>, or <h1> tags
- Do NOT include the main title

### 7. FAQ SECTION FORMAT
Use this exact format for FAQ section:
<h2>คำถามที่พบบ่อย (FAQ)</h2>
<h3>Q: [Question about {keyword}]?</h3>
<p>A: [Detailed answer]</p>

Write the complete SEO-optimized content now:
PROMPT;
    }
}
