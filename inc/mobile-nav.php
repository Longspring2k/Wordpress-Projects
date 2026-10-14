<?php
/**
 * Mobile menu overlay: matches the prototype .mobile-nav sheet (brightpath-html
 * main.css 1007-1066) on top of core/navigation's responsive overlay.
 * Paired with assets/css/mobile-nav.css and assets/js/mobile-nav.js; deleting
 * the three files rolls the layer back.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function brightpath_enqueue_mobile_nav() {
	$css = 'assets/css/mobile-nav.css';
	$js  = 'assets/js/mobile-nav.js';

	if ( file_exists( get_theme_file_path( $css ) ) ) {
		wp_enqueue_style( 'brightpath-mobile-nav', get_theme_file_uri( $css ), array(), (string) filemtime( get_theme_file_path( $css ) ) );
	}
	if ( file_exists( get_theme_file_path( $js ) ) ) {
		wp_enqueue_script( 'brightpath-mobile-nav', get_theme_file_uri( $js ), array(), (string) filemtime( get_theme_file_path( $js ) ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_mobile_nav', 30 );
