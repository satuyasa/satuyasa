<?php
/**
 * Render specimen font jadi gambar PNG di sisi server, memakai GD +
 * FreeType — TANPA microservice Python.
 *
 * Ini menutup PRD Bagian 4.3 poin 3: "Untuk mode display (bukan typing
 * tool) seperti card 'A B C...' di listing, gunakan preview image
 * (PNG/SVG) hasil render server-side, bukan font file sama sekali —
 * lebih aman & lebih cepat load."
 *
 * KENAPA GAMBAR HASIL RENDER AMAN DITARUH DI FOLDER PUBLIK:
 * berbeda dari .woff2 subset (yang tetap berisi data outline glyph dan
 * bisa dipasang ulang sebagai font), PNG hanya berisi piksel. Tidak ada
 * file font yang bisa diekstrak darinya. Karena itu specimen disimpan di
 * uploads publik biasa supaya bisa di-cache browser/CDN seperti gambar
 * lain — bukan di folder privat.
 *
 * PEMBAGIAN TUGAS dengan services/font-preview-service/:
 * - Class ini  -> mode DISPLAY (listing, kartu, judul) & fallback.
 *                  Statis, di-cache selamanya, tanpa dependency eksternal.
 * - Microservice -> mode INTERAKTIF (typing tool), teks bebas dari
 *                  pengunjung, butuh subsetting .woff2 sungguhan.
 * Dengan pembagian ini, situs tetap menampilkan font aslinya di sebagian
 * besar halaman meski microservice sedang mati.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Specimen_Image.
 */
class Aksara_Specimen_Image {

	const SUBDIR = 'aksara-specimens';

	/**
	 * Ekstensi yang bisa dibaca FreeType/GD. WOFF & WOFF2 TIDAK didukung
	 * (itu format khusus web, bukan format yang dibaca FreeType), jadi
	 * style yang diunggah dalam format tersebut tidak mendapat specimen —
	 * pemanggil harus siap menerima string kosong dan mundur ke font tema.
	 */
	const RENDERABLE_EXTENSIONS = array( 'ttf', 'otf' );

	/**
	 * Skala render untuk layar high-DPI: gambar dibuat 2x lalu ditampilkan
	 * setengahnya lewat CSS/atribut width, supaya tidak buram di retina.
	 */
	const SCALE = 2;

	/**
	 * Teks pratinjau default, dipakai bersama oleh typing tool (sebagai isi
	 * awal kotak ketik) DAN oleh specimen fallback. Disatukan di sini supaya
	 * keduanya tidak melenceng — kalau berbeda, gambar fallback yang tampil
	 * saat microservice mati akan menampilkan kalimat yang berbeda dari yang
	 * tadinya ada di layar.
	 *
	 * @return string
	 */
	public static function get_default_preview_text() {
		return apply_filters(
			'aksara_default_preview_text',
			__( 'Kopi pagi, ide baru, karya berani', 'aksara-marketplace' )
		);
	}

	/**
	 * Ambil URL specimen untuk sebuah style — buat dulu kalau belum ada.
	 *
	 * @param object $style Baris dari aksara_font_styles.
	 * @param string $text  Teks yang dirender.
	 * @param int    $size  Ukuran font dalam piksel (ukuran tampilan, bukan ukuran render).
	 * @return string URL gambar, atau string kosong kalau tidak bisa dirender.
	 */
	public static function get_url( $style, $text, $size = 40 ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $text || empty( $style->file_path ) ) {
			return '';
		}

		$extension = strtolower( pathinfo( $style->file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::RENDERABLE_EXTENSIONS, true ) ) {
			return '';
		}

		if ( ! function_exists( 'imagettftext' ) ) {
			return '';
		}

		$filename = self::build_filename( $style, $text, $size );
		$paths    = self::get_paths( $filename );

		if ( file_exists( $paths['path'] ) ) {
			return $paths['url'];
		}

		$font_path = Aksara_File_Storage::get_absolute_path( $style->file_path );
		if ( ! file_exists( $font_path ) ) {
			return '';
		}

