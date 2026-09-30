// Open links to other sites in a new tab.
document.querySelectorAll( 'a[href^="http"]' ).forEach( function ( link ) {
	if ( ! link.hostname || link.hostname === window.location.hostname ) {
		return;
	}
	link.setAttribute( 'target', '_blank' );
	var rel = ( link.getAttribute( 'rel' ) || '' ).split( /\s+/ ).filter( Boolean );
	if ( rel.indexOf( 'noopener' ) === -1 ) {
		rel.push( 'noopener' );
	}
	link.setAttribute( 'rel', rel.join( ' ' ) );
} );
