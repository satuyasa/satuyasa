<?php
/**
 * Fungsi bantuan tampilan (template tags) untuk konten blog/halaman.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'aksara_posted_on' ) ) {
	/**
	 * Tampilkan tanggal terbit.
	 */
	function aksara_posted_on() {
		printf(
			'<span class="posted-on"><a href="%1$s" rel="bookmark"><time class="entry-date published" datetime="%2$s">%3$s</time></a></span> ',
			esc_url( get_permalink() ),
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() )
		);
	}
}

if ( ! function_exists( 'aksara_posted_by' ) ) {
	/**
	 * Tampilkan penulis.
	 */
	function aksara_posted_by() {
		printf(
			'<span class="byline">%1$s <a href="%2$s">%3$s</a></span>',
			esc_html__( 'by', 'aksara' ),
			esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
			esc_html( get_the_author() )
		);
	}
}

if ( ! function_exists( 'aksara_entry_footer' ) ) {
	/**
	 * Tampilkan kategori, tag, dan tautan sunting untuk artikel blog.
	 */
	function aksara_entry_footer() {
		if ( 'post' === get_post_type() ) {
			$categories_list = get_the_category_list( esc_html__( ', ', 'aksara' ) );
			if ( $categories_list ) {
				printf( '<span class="cat-links">%1$s %2$s</span> ', esc_html__( 'Categories:', 'aksara' ), $categories_list );
			}
		}

		edit_post_link(
			sprintf(
				/* translators: %s: nama artikel. */
				esc_html__( 'Edit %s', 'aksara' ),
				the_title( '<span class="screen-reader-text">"', '"</span>', false )
			),
			'<span class="edit-link">',
			'</span>'
		);
	}
}

if ( ! function_exists( 'aksara_pagination' ) ) {
	/**
	 * Paginasi untuk query SEKUNDER (WP_Query sendiri, bukan query utama).
	 *
	 * KENAPA FUNGSI INI ADA, dan kenapa the_posts_pagination() tidak bisa
	 * dipakai di tempat-tempat itu — dua alasan, keduanya diverifikasi
	 * langsung di sumber WordPress:
	 *
	 * 1. get_the_posts_pagination() DIAM SAMA SEKALI kalau
	 *    $GLOBALS['wp_query']->max_num_pages tidak lebih dari 1. Di Page
	 *    template, query utamanya satu halaman, jadi nilainya 1 dan fungsi
	 *    itu keluar lebih awal — argumen 'total' yang kita kirim bahkan tidak
	 *    pernah dibaca. Akibatnya halaman Canva Template & Canva Element
	 *    memuat 24 produk lalu berhenti: sisanya tidak bisa dijangkau.
	 *
	 * 2. paginate_links() mengambil halaman aktif dari
	 *    get_query_var('paged'), yang di Page template selalu 0 (WordPress
	 *    memakai 'page' di sana). Jadi walau markupnya tercetak, nomor yang
	 *    disorot akan selalu 1.
	 *
	 * Karena itu total DAN current dikirim eksplisit di sini.
	 *
	 * Markupnya sengaja meniru persis keluaran the_posts_pagination()
	 * (nav.navigation.pagination > h2.screen-reader-text + div.nav-links)
	 * supaya CSS .pagination yang sudah ada berlaku tanpa aturan baru — dan
	 * supaya halaman yang memakai fungsi ini tidak terlihat berbeda dari
	 * halaman yang memakai fungsi bawaan.
	 *
	 * @param int   $total   Jumlah halaman (max_num_pages milik query itu).
	 * @param int   $current Halaman aktif.
	 * @param array $args    Tambahan untuk paginate_links(), mis. add_args.
	 */
	function aksara_pagination( $total, $current, $args = array() ) {
		$total   = (int) $total;
		$current = max( 1, (int) $current );

		if ( $total < 2 ) {
			return;
		}

		$links = paginate_links( array_merge( array(
			'total'     => $total,
			'current'   => $current,
			'mid_size'  => 1,
			'prev_text' => __( 'Previous', 'aksara' ),
			'next_text' => __( 'Next', 'aksara' ),
		), $args ) );

		if ( ! $links ) {
			return;
		}

		printf(
			'<nav class="navigation pagination" aria-label="%1$s"><h2 class="screen-reader-text">%2$s</h2><div class="nav-links">%3$s</div></nav>',
			esc_attr__( 'Catalog pages', 'aksara' ),
			esc_html__( 'Catalog navigation', 'aksara' ),
			wp_kses_post( $links )
		);
	}
}
