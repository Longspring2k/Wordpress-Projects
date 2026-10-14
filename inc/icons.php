<?php
/**
 * Bright Path — icon library, REST registry, custom SVG icons, admin screen.
 *
 * OWNER: icon lane I1. Loaded by functions.php (shard slot "icons").
 *
 * - Built-in icons: the 39 `--bp-icon-<name>` data-URI masks that already live
 *   in assets/css/*.css are PARSED (never retyped) so the picker renders the very
 *   same SVGs the front paints. The CSS itself is untouched.
 * - Custom icons: option `brightpath_custom_icons` (slug => label + sanitized svg).
 *   They are printed as extra CSS vars + `.bp-icon--custom-<slug>` rules on the
 *   front, and in the editor canvas iframe (enqueue_block_assets). Nothing is
 *   printed when there are no custom icons, so the front is byte-identical.
 * - REST /brightpath/v1/icons : GET (edit_posts), POST + DELETE (manage_options).
 * - Editor: assets/js/icons-editor.js (block sidebar + toolbar picker).
 * - Admin:  Appearance → Icons (paste / upload SVG / pick from Media Library).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BRIGHTPATH_ICONS_OPTION = 'brightpath_custom_icons';

/* ------------------------------------------------------------------------
 * Registry
 * ---------------------------------------------------------------------- */

/**
 * Built-in icons parsed from the theme CSS. Cached per css mtime set.
 *
 * @return array<string,array{name:string,label:string,uri:string}>
 */
function brightpath_icons_builtin() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$files = glob( get_theme_file_path( 'assets/css/*.css' ) );
	$files = is_array( $files ) ? $files : array();
	sort( $files );
	$sig = '';
	foreach ( $files as $f ) {
		$sig .= basename( $f ) . filemtime( $f ) . ';';
	}
	$key    = 'bp_icons_builtin_' . md5( $sig );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		$memo = $cached;
		return $memo;
	}
	$icons = array();
	foreach ( $files as $f ) {
		$css = (string) file_get_contents( $f );
		if ( ! preg_match_all( '/--bp-icon-([a-z0-9-]+)\s*:\s*url\(\s*"(data:image\/svg\+xml,[^"]+)"\s*\)/', $css, $m, PREG_SET_ORDER ) ) {
			continue;
		}
		foreach ( $m as $hit ) {
			$name           = $hit[1];
			$icons[ $name ] = array(
				'name'   => $name,
				'label'  => ucwords( str_replace( '-', ' ', $name ) ),
				'uri'    => $hit[2],
				'custom' => false,
			);
		}
	}
	ksort( $icons );
	set_transient( $key, $icons, DAY_IN_SECONDS );
	$memo = $icons;
	return $memo;
}

/** @return array<string,array> slug => {label, svg} */
function brightpath_icons_custom_raw() {
	$opt = get_option( BRIGHTPATH_ICONS_OPTION, array() );
	return is_array( $opt ) ? $opt : array();
}

/** @return array<string,array> */
function brightpath_icons_custom() {
	$out = array();
	foreach ( brightpath_icons_custom_raw() as $slug => $row ) {
		if ( ! is_array( $row ) || empty( $row['svg'] ) || ! preg_match( '/^custom-[a-z0-9-]+$/', (string) $slug ) ) {
			continue;
		}
		$out[ $slug ] = array(
			'name'   => $slug,
			'label'  => (string) ( $row['label'] ?? $slug ),
			'uri'    => 'data:image/svg+xml,' . rawurlencode( $row['svg'] ),
			'custom' => true,
		);
	}
	return $out;
}

/** Full list as the picker / REST consumers see it. */
function brightpath_icons_all() {
	$list = array();
	foreach ( array_merge( brightpath_icons_builtin(), brightpath_icons_custom() ) as $ic ) {
		$list[] = array(
			'name'   => $ic['name'],
			'label'  => $ic['label'],
			'class'  => 'bp-icon--' . $ic['name'],
			'var'    => '--bp-icon-' . $ic['name'],
			'uri'    => $ic['uri'],
			'mask'   => 'url("' . $ic['uri'] . '")',
			'custom' => (bool) $ic['custom'],
		);
	}
	return $list;
}

/* ------------------------------------------------------------------------
 * SVG sanitizer (whitelist rebuild)
 * ---------------------------------------------------------------------- */

/**
 * @return string|WP_Error Clean single-root <svg> markup.
 */
