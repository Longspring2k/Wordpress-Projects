<?php
/**
 * Band rhythm: two adjacent full-width bands with the same background read as
 * one surface, so the seam between them gets half the usual section padding.
 * Bands with different backgrounds keep the full padding.
 * Paired with assets/css/band-rhythm.css and assets/js/band-rhythm.js.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function brightpath_enqueue_band_rhythm() {
	foreach ( array( 'css', 'js' ) as $ext ) {
		$rel  = 'assets/' . $ext . '/band-rhythm.' . $ext;
		$path = get_theme_file_path( $rel );
		if ( ! file_exists( $path ) ) {
			continue;
		}
		if ( 'css' === $ext ) {
			wp_enqueue_style( 'brightpath-band-rhythm', get_theme_file_uri( $rel ), array(), (string) filemtime( $path ) );
		} else {
			// In the footer, not deferred: it runs as soon as the bands are parsed, before first paint.
			wp_enqueue_script( 'brightpath-band-rhythm', get_theme_file_uri( $rel ), array(), (string) filemtime( $path ), true );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_band_rhythm', 30 );
