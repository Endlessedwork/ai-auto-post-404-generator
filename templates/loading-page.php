<?php
/**
 * Loading Page Template
 *
 * Displayed while content is being generated
 *
 * @package AIAPG
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php esc_html_e('Generating Content...', 'ai-auto-post-generator'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .loading-container {
            text-align: center;
            padding: 40px;
            max-width: 500px;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 30px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        h1 {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .keyword {
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 10px;
        }

        .progress-text {
            margin-top: 20px;
            font-size: 14px;
            opacity: 0.8;
        }

        .dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <div class="loading-spinner"></div>
        <h1><?php esc_html_e('Creating Your Article', 'ai-auto-post-generator'); ?></h1>
        <p><?php esc_html_e('Our AI is writing a comprehensive article about:', 'ai-auto-post-generator'); ?></p>
        <span class="keyword"><?php echo esc_html($keyword ?? 'your topic'); ?></span>
        <p class="progress-text">
            <span class="dots"><?php esc_html_e('Please wait, this may take a moment', 'ai-auto-post-generator'); ?></span>
        </p>
    </div>

    <script>
        // Auto-refresh after a timeout as fallback
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
