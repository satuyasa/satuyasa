<?php
/**
 * Halaman Free Font — adapter ke CPT ath_free_download milik Authentype.
 *
 * Datanya BUKAN milik tema. Authentype sudah punya seluruh sistemnya: CPT
 * ath_free_download, preset lisensi, gerbang email (lead), token unduhan
 * sekali pakai, rate limit per IP, dan honeypot. Tema hanya menyediakan
 * tampilan; tombol unduhnya tetap dirender oleh shortcode plugin supaya
 * seluruh alur keamanan itu tidak pernah diduplikasi di sini.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Apakah fitur free download Authentype tersedia? */
function aksara_free_fonts_available() {
	return post_type_exists( 'ath_free_download' ) && function_exists( 'ath_free_download_types' );
}

/** Baca meta free download lewat helper plugin, dengan cadangan sendiri. */
function aksara_free_meta( $post_id, $key, $default = '' ) {
	if ( function_exists( 'ath_specimen_get_meta' ) ) {
		return ath_specimen_get_meta( $post_id, $key, $default );
	}
	$value = get_post_meta( $post_id, $key, true );
	return ( '' === $value || false === $value || null === $value ) ? $default : $value;
}

/**
 * Query free download.
 *
 * Diurutkan sesuai _ath_free_download_display_order lalu tanggal, sama
 * seperti shortcode plugin — supaya urutan di arsip tema dan di shortcode
 * tidak pernah berbeda dan membingungkan admin yang sudah menyetelnya.
 */
function aksara_query_free_fonts( $limit = 12, $paged = 1, $type = 'font' ) {
	$meta_query = array( 'relation' => 'AND' );
	if ( $type && function_exists( 'ath_free_download_types' ) && array_key_exists( $type, ath_free_download_types() ) ) {
		$meta_query[] = array(
			'key'   => '_ath_free_download_type',
			'value' => $type,
		);
	}

	return new WP_Query( array(
		'post_type'           => 'ath_free_download',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $limit,
		'paged'               => max( 1, (int) $paged ),
		'meta_query'          => count( $meta_query ) > 1 ? $meta_query : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_key'            => '_ath_free_download_display_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'             => array( 'meta_value_num' => 'ASC', 'date' => 'DESC' ),
		'ignore_sticky_posts' => true,
	) );
}

/** URL arsip free download; jatuh ke slug bawaan plugin kalau belum ada. */
function aksara_free_fonts_archive_url() {
	$url = get_post_type_archive_link( 'ath_free_download' );
	return $url ? $url : home_url( '/free-downloads/' );
}

/**
 * Style Authentype milik font berbayar yang ditautkan ke sebuah free download.
 *
 * Free download adalah post tersendiri dan TIDAK punya token preview. Yang
 * punya token adalah ath_font, dan admin bisa menautkan keduanya lewat
 * _ath_free_download_related_font. Kalau tautannya ada, spesimennya bisa
 * dirender sungguhan; kalau tidak, halaman memakai placeholder yang jujur
 * mengaku placeholder (lihat foundry.css) — bukan judul yang dicetak besar
 * seolah-olah itu wujud fontnya.
 *
 * @param int $download_id ID post ath_free_download.
 * @return array{token:string,font_id:int}|null
 */
function aksara_free_font_specimen( $download_id ) {
	if ( function_exists( 'ath_free_download_preview_data' ) ) {
		$preview = ath_free_download_preview_data( $download_id );
		if ( ! empty( $preview['token'] ) && ! empty( $preview['font_id'] ) ) {
			return $preview;
		}
	}
	$font_id = absint( aksara_free_meta( $download_id, '_ath_free_download_related_font', 0 ) );
	if ( ! $font_id || ! function_exists( 'aksara_authentype_styles' ) ) {
		return null;
	}
	$styles = aksara_authentype_styles( $font_id );
	if ( empty( $styles[0]['token'] ) ) {
		return null;
	}
	return array(
		'token'   => (string) $styles[0]['token'],
		'font_id' => $font_id,
	);
}

/** Label tipe yang bisa dibaca manusia. */
function aksara_free_type_label( $type ) {
	if ( ! function_exists( 'ath_free_download_types' ) ) {
		return $type;
	}
	$types = ath_free_download_types();
	return isset( $types[ $type ] ) ? $types[ $type ] : $type;
}

/**
 * Muat aset Foundry HANYA di halaman free download.
 *
 * Prioritas 20, alasan yang sama dengan preload specimen: registrasi handle
 * milik plugin Authentype berjalan di prioritas 10, dan enqueue by-handle
 * diam-diam tidak melakukan apa-apa kalau handle-nya belum terdaftar.
 */
