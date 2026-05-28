<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
function get_first_gallery_image_url() {
    global $product;
    
    // Check if we're on a product page and product exists
    if (!is_product() || !$product) {
        return '';
    }
    
    $product_id = $product->get_id();
    
    // First priority: secondary-image ACF field
    $secondary_image = get_field('secondary-image', $product_id);
    if (!empty($secondary_image)) {
        // ACF returns array when "Image Array" is selected
        if (is_array($secondary_image) && isset($secondary_image['sizes']['large'])) {
            return $secondary_image['sizes']['large'];
        } elseif (is_array($secondary_image) && isset($secondary_image['url'])) {
            return $secondary_image['url'];
        } elseif (is_numeric($secondary_image)) {
            // If it returns ID
            return wp_get_attachment_image_url($secondary_image, 'large');
        } elseif (is_string($secondary_image)) {
            // If it returns URL
            return $secondary_image;
        }
    }
    
    // Second priority: if gallery_video has value, return main product image
    $gallery_video = get_field('gallery_video', $product_id);
    if (!empty($gallery_video)) {
        $main_image_id = $product->get_image_id();
        if ($main_image_id) {
            return wp_get_attachment_image_url($main_image_id, 'large');
        }
    }
    
    // Third priority: first gallery image (original behavior)
    $gallery_image_ids = $product->get_gallery_image_ids();
    if (!empty($gallery_image_ids)) {
        return wp_get_attachment_image_url($gallery_image_ids[0], 'large');
    }
    
    return '';
}
    // End Code Snippet Code

}, 10);