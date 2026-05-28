<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Flash Sale System for Orlando Watches - CATEGORY VERSION
 * 
 * Features:
 * - Real WooCommerce Sale Prices (no dates, we manage timing)
 * - Hourly precision with custom meta fields
 * - WordPress Cron every 3 minutes
 * - Manual disable properly removes everything
 * - Admin Meta Box showing exact schedule
 * - Complete backup and restore system
 * - Coupons automatically excluded via category
 * - Smart cache clearing (only on status change, no memory exhaustion)
 * - ALL fields locked when sale is active (except enabled toggle)
 * 
 * @version 3.4 - Lock all fields when active
 * @author Insight
 */



// ============================================================================
// CONSTANTS
// ============================================================================

define('FLASH_SALE_CATEGORY_SLUG', 'flash-sale');
define('FLASH_SALE_CRON_HOOK', 'flash_sale_check_expired');
define('FLASH_SALE_CRON_INTERVAL', 'flash_sale_3min');

// ============================================================================
// 1. DEPENDENCY CHECK
// ============================================================================

/**
 * Check if WooCommerce is active
 */
function flash_sale_check_dependencies() {
    return function_exists('wc_get_product');
}

/**
 * Show admin notice if dependencies missing - only on relevant pages
 */
add_action('admin_notices', 'flash_sale_dependency_notice');
function flash_sale_dependency_notice() {
    $screen = get_current_screen();
    if (!$screen) {
        return;
    }
    
    $relevant_screens = array(
        'toplevel_page_flash-sale-settings',
        'product',
        'edit-product'
    );
    
    if (!in_array($screen->id, $relevant_screens)) {
        return;
    }
    
    if (!function_exists('wc_get_product')) {
        echo '<div class="notice notice-error"><p><strong>Flash Sale:</strong> WooCommerce חייב להיות מותקן ופעיל.</p></div>';
    }
    if (!function_exists('acf_add_options_page')) {
        echo '<div class="notice notice-error"><p><strong>Flash Sale:</strong> ACF Pro חייב להיות מותקן ופעיל.</p></div>';
    }
}

// ============================================================================
// 2. ACF OPTIONS PAGE
// ============================================================================

if (function_exists('acf_add_options_page')) {
    acf_add_options_page(array(
        'page_title'    => 'Flash Sale Settings',
        'menu_title'    => 'Flash Sale',
        'menu_slug'     => 'flash-sale-settings',
        'capability'    => 'manage_options',
        'icon_url'      => 'dashicons-clock',
        'position'      => 56,
        'redirect'      => false,
        'update_button' => __('שמור הגדרות', 'acf'),
        'updated_message' => __('הגדרות נשמרו', 'acf'),
    ));
}

// ============================================================================
// 3. HELPER FUNCTIONS
// ============================================================================

/**
 * Check if ACF option is enabled (handles ACF boolean properly)
 * ACF saves toggle as '1' or '' (empty string), not true/false
 * 
 * @param string $option_name Option name without 'options_' prefix
 * @return bool
 */
function flash_sale_is_option_enabled($option_name) {
    $value = get_option('options_' . $option_name, '');
    return ($value === '1' || $value === 1 || $value === true);
}

/**
 * Get current timestamp (WordPress timezone aware)
 * Uses current_time('U') instead of deprecated current_time('timestamp')
 * 
 * @return int Unix timestamp
 */
function flash_sale_get_current_time() {
    return (int) current_time('U');
}

/**
 * Log Flash Sale events
 * 
 * @param string $message Message to log
 * @param string $level Log level: 'info', 'warning', 'error'
 */
function flash_sale_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[Flash Sale][%s] %s', strtoupper($level), $message));
    }
}

// ============================================================================
// 4. CORE LOGIC - SALE STATUS CHECKER
// ============================================================================

/**
 * Check if Flash Sale is currently active
 * Checks exact date AND time (hours and minutes)
 * 
 * @param bool $skip_cache Whether to skip transient cache
 * @return bool True if sale is active, false otherwise
 */
