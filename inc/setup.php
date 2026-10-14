<?php
/**
 * Bright Path — theme supports.
 *
 * OWNER: Main.
 *
 * One shard of what used to be a single functions.php. The split exists so
 * that parallel work cannot collide: `.design-core/` is not under version
 * control, so two agents editing one file means one of them is lost, not
 * merged. Loaded by functions.php; never loaded directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function brightpath_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'script', 'style' ) );

	// The editor canvas needs the same shards the front end gets, in the same
	// order, or the canvas stops resembling the page the client is editing.
	foreach ( brightpath_style_shards() as $shard ) {
		add_editor_style( 'assets/css/' . $shard . '.css' );
	}
	// Parity shards are enqueued on wp_enqueue_scripts only (inc/design-parity.php);
	// mirror them here in front-end order, then the editor-only overrides.
	foreach ( array( 'parity', 'parity-editorial', 'parity-content', 'editor', 'editor-parity-A', 'editor-parity-B', 'editor-parity-C', 'editor-parity-D' ) as $shard ) {
		if ( file_exists( get_theme_file_path( 'assets/css/' . $shard . '.css' ) ) ) {
			add_editor_style( 'assets/css/' . $shard . '.css' );
		}
	}
}
add_action( 'after_setup_theme', 'brightpath_setup' );

/**
 * Editor canvas parity: mirror front-end body classes into the iframe and
 * hide the post title for templates that never print it.
 */
function brightpath_editor_canvas_assets() {
	$js = 'assets/js/editor-canvas.js';
	if ( ! file_exists( get_theme_file_path( $js ) ) ) {
		return;
	}
	wp_enqueue_script( 'brightpath-editor-canvas', get_theme_file_uri( $js ), array( 'wp-data', 'wp-editor', 'wp-blocks' ), (string) filemtime( get_theme_file_path( $js ) ), true );
	wp_add_inline_script( 'brightpath-editor-canvas', 'window.brightpathEditor=' . wp_json_encode( array( 'frontId' => (int) get_option( 'page_on_front' ) ) ) . ';', 'before' );
}
add_action( 'enqueue_block_editor_assets', 'brightpath_editor_canvas_assets' );

/**
 * Editor-parity scripts (one per agent lane: A visual bands, B query/bindings,
 * C locks/editability, D forms). Missing files are skipped. They run in the
 * editor window, not in the canvas iframe; CSS goes through add_editor_style.
 */
function brightpath_editor_parity_assets() {
	foreach ( array( 'A', 'B', 'C', 'D' ) as $lane ) {
		$js = 'assets/js/editor-parity-' . $lane . '.js';
		if ( file_exists( get_theme_file_path( $js ) ) ) {
			wp_enqueue_script( 'brightpath-editor-parity-' . $lane, get_theme_file_uri( $js ), array( 'wp-data', 'wp-editor', 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-compose', 'wp-block-editor', 'brightpath-editor-canvas' ), (string) filemtime( get_theme_file_path( $js ) ), true );
		}
	}
}
add_action( 'enqueue_block_editor_assets', 'brightpath_editor_parity_assets', 20 );