		return self::render( $font_path, $text, $size, $paths ) ? $paths['url'] : '';
	}

	/**
	 * Nama berkas cache: unik per kombinasi style + teks + ukuran, dan ikut
	 * berubah kalau berkas fontnya diganti (mtime), supaya specimen lama
	 * tidak menempel setelah admin mengunggah ulang style yang sama.
	 *
	 * @param object $style Baris style.
	 * @param string $text  Teks.
	 * @param int    $size  Ukuran.
	 * @return string
	 */
	private static function build_filename( $style, $text, $size ) {
		$font_path = Aksara_File_Storage::get_absolute_path( $style->file_path );
		$mtime     = file_exists( $font_path ) ? filemtime( $font_path ) : 0;

		return sprintf(
			'style-%d-%s.png',
			(int) $style->id,
			substr( md5( $text . '|' . $size . '|' . $mtime ), 0, 12 )
		);
	}

	/**
	 * Path & URL untuk sebuah nama berkas specimen.
	 *
	 * @param string $filename Nama berkas.
	 * @return array{dir:string,path:string,url:string}
	 */
	private static function get_paths( $filename ) {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::SUBDIR;

		return array(
			'dir'  => $dir,
			'path' => trailingslashit( $dir ) . $filename,
			'url'  => trailingslashit( $upload['baseurl'] ) . self::SUBDIR . '/' . $filename,
		);
	}

	/**
	 * Render teks jadi PNG transparan.
	 *
	 * @param string $font_path Path absolut berkas font (.ttf/.otf).
	 * @param string $text      Teks.
	 * @param int    $size      Ukuran tampilan (piksel).
	 * @param array  $paths     Hasil get_paths().
	 * @return bool Berhasil atau tidak.
	 */
	private static function render( $font_path, $text, $size, $paths ) {
		$render_size = $size * self::SCALE;

		// imagettfbbox() mengembalikan 8 angka (4 titik sudut). Dipakai untuk
		// menghitung ukuran kanvas yang pas, termasuk bagian huruf yang turun
		// di bawah baseline (descender pada g/y/p) supaya tidak terpotong.
		$bbox = @imagettfbbox( $render_size, 0, $font_path, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- font rusak/tak terbaca ditangani lewat pengecekan false di bawah.
		if ( ! is_array( $bbox ) ) {
			return false;
		}

		$padding = (int) round( $render_size * 0.12 );
		$width   = abs( $bbox[4] - $bbox[0] ) + ( $padding * 2 );
		$height  = abs( $bbox[5] - $bbox[1] ) + ( $padding * 2 );

		if ( $width < 1 || $height < 1 || $width > 4000 || $height > 1000 ) {
			return false;
		}

		$image = imagecreatetruecolor( $width, $height );
		if ( ! $image ) {
			return false;
		}

		// Latar transparan supaya specimen menyatu dengan warna latar apa pun
		// (kartu, baris listing, latar gelap) tanpa perlu varian gambar.
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefill( $image, 0, 0, $transparent );
		imagealphablending( $image, true );

		/**
		 * Warna teks specimen. Default mengikuti token --ink tema Aksara.
		 *
		 * @param array $rgb Array [r, g, b].
		 */
		$rgb   = apply_filters( 'aksara_specimen_color', array( 34, 31, 26 ) );
		$color = imagecolorallocate( $image, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] );

		$x = $padding - $bbox[0];
		$y = $padding - $bbox[5];

		$drawn = @imagettftext( $image, $render_size, 0, $x, $y, $color, $font_path, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $drawn ) {
			imagedestroy( $image );
			return false;
		}

		wp_mkdir_p( $paths['dir'] );
		$saved = imagepng( $image, $paths['path'], 6 );
		imagedestroy( $image );

		return (bool) $saved;
	}

	/**
	 * Cetak <img> specimen, atau kembalikan string kosong kalau tidak bisa
	 * dirender — supaya pemanggil bisa mundur ke teks biasa.
	 *
	 * @param object $style Baris style.
	 * @param string $text  Teks.
	 * @param int    $size  Ukuran tampilan (piksel).
	 * @param string $class Class CSS tambahan.
	 * @return string HTML <img> atau string kosong.
	 */
	public static function get_img_tag( $style, $text, $size = 40, $class = 'aksara-specimen' ) {
		$url = self::get_url( $style, $text, $size );
		if ( ! $url ) {
			return '';
		}

		return sprintf(
			'<img src="%1$s" alt="%2$s" class="%3$s" style="height:%4$dpx;width:auto;" loading="lazy" decoding="async">',
			esc_url( $url ),
			/* translators: %s: teks yang dirender dalam font tersebut. */
			esc_attr( sprintf( __( '%s — contoh tampilan font', 'aksara-marketplace' ), $text ) ),
			esc_attr( $class ),
			(int) $size
		);
	}

	/**
	 * Hapus seluruh specimen milik satu style (dipanggil saat style dihapus
	 * atau berkas fontnya diganti).
	 *
	 * @param int $style_id ID style.
	 */
	public static function purge_for_style( $style_id ) {
		$paths = self::get_paths( '' );
		$glob  = glob( trailingslashit( $paths['dir'] ) . 'style-' . (int) $style_id . '-*.png' );

		if ( ! $glob ) {
			return;
		}

		foreach ( $glob as $file ) {
			wp_delete_file( $file );
		}
	}
}
