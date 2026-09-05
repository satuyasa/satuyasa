/**
 * Admin fixes for the product edit screen.
 *
 * WooCommerce shows and hides the "Product data" panels purely from CSS
 * classes: every group is tagged `show_if_<type>` / `hide_if_<type>`, and
 * meta-boxes-product.js reveals only the ones matching the currently
 * selected product type. Core ships those classes for its own built-in
 * types only, so a custom type slug matches nothing and the panels stay
 * hidden. This file opts our types into the groups they actually need.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $type = $( '#product-type' );

		if ( ! $type.length ) {
			return;
		}

		/*
		 * The price fields live in a group marked `show_if_simple
		 * show_if_external`. Canva Template and Canva Element extend
		 * WC_Product_Simple and are sold at a flat price, so without this
		 * the Regular price field never appears — and an admin simply
		 * cannot put a price on the product. It stays at 0/empty, which
		 * makes it unpurchasable.
		 */
		$( '.options_group.pricing' ).addClass( 'show_if_canva_template show_if_canva_element' );
		$( '.general_options' ).addClass( 'show_if_canva_template show_if_canva_element show_if_font' );
		$( '#general_product_data' ).addClass( 'show_if_canva_template show_if_canva_element show_if_font' );

		// WooCommerce evaluates the classes above on 'change'; fire it once
		// now so the correct panels are visible on first page load too,
		// not only after the admin touches the dropdown.
		$type.trigger( 'change' );

		/*
		 * Font products have no single price — it comes from the style x
		 * license matrix in the Font Styles box below. With every pricing
		 * panel legitimately hidden, the Product data box looks broken
		 * ("where do I type the price?"), so say where the price lives.
		 */
		var $hint = $( '<div class="options_group aksara-type-note"><p></p></div>' ).hide();
		$( '#general_product_data' ).append( $hint );

		function syncHint() {
			var type = $type.val();
			var text = '';

			if ( 'font' === type ) {
				text = window.aksaraAdminProduct ? window.aksaraAdminProduct.fontPriceNote : '';
			}

			$hint.find( 'p' ).text( text );
			$hint.toggle( '' !== text );
		}

		$type.on( 'change', syncHint );
		syncHint();
	} );
}( jQuery ) );
