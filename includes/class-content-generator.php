<?php
/**
 * Content Generator Class
 *
 * Generates blog post content using AI providers
 *
 * @package AIAPG
 */

namespace AIAPG;

use AIAPG\AIProviders\OpenAI;
use AIAPG\AIProviders\Anthropic;
use AIAPG\AIProviders\Gemini;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles content generation using AI
 */
class ContentGenerator {

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * AI provider instance
     *
     * @var AbstractAIProvider
     */
    private $provider;

    /**
     * OpenAI instance for images (fallback)
     *
     * @var OpenAI|null
     */
    private $openai_for_images;

    /**
     * Constructor
     *
     * @param array $settings Plugin settings
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init_provider();
    }

    /**
     * Initialize AI provider based on settings
     */
    private function init_provider() {
        require_once AIAPG_PLUGIN_DIR . 'includes/ai-providers/class-openai.php';
        require_once AIAPG_PLUGIN_DIR . 'includes/ai-providers/class-anthropic.php';
        require_once AIAPG_PLUGIN_DIR . 'includes/ai-providers/class-gemini.php';

        $provider_type = $this->settings['ai_provider'] ?? 'openai';

        switch ($provider_type) {
            case 'anthropic':
                $this->provider = new Anthropic(
                    $this->settings['anthropic_api_key'] ?? '',
                    $this->settings['anthropic_model'] ?? ''
                );
                break;

            case 'gemini':
                $this->provider = new Gemini(
                    $this->settings['gemini_api_key'] ?? '',
                    $this->settings['gemini_model'] ?? ''
                );
                break;

            case 'openai':
            default:
                $this->provider = new OpenAI(
                    $this->settings['openai_api_key'] ?? '',
                    $this->settings['openai_model'] ?? ''
                );
                break;
        }

        // Initialize OpenAI for image generation if needed
        if (!$this->provider->supports_image_generation() && !empty($this->settings['openai_api_key'])) {
            $this->openai_for_images = new OpenAI($this->settings['openai_api_key']);
        }
    }

    /**
     * Generate complete content for a keyword
     *
     * @param string $keyword Keyword to generate content for
     * @return array|WP_Error Content array or error
     */
    public function generate($keyword) {
        $language = $this->settings['content_language'] ?? 'th';
        $min_words = $this->settings['min_word_count'] ?? 500;

        // Build content generation prompt
        $prompt = $this->build_content_prompt($keyword, $language, $min_words);

        // Generate main content
        $content = $this->provider->generate_text($prompt);

        if (is_wp_error($content)) {
            return $content;
        }

        // Prepare result array
        $result = [
            'content'     => $content,
            'title'       => '',
            'excerpt'     => '',
            'meta_title'  => '',
            'meta_desc'   => '',
            'image_url'   => '',
        ];

        // Generate SEO metadata if enabled
        if (!empty($this->settings['enable_seo'])) {
            $seo_data = $this->generate_seo_data($keyword, $content, $language);
            if (!is_wp_error($seo_data)) {
                $result = array_merge($result, $seo_data);
            }
        } else {
            // Generate basic title
            $result['title'] = $this->generate_title($keyword, $language);
        }

        // Generate featured image if enabled
        if (!empty($this->settings['enable_featured_image'])) {
            $image_url = $this->generate_featured_image($keyword, $language);
            if (!is_wp_error($image_url) && !empty($image_url)) {
                $result['image_url'] = $image_url;
            }
        }

        // Add internal links if enabled
        if (!empty($this->settings['enable_internal_links'])) {
            $result['content'] = $this->add_internal_links($result['content'], $keyword, $result['secondary_keywords'] ?? []);
        }

        return $result;
    }

    /**
     * Build content generation prompt
     *
     * @param string $keyword  Keyword
     * @param string $language Language code
     * @param int    $min_words Minimum word count
     * @return string Prompt
     */
    private function build_content_prompt($keyword, $language, $min_words) {
        $language_name = $this->get_language_name($language);

        // Check if custom prompt is enabled and set
        if (!empty($this->settings['use_custom_prompt']) && !empty($this->settings['custom_prompt'])) {
            $prompt = $this->settings['custom_prompt'];

            // Replace placeholders
            $prompt = str_replace('{keyword}', $keyword, $prompt);
            $prompt = str_replace('{language}', $language_name, $prompt);
            $prompt = str_replace('{min_words}', $min_words, $prompt);

            return $prompt;
        }

        // Default prompt
        $prompt = <<<PROMPT
You are an expert SEO content writer. Write a comprehensive, fully SEO-optimized blog post about "{$keyword}" in {$language_name}.

## SEO REQUIREMENTS (MUST FOLLOW):

### 1. KEYWORD OPTIMIZATION
- Primary keyword "{$keyword}" must appear in:
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
- Minimum {$min_words} words
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
<h3>Q: [Question about {$keyword}]?</h3>
<p>A: [Detailed answer]</p>

Write the complete SEO-optimized content now:
PROMPT;

        return $prompt;
    }

    /**
     * Generate SEO metadata
     *
     * @param string $keyword  Keyword
     * @param string $content  Generated content
     * @param string $language Language code
     * @return array|WP_Error SEO data or error
     */
    private function generate_seo_data($keyword, $content, $language) {
        $language_name = $this->get_language_name($language);

        $prompt = <<<PROMPT
You are an SEO expert. Generate optimized metadata for the keyword "{$keyword}" in {$language_name}.

Content summary: {$this->get_content_summary($content)}

## SEO METADATA REQUIREMENTS:

### Title Tag (meta_title):
- MUST start with primary keyword "{$keyword}"
- Length: 50-60 characters (Google displays ~60)
- Include power words: Ultimate, Complete, Best, Guide, [Year]
- Make it click-worthy but not clickbait

### Meta Description (meta_desc):
- MUST include primary keyword naturally
- Length: 150-160 characters (Google displays ~155)
- Include call-to-action (Learn, Discover, Find out)
- Create urgency or curiosity
- Match search intent

### Post Title (title):
- Different from meta_title but includes keyword
- More creative/engaging for readers
- 50-70 characters

### Excerpt:
- Summarize the value proposition
- 150-200 characters
- Include keyword once

Generate in JSON format ONLY (no markdown, no explanation):
{
    "title": "Post title here",
    "excerpt": "Excerpt here",
    "meta_title": "SEO title here",
    "meta_desc": "Meta description here",
    "focus_keyphrase": "{$keyword}",
    "secondary_keywords": ["keyword1", "keyword2", "keyword3"]
}
PROMPT;

        $response = $this->provider->generate_text($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parse JSON response
        $response = trim($response);
        // Remove markdown code blocks if present
        $response = preg_replace('/^```json?\s*|\s*```$/m', '', $response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback to basic title
            return [
                'title'      => $this->generate_title($keyword, $language),
                'excerpt'    => '',
                'meta_title' => '',
                'meta_desc'  => '',
            ];
        }

        return $data;
    }

    /**
     * Generate basic title
     *
     * @param string $keyword  Keyword
     * @param string $language Language code
     * @return string Title
     */
    private function generate_title($keyword, $language) {
        // Capitalize first letter of each word
        $title = mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8');

        // Add prefix based on language
        if ($language === 'th') {
            $title = 'คู่มือ ' . $title . ' ฉบับสมบูรณ์';
        } else {
            $title = 'Complete Guide to ' . $title;
        }

        return $title;
    }

    /**
     * Generate featured image
     *
     * @param string $keyword  Keyword
     * @param string $language Language code
     * @return string|WP_Error Image URL or error
     */
    private function generate_featured_image($keyword, $language) {
        // Use OpenAI for images (DALL-E)
        $image_provider = $this->provider->supports_image_generation()
            ? $this->provider
            : $this->openai_for_images;

        if (!$image_provider) {
            return new \WP_Error('no_image_provider', 'No image generation provider available');
        }

        // Get image style from settings
        $style = $this->settings['image_style'] ?? 'professional';
        $style_description = $this->get_image_style_description($style);

        $prompt = "Create a blog featured image for an article about \"{$keyword}\". Style: {$style_description}. No text overlay, no watermarks.";

        return $image_provider->generate_image($prompt);
    }

    /**
     * Get image style description for prompt
     *
     * @param string $style Style key
     * @return string Style description
     */
    private function get_image_style_description($style) {
        $styles = [
            'professional' => 'Professional, clean, modern corporate style with subtle colors',
            'illustration' => 'Artistic digital illustration, creative and eye-catching',
            'realistic' => 'Photo-realistic, high quality photography style',
            'minimalist' => 'Minimalist design with simple shapes and limited colors',
            'vibrant' => 'Vibrant, colorful, energetic with bold colors and dynamic composition',
        ];

        return $styles[$style] ?? $styles['professional'];
    }

    /**
     * Get content summary for SEO prompt
     *
     * @param string $content Full content
     * @return string Summary
     */
    private function get_content_summary($content) {
        // Strip HTML and get first 500 characters
        $text = wp_strip_all_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);

        return mb_substr($text, 0, 500);
    }

