<?php
/**
 * Penyimpanan file privat (font asli, file template asli) di luar akses URL langsung.
 *
 * Mengikuti pola yang sama dipakai WooCommerce sendiri untuk downloadable
 * products (wp-content/uploads/woocommerce_uploads/): folder di dalam
 * uploads dir tapi diblokir lewat .htaccess + index.php kosong, bukan
 * disimpan lewat Media Library biasa (yang selalu bisa diakses publik
 * lewat URL langsung).
 *
 * Keputusan arsitektur: disk lokal + permission ketat (lihat Starter
 * Brief Bagian 2) — bukan S3, untuk mulai cepat. Bisa dimigrasikan ke
 * S3-compatible storage nanti tanpa mengubah kontrak repository (path
 * relatif) yang dipakai kode lain.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_File_Storage.
 */
class Aksara_File_Storage {

	const SUBDIR = 'aksara-private';

	const ALLOWED_FONT_EXTENSIONS = array( 'ttf', 'otf', 'woff', 'woff2' );

	/**
	 * Direktori absolut tempat file privat disimpan (di luar akses URL langsung).
	 *
	 * @return string
	 */
	public static function get_base_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . self::SUBDIR;
	}

	/**
	 * Pastikan direktori privat ada dan diblokir dari akses web langsung.
	 *
	 * @param string $subdir Sub-folder di dalam direktori privat (mis. 'fonts').
	 * @return string Path absolut direktori yang sudah dipastikan ada.
	 */
	public static function ensure_protected_dir( $subdir = '' ) {
		$base_dir = self::get_base_dir();
		$dir      = $subdir ? trailingslashit( $base_dir ) . trim( $subdir, '/' ) : $base_dir;

		wp_mkdir_p( $dir );

		$htaccess = trailingslashit( $base_dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// "deny from all" (Apache 2.2) + "Require all denied" (Apache 2.4)
			// dipasang bersamaan supaya berlaku di kedua versi. Setup Nginx
			// TIDAK membaca .htaccess sama sekali — server block Nginx wajib
			// menambahkan aturan setara secara manual (lihat readme.txt).
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$htaccess,
				"Require all denied\ndeny from all\n"
			);
		}

		$index = trailingslashit( $base_dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}

	/**
	 * Simpan file font yang diunggah admin ke folder privat.
	 *
	 * @param string $tmp_path          Path file sementara (mis. dari $_FILES[...]['tmp_name']).
	 * @param string $original_filename Nama file asli (untuk menentukan ekstensi & nama unik).
	 * @return string|WP_Error Path RELATIF (terhadap base dir privat) jika sukses, WP_Error jika gagal.
	 */
	public static function store_uploaded_font( $tmp_path, $original_filename ) {
		if ( ! is_uploaded_file( $tmp_path ) ) {
			return new WP_Error( 'aksara_invalid_upload', __( 'Berkas yang diunggah tidak valid.', 'aksara-marketplace' ) );
		}

		$extension = strtolower( pathinfo( $original_filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::ALLOWED_FONT_EXTENSIONS, true ) ) {
			return new WP_Error(
				'aksara_invalid_extension',
				sprintf(
					/* translators: %s: daftar ekstensi yang diizinkan. */
					__( 'Jenis berkas tidak diizinkan. Gunakan salah satu dari: %s.', 'aksara-marketplace' ),
					implode( ', ', self::ALLOWED_FONT_EXTENSIONS )
				)
			);
		}

		$dir = self::ensure_protected_dir( 'fonts' );

		$safe_name = sanitize_file_name( pathinfo( $original_filename, PATHINFO_FILENAME ) );
		$filename  = $safe_name . '-' . wp_generate_password( 8, false, false ) . '.' . $extension;

		$destination = trailingslashit( $dir ) . $filename;

		if ( ! @move_uploaded_file( $tmp_path, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
			return new WP_Error( 'aksara_move_failed', __( 'Gagal menyimpan berkas font ke folder privat.', 'aksara-marketplace' ) );
		}

		return 'fonts/' . $filename;
	}

	/**
	 * Simpan konten yang di-generate server (mis. PDF sertifikat lisensi)
	 * ke folder privat — beda dari store_uploaded_font() yang menangani
	 * $_FILES upload, ini cuma menulis string biner apa adanya.
	 *
	 * @param string $subdir   Sub-folder di dalam direktori privat (mis. 'certificates').
	 * @param string $filename Nama file (harus sudah aman/unik, dipanggil sudah lewat sanitize_file_name()).
	 * @param string $content  Konten biner.
	 * @return string|WP_Error Path RELATIF terhadap base dir privat, atau WP_Error.
	 */
	public static function store_generated_file( $subdir, $filename, $content ) {
		$dir         = self::ensure_protected_dir( $subdir );
		$filename    = sanitize_file_name( $filename );
		$destination = trailingslashit( $dir ) . $filename;

		$written = file_put_contents( $destination, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			return new WP_Error( 'aksara_write_failed', __( 'Gagal menyimpan berkas yang dihasilkan.', 'aksara-marketplace' ) );
		}

		return trim( $subdir, '/' ) . '/' . $filename;
	}

	/**
	 * Ubah path relatif (tersimpan di DB) menjadi path absolut di disk.
	 *
	 * @param string $relative_path Path relatif hasil dari store_uploaded_font().
	 * @return string
	 */
	public static function get_absolute_path( $relative_path ) {
		return trailingslashit( self::get_base_dir() ) . ltrim( $relative_path, '/' );
	}

	/**
	 * Hapus file fisik berdasarkan path relatif.
	 *
	 * @param string $relative_path Path relatif hasil dari store_uploaded_font().
	 */
	public static function delete( $relative_path ) {
		$absolute = self::get_absolute_path( $relative_path );
		if ( file_exists( $absolute ) ) {
			wp_delete_file( $absolute );
		}
	}
}