function is_flash_sale_active($skip_cache = false) {
    static $checking = false;
    
    // Prevent infinite loops
    if ($checking) {
        return false;
    }
    
    $checking = true;
    
    // Check cache (60 seconds) unless skipped
    if (!$skip_cache) {
        $cached = get_transient('flash_sale_status');
        if ($cached !== false) {
            $checking = false;
            return $cached === 'active';
        }
    }
    
    // Check dependencies
    if (!flash_sale_check_dependencies()) {
        $checking = false;
        return false;
    }
    
    // Get settings (using proper ACF boolean check)
    $is_enabled = flash_sale_is_option_enabled('flash_sale_enabled');
    $start_date = get_option('options_flash_sale_start', '');
    $end_date = get_option('options_flash_sale_end', '');
    $product_1 = get_option('options_flash_sale_product_1', 0);
    $product_2 = get_option('options_flash_sale_product_2', 0);
    
    // Validate all requirements
    if (!$is_enabled || !$start_date || !$end_date || !$product_1 || !$product_2) {
        set_transient('flash_sale_status', 'inactive', 60);
        $checking = false;
        return false;
    }
    
    // Check EXACT time (with hours and minutes)
    $now = flash_sale_get_current_time();
    $start_time = strtotime($start_date);
    $end_time = strtotime($end_date);
    
    // Validate timestamps
    if (!$start_time || !$end_time) {
        flash_sale_log('Invalid date format: start=' . $start_date . ', end=' . $end_date, 'error');
        set_transient('flash_sale_status', 'inactive', 60);
        $checking = false;
        return false;
    }
    
    $is_in_timeframe = ($now >= $start_time && $now <= $end_time);
    
    // Cache result
    $status = $is_in_timeframe ? 'active' : 'inactive';
    set_transient('flash_sale_status', $status, 60);
    
    $checking = false;
    return $is_in_timeframe;
}

/**
 * Get Flash Sale product IDs
 * 
 * @return array Array of product IDs
 */
function flash_sale_get_product_ids() {
    $product_1 = get_option('options_flash_sale_product_1', 0);
    $product_2 = get_option('options_flash_sale_product_2', 0);
    
    return array_filter(array(
        absint($product_1),
        absint($product_2)
    ));
}

/**
 * Get Flash Sale category ID
 * 
 * @return int|false Category ID or false if not found
 */
function flash_sale_get_category_id() {
    $category = get_term_by('slug', FLASH_SALE_CATEGORY_SLUG, 'product_cat');
    return $category ? $category->term_id : false;
}

// ============================================================================
// 5. CACHE MANAGEMENT (OPTIMIZED - NO MEMORY EXHAUSTION)
// ============================================================================

/**
 * Clear relevant caches (WooCommerce products, WP-Rocket, Cloudflare)
 * 
 * NOTE: We deliberately avoid wp_cache_flush() as it causes memory exhaustion
 * with Object Cache Pro on sites with large Redis cache.
 * Instead, we clear only what's necessary for Flash Sale to work.
 */
function flash_sale_clear_all_caches() {
    flash_sale_log('Clearing caches (targeted)');
    
    // 1. Clear Flash Sale status transient
    delete_transient('flash_sale_status');
    
    // 2. Clear WooCommerce transients for Flash Sale products only
    $product_ids = flash_sale_get_product_ids();
    foreach ($product_ids as $product_id) {
        wc_delete_product_transients($product_id);
        clean_post_cache($product_id);
    }
    
    // 3. Clear WooCommerce cart fragments (so cart shows updated prices)
    if (function_exists('WC')) {
        WC()->cart && WC()->cart->empty_cart(false);
    }
    
    // 4. Clear page cache (WP-Rocket) - includes Cloudflare if connected
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
        flash_sale_log('WP-Rocket cache cleared (including Cloudflare if connected)');
    }
    
    // 5. Clear LiteSpeed Cache
    if (class_exists('LiteSpeed_Cache_API')) {
        LiteSpeed_Cache_API::purge_all();
        flash_sale_log('LiteSpeed cache cleared');
    }
    
    // 6. Clear W3 Total Cache
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
        flash_sale_log('W3 Total Cache cleared');
    }
    
    // 7. Clear WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
        flash_sale_log('WP Super Cache cleared');
    }
    
    // NOTE: We do NOT call wp_cache_flush() here!
    // It causes memory exhaustion with Object Cache Pro.
    // The above targeted clearing is sufficient for Flash Sale.
    
    flash_sale_log('Cache clearing completed');
}

// ============================================================================
// 6. SALE PRICE MANAGEMENT (USING CATEGORY)
// ============================================================================

