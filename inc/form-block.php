<?php
/**
 * Registers the brightpath/form block (server-rendered, no build step).
 *
 * Moved in from the brightpath-core plugin together with inc/{mail,post-types,forms}.php.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Form slugs offered in the editor, straight from the schema. */
function brightpath_form_block_slugs() {
	return array_keys( brightpath_forms_schema() );
}

function brightpath_register_form_block() {
	$handle = 'brightpath-form-editor';
	$rel    = 'blocks/form/editor.js';
	$file   = get_theme_file_path( $rel );

	wp_register_script(
		$handle,
		get_theme_file_uri( $rel ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		file_exists( $file ) ? (string) filemtime( $file ) : null,
		true
	);
	wp_localize_script( $handle, 'brightpathFormBlock', array(
		'slugs' => brightpath_form_block_slugs(),
	) );

	register_block_type( get_theme_file_path( 'blocks/form' ) );
}
add_action( 'init', 'brightpath_register_form_block', 13 );
