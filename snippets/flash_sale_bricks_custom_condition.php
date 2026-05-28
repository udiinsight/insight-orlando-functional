<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Flash Sale - Bricks Custom Condition
 * 
 * Adds "Flash Sale" condition to Bricks Element Conditions
 * This allows you to conditionally display elements (including popups)
 * based on whether Flash Sale is currently active
 * 
 * Add this to your existing Flash Sale wpCodeBox2 snippet
 * 
 * @since Bricks 1.8.4+
 */

// ============================================================================
// BRICKS CUSTOM CONDITION - FLASH SALE
// ============================================================================

/**
 * Step 1: Add "Flash Sale" condition group
 */
add_filter('bricks/conditions/groups', 'flash_sale_add_condition_group');
function flash_sale_add_condition_group($groups) {
    $groups[] = array(
        'name'  => 'flash_sale',
        'label' => __('Flash Sale', 'bricks'),
    );
    
    return $groups;
}

/**
 * Step 2: Add condition options
 */
add_filter('bricks/conditions/options', 'flash_sale_add_condition_options');
function flash_sale_add_condition_options($options) {
    
    // Condition 1: Is Flash Sale Active
    $options[] = array(
        'key'     => 'flash_sale_is_active',
        'label'   => __('Flash Sale is Active', 'bricks'),
        'group'   => 'flash_sale',
        'compare' => array(
            'type'        => 'select',
            'options'     => array(
                '=='  => __('is', 'bricks'),
                '!='  => __('is not', 'bricks'),
            ),
            'placeholder' => __('is', 'bricks'),
        ),
        'value'   => array(
            'type'        => 'select',
            'options'     => array(
                'active'   => __('Active', 'bricks'),
                'inactive' => __('Inactive', 'bricks'),
            ),
            'placeholder' => __('Select status', 'bricks'),
        ),
    );
    
    // Condition 2: Has Flash Sale Products (optional - bonus!)
    $options[] = array(
        'key'     => 'flash_sale_has_products',
        'label'   => __('Has Flash Sale Products', 'bricks'),
        'group'   => 'flash_sale',
        'compare' => array(
            'type'        => 'select',
            'options'     => array(
                '=='  => __('is', 'bricks'),
                '!='  => __('is not', 'bricks'),
            ),
            'placeholder' => __('is', 'bricks'),
        ),
        'value'   => array(
            'type'        => 'select',
            'options'     => array(
                'true'  => __('True', 'bricks'),
                'false' => __('False', 'bricks'),
            ),
            'placeholder' => __('Select', 'bricks'),
        ),
    );
    
    return $options;
}

/**
 * Step 3: Execute the condition logic
 */
add_filter('bricks/conditions/result', 'flash_sale_run_condition', 10, 3);
function flash_sale_run_condition($result, $condition_key, $condition) {
    
    // Only handle our conditions
    if (!in_array($condition_key, array('flash_sale_is_active', 'flash_sale_has_products'))) {
        return $result;
    }
    
    $compare = isset($condition['compare']) ? $condition['compare'] : '==';
    $value = isset($condition['value']) ? $condition['value'] : '';
    
    $condition_met = false;
    
    // Handle: Flash Sale is Active
    if ($condition_key === 'flash_sale_is_active') {
        $is_active = is_flash_sale_active();
        
        $current_status = $is_active ? 'active' : 'inactive';
        
        switch ($compare) {
            case '==':
                $condition_met = ($current_status === $value);
                break;
            case '!=':
                $condition_met = ($current_status !== $value);
                break;
        }
    }
    
    // Handle: Has Flash Sale Products (bonus condition)
    if ($condition_key === 'flash_sale_has_products') {
        $products = flash_sale_get_product_ids();
        $has_products = !empty($products);
        
        $current_value = $has_products ? 'true' : 'false';
        
        switch ($compare) {
            case '==':
                $condition_met = ($current_value === $value);
                break;
            case '!=':
                $condition_met = ($current_value !== $value);
                break;
        }
    }
    
    return $condition_met;
}


    // End Code Snippet Code

}, 10);