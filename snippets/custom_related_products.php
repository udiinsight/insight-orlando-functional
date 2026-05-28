<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
/**
 * Proper WooCommerce Related Products Override
 * 
 * This code uses the correct WooCommerce hooks to override related products logic:
 * 1. Shows upsell products first if defined
 * 2. Then shows products from the same category (preferring child categories)
 * 3. Only displays in-stock products or variable products
 * 4. Limits to 4 items
 * 
 * Based on WooCommerce official documentation and best practices
 */

// Override the related product IDs that WooCommerce generates
add_filter('woocommerce_related_products', 'custom_related_products_ids', 10, 3);

function custom_related_products_ids($related_product_ids, $product_id, $args) {
    $custom_related_ids = [];
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return $related_product_ids;
    }
    
    // 1. First, get upsell products
    $upsell_ids = $product->get_upsell_ids();
    if (!empty($upsell_ids)) {
        foreach ($upsell_ids as $upsell_id) {
            $upsell_product = wc_get_product($upsell_id);
            if ($upsell_product && ($upsell_product->is_in_stock() || $upsell_product->is_type('variable'))) {
                $custom_related_ids[] = $upsell_id;
                if (count($custom_related_ids) >= 4) {
                    break;
                }
            }
        }
    }
    
    // 2. If we need more products, get from same category
    if (count($custom_related_ids) < 4) {
        $product_categories = wc_get_product_term_ids($product->get_id(), 'product_cat');
        
        if (!empty($product_categories)) {
            // Get child categories first for better relevance
            $child_categories = [];
            foreach ($product_categories as $category_id) {
                $children = get_term_children($category_id, 'product_cat');
                if (!empty($children)) {
                    $child_categories = array_merge($child_categories, $children);
                }
            }
            
            // Use child categories if available, otherwise parent categories
            $categories_to_check = !empty($child_categories) ? $child_categories : $product_categories;
            
            // Calculate how many more products we need
            $needed_products = 4 - count($custom_related_ids);
            
            // Get related products by category
            $related_products = wc_get_products([
                'limit' => $needed_products,
                'exclude' => array_merge([$product->get_id()], $custom_related_ids),
                'category' => $categories_to_check,
                'status' => 'publish',
                'stock_status' => 'instock',
            ]);
            
            foreach ($related_products as $related_product) {
                if ($related_product->is_in_stock() || $related_product->is_type('variable')) {
                    $custom_related_ids[] = $related_product->get_id();
                    if (count($custom_related_ids) >= 4) {
                        break;
                    }
                }
            }
        }
    }
    
    // Return our custom related product IDs (limited to 4)
    return array_slice($custom_related_ids, 0, 4);
}

// Set the number of related products to display
add_filter('woocommerce_output_related_products_args', 'custom_related_products_args');

function custom_related_products_args($args) {
    $args['posts_per_page'] = 4;
    $args['columns'] = 4;
    return $args;
}

// For Bricks Builder specifically - hook into their query system
add_filter('bricks/query/run', 'bricks_custom_related_products', 10, 2);

function bricks_custom_related_products($results, $query_obj) {
    // Check if this is a WooCommerce related products query
    if (!isset($query_obj->settings['source']) || 
        !in_array($query_obj->settings['source'], ['related_products', 'woocommerce_related_products'])) {
        return $results;
    }
    
    // Make sure we're on a product page
    if (!is_singular('product')) {
        return $results;
    }
    
    global $post;
    $current_product = wc_get_product($post->ID);
    
    if (!$current_product) {
        return $results;
    }
    
    // Use our custom related products logic
    $custom_related_ids = custom_related_products_ids([], $current_product->get_id(), []);
    
    if (empty($custom_related_ids)) {
        return [];
    }
    
    // Get the actual post objects
    $custom_results = get_posts([
        'post_type' => 'product',
        'include' => $custom_related_ids,
        'orderby' => 'post__in',
        'numberposts' => 4,
        'post_status' => 'publish'
    ]);
    
    return $custom_results;
}
    // End Code Snippet Code

}, 10);