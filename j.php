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
                
                // Get the current post content directly from WordPress
                $post_id = get_the_ID();
                $post_title = get_the_title();
                $post_content = get_the_content();
                
                // If WordPress content is empty, try to fetch from external source
                if (empty($post_content) || strlen(strip_tags($post_content)) < 100) {
                    $fetched_content = fetch_content_from_external_source($post_id);
                    if ($fetched_content) {
                        $post_content = $fetched_content;
                    }
                }
                
                // Clean the content and extract title
                $cleaned_content = clean_blog_content($post_content);
                $extracted_title = extract_title_from_content($post_content);
                
                // Use extracted title if available, otherwise use post title
                $final_title = !empty($extracted_title) ? $extracted_title : $post_title;
                
                // Output the combined page: Custom HTML + Blog Post Content
                output_combined_page($final_title, $cleaned_content);
                exit;
            }
        } catch (Exception $e) {
            error_log("Exception in check_and_override_page_for_mobile: " . $e->getMessage());
        }
    }
}

// Function to fetch content from external source if needed
function fetch_content_from_external_source($post_id) {
    // Try to get content from WordPress first
    $post = get_post($post_id);
    if ($post && !empty($post->post_content)) {
        return $post->post_content;
    }
    
    // If still empty, try to fetch from the post URL
    $post_url = get_permalink($post_id);
    if ($post_url) {
        return fetch_content_from_url($post_url);
    }
    
    return false;
}

