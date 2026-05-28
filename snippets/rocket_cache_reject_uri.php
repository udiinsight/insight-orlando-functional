<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

add_filter('rocket_cache_reject_uri', function($urls) {
    $urls[] = '/wp-content/uploads/woo-feed/(.*)';
    return $urls;
});
    // End Code Snippet Code

}, 10);