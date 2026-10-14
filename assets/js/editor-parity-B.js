/* Editor parity, lane B: query-loop cards, bound values, PHP-rendered widgets.
 *
 * Everything here is editor-window only. Nothing changes post_content, nothing
 * locks a block. Values come from the read-only REST field `bp_parity`
 * (inc/editor-parity-b.php), computed by the same PHP the front uses.
 */
( function ( wp ) {
	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.blockEditor || ! wp.data ) { return; }
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor;
	var data = wp.data;
	var cfg = window.brightpathEditor || {};

	function rec( select, type, id ) {
		if ( ! type || ! id ) { return null; }
		var r = select( 'core' ).getEntityRecord( 'postType', type, id );
		return r && r.bp_parity ? r.bp_parity : null;
	}

	/* ------------------------------------------------------------------ *
	 * 1. Binding sources: brightpath/parity + brightpath/derived, all keys.
	 * ------------------------------------------------------------------ */
	var TITLE_KEYS = {
		location_services_h: function ( t ) { return 'Services available in ' + t; },
		location_why_h: function ( t ) { return 'Why participants in ' + t + ' choose us.'; },
		location_team_btn: function ( t ) { return 'Talk to the ' + t + ' team'; },
		location_map_label: function ( t ) { return 'Map \u2014 ' + t + ' Service Area'; }
	};

	function makeGetValues( source ) {
		return function ( o ) {
			var ctx = o.context || {};
			var cur = o.select( 'core/editor' );
			var curId = cur && cur.getCurrentPostId ? cur.getCurrentPostId() : 0;
			var postId = ctx.postId || curId;
			var postType = ctx.postType || ( cur && cur.getCurrentPostType ? cur.getCurrentPostType() : '' );
			var p = rec( o.select, postType, postId );
			var title = '';
			if ( String( postId ) === String( curId ) && cur.getEditedPostAttribute ) {
				title = cur.getEditedPostAttribute( 'title' ) || '';
			} else {
				var r = o.select( 'core' ).getEntityRecord( 'postType', postType, postId );
				title = r && r.title ? ( r.title.raw || r.title.rendered || '' ) : '';
			}
			var out = {};
			Object.keys( o.bindings || {} ).forEach( function ( attr ) {
				var k = ( o.bindings[ attr ].args || {} ).key;
				var v = '';
				if ( source === 'brightpath/parity' && TITLE_KEYS[ k ] ) {
					v = TITLE_KEYS[ k ]( title );
				} else if ( k === 'location_h1' && ! p ) {
					v = title ? 'NDIS Support Services in ' + title : '';
				} else if ( k === 'location_cta' && ! p ) {
					v = title ? 'Request support in ' + title : 'Request support';
				} else if ( k === 'search_count' ) {
					v = 'Type a word or two above to search services, homes, resources and more.';
				} else if ( p && typeof p[ k ] === 'string' ) {
					v = p[ k ];
				}
				out[ attr ] = v;
			} );
			return out;
		};
	}

	function bindSources() {
		[ 'brightpath/parity', 'brightpath/derived' ].forEach( function ( name ) {
			var def = {
				name: name,
				label: name === 'brightpath/parity' ? 'Bright Path design field' : 'Bright Path derived field',
				usesContext: [ 'postId', 'postType' ],
				getValues: makeGetValues( name ),
				canUserEditValue: function () { return false; }
			};
			try {
				if ( wp.blocks.unregisterBlockBindingsSource ) { wp.blocks.unregisterBlockBindingsSource( name ); }
			} catch ( e ) {}
			try { wp.blocks.registerBlockBindingsSource( def ); } catch ( e ) {}
		} );
	}
	bindSources();

	/* ------------------------------------------------------------------ *
	 * 2. Query-loop card text, same conditions as inc/design-parity.php.
	 * ------------------------------------------------------------------ */
	function inLoop( ctx ) {
		var cur = data.select( 'core/editor' ).getCurrentPostId();
		return ctx && ctx.postId && String( ctx.postId ) !== String( cur );
	}
	function isFront() {
		return String( data.select( 'core/editor' ).getCurrentPostId() ) === String( cfg.frontId );
	}

	function usePar( ctx ) {
		return data.useSelect( function ( select ) { return rec( select, ctx.postType, ctx.postId ); }, [ ctx.postType, ctx.postId ] );
	}

	function TitleOverride( p ) {
		var props = p.blockProps;
		var bp = be.useBlockProps();
		var Tag = 'h' + ( props.attributes.level || 2 );
		var href = props.attributes.isLink ? ( data.select( 'core' ).getEntityRecord( 'postType', props.context.postType, props.context.postId ) || {} ).link : null;
		return el( Tag, bp, href ? el( 'a', { href: href, target: props.attributes.linkTarget || '_self', rel: props.attributes.rel || '' }, p.text ) : p.text );
	}
	function CardTitle( p ) {
		var par = usePar( p.blockProps.context );
		if ( ! ( par && par.card_title ) ) { return el( p.base, p.blockProps ); }
		return el( TitleOverride, { blockProps: p.blockProps, text: par.card_title } );
	}

	function ServiceExcerptOverride( p ) {
		var bp = be.useBlockProps();
		return el( Fragment, null,
			el( 'div', bp, el( 'p', { className: 'wp-block-post-excerpt__excerpt' }, p.text ) ),
			el( 'p', { className: 'card__foot' }, el( 'a', { className: 'card__arrow bp-link-arrow', tabIndex: -1, 'aria-hidden': 'true' }, 'Learn more' ) )
		);
	}
	function JobExcerptOverride( p ) {
		var bp = be.useBlockProps();
		bp = Object.assign( {}, bp, { className: String( bp.className || '' ).split( ' ' ).filter( function ( c ) { return c !== 'card__text' && c !== 'wp-block-post-excerpt'; } ).concat( [ 'job-meta', 'card__meta' ] ).join( ' ' ) } );
		return el( Fragment, null,
			el( 'p', bp, p.text ),
			el( 'p', { className: 'card__cta' }, el( 'a', { className: 'bp-btn-sm is-style-outline', tabIndex: -1, 'aria-hidden': 'true' }, 'View role' ) )
		);
	}
	function CardExcerpt( p ) {
		var props = p.blockProps;
		var par = usePar( props.context );
		var type = props.context.postType;
		if ( type === 'service' && par && par.card_text ) { return el( ServiceExcerptOverride, { text: par.card_text } ); }
		if ( type === 'job' && par ) { return el( JobExcerptOverride, { text: par.job_meta || '' } ); }
		return el( p.base, props );
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'brightpath/parity-b', function ( BlockEdit ) {
		return function ( props ) {
			var ctx = props.context;
			if ( props.name === 'core/post-title' && ctx && ctx.postType === 'service' && isFront() && inLoop( ctx ) ) {
				return el( CardTitle, { base: BlockEdit, blockProps: props } );
			}
			if ( props.name === 'core/post-excerpt' && ctx && ( ctx.postType === 'service' || ctx.postType === 'job' ) && inLoop( ctx ) ) {
				return el( CardExcerpt, { base: BlockEdit, blockProps: props } );
			}
			return el( BlockEdit, props );
		};
	} );
} )( window.wp );

