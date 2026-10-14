/* Appearance → Icons: pick an .svg from the Media Library. */
( function ( $ ) {
	$( function () {
		var frame;
		$( '#bp-icon-media' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = wp.media( { title: 'Choose an SVG', button: { text: 'Use this icon' }, multiple: false } );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					$( '#bp-icon-att' ).val( a.id );
					$( '#bp-icon-media-name' ).text( a.filename || a.title || '' );
					if ( ! $( '#bp-icon-label' ).val() && a.title ) {
						$( '#bp-icon-label' ).val( a.title );
					}
				} );
			}
			frame.open();
		} );
	} );
} )( jQuery );
