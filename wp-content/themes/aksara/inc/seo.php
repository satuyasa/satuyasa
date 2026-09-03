<?php
/**
 * SEO dasar: meta description & Open Graph tags.
 *
 * Yang SENGAJA TIDAK ditambahkan di sini karena sudah otomatis ditangani
 * platform tanpa kode tambahan:
 * - Meta <title>: sudah dari add_theme_support('title-tag') di functions.php.
 * - rel="canonical": WordPress core sudah mencetaknya lewat rel_canonical()
 *   (hook wp_head bawaan, aktif sejak WP 4.6).
 * - Sitemap XML: WordPress core (wp-sitemap.xml, aktif sejak WP 5.5) sudah
 *   otomatis mencakup post type publik manapun yang punya archive, termasuk
 *   'product' — font/canva_template/canva_element ikut masuk karena semuanya
 *   cuma term product_type dari post type 'product' yang sama, bukan CPT
 *   terpisah (lihat keputusan arsitektur di Starter Brief Bagian 1).
 * KOREKSI ATAS CATATAN LAMA DI SINI. Baris ini dulu menyatakan Product
 * structured data "sudah otomatis dari WooCommerce core untuk SEMUA
 * WC_Product". Itu benar untuk halaman post type 'product' — dan TIDAK
 * berlaku untuk halaman yang sebenarnya dikunjungi pembeli.
 *
 * WC_Structured_Data mengumpulkan datanya lewat hook
 * woocommerce_single_product_summary, dan hook itu hanya berjalan di dalam
 * template WooCommerce. Halaman font di situs ini adalah CPT 'ath_font'
 * (single-ath_font.php), dan SELURUH daftar menaut ke sana —
 * template-parts/font-specimen-row.php bahkan langsung return kalau post
 * type-nya bukan ath_font. Hook itu tidak pernah berjalan di sana, jadi
 * halaman komersial utama tidak punya Product schema sama sekali. Plugin
 * Authentype juga tidak mencetak JSON-LD apa pun (dicari 'ld+json' dan
 * 'schema.org' di seluruh plugin: nihil).
 *
 * Karena itu aksara_font_structured_data() di bawah mencetaknya sendiri,
 * KHUSUS untuk ath_font, dan sengaja tidak menyentuh halaman product supaya
 * tidak menghasilkan dua blok Product yang saling bertabrakan.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cetak <meta name="description">. WordPress core tidak menyediakan ini
 * secara default (biasanya tugas plugin SEO) — diambil dari short
 * description produk, excerpt post, deskripsi arsip, atau tagline situs.
 */
function aksara_meta_description() {
	$description = '';

	/*
	 * strip_shortcodes() sebelum apa pun: get_the_content() mengembalikan isi
	 * MENTAH, shortcode-nya belum dijalankan, dan wp_strip_all_tags() tidak
	 * menghapusnya karena shortcode bukan tag HTML. Tanpa ini, halaman yang
	 * isinya "[authentype_font_specimen id=12]" menghasilkan meta description
	 * berbunyi persis begitu — teks yang tampil di hasil pencarian Google.
	 */
	if ( is_singular( 'ath_font' ) ) {
		$description = has_excerpt() ? get_the_excerpt() : get_the_content();
		if ( ! $description && function_exists( 'aksara_authentype_styles' ) ) {
			$description = sprintf(
				/* translators: %s: font family name. */
				__( 'Preview styles, licenses and prices for the %s font family.', 'aksara' ),
				get_the_title()
			);
		}
	} elseif ( is_singular( 'product' ) ) {
		$product = wc_get_product( get_the_ID() );
		if ( $product ) {
			$description = $product->get_short_description();
			if ( ! $description ) {
				$description = $product->get_description();
			}
		}
	} elseif ( is_singular() ) {
		$description = has_excerpt() ? get_the_excerpt() : get_the_content();
	} elseif ( is_front_page() ) {
		$description = get_bloginfo( 'description' );
	} elseif ( is_category() || is_tax() || is_post_type_archive() ) {
		$description = get_the_archive_description();
	}

	$description = trim( wp_strip_all_tags( strip_shortcodes( (string) $description ) ) );
	if ( ! $description ) {
		return;
	}

	$description = wp_trim_words( $description, 30, '…' );

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
}
add_action( 'wp_head', 'aksara_meta_description', 1 );