function aksara_free_fonts_assets() {
	if ( ! is_post_type_archive( 'ath_free_download' ) && ! is_singular( 'ath_free_download' ) ) {
		return;
	}

	// JetBrains Mono (chrome) + Inter (body). Keduanya ditetapkan DESIGN3 dan
	// hanya dimuat di sini, jadi halaman lain tidak ikut menanggung requestnya.
	wp_enqueue_style(
		'aksara-foundry-fonts',
		'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'aksara-foundry',
		AKSARA_THEME_URI . '/assets/css/foundry.css',
		array( 'aksara-foundry-fonts' ),
		AKSARA_THEME_VERSION
	);

	if ( function_exists( 'aksara_authentype_enqueue_preview' ) ) {
		aksara_authentype_enqueue_preview();
	}

	/*
	 * Tidak ada skrip tema di sini, dan itu benar — tapi alasan yang dulu
	 * tertulis di baris ini KELIRU. Bunyinya "Authentype 1.0.7 menghormati
	 * data-text-color/data-bg-color langsung". Ia tidak. specimen.js baris
	 * 1259-1260 menimpa keduanya tanpa syarat:
	 *
	 *     root.dataset.textColor = "#111111";
	 *     root.dataset.bgColor   = "#ffffff";
	 *
	 * Kebetulan itu PERSIS warna yang diinginkan halaman free font sekarang
	 * (tinta hitam di atas kertas putih), jadi hasilnya benar walau
	 * penjelasannya salah. Yang dulu butuh skrip penimpa adalah versi GELAP
	 * halaman ini; sesudah arsipnya jadi terang, penimpanya memang tidak
	 * diperlukan lagi.
	 *
	 * Dicatat karena komentar yang salah lebih berbahaya daripada tidak ada
	 * komentar: ia membuat orang berikutnya percaya atribut markup cukup,
	 * lalu bingung kenapa warnanya tidak berubah.
	 */
}
add_action( 'wp_enqueue_scripts', 'aksara_free_fonts_assets', 20 );

/**
 * Batasi shortcode plugin ke SATU item di halaman tunggal.
 *
 * [authentype_free_downloads] tidak punya atribut id — ia hanya menyaring
 * lewat type/font_id. Untuk halaman tunggal kita perlu tepat satu item, dan
 * menulis ulang markup kartunya sendiri bukan pilihan: di dalamnya ada nonce,
 * license fingerprint, honeypot, dan hidden field yang seluruhnya bagian dari
 * kontrak keamanan plugin. Menyalinnya berarti menyalin sesuatu yang bisa
 * berubah saat plugin di-update.
 *
 * Jadi querynya yang dipersempit. get_posts() memakai WP_Query di baliknya,
 * dan pre_get_posts berlaku untuk SEMUA WP_Query, bukan hanya query utama.
 * Cakupannya sengaja dipersempit tiga lapis — hanya saat bendera dipasang,
 * hanya untuk post type ini, dan bendera itu dilepas segera setelah
 * shortcode selesai — supaya tidak ada query lain yang ikut terpengaruh.
 */
function aksara_free_fonts_scope_query( $query ) {
	$only = (int) apply_filters( 'aksara_free_download_only_id', 0 );
	if ( ! $only ) {
		return;
	}
	if ( 'ath_free_download' !== $query->get( 'post_type' ) ) {
		return;
	}
	$query->set( 'post__in', array( $only ) );
	$query->set( 'posts_per_page', 1 );
}
add_action( 'pre_get_posts', 'aksara_free_fonts_scope_query' );

/**
 * Render blok unduhan milik plugin untuk satu free download.
 *
 * @param int $download_id ID post.
 * @return string HTML, atau string kosong kalau plugin tidak menyediakannya.
 */
function aksara_free_download_block( $download_id ) {
	if ( ! shortcode_exists( 'authentype_free_downloads' ) ) {
		return '';
	}

	$only = static function () use ( $download_id ) {
		return $download_id;
	};
	add_filter( 'aksara_free_download_only_id', $only );
	$html = do_shortcode( '[authentype_free_downloads limit="1"]' );
	remove_filter( 'aksara_free_download_only_id', $only );

	return $html;
}

/**
 * Placeholder spesimen versi Foundry.
 *
 * Kembaran gelap dari aksara_specimen_placeholder(). Alasannya sama persis:
 * ini toko huruf, dan nama keluarga font yang dicetak besar dalam font UI
 * bisa membuat pengunjung mengira itulah wujud fontnya. Karena itu ia dibuat
 * terbaca sebagai placeholder — ukurannya jauh di bawah spesimen sungguhan,
 * warnanya --fd-ash, dan selalu ada keterangan apa yang sedang terjadi.
 *
 * @param string $name     Nama free download.
 * @param string $note     Keterangan singkat, sudah diterjemahkan.
 * @param bool   $on_error true kalau ini cadangan untuk canvas yang GAGAL
 *                         render — varian itu disembunyikan CSS sampai
 *                         specimen.js menandai canvas-nya dengan .has-error.
 */
function aksara_foundry_placeholder( $name, $note, $on_error = false ) {
	printf(
		'<span class="foundry-placeholder%1$s" aria-hidden="true"><span class="foundry-placeholder__name">%2$s</span><span class="foundry-placeholder__note">%3$s</span></span>',
		$on_error ? ' foundry-placeholder--on-error' : '',
		esc_html( $name ),
		esc_html( $note )
	);
}

/** Tandai halaman Foundry lewat body class, untuk keperluan CSS & debugging. */
function aksara_free_fonts_body_class( $classes ) {
	if ( is_singular( 'ath_free_download' ) ) {
		$classes[] = 'aksara-free-font-single';
	}
	if ( is_post_type_archive( 'ath_free_download' ) ) {
		$classes[] = 'aksara-free-font-archive';
	}
	return $classes;
}
add_filter( 'body_class', 'aksara_free_fonts_body_class' );
