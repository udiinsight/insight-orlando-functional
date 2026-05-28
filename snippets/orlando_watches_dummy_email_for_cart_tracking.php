<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
/**
 * Orlando Watches — Dummy Email for Cart Tracking (Server-side)
 * 
 * Handles two things:
 * 1. Enqueues the frontend JS that injects dummy emails on checkout
 * 2. Prevents WooCommerce from sending transactional emails to dummy addresses
 * 3. Blacklists dummy email domain from FunnelKit email broadcasts
 * 
 * @package Orlando Watches
 * @since 1.0.0
 * 
 *  wpCodeBox2 Settings:
 * ├── Title: Orlando - Dummy Email Cart Tracking
 * ├── Code Type: PHP
 * ├── Location: Everywhere
 * ├── Hook: init
 * ├── Priority: 10
 * └── Conditions: None (has internal checkout page check)
 */

// Configuration
if (!defined('ORLANDO_DUMMY_EMAIL_DOMAIN')) {
    define('ORLANDO_DUMMY_EMAIL_DOMAIN', 'noemail.orlando.co.il');
}

/**
 * Check if an email is a dummy/generated email
 */
function orlando_is_dummy_email($email) {
    if (empty($email)) return false;
    return (strpos($email, '@' . ORLANDO_DUMMY_EMAIL_DOMAIN) !== false);
}

/**
 * Enqueue the frontend JS on checkout pages
 */
add_action('wp_enqueue_scripts', 'orlando_enqueue_dummy_email_js', 20);
function orlando_enqueue_dummy_email_js() {
    // Only on checkout page
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    
    // Inline script approach — no external file needed
    // The JS is self-contained and small enough for inline
    wp_add_inline_script('wc-checkout', orlando_get_dummy_email_js());
}

/**
 * Return the inline JS code
 * This is the minified/essential version — see orlando-dummy-email-inject.js for commented version
 */
function orlando_get_dummy_email_js() {
    $dummy_domain = ORLANDO_DUMMY_EMAIL_DOMAIN;
    
    return <<<JS
(function(){
  'use strict';
  var DOMAIN='{$dummy_domain}',
      eId='billing_email', pId='billing_phone', fId='wfacp_checkout_form';

  function clean(p){return p.replace(/[^0-9]/g,'')}
  function genEmail(p){var c=clean(p);return c.length>=9?c+'@'+DOMAIN:''}
  function isReal(v){return v&&v.trim()&&v.indexOf('@'+DOMAIN)<0&&/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(v.trim())}
  function shouldInject(){
    var e=document.getElementById(eId),p=document.getElementById(pId);
    return e&&p&&!isReal(e.value.trim())&&clean(p.value.trim()).length>=9;
  }
  function inject(){
    if(!shouldInject())return false;
    var e=document.getElementById(eId),p=document.getElementById(pId),d=genEmail(p.value);
    if(!d)return false;
    e.value=d;
    ['change','blur','focusout'].forEach(function(t){e.dispatchEvent(new Event(t,{bubbles:true}))});
    if(typeof jQuery!=='undefined'){jQuery(e).trigger('change').trigger('blur').trigger('focusout')}
    return true;
  }

  function init(){
    var f=document.getElementById(fId);if(!f)return;

    // Scenario 1: Place Order click (CRITICAL — payment page abandonment)
    if(typeof jQuery!=='undefined'){
      jQuery(f).on('checkout_place_order',function(){inject();return true});
      jQuery(f).on('submit',function(){inject()});
    }

    // Scenario 2: Click navigation links
    document.addEventListener('click',function(ev){
      var a=ev.target.closest('a');
      if(!a)return;
      var h=a.getAttribute('href');
      if(!h||h==='#'||h.indexOf('javascript:')===0)return;
      if(a.closest('.wfacp_main_btn')||a.id==='place_order')return;
      if(!shouldInject())return;
      ev.preventDefault();inject();
      setTimeout(function(){window.location.href=h},800);
    },true);

    // Scenario 3: Tab close / browser back
    document.addEventListener('visibilitychange',function(){
      if(document.visibilityState==='hidden'&&shouldInject()){
        inject();
        if(navigator.sendBeacon){
          var p=document.getElementById(pId),n=document.querySelector('#billing_first_name'),
              d=genEmail(p?p.value:'');
          if(d){
            var fd=new FormData();
            fd.append('action','bwfan_insert_abandoned_cart');
            fd.append('email',d);
            fd.append('checkout_fields_data[billing_email]',d);
            fd.append('checkout_fields_data[billing_phone]',p?p.value:'');
            fd.append('checkout_fields_data[billing_first_name]',n?n.value:'');
            navigator.sendBeacon('/wp-admin/admin-ajax.php',fd);
          }
        }
      }
    });
  }

  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init)}else{init()}
})();
JS;
}

/**
 * Prevent WooCommerce from sending transactional emails to dummy addresses.
 * This stops order confirmation, processing, etc. from going to fake emails.
 */
add_filter('woocommerce_email_recipient_customer_processing_order', 'orlando_block_dummy_email_recipient', 10, 2);
add_filter('woocommerce_email_recipient_customer_completed_order', 'orlando_block_dummy_email_recipient', 10, 2);
add_filter('woocommerce_email_recipient_customer_on_hold_order', 'orlando_block_dummy_email_recipient', 10, 2);
add_filter('woocommerce_email_recipient_customer_refunded_order', 'orlando_block_dummy_email_recipient', 10, 2);
add_filter('woocommerce_email_recipient_customer_invoice', 'orlando_block_dummy_email_recipient', 10, 2);
add_filter('woocommerce_email_recipient_customer_note', 'orlando_block_dummy_email_recipient', 10, 2);
function orlando_block_dummy_email_recipient($recipient, $order) {
    if (orlando_is_dummy_email($recipient)) {
        return ''; // Empty string prevents email from being sent
    }
    return $recipient;
}

/**
 * Prevent FunnelKit Automations from sending emails to dummy addresses.
 * The FunnelKit Send Email action checks the contact email.
 */
add_filter('bwfan_send_email_to', 'orlando_block_dummy_funnelkit_email', 10, 2);
function orlando_block_dummy_funnelkit_email($email, $data) {
    if (orlando_is_dummy_email($email)) {
        return ''; // Block email sending
    }
    return $email;
}

/**
 * Optional: Mark orders with dummy emails in admin for easy identification
 * Adds a note to the order so you know this was a phone-only customer
 */
add_action('woocommerce_new_order', 'orlando_flag_dummy_email_order', 10, 1);
function orlando_flag_dummy_email_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $billing_email = $order->get_billing_email();
    if (orlando_is_dummy_email($billing_email)) {
        $order->add_order_note('⚠️ לקוח ללא מייל — כתובת מייל אוטומטית נוצרה לצורך מעקב עגלה נטושה. יש ליצור קשר בטלפון בלבד.');
        
        // Optional: Add custom meta for filtering in admin
        $order->update_meta_data('_orlando_phone_only_customer', 'yes');
        $order->save();
    }
}

/**
 * Debug logging (only when WP_DEBUG is enabled)
 */
function orlando_dummy_email_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $entry = '[Orlando Dummy Email] ' . $message;
        if ($data !== null) {
            $entry .= ' | ' . print_r($data, true);
        }
        error_log($entry);
    }
}

    // End Code Snippet Code

}, 10);