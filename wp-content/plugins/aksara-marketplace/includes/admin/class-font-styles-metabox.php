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
			<input type="number" id="aksara_bundle_discount_percent" name="aksara_bundle_discount_percent" min="0" max="90" step="1" style="width:100px;" value="<?php echo esc_attr( $bundle_discount ); ?>">
			<span class="description"><?php esc_html_e( 'Diterapkan otomatis di kalkulator saat pembeli memilih tombol "Pilih Semua" (seluruh style yang berharga untuk lisensi tersebut). Kosongkan/0 untuk menonaktifkan.', 'aksara-marketplace' ); ?></span>
		</p>
		<style>
			.aksara-styles-table { width: 100%; border-collapse: collapse; }
			.aksara-styles-table th, .aksara-styles-table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
			.aksara-styles-table input[type="text"], .aksara-styles-table input[type="number"] { width: 100%; }
			.aksara-styles-table .aksara-price-cell { width: 90px; }
			.aksara-styles-table .aksara-price-cell input { width: 80px; }
		</style>

		<?php if ( empty( $styles ) ) : ?>
			<p><?php esc_html_e( 'Belum ada style. Unggah file font di bawah untuk mulai.', 'aksara-marketplace' ); ?></p>
		<?php else : ?>
			<table class="aksara-styles-table">
				<thead>
					<tr>
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
					<?php foreach ( $styles as $style ) : ?>
						<tr>
							<td>
								<input type="text" name="aksara_style[<?php echo esc_attr( $style->id ); ?>][style_name]" value="<?php echo esc_attr( $style->style_name ); ?>">
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
							<td>
								<input type="checkbox" name="aksara_delete_style[]" value="<?php echo esc_attr( $style->id ); ?>">
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

			foreach ( $files['name'] as $index => $original_name ) {
				if ( UPLOAD_ERR_OK !== $files['error'][ $index ] || empty( $original_name ) ) {
					continue;
				}

				$stored_path = Aksara_File_Storage::store_uploaded_font( $files['tmp_name'][ $index ], $original_name );
				if ( is_wp_error( $stored_path ) ) {
					continue;
				}

				$name_without_ext = pathinfo( $original_name, PATHINFO_FILENAME );
				$guessed          = self::guess_weight_and_italic( $name_without_ext );

				Aksara_Font_Styles_Repository::insert(
					array(
						'product_id'  => $post_id,
						'style_name'  => self::guess_style_name( $name_without_ext ),
						'font_weight' => $guessed['weight'],
						'is_italic'   => $guessed['italic'],
						'file_path'   => $stored_path,
						'sort_order'  => $existing_count + $index,
					)
				);
			}
		}
	}
}
