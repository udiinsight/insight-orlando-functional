<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

add_filter('bricks/posts/query_vars', function($query_vars, $settings, $element_id) {
    // בדוק אם זה דף ארכיון של מוצרים
    if (is_post_type_archive('product') || is_tax(get_object_taxonomies('product'))) {
        // מיון לפי פופולריות (total sales)
        $query_vars['orderby'] = 'meta_value_num';
        $query_vars['meta_key'] = 'total_sales';
        $query_vars['order'] = 'DESC';
    }
    
    return $query_vars;
}, 10, 3);
    // End Code Snippet Code

}, 10);