/* Editor parity, lane D: forms.
 *
 * On the front a core/html block that holds only a `data-brightpath-form`
 * marker is swapped for the PHP-rendered form. In the editor the marker used
 * to show as a dashed grey box. This file replaces the block's edit UI with an
 * inert preview of the SAME markup (fetched from /brightpath/v1/form-preview/<slug>,
 * see inc/forms.php), so the canvas matches the front.
 *
 * Nothing about post_content changes: the block still stores the marker div,
 * stays a core/html block (selectable, movable, deletable, duplicable) and can
 * be re-inserted from the inserter's "Forms" patterns (inc/forms.php). The preview never submits: it is `inert`, its action is #
 * and the per-request tokens are stripped server-side.
 */
( function ( wp ) {
	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.blockEditor ) { return; }

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var comp = wp.components;

	var FORMS = {
		'contact': 'Contact form',
		'request-support': 'Support request form',
		'referral': 'Referral form',
		'feedback': 'Feedback form',
		'job-application': 'Job application form',
		'sil-enquiry': 'SIL enquiry form'
	};

	function slugOf( content ) {
		var m = /data-brightpath-form="([a-z0-9-]+)"/.exec( content || '' );
		return m ? m[ 1 ] : '';
	}

	function marker( slug ) {
		return '<div data-brightpath-form="' + slug + '" style="display:block;padding:var(--wp--preset--spacing--40);border:2px dashed var(--wp--custom--color--border-hover);border-radius:var(--wp--custom--radius--md);background-color:var(--wp--custom--color--bg-soft);color:var(--wp--custom--color--text-body);text-align:center"><strong style="display:block;font-weight:700;color:var(--wp--custom--color--text);margin-bottom:var(--wp--preset--spacing--10)">' + FORMS[ slug ] + '</strong><span style="display:block">Rendered from the field schema when someone views this page.</span></div>';
	}

	/* ---- preview fetch (cached per slug) ---- */
	var cache = {};
	function load( slug ) {
		if ( ! cache[ slug ] ) {
			cache[ slug ] = wp.apiFetch( { path: '/brightpath/v1/form-preview/' + slug } ).then( function ( r ) {
				return r && r.html ? r.html : '';
			} ).catch( function () { delete cache[ slug ]; return ''; } );
		}
		return cache[ slug ];
	}

	/* Static state forms.js gives the form on load: first step active, stepper
	   at step 1, form inert. */
	function prepare( html ) {
		var n = ( html.match( /<section class="form-step"/g ) || [] ).length;
		html = html.replace( '<section class="form-step"', '<section class="form-step is-active"' );
		if ( n ) {
			html = html.replace( /data-stepper-fill(="")?/, 'data-stepper-fill style="inline-size:' + ( 100 / n ) + '%"' );
			html = html.replace( '<form ', '<form data-current-step="1" ' );
		}
		/* forms.js also DISABLES the controls of dependent fields whose condition is unmet
		   (and the front's CSS still lays them out), so mirror that state. */
		var box = document.createElement( 'div' );
		box.innerHTML = html;
		box.querySelectorAll( '.field[data-depends]' ).forEach( function ( f ) {
			f.querySelectorAll( 'input,select,textarea' ).forEach( function ( c ) { c.setAttribute( 'disabled', '' ); } );
		} );
		html = box.innerHTML;
		return html.replace( '<form ', '<form inert ' );
	}

	function withFormPreview( BlockEdit ) {
		return function ( props ) {
			if ( props.name !== 'core/html' ) { return el( BlockEdit, props ); }
			return el( HtmlGate, { base: BlockEdit, blockProps: props } );
		};
	}

	/* WP 7.1 keeps core/html markup in innerContent, older cores in the content attribute. */
	function htmlOf( clientId, attrs ) {
		var b = wp.data.select( 'core/block-editor' ).getBlock( clientId );
		var c = '';
		try { c = b ? wp.blocks.getBlockContent( b ) : ''; } catch ( e ) {}
		return c || ( attrs && attrs.content ) || '';
	}

	function HtmlGate( p ) {
		var props = p.blockProps;
		var content = wp.data.useSelect( function () { return htmlOf( props.clientId, props.attributes ); }, [ props.clientId, props.attributes.content ] );
		if ( ! slugOf( content ) ) { return el( p.base, props ); }
		return el( FormPreview, { base: p.base, blockProps: props, slug: slugOf( content ) } );
	}

	function setMarker( props, slug ) {
		var upd = { innerContent: [ marker( slug ) ] };
		wp.data.dispatch( 'core/block-editor' ).updateBlock( props.clientId, upd );
		props.setAttributes( { metadata: Object.assign( {}, props.attributes.metadata, { name: FORMS[ slug ] } ) } );
	}

	function FormPreview( p ) {
		var props = p.blockProps;
		var slug = p.slug;
		var st = useState( '' ); var html = st[ 0 ]; var setHtml = st[ 1 ];
		var st2 = useState( false ); var raw = st2[ 0 ]; var setRaw = st2[ 1 ];
		useEffect( function () {
			var alive = true;
			load( slug ).then( function ( h ) { if ( alive ) { setHtml( h ? prepare( h ) : '' ); } } );
			return function () { alive = false; };
		}, [ slug ] );

		var controls = el( Fragment, null,
			el( be.BlockControls, { group: 'other' },
				el( comp.ToolbarButton, {
					onClick: function () { setRaw( ! raw ); },
					isPressed: raw
				}, raw ? __( 'Preview form' ) : __( 'Edit HTML' ) )
			),
			el( be.InspectorControls, null,
				el( comp.PanelBody, { title: __( 'Form' ), initialOpen: true },
					el( comp.SelectControl, {
						label: __( 'Form shown here' ),
						value: slug,
						options: Object.keys( FORMS ).map( function ( s ) { return { value: s, label: FORMS[ s ] }; } ),
						onChange: function ( s ) {
							setMarker( props, s );
						}
					} ),
					el( 'p', { style: { fontSize: '12px', color: '#757575' } },
						__( 'The fields, labels and submit behaviour come from the theme. The preview below is inert; the live page shows the working form.' ) )
				)
			)
		);

		if ( raw ) {
			return el( Fragment, null, controls, el( p.base, props ) );
		}

		var bp = be.useBlockProps( { className: 'bp-form-wrap bp-form-preview' } );
		var inner = html
			? { dangerouslySetInnerHTML: { __html: html } }
			: { children: el( 'p', { className: 'bp-form-preview__loading' }, __( 'Loading form preview…' ) ) };
		return el( Fragment, null, controls, el( 'div', Object.assign( {}, bp, inner ) ) );
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'brightpath/parity-D-form-preview', withFormPreview );
} )( window.wp );
