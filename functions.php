<?php
/**
 * Bright Path theme bootstrap.
 *
 * This file is a LOADER and nothing else. Every behaviour lives in a shard
 * under inc/, each with a single named owner, so that parallel work cannot
 * overwrite itself: `.design-core/` is not under version control, so two
 * agents editing one file means one of them is lost, not merged.
 *
 * To add behaviour, add a shard and list it below. Do not add a function here.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A shard that does not exist yet is skipped, so a track can be listed here
 * before it writes its file without taking the site down.
 */
foreach ( array(
	'setup',         // Main
	'assets',        // Main
	'patterns',      // Main
	'editor-locks',  // Main
	'nav-state',     // Main
	'design-parity', // theme agent C: design parity
	'editor-parity-b', // editor parity lane B: REST values for editor cards / bindings
	'icons',         // icon lane I1: icon library + block icon picker
	'icon-meta',     // icon lane I2: per-post icon meta for query cards
	'mobile-nav',    // mobile menu overlay parity with the prototype sheet
	'band-rhythm',   // half padding between adjacent same-background bands
	'page-classes',  // bp-page-<slug> body class for page-specific CSS
	'starter-content', // bundled site content, imported on activation
) as $brightpath_shard ) {
	$brightpath_shard_path = get_theme_file_path( 'inc/' . $brightpath_shard . '.php' );

	if ( file_exists( $brightpath_shard_path ) ) {
		require_once $brightpath_shard_path;
	}
}
unset( $brightpath_shard, $brightpath_shard_path );

/*
 * Business logic (post types, forms, mail, the brightpath/form block) lives in
 * the theme again; the brightpath-core plugin was folded back in. The guard
 * stays so a stray re-activation of the plugin cannot fatal on a redeclare.
 */
if ( ! defined( 'BRIGHTPATH_CORE_VERSION' ) ) {
	foreach ( array( 'mail', 'post-types', 'forms', 'form-block' ) as $brightpath_legacy ) {
		require_once get_theme_file_path( 'inc/' . $brightpath_legacy . '.php' );
	}
	unset( $brightpath_legacy );
}
