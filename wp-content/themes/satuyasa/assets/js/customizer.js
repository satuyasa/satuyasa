/**
 * Preview langsung perubahan Customizer tanpa reload penuh.
 *
 * @package Satuyasa
 */
( function ( $ ) {
	'use strict';

	wp.customize( 'blogname', function ( value ) {
		value.bind( function ( to ) {
			$( '.site-title a' ).text( to );
		} );
	} );

	wp.customize( 'blogdescription', function ( value ) {
		value.bind( function ( to ) {
			$( '.site-description' ).text( to );
		} );
	} );

	wp.customize( 'satuyasa_hero_title', function ( value ) {
		value.bind( function ( to ) {
			$( '.satuyasa-hero h1' ).text( to );
		} );
	} );

	wp.customize( 'satuyasa_hero_subtitle', function ( value ) {
		value.bind( function ( to ) {
			$( '.satuyasa-hero p' ).text( to );
		} );
	} );

	wp.customize( 'satuyasa_accent_color', function ( value ) {
		value.bind( function ( to ) {
			document.documentElement.style.setProperty( '--satuyasa-color-accent', to );
		} );
	} );
} )( jQuery );
