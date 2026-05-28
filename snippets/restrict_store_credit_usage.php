<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * הגבלת שימוש בקרדיט חנות מתחת ל-50 ש"ח - Backend Validation (מתוקן)
 * עבור Advanced Coupons Store Credits
 */

// וודא שהתוסף פעיל
if (!function_exists('ACFWF')) {
    return;
}

// הגדר את הסכום המינימלי (ש"ח)
define('MINIMUM_STORE_CREDIT_AMOUNT', 50);

/**
 * מנע שימוש בקרדיט חנות אם הסכום נמוך מהמינימום
 * משתמש ב-filter אמיתי של המערכת
 */
add_filter('acfw_is_allow_store_credits', 'validate_minimum_store_credit_balance', 10, 1);

function validate_minimum_store_credit_balance($is_allowed) {
    // אם כבר לא מותר, אל תשנה
    if (!$is_allowed) {
        return $is_allowed;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return $is_allowed;
    }
    
    // קבל את יתרת הקרדיט בדרך הנכונה
    $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
    
    // בדוק אם הסכום נמוך מהמינימום
    if ($store_credit_balance > 0 && $store_credit_balance < MINIMUM_STORE_CREDIT_AMOUNT) {
        return false;
    }
    
    return $is_allowed;
}

/**
 * הוסף הודעת שגיאה כאשר מנסים להשתמש בקרדיט נמוך מהמינימום
 */
add_action('woocommerce_coupon_is_valid', 'validate_store_credit_coupon_minimum_amount', 10, 2);

function validate_store_credit_coupon_minimum_amount($valid, $coupon) {
    // בדוק אם זה קופון של קרדיט חנות
    if ($coupon->get_code() === apply_filters('acfw_store_credit_coupon_code', 'store credit')) {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
            
            // בדוק אם הסכום נמוך מהמינימום
            if ($store_credit_balance > 0 && $store_credit_balance < MINIMUM_STORE_CREDIT_AMOUNT) {
                throw new Exception(
                    sprintf(
                        __('מינימום לשימוש בקרדיט חנות הוא %s. הקרדיט הנוכחי שלך הוא %s.', 'textdomain'),
                        wc_price(MINIMUM_STORE_CREDIT_AMOUNT),
                        wc_price($store_credit_balance)
                    )
                );
            }
        }
    }
    
    return $valid;
}

/**
 * הוסף ולידציה נוספת לפני אפליקציית הקרדיט
 */
add_filter('acfw_before_apply_store_credit_discount', 'validate_store_credit_before_apply', 10, 2);

function validate_store_credit_before_apply($sc_data, $session_key) {
    // בדוק אם יש נתוני store credit
    if (!$sc_data || !isset($sc_data['amount'])) {
        return $sc_data;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return $sc_data;
    }
    
    $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
    
    // אם הסכום נמוך מהמינימום, בטל את ה-session
    if ($store_credit_balance > 0 && $store_credit_balance < MINIMUM_STORE_CREDIT_AMOUNT) {
        // הוסף הודעת שגיאה
        if (!wp_doing_ajax()) {
            wc_add_notice(
                sprintf(
                    __('מינימום לשימוש בקרדיט חנות הוא %s. הקרדיט הנוכחי שלך הוא %s.', 'textdomain'),
                    wc_price(MINIMUM_STORE_CREDIT_AMOUNT),
                    wc_price($store_credit_balance)
                ),
                'error'
            );
        }
        
        // בטל את ה-session data
        return null;
    }
    
    return $sc_data;
}

/**
 * הוסף הודעה מידעית על המינימום הנדרש בדף הקופה
 */
add_action('woocommerce_review_order_before_payment', 'display_store_credit_minimum_notice');

function display_store_credit_minimum_notice() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return;
    }
    
    $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
    
    // הצג הודעה אם יש קרדיט אבל הוא נמוך מהמינימום
    if ($store_credit_balance > 0 && $store_credit_balance < MINIMUM_STORE_CREDIT_AMOUNT) {
        $remaining_needed = MINIMUM_STORE_CREDIT_AMOUNT - $store_credit_balance;
        
        echo '<div class="woocommerce-info store-credit-minimum-notice">';
        echo sprintf(
            __('יש לך קרדיט חנות של %1$s. לשימוש בקרדיט נדרש מינימום של %2$s (עוד %3$s).', 'textdomain'),
            '<strong>' . wc_price($store_credit_balance) . '</strong>',
            '<strong>' . wc_price(MINIMUM_STORE_CREDIT_AMOUNT) . '</strong>',
            '<strong>' . wc_price($remaining_needed) . '</strong>'
        );
        echo '</div>';
    }
}

/**
 * הוסף הודעה בתוך form הקרדיט (אם קיים)
 */
add_action('acfw_after_store_credits_checkout_field', 'add_minimum_requirement_message_to_form');

function add_minimum_requirement_message_to_form() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return;
    }
    
    $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
    
    if ($store_credit_balance > 0 && $store_credit_balance < MINIMUM_STORE_CREDIT_AMOUNT) {
        echo '<p class="form-row store-credit-minimum-message" style="color: #d63638; font-size: 0.9em;">';
        echo sprintf(
            __('מינימום נדרש לשימוש: %s', 'textdomain'),
            '<strong>' . wc_price(MINIMUM_STORE_CREDIT_AMOUNT) . '</strong>'
        );
        echo '</p>';
    }
}

/**
 * הוסף נתונים ל-JavaScript עבור הצד הקדמי
 */
add_action('wp_footer', 'add_store_credit_minimum_data_to_js');

function add_store_credit_minimum_data_to_js() {
    if (!is_checkout() && !is_cart()) {
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
    
    ?>
    <script type="text/javascript">
        window.storeCreditData = {
            balance: <?php echo json_encode($store_credit_balance); ?>,
            minimum: <?php echo json_encode(MINIMUM_STORE_CREDIT_AMOUNT); ?>,
            isAboveMinimum: <?php echo json_encode($store_credit_balance >= MINIMUM_STORE_CREDIT_AMOUNT); ?>,
            currency: '<?php echo get_woocommerce_currency_symbol(); ?>',
            minimumFormatted: '<?php echo wp_strip_all_tags(wc_price(MINIMUM_STORE_CREDIT_AMOUNT)); ?>',
            balanceFormatted: '<?php echo wp_strip_all_tags(wc_price($store_credit_balance)); ?>'
        };
    </script>
    <?php
}

/**
 * מניעת הגשת AJAX אם הסכום נמוך מהמינימום
 */
add_action('wp_ajax_acfwf_redeem_store_credits', 'prevent_ajax_redeem_below_minimum', 1);

function prevent_ajax_redeem_below_minimum() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return;
    }
    
    $store_credit_balance = ACFWF()->Store_Credits_Calculate->get_customer_balance($user_id);
    
    // בדוק אם הסכום נמוך מהמינימום
    if ($store_credit_balance > 0 && $store_credit_balance < MINIMUM_STORE_CREDIT_AMOUNT) {
        wp_send_json_error(
            array(
                'message' => sprintf(
                    __('מינימום לשימוש בקרדיט חנות הוא %s. הקרדיט הנוכחי שלך הוא %s.', 'textdomain'),
                    wc_price(MINIMUM_STORE_CREDIT_AMOUNT),
                    wc_price($store_credit_balance)
                )
            )
        );
    }
}
    // End Code Snippet Code

}, 10);