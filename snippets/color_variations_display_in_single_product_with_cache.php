<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
// Add color options to single product page with Object Cache + ACF + Loading Spinner
// Excludes out of stock products
//add_action('woocommerce_single_product_summary', 'display_color_variations', 25);

function display_color_variations() {
    global $product;
    
    // Get the product group from ACF
    $product_group = get_field('product_group', $product->get_id());
    
    if (!$product_group) return;
    
    // Try to get from object cache first
    $cache_key = 'color_group_' . md5($product_group);
    $cache_group = 'color_variations';
    $related_colors = wp_cache_get($cache_key, $cache_group);
    
    if (false === $related_colors) {
        // Not in cache - fetch from database
        $related_colors = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => 'product_group',
                    'value' => $product_group,
                    'compare' => '='
                ),
                array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '!='
                )
            ),
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        // Store in object cache for 1 hour
        wp_cache_set($cache_key, $related_colors, $cache_group, HOUR_IN_SECONDS);
    }
    
    // Filter out of stock products (in case they came from cache)
    $related_colors = array_filter($related_colors, function($color_product) {
        $color_product_obj = wc_get_product($color_product->ID);
        return $color_product_obj && $color_product_obj->is_in_stock();
    });
    
    if (count($related_colors) > 1) {
        echo '<div class="color-variations">';
        echo '<div class="color-options" id="color-options">';
        
        foreach ($related_colors as $color_product) {
            $color_product_obj = wc_get_product($color_product->ID);
            
            // Double check stock status
            if (!$color_product_obj || !$color_product_obj->is_in_stock()) {
                continue;
            }
            
            $is_current = ($color_product->ID == $product->get_id());
            
            // Try to get color data from cache
            $color_cache_key = 'color_data_' . $color_product->ID;
            $color_data = wp_cache_get($color_cache_key, $cache_group);
            
            if (false === $color_data) {
                $color_data = array(
                    'name' => get_field('color_name', $color_product->ID),
                    'code' => get_field('color_code', $color_product->ID)
                );
                wp_cache_set($color_cache_key, $color_data, $cache_group, HOUR_IN_SECONDS);
            }
            
            $color_name = $color_data['name'];
            $color_code = $color_data['code'];
            
            $tooltip_text = $color_name ?: $color_product->post_title;
            
            // Spinner element (hidden by default, shown on click)
            $spinner_html = '<span class="color-spinner"></span>';
            
            if ($is_current) {
                echo '<span class="color-option current-color" title="' . esc_attr($tooltip_text) . ' (נוכחי)">';
                if ($color_code) {
                    echo '<span class="color-swatch" style="background-color: ' . esc_attr($color_code) . '">' . $spinner_html . '</span>';
                } else {
                    $letter = $color_name ? strtoupper(substr($color_name, 0, 1)) : '?';
                    echo '<span class="color-swatch color-swatch-text"><span class="swatch-letter">' . $letter . '</span>' . $spinner_html . '</span>';
                }
                echo '</span>';
            } else {
                echo '<a href="' . get_permalink($color_product->ID) . '" class="color-option" title="' . esc_attr($tooltip_text) . '">';
                if ($color_code) {
                    echo '<span class="color-swatch" style="background-color: ' . esc_attr($color_code) . '">' . $spinner_html . '</span>';
                } else {
                    $letter = $color_name ? strtoupper(substr($color_name, 0, 1)) : '?';
                    echo '<span class="color-swatch color-swatch-text"><span class="swatch-letter">' . $letter . '</span>' . $spinner_html . '</span>';
                }
                echo '</a>';
            }
        }
        
        echo '</div></div>';
        
        // Inline JS for spinner activation
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.color-option:not(.current-color)').forEach(function(option) {
                option.addEventListener('click', function() {
                    this.classList.add('loading');
                });
            });
        });
        </script>
        <?php
    }
}
    // End Code Snippet Code

}, 10);