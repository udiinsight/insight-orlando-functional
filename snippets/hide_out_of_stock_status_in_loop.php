<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

add_filter('bricks/posts/query_vars', function($query_vars, $settings, $element_id) {
    if (($element_id === 'czdquy') || ($element_id === 'dspmhj')){
        $query_vars['meta_query'][] = [
            'key'     => '_stock_status',
            'value'   => 'instock',
            'compare' => '='
        ];
    }
    return $query_vars;
}, 10, 3);


    // End Code Snippet Code

}, 10);