/**
 * Apply Flash Sale prices to products
 * Sets only: Sale Price + Custom Meta for exact times
 * Does NOT use WooCommerce scheduling (we manage it ourselves)
 */
function flash_sale_apply_sale_prices() {
    if (!flash_sale_check_dependencies()) {
        return;
    }
    
    $product_ids = flash_sale_get_product_ids();
    $discount = absint(get_option('options_flash_sale_discount', 0));
    $start_date = get_option('options_flash_sale_start', '');
    $end_date = get_option('options_flash_sale_end', '');
    
    if (empty($product_ids) || !$discount || !$start_date || !$end_date) {
        flash_sale_log('Missing required fields for applying sale prices', 'warning');
        return;
    }
    
    $category_id = flash_sale_get_category_id();
    
    if (!$category_id) {
        flash_sale_log('Category "' . FLASH_SALE_CATEGORY_SLUG . '" not found!', 'error');
        return;
    }
    
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date);

    // Clean stale products from flash-sale category before applying
    $stale_products = get_posts(array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_id,
        )),
    ));
    foreach ($stale_products as $stale_id) {
        if (!in_array($stale_id, $product_ids)) {
            wp_remove_object_terms($stale_id, $category_id, 'product_cat');
            flash_sale_log('Removed stale product ' . $stale_id . ' from flash-sale category');
        }
    }

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            flash_sale_log('Product not found: ' . $product_id, 'warning');
            continue;
        }
        
        // Get regular price
        $regular_price = $product->get_regular_price();
        
        if (!$regular_price || $regular_price <= 0) {
            flash_sale_log('Product has no regular price: ' . $product_id, 'warning');
            continue;
        }
        
        // Calculate sale price
        $sale_price = $regular_price * (1 - ($discount / 100));
        $sale_price = round($sale_price, 2);
        
        // === BACKUP ORIGINAL VALUES (only if not already backed up) ===
        if (!get_post_meta($product_id, '_flash_sale_backup_regular_price', true)) {
            // Backup regular price
            update_post_meta($product_id, '_flash_sale_backup_regular_price', $regular_price);
            
            // Backup original sale price (if exists)
            $original_sale = get_post_meta($product_id, '_sale_price', true);
            if ($original_sale) {
                update_post_meta($product_id, '_flash_sale_backup_sale_price', $original_sale);
            }
            
            // Backup original price (current active price)
            $original_price = get_post_meta($product_id, '_price', true);
            if ($original_price) {
                update_post_meta($product_id, '_flash_sale_backup_price', $original_price);
            }
            
            // Backup original categories
            $original_cats = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!empty($original_cats)) {
                update_post_meta($product_id, '_flash_sale_backup_categories', $original_cats);
            }
            
            flash_sale_log('Backed up original values for product: ' . $product_id);
        }
        
        // === SET FLASH SALE PRICES ===
        update_post_meta($product_id, '_sale_price', $sale_price);
        update_post_meta($product_id, '_price', $sale_price);
        
        // === STORE EXACT TIMES (for our checks) ===
        update_post_meta($product_id, '_flash_sale_exact_start', $start_timestamp);
        update_post_meta($product_id, '_flash_sale_exact_end', $end_timestamp);
        update_post_meta($product_id, '_flash_sale_discount', $discount);
        
        // === ADD TO FLASH SALE CATEGORY ===
        wp_set_object_terms($product_id, $category_id, 'product_cat', true);
        
        // === MARK AS FLASH SALE PRODUCT ===
        update_post_meta($product_id, '_is_flash_sale_product', 'yes');
        
        // === CLEAR PRODUCT CACHE ===
        wc_delete_product_transients($product_id);
        
        flash_sale_log('Applied sale price to product ' . $product_id . ': ' . $regular_price . ' -> ' . $sale_price);
    }
}

/**
 * Remove Flash Sale prices from products
 * Restores ALL original values
 */
