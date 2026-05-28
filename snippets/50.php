<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Hide Store Credit Form Below Minimum Amount
 * הסתרת טופס קרדיט אם יש פחות מ-50 ש"ח זמין
 * 
 * wpCodeBox2 Settings:
 * - Code Type: PHP
 * - Location: Frontend
 * - Priority: 10
 */

// 1. הסתרת טופס הקרדיט אם אין מספיק יתרה
add_filter('acfw_show_store_credit_form_checkout', 'hide_store_credit_form_below_minimum');
add_filter('acfw_show_store_credit_form_my_account', 'hide_store_credit_form_below_minimum');
function hide_store_credit_form_below_minimum($show_form) {
    $minimum_credit_usage = 50;
    $customer_id = get_current_user_id();
    
    if (!$customer_id) {
        return false; // לא מחובר = לא מראים
    }
    
    // קבלת יתרת הקרדיט של הלקוח
    $customer_credit_balance = get_user_meta($customer_id, '_acfw_store_credit_balance', true);
    
    if (!$customer_credit_balance) {
        $customer_credit_balance = 0;
    }
    
    // הסתרת הטופס אם היתרה נמוכה מהמינימום
    if ($customer_credit_balance < $minimum_credit_usage) {
        return false;
    }
    
    return $show_form;
}

// 2. הוספת הודעה במקום הטופס המוסתר
add_action('acfw_after_store_credit_form_checkout', 'display_minimum_credit_message_checkout');
add_action('acfw_after_store_credit_form_my_account', 'display_minimum_credit_message_my_account');
function display_minimum_credit_message_checkout() {
    display_minimum_credit_message('checkout');
}

function display_minimum_credit_message_my_account() {
    display_minimum_credit_message('my_account');
}

function display_minimum_credit_message($location) {
    $minimum_credit_usage = 50;
    $customer_id = get_current_user_id();
    
    if (!$customer_id) return;
    
    $customer_credit_balance = get_user_meta($customer_id, '_acfw_store_credit_balance', true);
    if (!$customer_credit_balance) {
        $customer_credit_balance = 0;
    }
    
    // הצגת הודעה רק אם יש קרדיט אבל פחות מהמינימום
    if ($customer_credit_balance > 0 && $customer_credit_balance < $minimum_credit_usage) {
        $message = sprintf(
            'יש לך %s ש"ח קרדיט זמין, אך מינימום שימוש הוא %s ש"ח. צבור עוד %s ש"ח כדי להשתמש בקרדיט.',
            number_format($customer_credit_balance, 2, '.', ','),
            number_format($minimum_credit_usage, 0, '.', ','),
            number_format($minimum_credit_usage - $customer_credit_balance, 2, '.', ',')
        );
        
        $css_class = ($location === 'checkout') ? 'wfacp-info-msg' : 'woocommerce-info';
        
        echo '<div class="store-credit-minimum-info ' . $css_class . '" style="background: #f0f8ff; border-left: 4px solid #007cba; padding: 12px 15px; margin: 15px 0; border-radius: 4px;">';
        echo '<strong>קרדיט חנות:</strong> ' . $message;
        echo '</div>';
    }
}

