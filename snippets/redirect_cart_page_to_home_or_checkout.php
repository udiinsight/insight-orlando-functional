<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                
	// Condition Builder helper class
	$wpContext = new \WFPCore\WordPressContext();

	// Condition Builder generated Conditions
	if( !( ( $wpContext->is_frontend() ) )) {
		return false;
	}

	// Code Snippet Code
     
// Handle other checkout redirect scenarios
add_action( 'template_redirect', 'handle_all_checkout_redirects' );
function handle_all_checkout_redirects() {
    if ( is_checkout() && !is_wc_endpoint_url() ) {
        // Empty cart - redirect to homepage
        if ( WC()->cart->is_empty() ) {
            wp_redirect( home_url() );
            exit;
        }
        
        // Cart has errors but items exist - redirect to checkout to show errors
        if ( wc_notice_count( 'error' ) > 0 ) {
            // Let checkout page handle the errors naturally
            // Don't redirect, let the checkout process continue
            return;
        }
    }
    
    // If accessing cart page and cart has items, redirect to checkout
    if ( is_cart() && !WC()->cart->is_empty() ) {
        wp_redirect( wc_get_checkout_url() );
        exit;
    }
    
    // If accessing cart page and cart is empty, redirect to homepage
    if ( is_cart() && WC()->cart->is_empty() ) {
        wp_redirect( home_url() );
        exit;
    }
}

    // End Code Snippet Code

}, 10);