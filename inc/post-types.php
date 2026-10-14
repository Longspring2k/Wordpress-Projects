<?php
/**
 * Bright Path — post types, taxonomies, term seeds and derived fields.
 *
 * OWNER: the post-type track. Nothing else in the theme may declare a post
 * type or a taxonomy.
 *
 * One shard of what used to be a single functions.php. The split exists so
 * that parallel work cannot collide: `.design-core/` is not under version
 * control, so two agents editing one file means one of them is lost, not
 * merged. Loaded by functions.php; never loaded directly.
 *
 * THE MODEL, AND WHY — contract sections 7, 12.1/12.2/12.10 and 16.
 *
 * Zero post meta. Every field is either a taxonomy or part of post_content,
 * and the line between them is one criterion: a field becomes a hierarchical
 * taxonomy IFF it must be read from OUTSIDE post_content — it appears on a
 * listing card, or it drives a filter. Everything else is block content.
 *
 * The reason is the editing UI, not the data model. Registered meta is
 * storable today and INVISIBLE today: core renders no field for it and this
 * theme ships no meta box, so "change the pay band" would become a developer
 * ticket. A hierarchical taxonomy ships a checkbox list for free, and
 * core/post-terms renders it on a card with no custom block.
 *
 * Two fields are DERIVED and never stored, so they cannot disagree with their
 * source: a resource author's initials (from the term name) and a resource's
 * read time (from the word count). A stored read time goes stale the first
 * time the body is edited.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bump by hand whenever a rewrite-affecting argument changes: a slug, a
 * has_archive, a rewrite array, a new type or taxonomy. brightpath_maybe_flush_rewrites()
 * compares it against the stored option and flushes exactly once.
 *
 * Without a flush every single-item URL 404s even though the type IS
 * registered — the registration looks correct in every log and on every REST
 * response, and the page is still not there.
 */
define( 'BRIGHTPATH_REWRITE_VERSION', '2026-09-18-posttypes-4-pageattributes' );

/* -------------------------------------------------------------------------
 * 1. Label builders
 *
 * Every label key is emitted explicitly. Relying on the defaults gives the
 * admin the word "Post" in nine places on five different screens.
 * ---------------------------------------------------------------------- */

/**
 * The full post-type label set.
 *
 * @param string $singular Singular name, capitalised.
 * @param string $plural   Plural name, capitalised.
 * @param string $menu     Admin menu label.
 * @return array<string,string>
 */
function brightpath_pt_labels( $singular, $plural, $menu ) {
	$lc_s = strtolower( $singular );
	$lc_p = strtolower( $plural );

	return array(
		'name'                     => $plural,
		'singular_name'            => $singular,
		'menu_name'                => $menu,
		'name_admin_bar'           => $singular,
		'add_new'                  => 'Add ' . $singular,
		'add_new_item'             => 'Add ' . $singular,
		'new_item'                 => 'New ' . $singular,
		'edit_item'                => 'Edit ' . $singular,
		'view_item'                => 'View ' . $singular,
		'view_items'               => 'View ' . $plural,
		'all_items'                => 'All ' . $plural,
		'search_items'             => 'Search ' . $lc_p,
		'parent_item_colon'        => 'Parent ' . $lc_s . ':',
		'not_found'                => 'No ' . $lc_p . ' found.',
		'not_found_in_trash'       => 'No ' . $lc_p . ' found in Trash.',
		'archives'                 => $plural, // design breadcrumbs read "Services", not "Service archives"
		'attributes'               => $singular . ' attributes',
		'insert_into_item'         => 'Insert into ' . $lc_s,
		'uploaded_to_this_item'    => 'Uploaded to this ' . $lc_s,
		'featured_image'           => 'Featured image',
		'set_featured_image'       => 'Set featured image',
		'remove_featured_image'    => 'Remove featured image',
		'use_featured_image'       => 'Use as featured image',
		'filter_items_list'        => 'Filter ' . $lc_p . ' list',
		'filter_by_date'           => 'Filter by date',
		'items_list_navigation'    => $plural . ' list navigation',
		'items_list'               => $plural . ' list',
		'item_published'           => $singular . ' published.',
		'item_published_privately' => $singular . ' published privately.',
		'item_reverted_to_draft'   => $singular . ' reverted to draft.',
		'item_scheduled'           => $singular . ' scheduled.',
		'item_updated'             => $singular . ' updated.',
		'item_link'                => $singular . ' link',
		'item_link_description'    => 'A link to a ' . $lc_s . '.',
	);
}