function flash_sale_remove_sale_prices() {
    if (!flash_sale_check_dependencies()) {
        return;
    }
    
    // Get ALL products marked as Flash Sale
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_is_flash_sale_product',
                'value' => 'yes',
                'compare' => '='
            )
        ),
        'fields' => 'ids',
    );
    
    $product_ids = get_posts($args);
    
    if (empty($product_ids)) {
        flash_sale_log('No flash sale products to restore');
        return;
    }
    
    $category_id = flash_sale_get_category_id();
    
    foreach ($product_ids as $product_id) {
        // === RESTORE ORIGINAL PRICES ===
        $backup_regular = get_post_meta($product_id, '_flash_sale_backup_regular_price', true);
        $backup_sale = get_post_meta($product_id, '_flash_sale_backup_sale_price', true);
        $backup_price = get_post_meta($product_id, '_flash_sale_backup_price', true);
        
        // Restore regular price
        if ($backup_regular) {
            update_post_meta($product_id, '_regular_price', $backup_regular);
        }
        
        // Restore sale price (or remove if didn't exist)
        if ($backup_sale) {
            update_post_meta($product_id, '_sale_price', $backup_sale);
        } else {
            delete_post_meta($product_id, '_sale_price');
        }
        
        // Restore current price
        if ($backup_price) {
            update_post_meta($product_id, '_price', $backup_price);
        } elseif ($backup_regular) {
            update_post_meta($product_id, '_price', $backup_regular);
        }
        
        // === REMOVE FROM FLASH SALE CATEGORY ===
        if ($category_id) {
            wp_remove_object_terms($product_id, $category_id, 'product_cat');
        }
        
        // === REMOVE FLASH SALE META ===
        delete_post_meta($product_id, '_flash_sale_exact_start');
        delete_post_meta($product_id, '_flash_sale_exact_end');
        delete_post_meta($product_id, '_flash_sale_discount');
        delete_post_meta($product_id, '_is_flash_sale_product');
        
        // === REMOVE BACKUPS ===
        delete_post_meta($product_id, '_flash_sale_backup_regular_price');
        delete_post_meta($product_id, '_flash_sale_backup_sale_price');
        delete_post_meta($product_id, '_flash_sale_backup_price');
        delete_post_meta($product_id, '_flash_sale_backup_categories');
        
        // === CLEAR PRODUCT CACHE ===
        wc_delete_product_transients($product_id);
        
        flash_sale_log('Restored original prices for product: ' . $product_id);
    }
}

/**
 * Sync prices based on current status
 */
function flash_sale_sync_prices() {
    if (is_flash_sale_active(true)) { // Skip cache for accurate check
        flash_sale_apply_sale_prices();
    } else {
        flash_sale_remove_sale_prices();
    }
}

// ============================================================================
// 7. ADMIN UI - STATUS & FIELD LOCKING
// ============================================================================

/**
 * Update status message dynamically
 */
add_filter('acf/load_field/name=flash_sale_status_display', 'flash_sale_update_status_message');
function flash_sale_update_status_message($field) {
    $status_info = flash_sale_get_detailed_status();
    
    $status_colors = array(
        'active' => '#4CAF50',
        'inactive' => '#f44336',
        'scheduled' => '#FF9800',
        'expired' => '#9E9E9E',
    );
    
    $color = isset($status_colors[$status_info['state']]) ? $status_colors[$status_info['state']] : '#9E9E9E';
    
    $field['message'] = sprintf(
        '<div style="padding: 15px; background: %s; color: white; border-radius: 4px; font-size: 16px; font-weight: bold;">%s %s</div>',
        $color,
        $status_info['icon'],
        $status_info['message']
    );
    
    return $field;
}

/**
 * Lock ALL fields when sale is active (except the enabled toggle)
 * This prevents any changes to the sale configuration while it's running
 */
add_filter('acf/load_field/name=flash_sale_product_1', 'flash_sale_lock_field_when_active');
add_filter('acf/load_field/name=flash_sale_product_2', 'flash_sale_lock_field_when_active');
add_filter('acf/load_field/name=flash_sale_start', 'flash_sale_lock_field_when_active');
add_filter('acf/load_field/name=flash_sale_end', 'flash_sale_lock_field_when_active');
add_filter('acf/load_field/name=flash_sale_discount', 'flash_sale_lock_field_when_active');
function flash_sale_lock_field_when_active($field) {
    static $locking = false;
    
    if ($locking) {
        return $field;
    }
    
    $locking = true;
    
    if (is_flash_sale_active()) {
        $field['disabled'] = 1;
        $field['instructions'] = (isset($field['instructions']) ? $field['instructions'] : '') . ' <strong style="color: #f44336;">🔒 נעול - המבצע פעיל כרגע. כבה את המבצע כדי לערוך.</strong>';
    }
    
    $locking = false;
    return $field;
}

