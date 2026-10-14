/* Editor parity, lane A (visual bands). Editor window only, never on the front.
   The front wraps post content in template markup the canvas does not have
   (`main.bp-tpl.bp-tpl--single-<type>`, `.entry-content`, `.article-layout`,
   `.bp-job-content`, ...). Dozens of front rules are keyed on those classes, so
   the canvas mirrors them: type class on the iframe body (ancestor of the
   content, like `main.bp-tpl--single-x` on the front) and the post-content
   classes on the block-list root container. Idempotent, re-applied on re-render. */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.data ) { return; }

	// Classes the templates print on the wp:post-content element, by post type.
	var ROOT = {
		page: [ 'entry-content' ],
		service: [ 'entry-content' ],
		location: [ 'entry-content', 'bp-loc-content' ],
		resource: [ 'entry-content', 'article-layout', 'bp-prose' ],
		job: [ 'entry-content', 'bp-prose', 'bp-job-content' ],
		sil_property: [ 'entry-content', 'bp-prop-content' ]
	};
	var TPL_TYPES = [ 'service', 'location', 'resource', 'job', 'sil_property' ];

	function apply() {
		var ed = wp.data.select( 'core/editor' );
		if ( ! ed || ! ed.getCurrentPostType ) { return; }
		var type = ed.getCurrentPostType();
		var f = document.querySelector( 'iframe[name="editor-canvas"]' );
		var d = f && f.contentDocument;
		var root = d && d.querySelector( '.is-root-container' );
		if ( ! root || ! d.body ) { return; }
		( ROOT[ type ] || [ 'entry-content' ] ).forEach( function ( c ) {
			if ( ! root.classList.contains( c ) ) { root.classList.add( c ); }
		} );
		if ( TPL_TYPES.indexOf( type ) > -1 ) {
			[ 'bp-tpl', 'bp-tpl--single-' + type ].forEach( function ( t ) {
				if ( ! d.body.classList.contains( t ) ) { d.body.classList.add( t ); }
			} );
		}
	}

	wp.data.subscribe( apply );
	setInterval( apply, 500 );
}( window.wp ) );