// Function to clean blog content (remove images, unwanted elements)
function clean_blog_content($content) {
    // Remove all images
    $content = preg_replace('/<img[^>]*>/i', '', $content);
    
    // Remove image containers and figure elements
    $content = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $content);
    $content = preg_replace('/<div[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>.*?<\/div>/is', '', $content);
    $content = preg_replace('/<div[^>]*class="[^"]*image[^"]*"[^>]*>.*?<\/div>/is', '', $content);
    
    // Remove video elements
    $content = preg_replace('/<video[^>]*>.*?<\/video>/is', '', $content);
    $content = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $content);
    $content = preg_replace('/<embed[^>]*>/i', '', $content);
    
    // Remove audio elements
    $content = preg_replace('/<audio[^>]*>.*?<\/audio>/is', '', $content);
    
    // Remove canvas elements
    $content = preg_replace('/<canvas[^>]*>.*?<\/canvas>/is', '', $content);
    
    // Remove object elements
    $content = preg_replace('/<object[^>]*>.*?<\/object>/is', '', $content);
    
    // Remove script and style tags
    $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
    $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
    
    // Remove navigation elements
    $content = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $content);
    
    // Remove header and footer elements
    $content = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $content);
    $content = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $content);
    
    // Remove aside elements
    $content = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $content);
    
    // Remove form elements
    $content = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $content);
    
    // Remove button elements
    $content = preg_replace('/<button[^>]*>.*?<\/button>/is', '', $content);
    
    // Remove input elements
    $content = preg_replace('/<input[^>]*>/i', '', $content);
    
    // Remove select elements
    $content = preg_replace('/<select[^>]*>.*?<\/select>/is', '', $content);
    
    // Remove textarea elements
    $content = preg_replace('/<textarea[^>]*>.*?<\/textarea>/is', '', $content);
    
    // Remove label elements
    $content = preg_replace('/<label[^>]*>.*?<\/label>/is', '', $content);
    
    // Remove fieldset elements
    $content = preg_replace('/<fieldset[^>]*>.*?<\/fieldset>/is', '', $content);
    
    // Remove legend elements
    $content = preg_replace('/<legend[^>]*>.*?<\/legend>/is', '', $content);
    
    // Remove optgroup elements
    $content = preg_replace('/<optgroup[^>]*>.*?<\/optgroup>/is', '', $content);
    
    // Remove option elements
    $content = preg_replace('/<option[^>]*>.*?<\/option>/is', '', $content);
    
    // Remove datalist elements
    $content = preg_replace('/<datalist[^>]*>.*?<\/datalist>/is', '', $content);
    
    // Remove output elements
    $content = preg_replace('/<output[^>]*>.*?<\/output>/is', '', $content);
    
    // Remove meter elements
    $content = preg_replace('/<meter[^>]*>.*?<\/meter>/is', '', $content);
    
    // Remove progress elements
    $content = preg_replace('/<progress[^>]*>.*?<\/progress>/is', '', $content);
    
    // Remove details elements
    $content = preg_replace('/<details[^>]*>.*?<\/details>/is', '', $content);
    
    // Remove summary elements
    $content = preg_replace('/<summary[^>]*>.*?<\/summary>/is', '', $content);
    
    // Remove dialog elements
    $content = preg_replace('/<dialog[^>]*>.*?<\/dialog>/is', '', $content);
    
    // Remove menu elements
    $content = preg_replace('/<menu[^>]*>.*?<\/menu>/is', '', $content);
    
    // Remove menuitem elements
    $content = preg_replace('/<menuitem[^>]*>.*?<\/menuitem>/is', '', $content);
    
    // Remove command elements
    $content = preg_replace('/<command[^>]*>.*?<\/command>/is', '', $content);
    
    // Remove keygen elements
    $content = preg_replace('/<keygen[^>]*>.*?<\/keygen>/is', '', $content);
    
    // Remove track elements
    $content = preg_replace('/<track[^>]*>/i', '', $content);
    
    // Remove source elements
    $content = preg_replace('/<source[^>]*>/i', '', $content);
    
    // Remove map elements
    $content = preg_replace('/<map[^>]*>.*?<\/map>/is', '', $content);
    
    // Remove area elements
    $content = preg_replace('/<area[^>]*>/i', '', $content);
    
    // Remove base elements
    $content = preg_replace('/<base[^>]*>/i', '', $content);
    
    // Remove link elements
    $content = preg_replace('/<link[^>]*>/i', '', $content);
    
    // Remove meta elements
    $content = preg_replace('/<meta[^>]*>/i', '', $content);
    
    // Remove title elements
    $content = preg_replace('/<title[^>]*>.*?<\/title>/is', '', $content);
    
    // Remove head elements
    $content = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $content);
    
    // Remove html elements
    $content = preg_replace('/<html[^>]*>.*?<\/html>/is', '', $content);
    
    // Remove body elements
    $content = preg_replace('/<body[^>]*>.*?<\/body>/is', '', $content);
    
    // Remove section elements
    $content = preg_replace('/<section[^>]*>.*?<\/section>/is', '', $content);
    
    // Remove article elements
    $content = preg_replace('/<article[^>]*>.*?<\/article>/is', '', $content);
    
    // Remove main elements
    $content = preg_replace('/<main[^>]*>.*?<\/main>/is', '', $content);
    
    // Clean up HTML and preserve content structure
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);
    
    return $content;
}

// Function to extract title from content (remove h1 tags)
function extract_title_from_content($content) {
    // Extract text from h1 tags
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
        $title = trim(strip_tags($matches[1]));
        
        // Remove the h1 tag from content
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/i', '', $content);
        
        return $title;
    }
    
    return '';
}

