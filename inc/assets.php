<?php
/**
 * Bright Path — stylesheets and the reveal script.
 *
 * OWNER: Main. A track adds its own shard by appending one slug to
 * brightpath_style_shards(); it does not touch anything else here.
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
 * The CSS shards, in cascade order. Each has exactly one owner so that
 * parallel work cannot overwrite itself; see the banner in each file.
 *
 * A shard that does not exist yet is skipped, so a track can add its slug
 * here before or after it writes the file without breaking the site.
 */
function brightpath_style_shards() {
	return array(
		'foundation', // Main
		'bands',      // the band track
		'posts',      // the post-type track
		'forms',      // the forms track
		'fix-global', // parity pass: header, nav, breadcrumb, buttons, CTA, typography
		'fix-a',      // parity pass: home, about, services, careers, our-team, sil
		'fix-b',      // parity pass: forms pages + faq, locations
		'fix-c',      // parity pass: resources, sil-homes, legal, detail templates
		'fix-d',      // parity pass D2: service detail, location residuals, legal
		'fix-resp-A', // responsive sprint A: SIL region/home/archive
		'fix-resp-B', // responsive sprint B: locations + feedback/contact/forms
		'fix-resp-C', // responsive sprint C: global grids, headings, search, footer, nav
		'fix-audit',  // responsive audit 2026-10-14: alignment and tablet grids
	);
}

/**
 * Front-end stylesheets.
 *
 * Every shard depends on 'global-styles' so it prints AFTER the theme.json
 * output. foundation.css overrides --wp--style--root--padding-left/right
 * inside media queries; both rules have specificity (0,0,1) on `body`, so
 * source order is the only thing that decides the winner. Each shard also
 * depends on the one before it, which fixes the order between shards.
 */
function brightpath_enqueue_styles() {
	$prev = 'global-styles';

	foreach ( brightpath_style_shards() as $shard ) {
		$rel  = 'assets/css/' . $shard . '.css';
		$path = get_theme_file_path( $rel );

		if ( ! file_exists( $path ) ) {
			continue;
		}

		$handle = 'brightpath-' . $shard;
		wp_enqueue_style( $handle, get_theme_file_uri( $rel ), array( $prev ), (string) filemtime( $path ) );
		$prev = $handle;
	}
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_styles' );

/**
 * Entrance reveal. Two pieces, both load-bearing:
 *
 *  1. `.bp-js` on <html>, printed inline in wp_head so it lands BEFORE first
 *     paint. Section 6 of bands.css scopes every opacity:0 to `.bp-js`, so
 *     with JavaScript off, blocked, or merely slow, the content is visible.
 *     Printing this with the deferred script instead would flash every band
 *     blank until the script lands.
 *  2. The observer itself, deferred — NOT async, which can execute before the
 *     document has parsed the nodes it needs to observe.
 *
 * Both hang off wp_head / wp_enqueue_scripts, which the editor canvas does not
 * run, so `.bp-js` never reaches the editor and pattern content is visible
 * there by construction rather than by an is_admin() guard.
 */
function brightpath_reveal_js_flag() {
	echo "<script>document.documentElement.classList.add('bp-js');</script>\n";
}
add_action( 'wp_head', 'brightpath_reveal_js_flag', 1 );

/**
 * Client-side archive filters.
 *
 * Only enqueued where a control group exists, which keeps it off the 28 pages
 * that have no archive. It binds to `.bp-filter` and to the taxonomy classes
 * `get_post_class()` already prints, so there is no data contract to keep in
 * sync -- see BUILD-CONTRACT.md 16.
 */
/**
 * Page chrome: the scrolled header, back-to-top and the mobile sticky CTA.
 * Injected rather than authored, so a page without JavaScript has none of them
 * rather than three dead controls.
 */
function brightpath_enqueue_chrome() {
	$rel  = 'assets/js/chrome.js';
	$path = get_theme_file_path( $rel );

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'brightpath-chrome',
		get_theme_file_uri( $rel ),
		array(),
		(string) filemtime( $path ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_chrome' );

function brightpath_enqueue_filters() {
	$rel  = 'assets/js/filters.js';
	$path = get_theme_file_path( $rel );

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'brightpath-filters',
		get_theme_file_uri( $rel ),
		array(),
		(string) filemtime( $path ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_filters' );

function brightpath_enqueue_reveal() {
	$rel  = 'assets/js/reveal.js';
	$path = get_theme_file_path( $rel );

	wp_enqueue_script(
		'brightpath-reveal',
		get_theme_file_uri( $rel ),
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : null,
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'brightpath_enqueue_reveal' );

/*
 * Core's emoji loader swaps emoji characters for images fetched from s.w.org.
 * On an ordinary page that is merely a third-party request; on a 44-field
 * health-data intake form it is a request to an Automattic CDN carrying the
 * visitor's IP and user agent, made from the page where they are typing
 * clinical information about a third party.
 *
 * Measured before removing it: 0 external requests on load, but 2 requests to
 * s.w.org after a single emoji entered the DOM. The page has no emoji today --
 * and the field labels, success copy and recipient address are all client
 * editable, so "today" is not a property anyone maintains. The site ships no
 * emoji imagery, so the loader buys nothing.
 */
function brightpath_disable_emoji_loader() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'brightpath_disable_emoji_loader' );
