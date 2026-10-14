<?php
/**
 * Per-post card icons (lane I2).
 *
 * The icon tile on a query-loop card (services, locations, jobs) used to be
 * painted by hard-coded post-ID CSS (`li.post-45 .icon-tile { --bp-icon: ... }`).
 * It is now a per-post choice: post meta `bp_icon`, edited from the "Card icon"
 * panel in that post's Document sidebar (assets/js/icon-meta-editor.js), read by
 * every listing that prints the card.
 *
 * Meta value:
 *   ''     use the type default (service = sparkle, location = map-pin, job = check)
 *   'none' explicitly no icon
 *   <name> any icon name from the registry (inc/icons.php) — the 39 built-ins
 *          (`--bp-icon-<name>` in bands.css) or a custom-* icon the owner added.
 *
 * Front: render_block_core/post-template adds the existing `bp-icon--<name>`
 * class (bands.css: `.bp-icon--x { --bp-icon: var(--bp-icon-x) }`) to the
 * `.icon-tile` paragraph of every post <li>. Nothing is stored in post_content.
 * Editor: the same value is applied to the tile in the canvas by the JS file.
 *
 * @package brightpath
 */

/** Post types whose cards carry an icon tile. */
function brightpath_icon_meta_types() {
	return (array) apply_filters( 'brightpath_icon_meta_types', array( 'service', 'location', 'job' ) );
}

/** Icon a card shows when the post has no choice yet (was hard-coded in fix-d.css). */
function brightpath_icon_meta_defaults() {
	return array(
		'service'  => 'sparkle',
		'location' => 'map-pin',
		'job'      => 'check',
	);
}

/**
 * Names the picker can store: the registry from inc/icons.php when it exists,
 * otherwise the 39 built-in names.
 *
 * @return string[]
 */
function brightpath_icon_meta_names() {
	static $names = null;
	if ( null !== $names ) {
		return $names;
	}
	if ( function_exists( 'brightpath_icons_all' ) ) {
		$names = array();
		foreach ( brightpath_icons_all() as $icon ) {
			$names[] = (string) $icon['name'];
		}
		return $names;
	}
	$names = array(
		'accessibility', 'alert-circle', 'arrow-right', 'bed', 'book', 'briefcase', 'calendar', 'car', 'check',
		'check-circle', 'clock', 'compass', 'download', 'file-text', 'filter', 'handshake', 'heart', 'home', 'image',
		'info-circle', 'leaf', 'list', 'mail', 'map-pin', 'message', 'phone', 'plus', 'search', 'share', 'shield',
		'sparkle', 'star', 'target', 'upload', 'user', 'users',
	);
	return $names;
}

/** Meta sanitizer: '', 'none' or a known icon name. */
function brightpath_icon_meta_sanitize( $value ) {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
	if ( '' === $value || 'none' === $value ) {
		return $value;
	}
	return in_array( $value, brightpath_icon_meta_names(), true ) ? $value : '';
}

/**
 * The icon a post's card shows: an icon name, 'none' or ''.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function brightpath_icon_meta_resolve( $post_id ) {
	$type = get_post_type( $post_id );
	if ( ! $type || ! in_array( $type, brightpath_icon_meta_types(), true ) ) {
		return '';
	}
	$value = brightpath_icon_meta_sanitize( get_post_meta( $post_id, 'bp_icon', true ) );
	if ( '' !== $value ) {
		return $value;
	}
	$defaults = brightpath_icon_meta_defaults();
	return isset( $defaults[ $type ] ) ? $defaults[ $type ] : '';
}

/** Register the meta on the card post types. */
function brightpath_icon_meta_register() {
	foreach ( brightpath_icon_meta_types() as $type ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}
		register_post_meta(
			$type,
			'bp_icon',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'description'       => 'Card icon: an icon name from the Bright Path icon library, "none", or empty for the default.',
				'sanitize_callback' => 'brightpath_icon_meta_sanitize',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'brightpath_icon_meta_register', 11 );

/**
 * Let core/paragraph read the record a card is rendering, both on the front
 * (WP_Block context) and in the editor (usesContext), so the icon tile can
 * follow the post it sits in.
 */
function brightpath_icon_meta_paragraph_context( $args, $name ) {
	if ( 'core/paragraph' === $name ) {
		$args['uses_context'] = array_values( array_unique( array_merge( (array) ( $args['uses_context'] ?? array() ), array( 'postId', 'postType' ) ) ) );
	}
	return $args;
}
add_filter( 'register_block_type_args', 'brightpath_icon_meta_paragraph_context', 10, 2 );

/**
 * Front: put the post's icon class on the `.icon-tile` of every card <li>.
 *
 * @param string $content Rendered core/post-template.
 * @return string
 */
function brightpath_icon_meta_render_cards( $content ) {
	if ( ! is_string( $content ) || false === strpos( $content, 'icon-tile' ) ) {
		return $content;
	}
	$tags    = new WP_HTML_Tag_Processor( $content );
	$post_id = 0;
	while ( $tags->next_tag() ) {
		$tag = $tags->get_tag();
		if ( 'LI' === $tag ) {
			$class = $tags->get_attribute( 'class' );
			if ( is_string( $class ) && preg_match( '/(?:^|\s)wp-block-post(?:\s|$)/', $class ) ) {
				$post_id = preg_match( '/(?:^|\s)post-(\d+)(?:\s|$)/', $class, $m ) ? (int) $m[1] : 0;
			}
			continue;
		}
		if ( 'P' === $tag && $post_id && $tags->has_class( 'icon-tile' ) ) {
			$name = brightpath_icon_meta_resolve( $post_id );
			if ( 'none' === $name ) {
				$tags->add_class( 'bp-icon-none' );
			} elseif ( '' !== $name ) {
				$tags->add_class( 'bp-icon--' . $name );
			}
			$post_id = 0; // one tile per card
		}
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-template', 'brightpath_icon_meta_render_cards', 20 );

/** Editor: the "Card icon" panel and the canvas painter. */
function brightpath_icon_meta_editor_assets() {
	$js = 'assets/js/icon-meta-editor.js';
	if ( ! file_exists( get_theme_file_path( $js ) ) ) {
		return;
	}
	$deps = array( 'wp-data', 'wp-core-data', 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-i18n', 'wp-hooks', 'wp-block-editor' );
	if ( wp_script_is( 'brightpath-icons-editor', 'registered' ) ) {
		$deps[] = 'brightpath-icons-editor'; // window.brightpathIconsUI.IconGrid
	}
	wp_enqueue_script( 'brightpath-icon-meta-editor', get_theme_file_uri( $js ), $deps, (string) filemtime( get_theme_file_path( $js ) ), true );
	wp_add_inline_script(
		'brightpath-icon-meta-editor',
		'window.brightpathIconMeta=' . wp_json_encode(
			array(
				'types'    => array_values( brightpath_icon_meta_types() ),
				'defaults' => (object) brightpath_icon_meta_defaults(),
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'brightpath_icon_meta_editor_assets', 30 );
