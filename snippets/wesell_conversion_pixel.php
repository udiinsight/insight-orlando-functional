<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
/**
 * Wesell Conversion Pixel - FunnelKit Thank You Page
 * Embeds conversion tracking script with order total (excluding tax & shipping) and order ID
 * 
 * Calculates price excluding VAT by dividing the subtotal by (1 + tax_rate)
 */

add_action('woocommerce_thankyou', 'orlando_wesell_conversion_pixel', 10, 1);

function orlando_wesell_conversion_pixel($order_id) {
    // Verify order ID exists
    if (!$order_id) {
        return;
    }
    
    // Get order object
    $order = wc_get_order($order_id);
    
    // Verify order is valid
    if (!$order) {
        return;
    }
    
    // Get subtotal (includes tax in your case)
    $subtotal_inc_tax = $order->get_subtotal();
    
    // Calculate total excluding tax
    // If prices include tax, we need to calculate backwards
    if (wc_prices_include_tax()) {
        // Get the tax rate (default 17% for Israel, adjust if different)
        $tax_rate = 0.18; // 17% VAT
        
        // Calculate price without tax: price_with_tax / (1 + tax_rate)
        $total = $subtotal_inc_tax / (1 + $tax_rate);
        $total = round($total, 2); // Round to 2 decimals
    } else {
        // If prices don't include tax, use subtotal as is
        $total = $subtotal_inc_tax;
    }
    
    // Get order number
    $ext_id = $order->get_order_number();
    
    // Output the Wesell conversion script
    ?>
    <script type="text/javascript" src="https://track.wesell.co.il/conversionFirstParty/4oo5vl496po/oJwvDTDo7nY/json?total=<?php echo esc_js($total); ?>&extID=<?php echo esc_js($ext_id); ?>"></script>
    <?php
}
    // End Code Snippet Code

}, 10);