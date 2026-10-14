<?php
/**
 * brightpath/form render. Output is exactly brightpath_forms_render() — the same
 * markup the legacy wp:html marker produced.
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug = sanitize_key( $attributes['formSlug'] ?? 'contact' );

if ( ! function_exists( 'brightpath_forms_get' ) || ! brightpath_forms_get( $slug ) ) {
	return;
}

// Editor (ServerSideRender goes through REST): inert preview, no nonce/timing token.
if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
	echo '<div class="bp-form-wrap bp-form-preview">' . brightpath_forms_preview_html( $slug ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

echo brightpath_forms_render( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput
