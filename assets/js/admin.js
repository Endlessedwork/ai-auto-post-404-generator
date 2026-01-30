/**
 * AI Auto Post Generator - Admin Scripts
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        initMasterSwitch();
        initProviderTabs();
        initApiTest();
        initDebugTest();
        initCustomPrompt();
        initImageToggle();
        initLinkingToggle();
    });

    /**
     * Initialize master on/off switch
     */
    function initMasterSwitch() {
        var $switch = $('.aiapg-master-switch');
        var $checkbox = $switch.find('input[type="checkbox"]');
        var $label = $switch.find('.aiapg-toggle-label');

        $checkbox.on('change', function() {
            if ($(this).is(':checked')) {
                $switch.removeClass('disabled').addClass('enabled');
                $label.text('ON');
            } else {
                $switch.removeClass('enabled').addClass('disabled');
                $label.text('OFF');
            }
        });
    }

    /**
     * Initialize provider settings tabs
     */
    function initProviderTabs() {
        var $providerSelect = $('#ai_provider');
        var $providerSettings = $('.aiapg-provider-settings');

        // Show/hide provider settings based on selection
        function updateProviderSettings() {
            var selected = $providerSelect.val();

            $providerSettings.removeClass('active');
            $providerSettings.filter('[data-provider="' + selected + '"]').addClass('active');

            // Always show OpenAI if featured images enabled (needed for DALL-E)
            var $featuredImageCheckbox = $('input[name="aiapg_settings[enable_featured_image]"]');
            if ($featuredImageCheckbox.is(':checked') && selected !== 'openai') {
                $providerSettings.filter('[data-provider="openai"]').addClass('active');
            }
        }

        // Initial state
        updateProviderSettings();

        // On change
        $providerSelect.on('change', updateProviderSettings);

        // Also update when featured image checkbox changes
        $('input[name="aiapg_settings[enable_featured_image]"]').on('change', updateProviderSettings);
    }

    /**
     * Initialize API test buttons
     */
    function initApiTest() {
        $('.aiapg-test-api').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $button.siblings('.aiapg-test-result');
            var provider = $button.data('provider');
            var apiKeyField = '#' + provider + '_api_key';
            var apiKey = $(apiKeyField).val();

            if (!apiKey) {
                $result.removeClass('success loading').addClass('error').text('Please enter an API key first.');
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            $result.removeClass('success error').addClass('loading').text('Testing...');

            // Make AJAX request
            $.ajax({
                url: aiapg.ajax_url,
                type: 'POST',
                data: {
                    action: 'aiapg_test_api',
                    nonce: aiapg.nonce,
                    provider: provider,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('loading error').addClass('success').text('✓ ' + response.data.message);
                    } else {
                        $result.removeClass('loading success').addClass('error').text('✗ ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('loading success').addClass('error').text('✗ Connection failed: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize debug test
     */
    function initDebugTest() {
        $('#aiapg-run-test').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var keyword = $('#test_keyword').val();

            if (!keyword) {
                alert('Please enter a test keyword');
                return;
            }

            $button.prop('disabled', true).text('Testing...');

            $.ajax({
                url: aiapg.ajax_url,
                type: 'POST',
                data: {
                    action: 'aiapg_test_intercept',
                    nonce: aiapg.nonce,
                    keyword: keyword
                },
                success: function(response) {
                    if (response.success) {
                        var $results = $('#aiapg-test-results');
                        var $tbody = $('#aiapg-results-table tbody');

                        $tbody.empty();

                        response.data.debug.forEach(function(item) {
                            var statusIcon = item.status ? '✅' : '❌';
                            var statusClass = item.status ? 'pass' : 'fail';
                            $tbody.append(
                                '<tr class="' + statusClass + '">' +
                                '<td>' + item.check + '</td>' +
                                '<td>' + statusIcon + '</td>' +
                                '<td>' + item.value + '</td>' +
                                '</tr>'
                            );
                        });

                        var urlMsg = response.data.all_passed
                            ? '<strong style="color:green;">✅ All checks passed!</strong> Try visiting: <a href="' + response.data.test_url + '" target="_blank">' + response.data.test_url + '</a>'
                            : '<strong style="color:red;">❌ Some checks failed.</strong> Fix the issues above first.';

                        $('#aiapg-test-url').html(urlMsg);
                        $results.show();
                    } else {
                        alert('Test failed: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Run Test');
                }
            });
        });
    }

    /**
     * Initialize custom prompt UI
     */
    function initCustomPrompt() {
        var defaultPrompt = getDefaultPrompt();

        // Show/hide default prompt box
        $('#aiapg-show-default-prompt').on('click', function() {
            var $box = $('#aiapg-default-prompt-box');
            if ($box.is(':visible')) {
                $box.slideUp();
                $(this).text('Show Default Prompt');
            } else {
                $box.slideDown();
                $(this).text('Hide Default Prompt');
            }
        });

        // Reset to default prompt
        $('#aiapg-reset-prompt').on('click', function() {
            if (confirm('Are you sure you want to reset the custom prompt to default?')) {
                $('#custom_prompt').val(defaultPrompt);
            }
        });
    }

    /**
     * Get default prompt template
     */
    function getDefaultPrompt() {
        return 'You are an expert SEO content writer. Write a comprehensive, fully SEO-optimized blog post about "{keyword}" in {language}.\n\n## SEO REQUIREMENTS (MUST FOLLOW):\n\n### 1. KEYWORD OPTIMIZATION\n- Primary keyword "{keyword}" must appear in:\n  - First paragraph (within first 100 words)\n  - At least 2-3 H2 headings\n  - Naturally throughout content (keyword density 1-2%)\n  - Last paragraph/conclusion\n- Include LSI keywords (related terms) naturally\n- Use keyword variations and synonyms\n\n### 2. CONTENT STRUCTURE (Critical for SEO)\n- Start with a compelling hook that includes the keyword\n- Use Table of Contents friendly structure:\n  - H2 for main sections (5-8 sections minimum)\n  - H3 for subsections under each H2\n- Include these section types:\n  - "What is [keyword]" or definition section\n  - "Benefits/Advantages" section\n  - "How to" or step-by-step guide\n  - "Tips" or "Best Practices" section\n  - FAQ section with 3-5 common questions\n  - Conclusion with call-to-action\n\n### 3. READABILITY & ENGAGEMENT\n- Short paragraphs (2-3 sentences max)\n- Use bullet points and numbered lists frequently\n- Include statistics or data points (use realistic examples)\n- Add transition words between sections\n- Flesch reading score target: 60-70 (easy to read)\n\n### 4. E-E-A-T SIGNALS (Experience, Expertise, Authority, Trust)\n- Write from expert perspective\n- Include specific examples and case studies\n- Mention best practices from the industry\n- Add cautionary notes where appropriate\n\n### 5. CONTENT LENGTH & DEPTH\n- Minimum {min_words} words\n- Cover topic comprehensively\n- Answer user intent completely\n\n### 6. HTML FORMAT\n- Use semantic HTML: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>\n- Use <strong> for important keywords (but don\'t overdo)\n- Do NOT include <html>, <head>, <body>, or <h1> tags\n- Do NOT include the main title\n\n### 7. FAQ SECTION FORMAT\nUse this exact format for FAQ section:\n<h2>คำถามที่พบบ่อย (FAQ)</h2>\n<h3>Q: [Question about {keyword}]?</h3>\n<p>A: [Detailed answer]</p>\n\nWrite the complete SEO-optimized content now:';
    }

    /**
     * Initialize image generation toggle
     */
    function initImageToggle() {
        var $checkbox = $('#enable_featured_image');
        var $imageSettings = $('.aiapg-image-settings');

        $checkbox.on('change', function() {
            if ($(this).is(':checked')) {
                $imageSettings.slideDown();
            } else {
                $imageSettings.slideUp();
            }
        });
    }

    /**
     * Initialize internal linking toggle
     */
    function initLinkingToggle() {
        var $checkbox = $('#enable_internal_links');
        var $linkingSettings = $('.aiapg-linking-settings');

        $checkbox.on('change', function() {
            if ($(this).is(':checked')) {
                $linkingSettings.slideDown();
            } else {
                $linkingSettings.slideUp();
            }
        });
    }

})(jQuery);
