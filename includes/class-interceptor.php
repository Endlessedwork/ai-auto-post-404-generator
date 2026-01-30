<?php
/**
 * 404 Interceptor Class
 *
 * Intercepts 404 requests and generates content from URL keywords
 *
 * @package AIAPG
 */

namespace AIAPG;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles 404 interception and triggers content generation
 */
class Interceptor {

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
     * Initialize interceptor hooks
     */
    public function init() {
        // Hook into template_redirect to catch 404s early
        add_action('template_redirect', [$this, 'intercept_404'], 1);
    }

    /**
     * Intercept 404 requests
     */
    public function intercept_404() {
        // Reload settings fresh from database
        $this->settings = get_option('aiapg_settings', []);

        // Debug log
        error_log('AIAPG Debug: intercept_404 called');
        error_log('AIAPG Debug: plugin_enabled = ' . ($this->settings['plugin_enabled'] ?? 'not set'));

        // Check if plugin is enabled
        if (empty($this->settings['plugin_enabled'])) {
            error_log('AIAPG Debug: Plugin is disabled, skipping');
            return;
        }

        // Only process 404 pages
        if (!is_404()) {
            error_log('AIAPG Debug: Not a 404 page');
            return;
        }

        error_log('AIAPG Debug: Is 404 page');

        // Check if plugin is properly configured
        if (!$this->is_configured()) {
            error_log('AIAPG Debug: Plugin not configured');
            return;
        }

        error_log('AIAPG Debug: Plugin is configured');

        // Check rate limit
        if (!$this->check_rate_limit()) {
            error_log('AIAPG Debug: Rate limit exceeded');
            return;
        }

        // Get keyword from URL
        $keyword = $this->extract_keyword_from_url();
        error_log('AIAPG Debug: Extracted keyword = ' . $keyword);

        if (empty($keyword)) {
            error_log('AIAPG Debug: Empty keyword');
            return;
        }

        // Check if post with this slug already exists
        if ($this->post_exists($keyword)) {
            error_log('AIAPG Debug: Post already exists');
            return;
        }

        // Check if keyword is valid (not a file, not admin, etc.)
        if (!$this->is_valid_keyword($keyword)) {
            error_log('AIAPG Debug: Invalid keyword');
            if (function_exists('aiapg_debug_log')) {
                aiapg_debug_log(__FILE__ . ':intercept_404', 'invalid keyword', [
                    'keyword' => $keyword,
                ], 'H2');
            }
            return;
        }

        error_log('AIAPG Debug: Starting content generation for: ' . $keyword);

        if (function_exists('aiapg_debug_log')) {
            aiapg_debug_log(__FILE__ . ':intercept_404', 'start process_keyword', [
                'keyword' => $keyword,
            ], 'H2');
        }

        // Generate and create post
        $this->process_keyword($keyword);
    }

    /**
     * Check if plugin is properly configured
     *
     * @return bool
     */
    private function is_configured() {
        $provider = $this->settings['ai_provider'] ?? '';

        switch ($provider) {
            case 'openai':
                return !empty($this->settings['openai_api_key']);
            case 'anthropic':
                return !empty($this->settings['anthropic_api_key']);
            case 'gemini':
                return !empty($this->settings['gemini_api_key']);
            default:
                return false;
        }
    }

    /**
     * Check rate limit
     *
     * @return bool True if within limit
     */
    private function check_rate_limit() {
        $limit = $this->settings['rate_limit_per_day'] ?? 50;
        $today = date('Y-m-d');
        $count_key = 'aiapg_count_' . $today;

        $current_count = get_transient($count_key) ?: 0;

        if ($current_count >= $limit) {
            return false;
        }

        return true;
    }

    /**
     * Increment rate limit counter
     */
    private function increment_rate_limit() {
        $today = date('Y-m-d');
        $count_key = 'aiapg_count_' . $today;

        $current_count = get_transient($count_key) ?: 0;
        set_transient($count_key, $current_count + 1, DAY_IN_SECONDS);
    }

    /**
     * Extract keyword from URL
     *
     * @return string Extracted keyword
     */
    private function extract_keyword_from_url() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Remove query string
        $path = strtok($request_uri, '?');

        // Remove leading/trailing slashes
        $path = trim($path, '/');

        // Get the last segment (keyword)
        $segments = explode('/', $path);
        $keyword = end($segments);

        // Clean up the keyword
        $keyword = $this->clean_keyword($keyword);

