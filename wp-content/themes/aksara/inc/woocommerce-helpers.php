<?php
/**
 * Helper tema untuk terintegrasi dengan WooCommerce & plugin Aksara Marketplace.
 *
 * Semua akses ke class plugin dijaga dengan class_exists()/function_exists()
 * supaya tema tidak fatal error kalau plugin Aksara Marketplace kebetulan
 * nonaktif — tema tetap tampil (hanya kehilangan fitur spesifik marketplace).
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ambil produk berdasarkan slug product_type kustom (font/canva_template/canva_element).
 *
 * @param string $type  Slug product_type.
 * @param int    $limit Jumlah maksimal.
 * @return WP_Query
 */
function aksara_query_products_by_type( $type, $limit = 12 ) {
	return new WP_Query( array(
		'post_type'      => 'product',
		'posts_per_page' => $limit,
		'post_status'    => 'publish',
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $type,
			),
		),
	) );
}

/**
 * Hitung jumlah produk publish untuk satu product_type — dipakai di hero stats.
 *
 * Di-cache 1 jam: tanpa ini, tiap kali Home dibuka menjalankan 3 WP_Query
 * penuh (font/canva_template/canva_element) hanya untuk 3 angka di hero
 * yang jarang berubah drastis dalam hitungan menit.
 *
 * @param string $type Slug product_type.
 * @return int
 */
function aksara_count_products_by_type( $type ) {
	$cache_key = 'aksara_product_count_' . $type;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$query = aksara_query_products_by_type( $type, -1 );
	$count = (int) $query->found_posts;
	wp_reset_postdata();

	set_transient( $cache_key, $count, HOUR_IN_SECONDS );

	return $count;
}

/**
 * Bersihkan cache jumlah produk begitu ada produk yang disimpan/dihapus —
 * lebih murah daripada menghitung ulang tiap request, tapi tetap akurat
 * dalam hitungan detik setelah admin publish/unpublish produk.
 *
 * @param int $post_id ID post yang disimpan.
 */
function aksara_flush_product_count_cache( $post_id ) {
	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}
	foreach ( array( 'font', 'canva_template', 'canva_element' ) as $type ) {
		delete_transient( 'aksara_product_count_' . $type );
	}
}
add_action( 'save_post_product', 'aksara_flush_product_count_cache' );
add_action( 'trashed_post', 'aksara_flush_product_count_cache' );
add_action( 'deleted_post', 'aksara_flush_product_count_cache' );

/**
 * Tampilkan info tambahan di ringkasan single product, sesuai jenisnya:
 * jumlah style untuk Font, dimensi untuk Canva Template/Element.
 *
 * Tautan Canva (`_aksara_canva_link`) SENGAJA TIDAK ditampilkan di sini —
 * itu adalah aset berbayar yang baru boleh diberikan setelah pembelian
 * lunas. Pengiriman aman ditangani lewat token unduh (Fase 3, lihat
 * My Account > Unduhan Saya) setelah order selesai dibayar; menampilkannya
 * di halaman publik akan membocorkannya sebelum dibeli.
 */
function aksara_render_product_meta() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	echo '<div class="aksara-product-meta">';

	if ( $product instanceof WC_Product_Font && class_exists( 'Aksara_Font_Styles_Repository' ) ) {
		$styles = Aksara_Font_Styles_Repository::get_by_product( $product->get_id() );
		printf(
			'<span>%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: jumlah style. */
					_n( '%d style', '%d styles', count( $styles ), 'aksara' ),
					count( $styles )
				)
			)
		);
	}

	if ( in_array( $product->get_type(), array( 'canva_template', 'canva_element' ), true ) ) {
		$dimensions = get_post_meta( $product->get_id(), '_aksara_dimensions', true );
		if ( $dimensions ) {
			echo '<span>' . esc_html( $dimensions ) . '</span>';
		}
	}

	$categories = wc_get_product_category_list( $product->get_id() );
	if ( $categories ) {
		echo '<span>' . wp_kses_post( $categories ) . '</span>';
	}

	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'aksara_render_product_meta', 6 );

/**
 * Tombol wishlist di ringkasan single product (setelah tombol beli).
 */
function aksara_render_wishlist_button() {
	global $product;

	if ( ! $product instanceof WC_Product || ! function_exists( 'aksara_wishlist_button' ) ) {
		return;
	}

	aksara_wishlist_button( $product->get_id() );
}
add_action( 'woocommerce_single_product_summary', 'aksara_render_wishlist_button', 35 );

/**
 * Wrapper aman untuk cek apakah sebuah produk bertipe 'font'.
 *
 * @param WC_Product|null $product Produk WooCommerce.
 * @return bool
 */
function aksara_is_font_product( $product ) {
	return $product instanceof WC_Product && 'font' === $product->get_type();
}

/**
 * Cari URL halaman berdasarkan page template kustom yang dipakainya
 * (mis. 'fonts' -> page-templates/template-fonts.php). Halaman itu sendiri
 * dibuat manual oleh admin lewat wp-admin (Pages > Add New > pilih Page
 * Attributes > Template) — fungsi ini hanya mencari URL-nya secara dinamis
 * supaya tidak hardcode slug/page ID di seluruh tema.
 *
 * @param string $slug 'fonts' | 'templates' | 'elements' | 'license'.
 * @return string URL halaman, atau '#' jika halaman belum dibuat.
 */
function aksara_get_listing_url( $slug ) {
	static $request_cache = array();

	if ( isset( $request_cache[ $slug ] ) ) {
		return $request_cache[ $slug ];
	}

	$transient_key = 'aksara_listing_url_' . $slug;
	$cached        = get_transient( $transient_key );
	if ( false !== $cached ) {
		$request_cache[ $slug ] = $cached;
		return $cached;
	}

	$template_file = 'page-templates/template-' . $slug . '.php';

	$pages = get_posts( array(
		'post_type'      => 'page',
		'posts_per_page'   => 1,
		'post_status'      => 'publish',
		'meta_key'         => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'       => $template_file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	) );

	$url = ! empty( $pages ) ? get_permalink( $pages[0] ) : '#';

	// TTL panjang (halaman & template-nya jarang berubah) tapi tetap
	// dibersihkan langsung saat halaman disimpan (lihat hook di bawah),
	// jadi perubahan admin tidak perlu menunggu cache basi.
	set_transient( $transient_key, $url, DAY_IN_SECONDS );
	$request_cache[ $slug ] = $url;

	return $url;
}

/**
 * Bersihkan cache URL listing begitu ada Page yang disimpan — mencegah
 * cache basi kalau admin memindahkan template kustom ke Page lain.
 *
 * @param int $post_id ID post yang disimpan.
 */
function aksara_flush_listing_url_cache( $post_id ) {
	if ( 'page' !== get_post_type( $post_id ) ) {
		return;
	}
	foreach ( array( 'fonts', 'templates', 'elements', 'license' ) as $slug ) {
		delete_transient( 'aksara_listing_url_' . $slug );
	}
}
add_action( 'save_post_page', 'aksara_flush_listing_url_cache' );