function brightpath_icons_sanitize_svg( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return new WP_Error( 'bp_icon_empty', 'Empty SVG.' );
	}
	if ( strlen( $raw ) > 30000 ) {
		return new WP_Error( 'bp_icon_big', 'SVG is larger than 30 KB.' );
	}
	if ( preg_match( '/<!\s*(DOCTYPE|ENTITY)/i', $raw ) ) {
		return new WP_Error( 'bp_icon_doctype', 'DOCTYPE / ENTITY not allowed.' );
	}
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );

	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$ok   = $doc->loadXML( $raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok || ! $doc->documentElement || 'svg' !== strtolower( $doc->documentElement->localName ) ) {
		return new WP_Error( 'bp_icon_parse', 'This is not a valid SVG file.' );
	}

	$allowed_el = array( 'svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse' );
	$allowed_at = array(
		'viewbox' => 'viewBox', 'd' => 'd', 'cx' => 'cx', 'cy' => 'cy', 'r' => 'r', 'rx' => 'rx', 'ry' => 'ry',
		'x' => 'x', 'y' => 'y', 'x1' => 'x1', 'y1' => 'y1', 'x2' => 'x2', 'y2' => 'y2',
		'width' => 'width', 'height' => 'height', 'points' => 'points', 'transform' => 'transform',
		'fill' => 'fill', 'stroke' => 'stroke', 'stroke-width' => 'stroke-width', 'stroke-linecap' => 'stroke-linecap',
		'stroke-linejoin' => 'stroke-linejoin', 'stroke-miterlimit' => 'stroke-miterlimit',
		'stroke-dasharray' => 'stroke-dasharray', 'stroke-dashoffset' => 'stroke-dashoffset',
		'fill-rule' => 'fill-rule', 'clip-rule' => 'clip-rule', 'opacity' => 'opacity',
		'fill-opacity' => 'fill-opacity', 'stroke-opacity' => 'stroke-opacity',
	);

	$out  = new DOMDocument( '1.0', 'UTF-8' );
	$svgn = 'http://www.w3.org/2000/svg';
	$root = $out->createElementNS( $svgn, 'svg' );
	$out->appendChild( $root );
	$count = 0;

	$src_root = $doc->documentElement;
	// viewBox.
	$vb = trim( (string) $src_root->getAttribute( 'viewBox' ) );
	if ( ! preg_match( '/^-?[0-9.]+[ ,]+-?[0-9.]+[ ,]+[0-9.]+[ ,]+[0-9.]+$/', $vb ) ) {
		$w  = (float) $src_root->getAttribute( 'width' );
		$h  = (float) $src_root->getAttribute( 'height' );
		$vb = ( $w > 0 && $h > 0 ) ? '0 0 ' . $w . ' ' . $h : '0 0 24 24';
	}
	$root->setAttribute( 'viewBox', $vb );

	$copy = function ( DOMElement $from, DOMElement $to ) use ( &$copy, $out, $svgn, $allowed_el, $allowed_at, &$count ) {
		foreach ( iterator_to_array( $from->childNodes ) as $child ) {
			if ( ! ( $child instanceof DOMElement ) ) {
				continue;
			}
			$ns = (string) $child->namespaceURI;
			$ln = strtolower( $child->localName );
			if ( ( '' !== $ns && $svgn !== $ns ) || ! in_array( $ln, $allowed_el, true ) || 'svg' === $ln ) {
				continue;
			}
			$el = $out->createElementNS( $svgn, $ln );
			foreach ( iterator_to_array( $child->attributes ) as $attr ) {
				if ( '' !== (string) $attr->namespaceURI ) {
					continue;
				}
				$an = strtolower( $attr->name );
				if ( ! isset( $allowed_at[ $an ] ) || 'viewbox' === $an ) {
					continue;
				}
				$val = trim( (string) $attr->value );
				if ( ! preg_match( '/^[A-Za-z0-9 .,#%()+\-_]*$/', $val ) || preg_match( '/url|javascript|expression|data:|script/i', $val ) ) {
					continue;
				}
				$el->setAttribute( $allowed_at[ $an ], $val );
			}
			$to->appendChild( $el );
			if ( 'g' !== $ln ) {
				++$count;
			}
			$copy( $child, $el );
		}
	};
	$copy( $src_root, $root );

	if ( $count < 1 ) {
		return new WP_Error( 'bp_icon_shapes', 'No drawable shapes found (path, circle, rect, line, polyline, polygon, ellipse).' );
	}
	$xml = $out->saveXML( $root );
	if ( ! is_string( $xml ) || '' === $xml ) {
		return new WP_Error( 'bp_icon_save', 'Could not serialise the SVG.' );
	}
	return $xml;
}

