<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
function get_pure_product_excerpt() {
    global $product;
    
    if ( ! $product ) {
        return '';
    }
    
    $excerpt = $product->get_short_description();
    
    return $excerpt ? $excerpt : '';
}

    // End Code Snippet Code

}, 10);