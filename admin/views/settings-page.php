<?php
/**
 * Settings Page Template
 *
 * @package AIAPG
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get all users for author dropdown
$users = get_users(['role__in' => ['administrator', 'editor', 'author']]);

// Get all categories
$categories = get_categories(['hide_empty' => false]);

// Get rate limit count for today
$today = date('Y-m-d');
$today_count = get_transient('aiapg_count_' . $today) ?: 0;
?>

<div class="wrap aiapg-settings">
    <h1><?php esc_html_e('AI Auto Post Generator Settings', 'ai-auto-post-generator'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('aiapg_settings_group'); ?>

        <!-- Master Switch -->
        <div class="aiapg-master-switch <?php echo !empty($settings['plugin_enabled']) ? 'enabled' : 'disabled'; ?>">
            <div class="aiapg-switch-content">
                <div class="aiapg-switch-info">
                    <h3><?php esc_html_e('Auto Post Generation', 'ai-auto-post-generator'); ?></h3>
                    <p><?php esc_html_e('Enable or disable automatic post generation from 404 URLs', 'ai-auto-post-generator'); ?></p>
                </div>
                <label class="aiapg-toggle">
                    <input type="checkbox" name="aiapg_settings[plugin_enabled]" value="1"
                        <?php checked(!empty($settings['plugin_enabled'])); ?>>
                    <span class="aiapg-toggle-slider"></span>
                    <span class="aiapg-toggle-label">
                        <?php echo !empty($settings['plugin_enabled']) ? esc_html__('ON', 'ai-auto-post-generator') : esc_html__('OFF', 'ai-auto-post-generator'); ?>
                    </span>
                </label>
            </div>
        </div>

        <!-- Debug Test Box -->
        <div class="aiapg-section aiapg-debug-section">
            <h2><?php esc_html_e('Test & Debug', 'ai-auto-post-generator'); ?></h2>
            <p><?php esc_html_e('Test if the plugin will intercept a 404 URL and generate content.', 'ai-auto-post-generator'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_keyword"><?php esc_html_e('Test Keyword', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="test_keyword" value="how-to-make-coffee" class="regular-text">
                        <button type="button" class="button button-primary" id="aiapg-run-test">
                            <?php esc_html_e('Run Test', 'ai-auto-post-generator'); ?>
                        </button>
                    </td>
                </tr>
            </table>

            <div id="aiapg-test-results" style="display:none; margin-top: 15px;">
                <h4><?php esc_html_e('Test Results:', 'ai-auto-post-generator'); ?></h4>
                <table class="widefat" id="aiapg-results-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Check', 'ai-auto-post-generator'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-auto-post-generator'); ?></th>
                            <th><?php esc_html_e('Value', 'ai-auto-post-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <p id="aiapg-test-url" style="margin-top: 10px;"></p>
            </div>
        </div>

        <!-- Stats Box -->
        <div class="aiapg-stats-box">
            <h3><?php esc_html_e('Today\'s Statistics', 'ai-auto-post-generator'); ?></h3>
            <p>
                <strong><?php esc_html_e('Posts Generated:', 'ai-auto-post-generator'); ?></strong>
                <?php echo esc_html($today_count); ?> / <?php echo esc_html($settings['rate_limit_per_day'] ?? 50); ?>
            </p>
        </div>

        <!-- AI Provider Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('AI Provider Settings', 'ai-auto-post-generator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ai_provider"><?php esc_html_e('Default AI Provider', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <select name="aiapg_settings[ai_provider]" id="ai_provider">
                            <option value="openai" <?php selected($settings['ai_provider'] ?? '', 'openai'); ?>>OpenAI (GPT-4)</option>
                            <option value="anthropic" <?php selected($settings['ai_provider'] ?? '', 'anthropic'); ?>>Anthropic (Claude)</option>
                            <option value="gemini" <?php selected($settings['ai_provider'] ?? '', 'gemini'); ?>>Google (Gemini)</option>
                        </select>
                        <p class="description"><?php esc_html_e('Select the default AI provider for content generation.', 'ai-auto-post-generator'); ?></p>
                    </td>
                </tr>
            </table>

            <!-- OpenAI Settings -->
            <div class="aiapg-provider-settings" data-provider="openai">
                <h3>OpenAI Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php esc_html_e('API Key', 'ai-auto-post-generator'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="aiapg_settings[openai_api_key]" id="openai_api_key"
                                   value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>"
                                   class="regular-text">
                            <button type="button" class="button aiapg-test-api" data-provider="openai">
                                <?php esc_html_e('Test Connection', 'ai-auto-post-generator'); ?>
                            </button>
                            <span class="aiapg-test-result"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="openai_model"><?php esc_html_e('Model', 'ai-auto-post-generator'); ?></label>
                        </th>
                        <td>
                            <select name="aiapg_settings[openai_model]" id="openai_model">
                                <option value="gpt-4" <?php selected($settings['openai_model'] ?? '', 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-4-turbo" <?php selected($settings['openai_model'] ?? '', 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-4o" <?php selected($settings['openai_model'] ?? '', 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'] ?? '', 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Anthropic Settings -->
            <div class="aiapg-provider-settings" data-provider="anthropic">
                <h3>Anthropic Claude Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="anthropic_api_key"><?php esc_html_e('API Key', 'ai-auto-post-generator'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="aiapg_settings[anthropic_api_key]" id="anthropic_api_key"
                                   value="<?php echo esc_attr($settings['anthropic_api_key'] ?? ''); ?>"
                                   class="regular-text">
                            <button type="button" class="button aiapg-test-api" data-provider="anthropic">
                                <?php esc_html_e('Test Connection', 'ai-auto-post-generator'); ?>
                            </button>
                            <span class="aiapg-test-result"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="anthropic_model"><?php esc_html_e('Model', 'ai-auto-post-generator'); ?></label>
                        </th>
                        <td>
                            <select name="aiapg_settings[anthropic_model]" id="anthropic_model">
                                <option value="claude-3-opus-20240229" <?php selected($settings['anthropic_model'] ?? '', 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
                                <option value="claude-3-sonnet-20240229" <?php selected($settings['anthropic_model'] ?? '', 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet</option>
                                <option value="claude-3-haiku-20240307" <?php selected($settings['anthropic_model'] ?? '', 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Gemini Settings -->
            <div class="aiapg-provider-settings" data-provider="gemini">
                <h3>Google Gemini Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gemini_api_key"><?php esc_html_e('API Key', 'ai-auto-post-generator'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="aiapg_settings[gemini_api_key]" id="gemini_api_key"
                                   value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>"
                                   class="regular-text">
                            <button type="button" class="button aiapg-test-api" data-provider="gemini">
                                <?php esc_html_e('Test Connection', 'ai-auto-post-generator'); ?>
                            </button>
                            <span class="aiapg-test-result"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gemini_model"><?php esc_html_e('Model', 'ai-auto-post-generator'); ?></label>
                        </th>
                        <td>
                            <select name="aiapg_settings[gemini_model]" id="gemini_model">
                                <option value="gemini-pro" <?php selected($settings['gemini_model'] ?? '', 'gemini-pro'); ?>>Gemini Pro</option>
                                <option value="gemini-1.5-pro" <?php selected($settings['gemini_model'] ?? '', 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Post Settings Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Post Settings', 'ai-auto-post-generator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post_status"><?php esc_html_e('Post Status', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <select name="aiapg_settings[post_status]" id="post_status">
                            <option value="publish" <?php selected($settings['post_status'] ?? '', 'publish'); ?>><?php esc_html_e('Publish Immediately', 'ai-auto-post-generator'); ?></option>
                            <option value="draft" <?php selected($settings['post_status'] ?? '', 'draft'); ?>><?php esc_html_e('Save as Draft', 'ai-auto-post-generator'); ?></option>
                            <option value="pending" <?php selected($settings['post_status'] ?? '', 'pending'); ?>><?php esc_html_e('Pending Review', 'ai-auto-post-generator'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="post_author"><?php esc_html_e('Post Author', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <select name="aiapg_settings[post_author]" id="post_author">
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($settings['post_author'] ?? 1, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_category"><?php esc_html_e('Default Category', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <select name="aiapg_settings[default_category]" id="default_category">
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($settings['default_category'] ?? 1, $category->term_id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Content Settings Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Content Settings', 'ai-auto-post-generator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="content_language"><?php esc_html_e('Content Language', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <select name="aiapg_settings[content_language]" id="content_language">
                            <option value="th" <?php selected($settings['content_language'] ?? '', 'th'); ?>>ไทย (Thai)</option>
                            <option value="en" <?php selected($settings['content_language'] ?? '', 'en'); ?>>English</option>
                            <option value="zh" <?php selected($settings['content_language'] ?? '', 'zh'); ?>>中文 (Chinese)</option>
                            <option value="ja" <?php selected($settings['content_language'] ?? '', 'ja'); ?>>日本語 (Japanese)</option>
                            <option value="ko" <?php selected($settings['content_language'] ?? '', 'ko'); ?>>한국어 (Korean)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="min_word_count"><?php esc_html_e('Minimum Word Count', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aiapg_settings[min_word_count]" id="min_word_count"
                               value="<?php echo esc_attr($settings['min_word_count'] ?? 500); ?>"
                               min="100" max="5000" step="100">
                        <p class="description"><?php esc_html_e('Minimum number of words for generated content.', 'ai-auto-post-generator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Custom Prompt Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Custom Content Prompt', 'ai-auto-post-generator'); ?></h2>
            <p class="description"><?php esc_html_e('Customize the AI prompt used for content generation. Use placeholders: {keyword}, {language}, {min_words}', 'ai-auto-post-generator'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Use Custom Prompt', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="aiapg_settings[use_custom_prompt]" id="use_custom_prompt" value="1"
                                <?php checked(!empty($settings['use_custom_prompt'])); ?>>
                            <?php esc_html_e('Enable custom prompt (otherwise use default)', 'ai-auto-post-generator'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="custom_prompt"><?php esc_html_e('Content Prompt', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <textarea name="aiapg_settings[custom_prompt]" id="custom_prompt"
                                  rows="20" class="large-text code"
                                  placeholder="<?php esc_attr_e('Enter your custom prompt here...', 'ai-auto-post-generator'); ?>"><?php echo esc_textarea($settings['custom_prompt'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Available placeholders:', 'ai-auto-post-generator'); ?><br>
                            • <code>{keyword}</code> - <?php esc_html_e('The keyword from URL', 'ai-auto-post-generator'); ?><br>
                            • <code>{language}</code> - <?php esc_html_e('Content language name (e.g., Thai, English)', 'ai-auto-post-generator'); ?><br>
                            • <code>{min_words}</code> - <?php esc_html_e('Minimum word count setting', 'ai-auto-post-generator'); ?>
                        </p>
                        <p style="margin-top: 10px;">
                            <button type="button" class="button" id="aiapg-show-default-prompt">
                                <?php esc_html_e('Show Default Prompt', 'ai-auto-post-generator'); ?>
                            </button>
                            <button type="button" class="button" id="aiapg-reset-prompt">
                                <?php esc_html_e('Reset to Default', 'ai-auto-post-generator'); ?>
                            </button>
                        </p>
                    </td>
                </tr>
            </table>

            <!-- Default Prompt Reference (hidden by default) -->
            <div id="aiapg-default-prompt-box" style="display: none; margin-top: 15px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;"><?php esc_html_e('Default Prompt:', 'ai-auto-post-generator'); ?></h4>
                <pre style="white-space: pre-wrap; font-size: 12px; max-height: 400px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ccc;"><?php echo esc_html($this->get_default_prompt()); ?></pre>
            </div>
        </div>

        <!-- Internal Linking Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Internal Linking', 'ai-auto-post-generator'); ?></h2>
            <p class="description"><?php esc_html_e('Automatically add internal links to related keywords in the content.', 'ai-auto-post-generator'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Internal Links', 'ai-auto-post-generator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aiapg_settings[enable_internal_links]" id="enable_internal_links" value="1"
                                <?php checked(!empty($settings['enable_internal_links'])); ?>>
                            <?php esc_html_e('Automatically add internal links to related keywords', 'ai-auto-post-generator'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Links will point to: yoursite.com/keyword-slug', 'ai-auto-post-generator'); ?>
                        </p>
                    </td>
                </tr>
                <tr class="aiapg-linking-settings" <?php echo empty($settings['enable_internal_links']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="max_internal_links"><?php esc_html_e('Max Links Per Post', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aiapg_settings[max_internal_links]" id="max_internal_links"
                               value="<?php echo esc_attr($settings['max_internal_links'] ?? 5); ?>"
                               min="1" max="20" style="width: 80px;">
                        <p class="description"><?php esc_html_e('Maximum number of internal links to add per post.', 'ai-auto-post-generator'); ?></p>
                    </td>
                </tr>
                <tr class="aiapg-linking-settings" <?php echo empty($settings['enable_internal_links']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><?php esc_html_e('Link Target', 'ai-auto-post-generator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aiapg_settings[internal_links_new_tab]" value="1"
                                <?php checked(!empty($settings['internal_links_new_tab'])); ?>>
                            <?php esc_html_e('Open links in new tab', 'ai-auto-post-generator'); ?>
                        </label>
                    </td>
                </tr>
                <tr class="aiapg-linking-settings" <?php echo empty($settings['enable_internal_links']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="custom_link_keywords"><?php esc_html_e('Custom Link Keywords', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <textarea name="aiapg_settings[custom_link_keywords]" id="custom_link_keywords"
                                  rows="6" class="large-text code"
                                  placeholder="keyword1&#10;keyword2&#10;important topic"><?php echo esc_textarea($settings['custom_link_keywords'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Additional keywords to link (one per line). These will also be linked when found in content.', 'ai-auto-post-generator'); ?><br>
                            <?php esc_html_e('Leave empty to only use AI-suggested related keywords.', 'ai-auto-post-generator'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Blocked Keywords Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Blocked Keywords', 'ai-auto-post-generator'); ?></h2>
            <p class="description"><?php esc_html_e('Keywords or phrases that will NOT trigger article generation. One per line.', 'ai-auto-post-generator'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="blocked_keywords"><?php esc_html_e('Blocked Keywords', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <textarea name="aiapg_settings[blocked_keywords]" id="blocked_keywords"
                                  rows="8" class="large-text code"
                                  placeholder="casino&#10;gambling&#10;porn&#10;xxx"><?php echo esc_textarea($settings['blocked_keywords'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Enter keywords to block (one per line). Supports:', 'ai-auto-post-generator'); ?><br>
                            • <?php esc_html_e('Exact match: "casino" blocks "casino"', 'ai-auto-post-generator'); ?><br>
                            • <?php esc_html_e('Partial match: "porn" blocks "pornography", "porn-site"', 'ai-auto-post-generator'); ?><br>
                            • <?php esc_html_e('Thai keywords supported', 'ai-auto-post-generator'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="blocked_patterns"><?php esc_html_e('Blocked URL Patterns', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <textarea name="aiapg_settings[blocked_patterns]" id="blocked_patterns"
                                  rows="5" class="large-text code"
                                  placeholder="wp-*&#10;admin*&#10;*login*"><?php echo esc_textarea($settings['blocked_patterns'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('URL patterns to block (one per line). Use * as wildcard:', 'ai-auto-post-generator'); ?><br>
                            • <?php esc_html_e('"wp-*" blocks wp-admin, wp-login, etc.', 'ai-auto-post-generator'); ?><br>
                            • <?php esc_html_e('"*login*" blocks any URL containing "login"', 'ai-auto-post-generator'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Features Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Features', 'ai-auto-post-generator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('AI Image Generation', 'ai-auto-post-generator'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="aiapg_settings[enable_featured_image]" id="enable_featured_image" value="1"
                                    <?php checked(!empty($settings['enable_featured_image'])); ?>>
                                <?php esc_html_e('Enable AI featured image generation', 'ai-auto-post-generator'); ?>
                            </label>
                            <p class="description" style="margin-top: 8px;">
                                <?php esc_html_e('Requires OpenAI API key for DALL-E image generation.', 'ai-auto-post-generator'); ?>
                                <?php if (empty($settings['openai_api_key'])) : ?>
                                    <br><span style="color: #d63638;">⚠️ <?php esc_html_e('OpenAI API key not configured.', 'ai-auto-post-generator'); ?></span>
                                <?php endif; ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr class="aiapg-image-settings" <?php echo empty($settings['enable_featured_image']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="image_style"><?php esc_html_e('Image Style', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <select name="aiapg_settings[image_style]" id="image_style">
                            <option value="professional" <?php selected($settings['image_style'] ?? '', 'professional'); ?>><?php esc_html_e('Professional / Clean', 'ai-auto-post-generator'); ?></option>
                            <option value="illustration" <?php selected($settings['image_style'] ?? '', 'illustration'); ?>><?php esc_html_e('Illustration / Artistic', 'ai-auto-post-generator'); ?></option>
                            <option value="realistic" <?php selected($settings['image_style'] ?? '', 'realistic'); ?>><?php esc_html_e('Realistic / Photo-like', 'ai-auto-post-generator'); ?></option>
                            <option value="minimalist" <?php selected($settings['image_style'] ?? '', 'minimalist'); ?>><?php esc_html_e('Minimalist / Simple', 'ai-auto-post-generator'); ?></option>
                            <option value="vibrant" <?php selected($settings['image_style'] ?? '', 'vibrant'); ?>><?php esc_html_e('Vibrant / Colorful', 'ai-auto-post-generator'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('SEO Optimization', 'ai-auto-post-generator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aiapg_settings[enable_seo]" value="1"
                                <?php checked(!empty($settings['enable_seo'])); ?>>
                            <?php esc_html_e('Generate SEO meta title and description', 'ai-auto-post-generator'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Compatible with Yoast SEO, Rank Math, and All in One SEO', 'ai-auto-post-generator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Rate Limiting Section -->
        <div class="aiapg-section">
            <h2><?php esc_html_e('Rate Limiting', 'ai-auto-post-generator'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rate_limit_per_day"><?php esc_html_e('Max Posts Per Day', 'ai-auto-post-generator'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aiapg_settings[rate_limit_per_day]" id="rate_limit_per_day"
                               value="<?php echo esc_attr($settings['rate_limit_per_day'] ?? 50); ?>"
                               min="1" max="1000">
                        <p class="description"><?php esc_html_e('Maximum number of posts that can be auto-generated per day. Helps control API costs.', 'ai-auto-post-generator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
