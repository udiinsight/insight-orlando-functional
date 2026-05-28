<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

// הוסף לראות את הgroups הכי גדולים בObject Cache Pro dashboard
add_filter('objectcache/analytics/include', function() {
    return ['*'];
});
    // End Code Snippet Code

}, 10);