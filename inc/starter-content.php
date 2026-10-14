<?php
/**
 * Starter content: the whole Bright Path site travels inside the theme.
 *
 * starter/content.json (written by tools/export-starter.php) holds every
 * published page and post-type entry, their terms, meta, the synced CTA
 * block, the navigation and the reading/permalink settings. On a site that
 * does not have them yet, activating the theme imports them once. After
 * that they are ordinary database content: edit them in WordPress as usual.
 *
 * Safe to re-run: anything that already exists (same post type + slug, or
 * same taxonomy + slug) is left alone, so edits are never overwritten.
 * Original IDs are kept when they are free (import_id), so ID references
 * line up; when an ID is taken the new one is mapped and references to the
 * synced block / navigation / media are rewritten.
 *
 * Re-run manually: Appearance > Bright Path content.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BRIGHTPATH_STARTER_OPTION = 'brightpath_starter_import';
const BRIGHTPATH_STARTER_MAP    = 'brightpath_starter_id_map';

/**
 * Runs on activation. core fires after_switch_theme from init, after the
 * theme's post types and taxonomies are registered, so it imports straight
 * away; otherwise it queues the import for the next init.
 */
function brightpath_starter_queue() {
	if ( get_option( BRIGHTPATH_STARTER_OPTION ) ) {
		return;
	}
	update_option( BRIGHTPATH_STARTER_OPTION, 'pending', false );
	if ( did_action( 'init' ) && post_type_exists( 'service' ) ) {
		brightpath_starter_import();
	}
}
add_action( 'after_switch_theme', 'brightpath_starter_queue' );

function brightpath_starter_maybe_run() {
	if ( 'pending' === get_option( BRIGHTPATH_STARTER_OPTION ) ) {
		brightpath_starter_import();
	}
}
add_action( 'init', 'brightpath_starter_maybe_run', 99 );

/**
 * Imports starter/content.json. Returns a summary array.
 */
