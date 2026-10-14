( function ( wp, cfg ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var slugs = ( cfg && cfg.slugs ) || [ 'contact' ];

	wp.blocks.registerBlockType( 'brightpath/form', {
		edit: function ( props ) {
			var slug = props.attributes.formSlug || 'contact';
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					wp.blockEditor.InspectorControls,
					null,
					el(
						wp.components.PanelBody,
						{ title: __( 'Form', 'brightpath' ) },
						el( wp.components.SelectControl, {
							label: __( 'Form', 'brightpath' ),
							value: slug,
							options: slugs.map( function ( s ) { return { label: s, value: s }; } ),
							onChange: function ( v ) { props.setAttributes( { formSlug: v } ); }
						} )
					)
				),
				el( wp.serverSideRender, { block: 'brightpath/form', attributes: props.attributes } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp, window.brightpathFormBlock );