/**
 * The full taxonomy label set, for a hierarchical taxonomy.
 *
 * @param string $singular Singular name, capitalised.
 * @param string $plural   Plural name, capitalised.
 * @return array<string,string>
 */
function brightpath_tax_labels( $singular, $plural ) {
	$lc_s = strtolower( $singular );
	$lc_p = strtolower( $plural );

	return array(
		'name'                       => $plural,
		'singular_name'              => $singular,
		'menu_name'                  => $plural,
		'all_items'                  => 'All ' . $lc_p,
		'edit_item'                  => 'Edit ' . $lc_s,
		'view_item'                  => 'View ' . $lc_s,
		'update_item'                => 'Update ' . $lc_s,
		'add_new_item'               => 'Add ' . $lc_s,
		'new_item_name'              => 'New ' . $lc_s . ' name',
		'parent_item'                => 'Parent ' . $lc_s,
		'parent_item_colon'          => 'Parent ' . $lc_s . ':',
		'search_items'               => 'Search ' . $lc_p,
		'not_found'                  => 'No ' . $lc_p . ' found.',
		'no_terms'                   => 'No ' . $lc_p,
		'filter_by_item'             => 'Filter by ' . $lc_s,
		'items_list_navigation'      => $plural . ' list navigation',
		'items_list'                 => $plural . ' list',
		'back_to_items'              => '&larr; Go to ' . $lc_p,
		'item_link'                  => $singular . ' link',
		'item_link_description'      => 'A link to a ' . $lc_s . '.',
		'most_used'                  => 'Most used',
		'popular_items'              => null,
		'separate_items_with_commas' => null,
		'add_or_remove_items'        => null,
		'choose_from_most_used'      => null,
	);
}

/* -------------------------------------------------------------------------
 * 2. Starter templates
 *
 * A starter `template` only pre-fills a NEW item. It is NOT a lock: any
 * truthy `template_lock` on a post type refuses insertion everywhere in that
 * type, which would make the band patterns uninsertable. Per-band locks live
 * in the patterns, which the band track owns. Do not add template_lock here.
 * ---------------------------------------------------------------------- */

/**
 * Starter band skeleton for a new service.
 *
 * @return array
 */
function brightpath_service_starter_template() {
	return array(
		array(
			'core/paragraph',
			array(
				'placeholder' => 'Intro heading follows; write the two or three opening paragraphs here.',
			),
		),
		array( 'core/heading', array( 'level' => 2, 'placeholder' => 'What this support covers' ) ),
		array( 'core/list', array( 'className' => 'tick-list' ) ),
		array( 'core/heading', array( 'level' => 2, 'placeholder' => 'How it works' ) ),
		array( 'core/heading', array( 'level' => 2, 'placeholder' => 'Common questions' ) ),
	);
}

/**
 * Starter band skeleton for a new SIL property.
 *
 * @return array
 */
function brightpath_sil_starter_template() {
	return array(
		array( 'core/paragraph', array( 'placeholder' => 'About this home.' ) ),
		array( 'core/heading', array( 'level' => 2, 'placeholder' => 'The specifics' ) ),
		array( 'core/list', array( 'className' => 'bp-spec-list' ) ),
		array( 'core/heading', array( 'level' => 2, 'placeholder' => 'Features' ) ),
		array( 'core/list', array( 'className' => 'tick-list' ) ),
		array( 'core/heading', array( 'level' => 2, 'placeholder' => 'The available bedroom' ) ),
	);
}

/* -------------------------------------------------------------------------
 * 3. Post types
 * ---------------------------------------------------------------------- */

/**
 * Register the six types. Nothing is left to a default: show_in_rest is
 * explicit on every one of them, because without it the CLASSIC editor opens
 * and a block-composed detail page cannot be authored at all.
 */
