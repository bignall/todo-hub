/**
 * To-do Hub site: the guides menu and copy buttons.
 */
( function () {
	'use strict';

	// The guides menu starts collapsed on small screens, so the guide comes first.
	var menu = document.querySelector( '.docs-menu' );
	if ( menu && window.matchMedia( '(max-width: 899px)' ).matches ) {
		menu.open = false;
	}

	// Copy buttons.
	document.querySelectorAll( '[data-copy]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var source = document.querySelector( button.getAttribute( 'data-copy' ) );
			if ( ! source || ! navigator.clipboard ) {
				return;
			}
			navigator.clipboard.writeText( source.textContent.trim() ).then( function () {
				button.textContent = 'Copied';
				setTimeout( function () {
					button.textContent = 'Copy';
				}, 1500 );
			} );
		} );
	} );
} )();
