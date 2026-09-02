<?php
/** Authentype catalog adapter. Authentype remains the sole font authority. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aksara_authentype_available() {
	return post_type_exists( 'ath_font' ) && function_exists( 'ath_specimen_get_styles' );
}

function aksara_query_authentype_fonts( $limit = 20, $paged = 1, $search = '' ) {
	return new WP_Query( array(
		'post_type'           => 'ath_font',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $limit,
		'paged'               => max( 1, (int) $paged ),
		's'                   => sanitize_text_field( (string) $search ),
		'ignore_sticky_posts' => true,
	) );
}

function aksara_authentype_font_count() {
	$counts = wp_count_posts( 'ath_font' );
	return isset( $counts->publish ) ? (int) $counts->publish : 0;
}

function aksara_authentype_linked_product( $font_id ) {
	$product_id = absint( get_post_meta( $font_id, '_ath_linked_product', true ) );
	return $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
}

/**
 * Featured image followed by gallery images from the linked Woo product.
 * Pass 0 as the limit to return the complete WooCommerce gallery.
 */
function aksara_authentype_product_gallery_ids( $font_id, $limit = 3 ) {
	$product = aksara_authentype_linked_product( $font_id );
	if ( ! $product ) {
		return array();
	}

	$image_ids = array_merge(
		array( absint( $product->get_image_id() ) ),
		array_map( 'absint', (array) $product->get_gallery_image_ids() )
	);
	$image_ids = array_values( array_unique( array_filter( $image_ids ) ) );
	$limit = absint( $limit );
	return $limit ? array_slice( $image_ids, 0, $limit ) : $image_ids;
}

function aksara_authentype_product_terms( $product, $taxonomy ) {
	if ( ! $product instanceof WC_Product || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}
	$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
	return is_wp_error( $terms ) ? array() : $terms;
}

function aksara_term_links( $terms ) {
	$links = array();
	foreach ( (array) $terms as $term ) {
		$url = get_term_link( $term );
		if ( ! is_wp_error( $url ) ) {
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
		}
	}
	return implode( ', ', $links );
}

/** Related canonical font records, preferring linked Woo categories. */
function aksara_related_authentype_fonts( $font_id, $limit = 3 ) {
	$product            = aksara_authentype_linked_product( $font_id );
	$linked_product_ids = array();
	if ( $product && $product->get_category_ids() ) {
		$linked_product_ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			'fields'         => 'ids',
			'post__not_in'   => array( $product->get_id() ),
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', $product->get_category_ids() ),
				),
			),
		) );
	}

	$args = array(
		'post_type'           => 'ath_font',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, absint( $limit ) ),
		'post__not_in'        => array( absint( $font_id ) ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( $linked_product_ids ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_ath_linked_product',
				'value'   => array_map( 'strval', $linked_product_ids ),
				'compare' => 'IN',
			),
		);
	}
	$query = new WP_Query( $args );
	if ( ! $query->have_posts() && $linked_product_ids ) {
		unset( $args['meta_query'] );
		$query = new WP_Query( $args );
	}
	return $query;
}

/**
 * Calculate a trustworthy product-level discount from active Woo prices.
 *
 * Variable products may have different discounts per variation. We never
 * compare the minimum regular price with an unrelated minimum sale price;
 * every percentage is calculated within the same variation first.
 */
function aksara_product_discount_data( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return null;
	}

	$percentages = array();
	$priced_count = 0;
	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices( true );
		foreach ( (array) ( $prices['price'] ?? array() ) as $variation_id => $active_raw ) {
			$regular = isset( $prices['regular_price'][ $variation_id ] ) ? (float) $prices['regular_price'][ $variation_id ] : 0.0;
			$active  = (float) $active_raw;
			if ( $regular > 0 && $active > 0 ) {
				$priced_count++;
			}
			if ( $regular > 0 && $active > 0 && $active < $regular ) {
				$percentages[] = (int) round( ( ( $regular - $active ) / $regular ) * 100 );
			}
		}
	} elseif ( $product->is_on_sale() ) {
		$regular = (float) $product->get_regular_price();
		$active  = (float) $product->get_price();
		if ( $regular > 0 && $active > 0 && $active < $regular ) {
			$priced_count = 1;
			$percentages[] = (int) round( ( ( $regular - $active ) / $regular ) * 100 );
		}
	}

	$percentages = array_values( array_filter( $percentages, function ( $value ) {
		return $value > 0 && $value < 100;
	} ) );
	if ( ! $percentages ) {
		return null;
	}

	$minimum = min( $percentages );
	$maximum = max( $percentages );
	$uniform = count( $percentages ) === $priced_count && $minimum === $maximum;
	return array(
		'min'   => $minimum,
		'max'   => $maximum,
		'label' => $uniform
			? sprintf( __( '-%d%%', 'aksara' ), $maximum )
			: sprintf( __( 'Up to %d%% off', 'aksara' ), $maximum ),
	);
}