function brightpath_register_post_types() {

	$common = array(
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => true,
		'show_in_admin_bar'  => true,
		'show_in_rest'       => true,
		'query_var'          => true,
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'hierarchical'       => false,
		'can_export'         => true,
		'delete_with_user'   => false,
	);

	// service — archive at /services/, single at /services/<slug>/.
	register_post_type(
		'service',
		array_merge(
			$common,
			array(
				'labels'        => brightpath_pt_labels( 'Service', 'Services', 'Services' ),
				'description'   => 'The eight NDIS supports. One template, eight items — eight pages would be eight places to fix one typo.',
				'menu_icon'     => 'dashicons-heart',
				'menu_position' => 21,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ),
				'taxonomies'    => array( 'service_category' ),
				'has_archive'   => 'services',
				'rewrite'       => array(
					'slug'       => 'services',
					'with_front' => false,
					'feeds'      => true,
					'pages'      => true,
				),
				'rest_base'     => 'service',
				'template'      => brightpath_service_starter_template(),
			)
		)
	);

	// resource — NO 'author'. The byline is a taxonomy, not a WP user.
	register_post_type(
		'resource',
		array_merge(
			$common,
			array(
				'labels'        => brightpath_pt_labels( 'Resource', 'Resources', 'Resources' ),
				'description'   => 'Guides, NDIS explainers and company news. The byline is the resource_author taxonomy, because a byline is not a login.',
				'menu_icon'     => 'dashicons-media-document',
				'menu_position' => 22,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
				'taxonomies'    => array( 'resource_category', 'resource_author' ),
				'has_archive'   => 'resources',
				'rewrite'       => array(
					'slug'       => 'resources',
					'with_front' => false,
					'feeds'      => true,
					'pages'      => true,
				),
				'rest_base'     => 'resource',
			)
		)
	);

	/*
	 * job — has_archive FALSE on purpose.
	 *
	 * /careers/ is a PAGE with editorial bands above its listing (contract
	 * 12.4). Giving the type an archive would put a second document on the
	 * same URL and force a redirect to undo it. With has_archive false and
	 * rewrite.slug 'careers', /careers/ resolves to the page and
	 * /careers/<slug>/ to single-job.html. There is no archive URL, so there
	 * is nothing to redirect: 12.4's redirect step must not be built.
	 */
	register_post_type(
		'job',
		array_merge(
			$common,
			array(
				'labels'        => brightpath_pt_labels( 'Job', 'Jobs', 'Jobs' ),
				'description'   => 'Open roles. Listed by the careers PAGE, not by an archive — see the note in inc/post-types.php.',
				'menu_icon'     => 'dashicons-businessperson',
				'menu_position' => 23,
				'supports'      => array( 'title', 'editor', 'excerpt', 'revisions', 'page-attributes', 'custom-fields' ),
				'taxonomies'    => array( 'job_location', 'job_type', 'job_status', 'job_pay' ),
				'has_archive'   => false,
				'rewrite'       => array(
					'slug'       => 'careers',
					'with_front' => false,
					'feeds'      => false,
					'pages'      => false,
				),
				'rest_base'     => 'job',
			)
		)
	);

	// location — same has_archive:false reasoning as job.
	register_post_type(
		'location',
		array_merge(
			$common,
			array(
				'labels'        => brightpath_pt_labels( 'Location', 'Locations', 'Locations' ),
				'description'   => 'Service areas. Listed by the locations PAGE, not by an archive.',
				'menu_icon'     => 'dashicons-location',
				'menu_position' => 24,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ),
				'taxonomies'    => array( 'location_region' ),
				'has_archive'   => false,
				'rewrite'       => array(
					'slug'       => 'locations',
					'with_front' => false,
					'feeds'      => false,
					'pages'      => false,
				),
				'rest_base'     => 'location',
			)
		)
	);

	// sil_property — the filtered archive at /sil-homes/.
	register_post_type(
		'sil_property',
		array_merge(
			$common,
			array(
				'labels'        => brightpath_pt_labels( 'SIL home', 'SIL homes', 'SIL homes' ),
				'description'   => 'Supported Independent Living vacancies. Filtered on the archive by four taxonomies; see contract section 16.',
				'menu_icon'     => 'dashicons-admin-home',
				'menu_position' => 25,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ),
				'taxonomies'    => array( 'sil_region', 'sil_type', 'sil_access', 'sil_status' ),
				'has_archive'   => 'sil-homes',
				'rewrite'       => array(
					'slug'       => 'sil-homes',
					'with_front' => false,
					'feeds'      => true,
					'pages'      => true,
				),
				'rest_base'     => 'sil_property',
				'template'      => brightpath_sil_starter_template(),
			)
		)
	);

	/*
	 * team_member — NON-PUBLIC, and this is deliberate.
	 *
	 * Team members are rendered by a query band on /our-team/ and /about/
	 * (contract 12.2). There is no single-team_member document in the capture
	 * and therefore no template for one. A PUBLICLY QUERYABLE type with no
	 * single template renders the 404 template at a 200 URL — the exact trap
	 * section 7 records, and the one that looks like a working page in every
	 * link check. So the type is not queryable at all: no permalink, no
	 * archive, out of search. It is still show_in_rest, so the editor is the
	 * block editor and the query band can read it.
	 */
	register_post_type(
		'team_member',
		array(
			'labels'             => brightpath_pt_labels( 'Team member', 'Team members', 'Team' ),
			'description'        => 'People. Rendered by a query band on our-team and about. Deliberately has NO public URL: there is no single-team_member template, and a queryable type without one serves the 404 at a 200.',
			'public'             => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'team_member',
			'query_var'          => false,
			'rewrite'            => false,
			'has_archive'        => false,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-groups',
			'menu_position'      => 26,
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ),
		)
	);
}
add_action( 'init', 'brightpath_register_post_types', 5 );

