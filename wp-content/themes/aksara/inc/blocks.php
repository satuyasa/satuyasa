<?php
/**
 * Blok dinamis untuk block theme (FSE).
 *
 * Bagian halaman yang isinya berasal dari query — daftar font, grid Canva,
 * hitungan katalog, halaman lisensi, halaman font Authentype — tidak bisa
 * jadi blok statis, karena isinya tidak ada di dalam konten post. Blok di
 * sini merendernya di server (render_callback), jadi tetap bisa disisipkan
 * lewat inserter seperti blok biasa tapi datanya selalu terkini.
 *
 * Markup-nya SENGAJA memakai class yang sama persis dengan template PHP
 * versi classic (.hero, .cat-grid, .specimen-list, dst.) supaya seluruh
 * CSS yang sudah ada — dan sudah diuji di browser — tetap berlaku tanpa
 * ditulis ulang.
 *
 * Tidak ada build step: blok didaftarkan lewat register_block_type() di
 * PHP, dan sisi editornya memakai assets/js/blocks.js yang ditulis dalam
 * JavaScript biasa memakai global wp.* (tanpa JSX/webpack), konsisten
 * dengan konvensi proyek ini yang tidak memakai toolchain build.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render sebuah template part blok dan kembalikan HTML-nya.
 *
 * @param string $slug Nama berkas di template-parts/blocks/ tanpa ekstensi.
 * @param array  $args Variabel yang diekspos ke template part.
 * @return string
 */
function aksara_render_block_part( $slug, $args = array() ) {
	$file = AKSARA_THEME_DIR . '/template-parts/blocks/' . $slug . '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}

	ob_start();
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- kontrak internal, kunci ditentukan pemanggil di berkas ini saja.
	extract( $args, EXTR_SKIP );
	include $file;
	return (string) ob_get_clean();
}

/**
 * Daftarkan seluruh blok dinamis tema.
 */
function aksara_register_blocks() {
	$blocks = array(
		'aksara/hero' => array(
			'attributes' => array(
				'eyebrow'  => array( 'type' => 'string', 'default' => '' ),
				'headline' => array( 'type' => 'string', 'default' => '' ),
				'subtitle' => array( 'type' => 'string', 'default' => '' ),
			),
		),
		'aksara/category-row' => array( 'attributes' => array() ),
		'aksara/font-list'    => array(
			'attributes' => array(
				'limit' => array( 'type' => 'number', 'default' => 6 ),
			),
		),
		'aksara/asset-grid'   => array(
			'attributes' => array(
				'limit' => array( 'type' => 'number', 'default' => 8 ),
				'type'  => array( 'type' => 'string', 'default' => 'both' ),
			),
		),
		'aksara/font-library'      => array( 'attributes' => array() ),
		'aksara/license-list'      => array( 'attributes' => array() ),
		'aksara/authentype-single' => array( 'attributes' => array() ),
		'aksara/header-actions'    => array( 'attributes' => array() ),
	);

	foreach ( $blocks as $name => $config ) {
		$slug = str_replace( 'aksara/', '', $name );

		register_block_type(
			$name,
			array(
				'api_version'     => 3,
				'attributes'      => $config['attributes'],
				'editor_script'   => 'aksara-blocks',
				'render_callback' => function ( $attributes ) use ( $slug ) {
					return aksara_render_block_part( $slug, array( 'attributes' => (array) $attributes ) );
				},
			)
		);
	}
}
add_action( 'init', 'aksara_register_blocks' );

/**
 * Daftarkan skrip editor. Dipisah dari registrasi blok karena
 * register_block_type() hanya menerima HANDLE yang sudah terdaftar.
 */
function aksara_register_block_editor_assets() {
	wp_register_script(
		'aksara-blocks',
		AKSARA_THEME_URI . '/assets/js/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		AKSARA_THEME_VERSION,
		true
	);
}
add_action( 'init', 'aksara_register_block_editor_assets', 5 );

/**
 * Muat style tema di dalam editor supaya pratinjau blok sama dengan situs.
 */
function aksara_block_editor_styles() {
	wp_enqueue_style( 'aksara-editor', get_stylesheet_uri(), array(), AKSARA_THEME_VERSION );
}
add_action( 'enqueue_block_assets', 'aksara_block_editor_styles' );
