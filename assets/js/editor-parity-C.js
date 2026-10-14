/**
 * Lane C — editability.
 * Core hard-codes `templateLock: "all"` inside core/accordion-item (heading + panel cannot be
 * removed or moved), and the editor sets it through block-list settings, not through an
 * attribute we can strip from content. The owner requires every block to be add/edit/delete-able,
 * so relax that lock in the store for accordion items only. No content is changed.
 */
( function () {
	if ( ! window.wp || ! wp.data ) {
		return;
	}
	var busy = false;
	var relax = function () {
		if ( busy ) {
			return;
		}
		var sel = wp.data.select( "core/block-editor" );
		var ids = sel.getBlocksByName ? sel.getBlocksByName( "core/accordion-item" ) : [];
		if ( ! ids.length ) {
			return;
		}
		var dispatch = wp.data.dispatch( "core/block-editor" );
		busy = true;
		try {
			ids.forEach( function ( id ) {
				var s = sel.getBlockListSettings( id );
				if ( s && s.templateLock ) {
					dispatch.updateBlockListSettings( id, Object.assign( {}, s, { templateLock: false } ) );
				}
			} );
		} finally {
			busy = false;
		}
	};
	wp.data.subscribe( relax, "core/block-editor" );
} )();