function aksara_product_discount_badge( $product ) {
	$data = aksara_product_discount_data( $product );
	if ( ! $data ) {
		return '';
	}
	return '<span class="aksara-discount-label">' . esc_html( $data['label'] ) . '</span>';
}

/** Replace Woo's generic "Sale" flash with the audited percentage label. */
function aksara_woocommerce_sale_flash( $html, $post, $product ) {
	$badge = aksara_product_discount_badge( $product );
	return $badge ? $badge : $html;
}
add_filter( 'woocommerce_sale_flash', 'aksara_woocommerce_sale_flash', 10, 3 );

function aksara_authentype_styles( $font_id ) {
	if ( ! function_exists( 'ath_specimen_get_styles' ) ) {
		return array();
	}
	return array_values( array_filter( (array) ath_specimen_get_styles( $font_id ), function ( $style ) {
		return empty( $style['is_package'] );
	} ) );
}

/**
 * Umur nonce preview specimen.
 *
 * Masalahnya: renderNonce ditanam ke dalam HTML halaman. Umur nonce WordPress
 * default 1 hari, dan efektifnya hanya 12-24 jam karena verifikasi menerima
 * tick sekarang + tick sebelumnya. Di situs dengan full-page cache (WP Rocket,
 * LiteSpeed, Varnish, Cloudflare APO) HTML katalog bisa disajikan jauh lebih
 * lama dari itu, sehingga setiap canvas preview gagal sampai cache dibersihkan
 * manual. TANPA page cache, bug ini tidak akan pernah muncul - HTML dibuat
 * baru tiap request dan nonce-nya tidak pernah berumur lebih dari detik.
 *
 * HANYA action preview yang dilonggarkan. Nonce keranjang
 * (ath_specimen_cart) SENGAJA dibiarkan di umur default meski kena masalah
 * cache yang sama, karena ia action yang MENGUBAH STATE: memperpanjangnya
 * berarti memperlebar jendela CSRF add-to-cart. Kalau suatu saat tombol Add
 * to cart terbukti gagal di halaman yang di-cache, tambahkan cabangnya di
 * sini secara sadar - jangan disamaratakan.
 *
 * Kenapa memperpanjang umur nonce PREVIEW aman:
 *   - Nonce ini sudah dicetak di HTML publik pada setiap halaman katalog, jadi
 *     siapa pun yang bisa membuka halaman sudah memilikinya. Ia tidak pernah
 *     rahasia dan tidak melindungi apa pun yang bernilai.
 *   - Endpointnya read-only: ia hanya me-render PNG specimen.
 *   - Endpoint tetap punya pertahanan sendiri yang tidak bergantung nonce:
 *     rate limit per-IP (ath_specimen_render_rate_limit_ok) plus pemeriksaan
 *     bahwa post bertipe ath_font dan berstatus publish.
 * Yang hilang hanyalah jendela replay endpoint gambar publik.
 *
 * PENTING - filter ini harus berlaku untuk PEMBUATAN dan VERIFIKASI sekaligus.
 * Ada dua tempat pembuatan nonce dengan action yang sama: tema (fungsi di
 * bawah) dan plugin Authentype (shortcode-specimen.php). Kalau umur nonce
 * hanya dilonggarkan saat request AJAX, pembuatan tetap memakai umur default
 * sementara verifikasi memakai umur panjang; tick-nya tidak akan pernah cocok
 * dan SEMUA preview langsung gagal. Karena filter nonce_life ini global dan
 * hanya menyaring lewat $action, kedua tempat pembuatan dan ketiga tempat
 * verifikasi otomatis memakai umur yang sama.
 *
 * Catatan versi WordPress: parameter $action baru diteruskan ke filter
 * nonce_life pada WordPress yang wp_nonce_tick()-nya menerima argumen. Pada
 * WordPress lama argumen kedua tidak dikirim, $action tetap '' , dan fungsi
 * ini mengembalikan $life apa adanya - fail-safe: tidak ada yang rusak, tapi
 * bug cache di atas juga belum tertutup. Status dukungan itu dilaporkan di
 * Site Health supaya operator tahu harus menurunkan TTL page cache di bawah
 * 12 jam. Kodenya memeriksa runtime, bukan menebak nomor versi.
 */
