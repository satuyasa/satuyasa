<?php
/**
 * Data tambahan halaman produk font: Team, tanggal rilis/pembaruan, dan
 * "Additionally" (tautan pelengkap seperti specimen PDF atau poster).
 *
 * KENAPA DI TEMA, BUKAN DI PLUGIN
 *
 * Semua ini murni isi editorial halaman: siapa yang menggambar font itu,
 * kapan versi terakhir keluar, apa yang berubah, dan berkas pelengkap apa
 * yang bisa diunduh. Tidak satu pun menyentuh harga, lisensi, atau rantai
 * unduhan berbayar — jadi tidak ada alasan menaruhnya di Authentype, dan
 * ada satu alasan kuat untuk TIDAK: Authentype plugin pihak ketiga, dan
 * setiap suntingan di sana hilang diam-diam pada pembaruan berikutnya.
 *
 * Kunci meta-nya diawali _aksara_ supaya tidak pernah bertabrakan dengan
 * kunci _ath_ milik plugin, sekarang maupun nanti.
 *
 * KENAPA REPEATER-NYA TANPA JAVASCRIPT
 *
 * Baris "Additionally" jumlahnya bebas. Cara biasa: tombol "Tambah baris"
 * yang mengkloning template lewat JS. Di sini caranya lebih sederhana —
 * layar selalu mencetak baris yang sudah ada DITAMBAH dua baris kosong, dan
 * baris kosong dibuang saat disimpan. Hasilnya sama bagi penyunting (selalu
 * ada tempat untuk menambah), tanpa satu baris JS pun yang bisa rusak,
 * bentrok dengan plugin lain, atau gagal dimuat.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Jumlah baris kosong yang selalu disediakan di bagian "Additionally". */
const AKSARA_FONT_EXTRA_BLANKS = 2;

/**
 * Membaca seluruh data tambahan sebuah font dan menormalkannya.
 *
 * Selalu mengembalikan bentuk yang sama — array kosong, bukan false atau
 * null — supaya templat tidak perlu memeriksa tipe sebelum melakukan foreach.
 *
 * @param int $post_id ID post ath_font.
 * @return array{team:array,release:string,updated:string,changelog:array,extras:array}
 */
function aksara_font_details( $post_id ) {
	$post_id = absint( $post_id );

	$team = array();
	foreach ( aksara_font_details_lines( get_post_meta( $post_id, '_aksara_font_team', true ) ) as $line ) {
		// "Nama | Peran" — pemisahnya opsional; tanpa itu barisnya nama saja.
		$parts  = array_map( 'trim', explode( '|', $line, 2 ) );
		$team[] = array(
			'name' => $parts[0],
			'role' => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	$extras = array();
	$raw    = get_post_meta( $post_id, '_aksara_font_extras', true );
	if ( is_array( $raw ) ) {
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || '' === trim( (string) ( isset( $row['label'] ) ? $row['label'] : '' ) ) ) {
				continue;
			}
			$extras[] = array(
				'label' => (string) $row['label'],
				'url'   => isset( $row['url'] ) ? (string) $row['url'] : '',
				'text'  => isset( $row['text'] ) ? (string) $row['text'] : '',
			);
		}
	}

	return array(
		'team'      => $team,
		'release'   => trim( (string) get_post_meta( $post_id, '_aksara_font_release', true ) ),
		'updated'   => trim( (string) get_post_meta( $post_id, '_aksara_font_updated', true ) ),
		'changelog' => aksara_font_details_lines( get_post_meta( $post_id, '_aksara_font_changelog', true ) ),
		'extras'    => $extras,
	);
}

/**
 * Memecah textarea jadi baris, membuang baris kosong dan spasi di tepi.
 *
 * @param mixed $value Isi textarea.
 * @return string[]
 */
function aksara_font_details_lines( $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return array();
	}
	$lines = preg_split( '/\R/', $value );
	$lines = array_map( 'trim', is_array( $lines ) ? $lines : array() );

	return array_values( array_filter( $lines, 'strlen' ) );
}

