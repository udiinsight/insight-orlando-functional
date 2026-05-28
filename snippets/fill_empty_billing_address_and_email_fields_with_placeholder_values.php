<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

/**
 * Fill empty billing address and email fields with placeholder values
 * Compatible with FunnelKit Checkout
 * 
 * @package Custom WooCommerce
 * @since 1.0.0
 */

// Hook רגיל של WooCommerce
add_action('woocommerce_checkout_create_order', 'custom_fill_empty_fields_for_shipping', 5, 2);

// Hook של FunnelKit - לביטחון
add_action('wfacp_order_created', 'custom_fill_empty_fields_after_funnelkit', 10, 2);

function custom_fill_empty_fields_for_shipping($order, $data) {
    custom_apply_placeholder_values($order);
}

function custom_fill_empty_fields_after_funnelkit($order_id, $order) {
    if (!$order) {
        $order = wc_get_order($order_id);
    }
    if ($order) {
        custom_apply_placeholder_values($order);
        $order->save();
    }
}

function custom_apply_placeholder_values($order) {
    
    // ערכי ברירת מחדל
    $default_address = 'ללא רחוב';
    $default_email = 'noemail@noemail.com';
    
    // מילוי כתובת חיוב
    if (empty(trim($order->get_billing_address_1()))) {
        $order->set_billing_address_1($default_address);
    }
    
    // מילוי כתובת משלוח
    if (empty(trim($order->get_shipping_address_1()))) {
        $billing = $order->get_billing_address_1();
        $order->set_shipping_address_1($billing ?: $default_address);
    }
    
    // מילוי אימייל
    if (empty(trim($order->get_billing_email()))) {
        // ניסיון לקחת מהמשתמש
        $user_id = $order->get_user_id();
        $email = $user_id ? get_userdata($user_id)->user_email ?? '' : '';
        
        $order->set_billing_email($email ?: $default_email);
    }
}
    // End Code Snippet Code

}, 10);