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
 * - Product structured data (JSON-LD: harga, ketersediaan, nama, gambar):
 *   WooCommerce core (WC_Structured_Data, hook wp_footer) sudah mencetaknya
 *   otomatis untuk SEMUA WC_Product — termasuk WC_Product_Font, karena
 *   generator-nya membaca lewat method standar get_price()/is_purchasable()/
 *   dst. yang sudah di-override dengan benar sejak Fase 1. Cek langsung di
 *   "Rich Results Test" Google pada produk yang sudah publish untuk verifikasi.
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

	$description = trim( wp_strip_all_tags( (string) $description ) );
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