/* -------------------------------------------------------------------------
 * 4. Taxonomies
 *
 * All nine are hierarchical:true. That is the UI decision, not a statement
 * about trees: hierarchical gives the editor a CHECKBOX LIST with the
 * existing terms visible, where a flat taxonomy gives a free-text tag box
 * that invites "Bankstown", "bankstown " and "Bankstwon" as three terms and
 * silently breaks the filter that reads the slug.
 *
 * The sil_ prefix is not cosmetic. `type` and `status` are reserved WP query
 * vars AND core already emits type-post / status-publish into post_class, on
 * which the archive filter depends (contract 16). A taxonomy named `type`
 * would collide in both namespaces at once.
 * ---------------------------------------------------------------------- */

/**
 * Register the nine taxonomies.
 */
function brightpath_register_taxonomies() {

	$common = array(
		'public'             => true,
		'publicly_queryable' => true,
		'hierarchical'       => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => true,
		'show_in_rest'       => true,
		'show_admin_column'  => true,
		'show_tagcloud'      => false,
		'show_in_quick_edit' => true,
	);

	$taxonomies = array(
		// sil_property — the four filter axes of contract 16.
		'sil_region'        => array( 'SIL region', 'SIL regions', array( 'sil_property' ), 'sil-region' ),
		'sil_type'          => array( 'SIL dwelling type', 'SIL dwelling types', array( 'sil_property' ), 'sil-type' ),
		'sil_access'        => array( 'SIL accessibility', 'SIL accessibility levels', array( 'sil_property' ), 'sil-access' ),
		'sil_status'        => array( 'SIL vacancy status', 'SIL vacancy statuses', array( 'sil_property' ), 'sil-status' ),

		'service_category'  => array( 'Service category', 'Service categories', array( 'service' ), 'service-category' ),

		'resource_category' => array( 'Resource category', 'Resource categories', array( 'resource' ), 'resource-category' ),
		// A byline, not a login. supports:['author'] would force a WP user
		// account per contributor, with a password and a login surface, for
		// a name under a heading. Role lives in the term description;
		// initials are derived from the name, never stored.
		'resource_author'   => array( 'Resource author', 'Resource authors', array( 'resource' ), 'resource-author' ),

		'job_location'      => array( 'Job location', 'Job locations', array( 'job' ), 'job-location' ),
		'job_type'          => array( 'Employment type', 'Employment types', array( 'job' ), 'job-type' ),
		'job_status'        => array( 'Job status', 'Job statuses', array( 'job' ), 'job-status' ),
		// The least conventional call here, and it is forced by a measured
		// fact: the pay band RENDERS ON THE CAREERS LISTING CARD, so it must
		// be readable from outside post_content, which is exactly the
		// criterion for a taxonomy. The closing date, which does not appear
		// on the card, stays in post_content.
		'job_pay'           => array( 'Pay band', 'Pay bands', array( 'job' ), 'job-pay' ),

		'location_region'   => array( 'Location region', 'Location regions', array( 'location' ), 'location-region' ),
	);

	foreach ( $taxonomies as $name => $args ) {
		list( $singular, $plural, $object_types, $slug ) = $args;

		register_taxonomy(
			$name,
			$object_types,
			array_merge(
				$common,
				array(
					'labels'    => brightpath_tax_labels( $singular, $plural ),
					'rest_base' => $name,
					'query_var' => $name,
					'rewrite'   => array(
						'slug'         => $slug,
						'with_front'   => false,
						'hierarchical' => false,
					),
				)
			)
		);
	}
}
add_action( 'init', 'brightpath_register_taxonomies', 6 );

