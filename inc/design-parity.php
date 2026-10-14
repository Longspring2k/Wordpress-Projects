<?php
/**
 * Design parity: small render-time adjustments that bring the block output in
 * line with the static design without touching post_content.
 *
 *  - accordion headings: the "+" glyph leaves the heading's text (CSS draws it)
 *  - query-loop cards: service short title / one-liner + "Learn more",
 *    job cards "location · type · pay" + "View role", SIL card specs
 *  - resource singles: "On this page" table of contents built from the H2s
 *  - search: results ordered by post type, a per-result type label, a count
 *  - FAQ search / SIL filter toggle script
 *
 * @package brightpath
 */

/* -------------------------------------------------------------------------
 * 1. Card fields (meta the content agents fill; every one has a fallback)
 * ---------------------------------------------------------------------- */

/**
 * Register the card meta keys.
 */
function brightpath_parity_register_meta() {
	$keys = array(
		'service'      => array(
			'bp_card_title' => 'Short card title, e.g. "Daily Living". Falls back to the post title.',
			'bp_card_text'  => 'One-line card description. Falls back to the excerpt.',
		),
		'sil_property' => array(
			'bp_vacancy_label' => 'Card badge, e.g. "1 vacancy", "Filling soon", "No vacancy". Falls back to the availability term.',
			'bp_bedrooms'      => 'Number of bedrooms, e.g. "3".',
			'bp_access_label'  => 'Access spec on the card, e.g. "Wheelchair accessible". Falls back to the access term.',
		),
	);

	foreach ( $keys as $type => $metas ) {
		foreach ( $metas as $key => $desc ) {
			register_post_meta(
				$type,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'description'       => $desc,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
}
add_action( 'init', 'brightpath_parity_register_meta', 9 );

/**
 * First term name of a taxonomy for a post, or ''.
 *
 * @param int    $post_id Post ID.
 * @param string $tax     Taxonomy.
 * @return string
 */
function brightpath_parity_term( $post_id, $tax ) {
	$terms = get_the_terms( $post_id, $tax );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
}

/**
 * SIL card badge label.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function brightpath_parity_vacancy_label( $post_id ) {
	$label = get_post_meta( $post_id, 'bp_vacancy_label', true );
	if ( $label ) {
		return $label;
	}
	$terms = get_the_terms( $post_id, 'sil_status' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$map = array(
		'available' => 'Vacancy now',
		'limited'   => 'Filling soon',
		'full'      => 'No vacancy',
	);
	return $map[ $terms[0]->slug ] ?? $terms[0]->name;
}

/**
 * SIL card spec line: "3 bedrooms · Wheelchair accessible".
 *
 * @param int $post_id Post ID.
 * @return string
 */
function brightpath_parity_property_specs( $post_id ) {
	$parts = array();
	$beds  = get_post_meta( $post_id, 'bp_bedrooms', true );
	if ( '' !== (string) $beds ) {
		$parts[] = $beds . ( 1 === (int) $beds ? ' bedroom' : ' bedrooms' );
	}
	$access = get_post_meta( $post_id, 'bp_access_label', true );
	if ( ! $access ) {
		$access = brightpath_parity_term( $post_id, 'sil_access' );
	}
	if ( $access ) {
		$parts[] = $access;
	}
	return implode( ' · ', $parts );
}

/**
 * Job meta line: "Parramatta · Casual · $34.85 – $41.76 per hour + super".
 *
 * @param int $post_id Post ID.
 * @return string
 */
function brightpath_parity_job_meta( $post_id ) {
	$parts = array();
	foreach ( array( 'job_location', 'job_type', 'job_pay' ) as $tax ) {
		$name = brightpath_parity_term( $post_id, $tax );
		if ( $name && 'Pay band to be confirmed' !== $name ) {
			$parts[] = $name;
		}
	}
	return implode( ' · ', $parts );
}

/**
 * The binding source for the parity keys.
 */
function brightpath_parity_register_bindings() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}
	register_block_bindings_source(
		'brightpath/parity',
		array(
			'label'              => 'Bright Path design field',
			'get_value_callback' => static function ( $source_args, $block_instance ) {
				$post_id = isset( $block_instance->context['postId'] ) ? (int) $block_instance->context['postId'] : get_the_ID();
				$key     = $source_args['key'] ?? '';
				switch ( $key ) {
					case 'vacancy_label':
						return brightpath_parity_vacancy_label( $post_id );
					case 'property_specs':
						return brightpath_parity_property_specs( $post_id );
					case 'job_meta':
						return brightpath_parity_job_meta( $post_id );
					case 'location_services_h':
						return 'Services available in ' . get_the_title( $post_id );
					case 'location_why_h':
						return 'Why participants in ' . get_the_title( $post_id ) . ' choose us.';
					case 'location_team_btn':
						return 'Talk to the ' . get_the_title( $post_id ) . ' team';
					case 'location_map_label':
						return 'Map — ' . get_the_title( $post_id ) . ' Service Area';
					case 'post_type_label':
						$obj = get_post_type_object( get_post_type( $post_id ) );
						return $obj ? $obj->labels->name : '';
					case 'search_count':
						global $wp_query;
						if ( '' === trim( get_search_query( false ) ) ) {
							return 'Type a word or two above to search services, homes, resources and more.';
						}
						$n = (int) $wp_query->found_posts;
						return sprintf( "%d %s for \u{201C}%s\u{201D}", $n, 1 === $n ? 'result' : 'results', get_search_query( false ) );
				}
				return '';
			},
			'uses_context'       => array( 'postId', 'postType' ),
		)
	);
}
add_action( 'init', 'brightpath_parity_register_bindings', 8 );

/**
 * Is this block rendering inside a query loop?
 *
 * @param WP_Block|null $instance Block.
 * @return bool
 */
function brightpath_parity_in_loop( $instance ) {
	// core does not pass queryId down through post-template, so "in a loop"
	// means: the block renders a record other than the one this URL is for.
	return $instance instanceof WP_Block && ! empty( $instance->context['postId'] )
		&& ( ! is_singular() || (int) $instance->context['postId'] !== get_queried_object_id() );
}

/**
 * Card titles: the short design title (bp_card_title) for service cards on
 * the front page only; archives and other listings keep the full post title.
 */
function brightpath_parity_post_title( $content, $parsed, $instance ) {
	if ( ! is_front_page() || ! brightpath_parity_in_loop( $instance ) || 'service' !== get_post_type( $instance->context['postId'] ) ) {
		return $content;
	}
	$short = get_post_meta( $instance->context['postId'], 'bp_card_title', true );
	if ( ! $short ) {
		return $content;
	}
	$full = get_the_title( $instance->context['postId'] );
	return str_replace( '>' . esc_html( $full ) . '<', '>' . esc_html( $short ) . '<', str_replace( '>' . $full . '<', '>' . esc_html( $short ) . '<', $content ) );
}
add_filter( 'render_block_core/post-title', 'brightpath_parity_post_title', 10, 3 );

/**
 * Card text: service one-liner + "Learn more"; job meta + "View role".
 */
function brightpath_parity_post_excerpt( $content, $parsed, $instance ) {
	if ( ! brightpath_parity_in_loop( $instance ) || is_search() ) {
		return $content;
	}
	$id   = (int) $instance->context['postId'];
	$type = get_post_type( $id );
	$url  = esc_url( get_permalink( $id ) );

	if ( 'service' === $type ) {
		$text = get_post_meta( $id, 'bp_card_text', true );
		$html = $text
			? '<div class="wp-block-post-excerpt card__text has-small-font-size"><p class="wp-block-post-excerpt__excerpt">' . esc_html( $text ) . '</p></div>'
			: $content;
		return $html . '<p class="card__foot"><a class="card__arrow bp-link-arrow" href="' . $url . '" tabindex="-1" aria-hidden="true">Learn more</a></p>';
	}

	if ( 'job' === $type ) {
		$meta = brightpath_parity_job_meta( $id );
		return '<p class="job-meta card__meta has-small-font-size">' . esc_html( $meta ) . '</p>'
			. '<p class="card__cta"><a class="bp-btn-sm is-style-outline" href="' . $url . '" tabindex="-1" aria-hidden="true">View role</a></p>';
	}

	return $content;
}
add_filter( 'render_block_core/post-excerpt', 'brightpath_parity_post_excerpt', 10, 3 );

/**
 * SIL single: the "Property overview" definition list from the record's fields.
 */
function brightpath_parity_property_overview( $content ) {
	if ( false === strpos( $content, 'data-bp-overview' ) || ! is_singular( 'sil_property' ) ) {
		return $content;
	}
	$id    = get_queried_object_id();
	$beds  = get_post_meta( $id, 'bp_bedrooms', true );
	$rows  = array(
		'Property type' => brightpath_parity_term( $id, 'sil_type' ),
		'Bedrooms'      => '' !== (string) $beds ? $beds . ( 1 === (int) $beds ? ' bedroom' : ' bedrooms' ) : '',
		'Vacancies'     => brightpath_parity_vacancy_label( $id ),
		'Accessibility' => get_post_meta( $id, 'bp_access_label', true ) ?: brightpath_parity_term( $id, 'sil_access' ),
		'Location'      => brightpath_parity_term( $id, 'sil_region' ),
	);
	$html = '';
	foreach ( array_filter( $rows ) as $k => $v ) {
		$html .= '<div class="spec-list__row"><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	return str_replace( '<dl class="spec-list" data-bp-overview></dl>', '<dl class="spec-list" data-bp-overview>' . $html . '</dl>', $content );
}
add_filter( 'render_block_core/html', 'brightpath_parity_property_overview' );

/* -------------------------------------------------------------------------
 * 2. Accordion: the "+" is decoration, not heading text
 * ---------------------------------------------------------------------- */

/**
 * Empty the icon span; CSS paints the glyph with ::before.
 */
function brightpath_parity_accordion_icon( $content ) {
	return preg_replace(
		'#(<span class="wp-block-accordion-heading__toggle-icon"[^>]*)>\s*\+\s*</span>#',
		'$1 data-icon="plus"></span>',
		$content
	);
}
add_filter( 'render_block_core/accordion-heading', 'brightpath_parity_accordion_icon' );

/* -------------------------------------------------------------------------
 * 3. Resource single: "On this page"
 * ---------------------------------------------------------------------- */

/**
 * Add ids to H2s and prepend the TOC.
 */
function brightpath_parity_toc( $content, $parsed, $instance ) {
	if ( ! is_singular( 'resource' ) || ! $instance instanceof WP_Block || empty( $instance->context['postId'] ) || (int) $instance->context['postId'] !== get_queried_object_id() ) {
		return $content;
	}
	$items = array();
	$used  = array();
	$content = preg_replace_callback(
		'#<h2(\s[^>]*)?>(.*?)</h2>#s',
		static function ( $m ) use ( &$items, &$used ) {
			$attrs = $m[1] ?? '';
			$text  = trim( wp_strip_all_tags( $m[2] ) );
			if ( preg_match( '#\sid="([^"]+)"#', $attrs, $idm ) ) {
				$id = $idm[1];
			} else {
				$id = sanitize_title( $text );
				$base = $id;
				$i = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $i++;
				}
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			$items[]     = array( $id, $text );
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$content
	);
	if ( count( $items ) < 2 ) {
		return $content;
	}
	$nav = '<nav class="toc bp-toc" aria-labelledby="bp-toc-title"><p class="toc__title" id="bp-toc-title">On this page</p><ul class="toc__list">';
	foreach ( $items as $it ) {
		$nav .= '<li><a href="#' . esc_attr( $it[0] ) . '">' . esc_html( $it[1] ) . '</a></li>';
	}
	$nav .= '</ul></nav>';
	return $nav . $content;
}
add_filter( 'render_block_core/post-content', 'brightpath_parity_toc', 10, 3 );

/**
 * Related articles: exclude the current resource from the related query.
 */
function brightpath_parity_related_query( $query, $block ) {
	$ns = $block->context['query']['namespace'] ?? '';
	if ( in_array( $block->context['query']['bpRole'] ?? $ns, array( 'related', 'brightpath/related' ), true ) && is_singular() ) {
		$query['post__not_in'] = array( get_queried_object_id() );
	}
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'brightpath_parity_related_query', 10, 2 );

/* -------------------------------------------------------------------------
 * 4. Search: group by type
 * ---------------------------------------------------------------------- */

/**
 * Order search results by post type so each type reads as a group.
 */
function brightpath_parity_search_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	// Empty query (/?s=): WP returns every post; show the no-results prompt instead.
	if ( '' === trim( (string) $query->get( 's' ) ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}
	$query->set( 'orderby', array( 'type' => 'ASC', 'relevance' => 'DESC' ) );
	$query->set( 'posts_per_page', 20 );
}
add_action( 'pre_get_posts', 'brightpath_parity_search_order' );

/* -------------------------------------------------------------------------
 * 5. Script: FAQ search + SIL filter toggle
 * ---------------------------------------------------------------------- */

/**
 * Enqueue parity.js.
 */
function brightpath_parity_enqueue() {
	$rel  = 'assets/js/parity.js';
	$path = get_theme_file_path( $rel );
	if ( ! file_exists( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'brightpath-parity',
		get_theme_file_uri( $rel ),
		array(),
		(string) filemtime( $path ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
	$css = 'assets/css/parity.css';
	if ( file_exists( get_theme_file_path( $css ) ) ) {
		wp_enqueue_style( 'brightpath-parity', get_theme_file_uri( $css ), array( 'brightpath-forms' ), (string) filemtime( get_theme_file_path( $css ) ) );
	}
}
add_action( 'wp_enqueue_scripts', 'brightpath_parity_enqueue', 20 );

/* 6. Home hero call card: now real block content in page 107 (hero__card paragraph). */

/* -------------------------------------------------------------------------
 * Editorial parity styles (agent P3): careers, jobs, resources, team, FAQ,
 * privacy, search, 404.
 * ---------------------------------------------------------------------- */
function brightpath_parity_editorial_enqueue() {
	$css = 'assets/css/parity-editorial.css';
	if ( file_exists( get_theme_file_path( $css ) ) ) {
		wp_enqueue_style( 'brightpath-parity-editorial', get_theme_file_uri( $css ), array( 'brightpath-parity' ), (string) filemtime( get_theme_file_path( $css ) ) );
	}
}
add_action( 'wp_enqueue_scripts', 'brightpath_parity_editorial_enqueue', 21 );

/* -------------------------------------------------------------------------
 * Listing & property parity styles (agent P2): services, SIL, locations.
 * ---------------------------------------------------------------------- */
function brightpath_parity_content_enqueue() {
	$css = 'assets/css/parity-content.css';
	if ( file_exists( get_theme_file_path( $css ) ) ) {
		wp_enqueue_style( 'brightpath-parity-content', get_theme_file_uri( $css ), array( 'brightpath-parity' ), (string) filemtime( get_theme_file_path( $css ) ) );
	}
}
add_action( 'wp_enqueue_scripts', 'brightpath_parity_content_enqueue', 22 );