/** Add a custom icon. @return array|WP_Error the icon row. */
function brightpath_icons_add( $label, $svg ) {
	$clean = brightpath_icons_sanitize_svg( $svg );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}
	$label = trim( wp_strip_all_tags( (string) $label ) );
	if ( '' === $label ) {
		$label = 'Custom icon';
	}
	$label = mb_substr( $label, 0, 40 );
	$opt   = brightpath_icons_custom_raw();
	if ( count( $opt ) >= 200 ) {
		return new WP_Error( 'bp_icon_limit', 'Too many custom icons (limit 200).' );
	}
	$base = sanitize_title( $label );
	$base = '' === $base ? 'icon' : $base;
	$slug = 'custom-' . $base;
	$i    = 2;
	while ( isset( $opt[ $slug ] ) ) {
		$slug = 'custom-' . $base . '-' . $i++;
	}
	$opt[ $slug ] = array( 'label' => $label, 'svg' => $clean, 'added' => time() );
	update_option( BRIGHTPATH_ICONS_OPTION, $opt, false );
	$all = brightpath_icons_custom();
	return $all[ $slug ];
}

function brightpath_icons_svg_from_attachment( $id ) {
	$file = get_attached_file( (int) $id );
	if ( ! $file || ! file_exists( $file ) || 'svg' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'bp_icon_att', 'That Media Library item is not an .svg file.' );
	}
	return (string) file_get_contents( $file );
}

/* ------------------------------------------------------------------------
 * REST
 * ---------------------------------------------------------------------- */

function brightpath_icons_rest_register() {
	register_rest_route( 'brightpath/v1', '/icons', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => function () {
				return rest_ensure_response( array(
					'icons'     => brightpath_icons_all(),
					'canManage' => current_user_can( 'manage_options' ),
					'adminUrl'  => admin_url( 'themes.php?page=brightpath-icons' ),
				) );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'callback'            => function ( WP_REST_Request $req ) {
				$svg = (string) $req->get_param( 'svg' );
				if ( '' === trim( $svg ) && $req->get_param( 'attachment_id' ) ) {
					$svg = brightpath_icons_svg_from_attachment( $req->get_param( 'attachment_id' ) );
					if ( is_wp_error( $svg ) ) {
						return $svg;
					}
				}
				$icon = brightpath_icons_add( (string) $req->get_param( 'label' ), $svg );
				if ( is_wp_error( $icon ) ) {
					return new WP_Error( $icon->get_error_code(), $icon->get_error_message(), array( 'status' => 400 ) );
				}
				return rest_ensure_response( array( 'icon' => $icon ) );
			},
		),
	) );
	register_rest_route( 'brightpath/v1', '/icons/(?P<slug>custom-[a-z0-9-]+)', array(
		'methods'             => 'DELETE',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => function ( WP_REST_Request $req ) {
			$opt  = brightpath_icons_custom_raw();
			$slug = (string) $req['slug'];
			if ( ! isset( $opt[ $slug ] ) ) {
				return new WP_Error( 'bp_icon_404', 'Unknown custom icon.', array( 'status' => 404 ) );
			}
			unset( $opt[ $slug ] );
			update_option( BRIGHTPATH_ICONS_OPTION, $opt, false );
			return rest_ensure_response( array( 'deleted' => $slug ) );
		},
	) );
}
add_action( 'rest_api_init', 'brightpath_icons_rest_register' );

/* ------------------------------------------------------------------------
 * Custom icon CSS (front + editor canvas iframe). Empty when none exist.
 * ---------------------------------------------------------------------- */

function brightpath_icons_custom_css() {
	$css = '';
	foreach ( brightpath_icons_custom() as $slug => $ic ) {
		$css .= ':root{--bp-icon-' . $slug . ':url("' . $ic['uri'] . '")}';
		$css .= '.bp-icon--' . $slug . '{--bp-icon:var(--bp-icon-' . $slug . ')}';
	}
	return $css;
}

/** Fires on the front AND inside the editor canvas iframe (WP >= 6.3). */
function brightpath_icons_enqueue_block_assets() {
	$css = brightpath_icons_custom_css();
	if ( '' === $css ) {
		return;
	}
	wp_register_style( 'brightpath-custom-icons', false, array(), null );
	wp_enqueue_style( 'brightpath-custom-icons' );
	wp_add_inline_style( 'brightpath-custom-icons', $css );
}
add_action( 'enqueue_block_assets', 'brightpath_icons_enqueue_block_assets', 30 );

/* ------------------------------------------------------------------------
 * Editor picker assets (editor window only)
 * ---------------------------------------------------------------------- */

