<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Title: Secondary Product Image Function
 * 
 * wpCodeBox2 Settings:
 * - Code Type: PHP
 * - Location: Frontend + Backend
 * - Priority: 10
 * 
 * Usage in Bricks: {echo:get_secondary_product_image}
 */

function get_secondary_product_image() {
    // Get post ID from Bricks loop or global
    $post_id = null;
    
    if (class_exists('\Bricks\Query') && isset(\Bricks\Query::$loop_object->ID)) {
        $post_id = \Bricks\Query::$loop_object->ID;
    } else {
        $post_id = get_the_ID();
    }
    
    if (!$post_id) {
        return '';
    }
    
    // Get gallery image IDs
    $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
    
    if (empty($gallery_ids)) {
        // Fallback: return featured image URL
        return get_the_post_thumbnail_url($post_id, 'woocommerce_thumbnail') ?: '';
    }
    
    // Return first gallery image URL
    $gallery_array = explode(',', $gallery_ids);
    $first_gallery_image_id = trim($gallery_array[0]);
    
    return wp_get_attachment_image_url($first_gallery_image_id, 'woocommerce_thumbnail') ?: '';
}
    // End Code Snippet Code

}, 10);