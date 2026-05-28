<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                
	// Condition Builder helper class
	$wpContext = new \WFPCore\WordPressContext();

	// Condition Builder generated Conditions
	if( !( ( $wpContext->is_frontend() ) )) {
		return false;
	}

	// Code Snippet Code
     
/**
 * Get brand description for current product
 * 
 * This function returns the description of the "brands" taxonomy
 * for the current product when called from a product page.
 * 
 * @return string The brand description or empty string if no brand found
 */
function get_product_brand_description() {
    // Make sure we're on a product page
    if (!is_singular('product')) {
        return '';
    }
    
    // Get the current product ID
    $product_id = get_the_ID();
    
    // Get the brand terms associated with this product
    $brands = get_the_terms($product_id, 'brands');
    
    // Check if we have brands and no errors occurred
    if (!is_wp_error($brands) && !empty($brands)) {
        // Get the first brand (assuming one brand per product)
        $brand = reset($brands);
        
        // Return the brand description with formatting preserved
        return apply_filters('the_content', $brand->description);
    }
    
    // Return empty string if no brand found
    return '';
}
    // End Code Snippet Code

}, 10);