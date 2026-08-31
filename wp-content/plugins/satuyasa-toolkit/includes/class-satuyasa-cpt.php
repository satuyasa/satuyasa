<?php
/**
 * Custom Post Type & Taxonomy: Portofolio.
 *
 * @package Satuyasa_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Satuyasa_CPT.
 */
class Satuyasa_CPT {

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Daftarkan post type "portfolio" dan taxonomy "portfolio_category".
	 */
	public static function register() {

		$labels = array(
			'name'                  => __( 'Portofolio', 'satuyasa-toolkit' ),
			'singular_name'         => __( 'Portofolio', 'satuyasa-toolkit' ),
			'add_new'               => __( 'Tambah Baru', 'satuyasa-toolkit' ),
			'add_new_item'          => __( 'Tambah Portofolio Baru', 'satuyasa-toolkit' ),
			'edit_item'             => __( 'Sunting Portofolio', 'satuyasa-toolkit' ),
			'new_item'              => __( 'Portofolio Baru', 'satuyasa-toolkit' ),
			'view_item'             => __( 'Lihat Portofolio', 'satuyasa-toolkit' ),
			'view_items'            => __( 'Lihat Portofolio', 'satuyasa-toolkit' ),
			'search_items'          => __( 'Cari Portofolio', 'satuyasa-toolkit' ),
			'not_found'             => __( 'Belum ada portofolio.', 'satuyasa-toolkit' ),
			'not_found_in_trash'    => __( 'Tidak ada portofolio di tong sampah.', 'satuyasa-toolkit' ),
			'all_items'             => __( 'Semua Portofolio', 'satuyasa-toolkit' ),
			'menu_name'             => __( 'Portofolio', 'satuyasa-toolkit' ),
			'featured_image'        => __( 'Gambar Utama', 'satuyasa-toolkit' ),
			'set_featured_image'    => __( 'Atur gambar utama', 'satuyasa-toolkit' ),
			'remove_featured_image' => __( 'Hapus gambar utama', 'satuyasa-toolkit' ),
		);

		register_post_type( 'portfolio', array(
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-portfolio',
			'menu_position'      => 5,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'rewrite'            => array( 'slug' => 'portofolio' ),
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		) );

		register_taxonomy( 'portfolio_category', 'portfolio', array(
			'labels'            => array(
				'name'          => __( 'Kategori Portofolio', 'satuyasa-toolkit' ),
				'singular_name' => __( 'Kategori Portofolio', 'satuyasa-toolkit' ),
				'search_items'  => __( 'Cari Kategori', 'satuyasa-toolkit' ),
				'all_items'     => __( 'Semua Kategori', 'satuyasa-toolkit' ),
				'edit_item'     => __( 'Sunting Kategori', 'satuyasa-toolkit' ),
				'update_item'   => __( 'Perbarui Kategori', 'satuyasa-toolkit' ),
				'add_new_item'  => __( 'Tambah Kategori Baru', 'satuyasa-toolkit' ),
				'menu_name'     => __( 'Kategori', 'satuyasa-toolkit' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'kategori-portofolio' ),
		) );
	}
}