function aksara_specimen_nonce_life( $life, $action = '' ) {
	if ( 'ath_specimen_render_preview' !== $action ) {
		return $life;
	}
	$ttl = (int) apply_filters( 'aksara_specimen_nonce_ttl', 30 * DAY_IN_SECONDS );
	return $ttl > 0 ? $ttl : $life;
}
add_filter( 'nonce_life', 'aksara_specimen_nonce_life', 10, 2 );

/**
 * Apakah WordPress ini meneruskan $action ke filter nonce_life?
 *
 * Dijawab dari runtime, bukan dari tebakan nomor versi: kalau wp_nonce_tick()
 * punya parameter, WordPress meneruskan action-nya ke filter.
 */
function aksara_nonce_life_is_action_scoped() {
	static $supported = null;
	if ( null !== $supported ) {
		return $supported;
	}
	$supported = false;
	if ( function_exists( 'wp_nonce_tick' ) ) {
		try {
			$reflection = new ReflectionFunction( 'wp_nonce_tick' );
			$supported  = $reflection->getNumberOfParameters() > 0;
		} catch ( ReflectionException $e ) {
			$supported = false;
		}
	}
	return $supported;
}

/** Laporkan status umur nonce preview di Tools > Site Health > Info. */
function aksara_specimen_debug_information( $info ) {
	$scoped = aksara_nonce_life_is_action_scoped();
	$days   = (int) round( (int) apply_filters( 'aksara_specimen_nonce_ttl', 30 * DAY_IN_SECONDS ) / DAY_IN_SECONDS );

	$info['aksara'] = array(
		'label'  => __( 'Aksara theme', 'aksara' ),
		'fields' => array(
			'specimen_nonce_ttl' => array(
				'label' => __( 'Font preview nonce lifetime', 'aksara' ),
				'value' => $scoped
					/* translators: %d: number of days. */
					? sprintf( _n( '%d day', '%d days', $days, 'aksara' ), $days )
					: __( 'WordPress default (about 1 day)', 'aksara' ),
			),
			'specimen_nonce_scoped' => array(
				'label' => __( 'Page cache safe for font previews', 'aksara' ),
				'value' => $scoped
					? __( 'Yes', 'aksara' )
					: __( 'No — this WordPress version cannot scope nonce lifetime per action. Keep the full-page cache lifetime under 12 hours, or font previews will fail on cached pages.', 'aksara' ),
			),
		),
	);
	return $info;
}
add_filter( 'debug_information', 'aksara_specimen_debug_information' );

/**
 * Placeholder untuk baris specimen yang tidak bisa menampilkan huruf aslinya.
 *
 * Kenapa BUKAN sekadar mencetak nama keluarga font sebesar spesimen aslinya:
 * ini toko huruf. Menampilkan "Honic" dalam font TEMA di kotak yang
 * seharusnya berisi spesimen Honic bisa membuat calon pembeli mengira itulah
 * wujud Honic. Kesan yang percaya diri tapi salah lebih merugikan daripada
 * mengaku terus terang. Karena itu placeholder ini sengaja dibuat terbaca
 * sebagai placeholder: ukurannya jauh di bawah spesimen sungguhan, warnanya
 * --muted (bukan tinta penuh), dan ada keterangan kecil yang menyebut apa
 * yang sedang terjadi.
 *
 * Tetap menampilkan nama keluarganya supaya barisnya tidak jadi kotak kosong
 * dan tetap bisa diklik menuju halaman produknya.
 *
 * @param string $name     Nama keluarga font.
 * @param string $note     Keterangan singkat, sudah diterjemahkan.
 * @param bool   $on_error true kalau ini cadangan untuk canvas yang GAGAL
 *                         render - varian itu disembunyikan CSS sampai
 *                         specimen.js menandai canvas-nya dengan .has-error.
 */
