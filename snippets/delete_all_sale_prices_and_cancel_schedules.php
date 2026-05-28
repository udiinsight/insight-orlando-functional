<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * מחיקת כל מחירי המבצע וביטול תזמונים - אורלנדו
 * 
 * שימוש: הוסף לwpCodeBox או הרץ פעם אחת דרך functions.php
 * מומלץ לגבות את בסיס הנתונים לפני ההרצה!
 */

// הוסף כפתור בממשק הניהול
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'מחיקת מחירי מבצע',
        'מחיקת מחירי מבצע',
        'manage_woocommerce',
        'remove-sale-prices',
        'orlando_remove_sale_prices_page'
    );
});

function orlando_remove_sale_prices_page() {
    ?>
    <div class="wrap">
        <h1>מחיקת כל מחירי המבצע</h1>
        
        <?php
        if (isset($_POST['remove_sale_prices']) && wp_verify_nonce($_POST['_wpnonce'], 'remove_sale_prices_action')) {
            $result = orlando_remove_all_sale_prices();
            echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
        }
        
        if (isset($_POST['preview_sale_prices']) && wp_verify_nonce($_POST['_wpnonce'], 'remove_sale_prices_action')) {
            $products = orlando_get_products_with_sale();
            echo '<div class="notice notice-info">';
            echo '<p><strong>נמצאו ' . count($products) . ' מוצרים עם מחיר מבצע:</strong></p>';
            if (!empty($products)) {
                echo '<ul style="max-height: 300px; overflow-y: auto;">';
                foreach ($products as $product) {
                    echo '<li>' . esc_html($product['name']) . ' (ID: ' . $product['id'] . ') - מחיר רגיל: ₪' . $product['regular'] . ', מבצע: ₪' . $product['sale'] . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        ?>
        
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <p><strong>שים לב:</strong> פעולה זו תמחק את כל מחירי המבצע מכל המוצרים כולל:</p>
            <ul>
                <li>מחירי מבצע רגילים</li>
                <li>תזמוני מבצע (תאריך התחלה וסיום)</li>
                <li>מחירי מבצע בוריאציות</li>
            </ul>
            <p style="color: red;"><strong>מומלץ לגבות את בסיס הנתונים לפני ביצוע!</strong></p>
            
            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('remove_sale_prices_action'); ?>
                
                <button type="submit" name="preview_sale_prices" class="button button-secondary" style="margin-left: 10px;">
                    תצוגה מקדימה
                </button>
                
                <button type="submit" name="remove_sale_prices" class="button button-primary" 
                        onclick="return confirm('האם אתה בטוח? פעולה זו תמחק את כל מחירי המבצע!');">
                    מחק את כל מחירי המבצע
                </button>
            </form>
        </div>
    </div>
    <?php
}

/**
 * קבלת רשימת מוצרים עם מחיר מבצע
 */
function orlando_get_products_with_sale() {
    $products_with_sale = [];
    
    $args = [
        'post_type' => ['product', 'product_variation'],
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => '_sale_price',
                'value' => '',
                'compare' => '!='
            ]
        ],
        'fields' => 'ids'
    ];
    
    $product_ids = get_posts($args);
    
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $products_with_sale[] = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'regular' => $product->get_regular_price(),
                'sale' => $product->get_sale_price()
            ];
        }
    }
    
    return $products_with_sale;
}

/**
 * מחיקת כל מחירי המבצע
 */
function orlando_remove_all_sale_prices() {
    global $wpdb;
    
    $updated_products = 0;
    $updated_variations = 0;
    
    // קבלת כל המוצרים הפשוטים עם מחיר מבצע
    $simple_products = $wpdb->get_col("
        SELECT DISTINCT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sale_price' 
        AND meta_value != '' 
        AND post_id IN (
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status IN ('publish', 'draft', 'private')
        )
    ");
    
    foreach ($simple_products as $product_id) {
        $product = wc_get_product($product_id);
        if ($product && !$product->is_type('variable')) {
            // שמירת המחיר הרגיל
            $regular_price = $product->get_regular_price();
            
            // מחיקת מחיר מבצע ותזמונים
            update_post_meta($product_id, '_sale_price', '');
            update_post_meta($product_id, '_sale_price_dates_from', '');
            update_post_meta($product_id, '_sale_price_dates_to', '');
            
            // עדכון המחיר הפעיל למחיר הרגיל
            update_post_meta($product_id, '_price', $regular_price);
            
            $updated_products++;
        }
    }
    
    // קבלת כל הווריאציות עם מחיר מבצע
    $variations = $wpdb->get_col("
        SELECT DISTINCT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sale_price' 
        AND meta_value != '' 
        AND post_id IN (
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product_variation' 
            AND post_status IN ('publish', 'draft', 'private')
        )
    ");
    
    foreach ($variations as $variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $regular_price = $variation->get_regular_price();
            
            update_post_meta($variation_id, '_sale_price', '');
            update_post_meta($variation_id, '_sale_price_dates_from', '');
            update_post_meta($variation_id, '_sale_price_dates_to', '');
            update_post_meta($variation_id, '_price', $regular_price);
            
            $updated_variations++;
        }
    }
    
    // עדכון מוצרים משתנים - סנכרון מחירים
    $variable_products = $wpdb->get_col("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status IN ('publish', 'draft', 'private')
        AND ID IN (
            SELECT post_parent FROM {$wpdb->posts} 
            WHERE post_type = 'product_variation'
        )
    ");
    
    foreach ($variable_products as $product_id) {
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            // מחיקת תזמונים גם מהמוצר הראשי
            update_post_meta($product_id, '_sale_price_dates_from', '');
            update_post_meta($product_id, '_sale_price_dates_to', '');
            
            // סנכרון מחירי המוצר המשתנה
            $product->sync();
        }
    }
    
    // ניקוי קאש של WooCommerce
    wc_delete_product_transients();
    
    // ניקוי קאש נוסף אם קיים
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    return "הושלם! עודכנו {$updated_products} מוצרים פשוטים ו-{$updated_variations} וריאציות.";
}

/**
 * WP-CLI command - אופציונלי
 * שימוש: wp orlando remove-sale-prices
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('orlando remove-sale-prices', function() {
        WP_CLI::confirm('האם אתה בטוח שברצונך למחוק את כל מחירי המבצע?');
        $result = orlando_remove_all_sale_prices();
        WP_CLI::success($result);
    });
}

    // End Code Snippet Code

}, 10);