function brightpath_starter_import() {
	$file = get_theme_file_path( 'starter/content.json' );
	if ( ! file_exists( $file ) ) {
		return array( 'error' => 'starter/content.json missing' );
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		return array( 'error' => 'starter/content.json unreadable' );
	}

	// First import (not a manual re-run): terms the theme seeded itself with
	// default names take the bundled names and descriptions.
	$first = 'done' !== get_option( BRIGHTPATH_STARTER_OPTION );

	// Mark it done first so a fatal half-way cannot loop on every request.
	update_option( BRIGHTPATH_STARTER_OPTION, 'running', false );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	kses_remove_filters();
	wp_defer_term_counting( true );

	$map   = array( 'term' => array(), 'post' => array(), 'media' => array() );
	$stats = array( 'terms' => 0, 'posts' => 0, 'media' => 0, 'skipped' => 0 );

	// Terms: parents before children, matched by taxonomy + slug.
	$pending = isset( $data['terms'] ) ? $data['terms'] : array();
	for ( $pass = 0; $pending && $pass < 10; $pass++ ) {
		$later = array();
		foreach ( $pending as $t ) {
			if ( ! taxonomy_exists( $t['taxonomy'] ) ) {
				continue;
			}
			if ( $t['parent'] && ! isset( $map['term'][ $t['parent'] ] ) ) {
				$later[] = $t;
				continue;
			}
			$existing = get_term_by( 'slug', $t['slug'], $t['taxonomy'] );
			if ( $existing ) {
				$map['term'][ $t['id'] ] = (int) $existing->term_id;
				if ( $first && ( $existing->name !== $t['name'] || $existing->description !== $t['description'] ) ) {
					wp_update_term( $existing->term_id, $t['taxonomy'], array( 'name' => $t['name'], 'description' => $t['description'] ) );
				}
				continue;
			}
			$res = wp_insert_term( $t['name'], $t['taxonomy'], array(
				'slug'        => $t['slug'],
				'description' => $t['description'],
				'parent'      => $t['parent'] ? $map['term'][ $t['parent'] ] : 0,
			) );
			if ( is_wp_error( $res ) ) {
				continue;
			}
			$map['term'][ $t['id'] ] = (int) $res['term_id'];
			foreach ( (array) $t['meta'] as $k => $v ) {
				update_term_meta( $res['term_id'], $k, $v );
			}
			$stats['terms']++;
		}
		$pending = $later;
	}

	// Media: copied from starter/media into the uploads folder.
	require_once ABSPATH . 'wp-admin/includes/image.php';
	foreach ( isset( $data['media'] ) ? $data['media'] : array() as $m ) {
		$src = get_theme_file_path( 'starter/media/' . $m['file'] );
		if ( ! file_exists( $src ) ) {
			continue;
		}
		$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_brightpath_starter_file', 'meta_value' => $m['file'] ) );
		if ( $found ) {
			$map['media'][ $m['id'] ] = (int) $found[0];
			continue;
		}
		$upload = wp_upload_bits( $m['file'], null, (string) file_get_contents( $src ) );
		if ( ! empty( $upload['error'] ) ) {
			continue;
		}
		$type = wp_check_filetype( $upload['file'] );
		$id   = wp_insert_attachment( array(
			'import_id'      => $m['id'],
			'post_title'     => $m['title'],
			'post_mime_type' => $type['type'],
			'post_status'    => 'inherit',
		), $upload['file'] );
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $m['alt'] );
		update_post_meta( $id, '_brightpath_starter_file', $m['file'] );
		$map['media'][ $m['id'] ] = (int) $id;
		$stats['media']++;
	}

	// Posts: synced block and navigation first, then pages, then entries.
	$created = array();
	foreach ( isset( $data['posts'] ) ? $data['posts'] : array() as $p ) {
		if ( ! post_type_exists( $p['type'] ) ) {
			continue;
		}
		$existing = get_page_by_path( $p['slug'], OBJECT, $p['type'] );
		if ( $existing ) {
			$map['post'][ $p['id'] ] = (int) $existing->ID;
			$stats['skipped']++;
			continue;
		}
		$id = wp_insert_post( wp_slash( array(
			'import_id'    => $p['id'],
			'post_type'    => $p['type'],
			'post_status'  => 'publish',
			'post_name'    => $p['slug'],
			'post_title'   => $p['title'],
			'post_content' => brightpath_starter_fill( $p['content'], $map ),
			'post_excerpt' => brightpath_starter_fill( $p['excerpt'], $map ),
			'menu_order'   => (int) $p['menu_order'],
			'post_date'    => $p['date'],
		) ), true );
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		$map['post'][ $p['id'] ] = (int) $id;
		$created[]               = $p;
		$stats['posts']++;
	}

	// Second pass, now every ID is known: parents, meta, terms, references.
	foreach ( $created as $p ) {
		$id = $map['post'][ $p['id'] ];
		if ( $p['parent'] && isset( $map['post'][ $p['parent'] ] ) ) {
			wp_update_post( array( 'ID' => $id, 'post_parent' => $map['post'][ $p['parent'] ] ) );
		}
		foreach ( (array) $p['meta'] as $k => $v ) {
			if ( '_thumbnail_id' === $k ) {
				$v = isset( $map['media'][ $v ] ) ? $map['media'][ $v ] : 0;
				if ( ! $v ) {
					continue;
				}
			}
			update_post_meta( $id, $k, wp_slash( $v ) );
		}
		foreach ( (array) $p['terms'] as $tax => $ids ) {
			$new = array_values( array_filter( array_map( fn( $t ) => isset( $map['term'][ $t ] ) ? $map['term'][ $t ] : 0, $ids ) ) );
			if ( $new && taxonomy_exists( $tax ) ) {
				wp_set_object_terms( $id, $new, $tax );
			}
		}
		$content = get_post_field( 'post_content', $id, 'raw' );
		$fixed   = brightpath_starter_remap_refs( $content, $map['post'] );
		if ( $fixed !== $content ) {
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $fixed ) ) );
		}
		// "Last updated" lines show the bundled date, not the import date.
		if ( ! empty( $p['modified'] ) ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_modified' => $p['modified'], 'post_modified_gmt' => get_gmt_from_date( $p['modified'] ) ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}
	}

	// Reading + permalinks.
	$o = isset( $data['options'] ) ? $data['options'] : array();
	if ( ! empty( $o['page_on_front_slug'] ) ) {
		$front = get_page_by_path( $o['page_on_front_slug'] );
		if ( $front ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front->ID );
		}
	}
	if ( ! empty( $o['permalink_structure'] ) && ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', $o['permalink_structure'] );
	}
	foreach ( array( 'blogname', 'blogdescription' ) as $k ) {
		if ( ! empty( $o[ $k ] ) && in_array( get_option( $k ), array( '', 'My WordPress Website', 'My WordPress Blog', 'Just another WordPress site' ), true ) ) {
			update_option( $k, $o[ $k ] );
		}
	}

	wp_defer_term_counting( false );
	kses_init_filters();
	flush_rewrite_rules( false );

	// Only IDs that changed need remapping in theme templates.
	$changed = array();
	foreach ( $map['post'] as $old => $new ) {
		if ( (int) $old !== (int) $new ) {
			$changed[ (int) $old ] = (int) $new;
		}
	}
	update_option( BRIGHTPATH_STARTER_MAP, $changed );
	update_option( BRIGHTPATH_STARTER_OPTION, 'done', false );

	return $stats;
}