        return $keyword;
    }

    /**
     * Clean and normalize keyword
     *
     * @param string $keyword Raw keyword
     * @return string Cleaned keyword
     */
    private function clean_keyword($keyword) {
        // URL decode
        $keyword = urldecode($keyword);

        // Replace hyphens/underscores with spaces
        $keyword = str_replace(['-', '_'], ' ', $keyword);

        // Remove code-like patterns (hex, base64, query strings, etc.)
        // Remove strings that look like encoded data
        $keyword = preg_replace('/[a-f0-9]{32,}/i', '', $keyword); // MD5, SHA hashes
        $keyword = preg_replace('/[A-Za-z0-9+\/=]{20,}/', '', $keyword); // Base64-like
        $keyword = preg_replace('/0x[a-f0-9]+/i', '', $keyword); // Hex values
        $keyword = preg_replace('/\\\\x[a-f0-9]{2}/i', '', $keyword); // Escaped hex
        $keyword = preg_replace('/%[0-9a-f]{2}/i', '', $keyword); // URL encoded chars
        $keyword = preg_replace('/&#?[a-z0-9]+;/i', '', $keyword); // HTML entities

        // Remove special characters, code symbols, brackets, etc.
        // Keep only: Thai, Latin letters, numbers, spaces
        $keyword = preg_replace('/[^\p{Thai}\p{Latin}\p{N}\s]/u', '', $keyword);

        // Remove standalone numbers (likely IDs or codes)
        $keyword = preg_replace('/\b\d{5,}\b/', '', $keyword); // Numbers 5+ digits

        // Trim and normalize spaces
        $keyword = preg_replace('/\s+/', ' ', trim($keyword));

        return $keyword;
    }

    /**
     * Check if post with slug exists
     *
     * @param string $keyword Keyword to check
     * @return bool
     */
    private function post_exists($keyword) {
        $slug = sanitize_title($keyword);

        $post = get_page_by_path($slug, OBJECT, 'post');

        return $post !== null;
    }

    /**
     * Validate keyword
     *
     * @param string $keyword Keyword to validate
     * @return bool
     */
    private function is_valid_keyword($keyword) {
        // Count actual letters (Thai + Latin only, not numbers or spaces)
        $letters_only = preg_replace('/[^\p{Thai}\p{Latin}]/u', '', $keyword);
        $letter_count = mb_strlen($letters_only);

        // Must have at least 3 letters
        if ($letter_count < 3) {
            error_log('AIAPG: Keyword has less than 3 letters: ' . $keyword . ' (letters: ' . $letter_count . ')');
            return false;
        }

        // Maximum length
        if (mb_strlen($keyword) > 200) {
            return false;
        }

        // Reject if mostly numbers (more numbers than letters = likely code/ID)
        $numbers_only = preg_replace('/[^0-9]/', '', $keyword);
        if (strlen($numbers_only) > $letter_count) {
            error_log('AIAPG: Keyword has more numbers than letters: ' . $keyword);
            return false;
        }

        // Reject random-looking strings (no vowels = likely code)
        $has_vowels = preg_match('/[aeiouAEIOUàáâãäåèéêëìíîïòóôõöùúûüаеёиоуыэюяəαεηιοωυ\p{Thai}]/u', $keyword);
        if (!$has_vowels && $letter_count > 5) {
            error_log('AIAPG: Keyword looks like random code (no vowels): ' . $keyword);
            return false;
        }

        // Check user-defined blocked keywords
        if ($this->is_blocked_keyword($keyword)) {
            error_log('AIAPG: Keyword blocked by user settings: ' . $keyword);
            return false;
        }

        // Check user-defined blocked URL patterns
        if ($this->is_blocked_pattern($keyword)) {
            error_log('AIAPG: Keyword blocked by URL pattern: ' . $keyword);
            return false;
        }

        // Blocked patterns (files, admin paths, etc.)
        $blocked_patterns = [
            '/\.(php|js|css|html|xml|json|txt|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot|map|min)$/i',
            '/^(wp-admin|wp-content|wp-includes|wp-json|xmlrpc|feed|author|tag|category|page|cron|ajax)$/i',
            '/^[0-9]+$/', // Numeric only
            '/^[a-f0-9]{8,}$/i', // Hex strings
            '/^\d+[a-z]+\d+$/i', // Mixed number-letter patterns like "123abc456"
        ];

        foreach ($blocked_patterns as $pattern) {
            if (preg_match($pattern, $keyword)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process keyword and generate post
     *
     * @param string $keyword Keyword to process
     */
    private function process_keyword($keyword) {
        // Store keyword in transient for AJAX processing
        $token = md5($keyword . time());
        set_transient('aiapg_pending_' . $token, $keyword, 300);

        // Show loading page with AJAX generation
        $this->show_loading_page_ajax($keyword, $token);
        exit;
    }

    /**
     * Show loading page with AJAX content generation
     *
     * @param string $keyword Keyword
     * @param string $token   Unique token
     */
    private function show_loading_page_ajax($keyword, $token) {
        $ajax_url = admin_url('admin-ajax.php');
        $post_url = home_url('/' . sanitize_title($keyword) . '/');
        $home_url = home_url('/');
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php esc_html_e('กรุณารอสักครู่...', 'ai-auto-post-generator'); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                }
                .container { text-align: center; padding: 40px; max-width: 500px; }
                .spinner {
                    width: 60px; height: 60px;
                    border: 4px solid rgba(255,255,255,0.3);
                    border-top-color: #fff;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 30px;
                }
                @keyframes spin { to { transform: rotate(360deg); } }
                h1 { font-size: 24px; margin-bottom: 15px; }
                p { font-size: 16px; opacity: 0.9; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="spinner"></div>
                <h1><?php esc_html_e('กรุณารอสักครู่...', 'ai-auto-post-generator'); ?></h1>
            </div>

            <script>
                var ajaxUrl = '<?php echo esc_url($ajax_url); ?>';
                var token = '<?php echo esc_attr($token); ?>';
                var postUrl = '<?php echo esc_url($post_url); ?>';
                var homeUrl = '<?php echo esc_url($home_url); ?>';

                // Start generation in background
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=aiapg_generate_post&token=' + token
                })
                .then(response => response.json())
                .then(data => {
                    // Generation complete - redirect handled by background process
                })
                .catch(error => {
                    // Silent fail - user already redirected
                });

                // Redirect to home immediately (don't wait for generation)
                setTimeout(function() {
                    window.location.href = homeUrl;
                }, 1500);
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Show loading page while generating
     *
     * @param string $keyword Current keyword
     */
    private function show_loading_page($keyword) {
        // For AJAX requests or if header already sent, skip loading page
        if (wp_doing_ajax() || headers_sent()) {
            return;
        }

        // Use output buffering for async generation
        if (ob_get_level() === 0) {
            ob_start();
        }

        // Include loading template if exists
        $template = AIAPG_PLUGIN_DIR . 'templates/loading-page.php';

        if (file_exists($template)) {
            // We'll handle this via JavaScript instead
            // to allow async content generation
        }
    }

    /**
     * Check if keyword matches any blocked keywords from settings
     *
     * @param string $keyword Keyword to check
     * @return bool True if keyword is blocked
     */
    private function is_blocked_keyword($keyword) {
        $blocked_keywords = $this->settings['blocked_keywords'] ?? '';

        if (empty($blocked_keywords)) {
            return false;
        }

        // Split by newlines and filter empty lines
        $blocked_list = array_filter(array_map('trim', explode("\n", $blocked_keywords)));

        if (empty($blocked_list)) {
            return false;
        }

        // Convert keyword to lowercase for case-insensitive matching
        $keyword_lower = mb_strtolower($keyword, 'UTF-8');

        foreach ($blocked_list as $blocked) {
            $blocked_lower = mb_strtolower(trim($blocked), 'UTF-8');

            if (empty($blocked_lower)) {
                continue;
            }

            // Check if keyword contains the blocked word (partial match)
            if (mb_strpos($keyword_lower, $blocked_lower) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if keyword matches any blocked URL patterns from settings
     *
     * @param string $keyword Keyword to check
     * @return bool True if keyword matches a blocked pattern
     */
    private function is_blocked_pattern($keyword) {
        $blocked_patterns = $this->settings['blocked_patterns'] ?? '';

        if (empty($blocked_patterns)) {
            return false;
        }

        // Split by newlines and filter empty lines
        $pattern_list = array_filter(array_map('trim', explode("\n", $blocked_patterns)));

        if (empty($pattern_list)) {
            return false;
        }

        // Convert keyword to lowercase for case-insensitive matching
        $keyword_lower = mb_strtolower($keyword, 'UTF-8');

        foreach ($pattern_list as $pattern) {
            $pattern = trim($pattern);

            if (empty($pattern)) {
                continue;
            }

            // Convert wildcard pattern to regex
            // * matches any characters, ? matches single character
            $regex_pattern = preg_quote(mb_strtolower($pattern, 'UTF-8'), '/');
            $regex_pattern = str_replace('\*', '.*', $regex_pattern);
            $regex_pattern = str_replace('\?', '.', $regex_pattern);
            $regex_pattern = '/^' . $regex_pattern . '$/u';

            if (preg_match($regex_pattern, $keyword_lower)) {
                return true;
            }
        }

        return false;
    }
}
