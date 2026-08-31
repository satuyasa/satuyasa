/**
 * Toggle tombol wishlist (ikon hati) di listing produk, single product,
 * dan tab Wishlist My Account. Hanya di-enqueue untuk user yang sudah
 * login (lihat aksara_marketplace_enqueue_assets()) karena wishlist
 * memang fitur khusus akun (lihat class-account-endpoints.php).
 */
( function () {
	'use strict';

	function toggle( button ) {
		var config = window.aksaraWishlist;
		if ( ! config ) {
			return;
		}

		button.disabled = true;

		fetch( config.restUrl + '/wishlist/toggle', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( { product_id: button.dataset.productId } ),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				var inWishlist = !! data.in_wishlist;

				// Glif ikut berubah bentuk (hati penuh vs kosong), bukan cuma
				// class untuk warna: di palet monokrom warna tidak bisa jadi
				// satu-satunya pembeda status. aria-pressed menyampaikan hal
				// yang sama ke screen reader.
				button.classList.toggle( 'is-active', inWishlist );
				button.setAttribute( 'aria-pressed', inWishlist ? 'true' : 'false' );
				button.innerHTML = inWishlist ? '&hearts;' : '&#9825;';

				// Di tab Wishlist My Account, hapus kartunya langsung dari layar
				// begitu di-unwishlist supaya user tidak perlu reload halaman.
				if ( ! data.in_wishlist && button.closest( '.aksara-wishlist-grid' ) ) {
					var card = button.closest( '.asset-card' );
					if ( card ) {
						card.remove();
					}
				}
			} )
			.finally( function () {
				button.disabled = false;
			} );
	}

	document.addEventListener( 'click', function ( evt ) {
		var button = evt.target.closest( '.aksara-wishlist-toggle' );
		if ( ! button ) {
			return;
		}
		evt.preventDefault();
		toggle( button );
	} );
} )();
