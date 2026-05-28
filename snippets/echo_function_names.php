<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

add_filter( 'bricks/code/echo_function_names', function() {
  return [
    'display_product_price_after_coupon', // function does not exist
    'get_product_brand_description', // function does not exist
    'get_first_gallery_image_url',
    'display_color_variations',
    'get_secondary_product_image',
    'get_pure_product_excerpt',

  ];
} );

    // End Code Snippet Code

}, 10);