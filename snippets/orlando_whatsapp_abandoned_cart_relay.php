<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Orlando Watches - WhatsApp Relay via 360dialog
 *
 * Intercepts FunnelKit's outgoing HTTP requests to 360dialog,
 * transforms flat key-value data into proper nested WhatsApp JSON,
 * and dispatches to the correct template based on the 'template' key.
 *
 * Supported automations:
 *  - abandoned_cart_v2  → phone, name, cart_link
 *  - year_offer              → phone, coupon_code
 *  - birthday                → phone, coupon_code
 *
 * @package Orlando Watches
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// CONFIGURATION
// ============================================================

if ( ! defined( 'ORLANDO_WA_API_KEY' ) ) {
    define( 'ORLANDO_WA_API_KEY', 'GI1f82YeUbbcdIcOZJQDOn22AK' );
}
if ( ! defined( 'ORLANDO_WA_ENDPOINT' ) ) {
    define( 'ORLANDO_WA_ENDPOINT', 'https://waba-v2.360dialog.io/marketing_messages' );
}
if ( ! defined( 'ORLANDO_WA_LANG' ) ) {
    define( 'ORLANDO_WA_LANG', 'he' );
}

// ============================================================
// INTERCEPT OUTGOING HTTP REQUESTS TO 360DIALOG
// ============================================================

add_filter( 'pre_http_request', 'orlando_wa_intercept_request', 10, 3 );

/**
 * Intercept wp_remote_post calls to 360dialog, build the correct
 * WhatsApp template payload for each automation, then fire the
 * real API call and return the response to FunnelKit.
 *
 * @param false|array|WP_Error $preempt
 * @param array                $args
 * @param string               $url
 * @return false|array|WP_Error
 */