/**
 * Get detailed status information
 */
function flash_sale_get_detailed_status() {
    $is_enabled = flash_sale_is_option_enabled('flash_sale_enabled');
    $start_date = get_option('options_flash_sale_start', '');
    $end_date = get_option('options_flash_sale_end', '');
    $product_1 = get_option('options_flash_sale_product_1', 0);
    $product_2 = get_option('options_flash_sale_product_2', 0);
    
    $now = flash_sale_get_current_time();
    $start_time = $start_date ? strtotime($start_date) : 0;
    $end_time = $end_date ? strtotime($end_date) : 0;
    
    $has_all_fields = ($product_1 && $product_2 && $start_date && $end_date);
    
    if (!$is_enabled) {
        return array(
            'state' => 'inactive',
            'icon' => '🔴',
            'message' => 'מבצע לא פעיל (כבוי ידנית)'
        );
    }
    
    if (!$has_all_fields) {
        return array(
            'state' => 'inactive',
            'icon' => '⚠️',
            'message' => 'מבצע לא פעיל (חסרים שדות חובה)'
        );
    }
    
    if ($now < $start_time) {
        $time_until = human_time_diff($now, $start_time);
        return array(
            'state' => 'scheduled',
            'icon' => '⏰',
            'message' => "מבצע מתוזמן - יתחיל בעוד {$time_until}"
        );
    }
    
    if ($now > $end_time) {
        $time_since = human_time_diff($end_time, $now);
        return array(
            'state' => 'expired',
            'icon' => '✅',
            'message' => "מבצע הסתיים לפני {$time_since}"
        );
    }
    
    $time_left = human_time_diff($now, $end_time);
    return array(
        'state' => 'active',
        'icon' => '🟢',
        'message' => "מבצע פעיל! נותרו {$time_left}"
    );
}

// ============================================================================
// 8. PRODUCT ADMIN META BOX (Shows exact schedule)
// ============================================================================

/**
 * Add Flash Sale meta box to product edit screen
 */
add_action('add_meta_boxes', 'flash_sale_add_product_meta_box');
function flash_sale_add_product_meta_box() {
    add_meta_box(
        'flash_sale_info',
        '⚡ Flash Sale Information',
        'flash_sale_product_meta_box_content',
        'product',
        'side',
        'high'
    );
}

/**
 * Display Flash Sale info in product meta box
 */