/**
 * team_role — the ONE field the team card renders beside the name.
 *
 * Why a taxonomy, and why not typed into the card. Contract 12 says a badge is
 * bound to a field and never typed, and section 12.2 resolved the team into a
 * CPT precisely because /about/'s three cards and /our-team/'s six are the same
 * people: a role typed into a band drifts from the person's record the first
 * time a job title changes. The model in this file admits a field as a taxonomy
 * IFF it must be read from OUTSIDE post_content — and the role is read by the
 * query band's card, which is exactly that test. Hierarchical, so the editor
 * gets a checkbox list of the roles that already exist rather than a free-text
 * box that invites three spellings of "Support Coordinator".
 *
 * NON-PUBLIC, for the same reason team_member is. A public taxonomy gives every
 * term an archive URL, and there is no template for /team-role/<slug>/, so that
 * URL would serve the 404 document at a 200 — the trap section 7 records.
 *
 * It is also why the card does NOT use core/post-terms: that block always wraps
 * each term in an <a href="…">, and for a non-public taxonomy get_term_link()
 * yields a URL that cannot resolve. The role is printed through the
 * brightpath/derived binding source instead (key `team_role`), which emits text
 * and no link at all.
 */
function brightpath_register_team_role_taxonomy() {
	register_taxonomy(
		'team_role',
		array( 'team_member' ),
		array(
			'labels'             => brightpath_tax_labels( 'Role', 'Roles' ),
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => true,
			'rest_base'          => 'team_role',
			'show_admin_column'  => true,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => true,
			'query_var'          => false,
			'rewrite'            => false,
		)
	);
}
add_action( 'init', 'brightpath_register_team_role_taxonomy', 6 );

/* -------------------------------------------------------------------------
 * 5. Term seeds — idempotent
 *
 * Guarded by term_exists, so this runs on every request harmlessly and an
 * editor's renamed or deleted term is never resurrected under a new ID.
 * ---------------------------------------------------------------------- */

/**
 * The seed terms. slug => name, or slug => array( name, description ).
 *
 * @return array<string,array>
 */
function brightpath_seed_terms() {
	return array(
		'sil_region'        => array(
			'bankstown'      => 'Bankstown',
			'blacktown'      => 'Blacktown',
			'hills'          => 'The Hills',
			'inner-west'     => 'Inner West',
			'liverpool'      => 'Liverpool',
			'parramatta'     => 'Parramatta',
			'penrith'        => 'Penrith',
			'western-sydney' => 'Western Sydney',
		),
		'sil_type'          => array(
			'individual' => 'Individual',
			'shared'     => 'Shared',
		),
		'sil_access'        => array(
			'ambulant'   => 'Ambulant',
			'step-free'  => 'Step-free',
			'wheelchair' => 'Wheelchair accessible',
		),
		'sil_status'        => array(
			'available' => 'Available',
			'limited'   => 'Limited availability',
			'full'      => 'Full',
		),
		'service_category'  => array(
			'core-supports'     => 'Core supports',
			'capacity-building' => 'Capacity building',
			'accommodation'     => 'Accommodation',
			'plan-supports'     => 'Plan supports',
		),
		'resource_category' => array(
			'guides'                => 'Guides',
			'ndis-information'      => 'NDIS information',
			'participant-resources' => 'Participant resources',
			'coordinator-resources' => 'Coordinator resources',
			'company-news'          => 'Company news',
		),
		'resource_author'   => array(
			// The term DESCRIPTION carries the role. Initials are derived
			// from the name at render time and are never stored, so they
			// cannot disagree with it.
			'bright-path-team' => array( 'Bright Path Team', 'Placeholder byline — replace with a named contributor before launch.' ),
		),
		'job_location'      => array(
			'parramatta'     => 'Parramatta',
			'blacktown'      => 'Blacktown',
			'liverpool'      => 'Liverpool',
			'penrith'        => 'Penrith',
			'western-sydney' => 'Western Sydney',
			// MEASURED from design/careers.html: the sixth vacancy is located
			// "Marrickville", not "Inner West". Seeding the region instead
			// would put a different word on the card than the design shows.
			'marrickville'   => 'Marrickville',
		),
		'job_type'          => array(
			'full-time'  => 'Full time',
			'part-time'  => 'Part time',
			'casual'     => 'Casual',
			'contract'   => 'Contract',
		),
		'job_status'        => array(
			'open'           => 'Open',
			'closing-soon'   => 'Closing soon',
			'closed'         => 'Closed',
		),
		'job_pay'           => array(
			/*
			 * PLACEHOLDER, deliberately. Contract 12 records the prototype's
			 * "$34.85 - $41.76" as placeholder copy that is a FACTUAL CLAIM
			 * about pay and must not ship as written. Seeding the real-looking
			 * number here is how it ships by accident.
			 */
			'pay-band-tbc' => array( 'Pay band to be confirmed', 'PLACEHOLDER. Replace with the award classification and rate confirmed by the employer. Do not publish a rate that has not been confirmed.' ),
		),
		'team_role'         => array(
			'chief-executive-officer' => 'Chief Executive Officer',
			'operations-manager'      => 'Operations Manager',
			'clinical-lead'           => 'Clinical Lead',
			'support-coordinator'     => 'Support Coordinator',
			'sil-house-manager'       => 'SIL House Manager',
			'community-engagement'    => 'Community Engagement Lead',
		),
		'location_region'   => array(
			'western-sydney'  => 'Western Sydney',
			'south-western-sydney' => 'South Western Sydney',
			'inner-west'      => 'Inner West',
			'hills'           => 'The Hills',
		),
	);
}

