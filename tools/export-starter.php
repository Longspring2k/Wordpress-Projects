<?php
/**
 * Dumps this site's published content into the theme's starter/ folder, which
 * inc/starter-content.php imports on a fresh install.
 *
 * Command line only:  php export-starter.php /path/to/wordpress site-host
 *
 * @package brightpath
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$wp_root = rtrim( $argv[1] ?? '/var/www/html', '/' );
$_SERVER['HTTP_HOST']       = $argv[2] ?? 'localhost';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (export-starter)';
require $wp_root . '/wp-load.php';

$dir = get_theme_file_path( 'starter' );
wp_mkdir_p( $dir . '/media' );

$home    = untrailingslashit( home_url() );
$uploads = wp_get_upload_dir();
$out     = array( 'version' => 1, 'exported' => gmdate( 'c' ), 'options' => array(), 'terms' => array(), 'media' => array(), 'posts' => array() );

// Media first, so content can point at it by placeholder.
$url_map = array();
foreach ( get_posts( array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'inherit' ) ) as $a ) {
	$file = get_attached_file( $a->ID );
	if ( ! $file || ! file_exists( $file ) ) { continue; }
	// Only media that published content actually uses travels with the theme.
	global $wpdb;
	$stem_like = '%' . $wpdb->esc_like( pathinfo( $file, PATHINFO_FILENAME ) ) . '%';
	$used      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type NOT IN ('revision','attachment') AND post_content LIKE %s", $stem_like ) )
		|| $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d", $a->ID ) );
	if ( ! $used ) { continue; }
	copy( $file, $dir . '/media/' . basename( $file ) );
	$out['media'][] = array(
		'id'    => $a->ID,
		'file'  => basename( $file ),
		'title' => $a->post_title,
		'alt'   => get_post_meta( $a->ID, '_wp_attachment_image_alt', true ),
	);
	// The original URL and every generated size point at the same placeholder.
	$base = $uploads['baseurl'] . '/' . dirname( get_post_meta( $a->ID, '_wp_attached_file', true ) ) . '/';
	$stem = pathinfo( $file, PATHINFO_FILENAME );
	$ext  = pathinfo( $file, PATHINFO_EXTENSION );
	$url_map[] = array( '#' . preg_quote( $base . $stem, '#' ) . '(-\d+x\d+)?(-scaled)?\.' . $ext . '#', '{{media_url:' . $a->ID . '}}' );
}

$fix = function ( $s ) use ( $home, $url_map ) {
	foreach ( $url_map as $m ) { $s = preg_replace( $m[0], $m[1], $s ); }
	return str_replace( array( $home, str_replace( '/', '\\/', $home ) ), array( '{{home_url}}', '{{home_url_json}}' ), $s );
};

foreach ( get_taxonomies( array( '_builtin' => false ), 'names' ) as $tax ) {
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'term_id' ) );
	foreach ( $terms as $t ) {
		$out['terms'][] = array(
			'id' => $t->term_id, 'taxonomy' => $tax, 'name' => $t->name, 'slug' => $t->slug,
			'description' => $t->description, 'parent' => $t->parent,
			'meta' => array_map( fn( $v ) => maybe_unserialize( $v[0] ), get_term_meta( $t->term_id ) ),
		);
	}
}

$skip_meta = '/^(_edit_|_wp_old_|_wp_trash_|_wp_desired_|_yoast_wpseo_(content_score|estimated|.*_score)|_wp_design_core_)/';
$types     = array_merge( array( 'wp_block', 'wp_navigation', 'page' ), array_values( array_diff( get_post_types( array( '_builtin' => false ) ), array( 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face' ) ) ) );
foreach ( $types as $type ) {
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) {
		$meta = array();
		foreach ( get_post_meta( $p->ID ) as $k => $v ) {
			if ( preg_match( $skip_meta, $k ) ) { continue; }
			$meta[ $k ] = maybe_unserialize( $v[0] );
		}
		$terms = array();
		foreach ( get_object_taxonomies( $type ) as $tax ) {
			$ids = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'ids' ) );
			if ( $ids && ! is_wp_error( $ids ) ) { $terms[ $tax ] = $ids; }
		}
		$out['posts'][] = array(
			'id' => $p->ID, 'type' => $type, 'slug' => $p->post_name, 'title' => $p->post_title,
			'content' => $fix( $p->post_content ), 'excerpt' => $fix( $p->post_excerpt ),
			'parent' => $p->post_parent, 'menu_order' => $p->menu_order, 'date' => $p->post_date, 'modified' => $p->post_modified,
			'meta' => $meta, 'terms' => $terms,
		);
	}
}

$front = get_post( (int) get_option( 'page_on_front' ) );
$out['options'] = array(
	'blogname'            => get_option( 'blogname' ),
	'blogdescription'     => get_option( 'blogdescription' ),
	'permalink_structure' => get_option( 'permalink_structure' ),
	'show_on_front'       => get_option( 'show_on_front' ),
	'page_on_front_slug'  => $front ? $front->post_name : '',
);

file_put_contents( $dir . '/content.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
printf( "terms %d, posts %d, media %d, home refs left %d\n", count( $out['terms'] ), count( $out['posts'] ), count( $out['media'] ), substr_count( file_get_contents( $dir . '/content.json' ), 'buildkitly' ) );
