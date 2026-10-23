<?php
/**
 * Alat baca-saja: kenapa badge diskon menampilkan angka yang tidak diketik.
 *
 * MASALAH YANG DIJAWAB BERKAS INI
 *
 * Kolom "Discount %" di matriks harga Authentype TIDAK punya atribut name
 * (admin-metaboxes.php:487). Ia tidak pernah terkirim dan tidak pernah
 * disimpan — hanya alat bantu di peramban untuk mengisikan harga jual. Yang
 * tersimpan cuma harga normal dan harga jual; SETIAP tampilan persen sesudah
 * itu menghitung ulang:
 *
 *     round( ( ( normal - jual ) / normal ) * 100 )
 *
 * Akibatnya badge berbunyi 31% begitu harga jual turun ke 0,695 x normal —
 * hanya 0,5% di bawah 70%. Harga 39 dengan diskon 30% seharusnya 27,30;
 * diketik rapi jadi 27 saja sudah menghasilkan 30,77% dan dibulatkan ke 31%.
 *
 * Ditambah satu hal lagi: aksara_product_discount_data() mengambil MAKSIMUM
 * dari seluruh variasi. Satu sel gaya x lisensi yang meleset dari puluhan sel
 * sudah cukup mengubah badge seluruh produk.
 *
 * Jadi badge-nya TIDAK salah hitung — ia melaporkan diskon yang sebenarnya.
 * Yang meleset harganya. Alat ini menunjukkan variasi mana persisnya.
 *
 * Baca-saja: tidak menulis apa pun, tidak mengubah harga.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Daftarkan di bawah Products, tempat orang mencari soal harga. */
function aksara_discount_audit_menu() {
	if ( ! post_type_exists( 'product' ) ) {
		return;
	}
	add_submenu_page(
		'edit.php?post_type=product',
		__( 'Discount audit', 'aksara' ),
		__( 'Discount audit', 'aksara' ),
		'manage_woocommerce',
		'aksara-discount-audit',
		'aksara_discount_audit_screen'
	);
}
add_action( 'admin_menu', 'aksara_discount_audit_menu' );

/**
 * Persen sungguhan per variasi, memakai rumus yang SAMA dengan badge.
 *
 * Sengaja tidak memakai get_variation_prices( true ): argumen itu menyalakan
 * penyesuaian pajak dan pembulatan tampilan, dan di layar diagnosis kita ingin
 * melihat angka yang tersimpan apa adanya.
 *
 * @param WC_Product $product Produk.
 * @return array<int,array<string,mixed>>
 */
function aksara_discount_audit_rows( $product ) {
	$rows = array();
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return $rows;
	}
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			continue;
		}
		$regular = (float) $variation->get_regular_price();
		$sale    = (float) $variation->get_sale_price();
		if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
			continue;
		}
		$exact = ( ( $regular - $sale ) / $regular ) * 100;
		$rows[] = array(
			'name'    => implode( ' / ', array_map( 'wc_attribute_label', $variation->get_attributes() ) ),
			'regular' => $regular,
			'sale'    => $sale,
			'exact'   => $exact,
			// floor(), sama dengan badge — lihat alasannya di
			// aksara_product_discount_data().
			'shown'   => (int) floor( $exact ),
		);
	}
	return $rows;
}

/** Layarnya. */
function aksara_discount_audit_screen() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this.', 'aksara' ) );
	}

	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
	) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Discount audit', 'aksara' ); ?></h1>
		<p><?php esc_html_e( 'The percentage on a product badge is never stored — it is recalculated from the regular and sale price every time it is shown, and the badge reports the highest percentage across all variations. A badge that reads one point higher than you intended means one variation is priced slightly below the round figure, not that the badge miscounted.', 'aksara' ); ?></p>
		<p><?php esc_html_e( 'Rows highlighted below are the ones pulling a badge away from a round number.', 'aksara' ); ?></p>

		<?php foreach ( $products as $product_id ) : ?>
			<?php
			$product = wc_get_product( $product_id );
			$rows    = aksara_discount_audit_rows( $product );
			if ( ! $rows ) {
				continue;
			}
			$shown = wp_list_pluck( $rows, 'shown' );
			$badge = max( $shown );
			$mixed = count( array_unique( $shown ) ) > 1;
			?>
			<h2 style="margin-top:2em">
				<?php echo esc_html( get_the_title( $product_id ) ); ?>
				— <?php printf( esc_html__( 'badge shows %d%%', 'aksara' ), (int) $badge ); ?>
				<?php if ( $mixed ) : ?><em style="font-weight:400"><?php esc_html_e( '(variations disagree)', 'aksara' ); ?></em><?php endif; ?>
			</h2>
			<table class="widefat striped" style="max-width:60em">
				<thead><tr>
					<th><?php esc_html_e( 'Variation', 'aksara' ); ?></th>
					<th><?php esc_html_e( 'Regular', 'aksara' ); ?></th>
					<th><?php esc_html_e( 'Sale', 'aksara' ); ?></th>
					<th><?php esc_html_e( 'Actual discount', 'aksara' ); ?></th>
					<th><?php esc_html_e( 'Shown as', 'aksara' ); ?></th>
					<th><?php esc_html_e( 'Sale price for an exact round figure', 'aksara' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$off    = abs( $row['exact'] - $row['shown'] ) > 0.001;
					$target = $row['regular'] * ( 1 - ( $row['shown'] / 100 ) );
					?>
					<tr<?php echo $off ? ' style="background:#fff5e5"' : ''; ?>>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row['regular'] ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row['sale'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['exact'], 2 ) ); ?>%</td>
						<td><strong><?php echo (int) $row['shown']; ?>%</strong></td>
						<td><?php echo $off ? wp_kses_post( wc_price( $target ) ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</div>
	<?php
}