/**
 * Insert any seed term that does not already exist.
 */
function brightpath_seed_taxonomy_terms() {
	foreach ( brightpath_seed_terms() as $taxonomy => $terms ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		foreach ( $terms as $slug => $value ) {
			if ( term_exists( $slug, $taxonomy ) ) {
				continue;
			}

			$name        = is_array( $value ) ? $value[0] : $value;
			$description = is_array( $value ) && isset( $value[1] ) ? $value[1] : '';

			wp_insert_term(
				$name,
				$taxonomy,
				array(
					'slug'        => $slug,
					'description' => $description,
				)
			);
		}
	}
}
add_action( 'init', 'brightpath_seed_taxonomy_terms', 15 );

/* -------------------------------------------------------------------------
 * 6. The rewrite flush — once, on a version bump, never on every request
 * ---------------------------------------------------------------------- */

/**
 * Flush rewrite rules exactly once after a registration change.
 *
 * flush_rewrite_rules() on every request rebuilds and re-saves the whole rule
 * set on every page load. Not flushing at all 404s every single-item URL for
 * a type that is registered perfectly. This is the narrow path between them.
 */
function brightpath_maybe_flush_rewrites() {
	if ( get_option( 'brightpath_rewrite_version' ) === BRIGHTPATH_REWRITE_VERSION ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'brightpath_rewrite_version', BRIGHTPATH_REWRITE_VERSION, true );
}
add_action( 'init', 'brightpath_maybe_flush_rewrites', 20 );

/* -------------------------------------------------------------------------
 * 7. Derived fields — computed at render, never stored
 *
 * Exposed through the block bindings API so a core/paragraph in a template
 * can print them. A stored read time is wrong the first time the body is
 * edited; stored initials are wrong the first time the name is corrected.
 * ---------------------------------------------------------------------- */

/**
 * Initials from a name: "Bright Path Team" -> "BT" (first and last word).
 *
 * @param string $name Full name.
 * @return string
 */
function brightpath_initials( $name ) {
	$words = preg_split( '/\s+/', trim( wp_strip_all_tags( (string) $name ) ), -1, PREG_SPLIT_NO_EMPTY );

	if ( empty( $words ) ) {
		return '';
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $words[0], 0, 1 ) : substr( $words[0], 0, 1 );

	if ( count( $words ) === 1 ) {
		return strtoupper( $first );
	}

	$last = end( $words );
	$last = function_exists( 'mb_substr' ) ? mb_substr( $last, 0, 1 ) : substr( $last, 0, 1 );

	return strtoupper( $first . $last );
}

/**
 * Read time in whole minutes, from the rendered word count at 220 wpm.
 *
 * @param int $post_id Post ID.
 * @return string e.g. "4 min read".
 */
function brightpath_read_time( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return '';
	}

	$words   = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	$minutes = max( 1, (int) ceil( $words / 220 ) );

	return $minutes . ' min read';
}

/**
 * The first resource_author term of a post, or null.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function brightpath_resource_author_term( $post_id ) {
	$terms = get_the_terms( $post_id, 'resource_author' );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}

	return $terms[0];
}

/**
 * The first team_role term NAME of a team member, or ''.
 *
 * Returns the name, not a link: team_role is non-public and has no archive.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function brightpath_team_role_name( $post_id ) {
	$terms = get_the_terms( $post_id, 'team_role' );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	return $terms[0]->name;
}

/**
 * Resolve one derived key for a post.
 *
 * @param string $key     One of read_time, team_role, media_label, location_h1, location_cta, author_initials, author_name, author_role.
 * @param int    $post_id Post ID.
 * @return string
 */
