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
			esc_html__( 'oleh', 'aksara' ),
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
				printf( '<span class="cat-links">%1$s %2$s</span> ', esc_html__( 'Kategori:', 'aksara' ), $categories_list );
			}
		}

		edit_post_link(
			sprintf(
				/* translators: %s: nama artikel. */
				esc_html__( 'Sunting %s', 'aksara' ),
				the_title( '<span class="screen-reader-text">"', '"</span>', false )
			),
			'<span class="edit-link">',
			'</span>'
		);
	}
}