function brightpath_icons_editor_assets() {
	$js  = 'assets/js/icons-editor.js';
	$css = 'assets/css/icons.css';
	if ( file_exists( get_theme_file_path( $css ) ) ) {
		wp_enqueue_style( 'brightpath-icons-ui', get_theme_file_uri( $css ), array(), (string) filemtime( get_theme_file_path( $css ) ) );
	}
	if ( ! file_exists( get_theme_file_path( $js ) ) ) {
		return;
	}
	wp_enqueue_script(
		'brightpath-icons-editor',
		get_theme_file_uri( $js ),
		array( 'wp-data', 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-compose', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
		(string) filemtime( get_theme_file_path( $js ) ),
		true
	);
	wp_add_inline_script(
		'brightpath-icons-editor',
		'window.brightpathIcons=' . wp_json_encode( array(
			'icons'     => brightpath_icons_all(),
			'canManage' => current_user_can( 'manage_options' ),
			'adminUrl'  => admin_url( 'themes.php?page=brightpath-icons' ),
		) ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'brightpath_icons_editor_assets', 25 );

/* ------------------------------------------------------------------------
 * Admin screen: Appearance → Icons
 * ---------------------------------------------------------------------- */

function brightpath_icons_admin_menu() {
	add_theme_page( 'Icons', 'Icons', 'manage_options', 'brightpath-icons', 'brightpath_icons_admin_page' );
}
add_action( 'admin_menu', 'brightpath_icons_admin_menu' );

function brightpath_icons_admin_assets( $hook ) {
	if ( 'appearance_page_brightpath-icons' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	$css = 'assets/css/icons.css';
	$js  = 'assets/js/icons-admin.js';
	if ( file_exists( get_theme_file_path( $css ) ) ) {
		wp_enqueue_style( 'brightpath-icons-ui', get_theme_file_uri( $css ), array(), (string) filemtime( get_theme_file_path( $css ) ) );
	}
	if ( file_exists( get_theme_file_path( $js ) ) ) {
		wp_enqueue_script( 'brightpath-icons-admin', get_theme_file_uri( $js ), array( 'jquery', 'media-editor' ), (string) filemtime( get_theme_file_path( $js ) ), true );
	}
}
add_action( 'admin_enqueue_scripts', 'brightpath_icons_admin_assets' );

function brightpath_icons_admin_redirect( $type, $msg ) {
	wp_safe_redirect( add_query_arg( array( 'page' => 'brightpath-icons', 'bp_msg' => rawurlencode( $msg ), 'bp_type' => $type ), admin_url( 'themes.php' ) ) );
	exit;
}

function brightpath_icons_handle_add() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'brightpath_add_icon' );
	$svg = isset( $_POST['svg'] ) ? wp_unslash( $_POST['svg'] ) : ''; // phpcs:ignore -- sanitized by allowlist rebuild.
	if ( ! empty( $_FILES['svg_file']['tmp_name'] ) && is_uploaded_file( $_FILES['svg_file']['tmp_name'] ) ) {
		if ( 'svg' !== strtolower( pathinfo( (string) $_FILES['svg_file']['name'], PATHINFO_EXTENSION ) ) ) {
			brightpath_icons_admin_redirect( 'error', 'The uploaded file must be an .svg.' );
		}
		$svg = (string) file_get_contents( $_FILES['svg_file']['tmp_name'] );
	}
	if ( '' === trim( (string) $svg ) && ! empty( $_POST['attachment_id'] ) ) {
		$svg = brightpath_icons_svg_from_attachment( absint( $_POST['attachment_id'] ) );
		if ( is_wp_error( $svg ) ) {
			brightpath_icons_admin_redirect( 'error', $svg->get_error_message() );
		}
	}
	$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$icon  = brightpath_icons_add( $label, $svg );
	if ( is_wp_error( $icon ) ) {
		brightpath_icons_admin_redirect( 'error', $icon->get_error_message() );
	}
	brightpath_icons_admin_redirect( 'success', 'Icon "' . $icon['label'] . '" added. It now appears in the block icon picker.' );
}
add_action( 'admin_post_brightpath_add_icon', 'brightpath_icons_handle_add' );

function brightpath_icons_handle_delete() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
	check_admin_referer( 'brightpath_delete_icon_' . $slug );
	$opt = brightpath_icons_custom_raw();
	if ( isset( $opt[ $slug ] ) ) {
		unset( $opt[ $slug ] );
		update_option( BRIGHTPATH_ICONS_OPTION, $opt, false );
		brightpath_icons_admin_redirect( 'success', 'Icon deleted. Blocks that still use it will show no icon.' );
	}
	brightpath_icons_admin_redirect( 'error', 'Unknown icon.' );
}
add_action( 'admin_post_brightpath_delete_icon', 'brightpath_icons_handle_delete' );

function brightpath_icons_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg  = isset( $_GET['bp_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['bp_msg'] ) ) ) : '';
	$type = ( isset( $_GET['bp_type'] ) && 'error' === $_GET['bp_type'] ) ? 'error' : 'success';
	$post = esc_url( admin_url( 'admin-post.php' ) );
	echo '<div class="wrap bp-icons-admin"><h1>Icons</h1>';
	if ( '' !== $msg ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	echo '<p>Add your own icons here. They appear in the icon picker of every block (select a block → sidebar <strong>Icon</strong> panel, or the icon button in the block toolbar) and in the per-post <strong>Card icon</strong> picker.</p>';

	echo '<h2>Add an icon</h2>';
	echo '<form method="post" enctype="multipart/form-data" action="' . $post . '" class="bp-icons-admin__form">';
	wp_nonce_field( 'brightpath_add_icon' );
	echo '<input type="hidden" name="action" value="brightpath_add_icon" />';
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th><label for="bp-icon-label">Name</label></th><td><input type="text" id="bp-icon-label" name="label" class="regular-text" maxlength="40" placeholder="e.g. Wheelchair" /></td></tr>';
	echo '<tr><th><label for="bp-icon-file">Upload an .svg file</label></th><td><input type="file" id="bp-icon-file" name="svg_file" accept=".svg,image/svg+xml" /></td></tr>';
	echo '<tr><th>…or Media Library</th><td><button type="button" class="button" id="bp-icon-media">Choose from Media Library</button> <span id="bp-icon-media-name" class="description"></span><input type="hidden" name="attachment_id" id="bp-icon-att" value="" /><p class="description">Only .svg items. (If your Media Library does not accept .svg, use the upload or paste option.)</p></td></tr>';
	echo '<tr><th><label for="bp-icon-svg">…or paste SVG code</label></th><td><textarea id="bp-icon-svg" name="svg" rows="6" class="large-text code" placeholder="&lt;svg viewBox=&quot;0 0 24 24&quot;&gt;…&lt;/svg&gt;"></textarea><p class="description">Icons are single-colour: the shape is used as a mask and takes the text/icon colour of the block. Scripts, links, styles, images and event attributes are removed automatically; only svg, g, path, circle, rect, line, polyline, polygon and ellipse are kept.</p></td></tr>';
	echo '</table>';
	submit_button( 'Add icon' );
	echo '</form>';

	$custom = brightpath_icons_custom();
	echo '<h2>Your icons (' . count( $custom ) . ')</h2>';
	if ( ! $custom ) {
		echo '<p class="description">No custom icons yet.</p>';
	} else {
		echo '<div class="bp-icons-admin__grid">';
		foreach ( $custom as $slug => $ic ) {
			echo '<div class="bp-icons-admin__cell">';
			echo '<span class="bp-icon-swatch" style="-webkit-mask-image:url(&quot;' . esc_attr( $ic['uri'] ) . '&quot;);mask-image:url(&quot;' . esc_attr( $ic['uri'] ) . '&quot;)"></span>';
			echo '<strong>' . esc_html( $ic['label'] ) . '</strong><code>bp-icon--' . esc_html( $slug ) . '</code>';
			echo '<form method="post" action="' . $post . '" onsubmit="return confirm(\'Delete this icon?\');">';
			wp_nonce_field( 'brightpath_delete_icon_' . $slug );
			echo '<input type="hidden" name="action" value="brightpath_delete_icon" /><input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
			echo '<button class="button-link button-link-delete" type="submit">Delete</button></form>';
			echo '</div>';
		}
		echo '</div>';
	}

	$builtin = brightpath_icons_builtin();
	echo '<h2>Built-in icons (' . count( $builtin ) . ')</h2><div class="bp-icons-admin__grid">';
	foreach ( $builtin as $ic ) {
		echo '<div class="bp-icons-admin__cell"><span class="bp-icon-swatch" style="-webkit-mask-image:url(&quot;' . esc_attr( $ic['uri'] ) . '&quot;);mask-image:url(&quot;' . esc_attr( $ic['uri'] ) . '&quot;)"></span><strong>' . esc_html( $ic['label'] ) . '</strong><code>bp-icon--' . esc_html( $ic['name'] ) . '</code></div>';
	}
	echo '</div></div>';
}