function aksara_specimen_placeholder( $name, $note, $on_error = false ) {
	printf(
		'<span class="sp-specimen-placeholder%1$s" aria-hidden="true"><span class="sp-specimen-placeholder__name">%2$s</span><span class="sp-specimen-placeholder__note">%3$s</span></span>',
		$on_error ? ' sp-specimen-placeholder--on-error' : '',
		esc_html( $name ),
		esc_html( $note )
	);
}

function aksara_authentype_enqueue_preview() {
	static $done = false;
	if ( $done || ! aksara_authentype_available() ) {
		return;
	}
	$done = true;
	wp_enqueue_style( 'authentype-font-specimen' );
	wp_enqueue_script( 'authentype-font-specimen' );
	wp_localize_script( 'authentype-font-specimen', 'AthSpecimen', array(
		'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		'renderNonce' => wp_create_nonce( 'ath_specimen_render_preview' ),
		'i18n'        => array(
			'loading'      => __( 'Loading preview…', 'aksara' ),
			'renderFailed' => __( 'Preview unavailable.', 'aksara' ),
			'failed'       => __( 'Request failed.', 'aksara' ),
		),
	) );
}

function aksara_authentype_archive_url() {
	$url = get_post_type_archive_link( 'ath_font' );
	return $url ? $url : home_url( '/font-shop/' );
}

/**
 * Return WooCommerce products generated by Authentype.
 *
 * The result is cached because resolving every relationship on every shop
 * request causes one metadata query per font family on a large catalog.
 */
function aksara_authentype_linked_product_ids() {
	$cached = get_transient( 'aksara_authentype_linked_product_ids' );
	if ( false !== $cached && is_array( $cached ) ) {
		return array_map( 'absint', $cached );
	}

	$font_ids = get_posts( array(
		'post_type'      => 'ath_font',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$linked = array_filter( array_map( function ( $font_id ) {
		return absint( get_post_meta( $font_id, '_ath_linked_product', true ) );
	}, $font_ids ) );
	$linked = array_values( array_unique( $linked ) );

	set_transient( 'aksara_authentype_linked_product_ids', $linked, HOUR_IN_SECONDS );
	return $linked;
}

function aksara_flush_authentype_product_cache() {
	delete_transient( 'aksara_authentype_linked_product_ids' );
}
add_action( 'save_post_ath_font', 'aksara_flush_authentype_product_cache' );
add_action( 'deleted_post', 'aksara_flush_authentype_product_cache' );
add_action( 'updated_post_meta', function ( $meta_id, $object_id, $meta_key ) {
	if ( '_ath_linked_product' === $meta_key && 'ath_font' === get_post_type( $object_id ) ) {
		aksara_flush_authentype_product_cache();
	}
}, 10, 3 );

/** Keep generated Woo variable products out of public catalog duplicates. */
function aksara_hide_authentype_linked_products( $query ) {
	$is_shop     = function_exists( 'is_shop' ) && is_shop();
	$is_taxonomy = function_exists( 'is_product_taxonomy' ) && is_product_taxonomy();
	if ( is_admin() || ! $query->is_main_query() || ( ! $is_shop && ! $is_taxonomy ) ) {
		return;
	}
	$linked = aksara_authentype_linked_product_ids();
	if ( $linked ) {
		$excluded = array_map( 'absint', (array) $query->get( 'post__not_in' ) );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( $excluded, $linked ) ) ) );
	}
}
add_action( 'pre_get_posts', 'aksara_hide_authentype_linked_products' );

function aksara_authentype_font_for_product( $product_id ) {
	$ids = get_posts( array(
		'post_type'      => 'ath_font',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_ath_linked_product', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => (string) absint( $product_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'no_found_rows'  => true,
	) );
	return ! empty( $ids[0] ) ? (int) $ids[0] : 0;
}

function aksara_redirect_linked_woo_product() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$font_id = aksara_authentype_font_for_product( get_queried_object_id() );
	if ( $font_id ) {
		wp_safe_redirect( get_permalink( $font_id ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'aksara_redirect_linked_woo_product', 5 );