function brightpath_derived_value( $key, $post_id ) {
	if ( 'read_time' === $key ) {
		return brightpath_read_time( $post_id );
	}

	if ( 'team_role' === $key ) {
		return brightpath_team_role_name( $post_id );
	}

	/*
	 * MEASURED from design/locations.html and design/location.html, which
	 * disagree with each other on purpose: the listing card says
	 * "Parramatta" and the page heading says "NDIS Support Services in
	 * Parramatta". That is ONE string and a formula, not two stored fields,
	 * so post_title holds the short name the card needs and the single
	 * template composes the heading. Storing both would create a second
	 * place for "Parramatta" to be spelled.
	 */
	/*
	 * MEASURED, and the reason this is derived rather than a raw
	 * core/post-meta binding: core's post-meta source returns the stored
	 * value even when that value is the empty string, and the block then
	 * renders an EMPTY label. An unseeded record therefore showed a striped
	 * frame with no brief in it -- indistinguishable from a correct one at a
	 * glance, and a silent loss of the photographer's brief. Going through
	 * this source gives the fallback a place to live.
	 */
	if ( 'media_label' === $key ) {
		$label = get_post_meta( $post_id, 'bp_media_label', true );

		return $label ? $label : 'Photograph — brief to be set';
	}

	if ( 'location_h1' === $key ) {
		$title = get_the_title( $post_id );

		return $title ? sprintf( 'NDIS Support Services in %s', $title ) : '';
	}

	/*
	 * MEASURED from design/location.html: the hero CTA reads "Request support
	 * in Parramatta". Same shape as location_h1 and derived for the same
	 * reason -- the place name is spelled once, in post_title, and both the
	 * heading and the button label are formulas over it. A stored button
	 * label would be a second place for "Parramatta" to be spelled and a
	 * second place for it to be spelled differently.
	 *
	 * The empty-title fallback is the bare label rather than '' because this
	 * one feeds a BUTTON: an empty string renders a button with no text,
	 * which is a click target a screen reader announces as nothing. An
	 * unseeded record must degrade to a usable control, not an invisible one.
	 */
	if ( 'location_cta' === $key ) {
		$title = get_the_title( $post_id );

		return $title ? sprintf( 'Request support in %s', $title ) : 'Request support';
	}

	$term = brightpath_resource_author_term( $post_id );

	if ( ! $term ) {
		return '';
	}

	switch ( $key ) {
		case 'author_initials':
			return brightpath_initials( $term->name );
		case 'author_name':
			return $term->name;
		case 'author_role':
			return $term->description;
	}

	return '';
}

/**
 * Register the bindings source. Guarded: the API is WP 6.5+, and a theme that
 * fatals on an older core takes the whole site down rather than losing a
 * byline.
 */
function brightpath_register_derived_bindings() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	register_block_bindings_source(
		'brightpath/derived',
		array(
			'label'              => 'Bright Path derived field',
			'get_value_callback' => static function ( $source_args, $block_instance ) {
				$post_id = isset( $block_instance->context['postId'] ) ? (int) $block_instance->context['postId'] : get_the_ID();
				$key     = isset( $source_args['key'] ) ? $source_args['key'] : '';

				if ( ! $post_id || ! $key ) {
					return '';
				}

				return brightpath_derived_value( $key, $post_id );
			},
			'uses_context'       => array( 'postId', 'postType' ),
		)
	);
}
add_action( 'init', 'brightpath_register_derived_bindings', 8 );

/* -------------------------------------------------------------------------
 * 7b. The ONE registered meta field, and why the "zero post meta" rule above
 *     has an exception here
 *
 * The model at the top of this file says zero post meta, and the reason it
 * gives is the editing UI: registered meta is storable and invisible, so
 * changing it becomes a developer ticket. That reason is sound and it still
 * holds for every operational field -- the pay band, the vacancy status, the
 * region -- all of which stayed taxonomies.
 *
 * It does not hold for this one. `bp_media_label` is the BRIEF FOR A
 * PHOTOGRAPH ("SIL Home Exterior -- Parramatta"), contract section 11.3. It
 * is:
 *
 *   - per record and shared with nothing, so a taxonomy is the wrong shape:
 *     a taxonomy with one term per post is a table with a unique index on a
 *     free-text column, and it would put 30 single-use terms in a filter UI;
 *   - not post_excerpt, which is already the page lede AND the text the
 *     listing card renders -- measured, not assumed: every card grid in this
 *     build is post-title + post-excerpt;
 *   - generator-owned, not editor-owned. It is written by qa/posts-seed.py
 *     from the prototype and it is deleted the day a real photograph lands.
 *
 * It is not invisible either: every type here declares `custom-fields`
 * support, and a registered, unprotected meta key renders in core's own
 * Custom Fields panel with no meta box written by this theme.
 *
 * `single` and `show_in_rest` are both required -- show_in_rest so the
 * generator can write it, `single` so core/post-meta bindings can read it.
 * WITHOUT an auth_callback this key would be editable by anyone who can edit
 * the post; with the default it is `edit_post`, which is what we want, but it
 * is stated out loud because the default for a registered meta changed shape
 * once already.
 * ---------------------------------------------------------------------- */

