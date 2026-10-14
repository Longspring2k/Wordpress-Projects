/* Editor canvas parity: mirror the front-end body classes that CSS keys on
   (page-id-N, bp-page-<slug>, home, single-<type>) onto the iframe body, and flag templates
   that print no title so editor.css can hide the post-title field. */
( function ( wp ) {
	if ( ! wp || ! wp.data ) { return; }
	var cfg = window.brightpathEditor || {};
	var NO_TITLE = [ 'page-no-title', 'front-page' ];
	var last = '';
	function apply() {
		var ed = wp.data.select( 'core/editor' );
		if ( ! ed || ! ed.getCurrentPostId ) { return; }
		var id = ed.getCurrentPostId(), type = ed.getCurrentPostType();
		var tpl = ed.getEditedPostAttribute( 'template' ) || '';
		var cls = [ 'page-id-' + id, 'postid-' + id, 'single-' + type, 'bp-editor-canvas' ];
		var slug = ed.getEditedPostAttribute( 'slug' );
		if ( 'page' === type && slug ) { cls.push( 'bp-page-' + slug ); }
		if ( String( id ) === String( cfg.frontId ) ) { cls.push( 'home', 'front-page' ); tpl = tpl || 'front-page'; }
		if ( NO_TITLE.indexOf( tpl ) > -1 ) { cls.push( 'bp-editor--no-title' ); }
		var key = cls.join( ' ' );
		var frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		var body = frame && frame.contentDocument && frame.contentDocument.body;
		if ( ! body ) { return; }
		if ( key === last && body.classList.contains( 'bp-editor-canvas' ) ) { return; }
		( body.dataset.bpCls || '' ).split( ' ' ).forEach( function ( c ) { if ( c ) { body.classList.remove( c ); } } );
		cls.forEach( function ( c ) { body.classList.add( c ); } );
		body.dataset.bpCls = key; last = key;
	}
	wp.data.subscribe( apply );
	setInterval( apply, 1000 );
} )( window.wp );

/* Editor values for the brightpath/parity keys used by the location bands
   (patterns location-services / location-why), from the edited title. */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.blocks.registerBlockBindingsSource ) { return; }
	var T = {
		location_services_h: function ( t ) { return 'Services available in ' + t; },
		location_why_h: function ( t ) { return 'Why participants in ' + t + ' choose us.'; },
		location_team_btn: function ( t ) { return 'Talk to the ' + t + ' team'; },
		location_map_label: function ( t ) { return 'Map \u2014 ' + t + ' Service Area'; }
	};
	try {
		wp.blocks.registerBlockBindingsSource( {
			name: 'brightpath/parity',
			usesContext: [ 'postId', 'postType' ],
			getValues: function ( o ) {
				var ed = o.select( 'core/editor' );
				var t = ed && ed.getEditedPostAttribute ? ( ed.getEditedPostAttribute( 'title' ) || '' ) : '';
				var out = {};
				Object.keys( o.bindings || {} ).forEach( function ( attr ) {
					var k = ( o.bindings[ attr ].args || {} ).key;
					out[ attr ] = T[ k ] ? T[ k ]( t ) : k;
				} );
				return out;
			},
			canUserEditValue: function () { return false; }
		} );
	} catch ( e ) {}
} )( window.wp );
