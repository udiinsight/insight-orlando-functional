<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * בדיקה פשוטה - כמה מוצרים עם Sale?
 */

add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'בדיקת מבצע',
        '🔍 בדיקת מבצע',
        'manage_woocommerce',
        'check-bulk-sale',
        'display_sale_check_page'
    );
});

function display_sale_check_page() {
    global $wpdb;
    
    // ספירת מוצרים עם Sale Price
    $products_with_sale = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_type,
               pm_regular.meta_value as regular_price,
               pm_sale.meta_value as sale_price,
               pm_from.meta_value as date_from,
               pm_to.meta_value as date_to
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
        LEFT JOIN {$wpdb->postmeta} pm_regular ON p.ID = pm_regular.post_id AND pm_regular.meta_key = '_regular_price'
        LEFT JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id AND pm_from.meta_key = '_sale_price_dates_from'
        LEFT JOIN {$wpdb->postmeta} pm_to ON p.ID = pm_to.post_id AND pm_to.meta_key = '_sale_price_dates_to'
        WHERE p.post_type IN ('product', 'product_variation')
        AND p.post_status = 'publish'
        AND pm_sale.meta_value != ''
        AND pm_sale.meta_value != '0'
        ORDER BY p.ID DESC
        LIMIT 30
    ");
    
    // ספירה כוללת
    $total_with_sale = $wpdb->get_var("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE p.post_type IN ('product', 'product_variation')
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sale_price'
        AND pm.meta_value != ''
        AND pm.meta_value != '0'
    ");
    
    // ספירת המוצרים שנוצרו/עודכנו היום
    $today_start = strtotime('today');
    $updated_today = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE p.post_type IN ('product', 'product_variation')
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sale_price'
        AND pm.meta_value != ''
        AND pm.meta_value != '0'
        AND p.post_modified >= %s
    ", date('Y-m-d H:i:s', $today_start)));
    
    ?>
    <div class="wrap" style="direction: rtl;">
        <h1>🔍 בדיקת מוצרים במבצע</h1>
        
        <?php if (empty($products_with_sale)): ?>
            <div class="notice notice-error" style="padding: 20px;">
                <h2>❌ לא נמצאו מוצרים עם Sale Price!</h2>
                <p><strong>זה אומר שהתהליך כנראה לא רץ או נכשל.</strong></p>
                <p>אפשרויות:</p>
                <ul>
                    <li>חזור לדף "מבצע המוני" והרץ שוב</li>
                    <li>בדוק את הלוג לשגיאות</li>
                    <li>וודא שיש מוצרים ללא Sale Price קיים</li>
                </ul>
            </div>
        <?php else: ?>
            
            <!-- סיכום -->
            <div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h2>📊 סיכום</h2>
                <p style="font-size: 18px;"><strong>סה"כ מוצרים/וריאציות עם Sale Price: <?php echo number_format($total_with_sale); ?></strong></p>
                <p style="font-size: 16px;">עודכנו היום: <strong><?php echo number_format($updated_today); ?></strong></p>
            </div>
            
            <!-- טבלת מוצרים -->
            <h2>30 המוצרים האחרונים עם Sale Price</h2>
            <table class="wp-list-table widefat fixed striped" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 80px;">סוג</th>
                        <th>שם המוצר</th>
                        <th style="width: 100px;">מחיר רגיל</th>
                        <th style="width: 100px;">מחיר Sale</th>
                        <th style="width: 80px;">הנחה</th>
                        <th style="width: 100px;">מתאריך</th>
                        <th style="width: 100px;">עד תאריך</th>
                        <th style="width: 80px;">עריכה</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products_with_sale as $item): 
                        $date_from = $item->date_from ? date('d/m/Y', $item->date_from) : '-';
                        $date_to = $item->date_to ? date('d/m/Y', $item->date_to) : '-';
                        
                        $discount = 0;
                        if ($item->regular_price > 0 && $item->sale_price > 0) {
                            $discount = round((($item->regular_price - $item->sale_price) / $item->regular_price) * 100, 1);
                        }
                        
                        $type_label = $item->post_type === 'product' ? 'מוצר' : 'וריאציה';
                        $type_color = $item->post_type === 'product' ? '#fff3e0' : '#e3f2fd';
                        
                        $edit_link = admin_url('post.php?post=' . $item->ID . '&action=edit');
                    ?>
                    <tr>
                        <td><?php echo $item->ID; ?></td>
                        <td><span style="background: <?php echo $type_color; ?>; padding: 3px 8px; border-radius: 3px; display: inline-block;"><?php echo $type_label; ?></span></td>
                        <td><strong><?php echo esc_html($item->post_title); ?></strong></td>
                        <td><strong><?php echo number_format($item->regular_price, 2); ?> ₪</strong></td>
                        <td style="color: #d63638;"><strong><?php echo number_format($item->sale_price, 2); ?> ₪</strong></td>
                        <td><span style="background: #d63638; color: white; padding: 3px 8px; border-radius: 3px; display: inline-block;"><?php echo $discount; ?>%</span></td>
                        <td><?php echo $date_from; ?></td>
                        <td><?php echo $date_to; ?></td>
                        <td><a href="<?php echo esc_url($edit_link); ?>" class="button button-small" target="_blank">ערוך</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=safe-bulk-sale'); ?>" class="button button-primary">חזור למבצע המוני</a>
                <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-secondary">כל המוצרים</a>
            </p>
            
        <?php endif; ?>
    </div>
    <?php
}
    // End Code Snippet Code

}, 10);