<?php
/**
 * Plugin Name: Insight - Orlando - Functional
 * Plugin URI: https://github.com/udiinsight/insight-orlando-functional
 * Description: Custom functionality for Orlando Watches
 * Version: 1.1.1
 * Author: Insight Marketing
 * Author URI: https://insight-marketing.co.il
 * Text Domain: insight-orlando-functional
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSIGHT_ORLANDO_FUNCTIONAL_VERSION', '1.1.1' );

include_once __DIR__ . '/includes/WFPCore/WordPressContext.php';

include_once __DIR__ . '/snippets/orlando_whatsapp_abandoned_cart_relay.php';
include_once __DIR__ . '/snippets/orlando_watches_dummy_email_for_cart_tracking.php';
include_once __DIR__ . '/snippets/registration_popup_add_phone.php';
include_once __DIR__ . '/snippets/faq_schema_markup_from_h2_headings_in_single_postsd.php';
include_once __DIR__ . '/snippets/fix_imagify_picture_tag_conflict_with_bricks_lazy_loading.php';
include_once __DIR__ . '/snippets/flash_sale_system.php';
include_once __DIR__ . '/snippets/auto_exclude_flash_sale_category_from_new_coupons.php';
include_once __DIR__ . '/snippets/flash_sale_bricks_custom_condition.php';
include_once __DIR__ . '/snippets/disable_dashicons.php';
include_once __DIR__ . '/snippets/wesell_conversion_pixel.php';
include_once __DIR__ . '/snippets/sort_archive_pages.php';
include_once __DIR__ . '/snippets/add_og_ttl_meta_tag_to_limit_facebook_crawler.php';
include_once __DIR__ . '/snippets/product_in_shipos_notes.php';
include_once __DIR__ . '/snippets/rocket_cache_reject_uri.php';
include_once __DIR__ . '/snippets/delete_all_sale_prices_and_cancel_schedules.php';
include_once __DIR__ . '/snippets/fill_empty_billing_address_and_email_fields_with_placeholder_values.php';
include_once __DIR__ . '/snippets/secondary_product_image_function.php';
include_once __DIR__ . '/snippets/add_privacy_policy_checkbox_to_registration.php';
include_once __DIR__ . '/snippets/hide_credit_form_if_less_than_50_available.php';
include_once __DIR__ . '/snippets/get_pure_product_excerpt.php';
include_once __DIR__ . '/snippets/change_the_checkout_city_field_to_a_dropdown_field.php';
include_once __DIR__ . '/snippets/hide_out_of_stock_status_in_loop.php';
include_once __DIR__ . '/snippets/bulk_sale_price_update.php';
include_once __DIR__ . '/snippets/bulk_sale_check.php';
include_once __DIR__ . '/snippets/display_coupon_checkbox_on_product_page.php';
include_once __DIR__ . '/snippets/color_variations_display_in_single_product_with_cache.php';
include_once __DIR__ . '/snippets/ajax_color_variations_loop_with_object_cache.php';
include_once __DIR__ . '/snippets/disable_gutenberg_for_posts.php';
include_once __DIR__ . '/snippets/woocommerce_first_gallery_image_id.php';
include_once __DIR__ . '/snippets/dashboard_widgets.php';
include_once __DIR__ . '/snippets/custom_related_products.php';
include_once __DIR__ . '/snippets/redirect_cart_page_to_home_or_checkout.php';
include_once __DIR__ . '/snippets/auto_convert_loyalty_points_to_credit.php';
include_once __DIR__ . '/snippets/restrict_store_credit_usage.php';
include_once __DIR__ . '/snippets/remove_my_points_endpoint.php';
include_once __DIR__ . '/snippets/get_brand_for_product.php';
include_once __DIR__ . '/snippets/server_side_phone_field_validation.php';
include_once __DIR__ . '/snippets/echo_function_names.php';