/**
 * Cetak tag Open Graph dasar untuk preview yang layak saat halaman
 * dibagikan (WhatsApp, media sosial, dst.) — bukan bagian dari checklist
 * SEO teknis PRD, tapi bagian tak terpisahkan dari "SEO" yang sering
 * dilupakan padahal murah untuk diimplementasikan.
 */
function aksara_open_graph_tags() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$title = wp_get_document_title();
	$url   = aksara_get_current_url();
	$type  = ( is_singular( 'product' ) || is_singular( 'ath_font' ) ) ? 'product' : ( is_singular() ? 'article' : 'website' );

	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );

	$image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( get_the_ID(), 'large' );
	} elseif ( has_custom_logo() ) {
		$image = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'medium' );
	}
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
	}

	/*
	 * Twitter/X tidak membaca og:* sendirian — tanpa twitter:card ia menampilkan
	 * tautan polos tanpa gambar. summary_large_image dipakai kalau ada gambar,
	 * karena spesimen font adalah alasan orang mengklik.
	 */
	printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );

	if ( is_singular( 'product' ) ) {
		$product = wc_get_product( get_the_ID() );
		if ( $product ) {
			printf( '<meta property="product:price:amount" content="%s">' . "\n", esc_attr( $product->get_price() ) );
			printf( '<meta property="product:price:currency" content="%s">' . "\n", esc_attr( get_woocommerce_currency() ) );
		}
	} elseif ( is_singular( 'ath_font' ) && function_exists( 'aksara_authentype_linked_product' ) ) {
		$product = aksara_authentype_linked_product( get_the_ID() );
		if ( $product && '' !== $product->get_price() ) {
			printf( '<meta property="product:price:amount" content="%s">' . "\n", esc_attr( $product->get_price() ) );
			printf( '<meta property="product:price:currency" content="%s">' . "\n", esc_attr( get_woocommerce_currency() ) );
		}
	}
}
add_action( 'wp_head', 'aksara_open_graph_tags', 2 );

/**
 * URL absolut halaman saat ini, dipakai untuk og:url.
 *
 * @return string
 */
function aksara_get_current_url() {
	if ( is_singular() ) {
		$canonical = wp_get_canonical_url();
		if ( $canonical ) {
			return $canonical;
		}
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	return home_url( $request_uri );
}

/**
 * Product + BreadcrumbList JSON-LD untuk halaman font (CPT ath_font).
 *
 * Kenapa harus ditulis sendiri, bukan mengandalkan WooCommerce, dijelaskan di
 * docblock berkas ini. Ringkasnya: WC_Structured_Data hanya bekerja di halaman
 * post type 'product', sedangkan halaman font di situs ini ath_font.
 *
 * HARGA DIAMBIL DARI PRODUK TERTAUT, bukan dari post ath_font itu sendiri.
 * ath_font tidak punya harga; yang punya adalah produk WooCommerce yang
 * ditautkan lewat _ath_linked_product. Kalau tautannya belum diisi, blok ini
 * TIDAK dicetak sama sekali — schema Product tanpa penawaran lebih buruk
 * daripada tidak ada schema, karena Google akan menandainya sebagai tidak
 * lengkap.
 *
 * Produk variabel memakai AggregateOffer dengan lowPrice/highPrice. Font
 * selalu variabel (gaya x lisensi), dan memaksakan satu angka Offer di sana
 * berarti mengiklankan harga yang belum tentu bisa dibeli.
 */
function aksara_font_structured_data() {
	if ( ! is_singular( 'ath_font' ) || ! function_exists( 'aksara_authentype_linked_product' ) ) {
		return;
	}

	$font_id = get_the_ID();
	$product = aksara_authentype_linked_product( $font_id );
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IDR';
	$offers   = null;

	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices( false );
		$active = array_filter( array_map( 'floatval', (array) ( $prices['price'] ?? array() ) ) );
		if ( $active ) {
			$offers = array(
				'@type'         => 'AggregateOffer',
				'priceCurrency' => $currency,
				'lowPrice'      => (string) min( $active ),
				'highPrice'     => (string) max( $active ),
				'offerCount'    => count( $active ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => get_permalink( $font_id ),
			);
		}
	} elseif ( '' !== $product->get_price() ) {
		$offers = array(
			'@type'         => 'Offer',
			'priceCurrency' => $currency,
			'price'         => (string) $product->get_price(),
			'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			'url'           => get_permalink( $font_id ),
		);
	}

	if ( ! $offers ) {
		return;
	}

	$data = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => get_the_title( $font_id ),
		'url'         => get_permalink( $font_id ),
		'offers'      => $offers,
	);

	$description = get_the_excerpt( $font_id );
	if ( $description ) {
		$data['description'] = wp_strip_all_tags( strip_shortcodes( $description ) );
	}

	$image = get_the_post_thumbnail_url( $font_id, 'aksara-preview-xl' );
	if ( $image ) {
		$data['image'] = $image;
	}

	$sku = $product->get_sku();
	if ( $sku ) {
		$data['sku'] = $sku;
	}

	// Rating hanya dicetak kalau BENAR-BENAR ada ulasan. AggregateRating dengan
	// reviewCount 0 adalah pelanggaran pedoman Google, bukan sekadar kosong.
	$rating_count = (int) $product->get_rating_count();
	if ( $rating_count > 0 ) {
		$data['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) $product->get_average_rating(),
			'reviewCount' => $rating_count,
		);
	}

	aksara_print_jsonld( $data );

	/*
	 * BreadcrumbList. Remah roti visualnya sudah lama ada di single-ath_font.php
	 * tapi tidak pernah punya padanan terstruktur, jadi Google menampilkan URL
	 * mentah alih-alih jalur "Fonts > Nama Font" di hasil pencarian.
	 */
	aksara_print_jsonld( array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Fonts', 'aksara' ),
				'item'     => function_exists( 'aksara_authentype_archive_url' ) ? aksara_authentype_archive_url() : home_url( '/' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => get_the_title( $font_id ),
			),
		),
	) );
}
add_action( 'wp_footer', 'aksara_font_structured_data' );