function flash_sale_product_meta_box_content($post) {
    $product_id = $post->ID;
    
    $is_flash_sale = get_post_meta($product_id, '_is_flash_sale_product', true) === 'yes';
    
    if (!$is_flash_sale) {
        echo '<p style="color: #999;">מוצר זה לא במבצע Flash Sale</p>';
        return;
    }
    
    // Get Flash Sale data
    $start_time = get_post_meta($product_id, '_flash_sale_exact_start', true);
    $end_time = get_post_meta($product_id, '_flash_sale_exact_end', true);
    $discount = get_post_meta($product_id, '_flash_sale_discount', true);
    $sale_price = get_post_meta($product_id, '_sale_price', true);
    $regular_price = get_post_meta($product_id, '_regular_price', true);
    
    // Validate data exists
    if (!$start_time || !$end_time) {
        echo '<p style="color: #f44336;">⚠️ נתוני מבצע חסרים. יש לשמור מחדש את הגדרות המבצע.</p>';
        return;
    }
    
    $now = flash_sale_get_current_time();
    
    // Determine status
    if ($now < $start_time) {
        $status_text = '⏰ מבצע מתוזמן';
        $status_bg = '#fff3cd';
        $status_color = '#856404';
    } elseif ($now > $end_time) {
        $status_text = '✅ מבצע הסתיים';
        $status_bg = '#f8d7da';
        $status_color = '#721c24';
    } else {
        $status_text = '🟢 מבצע פעיל';
        $status_bg = '#d4edda';
        $status_color = '#155724';
    }
    
    // Safe number formatting
    $regular_display = $regular_price ? number_format((float)$regular_price, 2) : '—';
    $sale_display = $sale_price ? number_format((float)$sale_price, 2) : '—';
    
    ?>
    <div style="padding: 10px; background: <?php echo esc_attr($status_bg); ?>; border-radius: 4px; margin-bottom: 10px;">
        <p style="margin: 0; font-weight: bold; color: <?php echo esc_attr($status_color); ?>;">
            <?php echo esc_html($status_text); ?>
        </p>
    </div>
    
    <table style="width: 100%; font-size: 13px;">
        <tr>
            <td style="padding: 5px 0; font-weight: bold;">התחלה:</td>
            <td style="padding: 5px 0;"><?php echo esc_html(date('d/m/Y H:i', $start_time)); ?></td>
        </tr>
        <tr>
            <td style="padding: 5px 0; font-weight: bold;">סיום:</td>
            <td style="padding: 5px 0;"><?php echo esc_html(date('d/m/Y H:i', $end_time)); ?></td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 10px 0;"><hr style="margin: 0;"></td>
        </tr>
        <tr>
            <td style="padding: 5px 0; font-weight: bold;">מחיר רגיל:</td>
            <td style="padding: 5px 0;">₪<?php echo esc_html($regular_display); ?></td>
        </tr>
        <tr>
            <td style="padding: 5px 0; font-weight: bold;">מחיר מבצע:</td>
            <td style="padding: 5px 0; color: #d9534f; font-weight: bold;">₪<?php echo esc_html($sale_display); ?></td>
        </tr>
        <tr>
            <td style="padding: 5px 0; font-weight: bold;">הנחה:</td>
            <td style="padding: 5px 0;"><?php echo esc_html($discount); ?>%</td>
        </tr>
    </table>
    
    <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-right: 3px solid #2196F3; font-size: 12px;">
        <strong>ℹ️ מידע:</strong><br>
        מוצר זה בקטגוריית Flash Sale.<br>
        קופונים לא יחולו עליו.
    </div>
    
    <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-right: 3px solid #17a2b8; font-size: 12px;">
        <strong>⚠️ שים לב:</strong><br>
        מוצר זה מנוהל על ידי מערכת Flash Sale.<br>
        אל תערוך מחירים ידנית.
    </div>
    <?php
}

// ============================================================================
// 9. HOOKS & AUTOMATION
// ============================================================================

/**
 * Sync prices when ACF options are saved
 */
add_action('acf/save_post', 'flash_sale_on_save', 20);
function flash_sale_on_save($post_id) {
    if ($post_id !== 'options') {
        return;
    }
    
    flash_sale_log('Settings saved, syncing prices');
    
    // Clear status transient
    delete_transient('flash_sale_status');
    
    // Get current status and update last status
    $current_status = is_flash_sale_active(true) ? 'active' : 'inactive';
    update_option('flash_sale_last_status', $current_status);
    
    // Sync prices immediately
    flash_sale_sync_prices();
    
    // Clear caches (always on manual save)
    flash_sale_clear_all_caches();
}

/**
 * Add custom 3-minute cron schedule
 */
add_filter('cron_schedules', 'flash_sale_add_cron_schedule');
function flash_sale_add_cron_schedule($schedules) {
    $schedules[FLASH_SALE_CRON_INTERVAL] = array(
        'interval' => 180, // 3 minutes in seconds
        'display' => __('Every 3 Minutes (Flash Sale)')
    );
    return $schedules;
}

/**
 * Setup WordPress Cron - runs every 3 minutes
 * Also cleans up old cron schedules
 */
add_action('init', 'flash_sale_setup_cron');
function flash_sale_setup_cron() {
    // Clean up old 5-minute cron if exists (from previous version)
    $old_timestamp = wp_next_scheduled('flash_sale_check_expired_old');
    if ($old_timestamp) {
        wp_unschedule_event($old_timestamp, 'flash_sale_check_expired_old');
        flash_sale_log('Cleaned up old cron schedule');
    }
    
    // Initialize last status if not exists
    if (get_option('flash_sale_last_status') === false) {
        $current_status = is_flash_sale_active(true) ? 'active' : 'inactive';
        update_option('flash_sale_last_status', $current_status);
    }
    
    // Schedule new cron if not exists
    if (!wp_next_scheduled(FLASH_SALE_CRON_HOOK)) {
        wp_schedule_event(time(), FLASH_SALE_CRON_INTERVAL, FLASH_SALE_CRON_HOOK);
        flash_sale_log('Cron scheduled');
    }
}

