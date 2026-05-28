<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
add_filter( 'dvsfw_customer_note', function( $note, $order_id ) {
    return '';
}, 10, 2 );
    // End Code Snippet Code

}, 10);