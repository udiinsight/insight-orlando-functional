<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                
	// Condition Builder helper class
	$wpContext = new \WFPCore\WordPressContext();

	// Condition Builder generated Conditions
	if( !( ( !$wpContext->is_frontend() ) )) {
		return false;
	}

	// Code Snippet Code
     

// Add a dashboard widget to display ACF repeater links
function dashboard_links_widget() {
    wp_add_dashboard_widget(
        'dashboard_links_widget',           // Widget slug
        'קישורים מהירים',                    // Title
        'display_dashboard_links'           // Display function
    );
}
add_action('wp_dashboard_setup', 'dashboard_links_widget');

// Function to display the dashboard links
function display_dashboard_links() {
    // Check if the ACF function exists and if the field has values
    if (function_exists('have_rows') && have_rows('dashboard_links', 'option')) {
        echo '<div class="dashboard-links-container">';
        
        // Loop through the repeater field
        while (have_rows('dashboard_links', 'option')) {
            the_row();
            
            // Get sub field values
            $title = get_sub_field('dash_title');
            $link = get_sub_field('dash_link');
            
            // Only display if both fields have values
            if ($title && $link) {
                echo '<div class="dashboard-link-item">';
                echo '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        
        // Add some basic styling
        echo '<style>
            .dashboard-links-container {
                margin: 10px 0;
            }
            .dashboard-link-item {
                margin-bottom: 8px;
            }
            .dashboard-link-item a {
                display: block;
                padding: 8px 12px;
                background-color: #f7f7f7;
                border-radius: 4px;
                text-decoration: none;
                transition: all 0.2s ease;
            }
            .dashboard-link-item a:hover {
                background-color: #0073aa;
                color: #fff;
            }
        </style>';
    } else {
        echo 'לא נמצאו קישורים להצגה.';
    }
}
    // End Code Snippet Code

}, 10);