/**
 * Cron job: Check if sale status changed and update accordingly
 * Only clears cache when status actually changes
 */
add_action(FLASH_SALE_CRON_HOOK, 'flash_sale_cron_check');
function flash_sale_cron_check() {
    // Get previous status
    $previous_status = get_option('flash_sale_last_status', 'unknown');
    
    // Clear transient to force fresh check
    delete_transient('flash_sale_status');
    
    // Get current status (skip cache)
    $current_status = is_flash_sale_active(true) ? 'active' : 'inactive';
    
    // Only sync and clear cache if status changed
    if ($previous_status !== $current_status) {
        flash_sale_log('Status changed: ' . $previous_status . ' -> ' . $current_status);
        
        // Sync prices
        flash_sale_sync_prices();
        
        // Clear caches
        flash_sale_clear_all_caches();
        
        // Update stored status
        update_option('flash_sale_last_status', $current_status);
    }
}

/**
 * Manual cron reset (uncomment temporarily if needed)
 */
// add_action('init', 'flash_sale_reset_cron', 5);
function flash_sale_reset_cron() {
    $timestamp = wp_next_scheduled(FLASH_SALE_CRON_HOOK);
    if ($timestamp) {
        wp_unschedule_event($timestamp, FLASH_SALE_CRON_HOOK);
        flash_sale_log('Cron reset');
    }
}

// ============================================================================
// 10. ADMIN NOTICES & HELPERS
// ============================================================================

/**
 * Show admin notice if misconfigured
 */
add_action('admin_notices', 'flash_sale_admin_notices');
function flash_sale_admin_notices() {
    $screen = get_current_screen();
    
    if (!$screen || $screen->id !== 'toplevel_page_flash-sale-settings') {
        return;
    }
    
    // Check if category exists
    $category_id = flash_sale_get_category_id();
    if (!$category_id) {
        echo '<div class="notice notice-error"><p><strong>שגיאת Flash Sale:</strong> קטגוריית "' . esc_html(FLASH_SALE_CATEGORY_SLUG) . '" לא קיימת! יש ליצור אותה בקטגוריות מוצרים.</p></div>';
        return;
    }
    
    $is_enabled = flash_sale_is_option_enabled('flash_sale_enabled');
    
    if (!$is_enabled) {
        return;
    }
    
    $start_date = get_option('options_flash_sale_start', '');
    $end_date = get_option('options_flash_sale_end', '');
    $product_1 = get_option('options_flash_sale_product_1', 0);
    $product_2 = get_option('options_flash_sale_product_2', 0);
    
    $errors = array();
    
    if (!$start_date) $errors[] = 'תאריך התחלה חסר';
    if (!$end_date) $errors[] = 'תאריך סיום חסר';
    if (!$product_1) $errors[] = 'מוצר ראשון חסר';
    if (!$product_2) $errors[] = 'מוצר שני חסר';
    
    if ($start_date && $end_date && strtotime($start_date) >= strtotime($end_date)) {
        $errors[] = 'תאריך הסיום חייב להיות אחרי תאריך ההתחלה';
    }
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p><strong>שגיאת Flash Sale:</strong> ' . esc_html(implode(', ', $errors)) . '</p></div>';
    }
}

/**
 * Add custom CSS to admin
 */
add_action('admin_head', 'flash_sale_admin_styles');
function flash_sale_admin_styles() {
    $screen = get_current_screen();
    
    if (!$screen || $screen->id !== 'toplevel_page_flash-sale-settings') {
        return;
    }
    ?>
    <style>
        .acf-field input[disabled],
        .acf-field select[disabled],
        .acf-field .select2-container--disabled {
            background: #f5f5f5 !important;
            border: 2px solid #ddd !important;
            cursor: not-allowed !important;
            opacity: 0.7;
        }
        
        /* Style disabled ACF relationship/post object fields */
        .acf-field[data-name="flash_sale_product_1"].disabled .acf-input,
        .acf-field[data-name="flash_sale_product_2"].disabled .acf-input {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .acf-field[data-name="flash_sale_enabled"] {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 4px;
            border: 2px solid #2196F3;
        }
        
        .acf-field[data-name="flash_sale_status_display"] {
            margin-bottom: 20px;
        }
        
        /* Warning banner when sale is active */
        .flash-sale-active-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-right: 4px solid #ffc107;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .flash-sale-active-warning p {
            margin: 0;
            color: #856404;
            font-weight: 500;
        }
    </style>
    <?php
}

