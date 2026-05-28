<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
    
/**
 * Fix Imagify <picture> tag conflict with Bricks lazy loading
 *
 * Imagify wraps <img> in <picture> + <source>, breaking Bricks' lazy loader.
 * Bricks swaps data-src/data-srcset on <img> but doesn't touch <source>.
 * The <source> has a placeholder SVG in srcset, so images show blank.
 * Fix: unwrap Imagify's <picture> tags, restore classes to <img>,
 * and swap <img> URLs to WebP versions from the <source>.
 *
 * @package Custom
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Method A: WP Rocket buffer (processes cached pages, runs after Imagify)
add_filter( 'rocket_buffer', 'perf_fix_imagify_bricks_picture', PHP_INT_MAX );

// Method B: Output buffer (catches non-cached pages)
// Priority 1 = starts BEFORE Imagify's buffer (priority 10), making ours the
// outer buffer so it processes AFTER Imagify wraps <img> in <picture> tags.
add_action( 'template_redirect', function() {
    if ( is_admin() ) return;
    ob_start( 'perf_fix_imagify_bricks_picture' );
}, 1 );

function perf_fix_imagify_bricks_picture( $html ) {
    if ( empty( $html ) || strpos( $html, '<picture' ) === false ) return $html;

    $html = preg_replace_callback(
        '/<picture([^>]*)>(.*?)<\/picture>/s',
        function( $m ) {
            $pic_attrs = $m[1];
            $inner     = $m[2];

            // Only process Imagify-created <picture> tags (skip Bricks' own with brxe- id)
            if ( preg_match( '/id="brxe-/', $pic_attrs ) ) return $m[0];

            // Extract <source> data-srcset (WebP URLs from Imagify)
            $webp_srcset = '';
            if ( preg_match( '/<source[^>]*data-srcset="([^"]*)"/', $inner, $sm ) ) {
                $webp_srcset = $sm[1];
            }

            // Extract the <img> tag
            if ( ! preg_match( '/<img[^>]*>/', $inner, $img_match ) ) return $m[0];
            $img = $img_match[0];

            // Restore classes from <picture> to <img>
            if ( preg_match( '/class="([^"]*)"/', $pic_attrs, $pc ) ) {
                $pic_classes = $pc[1];
                if ( preg_match( '/class="([^"]*)"/', $img, $ic ) ) {
                    $img = str_replace( 'class="' . $ic[1] . '"', 'class="' . $ic[1] . ' ' . $pic_classes . '"', $img );
                } else {
                    $img = str_replace( '<img ', '<img class="' . $pic_classes . '" ', $img );
                }
            }

            // Swap <img> data-srcset to WebP versions if available
            if ( $webp_srcset && preg_match( '/data-srcset="([^"]*)"/', $img, $ds ) ) {
                $img = str_replace( 'data-srcset="' . $ds[1] . '"', 'data-srcset="' . $webp_srcset . '"', $img );
            }

            // Swap <img> data-src to WebP version if available
            if ( preg_match( '/data-src="([^"]*\.(jpe?g|png))"/', $img, $dsm ) ) {
                $webp_url = $dsm[1] . '.webp';
                $img = str_replace( 'data-src="' . $dsm[1] . '"', 'data-src="' . $webp_url . '"', $img );
            }

            return $img;
        },
        $html
    );

    return $html;
}

    // End Code Snippet Code

}, 10);