function orlando_wa_intercept_request( $preempt, $args, $url ) {

    // Only intercept 360dialog requests
    if ( strpos( $url, 'waba-v2.360dialog.io' ) === false ) {
        return $preempt;
    }

    orlando_wa_log( '=== Intercepted request to 360dialog ===' );
    orlando_wa_log( 'URL: ' . $url );
    orlando_wa_log( 'Body: ' . print_r( $args['body'], true ) );

    // Parse the flat body FunnelKit sends
    $data = array();
    if ( is_string( $args['body'] ) ) {
        $json_test = json_decode( $args['body'], true );
        if ( json_last_error() === JSON_ERROR_NONE && isset( $json_test['messaging_product'] ) ) {
            orlando_wa_log( 'Body is already valid WhatsApp JSON — skipping transform.' );
            return $preempt;
        }
        // Use a robust parser instead of parse_str() — parse_str() breaks when
        // values contain '=' or "'" characters (e.g. wc_dynamic_coupon merge tags).
        $data = orlando_wa_parse_body( $args['body'] );
    } elseif ( is_array( $args['body'] ) ) {
        $data = $args['body'];
    }

    // Require at least a phone number
    if ( empty( $data['phone'] ) ) {
        orlando_wa_log( 'Not a WhatsApp request (no phone key) — skipping.' );
        return $preempt;
    }

    // ----------------------------------------------------------
    // Common fields
    // ----------------------------------------------------------
    $phone    = orlando_wa_clean_phone( sanitize_text_field( $data['phone'] ) );
    $template = isset( $data['template'] ) ? sanitize_key( $data['template'] ) : 'abandoned_cart_v2';

    if ( empty( $phone ) ) {
        orlando_wa_log( 'ERROR: Phone number could not be cleaned.' );
        return new WP_Error( 'missing_phone', 'WhatsApp relay: invalid phone number.' );
    }

    // ----------------------------------------------------------
    // Build template-specific components
    // ----------------------------------------------------------
    $components = array();

    if ( 'year_offer' === $template ) {

        $coupon = isset( $data['coupon_code'] ) ? sanitize_text_field( $data['coupon_code'] ) : '';

        if ( empty( $coupon ) ) {
            orlando_wa_log( 'ERROR: year_offer missing coupon_code.' );
            return new WP_Error( 'missing_coupon', 'WhatsApp relay: missing coupon_code for year_offer.' );
        }

        $components = array(
            array(
                'type'       => 'body',
                'parameters' => array(
                    array( 'type' => 'text', 'text' => $coupon ),
                ),
            ),
        );

        orlando_wa_log( 'Template: year_offer | Phone: ' . $phone . ' | Coupon: ' . $coupon );

    } elseif ( 'birthday' === $template ) {

        $coupon = isset( $data['coupon_code'] ) ? sanitize_text_field( $data['coupon_code'] ) : '';

        if ( empty( $coupon ) ) {
            orlando_wa_log( 'ERROR: birthday missing coupon_code.' );
            return new WP_Error( 'missing_coupon', 'WhatsApp relay: missing coupon_code for birthday.' );
        }

        $components = array(
            array(
                'type'       => 'body',
                'parameters' => array(
                    array( 'type' => 'text', 'text' => $coupon ),
                ),
            ),
        );

        orlando_wa_log( 'Template: birthday | Phone: ' . $phone . ' | Coupon: ' . $coupon );

    } else {
        // Default: abandoned cart (name + cart_link)
        $template  = 'abandoned_cart_v2';
        $name      = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : 'לקוח';
        $cart_link = isset( $data['cart_link'] ) ? esc_url_raw( $data['cart_link'] ) : '';

        if ( empty( $cart_link ) ) {
            orlando_wa_log( 'ERROR: abandoned_cart missing cart_link.' );
            return new WP_Error( 'missing_cart_link', 'WhatsApp relay: missing cart_link for abandoned cart.' );
        }

        if ( empty( $name ) ) {
            $name = 'לקוח';
        }

        $cart_link = orlando_wa_validate_cart_link( $cart_link, $phone );

        $components = array(
            array(
                'type'       => 'body',
                'parameters' => array(
                    array( 'type' => 'text', 'text' => $name ),
                    array( 'type' => 'text', 'text' => $cart_link ),
                ),
            ),
        );

        orlando_wa_log( 'Template: abandoned_cart | Phone: ' . $phone . ' | Name: ' . $name . ' | Link: ' . $cart_link );
    }

    // ----------------------------------------------------------
    // Build the full payload
    // ----------------------------------------------------------
    $payload = array(
        'messaging_product'       => 'whatsapp',
        'recipient_type'          => 'individual',
        'to'                      => $phone,
        'type'                    => 'template',
        'message_activity_sharing' => true,
        'template'                => array(
            'name'       => $template,
            'language'   => array(
                'code'   => ORLANDO_WA_LANG,
                'policy' => 'deterministic',
            ),
            'components' => $components,
        ),
    );

    orlando_wa_log( 'Payload: ' . wp_json_encode( $payload ) );

    // Temporarily remove filter to prevent infinite loop
    remove_filter( 'pre_http_request', 'orlando_wa_intercept_request', 10 );

    $response = wp_remote_post(
        ORLANDO_WA_ENDPOINT,
        array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'D360-API-KEY' => ORLANDO_WA_API_KEY,
            ),
            'body' => wp_json_encode( $payload ),
        )
    );

    // Re-add filter
    add_filter( 'pre_http_request', 'orlando_wa_intercept_request', 10, 3 );

    // Log response
    if ( is_wp_error( $response ) ) {
        orlando_wa_log( 'WP_Error: ' . $response->get_error_message() );
    } else {
        orlando_wa_log( 'HTTP ' . wp_remote_retrieve_response_code( $response ) . ': ' . wp_remote_retrieve_body( $response ) );
    }

    return $response;
}

// ============================================================
// HELPERS
// ============================================================

/**
 * Parse a URL-encoded body string into a key=>value array.
 *
 * Unlike parse_str(), this handles values that contain '=' or "'"
 * characters, which appear in WooCommerce dynamic coupon merge tags
 * like {{'wc_dynamic_coupon id='69'}}.
 *
 * @param string $body Raw URL-encoded body string.
 * @return array
 */
function orlando_wa_parse_body( $body ) {
    $result = array();
    $pairs  = explode( '&', $body );

    foreach ( $pairs as $pair ) {
        // Split only on the FIRST '=' so values containing '=' are preserved
        $pos = strpos( $pair, '=' );
        if ( $pos === false ) {
            continue;
        }
        $key   = urldecode( substr( $pair, 0, $pos ) );
        $value = urldecode( substr( $pair, $pos + 1 ) );

        if ( $key !== '' ) {
            $result[ $key ] = $value;
        }
    }

    return $result;
}

