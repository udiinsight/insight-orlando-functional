<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                
	// Condition Builder helper class
	$wpContext = new \WFPCore\WordPressContext();

	// Condition Builder generated Conditions
	if( !( ( $wpContext->is_frontend() ) )) {
		return false;
	}

	// Code Snippet Code
     

/**
 * Disable Dashicons on frontend for non-logged-in users
 * Keeps dashicons available for logged-in users (admin bar)
 */
add_action('wp_enqueue_scripts', function() {
    if (!is_user_logged_in()) {
        wp_dequeue_style('dashicons');
        wp_deregister_style('dashicons');
    }
}, 100);
    // End Code Snippet Code

}, 10);