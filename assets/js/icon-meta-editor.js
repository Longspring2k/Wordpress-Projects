/* Bright Path — per-post card icon (lane I2).
 *
 * 1. Document sidebar panel "Card icon" on service / location / job records:
 *    a visual grid (the same one the block icon picker uses) that stores the
 *    choice in post meta `bp_icon`. Nothing here touches post_content.
 * 2. Canvas: the icon tile of every query-loop card (`.icon-tile` inside a
 *    core/post-template) shows the icon of the post it belongs to, exactly like
 *    the front (inc/icon-meta.php puts `bp-icon--<name>` on the front tile; here
 *    the same `--bp-icon` variable is set on the canvas tile).
 *
 * Meta: '' = type default, 'none' = no icon, otherwise an icon name.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.data || ! wp.plugins ) { return; }
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useLayoutEffect = wp.element.useLayoutEffect;
	var useRef = wp.element.useRef;
	var data = wp.data;
	var C = wp.components;
	var __ = wp.i18n.__;
	var cfg = window.brightpathIconMeta || { types: [], defaults: {} };
	var TYPES = cfg.types || [];
	var DEFAULTS = cfg.defaults || {};

	function resolve( value, type ) {
		if ( value === 'none' ) { return 'none'; }
		if ( value ) { return value; }
		return DEFAULTS[ type ] || '';
	}
	function cssValue( name ) {
		if ( ! name ) { return ''; }
		return name === 'none' ? 'none' : 'var(--bp-icon-' + name + ')';
	}

	/* ------------------------------------------------------------------ *
	 * 1. Canvas: paint the tile of a card with its own post's icon.
	 * ------------------------------------------------------------------ */
	function Anchor( props ) {
		var ref = useRef();
		var value = data.useSelect( function ( select ) {
			var core = select( 'core' );
			core.getEntityRecord( 'postType', props.postType, props.postId ); // triggers the fetch
			var r = core.getEditedEntityRecord( 'postType', props.postType, props.postId );
			return r && r.meta ? r.meta.bp_icon : undefined;
		}, [ props.postType, props.postId ] );
		var css = cssValue( resolve( value, props.postType ) );
		useLayoutEffect( function () {
			var tile = ref.current && ref.current.previousElementSibling;
			if ( ! tile ) { return; }
			if ( css ) { tile.style.setProperty( '--bp-icon', css ); } else { tile.style.removeProperty( '--bp-icon' ); }
			// DOM-only class (never saved): the same "no icon" rule the front uses (fix-d.css).
			tile.classList.toggle( 'bp-icon-none', css === 'none' || css === '' );
		} );
		return el( 'span', { ref: ref, hidden: true, 'aria-hidden': 'true', 'data-bp-icon-anchor': '' } );
	}

	// Innermost HOC (priority 1) so other BlockEdit filters see the block untouched.
	wp.hooks.addFilter( 'editor.BlockEdit', 'brightpath/icon-meta-tile', function ( BlockEdit ) {
		return function ( props ) {
			var ctx = props.context;
			if ( props.name === 'core/paragraph' && ctx && ctx.postId && TYPES.indexOf( ctx.postType ) !== -1 &&
				/(^|\s)icon-tile(\s|$)/.test( props.attributes.className || '' ) ) {
				var cur = data.select( 'core/editor' ).getCurrentPostId();
				if ( String( ctx.postId ) !== String( cur ) ) { // a card of another record = inside a query loop
					return el( Fragment, null, el( BlockEdit, props ), el( Anchor, { postType: ctx.postType, postId: ctx.postId } ) );
				}
			}
			return el( BlockEdit, props );
		};
	}, 1 );

	/* ------------------------------------------------------------------ *
	 * 2. Document sidebar panel.
	 * ------------------------------------------------------------------ */
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! Panel ) { return; }

	function CardIcon() {
		var postType = data.useSelect( function ( s ) { return s( 'core/editor' ).getCurrentPostType(); }, [] );
		if ( TYPES.indexOf( postType ) === -1 ) { return null; }
		return el( CardIconPanel, { postType: postType } );
	}

	function CardIconPanel( props ) {
		var postType = props.postType;
		var st = wp.coreData.useEntityProp( 'postType', postType, 'meta' );
		var meta = st[ 0 ] || {};
		var setMeta = st[ 1 ];
		var value = meta.bp_icon || '';
		var current = resolve( value, postType );
		var ui = window.brightpathIconsUI;
		var def = DEFAULTS[ postType ] || '';
		var icons = ( ui && ui.icons ) || [];
		var hit = icons.filter( function ( i ) { return i.name === current; } )[ 0 ];

		function choose( name ) {
			setMeta( Object.assign( {}, meta, { bp_icon: name === null ? 'none' : name } ) );
		}
		function reset() {
			setMeta( Object.assign( {}, meta, { bp_icon: '' } ) );
		}
		var label = current === 'none' || ! current ? __( 'No icon', 'brightpath' ) : ( hit ? hit.label : current );
		return el( Panel, { name: 'bp-card-icon', title: __( 'Card icon', 'brightpath' ), className: 'bp-card-icon-panel' },
			el( 'p', { className: 'bp-icon-picker__current' },
				hit && ui && ui.swatch ? ui.swatch( hit ) : null,
				el( 'span', null, label + ( ! value && def ? ' ' + __( '(default)', 'brightpath' ) : '' ) )
			),
			ui && ui.IconGrid
				? el( ui.IconGrid, { value: current === 'none' ? null : current, onChange: choose } )
				: el( 'p', { className: 'bp-icon-picker__hint' }, __( 'The icon library is not available.', 'brightpath' ) ),
			value && el( C.Button, { variant: 'link', onClick: reset, style: { marginTop: 8 } }, def ? __( 'Reset to default', 'brightpath' ) : __( 'Clear choice', 'brightpath' ) ),
			el( 'p', { className: 'bp-icon-picker__hint' }, __( 'Shown in this record\u2019s card wherever it is listed (home, archive, related lists). Save the record to publish the change.', 'brightpath' ) )
		);
	}

	wp.plugins.registerPlugin( 'brightpath-card-icon', { render: CardIcon } );
} )( window.wp );