/**
 * Normalize a phone number to the 972XXXXXXXXX format.
 *
 * Handles: +972..., 972..., 05X..., 5X... (with any spaces/dashes)
 *
 * @param string $phone Raw phone input.
 * @return string Cleaned phone or empty string if invalid.
 */
function orlando_wa_clean_phone( $phone ) {
    $phone = preg_replace( '/[^0-9]/', '', $phone );

    if ( empty( $phone ) ) {
        return '';
    }

    // Remove leading 972 then re-add cleanly
    if ( substr( $phone, 0, 3 ) === '972' ) {
        $phone = substr( $phone, 3 );
    }

    // Remove leading 0 (Israeli local format)
    if ( substr( $phone, 0, 1 ) === '0' ) {
        $phone = substr( $phone, 1 );
    }

    return '972' . $phone;
}

/**
 * Validate that the cart recovery link belongs to the recipient phone.
 *
 * Extracts the bwfan-ab-id token from the URL, looks up the abandoned cart
 * row in the DB, and compares the stored phone with the recipient. If they
 * don't match, fetches the correct cart for this phone and rebuilds the URL.
 *
 * @param string $cart_link The recovery URL from FunnelKit.
 * @param string $phone     Cleaned 972XXXXXXXXX phone.
 * @return string Validated (or corrected) cart recovery URL.
 */
function orlando_wa_validate_cart_link( $cart_link, $phone ) {
    global $wpdb;

    $parsed = wp_parse_url( $cart_link );
    if ( empty( $parsed['query'] ) ) {
        return $cart_link;
    }

    parse_str( $parsed['query'], $params );
    $token = isset( $params['bwfan-ab-id'] ) ? $params['bwfan-ab-id'] : '';

    if ( empty( $token ) ) {
        return $cart_link;
    }

    $cart_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ID, email, checkout_data FROM {$wpdb->prefix}bwfan_abandonedcarts WHERE token = %s LIMIT 1",
            $token
        ),
        ARRAY_A
    );

    if ( empty( $cart_row ) ) {
        orlando_wa_log( 'VALIDATE: Token not found in DB — sending as-is.' );
        return $cart_link;
    }

    $cart_phone = orlando_wa_extract_phone_from_cart( $cart_row );
    $cart_phone_clean = orlando_wa_clean_phone( $cart_phone );

    if ( $cart_phone_clean === $phone ) {
        orlando_wa_log( 'VALIDATE: Token matches phone ✓' );
        return $cart_link;
    }

    orlando_wa_log( 'VALIDATE: MISMATCH! Token phone=' . $cart_phone_clean . ' but recipient=' . $phone . '. Searching for correct cart...' );

    $local_phone = substr( $phone, 3 );
    $correct_cart = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT token FROM {$wpdb->prefix}bwfan_abandonedcarts
             WHERE (checkout_data LIKE %s OR checkout_data LIKE %s OR email LIKE %s)
             AND status IN (0, 1, 4)
             ORDER BY last_modified DESC LIMIT 1",
            '%' . $wpdb->esc_like( $phone ) . '%',
            '%' . $wpdb->esc_like( '0' . $local_phone ) . '%',
            '%' . $wpdb->esc_like( $local_phone ) . '%'
        ),
        ARRAY_A
    );

    if ( empty( $correct_cart ) || empty( $correct_cart['token'] ) ) {
        orlando_wa_log( 'VALIDATE: No matching cart found for phone ' . $phone . ' — sending original link.' );
        return $cart_link;
    }

    $base_url = strtok( $cart_link, '?' );
    $new_link = add_query_arg( 'bwfan-ab-id', $correct_cart['token'], $base_url );
    if ( ! empty( $params['bwfan-coupon'] ) ) {
        $new_link = add_query_arg( 'bwfan-coupon', $params['bwfan-coupon'], $new_link );
    }
    if ( ! empty( $params['bwfan-uid'] ) ) {
        $new_link = add_query_arg( 'bwfan-uid', $params['bwfan-uid'], $new_link );
    }
    if ( ! empty( $params['automation-id'] ) ) {
        $new_link = add_query_arg( 'automation-id', $params['automation-id'], $new_link );
    }

    orlando_wa_log( 'VALIDATE: Corrected link from token ' . $token . ' to ' . $correct_cart['token'] );
    return $new_link;
}