/**
 * Menampilkan tanggal sesuai format situs KALAU nilainya benar-benar tanggal.
 *
 * Kolomnya sengaja teks bebas: banyak foundry menulis "Spring 2024" atau
 * "v2.1 — Juni 2024", dan memaksanya jadi date picker akan membuang
 * kemampuan itu. Jadi hanya nilai ISO (YYYY-MM-DD) — bentuk yang keluar dari
 * date picker peramban — yang diformat ulang; sisanya dicetak apa adanya.
 *
 * @param string $value Nilai mentah.
 * @return string
 */
function aksara_font_details_date( $value ) {
	$value = trim( (string) $value );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		return $value;
	}
	$stamp = strtotime( $value . ' 12:00:00' );

	return $stamp ? date_i18n( get_option( 'date_format' ), $stamp ) : $value;
}

/**
 * Mendaftarkan metabox di layar edit ath_font.
 */
function aksara_font_details_meta_box() {
	add_meta_box(
		'aksara-font-details',
		__( 'Product details (Aksara)', 'aksara' ),
		'aksara_font_details_meta_box_render',
		'ath_font',
		'normal',
		'low'
	);
}
add_action( 'add_meta_boxes', 'aksara_font_details_meta_box' );

/**
 * Isi metabox.
 *
 * @param WP_Post $post Post yang sedang disunting.
 */
