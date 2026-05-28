<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

// הוספת צ'ק בוקס מדיניות פרטיות בהרשמה
add_action('woocommerce_register_form', 'add_privacy_policy_checkbox', 10);
function add_privacy_policy_checkbox() {
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" style="font-size: var(--text-xs); color: var(--base)">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="privacy_policy_accepted" id="privacy_policy_accepted" required />
            <span>אני מאשר.ת את שמירת הפרטים שאמסור בהתאם ל<a href="<?php echo esc_url(get_privacy_policy_url()); ?>" class="woocommerce-privacy-policy-link" target="_blank">מדיניות פרטיות</a> <a href="https://orlando.co.il/privacy-policy/">לעיון בתקנון מועדון הלקוחות</a>&nbsp;<span class="required">*</span></span>
        </label>
    </p>
    <?php
}

// בדיקת אימות הצ'ק בוקס
add_filter('woocommerce_registration_errors', 'validate_privacy_policy_checkbox', 10, 3);
function validate_privacy_policy_checkbox($errors, $username, $email) {
    if (empty($_POST['privacy_policy_accepted'])) {
        $errors->add('privacy_policy_error', __('עליך לאשר את מדיניות הפרטיות כדי להירשם.', 'woocommerce'));
    }
    return $errors;
}

// הסרת הודעת מדיניות הפרטיות המקורית
remove_action('woocommerce_register_form', 'wc_registration_privacy_policy_text', 20);

    // End Code Snippet Code

}, 10);