/**
 * Add warning banner when sale is active
 */
add_action('admin_notices', 'flash_sale_active_warning_banner');
function flash_sale_active_warning_banner() {
    $screen = get_current_screen();
    
    if (!$screen || $screen->id !== 'toplevel_page_flash-sale-settings') {
        return;
    }
    
    if (is_flash_sale_active()) {
        echo '<div class="flash-sale-active-warning">';
        echo '<p>⚠️ <strong>המבצע פעיל כרגע!</strong> כל השדות נעולים לעריכה חוץ מכפתור ההפעלה. כבה את המבצע כדי לערוך את ההגדרות.</p>';
        echo '</div>';
    }
}

/**
 * Add Flash Sale column to products list
 */
add_filter('manage_product_posts_columns', 'flash_sale_add_admin_column');
function flash_sale_add_admin_column($columns) {
    $columns['flash_sale'] = '⚡ Flash Sale';
    return $columns;
}

add_action('manage_product_posts_custom_column', 'flash_sale_admin_column_content', 10, 2);
function flash_sale_admin_column_content($column, $post_id) {
    if ($column === 'flash_sale') {
        $is_flash_sale = get_post_meta($post_id, '_is_flash_sale_product', true) === 'yes';
        
        if ($is_flash_sale) {
            $sale_price = get_post_meta($post_id, '_sale_price', true);
            $start_time = get_post_meta($post_id, '_flash_sale_exact_start', true);
            $end_time = get_post_meta($post_id, '_flash_sale_exact_end', true);
            
            $now = flash_sale_get_current_time();
            
            if ($now < $start_time) {
                echo '<span style="color: #FF9800; font-weight: bold;">⏰ מתוזמן<br>₪' . esc_html(number_format((float)$sale_price, 2)) . '</span>';
            } elseif ($now > $end_time) {
                echo '<span style="color: #9E9E9E; font-weight: bold;">✅ הסתיים</span>';
            } else {
                echo '<span style="color: green; font-weight: bold;">✓ פעיל<br>₪' . esc_html(number_format((float)$sale_price, 2)) . '</span>';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}

// ============================================================================
// 11. DEBUG TOOLS (only when WP_DEBUG is on)
// ============================================================================

/**
 * Add debug info to Flash Sale settings page
 */
add_action('admin_footer', 'flash_sale_debug_info');
function flash_sale_debug_info() {
    // Only show when WP_DEBUG is enabled
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $screen = get_current_screen();
    
    if (!$screen || $screen->id !== 'toplevel_page_flash-sale-settings') {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $next_cron = wp_next_scheduled(FLASH_SALE_CRON_HOOK);
    $last_status = get_option('flash_sale_last_status', 'unknown');
    $current_status = is_flash_sale_active(true) ? 'active' : 'inactive';
    
    ?>
    <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
        <h3 style="margin-top: 0;">🔧 Debug Info (WP_DEBUG)</h3>
        <table style="font-size: 13px;">
            <tr>
                <td><strong>Current Time:</strong></td>
                <td><?php echo esc_html(date('Y-m-d H:i:s', flash_sale_get_current_time())); ?></td>
            </tr>
            <tr>
                <td><strong>Last Status:</strong></td>
                <td><?php echo esc_html($last_status); ?></td>
            </tr>
            <tr>
                <td><strong>Current Status:</strong></td>
                <td><?php echo esc_html($current_status); ?></td>
            </tr>
            <tr>
                <td><strong>Next Cron Run:</strong></td>
                <td><?php echo $next_cron ? esc_html(date('Y-m-d H:i:s', $next_cron)) : 'Not scheduled'; ?></td>
            </tr>
            <tr>
                <td><strong>Category ID:</strong></td>
                <td><?php echo esc_html(flash_sale_get_category_id() ?: 'Not found'); ?></td>
            </tr>
            <tr>
                <td><strong>Version:</strong></td>
                <td>3.4</td>
            </tr>
        </table>
    </div>
    <?php
}

// ============================================================================
// END OF FLASH SALE SYSTEM v3.4
// ============================================================================
    // End Code Snippet Code

}, 10);