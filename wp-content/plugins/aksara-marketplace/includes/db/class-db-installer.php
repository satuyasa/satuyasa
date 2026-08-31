<?php
/**
 * Migrasi tabel database custom untuk Aksara Marketplace.
 *
 * Skema mengikuti PRD Bagian 5. `aksara_license_tiers` dibuat untuk
 * kelengkapan skema (disebut eksplisit di Breakdown Task Fase 1) meski
 * belum dipakai selama keputusan skema lisensi web masih flat price
 * (lihat Starter Brief Bagian 2) — tabel ini siap dipakai begitu skema
 * tier per-pageview dibutuhkan tanpa migrasi ulang.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_DB_Installer.
 */
class Aksara_DB_Installer {

	const DB_VERSION_OPTION = 'aksara_marketplace_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Jalankan/perbarui migrasi jika versi skema berubah.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Buat/perbarui seluruh tabel custom via dbDelta.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$styles_table   = $wpdb->prefix . 'aksara_font_styles';
		$licenses_table = $wpdb->prefix . 'aksara_font_licenses';
		$tiers_table    = $wpdb->prefix . 'aksara_license_tiers';
		$prices_table   = $wpdb->prefix . 'aksara_style_prices';

		$sql = "CREATE TABLE {$styles_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			style_name VARCHAR(191) NOT NULL,
			font_weight SMALLINT UNSIGNED NOT NULL DEFAULT 400,
			is_italic TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			file_path VARCHAR(255) NOT NULL,
			charset TEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY product_id (product_id)
		) {$charset_collate};

		CREATE TABLE {$licenses_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description LONGTEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};

		CREATE TABLE {$tiers_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			license_id BIGINT UNSIGNED NOT NULL,
			tier_name VARCHAR(191) NOT NULL,
			pageview_limit BIGINT UNSIGNED NULL,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY license_id (license_id)
		) {$charset_collate};

		CREATE TABLE {$prices_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			style_id BIGINT UNSIGNED NOT NULL,
			license_id BIGINT UNSIGNED NOT NULL,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY style_license (style_id, license_id),
			KEY style_id (style_id),
			KEY license_id (license_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		self::maybe_seed_default_licenses();
	}

	/**
	 * Isi 5 jenis lisensi default dari PRD Bagian 4.1 jika tabel masih kosong,
	 * supaya halaman License & UI harga tidak kosong sejak awal.
	 */
	private static function maybe_seed_default_licenses() {
		global $wpdb;

		$licenses_table = $wpdb->prefix . 'aksara_font_licenses';
		$count           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$licenses_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $count > 0 ) {
			return;
		}

		$defaults = array(
			array(
				'name'        => __( 'Desktop', 'aksara-marketplace' ),
				'slug'        => 'desktop',
				'description' => __( 'Untuk digunakan di aplikasi desain seperti Photoshop, Illustrator, dan Microsoft Word yang terpasang di komputer Anda.', 'aksara-marketplace' ),
				'sort_order'  => 1,
			),
			array(
				'name'        => __( 'Web / Webfont', 'aksara-marketplace' ),
				'slug'        => 'web',
				'description' => __( 'Untuk ditanamkan (embed) di situs web sebagai webfont.', 'aksara-marketplace' ),
				'sort_order'  => 2,
			),
			array(
				'name'        => __( 'Aplikasi', 'aksara-marketplace' ),
				'slug'        => 'app',
				'description' => __( 'Untuk disertakan (embedding) dalam aplikasi mobile atau desktop.', 'aksara-marketplace' ),
				'sort_order'  => 3,
			),
			array(
				'name'        => __( 'E-book', 'aksara-marketplace' ),
				'slug'        => 'ebook',
				'description' => __( 'Untuk disertakan dalam berkas e-book yang didistribusikan.', 'aksara-marketplace' ),
				'sort_order'  => 4,
			),
			array(
				'name'        => __( 'Komersial Lanjutan', 'aksara-marketplace' ),
				'slug'        => 'extended',
				'description' => __( 'Untuk merchandise, kemasan produk, dan penggunaan komersial skala besar.', 'aksara-marketplace' ),
				'sort_order'  => 5,
			),
		);

		foreach ( $defaults as $license ) {
			$wpdb->insert( $licenses_table, $license ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
}