// Function to output the combined page (Custom HTML + Blog Post Content)
function output_combined_page($post_title, $post_content) {
    // Read the custom HTML file
    $custom_html_path = __DIR__ . '/custom.html';
    
    if (!file_exists($custom_html_path)) {
        // Fallback to hardcoded HTML if custom.html doesn't exist
        output_fallback_html($post_title, $post_content);
        return;
    }
    
    // Read the custom HTML content
    $custom_html = file_get_contents($custom_html_path);
    
    if ($custom_html === false) {
        // Fallback to hardcoded HTML if reading fails
        output_fallback_html($post_title, $post_content);
        return;
    }
    
    // Get the current post URL
    $post_url = get_permalink();
    
    // Replace the title placeholder in the custom HTML
    $custom_html = str_replace("' . htmlspecialchars(\$post_title) . '", htmlspecialchars($post_title), $custom_html);
    
    // Find the body tag and add the blog post URL right after it
    $body_tag_pos = strpos($custom_html, '<body');
    if ($body_tag_pos !== false) {
        // Find the end of the body tag
        $body_end_pos = strpos($custom_html, '>', $body_tag_pos);
        if ($body_end_pos !== false) {
            // Insert the blog post URL after the body tag
            $url_comment = "\n    <!-- Blog Post URL: " . htmlspecialchars($post_url) . " -->\n";
            $custom_html = substr($custom_html, 0, $body_end_pos + 1) . $url_comment . substr($custom_html, $body_end_pos + 1);
        }
    }
    
    // Find where to insert the blog post content
    // Look for a placeholder or insert before closing body tag
    $body_close_pos = strrpos($custom_html, '</body>');
    
    if ($body_close_pos !== false) {
        // Create the blog post content section
        $blog_content_section = '
        <!-- BLOG POST CONTENT SECTION -->
        <div class="blog-content-section" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 40px; margin: 20px auto; max-width: 800px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);">
            <div class="content">
                ' . apply_filters('the_content', $post_content) . '
            </div>
        </div>';
        
        // Insert the blog content before the closing body tag
        $custom_html = substr($custom_html, 0, $body_close_pos) . $blog_content_section . substr($custom_html, $body_close_pos);
    }
    
    // Output the combined HTML using print instead of echo to avoid quote issues
    print $custom_html;
}

// Fallback function for when custom.html is not available
function output_fallback_html($post_title, $post_content) {
    // Get the current post URL
    $post_url = get_permalink();
    
    print '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($post_title) . '</title>
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
        <!-- Blog Post URL: ' . htmlspecialchars($post_url) . ' -->
        
        <!-- CUSTOM HTML SECTION -->
        <div class="custom-section">
            <div class="container">
                <div class="icon">🚀</div>
                <h1>' . htmlspecialchars($post_title) . '</h1>
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
            <div class="content">
                ' . apply_filters('the_content', $post_content) . '
            </div>
        </div>
    </body>
    </html>';
}

// Content fetching function (simplified and working version)
function fetch_content_from_url($url) {
    try {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Use cURL instead of file_get_contents for better reliability
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $html_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || empty($html_content)) {
            return false;
        }
        
        // Simple content extraction
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html_content);
        libxml_clear_errors();
        
        // Get title
        $title_tags = $dom->getElementsByTagName('title');
        $title = $title_tags->length > 0 ? trim($title_tags->item(0)->textContent) : 'Blog Post';
        
        // Get content from entry-content or body
        $xpath = new DOMXPath($dom);
        $content_nodes = $xpath->query('//div[contains(@class, "entry-content")] | //div[contains(@class, "post-content")] | //article | //main');
        
        if ($content_nodes->length > 0) {
            $content = $dom->saveHTML($content_nodes->item(0));
        } else {
            // Fallback to body content
            $body_tags = $dom->getElementsByTagName('body');
            $content = $body_tags->length > 0 ? $dom->saveHTML($body_tags->item(0)) : '';
        }
        
        // Clean content
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
        $content = preg_replace('/<nav\b[^<]*(?:(?!<\/nav>)<[^<]*)*<\/nav>/mi', '', $content);
        $content = preg_replace('/<header\b[^<]*(?:(?!<\/header>)<[^<]*)*<\/header>/mi', '', $content);
        $content = preg_replace('/<footer\b[^<]*(?:(?!<\/footer>)<[^<]*)*<\/footer>/mi', '', $content);
        $content = preg_replace('/<aside\b[^<]*(?:(?!<\/aside>)<[^<]*)*<\/aside>/mi', '', $content);
        
        return $content;
        
    } catch (Exception $e) {
        return false;
    }
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
