<?php
/**
 * Body class per page slug: `bp-page-<slug>` (e.g. bp-page-careers).
 *
 * Page-specific CSS keys off this instead of `page-id-N`, so it still applies
 * when the theme's starter content lands under different IDs on another site.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function brightpath_page_slug_class( $classes ) {
	if ( is_page() ) {
		$page = get_queried_object();
		if ( $page && ! empty( $page->post_name ) ) {
			$classes[] = 'bp-page-' . sanitize_html_class( $page->post_name );
		}
	}
	return $classes;
}
add_filter( 'body_class', 'brightpath_page_slug_class' );