// 3. הסתרה ספציפית ל-FunnelKit Checkout
add_action('wp_footer', 'hide_store_credit_funnelkit_below_minimum');
function hide_store_credit_funnelkit_below_minimum() {
    // רק בדפי FunnelKit Checkout
    if (!function_exists('wfacp_is_checkout_page') || !wfacp_is_checkout_page()) {
        return;
    }
    
    $minimum_credit_usage = 50;
    $customer_id = get_current_user_id();
    
    if (!$customer_id) return;
    
    $customer_credit_balance = get_user_meta($customer_id, '_acfw_store_credit_balance', true);
    if (!$customer_credit_balance) {
        $customer_credit_balance = 0;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        const customerCredit = <?php echo floatval($customer_credit_balance); ?>;
        const minimumCredit = <?php echo $minimum_credit_usage; ?>;
        
        function hideStoreCreditForms() {
            if (customerCredit < minimumCredit) {
                // הסתרת כל טפסי הקרדיט
                $('.acfw-checkout-store-credit-field, .acfw-store-credit-form, .store-credit-form, [data-section="store_credit"]').hide();
                
                // הסתרת שורות בטבלה
                $('tr:contains("Store Credit"), tr:contains("קרדיט"), .store-credit-row').hide();
                
                // הסתרת כפתורים
                $('.acfw-apply-store-credit-btn, [data-action*="store_credit"]').hide();
                
                // הוספת הודעה אם יש קרדיט אבל לא מספיק
                if (customerCredit > 0) {
                    const needed = minimumCredit - customerCredit;
                    const message = 'יש לך ' + customerCredit.toFixed(2) + ' ש"ח קרדיט זמין, אך מינימום שימוש הוא ' + minimumCredit + ' ש"ח. ' +
                                  'צבור עוד ' + needed.toFixed(2) + ' ש"ח כדי להשתמש בקרדיט.';
                    
                    // הוספת הודעה לדף
                    if (!$('.store-credit-minimum-notice').length) {
                        const noticeHtml = '<div class="store-credit-minimum-notice wfacp-info-msg" style="background: #f0f8ff; border-left: 4px solid #007cba; padding: 12px 15px; margin: 15px 0; border-radius: 4px;">' +
                            '<strong>קרדיט חנות:</strong> ' + message +
                            '</div>';
                        
                        // מציאת המיקום הנכון באזור הקופונים והקרדיט
                        let targetLocation = $('.checkout-credits .wfacp-comm-form-detail');
                        
                        // אם לא נמצא, נסה מיקומים נוספים באזור הקופונים
                        if (!targetLocation.length) {
                            targetLocation = $('.checkout-credits .wfacp-row').first();
                        }
                        if (!targetLocation.length) {
                            targetLocation = $('.wfacp-section.checkout-credits').first();
                        }
                        if (!targetLocation.length) {
                            targetLocation = $('h2:contains("קופונים וקאשבק")').parent();
                        }
                        
                        // אם עדיין לא נמצא, נסה את המיקומים הכלליים
                        if (!targetLocation.length) {
                            targetLocation = $('.wfacp-payment-section, .wfacp_main_form, #payment').first();
                        }
                        
                        if (targetLocation.length) {
                            // הוספת ההודעה בתחילת האזור
                            targetLocation.prepend(noticeHtml);
                        }
                    }
                }
            }
        }
        
        // הסתרה מיידית
        hideStoreCreditForms();
        
        // הסתרה אחרי עדכונים
        $(document.body).on('updated_checkout wfacp_checkout_updated wfacp_step_loaded', function() {
            setTimeout(hideStoreCreditForms, 100);
        });
    });
    </script>
    
    <style>
    /* הסתרה נוספת ב-CSS למקרי חירום */
    body.customer-credit-below-minimum .acfw-checkout-store-credit-field,
    body.customer-credit-below-minimum .acfw-store-credit-form,
    body.customer-credit-below-minimum .store-credit-form {
        display: none !important;
    }
    
    .store-credit-minimum-notice {
        font-size: 14px;
        line-height: 1.5;
    }
    
    /* התאמה לעיצוב FunnelKit באזור הקופונים */
    .store-credit-minimum-notice .wfacp-coupon-page {
        background: #f0f8ff !important;
    }
    
    .store-credit-minimum-notice .woocommerce-info {
        font-size: 14px !important;
        font-weight: normal !important;
    }
    </style>
    
    <?php
    // הוספת class ל-body אם הקרדיט נמוך
    if ($customer_credit_balance < $minimum_credit_usage) {
        echo '<script>document.body.classList.add("customer-credit-below-minimum");</script>';
    }
}