/** Placeholders written by the exporter -> this site's URLs. */
function brightpath_starter_fill( $s, $map ) {
	$home = untrailingslashit( home_url() );
	$s    = str_replace( array( '{{home_url}}', '{{home_url_json}}' ), array( $home, str_replace( '/', '\\/', $home ) ), (string) $s );
	return preg_replace_callback( '/\{\{media_url:(\d+)\}\}/', function ( $m ) use ( $map ) {
		$id = isset( $map['media'][ $m[1] ] ) ? $map['media'][ $m[1] ] : 0;
		return $id ? (string) wp_get_attachment_url( $id ) : '';
	}, $s );
}

/** Block references ("ref":N) whose target was imported under a new ID. */
function brightpath_starter_remap_refs( $content, $post_map ) {
	return preg_replace_callback( '/"ref":(\d+)/', function ( $m ) use ( $post_map ) {
		$old = (int) $m[1];
		return isset( $post_map[ $old ] ) ? '"ref":' . $post_map[ $old ] : $m[0];
	}, (string) $content );
}

/**
 * Theme templates and patterns ship "ref":16 for the synced CTA. If that ID
 * was taken on this site, point them at the block's real ID instead.
 */
function brightpath_starter_template_refs( $templates ) {
	$changed = get_option( BRIGHTPATH_STARTER_MAP );
	if ( ! $changed ) {
		return $templates;
	}
	foreach ( $templates as $t ) {
		if ( isset( $t->content ) && 'theme' === $t->source ) {
			$t->content = brightpath_starter_remap_refs( $t->content, $changed );
		}
	}
	return $templates;
}
add_filter( 'get_block_templates', 'brightpath_starter_template_refs' );

function brightpath_starter_template_ref( $template ) {
	if ( $template ) {
		brightpath_starter_template_refs( array( $template ) );
	}
	return $template;
}
add_filter( 'get_block_file_template', 'brightpath_starter_template_ref' );
add_filter( 'get_block_template', 'brightpath_starter_template_ref' );

function brightpath_starter_block_ref( $block ) {
	if ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
		$changed = get_option( BRIGHTPATH_STARTER_MAP );
		$old     = (int) $block['attrs']['ref'];
		if ( $changed && isset( $changed[ $old ] ) ) {
			$block['attrs']['ref'] = $changed[ $old ];
		}
	}
	return $block;
}
add_filter( 'render_block_data', 'brightpath_starter_block_ref' );

/* ---- Appearance > Bright Path content: status + re-run (adds missing only) ---- */

function brightpath_starter_admin_menu() {
	add_theme_page( 'Bright Path content', 'Bright Path content', 'manage_options', 'brightpath-starter', 'brightpath_starter_admin_page' );
}
add_action( 'admin_menu', 'brightpath_starter_admin_menu' );

function brightpath_starter_admin_page() {
	$result = null;
	if ( isset( $_POST['brightpath_starter_run'] ) && check_admin_referer( 'brightpath_starter_run' ) && current_user_can( 'manage_options' ) ) {
		$result = brightpath_starter_import();
	}
	echo '<div class="wrap"><h1>Bright Path content</h1>';
	echo '<p>Status: <strong>' . esc_html( (string) get_option( BRIGHTPATH_STARTER_OPTION, 'not imported' ) ) . '</strong></p>';
	if ( is_array( $result ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html( wp_json_encode( $result ) ) . '</p></div>';
	}
	echo '<p>Imports the pages, services, SIL homes, locations, resources, jobs, team, terms and settings bundled with the theme. Anything that already exists (same slug) is skipped, so your edits are kept.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'brightpath_starter_run' );
	echo '<p><button class="button button-primary" name="brightpath_starter_run" value="1">Import missing content</button></p></form></div>';
}
