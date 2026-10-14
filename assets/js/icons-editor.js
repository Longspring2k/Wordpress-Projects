/**
 * Bright Path — block icon picker (lane I1).
 *
 * Adds an "Icon" panel to the block sidebar and an icon button to the block
 * toolbar. It only ever writes the block's className: it swaps the
 * `bp-icon--<name>` token and adds/removes `bp-icon` (icon before the text) or
 * `bp-icon-after` (icon after the text). No locks, no other attributes.
 * The icon list comes from window.brightpathIcons (printed by inc/icons.php).
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.hooks || ! wp.blockEditor ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useMemo = wp.element.useMemo;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var BlockControls = wp.blockEditor.BlockControls;
	var C = wp.components;
	var cfg = window.brightpathIcons || { icons: [], canManage: false, adminUrl: '' };
	var ICONS = cfg.icons || [];
	var BY_NAME = {};
	ICONS.forEach( function ( i ) {
		BY_NAME[ i.name ] = i;
	} );

	// Blocks that get the panel even when they carry no icon yet. Any other
	// block that already has a bp-icon class gets it too.
	var ALLOWED = [
		'core/paragraph', 'core/heading', 'core/group', 'core/button', 'core/list-item', 'core/post-terms',
		'core/post-title', 'core/post-excerpt', 'core/column', 'core/read-more', 'core/post-date',
		'core/post-author', 'core/site-title', 'core/navigation-link', 'core/quote', 'core/cover', 'core/row', 'core/stack',
	];

	function tokens( cn ) {
		return String( cn || '' ).split( /\s+/ ).filter( Boolean );
	}
	function isIconToken( t ) {
		return t === 'bp-icon' || t === 'bp-icon-after' || 0 === t.indexOf( 'bp-icon--' );
	}
	function readState( cn ) {
		var t = tokens( cn );
		var name = null;
		t.forEach( function ( x ) {
			if ( 0 === x.indexOf( 'bp-icon--' ) ) {
				name = x.slice( 9 );
			}
		} );
		var pos = t.indexOf( 'bp-icon-after' ) !== -1 ? 'after' : ( t.indexOf( 'bp-icon' ) !== -1 ? 'before' : null );
		return { name: name, pos: pos };
	}
	function build( cn, name, pos ) {
		var rest = tokens( cn ).filter( function ( x ) {
			return ! isIconToken( x );
		} );
		if ( name ) {
			rest.push( pos === 'after' ? 'bp-icon-after' : 'bp-icon' );
			rest.push( 'bp-icon--' + name );
		}
		return rest.length ? rest.join( ' ' ) : undefined;
	}
	function swatch( icon, extra ) {
		return el( 'span', {
			className: 'bp-icon-swatch' + ( extra ? ' ' + extra : '' ),
			'aria-hidden': 'true',
			style: { WebkitMaskImage: icon.mask, maskImage: icon.mask },
		} );
	}

	/** Reusable grid: also exposed for the per-post "Card icon" picker (lane I2). */
	function IconGrid( props ) {
		var st = useState( '' );
		var q = st[ 0 ];
		var setQ = st[ 1 ];
		var selected = props.value || null;
		var list = useMemo( function () {
			var n = q.trim().toLowerCase();
			return ICONS.filter( function ( i ) {
				return ! n || i.label.toLowerCase().indexOf( n ) !== -1 || i.name.indexOf( n ) !== -1;
			} );
		}, [ q ] );
		var custom = list.filter( function ( i ) {
			return i.custom;
		} );
		var builtin = list.filter( function ( i ) {
			return ! i.custom;
		} );
		function cell( i ) {
			return el( 'button', {
				key: i.name,
				type: 'button',
				className: 'bp-icon-picker__cell' + ( selected === i.name ? ' is-selected' : '' ),
				'aria-label': i.label,
				'aria-pressed': selected === i.name,
				title: i.label,
				'data-icon': i.name,
				onClick: function () {
					props.onChange( i.name );
				},
			}, swatch( i ) );
		}
		var kids = [];
		if ( props.allowNone !== false ) {
			kids.push( el( 'button', {
				key: '__none',
				type: 'button',
				className: 'bp-icon-picker__cell bp-icon-picker__cell--none' + ( ! selected ? ' is-selected' : '' ),
				'aria-pressed': ! selected,
				'data-icon': '',
				onClick: function () {
					props.onChange( null );
				},
			}, __( 'No icon', 'brightpath' ) ) );
		}
		if ( custom.length ) {
			kids.push( el( 'div', { key: '__c', className: 'bp-icon-picker__group' }, __( 'Your icons', 'brightpath' ) ) );
			custom.forEach( function ( i ) {
				kids.push( cell( i ) );
			} );
			kids.push( el( 'div', { key: '__b', className: 'bp-icon-picker__group' }, __( 'Library', 'brightpath' ) ) );
		}
		builtin.forEach( function ( i ) {
			kids.push( cell( i ) );
		} );
		return el( 'div', { className: 'bp-icon-picker' },
			el( C.SearchControl || C.TextControl, {
				className: 'bp-icon-picker__search',
				value: q,
				onChange: setQ,
				label: __( 'Search icons', 'brightpath' ),
				placeholder: __( 'Search icons', 'brightpath' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( 'div', { className: 'bp-icon-picker__grid', role: 'group', 'aria-label': __( 'Icons', 'brightpath' ) }, kids ),
			list.length === 0 ? el( 'p', { className: 'bp-icon-picker__hint' }, __( 'No icon matches.', 'brightpath' ) ) : null
		);
	}
	window.brightpathIconsUI = { IconGrid: IconGrid, icons: ICONS, swatch: swatch };

	function wants( name, cn ) {
		if ( ! wp.blocks.hasBlockSupport( name, 'customClassName', true ) ) {
			return false;
		}
		return ALLOWED.indexOf( name ) !== -1 || !! readState( cn ).pos || !! readState( cn ).name;
	}

	function Panel( props ) {
		var attrs = props.attributes;
		var s = readState( attrs.className );
		var isButton = props.name === 'core/button';
		var icon = s.name ? BY_NAME[ s.name ] : null;
		var pos = s.pos || ( isButton ? 'after' : 'before' );
		function apply( name, p ) {
			props.setAttributes( { className: build( attrs.className, name, p ) } );
		}
		return el( C.PanelBody, { title: __( 'Icon', 'brightpath' ), initialOpen: !! ( s.name || s.pos ), className: 'bp-icon-panel' },
			el( 'div', { className: 'bp-icon-picker__current' },
				icon ? swatch( icon ) : null,
				el( 'span', null, icon ? icon.label : ( s.name ? s.name : __( 'No icon on this block', 'brightpath' ) ) )
			),
			el( IconGrid, {
				value: s.name,
				onChange: function ( n ) {
					apply( n, pos );
				},
			} ),
			s.name ? el( 'div', { className: 'bp-icon-picker__pos' },
				el( C.RadioControl, {
					label: __( 'Icon position', 'brightpath' ),
					selected: pos,
					options: [
						{ label: __( 'Before the text', 'brightpath' ), value: 'before' },
						{ label: __( 'After the text', 'brightpath' ), value: 'after' },
					],
					onChange: function ( p ) {
						apply( s.name, p );
					},
				} )
			) : null,
			cfg.canManage && cfg.adminUrl ? el( 'p', { className: 'bp-icon-picker__hint' },
				__( 'Want your own icon? ', 'brightpath' ),
				el( 'a', { href: cfg.adminUrl, target: '_blank', rel: 'noopener' }, __( 'Add a custom icon', 'brightpath' ) )
			) : null
		);
	}

	function Toolbar( props ) {
		var attrs = props.attributes;
		var s = readState( attrs.className );
		var isButton = props.name === 'core/button';
		var pos = s.pos || ( isButton ? 'after' : 'before' );
		var icon = s.name ? BY_NAME[ s.name ] : null;
		return el( BlockControls, { group: 'other' },
			el( C.ToolbarGroup, null,
				el( C.Dropdown, {
					popoverProps: { placement: 'bottom-start' },
					renderToggle: function ( t ) {
						return el( C.ToolbarButton, {
							className: 'bp-icon-toolbar-btn',
							icon: icon ? swatch( icon ) : 'star-empty',
							label: __( 'Icon', 'brightpath' ),
							onClick: t.onToggle,
							'aria-expanded': t.isOpen,
							isPressed: t.isOpen,
						} );
					},
					renderContent: function ( t ) {
						return el( 'div', { className: 'bp-icon-picker__popover' },
							el( IconGrid, {
								value: s.name,
								onChange: function ( n ) {
									props.setAttributes( { className: build( attrs.className, n, pos ) } );
									t.onClose();
								},
							} )
						);
					},
				} )
			)
		);
	}

	var withIconPicker = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! props.isSelected || ! wants( props.name, props.attributes && props.attributes.className ) ) {
				return el( BlockEdit, props );
			}
			return el( Fragment, null,
				el( BlockEdit, props ),
				el( InspectorControls, null, el( Panel, props ) ),
				el( Toolbar, props )
			);
		};
	}, 'withBrightpathIconPicker' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'brightpath/icon-picker', withIconPicker );
}( window.wp ) );
