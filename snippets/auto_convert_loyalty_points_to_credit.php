<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * המרה אוטומטית של נקודות נאמנות לקרדיט בחנות
 * עבור Advanced Coupons Loyalty Program
 */

// וודא שהתוסף פעיל
if (!function_exists('LPFW')) {
    return;
}

/**
 * המר נקודות חדשות לקרדיט באופן אוטומטי
 * מופעל בכל פעם שמסתמן על נקודות שמשתנה
 */
add_action('lpfw_loyalty_points_total_changed', 'auto_convert_points_to_store_credit', 10, 1);

function auto_convert_points_to_store_credit($point_entry) {
    // וודא שזה כניסת נקודות חדשה ולא הורדה
    if (!$point_entry || 'earn' !== $point_entry->get_prop('type')) {
        return;
    }
    
    $user_id = $point_entry->get_prop('user_id');
    $earned_points = $point_entry->get_prop('points');
    
    // וודא שיש נקודות להמיר
    if (!$user_id || !$earned_points || $earned_points <= 0) {
        return;
    }
    
    // קבל את סך הנקודות הנוכחי של המשתמש
    $current_points = LPFW()->Calculate->get_user_total_points($user_id);
    
    // וודא שיש נקודות להמיר
    if ($current_points <= 0) {
        return;
    }
    
    // חשב את שווי הנקודות בכסף
    $credit_amount = LPFW()->Calculate->calculate_redeem_points_worth($current_points, false);
    
    // המר את כל הנקודות לקרדיט חנות
    $store_credit_entry = create_store_credit_from_points($user_id, $current_points, $credit_amount);
    
    if ($store_credit_entry && !is_wp_error($store_credit_entry)) {
        // הורד את הנקודות שהומרו
        $loyalty_entry_id = LPFW()->Entries->decrease_points(
            $user_id, 
            $current_points, 
            'store_credits', 
            $store_credit_entry->get_id(),
            'Auto-converted to store credit'
        );
        
        // עדכן את הקשר בין רשומת הקרדיט לרשומת הנקודות
        if (!is_wp_error($loyalty_entry_id)) {
            $store_credit_entry->set_prop('object_id', absint($loyalty_entry_id));
            $store_credit_entry->save();
            
            // הוסף הודעה ללוג (אופציונלי)
            error_log(sprintf(
                'Auto-converted %d loyalty points to %s store credit for user %d',
                $current_points,
                wc_price($credit_amount),
                $user_id
            ));
        }
    }
}

/**
 * יצירת רשומת קרדיט חנות מנקודות נאמנות
 */
function create_store_credit_from_points($user_id, $points, $amount) {
    // וודא שקיים המחלקה של קרדיט חנות
    if (!class_exists('ACFWF\Models\Objects\Store_Credit_Entry')) {
        return new WP_Error('missing_class', 'Store Credit Entry class not found');
    }
    
    $store_credit_entry = new ACFWF\Models\Objects\Store_Credit_Entry();
    
    $store_credit_entry->set_prop('amount', (float) $amount);
    $store_credit_entry->set_prop('user_id', $user_id);
    $store_credit_entry->set_prop('type', 'increase');
    $store_credit_entry->set_prop('action', 'loyalty_points');
    
    $result = $store_credit_entry->save();
    
    return !is_wp_error($result) ? $store_credit_entry : $result;
}

/**
 * אופציה להוסיף הגדרה למינימום נקודות להמרה (אופציונלי)
 */
add_filter('lpfw_minimum_points_for_auto_conversion', function() {
    return 0; // שנה למספר הנקודות המינימלי שתרצה
});

/**
 * אופציה לביטול ההמרה האוטומטית למשתמשים מסוימים (אופציונלי)
 */
add_filter('lpfw_should_auto_convert_points', 'should_auto_convert_for_user', 10, 2);

function should_auto_convert_for_user($should_convert, $user_id) {
    // דוגמה: לא להמיר למנהלים
    $user = get_userdata($user_id);
    if ($user && in_array('administrator', $user->roles)) {
        return false;
    }
    
    // דוגמה: בדיקת מטא נוסף
    $disable_auto_convert = get_user_meta($user_id, 'disable_auto_convert_points', true);
    if ('yes' === $disable_auto_convert) {
        return false;
    }
    
    return $should_convert;
}

    // End Code Snippet Code

}, 10);