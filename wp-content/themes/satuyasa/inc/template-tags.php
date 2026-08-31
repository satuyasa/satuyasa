<?php
/**
 * Fungsi bantuan tampilan (template tags) kustom.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'satuyasa_posted_on' ) ) {
	/**
	 * Tampilkan tanggal terbit.
	 */
	function satuyasa_posted_on() {
		$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time>';
		if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
		}

		$time_string = sprintf(
			$time_string,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);

		printf(
			'<span class="posted-on">%1$s</span> ',
			'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
		);
	}
}

if ( ! function_exists( 'satuyasa_posted_by' ) ) {
	/**
	 * Tampilkan penulis.
	 */
	function satuyasa_posted_by() {
		printf(
			'<span class="byline"> %1$s <span class="author vcard"><a class="url fn n" href="%2$s">%3$s</a></span></span>',
			esc_html__( 'oleh', 'satuyasa' ),
			esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
			esc_html( get_the_author() )
		);
	}
}

if ( ! function_exists( 'satuyasa_entry_footer' ) ) {
	/**
	 * Tampilkan kategori, tag, dan tautan sunting.
	 */
	function satuyasa_entry_footer() {
		if ( 'post' === get_post_type() ) {
			$categories_list = get_the_category_list( esc_html__( ', ', 'satuyasa' ) );
			if ( $categories_list ) {
				printf( '<span class="cat-links">%1$s %2$s</span> ', esc_html__( 'Kategori:', 'satuyasa' ), $categories_list );
			}

			$tags_list = get_the_tag_list( '', esc_html__( ', ', 'satuyasa' ) );
			if ( $tags_list && ! is_wp_error( $tags_list ) ) {
				printf( '<span class="tags-links">%1$s %2$s</span>', esc_html__( 'Tag:', 'satuyasa' ), $tags_list );
			}
		}

		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<span class="comments-link">';
			comments_popup_link(
				esc_html__( 'Tinggalkan komentar', 'satuyasa' ),
				esc_html__( '1 Komentar', 'satuyasa' ),
				esc_html__( '% Komentar', 'satuyasa' )
			);
			echo '</span>';
		}

		edit_post_link(
			sprintf(
				/* translators: %s: nama artikel. */
				esc_html__( 'Sunting %s', 'satuyasa' ),
				the_title( '<span class="screen-reader-text">"', '"</span>', false )
			),
			'<span class="edit-link">',
			'</span>'
		);
	}
}
