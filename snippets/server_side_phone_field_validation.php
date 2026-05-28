<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

// PHP: אימות שדה הטלפון בצד השרת

add_action('woocommerce_checkout_process', 'validate_phone_number_field');
function validate_phone_number_field() {
    // Get the phone number from the checkout fields
    $phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';
    // Remove all non-numeric characters except '+'
    $cleaned_phone = preg_replace('/[^\d+]/', '', $phone);
    // Validate the phone number
    if (strpos($cleaned_phone, '+972') === 0) {
        if (strlen($cleaned_phone) !== 13) {
            wc_add_notice('מספר הטלפון שמתחיל ב-+972 חייב להכיל 13 ספרות.', 'error');
        }
    } elseif (strpos($cleaned_phone, '972') === 0) {
        if (strlen($cleaned_phone) !== 12) {
            wc_add_notice('מספר הטלפון שמתחיל ב-972 חייב להכיל 12 ספרות.', 'error');
        }
    } elseif (strpos($cleaned_phone, '05') === 0) {
        if (strlen($cleaned_phone) !== 10) {
            wc_add_notice('מספר הטלפון שמתחיל ב-05 חייב להכיל 10 ספרות.', 'error');
        }
    } else {
        wc_add_notice('מספר הטלפון חייב להתחיל ב-05, 972 או +972.', 'error');
    }
}
// JavaScript: אימות ויזואלי בצד הלקוח
add_action('wp_footer', 'add_phone_field_error_handling_script');
function add_phone_field_error_handling_script() {
    if (is_checkout()) : ?>
        <script>
        jQuery(document).ready(function($) {
            // Select the phone input field
            var $phoneInput = $('#billing_phone');
            // Prevent typing of non-numeric characters
            $phoneInput.on('keypress', function(e) {
                var charCode = e.which ? e.which : e.keyCode;
                var char = String.fromCharCode(charCode);
                if (!/[\d+]/.test(char) || (char === '+' && this.value.includes('+'))) {
                    e.preventDefault();
                }
            });
            // Event handler for when the user moves away from the phone input field
            $phoneInput.on('blur', function() {
                var phone = $(this).val();
                var validationResult = validatePhone(phone);
                if (validationResult.isValid) {
                    $(this).css('border', '1px solid #D3D3D3'); // Normal border
                    removePhoneError();
                } else {
                    $(this).css('border', '2px solid red'); // Red border for invalid input
                    showPhoneError(validationResult.message);
                }
            });
            // Function to validate the phone number
            function validatePhone(phone) {
                var cleanedPhone = phone.replace(/[^\d+]/g, '');
                if (cleanedPhone.startsWith('+972')) {
                    if (cleanedPhone.length === 13) {
                        return { isValid: true };
                    } else if (cleanedPhone.length < 13) {
                        return { isValid: false, message: 'היי, חסר לך ' + (13 - cleanedPhone.length) + ' ספרות במספר המתחיל ב-+972.' };
                    } else {
                        return { isValid: false, message: 'היי, יש לך ' + (cleanedPhone.length - 13) + ' ספרות מיותרות במספר המתחיל ב-+972.' };
                    }
                } else if (cleanedPhone.startsWith('972')) {
                    if (cleanedPhone.length === 12) {
                        return { isValid: true };
                    } else if (cleanedPhone.length < 12) {
                        return { isValid: false, message: 'היי, חסר לך ' + (12 - cleanedPhone.length) + ' ספרות במספר המתחיל ב-972.' };
                    } else {
                        return { isValid: false, message: 'היי, יש לך ' + (cleanedPhone.length - 12) + ' ספרות מיותרות במספר המתחיל ב-972.' };
                    }
                } else if (cleanedPhone.startsWith('05')) {
                    if (cleanedPhone.length === 10) {
                        return { isValid: true };
                    } else if (cleanedPhone.length < 10) {
                        return { isValid: false, message: 'היי, חסר לך ' + (10 - cleanedPhone.length) + ' ספרות במספר המתחיל ב-05.' };
                    } else {
                        return { isValid: false, message: 'היי, יש לך ' + (cleanedPhone.length - 10) + ' ספרות מיותרות במספר המתחיל ב-05.' };
                    }
                } else {
                    return { isValid: false, message: 'מספר הטלפון חייב להתחיל ב-05, 972 או +972.' };
                }
            }
            // Function to show an error message under the phone field
            function showPhoneError(message) {
                removePhoneError();
                var $errorDiv = $('<div>', {
                    id: 'phone-error',
                    html: message,
                    css: {
                        padding: '15px 20px',
                        background: '#ff00000d',
                        borderRadius: '0 0 3px 3px',
                        color: '#3b2e2e',
                        fontSize: '14px',
                        cursor: 'pointer',
                        userSelect: 'none',
                        marginTop: '5px'
                    }
                });
                $phoneInput.parent().append($errorDiv);
            }
            // Function to remove the error message
            function removePhoneError() {
                var $errorDiv = $('#phone-error');
                if ($errorDiv.length) {
                    $errorDiv.remove();
                }
                $phoneInput.css('border', '1px solid #D3D3D3'); // Reset border
            }
        });
        </script>
    <?php
    endif;
}

    // End Code Snippet Code

}, 10);