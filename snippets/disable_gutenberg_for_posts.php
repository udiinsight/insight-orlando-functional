<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    

// ביטול גוטנברג עבור פוסטים בלבד
add_filter('use_block_editor_for_post', function($use_block_editor, $post) {
    // אם זה פוסט רגיל - השבת false כדי לבטל את גוטנברג
    if ($post->post_type === 'post') {
        return false;
    }
    // עבור כל שאר סוגי הפוסטים - השאר כמו שהיה
    return $use_block_editor;
}, 10, 2);

// ביטול גוטנברג עבור פוסטים חדשים גם כן
add_filter('use_block_editor_for_post_type', function($use_block_editor, $post_type) {
    if ($post_type === 'post') {
        return false;
    }
    return $use_block_editor;
}, 10, 2);

// אופציונלי: ביטול גוטנברג לחלוטין עבור כל סוגי הפוסטים

add_filter('use_block_editor_for_post', '__return_false', 10);
add_filter('use_block_editor_for_post_type', '__return_false', 10);


// ביטול הודעות ה-nag על גוטנברג (אופציונלי)
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_script('wp-embed');
});

// ביטול CSS של גוטנברג מה-frontend (אופציונלי)
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-block-style'); // אם יש WooCommerce
}, 100);


    // End Code Snippet Code

}, 10);