/* ------------------------------------------------------------------ *
 * 3. FAQ search box. The front script (assets/js/parity.js) inserts
 *    .bp-faq-search into the DOM when the page holds 10+ accordion items.
 *    It is not block content, so the canvas gets the same inert static
 *    element, injected next to (never inside) block DOM and never stored.
 * ------------------------------------------------------------------ */
( function ( wp ) {
	if ( ! wp || ! wp.data ) { return; }
	var HTML = '<label class="field__label" for="faq-search-ed">Search questions</label>' +
		'<input class="input" type="search" id="faq-search-ed" tabindex="-1" placeholder="Try &quot;rent&quot;, &quot;cancellation&quot; or &quot;worker&quot;">' +
		'<p class="field__hint"><strong data-faq-count>0</strong> questions shown</p>';

	function sync() {
		var frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		var doc = frame && frame.contentDocument;
		if ( ! doc || ! doc.body ) { return; }
		var root = doc.querySelector( '.is-root-container' );
		var have = doc.querySelector( '.bp-faq-search[data-bp-editor-injected]' );
		var items = root ? root.querySelectorAll( '.wp-block-accordion-item' ) : [];
		if ( ! root || items.length < 10 ) { if ( have ) { have.remove(); } return; }
		var first = items[ 0 ].closest( '.wp-block-accordion' );
		if ( ! first || ! first.parentElement ) { return; }
		var anchor = first;
		var prev = first.previousElementSibling;
		if ( prev && /^H2$/.test( prev.tagName ) ) { anchor = prev; }
		else if ( prev && prev.querySelector && prev.querySelector( ':scope > h2' ) ) { anchor = prev; }
		var chips = anchor.parentNode.querySelector( ':scope > .chip-row' );
		if ( chips && ( chips.compareDocumentPosition( anchor ) & Node.DOCUMENT_POSITION_FOLLOWING ) ) { anchor = chips; }
		if ( have && have.nextElementSibling === anchor ) {
			var c = have.querySelector( '[data-faq-count]' );
			if ( c && c.textContent !== String( items.length ) ) { c.textContent = items.length; }
			return;
		}
		if ( have ) { have.remove(); }
		var wrap = doc.createElement( 'div' );
		wrap.className = 'field bp-faq-search';
		wrap.setAttribute( 'data-bp-editor-injected', '' );
		wrap.setAttribute( 'contenteditable', 'false' );
		wrap.setAttribute( 'inert', '' );
		wrap.innerHTML = HTML;
		wrap.querySelector( '[data-faq-count]' ).textContent = items.length;
		anchor.parentNode.insertBefore( wrap, anchor );
	}
	wp.data.subscribe( sync );
	setInterval( sync, 800 );
} )( window.wp );

