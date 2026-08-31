<?php
/**
 * Metabox "Font Styles" untuk produk Font: bulk upload banyak file style
 * sekaligus, edit weight/italic/urutan, dan matriks harga per lisensi.
 *
 * Bulk upload diprioritaskan sejak Fase 1 (bukan ditunda ke fase lanjut)
 * karena perkiraan volume katalog awal "menengah" (ratusan produk) —
 * upload satu-satu tidak realistis untuk font family yang bisa punya
 * belasan style.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Font_Styles_Metabox.
 */
class Aksara_Font_Styles_Metabox {

	const NONCE_ACTION = 'aksara_save_font_styles';
	const NONCE_NAME   = 'aksara_font_styles_nonce';

	/**
	 * Kata kunci umum pada nama file untuk menebak weight otomatis saat bulk upload.
	 *
	 * @var array
	 */
	private static $weight_keywords = array(
		'thin'       => 100,
		'extralight' => 200,
		'ultralight' => 200,
		'light'      => 300,
		'regular'    => 400,
		'normal'     => 400,
		'medium'     => 500,
		'semibold'   => 600,
		'demibold'   => 600,
		'bold'       => 700,
		'extrabold'  => 800,
		'ultrabold'  => 800,
		'black'      => 900,
		'heavy'      => 900,
	);

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save' ) );
	}

	/**
	 * Daftarkan meta box.
	 */
	public static function register() {
		add_meta_box(
			'aksara_font_styles',
			__( 'Font Styles', 'aksara-marketplace' ),
			array( __CLASS__, 'render' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Cetak isi metabox: daftar style yang ada + form bulk upload.
	 *
	 * @param WP_Post $post Post produk saat ini.
	 */
	public static function render( $post ) {
		$product_type = Aksara_Canva_Info_Metabox::get_current_product_type( $post->ID );

		if ( 'font' !== $product_type ) {
			echo '<p>' . esc_html__( 'Set "Product type" ke Font (Aksara) untuk mengelola style di sini.', 'aksara-marketplace' ) . '</p>';
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$styles           = Aksara_Font_Styles_Repository::get_by_product( $post->ID );
		$licenses         = Aksara_Font_Licenses_Repository::get_all();
		$matrix           = Aksara_Font_Licenses_Repository::get_price_matrix_for_product( $post->ID );
		$bundle_discount  = get_post_meta( $post->ID, '_aksara_bundle_discount_percent', true );

		if ( empty( $licenses ) ) {
			echo '<p>' . esc_html__( 'Belum ada jenis lisensi. Tambahkan dulu di menu WooCommerce > Lisensi Font.', 'aksara-marketplace' ) . '</p>';
			return;
		}
		?>
		<p>
			<label for="aksara_bundle_discount_percent"><strong><?php esc_html_e( 'Diskon Paket Lengkap (%)', 'aksara-marketplace' ); ?></strong></label><br>
			<input type="number" id="aksara_bundle_discount_percent" name="aksara_bundle_discount_percent" class="aksara-discount-input" min="0" max="90" step="1" value="<?php echo esc_attr( $bundle_discount ); ?>">
			<span class="description"><?php esc_html_e( 'Diterapkan otomatis di kalkulator saat pembeli memilih tombol "Pilih Semua" (seluruh style yang berharga untuk lisensi tersebut). Kosongkan/0 untuk menonaktifkan.', 'aksara-marketplace' ); ?></span>
		</p>
		<?php if ( empty( $styles ) ) : ?>
			<p><?php esc_html_e( 'Belum ada style. Unggah file font di bawah untuk mulai.', 'aksara-marketplace' ); ?></p>
		<?php else : ?>
			<table class="aksara-styles-table">
				<thead>
					<tr>
						<th class="aksara-preview-cell"><?php esc_html_e( 'Pratinjau', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Nama Style', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Weight', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Italic', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Berkas', 'aksara-marketplace' ); ?></th>
						<?php foreach ( $licenses as $license ) : ?>
							<th class="aksara-price-cell"><?php echo esc_html( $license->name ); ?></th>
						<?php endforeach; ?>
						<th><?php esc_html_e( 'Hapus', 'aksara-marketplace' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $styles as $style ) :
						/*
						 * Sebuah style yang tidak punya harga untuk lisensi mana pun
						 * tidak akan pernah bisa dibeli — kalkulator di halaman produk
						 * melewatinya diam-diam. Sebelumnya kondisi ini sama sekali
						 * tidak terlihat dari sini: tabel harga penuh kotak kosong dan
						 * tidak ada yang membedakan "sengaja gratis di lisensi ini"
						 * dari "seluruh barisnya lupa diisi". Ditandai eksplisit.
						 */
						$row_prices = $matrix[ $style->id ] ?? array();
						$has_price  = false;
						foreach ( $row_prices as $row_price ) {
							if ( '' !== $row_price && null !== $row_price ) {
								$has_price = true;
								break;
							}
						}
						?>
						<tr class="<?php echo $has_price ? '' : 'aksara-style-unpriced'; ?>">
							<td class="aksara-preview-cell">
								<?php
								/*
								 * Pratinjau memakai mesin render yang sama dengan
								 * etalase (PHP GD), jadi admin melihat persis apa yang
								 * dilihat pengunjung — termasuk saat sebuah berkas
								 * TIDAK bisa dirender (mis. diunggah sebagai .woff2,
								 * yang tidak dibaca FreeType). Dulu kegagalan itu baru
								 * ketahuan setelah produk terbit dan listing-nya mundur
								 * ke teks biasa.
								 */
								$preview = class_exists( 'Aksara_Specimen_Image' )
									? Aksara_Specimen_Image::get_img_tag( $style, $style->style_name, 22, 'aksara-admin-specimen' )
									: '';
								if ( $preview ) {
									echo wp_kses_post( $preview );
								} else {
									echo '<span class="aksara-no-preview" title="' . esc_attr__( 'Berkas ini tidak bisa dirender jadi gambar (biasanya karena format .woff/.woff2). Style tetap bisa dijual & diunduh pembeli, tapi tidak akan tampil sebagai contoh huruf di listing.', 'aksara-marketplace' ) . '">' . esc_html__( 'tidak bisa dirender', 'aksara-marketplace' ) . '</span>';
								}
								?>
							</td>
							<td>
								<input type="text" name="aksara_style[<?php echo esc_attr( $style->id ); ?>][style_name]" value="<?php echo esc_attr( $style->style_name ); ?>">
								<?php if ( ! $has_price ) : ?>
									<span class="aksara-unpriced-flag"><?php esc_html_e( 'Belum ada harga — style ini tidak bisa dibeli.', 'aksara-marketplace' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<input type="number" min="100" max="900" step="100" name="aksara_style[<?php echo esc_attr( $style->id ); ?>][font_weight]" value="<?php echo esc_attr( $style->font_weight ); ?>">
							</td>
							<td>
								<input type="checkbox" name="aksara_style[<?php echo esc_attr( $style->id ); ?>][is_italic]" value="1" <?php checked( $style->is_italic, 1 ); ?>>
							</td>
							<td><code><?php echo esc_html( basename( $style->file_path ) ); ?></code></td>
							<?php foreach ( $licenses as $license ) : ?>
								<td class="aksara-price-cell">
									<input type="number" min="0" step="0.01" name="aksara_style_price[<?php echo esc_attr( $style->id ); ?>][<?php echo esc_attr( $license->id ); ?>]" value="<?php echo esc_attr( $matrix[ $style->id ][ $license->id ] ?? '' ); ?>">
								</td>
							<?php endforeach; ?>
							<td class="aksara-delete-cell">
								<label>
									<input type="checkbox" name="aksara_delete_style[]" value="<?php echo esc_attr( $style->id ); ?>">
									<span class="screen-reader-text">
										<?php
										printf(
											/* translators: %s: nama style. */
											esc_html__( 'Hapus style %s', 'aksara-marketplace' ),
											esc_html( $style->style_name )
										);
										?>
									</span>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h4><?php esc_html_e( 'Tambah Style Baru (Bulk Upload)', 'aksara-marketplace' ); ?></h4>
		<p>
			<input type="file" name="aksara_new_font_files[]" multiple accept=".ttf,.otf,.woff,.woff2">
		</p>
		<p class="description">
			<?php esc_html_e( 'Pilih beberapa berkas font sekaligus (.ttf/.otf/.woff/.woff2). Nama style, weight, dan italic akan ditebak otomatis dari nama berkas (mis. "Grafira-SemiBoldItalic.otf") — bisa disunting lagi setelah disimpan. Atur harga per lisensi setelah style baru muncul di tabel atas.', 'aksara-marketplace' ); ?>
		</p>
		<p class="description">
			<?php
			/*
			 * Batas ukuran server disebut di muka. Menabraknya adalah cara
			 * paling umum bulk upload gagal, dan pesan PHP-nya sendiri tidak
			 * pernah sampai ke admin — jadi lebih baik angkanya terlihat
			 * sebelum berkasnya dipilih, bukan sesudah gagal.
			 */
			echo wp_kses_post(
				sprintf(
					/* translators: 1: batas ukuran per berkas, 2: format .ttf/.otf. */
					esc_html__( 'Maksimal %1$s per berkas. Agar contoh hurufnya bisa tampil di listing, unggah dalam format %2$s — .woff/.woff2 tetap bisa dijual dan diunduh pembeli, tapi tidak bisa dirender jadi gambar spesimen.', 'aksara-marketplace' ),
					esc_html( size_format( wp_max_upload_size() ) ),
					'<code>.ttf</code>/<code>.otf</code>'
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Tebak weight & italic dari nama file.
	 *
	 * @param string $filename Nama file asli (tanpa ekstensi).
	 * @return array{weight:int,italic:bool}
	 */
	private static function guess_weight_and_italic( $filename ) {
		$lower  = strtolower( $filename );
		$weight = 400;
		$italic = false !== strpos( $lower, 'italic' ) || false !== strpos( $lower, 'oblique' );

		foreach ( self::$weight_keywords as $keyword => $value ) {
			if ( false !== strpos( $lower, $keyword ) ) {
				$weight = $value;
				break;
			}
		}

		return array(
			'weight' => $weight,
			'italic' => $italic,
		);
	}

	/**
	 * Ubah nama file menjadi nama style yang lebih rapi.
	 * Mis. "Grafira-SemiBoldItalic" -> "Grafira Semi Bold Italic".
	 *
	 * @param string $filename Nama file tanpa ekstensi.
	 * @return string
	 */
	private static function guess_style_name( $filename ) {
		$name = str_replace( array( '-', '_' ), ' ', $filename );
		$name = preg_replace( '/(?<=[a-z0-9])(?=[A-Z])/', ' ', $name );
		$name = preg_replace( '/\s+/', ' ', $name );
		return trim( $name );
	}

	/**
	 * Simpan seluruh perubahan: hapus, edit meta+harga, dan proses bulk upload.
	 *
	 * @param int $post_id ID produk.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'font' !== Aksara_Canva_Info_Metabox::get_current_product_type( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['aksara_bundle_discount_percent'] ) ) {
			$discount = max( 0, min( 90, (float) $_POST['aksara_bundle_discount_percent'] ) );
			update_post_meta( $post_id, '_aksara_bundle_discount_percent', $discount );
		}

		// 1) Hapus style yang ditandai.
		if ( ! empty( $_POST['aksara_delete_style'] ) && is_array( $_POST['aksara_delete_style'] ) ) {
			foreach ( wp_unslash( $_POST['aksara_delete_style'] ) as $style_id ) {
				$style = Aksara_Font_Styles_Repository::get( (int) $style_id );
				if ( $style && (int) $style->product_id === $post_id ) {
					Aksara_File_Storage::delete( $style->file_path );
					if ( class_exists( 'Aksara_Specimen_Image' ) ) {
						Aksara_Specimen_Image::purge_for_style( $style->id );
					}
					Aksara_Font_Styles_Repository::delete( $style->id );
				}
			}
		}

		// 2) Perbarui metadata & harga style yang sudah ada.
		if ( ! empty( $_POST['aksara_style'] ) && is_array( $_POST['aksara_style'] ) ) {
			foreach ( wp_unslash( $_POST['aksara_style'] ) as $style_id => $fields ) {
				$style = Aksara_Font_Styles_Repository::get( (int) $style_id );
				if ( ! $style || (int) $style->product_id !== $post_id ) {
					continue;
				}
				Aksara_Font_Styles_Repository::update_meta( $style_id, $fields );
			}
		}

		if ( ! empty( $_POST['aksara_style_price'] ) && is_array( $_POST['aksara_style_price'] ) ) {
			foreach ( wp_unslash( $_POST['aksara_style_price'] ) as $style_id => $prices_by_license ) {
				$style = Aksara_Font_Styles_Repository::get( (int) $style_id );
				if ( ! $style || (int) $style->product_id !== $post_id ) {
					continue;
				}
				foreach ( $prices_by_license as $license_id => $price ) {
					if ( '' === $price ) {
						continue;
					}
					Aksara_Font_Licenses_Repository::set_style_price( $style_id, $license_id, $price );
				}
			}
		}

		// 3) Bulk upload style baru.
		if ( ! empty( $_FILES['aksara_new_font_files']['name'][0] ) ) {
			$existing_count = Aksara_Font_Styles_Repository::count_by_product( $post_id );
			$files          = $_FILES['aksara_new_font_files']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already checked above.
			$added          = 0;
			$failures       = array();

			foreach ( $files['name'] as $index => $original_name ) {
				if ( empty( $original_name ) ) {
					continue;
				}

				/*
				 * Kegagalan unggah dilaporkan, bukan dilewati diam-diam.
				 * Versi sebelumnya memakai `continue` polos untuk setiap
				 * kondisi gagal, sehingga berkas yang terlalu besar atau
				 * ekstensinya salah menghilang tanpa jejak: admin menekan
				 * Update, halaman tersimpan "sukses", dan style-nya tidak
				 * ada. Sekarang setiap kegagalan punya sebab yang bisa
				 * dibaca.
				 */
				if ( UPLOAD_ERR_OK !== $files['error'][ $index ] ) {
					$failures[] = sprintf( '%s (%s)', $original_name, self::upload_error_message( $files['error'][ $index ] ) );
					continue;
				}

				$stored_path = Aksara_File_Storage::store_uploaded_font( $files['tmp_name'][ $index ], $original_name );
				if ( is_wp_error( $stored_path ) ) {
					$failures[] = sprintf( '%s (%s)', $original_name, $stored_path->get_error_message() );
					continue;
				}

				++$added;

				$name_without_ext = pathinfo( $original_name, PATHINFO_FILENAME );
				$guessed          = self::guess_weight_and_italic( $name_without_ext );

				$new_style_id = Aksara_Font_Styles_Repository::insert(
					array(
						'product_id'  => $post_id,
						'style_name'  => self::guess_style_name( $name_without_ext ),
						'font_weight' => $guessed['weight'],
						'is_italic'   => $guessed['italic'],
						'file_path'   => $stored_path,
						'sort_order'  => $existing_count + $index,
					)
				);

				// Buat specimen sekarang, saat admin mengunggah — bukan nanti
				// saat pengunjung pertama membuka halaman produk. Render GD
				// perlu puluhan milidetik per style; untuk family berisi
				// belasan style, membiarkannya terjadi saat page load berarti
				// satu pengunjung yang apes menanggung seluruh biayanya.
				if ( $new_style_id && class_exists( 'Aksara_Specimen_Image' ) ) {
					$new_style = Aksara_Font_Styles_Repository::get( $new_style_id );
					if ( $new_style ) {
						Aksara_Specimen_Image::get_url( $new_style, Aksara_Specimen_Image::get_default_preview_text(), 40 );
						Aksara_Specimen_Image::get_url( $new_style, get_the_title( $post_id ), 115 );
						Aksara_Specimen_Image::get_url( $new_style, $new_style->style_name, 22 );
					}
				}
			}

			self::report_upload_result( $added, $failures );
		}
	}

	/**
	 * Antrekan ringkasan hasil bulk upload sebagai notice admin.
	 *
	 * @param int   $added    Jumlah style yang berhasil ditambahkan.
	 * @param array $failures Daftar pesan kegagalan per berkas.
	 */
	private static function report_upload_result( $added, array $failures ) {
		if ( ! class_exists( 'Aksara_Admin_UI' ) ) {
			return;
		}

		if ( $added > 0 ) {
			Aksara_Admin_UI::queue_notice(
				sprintf(
					/* translators: %d: jumlah style yang ditambahkan. */
					_n( '%d style font ditambahkan. Jangan lupa isi harganya per lisensi.', '%d style font ditambahkan. Jangan lupa isi harganya per lisensi.', $added, 'aksara-marketplace' ),
					$added
				),
				'success'
			);
		}

		if ( ! empty( $failures ) ) {
			Aksara_Admin_UI::queue_notice(
				sprintf(
					/* translators: %s: daftar berkas yang gagal beserta sebabnya. */
					__( 'Berkas berikut gagal diunggah: %s', 'aksara-marketplace' ),
					implode( '; ', $failures )
				),
				'error'
			);
		}
	}

	/**
	 * Terjemahkan kode error upload PHP jadi kalimat yang bisa ditindaklanjuti.
	 *
	 * @param int $code Konstanta UPLOAD_ERR_*.
	 * @return string
	 */
	private static function upload_error_message( $code ) {
		switch ( (int) $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return sprintf(
					/* translators: %s: batas ukuran unggah server, mis. "8 MB". */
					__( 'melebihi batas ukuran unggah server (%s)', 'aksara-marketplace' ),
					size_format( wp_max_upload_size() )
				);
			case UPLOAD_ERR_PARTIAL:
				return __( 'terkirim sebagian, coba unggah ulang', 'aksara-marketplace' );
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'server tidak bisa menulis berkas sementara — hubungi hosting', 'aksara-marketplace' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'ditolak oleh ekstensi PHP di server', 'aksara-marketplace' );
			default:
				return __( 'gagal diunggah', 'aksara-marketplace' );
		}
	}
}
