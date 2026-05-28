<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

/**
 * Auto-Exclude Flash Sale Category from New Coupons
 * 
 * Automatically adds "Flash Sale" category to excluded categories
 * when a new coupon is created.
 * 
 * wpCodeBox2 Settings:
 * - Code Type: PHP
 * - Location: Admin only
 * - Keep this snippet ACTIVE permanently
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Flash Sale to excluded categories when coupon is created
 */
add_action('wp_insert_post', 'auto_exclude_flash_sale_category', 10, 3);

function auto_exclude_flash_sale_category($post_id, $post, $update) {
    // Only for new coupons (not updates)
    if ($update) {
        return;
    }
    
    // Only for shop_coupon post type
    if ($post->post_type !== 'shop_coupon') {
        return;
    }
    
    // Get Flash Sale category
    $flash_sale_cat = get_term_by('slug', 'flash-sale', 'product_cat');
    
    if (!$flash_sale_cat) {
        return;
    }
    
    $category_id = $flash_sale_cat->term_id;
    
    // Get current excluded categories (might be empty for new coupon)
    $excluded = get_post_meta($post_id, 'exclude_product_categories', true);
    
    if (!is_array($excluded)) {
        $excluded = array();
    }
    
    // Add Flash Sale category
    if (!in_array($category_id, $excluded)) {
        $excluded[] = $category_id;
        update_post_meta($post_id, 'exclude_product_categories', $excluded);
    }
}

/**
 * Alternative: Also catch coupons created via API/programmatically
 * This hook fires after all meta is saved
 */
add_action('woocommerce_new_coupon', 'auto_exclude_flash_sale_on_wc_create', 10, 2);

function auto_exclude_flash_sale_on_wc_create($coupon_id, $coupon) {
    // Get Flash Sale category
    $flash_sale_cat = get_term_by('slug', 'flash-sale', 'product_cat');
    
    if (!$flash_sale_cat) {
        return;
    }
    
    $category_id = $flash_sale_cat->term_id;
    
    // Get current excluded categories
    $excluded = $coupon->get_excluded_product_categories();
    
    if (!is_array($excluded)) {
        $excluded = array();
    }
    
    // Add Flash Sale category if not there
    if (!in_array($category_id, $excluded)) {
        $excluded[] = $category_id;
        $coupon->set_excluded_product_categories($excluded);
        $coupon->save();
    }
}
    // End Code Snippet Code

}, 10);