function aksara_font_details_meta_box_render( $post ) {
	$data = aksara_font_details( $post->ID );

	$team_raw = '';
	foreach ( $data['team'] as $member ) {
		$team_raw .= $member['name'] . ( '' !== $member['role'] ? ' | ' . $member['role'] : '' ) . "\n";
	}

	wp_nonce_field( 'aksara_font_details_save', 'aksara_font_details_nonce' );
	?>
	<style>
		.aksara-fd { max-width: 780px; }
		.aksara-fd p.description { margin-bottom: 12px; }
		.aksara-fd textarea { width: 100%; }
		.aksara-fd .aksara-fd-extra { border: 1px solid #dcdcde; padding: 12px; margin-bottom: 12px; }
		.aksara-fd .aksara-fd-extra label { display: block; margin-bottom: 8px; font-weight: 600; }
		.aksara-fd .aksara-fd-extra input { width: 100%; font-weight: 400; }
	</style>
	<div class="aksara-fd">
		<h2 class="title"><?php esc_html_e( 'Team', 'aksara' ); ?></h2>
		<p class="description"><?php esc_html_e( 'One person per line. Add a role after a vertical bar, for example: Ana Prasetya | Type design', 'aksara' ); ?></p>
		<textarea name="aksara_font_team" rows="4" class="large-text code"><?php echo esc_textarea( trim( $team_raw ) ); ?></textarea>

		<h2 class="title"><?php esc_html_e( 'Release and update date', 'aksara' ); ?></h2>
		<p>
			<label for="aksara_font_release"><strong><?php esc_html_e( 'Release', 'aksara' ); ?></strong></label><br>
			<input type="text" id="aksara_font_release" name="aksara_font_release" class="regular-text" value="<?php echo esc_attr( $data['release'] ); ?>" placeholder="2024-03-18">
		</p>
		<p>
			<label for="aksara_font_updated"><strong><?php esc_html_e( 'Last update', 'aksara' ); ?></strong></label><br>
			<input type="text" id="aksara_font_updated" name="aksara_font_updated" class="regular-text" value="<?php echo esc_attr( $data['updated'] ); ?>" placeholder="2025-01-09">
		</p>
		<p class="description"><?php esc_html_e( 'A date written as 2024-03-18 is shown in the site date format. Anything else is shown exactly as typed, so “Spring 2024” also works.', 'aksara' ); ?></p>

		<h2 class="title"><?php esc_html_e( 'What has changed in the font', 'aksara' ); ?></h2>
		<p class="description"><?php esc_html_e( 'One change per line. Leave empty to hide this list.', 'aksara' ); ?></p>
		<textarea name="aksara_font_changelog" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", $data['changelog'] ) ); ?></textarea>

		<h2 class="title"><?php esc_html_e( 'Additionally', 'aksara' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Extra material offered next to the family: a specimen PDF, poster images, a custom cut. A row without a label is discarded, and two empty rows are always kept ready below.', 'aksara' ); ?></p>
		<?php
		$rows = $data['extras'];
		for ( $i = 0; $i < AKSARA_FONT_EXTRA_BLANKS; $i++ ) {
			$rows[] = array(
				'label' => '',
				'url'   => '',
				'text'  => '',
			);
		}
		foreach ( $rows as $index => $row ) :
			?>
			<div class="aksara-fd-extra">
				<label><?php esc_html_e( 'Label', 'aksara' ); ?>
					<input type="text" name="aksara_font_extras[<?php echo (int) $index; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Specimen', 'aksara' ); ?>">
				</label>
				<label><?php esc_html_e( 'Link', 'aksara' ); ?>
					<input type="url" name="aksara_font_extras[<?php echo (int) $index; ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>" placeholder="https://">
				</label>
				<label><?php esc_html_e( 'Description', 'aksara' ); ?>
					<input type="text" name="aksara_font_extras[<?php echo (int) $index; ?>][text]" value="<?php echo esc_attr( $row['text'] ); ?>" placeholder="<?php esc_attr_e( 'A PDF with every style set at text and display sizes.', 'aksara' ); ?>">
				</label>
			</div>
			<?php
		endforeach;
		?>
	</div>
	<?php
}

/**
 * Menyimpan isi metabox.
 *
 * @param int $post_id ID post.
 */
function aksara_font_details_save( $post_id ) {
	if ( ! isset( $_POST['aksara_font_details_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aksara_font_details_nonce'] ) ), 'aksara_font_details_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$text_fields = array(
		'_aksara_font_release' => 'aksara_font_release',
		'_aksara_font_updated' => 'aksara_font_updated',
	);
	foreach ( $text_fields as $meta_key => $field ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		aksara_font_details_put( $post_id, $meta_key, $value );
	}

	$area_fields = array(
		'_aksara_font_team'      => 'aksara_font_team',
		'_aksara_font_changelog' => 'aksara_font_changelog',
	);
	foreach ( $area_fields as $meta_key => $field ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
		aksara_font_details_put( $post_id, $meta_key, implode( "\n", aksara_font_details_lines( $value ) ) );
	}

	$extras = array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tiap ruas disanitasi satu per satu di bawah.
	$posted = isset( $_POST['aksara_font_extras'] ) ? wp_unslash( $_POST['aksara_font_extras'] ) : array();
	if ( is_array( $posted ) ) {
		foreach ( $posted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === trim( $label ) ) {
				continue; // Baris kosong dibuang — inilah yang membuat repeater tanpa JS itu bekerja.
			}
			$extras[] = array(
				'label' => $label,
				'url'   => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
				'text'  => isset( $row['text'] ) ? sanitize_text_field( $row['text'] ) : '',
			);
		}
	}
	if ( $extras ) {
		update_post_meta( $post_id, '_aksara_font_extras', $extras );
	} else {
		delete_post_meta( $post_id, '_aksara_font_extras' );
	}
}
add_action( 'save_post_ath_font', 'aksara_font_details_save' );

/**
 * Menulis meta, atau menghapusnya kalau nilainya kosong.
 *
 * Kosong berarti "tidak ada", bukan "ada tapi berisi string kosong": baris
 * meta yang tersisa hanya menambah beban tanpa pernah dibaca.
 *
 * @param int    $post_id  ID post.
 * @param string $meta_key Kunci meta.
 * @param string $value    Nilai bersih.
 */
function aksara_font_details_put( $post_id, $meta_key, $value ) {
	if ( '' === $value ) {
		delete_post_meta( $post_id, $meta_key );
		return;
	}
	update_post_meta( $post_id, $meta_key, $value );
}