/**
 * Extract the billing phone from an abandoned cart row's checkout_data JSON.
 *
 * @param array $cart_row Row from wp_bwfan_abandonedcarts.
 * @return string Phone number or empty string.
 */
function orlando_wa_extract_phone_from_cart( $cart_row ) {
    if ( empty( $cart_row['checkout_data'] ) ) {
        return '';
    }
    $checkout = json_decode( $cart_row['checkout_data'], true );
    if ( isset( $checkout['fields']['billing_phone'] ) ) {
        return $checkout['fields']['billing_phone'];
    }
    if ( isset( $checkout['fields']['shipping_phone'] ) ) {
        return $checkout['fields']['shipping_phone'];
    }
    return '';
}

// ============================================================
// PREVENT INLINE BATCH EXECUTION FOR ABANDONED CART AUTOMATIONS
// ============================================================

add_filter( 'bwfan_run_v2_automation_immediately', 'orlando_wa_disable_immediate_run', 10, 3 );

function orlando_wa_disable_immediate_run( $run_immediately, $automation_id, $data ) {
    if ( isset( $data['event'] ) && $data['event'] === 'ab_cart_abandoned' ) {
        return false;
    }
    return $run_immediately;
}

// ============================================================
// PROTECT CART RECORD DURING CHECKOUT (FunnelKit 3.8.0 bug)
// ============================================================
//
// FunnelKit 3.8.0 added maybe_delete_abandoned_cart_if_empty() which
// deletes the abandoned cart record when WC()->cart is empty.
// Problem: WooCommerce empties the cart inside process_checkout() BEFORE
// redirecting to the payment gateway. For redirect gateways (Meshulam,
// CardCom, PayPal, etc.) the customer may never complete payment, but
// the cart record is already gone — so no recovery automation fires.
//
// Fix: change cart status from 0 (Pending) to 4 (Re-Scheduled) when
// "Place Order" is clicked. maybe_delete_abandoned_cart_if_empty() only
// deletes status 0, so the cart survives. The eligibility query checks
// status IN (0, 4), so the automation still picks it up after 30 min.
// If payment succeeds, recheck_abandoned_row() cleans up normally.

add_action( 'woocommerce_checkout_order_processed', 'orlando_wa_protect_cart_during_checkout', 20 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'orlando_wa_protect_cart_during_checkout', 20 );

function orlando_wa_protect_cart_during_checkout( $order ) {
    global $wpdb;

    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    // attach_order_id_to_abandoned_row() already ran at priority 10
    // and saved bwfan_cart_id as order meta.
    $ab_cart_id = $order->get_meta( 'bwfan_cart_id' );

    // Fallback: find by tracking cookie.
    if ( empty( $ab_cart_id ) && class_exists( 'BWFAN_Common' ) ) {
        $tracking_cookie = BWFAN_Common::get_cookie( 'bwfan_visitor' );
        if ( empty( $tracking_cookie ) && function_exists( 'WC' ) && ! is_null( WC()->session ) ) {
            $tracking_cookie = WC()->session->get( 'bwfan_visitor' );
        }
        if ( ! empty( $tracking_cookie ) ) {
            $ab_cart_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->prefix}bwfan_abandonedcarts WHERE cookie_key = %s AND status = 0 LIMIT 1",
                $tracking_cookie
            ) );
        }
    }

    if ( empty( $ab_cart_id ) ) {
        return;
    }

    // Update ONLY status using $wpdb directly — preserves original last_modified
    // so the 30-minute timer is counted from the last checkout interaction, not
    // from when Place Order was clicked.
    $updated = $wpdb->update(
        $wpdb->prefix . 'bwfan_abandonedcarts',
        array( 'status' => 4 ),
        array( 'ID' => absint( $ab_cart_id ), 'status' => 0 ),
        array( '%d' ),
        array( '%d', '%d' )
    );

    if ( $updated ) {
        orlando_wa_log( 'PROTECT: Cart #' . $ab_cart_id . ' status 0→4 during checkout for order #' . $order->get_id() );
    }
}

/**
 * Write a debug line to WP's error log.
 * Always logs to a dedicated file so cart-link issues can be diagnosed
 * even when WP_DEBUG is off.
 *
 * @param string $message
 */
function orlando_wa_log( $message ) {
    $log_file = WP_CONTENT_DIR . '/orlando-whatsapp.log';
    $entry    = '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
    file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
}
    // End Code Snippet Code

}, 10);