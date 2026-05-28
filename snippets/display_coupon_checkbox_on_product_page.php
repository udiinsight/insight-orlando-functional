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
 * Display coupon checkbox on product page
 * Controlled via ACF Options
 */
add_action('woocommerce_before_add_to_cart_form', 'custom_add_coupon_checkbox_to_product');
function custom_add_coupon_checkbox_to_product() {

    // Check if feature is enabled
    if (!get_field('enable_product_coupon', 'option')) {
        return;
    }
    
    global $product;
    
    // Get ACF options
    $coupon_code = get_field('product_coupon_code', 'option') ?: 'HOLIDAY30';
    $discount_percent = get_field('product_coupon_discount', 'option') ?: 30;
    $title_text = get_field('product_coupon_title', 'option') ?: '30% הנחה, משלוח חינם ומתנה בכל קניה!';
    $label_applied = get_field('product_coupon_label_applied', 'option') ?: 'קופון {coupon_code} מחכה לך בעגלה';
    $label_available = get_field('product_coupon_label_available', 'option') ?: 'קופון {coupon_code} - מחיר לאחר הנחה {price}';
    $excluded_cats = get_field('product_coupon_excluded_cats', 'option') ?: array();
    
    // Check if product is in excluded categories
    if (!empty($excluded_cats)) {
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        if (array_intersect($product_cats, $excluded_cats)) {
            return;
        }
    }
        // Check if product is in Flash Sale
    if (get_post_meta($product->get_id(), '_is_flash_sale_product', true) === 'yes') {
        return;
    }
    // Check if coupon is already applied
    $coupon_applied = WC()->cart->has_discount($coupon_code);
    
    // Calculate discount multiplier
    $discount_multiplier = 1 - ($discount_percent / 100);
    
    // Get original price
    if ($product->is_type('variable')) {
        $prices = $product->get_variation_prices();
        $original_price = min($prices['price']);
    } else {
        $original_price = $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price();
    }
    
    $discounted_price = $original_price * $discount_multiplier;
    $formatted_price = wc_price($discounted_price);
    
    // Prepare label texts with replacements
    $label_applied_text = str_replace('{coupon_code}', $coupon_code, $label_applied);
    $label_available_text = str_replace(
        array('{coupon_code}', '{price}'),
        array($coupon_code, $formatted_price),
        $label_available
    );
    
    // Set checkbox state
    $checked = $coupon_applied ? 'checked' : '';
    $disabled = $coupon_applied ? 'disabled' : '';
    $final_label = $coupon_applied ? $label_applied_text : $label_available_text;
    
    // Output HTML
    echo '<div class="product-coupon-wrapper">';
    echo '<div class="product-coupon-title">' . esc_html($title_text) . '</div>';
    echo '<div class="product-coupon-checkbox">';
    echo '<input type="checkbox" name="product_coupon_apply" id="product_coupon_apply" value="' . esc_attr($coupon_code) . '" ' . $checked . ' ' . $disabled . '>';
    echo '<label for="product_coupon_apply">' . esc_html($final_label) . '</label>';
    echo '</div>';
    echo '</div>';
    
    // Add inline JavaScript for price updates only
    ?>
    <script>
    (function() {
        const discountMultiplier = <?php echo esc_js($discount_multiplier); ?>;
        const couponCode = '<?php echo esc_js($coupon_code); ?>';
        const labelTemplate = '<?php echo esc_js($label_available); ?>';
        const checkbox = document.getElementById('product_coupon_apply');
        const label = checkbox ? checkbox.nextElementSibling : null;
        
        if (!checkbox || !label) return;
        
        // Format price via AJAX
        async function updateDiscountedPrice(price) {
            const discountedPrice = price * discountMultiplier;
            
            try {
                const formData = new FormData();
                formData.append('action', 'custom_format_discounted_price');
                formData.append('price', discountedPrice);
                formData.append('nonce', '<?php echo wp_create_nonce('format_price_nonce'); ?>');
                
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const formattedPrice = await response.text();
                const newLabel = labelTemplate
                    .replace('{coupon_code}', couponCode)
                    .replace('{price}', formattedPrice);
                
                label.textContent = newLabel;
            } catch (error) {
                console.error('Error formatting price:', error);
            }
        }
        
        <?php if ($product->is_type('variable')) : ?>
        // Handle variable product price changes
        document.addEventListener('DOMContentLoaded', function() {
            const variationForm = document.querySelector('.variations_form');
            if (variationForm) {
                variationForm.addEventListener('show_variation', function(event) {
                    if (event.detail && event.detail.variation) {
                        updateDiscountedPrice(event.detail.variation.display_price);
                    }
                });
            }
        });
        <?php endif; ?>
    })();
    </script>
    <?php
}

