<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
// Remove my-points endpoint completely
add_action('init', function() {
    // Remove from WooCommerce query vars
    add_filter('woocommerce_get_query_vars', function($vars) {
        unset($vars['my-points']);
        return $vars;
    }, 0);
});

// Remove from My Account menu
function remove_my_points_menu_item($items) {
    unset($items['my-points']);
    return $items;
}
add_filter('woocommerce_account_menu_items', 'remove_my_points_menu_item');

// Optional: Redirect if someone tries to access the URL directly
add_action('template_redirect', function() {
    if (is_wc_endpoint_url('my-points')) {
        wp_redirect(wc_get_account_endpoint_url('dashboard'), 301);
        exit();
    }
});
    // End Code Snippet Code

}, 10);