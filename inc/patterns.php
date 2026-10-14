<?php
/**
 * Bright Path — pattern categories
 *
 * OWNER: Main. A track registering its own category appends here.
 *
 * One shard of what used to be a single functions.php. The split exists so
 * that parallel work cannot collide: `.design-core/` is not under version
 * control, so two agents editing one file means one of them is lost, not
 * merged. Loaded by functions.php; never loaded directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * (c) Pattern categories.
 */
function brightpath_register_pattern_categories() {
	register_block_pattern_category(
		'brightpath-bands',
		array(
			'label'       => __( 'Bright Path bands', 'brightpath' ),
			'description' => __( 'Full-width page sections.', 'brightpath' ),
		)
	);
	register_block_pattern_category(
		'brightpath-detail',
		array(
			'label'       => __( 'Bright Path detail', 'brightpath' ),
			'description' => __( 'Smaller compositions used inside bands.', 'brightpath' ),
		)
	);
}
add_action( 'init', 'brightpath_register_pattern_categories' );

/**
 * Block styles for core/button.
 *
 * `outline` ships with core. `ghost` does not — the band track measured a
 * `is-style-ghost` class rendering with no `is-style-ghost--n` instance class,
 * which is how an unregistered style looks: it works, because the class is just
 * a class, and it is invisible in the editor's style picker. A variant the
 * client cannot find is a variant the client does not have.
 *
 * Registering them also puts the resting states in the editor canvas, where
 * until now every button drew as a pill because theme.json owned the radius.
 */
function brightpath_register_block_styles() {
	register_block_style( 'core/button', array(
		'name'  => 'ghost',
		'label' => __( 'Ghost', 'brightpath' ),
	) );

	register_block_style( 'core/button', array(
		'name'  => 'large',
		'label' => __( 'Large', 'brightpath' ),
	) );
}
add_action( 'init', 'brightpath_register_block_styles' );

/**
 * Block style variations (phase 3-6 pattern consolidation).
 *
 * Each style only adds an `is-style-*` class; assets/css/variations.css maps that
 * class onto the SAME rules the retired one-off pattern classes used, so a merged
 * pattern renders like the variant it replaces. Existing pages keep their old
 * classes and are untouched.
 */
function brightpath_register_variation_styles() {
	register_block_style( 'core/group', array(
		'name'  => 'dark',
		'label' => __( 'Dark (forest)', 'brightpath' ),
	) );
	register_block_style( 'core/columns', array(
		'name'  => 'cols-3',
		'label' => __( '3 columns', 'brightpath' ),
	) );
	register_block_style( 'core/columns', array(
		'name'  => 'cols-4',
		'label' => __( '4 columns', 'brightpath' ),
	) );
	register_block_style( 'core/post-template', array(
		'name'  => 'cols-3',
		'label' => __( '3 columns', 'brightpath' ),
	) );
	register_block_style( 'core/post-template', array(
		'name'  => 'cols-4',
		'label' => __( '4 columns', 'brightpath' ),
	) );
}
add_action( 'init', 'brightpath_register_variation_styles' );

/**
 * Load the variation stylesheet after every shard (front end and editor).
 */
function brightpath_enqueue_variations() {
	$rel  = 'assets/css/variations.css';
	$path = get_theme_file_path( $rel );
	if ( ! file_exists( $path ) ) {
		return;
	}
	$deps = wp_style_is( 'brightpath-fix-resp-C', 'registered' ) ? array( 'brightpath-fix-resp-C' ) : array( 'global-styles' );
	wp_enqueue_style( 'brightpath-variations', get_theme_file_uri( $rel ), $deps, (string) filemtime( $path ) );
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_variations', 20 );

function brightpath_variations_editor_style() {
	add_editor_style( 'assets/css/variations.css' );
}
add_action( 'after_setup_theme', 'brightpath_variations_editor_style', 20 );
