<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Add og:ttl meta tag to limit Facebook crawler
 * Tells Facebook to not re-scrape pages for 24 hours
 */
add_action('wp_head', function() {
    // Only add to frontend, not admin
    if (is_admin()) {
        return;
    }
    
    // 86400 seconds = 24 hours
    echo '<meta property="og:ttl" content="86400" />' . "\n";
}, 1);

    // End Code Snippet Code

}, 1);