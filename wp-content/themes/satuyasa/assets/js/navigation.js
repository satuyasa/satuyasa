/**
 * Toggle menu navigasi pada tampilan mobile.
 *
 * @package Satuyasa
 */
( function () {
	'use strict';

	var toggle = document.querySelector( '.menu-toggle' );
	var nav = document.getElementById( 'site-navigation' );

	if ( ! toggle || ! nav ) {
		return;
	}

	toggle.addEventListener( 'click', function () {
		var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
		toggle.setAttribute( 'aria-expanded', ! expanded );
		nav.classList.toggle( 'is-open' );
	} );
} )();
