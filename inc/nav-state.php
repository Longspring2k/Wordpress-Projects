<?php
/**
 * Bright Path — current-page state on the navigation.
 *
 * OWNER: Main.
 *
 * `core/navigation-link` marks itself active only when the block carries an `id`
 * (a post ID) and a `kind`, and the queried object matches. Authored links that
 * are plain URLs therefore never light up, which is what design-QA measured: on
 * `/about/` the About link had `aria-current` null, no `.current-menu-item`,
 * colour ink-900 and weight 400, where the design has forest-700, weight 600 and
 * a 2px terracotta underline.
 *
 * Binding by post ID would fix it and would also make the header depend on IDs
 * that differ between installs and change whenever a page is rebuilt. So this
 * compares the rendered href with the current request instead, which is true on
 * any install and survives a rebuild.
 *
 * One shard of what used to be a single functions.php; loaded by functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalise a URL to a comparable path: no host, no query, no trailing slash,
 * always leading-slash. `/about/`, `https://site/about`, and `/about/?x=1` all
 * become `/about`.
 */
function brightpath_nav_path( $url ) {
	$path = wp_parse_url( (string) $url, PHP_URL_PATH );

	if ( null === $path || '' === $path ) {
		return '/';
	}

	$path = '/' . trim( $path, '/' );

	return '/' === $path ? '/' : $path;
}

/** The path WordPress is currently serving, including the front page. */
function brightpath_current_path() {
	if ( is_front_page() ) {
		return '/';
	}

	return brightpath_nav_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
}

/**
 * Mark the navigation link for the current page, and its ancestor.
 *
 * Ancestor matching is what makes `/services/daily-living/` light up the
 * Services item: the design's dropdown parents have no page of their own, so an
 * exact match would leave every service page with no nav state at all.
 */
function brightpath_navigation_link_state( $block_content, $block ) {
	if ( empty( $block_content ) || is_admin() ) {
		return $block_content;
	}

	if ( ! preg_match( '/href=["\']([^"\']+)["\']/', $block_content, $m ) ) {
		return $block_content;
	}

	$link    = brightpath_nav_path( $m[1] );
	$current = brightpath_current_path();

	if ( '/' === $link && '/' !== $current ) {
		return $block_content; // Home must not match everything.
	}

	if ( $link === $current ) {
		$classes = 'current-menu-item';
		$aria    = ' aria-current="page"';
	} elseif ( '/' !== $link && 0 === strpos( $current, $link . '/' ) ) {
		$classes = 'current-menu-ancestor';
		$aria    = '';
	} else {
		return $block_content;
	}

	/*
	 * Idempotent: a submenu's content passes through this filter more than once
	 * -- measured, the class landed twice on the same <li>. Rather than work out
	 * every path that re-renders a block, refuse to add a class that is already
	 * there. A filter that is safe to run twice is safe whatever core does next.
	 */
	if ( false !== strpos( $block_content, $classes ) ) {
		return $block_content;
	}

	// Add the class to the <li> wrapper if there is one, otherwise to the <a>.
	$content = preg_replace(
		'/(<li\b[^>]*class=["\'])/',
		'$1' . $classes . ' ',
		$block_content,
		1,
		$count
	);

	if ( ! $count ) {
		$content = preg_replace( '/(<a\b[^>]*class=["\'])/', '$1' . $classes . ' ', $block_content, 1, $count );
	}

	if ( ! $count ) {
		$content = $block_content;
	}

	if ( $aria ) {
		$content = preg_replace( '/<a\b(?![^>]*aria-current)/', '<a' . $aria, $content, 1 );
	}

	return $content;
}
/*
 * The hook is `render_block_{$block_name}` with the block name VERBATIM, so it
 * carries a slash: `render_block_core/navigation-link`. Registered with
 * underscores it attaches cleanly, raises nothing, and never fires -- which is
 * exactly what happened here and cost a deploy to notice.
 */
add_filter( 'render_block_core/navigation-link', 'brightpath_navigation_link_state', 10, 2 );
add_filter( 'render_block_core/navigation-submenu', 'brightpath_navigation_link_state', 10, 2 );
