<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                
	// Condition Builder helper class
	$wpContext = new \WFPCore\WordPressContext();

	// Condition Builder generated Conditions
	if( !( ( $wpContext->is_frontend() ) )) {
		return false;
	}

	// Code Snippet Code
    
// Color Variations for Product Loops
function display_color_variations_loop()
{
    global $product;

    $product_group = get_field('product_group', $product->get_id());

    if (!$product_group) {
        return;
    }

    $cache_key = "color_group_" . md5($product_group);
    $cache_group = "color_variations";
    $related_colors = wp_cache_get($cache_key, $cache_group);

    if (false === $related_colors) {
        $related_colors = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => 'product_group',
                    'value' => $product_group,
                    'compare' => '='
                ],
                [
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '!='
                ]
            ],
            'posts_per_page' => 8,
            'post_status' => 'publish'
        ]);

        wp_cache_set(
            $cache_key,
            $related_colors,
            $cache_group,
            HOUR_IN_SECONDS
        );
    }

    // Filter out of stock products (in case they came from cache)
    $related_colors = array_filter($related_colors, function($color_product_obj) {
        $color_product = wc_get_product($color_product_obj->ID);
        return $color_product && $color_product->is_in_stock();
    });

    if (count($related_colors) > 1) {
        $product_item_id = "product-item-" . $product->get_id();

        echo '<div class="color-variations-loop" data-product-container="' . $product_item_id . '">';

        $displayed_count = 0;
        foreach ($related_colors as $color_product_obj) {
            if ($displayed_count >= 8) {
                break;
            }
            
            $color_product_id = $color_product_obj->ID;
            $color_product = wc_get_product($color_product_id);
            
            // Double check stock status
            if (!$color_product || !$color_product->is_in_stock()) {
                continue;
            }
            
            $is_current = $color_product_id == $product->get_id();
            
            $color_name = get_field('color_name', $color_product_id);
            $color_code = get_field('color_code', $color_product_id);

            if (!$color_code) {
                continue;
            }

            $product_link = get_permalink($color_product_id);
            $product_title = $color_product->get_name();
            $product_price = $color_product->get_price_html();
            
            $image_id = $color_product->get_image_id();
            $image_url = '';
            if ($image_id) {
                $image_data = wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail');
                if ($image_data) {
                    $image_url = $image_data[0];
                }
            }

            $tooltip_text = $color_name ?: get_the_title($color_product_id);

            echo '<button class="color-option-loop' . ($is_current ? " active" : "") . '" 
                    title="' . esc_attr($tooltip_text) . '"
                    data-product-id="' . $color_product_id . '"
                    data-current="' . ($is_current ? "1" : "0") . '"
                    data-link="' . esc_attr($product_link) . '"
                    data-title="' . esc_attr($product_title) . '"
                    data-price="' . esc_attr($product_price) . '"
                    data-image="' . esc_attr($image_url) . '"
                    type="button">';
            echo '<span class="color-swatch-loop" style="background-color: ' . esc_attr($color_code) . '"></span>';
            echo "</button>";
            
            $displayed_count++;
        }

        $total_colors = count($related_colors);
        if ($total_colors > 8) {
            $remaining = $total_colors - 8;
            echo '<span class="more-colors">+' . $remaining . '</span>';
        }

        echo "</div>";
    }

    wp_reset_postdata();
}

add_action("save_post_product", "clear_color_variation_cache");
add_action("woocommerce_update_product", "clear_color_variation_cache");

function clear_color_variation_cache($post_id)
{
    $cache_group = "color_variations";
    $color_cache_key = "color_data_" . $post_id;
    wp_cache_delete($color_cache_key, $cache_group);

    $product_group = get_field('product_group', $post_id);
    if ($product_group) {
        $group_cache_key = "color_group_" . md5($product_group);
        wp_cache_delete($group_cache_key, $cache_group);
    }
}

add_action("wp_footer", "color_variations_script_no_ajax");


function color_variations_script_no_ajax()
{
 ?>
    <script>
    jQuery(function($) {
        // Event Delegation - עובד גם על אלמנטים שנטענים ב-AJAX
        $(document).on('click', '.color-option-loop', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            
            if ($this.data('current') == '1') {
                return;
            }
            
            var newLink = $this.data('link');
            var newTitle = $this.data('title');
            var newPrice = $this.data('price');
            var newImage = $this.data('image');
            
            var $colorContainer = $this.closest('.color-variations-loop');
            var $productItem = $colorContainer.closest('.product-item, .product, [class*="product"]');
            
            var $imageWrap = $productItem.find('.product-item__image-wrap');
            var $imageLink = $imageWrap.find('a');
            var $image = $imageWrap.find('img').first();
            
            if ($image.length > 0 && newImage) {
                $image.css('opacity', '0.5');
                $image.removeAttr('srcset data-src data-srcset');
                $image.removeClass('lazy lazyload lazyloaded');
                $image.attr('src', newImage);
                $image[0].src = newImage;
                
                setTimeout(function() {
                    $image.css('opacity', '1');
                }, 100);
            }
            
            if ($imageLink.length > 0 && newLink) {
                $imageLink.attr('href', newLink);
            }
            
            var $title = $productItem.find('.product-item__name, .woocommerce-loop-product__title, .product-title');
            if ($title.length > 0 && newTitle) {
                $title.text(newTitle);
            }
            
            var $price = $productItem.find('.price');
            if ($price.length > 0 && newPrice) {
                $price.html(newPrice);
            }
            
            $colorContainer.find('.color-option-loop').removeClass('active').data('current', '0');
            $this.addClass('active').data('current', '1');
        });
    });
    </script>
    <?php
}

add_action("wp_head", "color_variations_loop_css");

function color_variations_loop_css()
{
    echo '<style>
    .color-variations-loop {
        display: flex;
        align-items: center;
        gap: 4px;
        margin: 8px 0;
        flex-wrap: wrap;
    }

    .color-option-loop {
        display: block;
        border: 2px solid transparent;
        border-radius: 50%;
        background: none;
        padding: 0;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .color-option-loop:hover {
        border-color: #999;
        transform: scale(1.15);
    }

    .color-option-loop.active {
        border-color: #0073aa;
    }

    .color-swatch-loop {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 1px solid #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        display: block;
        cursor: pointer;
    }

    .more-colors {
        font-size: 11px;
        color: #666;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 4px;
    }

    .color-option-loop[title]:hover::after {
        content: attr(title);
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 11px;
        white-space: nowrap;
        z-index: 1000;
        opacity: 0;
        animation: tooltipFadeIn 0.2s ease forwards;
    }

    @keyframes tooltipFadeIn {
        to { opacity: 1; }
    }

    @media (max-width: 768px) {
        .color-swatch-loop {
            width: 16px;
            height: 16px;
        }
        
        .color-option-loop[title]:hover::after {
            display: none;
        }
    }

    .product-item__image-wrap img {
        transition: opacity 0.3s ease;
    }
    </style>';
}

add_action('woocommerce_after_shop_loop_item_title', 'display_color_variations_loop', 15);
    // End Code Snippet Code

}, 10);