// 4. הסתרה בעמוד My Account
add_action('wp_footer', 'hide_store_credit_my_account_below_minimum');
function hide_store_credit_my_account_below_minimum() {
    if (!is_account_page()) return;
    
    $minimum_credit_usage = 50;
    $customer_id = get_current_user_id();
    
    if (!$customer_id) return;
    
    $customer_credit_balance = get_user_meta($customer_id, '_acfw_store_credit_balance', true);
    if (!$customer_credit_balance) {
        $customer_credit_balance = 0;
    }
    
    if ($customer_credit_balance < $minimum_credit_usage) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // הסתרת טופס מימוש קרדיט בעמוד My Account
            $('.acfw-store-credit-redeem-form, .store-credit-redeem-form, [data-form="store_credit_redeem"]').hide();
            
            // הוספת הודעה
            const customerCredit = <?php echo floatval($customer_credit_balance); ?>;
            const minimumCredit = <?php echo $minimum_credit_usage; ?>;
            
            if (customerCredit > 0) {
                const needed = minimumCredit - customerCredit;
                const message = 'יש לך ' + customerCredit.toFixed(2) + ' ש"ח קרדיט זמין, אך מינימום שימוש הוא ' + minimumCredit + ' ש"ח. ' +
                              'צבור עוד ' + needed.toFixed(2) + ' ש"ח כדי להשתמש בקרדיט.';
                
                $('.acfw-store-credit-balance, .store-credit-balance').after(
                    '<div class="woocommerce-info store-credit-info" style="margin: 15px 0;">' + message + '</div>'
                );
            }
        });
        </script>
        <?php
    }
}

// 5. פונקציה לקבלת יתרת קרדיט (גיבוי למקרה של שמות meta שונים)
function get_customer_store_credit_balance($customer_id) {
    // ניסיון קבלת היתרה במספר דרכים
    $balance_keys = array(
        '_acfw_store_credit_balance',
        'acfw_store_credit_balance', 
        '_store_credit_balance',
        'store_credit_balance'
    );
    
    foreach ($balance_keys as $key) {
        $balance = get_user_meta($customer_id, $key, true);
        if ($balance && is_numeric($balance)) {
            return floatval($balance);
        }
    }
    
    // אם לא נמצא, נסה לחשב מהיסטוריית הקרדיט
    if (function_exists('ACFW')) {
        // Advanced Coupons API
        $balance = apply_filters('acfw_get_customer_store_credit_balance', 0, $customer_id);
        if ($balance > 0) {
            return floatval($balance);
        }
    }
    
    return 0;
}

// 6. עדכון הפונקציות לשימוש בפונקציה החדשה
add_filter('acfw_show_store_credit_form_checkout', 'improved_hide_store_credit_form_below_minimum');
add_filter('acfw_show_store_credit_form_my_account', 'improved_hide_store_credit_form_below_minimum');
function improved_hide_store_credit_form_below_minimum($show_form) {
    $minimum_credit_usage = 50;
    $customer_id = get_current_user_id();
    
    if (!$customer_id) {
        return false;
    }
    
    $customer_credit_balance = get_customer_store_credit_balance($customer_id);
    
    if ($customer_credit_balance < $minimum_credit_usage) {
        return false;
    }
    
    return $show_form;
}

// 7. הודעה בדף העגלה (אם יש שם קרדיט)
add_action('woocommerce_before_cart_contents', 'show_store_credit_info_in_cart');
function show_store_credit_info_in_cart() {
    $minimum_credit_usage = 50;
    $customer_id = get_current_user_id();
    
    if (!$customer_id) return;
    
    $customer_credit_balance = get_customer_store_credit_balance($customer_id);
    
    if ($customer_credit_balance > 0 && $customer_credit_balance < $minimum_credit_usage) {
        $needed = $minimum_credit_usage - $customer_credit_balance;
        
        wc_print_notice(sprintf(
            'יש לך %s ש"ח קרדיט זמין. צבור עוד %s ש"ח כדי להשתמש בקרדיט (מינימום %s ש"ח).',
            number_format($customer_credit_balance, 2, '.', ','),
            number_format($needed, 2, '.', ','),
            number_format($minimum_credit_usage, 0, '.', ',')
        ), 'notice');
    }
}
    // End Code Snippet Code

}, 10);