/* ------------------------------------------------------------------ *
 * 4. Query-loop <li> classes. On the front every post <li> carries
 *    <type> / type-<type> / status-publish / hentry (+ taxonomy classes) that
 *    the card CSS keys on (li.team_member, .type-team_member > .bp-panel ...).
 *    The editor's <li>s (the editable one and the preview copies) only have
 *    `wp-block-post`, so add the type classes from the enclosing core/query
 *    block. DOM-only classes: not block attributes, never saved.
 * ------------------------------------------------------------------ */
( function ( wp ) {
	if ( ! wp || ! wp.data ) { return; }
	function sync() {
		var frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		var doc = frame && frame.contentDocument;
		if ( ! doc || ! doc.body ) { return; }
		var be = wp.data.select( 'core/block-editor' );
		var uls = doc.querySelectorAll( 'ul.wp-block-post-template[data-block]' );
		for ( var i = 0; i < uls.length; i++ ) {
			var ul = uls[ i ];
			var cid = ul.getAttribute( 'data-block' );
			var parents = be.getBlockParents( cid ) || [];
			var type = '';
			for ( var j = parents.length - 1; j >= 0 && ! type; j-- ) {
				var b = be.getBlock( parents[ j ] );
				if ( b && b.name === 'core/query' && b.attributes.query ) { type = b.attributes.query.postType || ''; }
			}
			if ( ! type || type === 'post' ) { continue; }
			var want = [ type, 'type-' + type, 'status-publish', 'hentry' ];
			for ( var k = 0; k < ul.children.length; k++ ) {
				var li = ul.children[ k ];
				if ( li.tagName !== 'LI' ) { continue; }
				for ( var m = 0; m < want.length; m++ ) { if ( ! li.classList.contains( want[ m ] ) ) { li.classList.add( want[ m ] ); } }
			}
		}
	}
	wp.data.subscribe( sync );
	setInterval( sync, 700 );
} )( window.wp );
