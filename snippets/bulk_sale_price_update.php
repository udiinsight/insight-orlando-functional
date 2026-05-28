<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * גרסה סופית עם ניהול גיבויים חכם
 */

class Safe_Bulk_Sale_Manager {
    
    private $batch_size = 20;
    private $discount_percent = 25;
    private $backup_option_name = 'bulk_sale_backup';
    private $max_total_products = 0; // 0 = ללא הגבלה
    private $backup_retention_days = 60; // כמה ימים לשמור גיבוי
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_post_rollback_bulk_sale', array($this, 'handle_rollback'));
        add_action('admin_post_delete_backup', array($this, 'handle_delete_backup'));
        add_action('wp_ajax_process_batch', array($this, 'ajax_process_batch'));
        add_action('admin_init', array($this, 'cleanup_old_backup'));
    }
    
    /**
     * ניקוי גיבוי ישן אוטומטית
     */
    public function cleanup_old_backup() {
        // בדוק רק פעם אחת ביום
        $last_check = get_option($this->backup_option_name . '_last_check', 0);
        if ((time() - $last_check) < (24 * 60 * 60)) {
            return; // כבר בדקנו היום
        }
        
        update_option($this->backup_option_name . '_last_check', time());
        
        $backup_data = get_option($this->backup_option_name, array());
        
        if (empty($backup_data)) {
            return;
        }
        
        // בדוק את זמן יצירת הגיבוי
        $backup_timestamp = get_option($this->backup_option_name . '_timestamp', 0);
        
        if (!$backup_timestamp) {
            return; // אין timestamp, כנראה גיבוי ישן מאוד
        }
        
        $days_old = floor((time() - $backup_timestamp) / (24 * 60 * 60));
        
        // אם הגיבוי ישן מדי, מחק אותו
        if ($days_old >= $this->backup_retention_days) {
            delete_option($this->backup_option_name);
            delete_option($this->backup_option_name . '_timestamp');
            
            // הוסף הודעה למנהל
            set_transient('bulk_sale_backup_deleted_notice', $days_old, 300); // 5 דקות
        }
    }
    
    /**
     * הוספת עמוד ניהול
     */
    public function add_admin_page() {
        add_submenu_page(
            'woocommerce',
            'מבצע המוני בטוח',
            '🛡️ מבצע המוני',
            'manage_woocommerce',
            'safe-bulk-sale',
            array($this, 'display_admin_page')
        );
    }
    
    /**
     * ספירת מוצרים שיעודכנו
     */
    private function count_products_to_update($include_on_sale = false) {
        global $wpdb;
        
        $sale_condition = $include_on_sale ? "" : "AND (pm_sale.meta_value IS NULL OR pm_sale.meta_value = '')";
        
        // ספירת מוצרים פשוטים
        $simple_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            {$sale_condition}
        ");
        
        // ספירת וריאציות
        $variation_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            {$sale_condition}
        ");
        
        return intval($simple_count) + intval($variation_count);
    }
    
    /**
     * עיבוד batch של מוצרים
     */
    public function ajax_process_batch() {
        check_ajax_referer('bulk_sale_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('אין הרשאות');
        }
        
        $offset = intval($_POST['offset']);
        $sale_from = sanitize_text_field($_POST['sale_from']);
        $sale_to = sanitize_text_field($_POST['sale_to']);
        $discount_percent = floatval($_POST['discount_percent']);
        $include_on_sale = isset($_POST['include_on_sale']) && $_POST['include_on_sale'] === '1';
        $exclude_products = isset($_POST['exclude_products']) ? sanitize_textarea_field($_POST['exclude_products']) : '';
        
        $result = $this->process_products_batch($offset, $sale_from, $sale_to, $discount_percent, $include_on_sale, $exclude_products);
        
        wp_send_json_success($result);
    }
    
    /**
     * עיבוד קבוצת מוצרים
     */
    private function process_products_batch($offset, $sale_from, $sale_to, $discount_percent, $include_on_sale = false, $exclude_products = '') {
        // בדיקת הגבלה כוללת
        $total_processed_so_far = $offset;
        if ($this->max_total_products > 0 && $total_processed_so_far >= $this->max_total_products) {
            return array(
                'processed' => 0,
                'updated' => 0,
                'skipped' => 0,
                'excluded' => 0,
                'has_more' => false
            );
        }
        
        // חישוב batch_size דינמי
        $batch_size = $this->batch_size;
        if ($this->max_total_products > 0) {
            $remaining = $this->max_total_products - $total_processed_so_far;
            $batch_size = min($batch_size, $remaining);
        }
        
        // הכנת רשימת החרגות
        $exclude_list = array();
        if (!empty($exclude_products)) {
            $exclude_items = array_map('trim', explode(',', $exclude_products));
            $exclude_list = array_filter($exclude_items);
        }
        
        $args = array(
            'post_type'      => array('product', 'product_variation'),
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'post_status'    => 'publish',
            'fields'         => 'ids'
        );
        
        $product_ids = get_posts($args);
        $updated_count = 0;
        $skipped_count = 0;
        $excluded_count = 0;
        $backup_data = get_option($this->backup_option_name, array());
        
        // השתמש ב-timezone של WordPress (קריטי!)
        $timezone_string = wp_timezone_string();
        $timezone = new DateTimeZone($timezone_string);

        try {
            $sale_from_date = new DateTime($sale_from . ' 00:00:00', $timezone);
            $sale_from_timestamp = $sale_from_date->getTimestamp();
            
            $sale_to_date = new DateTime($sale_to . ' 23:59:59', $timezone);
            $sale_to_timestamp = $sale_to_date->getTimestamp();
        } catch (Exception $e) {
            error_log('Error parsing dates: ' . $e->getMessage());
            // fallback
            $sale_from_timestamp = strtotime($sale_from . ' 00:00:00');
            $sale_to_timestamp = strtotime($sale_to . ' 23:59:59');
        }
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                $skipped_count++;
                continue;
            }
            
            // בדיקת החרגה לפי ID או SKU
            $product_sku = $product->get_sku();
            $is_excluded = false;
            
            foreach ($exclude_list as $exclude_item) {
                if (is_numeric($exclude_item)) {
                    if ($product_id == intval($exclude_item)) {
                        $is_excluded = true;
                        break;
                    }
                    if ($product->get_parent_id() == intval($exclude_item)) {
                        $is_excluded = true;
                        break;
                    }
                } else {
                    if ($product_sku && strtolower($product_sku) === strtolower($exclude_item)) {
                        $is_excluded = true;
                        break;
                    }
                }
            }
            
            if ($is_excluded) {
                $excluded_count++;
                continue;
            }
            
            // דלג על מוצרים שכבר יש להם sale
            if ($product->is_on_sale() && !$include_on_sale) {
                $skipped_count++;
                continue;
            }
            
            $regular_price = floatval($product->get_regular_price());
            
            if ($regular_price <= 0) {
                $skipped_count++;
                continue;
            }
            
            // שמור מצב מקורי לגיבוי
            $backup_data[$product_id] = array(
                'sale_price' => $product->get_sale_price(),
                'date_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->getTimestamp() : '',
                'date_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->getTimestamp() : '',
                'regular_price' => $regular_price
            );
            
            try {
                // חישוב מחיר מבצע
                $sale_price = round($regular_price * (1 - $discount_percent / 100), 0);
                
                // עדכון מחירים
                $product->set_sale_price($sale_price);
                $product->set_date_on_sale_from($sale_from_timestamp);
                $product->set_date_on_sale_to($sale_to_timestamp);
                $product->save();
                
                // ניקוי cache
                wc_delete_product_transients($product_id);
                
                $updated_count++;
                
            } catch (Exception $e) {
                error_log('Error updating product ' . $product_id . ': ' . $e->getMessage());
                $skipped_count++;
            }
        }
        
        // שמור גיבוי + timestamp
        update_option($this->backup_option_name, $backup_data);
        update_option($this->backup_option_name . '_timestamp', time());
        
        return array(
            'processed' => count($product_ids),
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'excluded' => $excluded_count,
            'has_more' => count($product_ids) === $batch_size
        );
    }
    
    /**
     * שחזור מצב קודם
     */
    public function handle_rollback() {
        check_admin_referer('rollback_bulk_sale');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('אין הרשאות');
        }
        
        $backup_data = get_option($this->backup_option_name, array());
        
        if (empty($backup_data)) {
            wp_redirect(add_query_arg(array(
                'page' => 'safe-bulk-sale',
                'message' => 'no_backup'
            ), admin_url('admin.php')));
            exit;
        }
        
        $restored_count = 0;
        
        foreach ($backup_data as $product_id => $original_data) {
            $product = wc_get_product($product_id);
            
            if (!$product) continue;
            
            try {
                $product->set_sale_price($original_data['sale_price']);
                
                if ($original_data['date_from']) {
                    $product->set_date_on_sale_from($original_data['date_from']);
                } else {
                    $product->set_date_on_sale_from('');
                }
                
                if ($original_data['date_to']) {
                    $product->set_date_on_sale_to($original_data['date_to']);
                } else {
                    $product->set_date_on_sale_to('');
                }
                
                $product->save();
                wc_delete_product_transients($product_id);
                
                $restored_count++;
                
            } catch (Exception $e) {
                error_log('Error restoring product ' . $product_id . ': ' . $e->getMessage());
            }
        }
        
        // מחק את הגיבוי אחרי שחזור מוצלח
        delete_option($this->backup_option_name);
        delete_option($this->backup_option_name . '_timestamp');
        
        wp_redirect(add_query_arg(array(
            'page' => 'safe-bulk-sale',
            'message' => 'restored',
            'count' => $restored_count
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * מחיקת גיבוי ידנית
     */
    public function handle_delete_backup() {
        check_admin_referer('delete_backup');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('אין הרשאות');
        }
        
        delete_option($this->backup_option_name);
        delete_option($this->backup_option_name . '_timestamp');
        
        wp_redirect(add_query_arg(array(
            'page' => 'safe-bulk-sale',
            'message' => 'backup_deleted'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * קבלת מידע על הגיבוי
     */
    private function get_backup_info() {
        $backup_data = get_option($this->backup_option_name, array());
        
        if (empty($backup_data)) {
            return null;
        }
        
        $timestamp = get_option($this->backup_option_name . '_timestamp', 0);
        $count = count($backup_data);
        $days_old = $timestamp ? floor((time() - $timestamp) / (24 * 60 * 60)) : null;
        $days_remaining = $timestamp ? ($this->backup_retention_days - $days_old) : null;
        
        return array(
            'count' => $count,
            'timestamp' => $timestamp,
            'date' => $timestamp ? date('d/m/Y H:i', $timestamp) : 'לא ידוע',
            'days_old' => $days_old,
            'days_remaining' => $days_remaining
        );
    }
    
    /**
     * תצוגת עמוד הניהול
     */
    public function display_admin_page() {
        $total_products = $this->count_products_to_update(false);
        $total_with_sale = $this->count_products_to_update(true) - $total_products;
        $backup_info = $this->get_backup_info();
        $has_backup = !empty($backup_info);
        
        // הודעת מחיקה אוטומטית
        $deleted_days = get_transient('bulk_sale_backup_deleted_notice');
        if ($deleted_days) {
            echo '<div class="notice notice-info is-dismissible" style="padding: 15px;">';
            echo '<p>🗑️ <strong>גיבוי ישן נמחק אוטומטית</strong></p>';
            echo '<p>הגיבוי היה בן ' . $deleted_days . ' ימים (המדיניות: מחיקה אחרי ' . $this->backup_retention_days . ' ימים)</p>';
            echo '</div>';
            delete_transient('bulk_sale_backup_deleted_notice');
        }
        
        // הודעת הצלחה אם התהליך הושלם
        if (isset($_GET['completed']) && $_GET['completed'] == '1') {
            $updated = isset($_GET['updated']) ? intval($_GET['updated']) : 0;
            $skipped = isset($_GET['skipped']) ? intval($_GET['skipped']) : 0;
            $excluded = isset($_GET['excluded']) ? intval($_GET['excluded']) : 0;
            $discount = isset($_GET['discount']) ? floatval($_GET['discount']) : 0;
            
            echo '<div class="notice notice-success is-dismissible" style="padding: 20px; border-right: 4px solid #46b450; margin: 20px 0 20px 0;">';
            echo '<h2 style="margin-top: 0; color: #46b450;">✅ המבצע הופעל בהצלחה!</h2>';
            echo '<div style="font-size: 16px; line-height: 1.8;">';
            echo '<p><strong>📊 סיכום:</strong></p>';
            echo '<ul style="list-style: none; padding: 0; margin: 10px 0;">';
            echo '<li style="padding: 5px 0;">✔️ <strong>עודכנו:</strong> ' . number_format($updated) . ' פריטים</li>';
            echo '<li style="padding: 5px 0;">➖ <strong>דולגו:</strong> ' . number_format($skipped) . ' פריטים</li>';
            if ($excluded > 0) {
                echo '<li style="padding: 5px 0;">🚫 <strong>הוחרגו:</strong> ' . number_format($excluded) . ' פריטים (לפי בחירה)</li>';
            }
            echo '<li style="padding: 5px 0;">💰 <strong>אחוז הנחה:</strong> ' . $discount . '%</li>';
            echo '<li style="padding: 5px 0;">💾 <strong>גיבוי:</strong> נשמר (ניתן לשחזר בכל עת)</li>';
            echo '</ul>';
            echo '</div>';
            echo '<p style="margin-top: 15px;">';
            echo '<a href="' . admin_url('admin.php?page=check-bulk-sale') . '" class="button button-primary">🔍 צפה בתוצאות המלאות</a> ';
            echo '<a href="' . admin_url('edit.php?post_type=product') . '" class="button button-secondary">📦 כל המוצרים</a>';
            echo '</p>';
            echo '</div>';
        }
        
        ?>
        <div class="wrap" style="direction: rtl;">
            <h1>🛡️ מבצע המוני בטוח</h1>
            
            <?php
            // הודעות נוספות
            if (isset($_GET['message'])) {
                if ($_GET['message'] === 'restored') {
                    echo '<div class="notice notice-success is-dismissible" style="padding: 15px; border-right: 4px solid #46b450;">';
                    echo '<h2 style="margin-top: 0;">✅ שחזור הושלם בהצלחה!</h2>';
                    echo '<p style="font-size: 16px;"><strong>שוחזרו ' . number_format(intval($_GET['count'])) . ' מוצרים למצב המקורי.</strong></p>';
                    echo '<p>✔️ כל המוצרים חזרו למצב שהיה לפני המבצע<br>';
                    echo '✔️ הגיבוי נמחק</p>';
                    echo '</div>';
                } elseif ($_GET['message'] === 'no_backup') {
                    echo '<div class="notice notice-warning"><p>⚠️ לא נמצא גיבוי לשחזור</p></div>';
                } elseif ($_GET['message'] === 'backup_deleted') {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ הגיבוי נמחק בהצלחה</p></div>';
                }
            }
            ?>
            
            <!-- סטטוס -->
            <div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h2>📊 סטטוס נוכחי</h2>
                <p><strong>מוצרים ללא Sale Price:</strong> <?php echo number_format($total_products); ?> פריטים</p>
                <p><strong>מוצרים עם Sale Price קיים:</strong> <?php echo number_format($total_with_sale); ?> פריטים</p>
                <p><strong>גיבוי קיים:</strong> <?php echo $has_backup ? '✅ כן' : '❌ לא'; ?></p>
                
                <?php if ($has_backup && $backup_info): ?>
                <div style="background: white; padding: 15px; margin-top: 15px; border-right: 3px solid #2271b1; border-radius: 3px;">
                    <h3 style="margin-top: 0; font-size: 16px;">📦 פרטי הגיבוי:</h3>
                    <ul style="margin: 5px 0; padding-right: 20px;">
                        <li><strong>מספר פריטים:</strong> <?php echo number_format($backup_info['count']); ?></li>
                        <li><strong>תאריך יצירה:</strong> <?php echo $backup_info['date']; ?></li>
                        <?php if ($backup_info['days_old'] !== null): ?>
                            <li><strong>גיל הגיבוי:</strong> <?php echo $backup_info['days_old']; ?> ימים</li>
                            <?php if ($backup_info['days_remaining'] > 0): ?>
                                <li style="color: #d63638;"><strong>ימחק אוטומטית בעוד:</strong> <?php echo $backup_info['days_remaining']; ?> ימים</li>
                            <?php else: ?>
                                <li style="color: #d63638;"><strong>⚠️ הגיבוי עומד להימחק בקרוב!</strong></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- טופס הרצת מבצע -->
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; max-width: 700px;">
                <h2>🚀 הפעלת מבצע</h2>
                
                <form id="bulk-sale-form">
                    <?php wp_nonce_field('bulk_sale_nonce', 'nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="discount_percent">אחוז הנחה:</label></th>
                            <td>
                                <input type="number" name="discount_percent" id="discount_percent" 
                                       value="25" min="1" max="99" step="0.01" required 
                                       style="width: 100px;">
                                <span style="font-size: 18px; margin-right: 5px;">%</span>
                                <p class="description">הכנס את אחוז ההנחה הרצוי (1-99)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sale_from">תאריך התחלה:</label></th>
                            <td>
                                <input type="date" name="sale_from" id="sale_from" value="<?php echo date('Y-m-d'); ?>" required style="width: 200px;">
                                <p class="description">המבצע יתחיל בתחילת היום (00:00)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sale_to">תאריך סיום:</label></th>
                            <td>
                                <input type="date" name="sale_to" id="sale_to" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required style="width: 200px;">
                                <p class="description">המבצע יסתיים בסוף היום (23:59)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="exclude_products">החרג מוצרים:</label></th>
                            <td>
                                <textarea name="exclude_products" id="exclude_products" 
                                          rows="3" style="width: 100%; max-width: 400px; font-family: monospace;"
                                          placeholder="דוגמאות:&#10;SKU123, SKU456&#10;או&#10;789, 1234, 5678"></textarea>
                                <p class="description">
                                    💡 הכנס <strong>קודי מוצר (SKU)</strong> או <strong>מספרי מזהה (ID)</strong>, מופרדים בפסיקים<br>
                                    📌 לדוגמה: <code>CANDLE-001, GIFT-BOX, 123, 456</code><br>
                                    🔍 אפשר למצוא את ה-ID/SKU בעריכת המוצר
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="include_on_sale">כלול מוצרים במבצע:</label></th>
                            <td>
                                <label style="display: flex; align-items: center;">
                                    <input type="checkbox" name="include_on_sale" id="include_on_sale" value="1" style="margin-left: 8px;">
                                    <span>עדכן גם מוצרים שכבר יש להם מחיר Sale</span>
                                </label>
                                <p class="description" style="color: #d63638; margin-top: 8px;">
                                    ⚠️ <strong>זהירות:</strong> זה ידרוס הנחות קיימות! המחיר החדש יחושב מהמחיר הרגיל.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-right: 4px solid #ffc107; border-radius: 5px;">
                        <h3 style="margin-top: 0;">⚠️ לפני שמתחילים:</h3>
                        <ul style="margin: 10px 0;">
                            <li>✅ מומלץ לגבות את מסד הנתונים</li>
                            <li>✅ העדכון ישמור גיבוי אוטומטי</li>
                            <li>✅ ניתן לבטל את הפעולה בלחיצת כפתור</li>
                            <li>✅ גיבוי נשמר ל-<?php echo $this->backup_retention_days; ?> ימים</li>
                            <li>✅ מוצרים מוחרגים לא יעודכנו בכלל</li>
                            <li>✅ העדכון מתבצע בקבוצות קטנות למניעת עומס</li>
                        </ul>
                    </div>
                    
                    <div id="progress-container" style="display: none; margin: 20px 0;">
                        <h3>מעבד...</h3>
                        <div style="background: #f0f0f1; height: 30px; border-radius: 5px; overflow: hidden; position: relative;">
                            <div id="progress-bar" style="background: linear-gradient(90deg, #2271b1, #135e96); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;"></div>
                        </div>
                        <p id="progress-text" style="margin-top: 10px; font-weight: bold;">מתחיל...</p>
                    </div>
                    
                    <div id="result-container" style="display: none; margin: 20px 0;"></div>
                    
                    <p>
                        <button type="submit" id="start-btn" class="button button-primary button-large" 
                                style="padding: 10px 30px; height: auto; font-size: 16px;">
                            ▶️ התחל עדכון המוני
                        </button>
                    </p>
                </form>
            </div>
            
            <!-- שחזור ומחיקה -->
            <?php if ($has_backup): ?>
            <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #dc3545; border-radius: 5px; max-width: 700px;">
                <h2 style="color: #dc3545;">🔄 ניהול גיבוי</h2>
                <p style="font-size: 15px;">נמצא גיבוי מעדכון קודם. ניתן לשחזר את כל המוצרים למצב המקורי או למחוק את הגיבוי.</p>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <!-- שחזור -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                        <input type="hidden" name="action" value="rollback_bulk_sale">
                        <?php wp_nonce_field('rollback_bulk_sale'); ?>
                        
                        <button type="submit" class="button button-secondary button-large"
                                style="padding: 10px 30px; height: auto; font-size: 16px; border-color: #dc3545; color: #dc3545;"
                                onclick="return confirm('האם אתה בטוח? פעולה זו תשחזר את כל המוצרים למצב המקורי לפני המבצע.\n\nהגיבוי יימחק אחרי השחזור!');">
                            ↩️ שחזר למצב קודם
                        </button>
                    </form>
                    
                    <!-- מחיקה -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                        <input type="hidden" name="action" value="delete_backup">
                        <?php wp_nonce_field('delete_backup'); ?>
                        
                        <button type="submit" class="button button-link-delete"
                                style="padding: 10px 20px; height: auto; font-size: 16px; color: #b32d2e; text-decoration: none;"
                                onclick="return confirm('למחוק את הגיבוי?\n\n⚠️ לא תוכל לשחזר את המוצרים אחרי מחיקת הגיבוי!');">
                            🗑️ מחק גיבוי
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let currentOffset = 0;
            let totalUpdated = 0;
            let totalSkipped = 0;
            let totalExcluded = 0;
            let isProcessing = false;
            
            $('#include_on_sale').on('change', function() {
                const includeOnSale = $(this).is(':checked');
                const baseCount = <?php echo $total_products; ?>;
                const withSale = <?php echo $total_with_sale; ?>;
                
                if (includeOnSale) {
                    $('.form-table tr:first-of-type td p.description').html(
                        'יעודכנו: <strong>' + (baseCount + withSale).toLocaleString('he-IL') + '</strong> פריטים (כולל מוצרים במבצע)'
                    );
                } else {
                    $('.form-table tr:first-of-type td p.description').text('הכנס את אחוז ההנחה הרצוי (1-99)');
                }
            });
            
            $('#bulk-sale-form').on('submit', function(e) {
                e.preventDefault();
                
                if (isProcessing) return;
                
                const discountPercent = parseFloat($('#discount_percent').val());
                const includeOnSale = $('#include_on_sale').is(':checked');
                const excludeProducts = $('#exclude_products').val().trim();
                
                if (discountPercent < 1 || discountPercent > 99) {
                    alert('אחוז ההנחה חייב להיות בין 1 ל-99');
                    return;
                }
                
                const baseCount = <?php echo $total_products; ?>;
                const withSale = <?php echo $total_with_sale; ?>;
                const totalProducts = includeOnSale ? (baseCount + withSale) : baseCount;
                
                let confirmMsg = 'האם אתה בטוח?\n\n' +
                                 '📊 יעודכנו: ' + totalProducts.toLocaleString('he-IL') + ' פריטים\n' +
                                 '💰 הנחה: ' + discountPercent + '%\n' +
                                 '📅 תקופה: ' + $('#sale_from').val() + ' עד ' + $('#sale_to').val();
                
                if (includeOnSale) {
                    confirmMsg += '\n\n⚠️ כולל דריסה של מוצרים שכבר במבצע!';
                }
                
                if (excludeProducts) {
                    const excludeCount = excludeProducts.split(',').length;
                    confirmMsg += '\n\n🚫 יוחרגו ' + excludeCount + ' מוצרים';
                }
                
                if (!confirm(confirmMsg)) {
                    return;
                }
                
                isProcessing = true;
                currentOffset = 0;
                totalUpdated = 0;
                totalSkipped = 0;
                totalExcluded = 0;
                
                $('#start-btn').prop('disabled', true);
                $('#progress-container').show();
                $('#result-container').hide();
                
                processBatch();
            });
            
            function processBatch() {
                const saleFrom = $('#sale_from').val();
                const saleTo = $('#sale_to').val();
                const discountPercent = $('#discount_percent').val();
                const includeOnSale = $('#include_on_sale').is(':checked') ? '1' : '0';
                const excludeProducts = $('#exclude_products').val();
                const nonce = $('#bulk-sale-form input[name="nonce"]').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'process_batch',
                        nonce: nonce,
                        offset: currentOffset,
                        sale_from: saleFrom,
                        sale_to: saleTo,
                        discount_percent: discountPercent,
                        include_on_sale: includeOnSale,
                        exclude_products: excludeProducts
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            totalUpdated += data.updated;
                            totalSkipped += data.skipped;
                            totalExcluded += data.excluded;
                            currentOffset += data.processed;
                            
                            const includeOnSaleChecked = $('#include_on_sale').is(':checked');
                            const baseCount = <?php echo $total_products; ?>;
                            const withSale = <?php echo $total_with_sale; ?>;
                            const totalProducts = includeOnSaleChecked ? (baseCount + withSale) : baseCount;
                            
                            const progress = Math.min(100, Math.round((currentOffset / totalProducts) * 100));
                            $('#progress-bar').css('width', progress + '%').text(progress + '%');
                            
                            let statusText = 'עובד... <strong>' + progress + '%</strong> | עודכנו <strong>' + totalUpdated.toLocaleString('he-IL') + '</strong> מוצרים';
                            if (totalExcluded > 0) {
                                statusText += ' | הוחרגו <strong>' + totalExcluded.toLocaleString('he-IL') + '</strong>';
                            }
                            $('#progress-text').html(statusText);
                            
                            if (data.has_more) {
                                processBatch();
                            } else {
                                finishProcess();
                            }
                        } else {
                            showError('שגיאה: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        showError('שגיאת תקשורת עם השרת: ' + error);
                    }
                });
            }
            
            function finishProcess() {
                isProcessing = false;
                $('#progress-bar').css('width', '100%').text('100%');
                $('#progress-text').html('✅ <strong>הושלם בהצלחה!</strong>');
                
                const discountPercent = $('#discount_percent').val();
                
                window.location.href = '<?php echo admin_url('admin.php?page=safe-bulk-sale'); ?>' + 
                                       '&completed=1' + 
                                       '&updated=' + totalUpdated + 
                                       '&skipped=' + totalSkipped + 
                                       '&excluded=' + totalExcluded +
                                       '&discount=' + discountPercent;
            }
            
            function showError(message) {
                isProcessing = false;
                $('#result-container').html(
                    '<div class="notice notice-error" style="padding: 15px; border-right: 4px solid #dc3545;">' +
                    '<h3 style="color: #dc3545;">❌ שגיאה</h3>' +
                    '<p>' + message + '</p>' +
                    '<p>נסה שוב או בדוק את הלוג לפרטים נוספים.</p>' +
                    '</div>'
                ).show();
                $('#start-btn').prop('disabled', false);
                $('#progress-container').hide();
            }
        });
        </script>
        
        <style>
        .form-table th {
            padding: 20px 10px 20px 0;
            width: 200px;
            vertical-align: top;
        }
        .form-table td {
            padding: 15px 10px;
        }
        .form-table tr {
            border-bottom: 1px solid #f0f0f1;
        }
        code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        </style>
        <?php
    }
}

// הפעלת המחלקה
new Safe_Bulk_Sale_Manager();
    // End Code Snippet Code

}, 10);