    /**
     * Get language name from code
     *
     * @param string $code Language code
     * @return string Language name
     */
    private function get_language_name($code) {
        $languages = [
            'th' => 'Thai (ภาษาไทย)',
            'en' => 'English',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
        ];

        return $languages[$code] ?? 'English';
    }

    /**
     * Add internal links to related keywords in content
     *
     * @param string $content           The HTML content
     * @param string $main_keyword      The main keyword (to exclude from linking)
     * @param array  $secondary_keywords AI-generated secondary keywords
     * @return string Content with internal links added
     */
    private function add_internal_links($content, $main_keyword, $secondary_keywords = []) {
        $max_links = $this->settings['max_internal_links'] ?? 5;
        $new_tab = !empty($this->settings['internal_links_new_tab']);
        $custom_keywords = $this->settings['custom_link_keywords'] ?? '';

        // Build list of keywords to link
        $keywords_to_link = [];

        // Add secondary keywords from SEO data
        if (!empty($secondary_keywords) && is_array($secondary_keywords)) {
            $keywords_to_link = array_merge($keywords_to_link, $secondary_keywords);
        }

        // Add custom keywords from settings
        if (!empty($custom_keywords)) {
            $custom_list = array_filter(array_map('trim', explode("\n", $custom_keywords)));
            $keywords_to_link = array_merge($keywords_to_link, $custom_list);
        }

        // Remove duplicates and empty values
        $keywords_to_link = array_unique(array_filter($keywords_to_link));

        // Remove main keyword from list (don't link to self)
        $main_keyword_lower = mb_strtolower($main_keyword, 'UTF-8');
        $keywords_to_link = array_filter($keywords_to_link, function($kw) use ($main_keyword_lower) {
            return mb_strtolower(trim($kw), 'UTF-8') !== $main_keyword_lower;
        });

        if (empty($keywords_to_link)) {
            return $content;
        }

        // Sort by length (longer first) to avoid partial matches
        usort($keywords_to_link, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        // Limit to max links
        $keywords_to_link = array_slice($keywords_to_link, 0, $max_links);

        // Get site URL
        $site_url = home_url('/');

        // Track which keywords have been linked
        $linked_count = 0;
        $linked_keywords = [];

        // Target attribute
        $target_attr = $new_tab ? ' target="_blank" rel="noopener"' : '';

        // Process each keyword
        foreach ($keywords_to_link as $keyword) {
            if ($linked_count >= $max_links) {
                break;
            }

            $keyword = trim($keyword);
            if (empty($keyword) || mb_strlen($keyword) < 2) {
                continue;
            }

            // Skip if already linked
            $keyword_lower = mb_strtolower($keyword, 'UTF-8');
            if (in_array($keyword_lower, $linked_keywords)) {
                continue;
            }

            // Create slug for the keyword
            $slug = sanitize_title($keyword);
            if (empty($slug)) {
                continue;
            }

            // Build link URL
            $link_url = $site_url . $slug . '/';

            // Escape for regex
            $keyword_escaped = preg_quote($keyword, '/');

            // Pattern to match keyword not already in a link
            // Match keyword that is:
            // - Not inside an <a> tag
            // - Not inside HTML tag attributes
            // - Is a whole word (using word boundaries that work with Unicode)
            $pattern = '/(?<!<a[^>]*>)(?<!["\'>\/])(\b' . $keyword_escaped . '\b)(?![^<]*<\/a>)/iu';

            // Check if pattern matches
            if (preg_match($pattern, $content)) {
                // Create the link HTML
                $link_html = '<a href="' . esc_url($link_url) . '"' . $target_attr . ' class="aiapg-internal-link">$1</a>';

                // Replace only first occurrence
                $content = preg_replace($pattern, $link_html, $content, 1, $count);

                if ($count > 0) {
                    $linked_count++;
                    $linked_keywords[] = $keyword_lower;
                }
            }
        }

        return $content;
    }
}