/**
 * Types that carry a media-placeholder brief on their single template.
 *
 * @return string[]
 */
function brightpath_media_label_types() {
	return array( 'service', 'location', 'sil_property', 'resource', 'team_member' );
}

/**
 * Register bp_media_label on every type whose template renders a media frame.
 */
function brightpath_register_media_label_meta() {
	foreach ( brightpath_media_label_types() as $type ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}

		register_post_meta(
			$type,
			'bp_media_label',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'description'       => 'The brief for the photograph that belongs in this record\'s media frame. Placeholder copy, contract 11.3 — it is deleted when a real image is uploaded.',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'brightpath_register_media_label_meta', 7 );

/* -------------------------------------------------------------------------
 * 8. The non-public type, and the one line that lets a query band see it
 * ---------------------------------------------------------------------- */

/**
 * Restore `post_type` for the team band's query loop.
 *
 * MEASURED, not assumed. A core/query CANNOT list team_member as shipped. Core's
 * build_query_vars_from_query_block() reads the block's postType and keeps it
 * only `if ( is_post_type_viewable( $post_type_param ) )` — and team_member is
 * deliberately neither public nor publicly_queryable, so the attribute is
 * DISCARDED IN SILENCE and the query falls back to the `post` default. The band
 * rendered one item: "Hello world". No error, no warning, no empty container —
 * a plausible-looking card with the wrong content, which is worse than nothing.
 *
 * The two ways out, and why this one:
 *
 *   publicly_queryable => true would satisfy is_post_type_viewable, and would
 *   also give every member a resolvable URL (?post_type=team_member&p=ID) that
 *   falls through to single.html. There is no single-team_member design, so
 *   that is a real page at a 200 for a document that does not exist — the trap
 *   section 7 records, reintroduced to fix a listing.
 *
 *   This filter changes the query and nothing else. No rewrite rule, no
 *   query_var, no permalink, no archive: the type stays unreachable and the
 *   band can read it.
 *
 * It is scoped by what the BLOCK ASKED FOR, not by a page or a template, so it
 * cannot leak: it only ever restores the exact post type the markup already
 * named, and only for a type this theme has deliberately hidden.
 *
 * @param array    $query The query vars core built.
 * @param WP_Block $block The post-template block, carrying the query context.
 * @return array
 */
function brightpath_restore_hidden_query_post_type( $query, $block ) {
	$requested = isset( $block->context['query']['postType'] ) ? $block->context['query']['postType'] : '';

	// The allowlist is the point: only types this theme hides on purpose.
	if ( ! in_array( $requested, array( 'team_member' ), true ) ) {
		return $query;
	}

	if ( ! post_type_exists( $requested ) ) {
		return $query;
	}

	$query['post_type'] = $requested;

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'brightpath_restore_hidden_query_post_type', 10, 2 );

/**
 * Order the CPT archives by menu_order.
 *
 * The archive templates use `core/query` with `"inherit": true`, which discards
 * the block's own `orderBy` and runs WordPress's main query instead. So the
 * `"orderBy":"date"` written in those blocks is decorative, and `menu_order` —
 * set on every record and correct in the database — was never consulted. The
 * SIL archive rendered newest-first while the homepage band, which uses
 * `inherit: false`, rendered the designed order. Same records, two orders, no
 * error anywhere.
 *
 * `pre_get_posts` is the only lever that reaches an inherited query.
 */
function brightpath_archive_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$ordered = array( 'service', 'sil_property', 'location', 'job', 'resource' );

	if ( ! $query->is_post_type_archive( $ordered ) ) {
		return;
	}

	// Only when the request has not asked for something else, so a sorted
	// archive URL still works.
	if ( ! empty( $query->get( 'orderby' ) ) ) {
		return;
	}

	$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
}
add_action( 'pre_get_posts', 'brightpath_archive_order' );