/**
 * Cetak satu blok JSON-LD.
 *
 * wp_json_encode dengan UNESCAPED_SLASHES/UNICODE supaya URL dan huruf non-ASCII
 * tidak berubah jadi rentetan escape yang menyulitkan saat diperiksa manual di
 * Rich Results Test.
 *
 * @param array $data Struktur data.
 */
function aksara_print_jsonld( $data ) {
	echo '<script type="application/ld+json">'
		. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>' . "\n";
}

/**
 * rel="canonical" untuk halaman ARSIP, dan noindex untuk tampilan tersaring.
 *
 * KENAPA PERLU. rel_canonical() milik WordPress core dibuka dengan
 * "if ( ! is_singular() ) return;" — arsip TIDAK pernah dapat canonical.
 * Sementara tema ini punya empat parameter URL yang semuanya bisa di-crawl:
 *
 *     ?q=          pencarian di arsip font
 *     ?type=       penyaring tipe di arsip free download
 *     ?kategori=   penyaring kategori di halaman Template & Element
 *     /page/N/     paginasi
 *
 * Tanpa canonical, setiap kombinasi adalah halaman terpisah di mata mesin
 * pencari, dengan isi yang sebagian besar sama. Yang dirugikan bukan cuma
 * duplikasi: crawl budget habis di kombinasi filter alih-alih di halaman font
 * yang sebenarnya ingin diperingkat.
 *
 * Paginasi TETAP dapat canonical ke dirinya sendiri, bukan ke halaman 1.
 * Menyatukan semua halaman ke halaman 1 akan menyembunyikan font di halaman 2
 * dan seterusnya dari indeks — persis kebalikan dari yang diinginkan toko
 * dengan katalog panjang.
 *
 * Hasil PENCARIAN diberi noindex: ia dibuat pengunjung, jumlahnya tak
 * terbatas, dan tidak satu pun layak masuk indeks.
 */
function aksara_archive_canonical() {
	if ( is_singular() || is_admin() || is_feed() ) {
		return;
	}

	if ( is_search() || ( is_post_type_archive( 'ath_font' ) && ! empty( $_GET['q'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<meta name="robots" content="noindex,follow">' . "\n";
		return;
	}

	$canonical = '';
	if ( is_post_type_archive() ) {
		$canonical = get_post_type_archive_link( get_post_type() ?: get_query_var( 'post_type' ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$canonical = get_term_link( get_queried_object() );
	} elseif ( is_home() ) {
		$canonical = get_permalink( (int) get_option( 'page_for_posts' ) );
	}

	if ( ! $canonical || is_wp_error( $canonical ) ) {
		return;
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged > 1 ) {
		$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
	}

	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
}
add_action( 'wp_head', 'aksara_archive_canonical', 3 );
