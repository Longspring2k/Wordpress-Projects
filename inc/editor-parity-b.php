<?php
/**
 * Editor parity, lane B: values the block editor needs to draw the same card
 * text / bound fields the front prints from PHP (inc/design-parity.php,
 * inc/post-types.php).
 *
 * Read-only REST field `bp_parity` on the design post types, only for users
 * who can edit posts. Nothing here runs on the front and nothing is stored:
 * every value is computed by the SAME functions the front render path uses.
 *
 * @package brightpath
 */

/**
 * Post types that carry cards / bound fields.
 *
 * @return string[]
 */
function brightpath_editor_parity_types() {
	return array( 'page', 'post', 'service', 'resource', 'job', 'location', 'sil_property', 'team_member' );
}

/**
 * Compute every design value for one record.
 *
 * @param array $post REST post array (needs id).
 * @return array<string,string>|null
 */
function brightpath_editor_parity_values( $post ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return null;
	}
	$id = isset( $post['id'] ) ? (int) $post['id'] : 0;
	if ( ! $id ) {
		return null;
	}
	$out = array(
		'card_title' => (string) get_post_meta( $id, 'bp_card_title', true ),
		'card_text'  => (string) get_post_meta( $id, 'bp_card_text', true ),
	);
	if ( function_exists( 'brightpath_parity_vacancy_label' ) ) {
		$out['vacancy_label'] = brightpath_parity_vacancy_label( $id );
		$out['property_specs'] = brightpath_parity_property_specs( $id );
		$out['job_meta']       = brightpath_parity_job_meta( $id );
	}
	$obj                    = get_post_type_object( get_post_type( $id ) );
	$out['post_type_label'] = $obj ? (string) $obj->labels->name : '';
	if ( function_exists( 'brightpath_derived_value' ) ) {
		foreach ( array( 'read_time', 'team_role', 'media_label', 'location_h1', 'location_cta', 'author_initials', 'author_name', 'author_role' ) as $key ) {
			$out[ $key ] = (string) brightpath_derived_value( $key, $id );
		}
	}
	return $out;
}

/**
 * Register the field.
 */
function brightpath_editor_parity_register_rest() {
	foreach ( brightpath_editor_parity_types() as $type ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}
		register_rest_field(
			$type,
			'bp_parity',
			array(
				'get_callback' => 'brightpath_editor_parity_values',
				'schema'       => array(
					'description' => 'Design values the editor mirrors from the front render path (read-only).',
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);
	}
}
add_action( 'rest_api_init', 'brightpath_editor_parity_register_rest' );