/**
 * Add hidden input to cart form
 */
add_action('woocommerce_before_add_to_cart_button', 'custom_add_coupon_hidden_input');
function custom_add_coupon_hidden_input() {


    // Check if feature is enabled
    if (!get_field('enable_product_coupon', 'option')) {
        return;
    }
    
    global $product;
    
    // Check for excluded categories
    $excluded_cats = get_field('product_coupon_excluded_cats', 'option') ?: array();
    if (!empty($excluded_cats)) {
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        if (array_intersect($product_cats, $excluded_cats)) {
            return;
        }
    }
        // Check if product is in Flash Sale
    if (get_post_meta($product->get_id(), '_is_flash_sale_product', true) === 'yes') {
        return;
    }
    // Add hidden input that will be updated by JavaScript
    echo '<input type="hidden" name="apply_product_coupon" id="apply_product_coupon_hidden" value="0">';
    
    // Add JavaScript to sync checkbox with hidden input
    ?>
    <script>
    (function() {
        const checkbox = document.getElementById('product_coupon_apply');
        const hiddenInput = document.getElementById('apply_product_coupon_hidden');
        
        if (!checkbox || !hiddenInput) return;
        
        // Set initial value based on checkbox state
        hiddenInput.value = checkbox.checked ? '1' : '0';
        
        // Update hidden input when checkbox changes
        checkbox.addEventListener('change', function() {
            hiddenInput.value = this.checked ? '1' : '0';
        });
    })();
    </script>
    <?php
}

/**
 * Apply coupon when product is added to cart
 */
add_action('woocommerce_add_to_cart', 'custom_apply_coupon_on_add_to_cart', 10, 6);
function custom_apply_coupon_on_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Check if coupon should be applied
    if (!isset($_POST['apply_product_coupon']) || $_POST['apply_product_coupon'] !== '1') {
        return;
    }
    
    $coupon_code = get_field('product_coupon_code', 'option') ?: 'HOLIDAY30';
    
    // Check if coupon already applied
    if (WC()->cart->has_discount($coupon_code)) {
        return;
    }
    
    // Apply the coupon
    $result = WC()->cart->apply_coupon(sanitize_text_field($coupon_code));
    
    if ($result) {
        // Force cart fragments refresh for FunnelKit Side Cart
        WC()->session->set('refresh_totals', true);
        wc_add_notice(sprintf(__('קופון %s הוחל בהצלחה!'), $coupon_code), 'success');
    }
}

/**
 * Trigger FunnelKit Side Cart refresh after coupon is applied
 */
add_action('woocommerce_applied_coupon', 'custom_trigger_cart_refresh');
function custom_trigger_cart_refresh($coupon_code) {
    // Check if this is our product coupon
    $product_coupon = get_field('product_coupon_code', 'option') ?: 'HOLIDAY30';
    
    if ($coupon_code !== $product_coupon) {
        return;
    }
    
    // Add inline JavaScript to trigger FunnelKit refresh
    add_action('wp_footer', function() {
        ?>
        <script>
        (function() {
            // Trigger WooCommerce cart update
            if (typeof jQuery !== 'undefined') {
                jQuery(document.body).trigger('wc_fragment_refresh');
                jQuery(document.body).trigger('update_checkout');
            }
            
            // Trigger FunnelKit Side Cart update (if exists)
            if (typeof window.wfacp_frontend !== 'undefined') {
                window.wfacp_frontend.trigger_update_checkout();
            }
            
            // Alternative: Trigger custom event that FunnelKit might listen to
            document.body.dispatchEvent(new CustomEvent('fkcart_update'));
        })();
        </script>
        <?php
    }, 999);
}

/**
 * AJAX handler for price formatting
 */
add_action('wp_ajax_custom_format_discounted_price', 'custom_format_discounted_price');
add_action('wp_ajax_nopriv_custom_format_discounted_price', 'custom_format_discounted_price');
function custom_format_discounted_price() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'format_price_nonce')) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }
    
    // Validate and sanitize price
    if (isset($_POST['price']) && is_numeric($_POST['price'])) {
        $price = floatval($_POST['price']);
        echo wc_price($price);
    } else {
        echo wc_price(0);
    }
    
    wp_die();
}
    // End Code Snippet Code

}, 10);