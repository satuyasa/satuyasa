<?php
/**
 * Baris tambahan DI DALAM daftar spesifikasi "Product details".
 *
 * Dipisah dari template-parts/font-details.php karena tempat cetaknya berbeda:
 * berkas ini menghasilkan <div><dt>..</dt><dd>..</dd></div> yang harus berada di
 * dalam <dl class="font-spec">, sementara berkas satunya menghasilkan blok
 * <section> yang harus berada SETELAH </dl>. Menggabungkan keduanya berarti
 * mencetak <section> di dalam <dl> — markup yang tidak sah, dan browser akan
 * memindahkannya keluar dengan cara yang tidak bisa ditebak.
 *
 * Dua baris pertama DITURUNKAN dari data style Authentype, bukan diketik:
 * lihat aksara_authentype_style_facts(). Tiga sisanya memang tidak diketahui
 * sistem dan diisi di metabox.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fs_facts   = isset( $args['facts'] ) && is_array( $args['facts'] ) ? $args['facts'] : array();
$fs_details = function_exists( 'aksara_font_details' ) ? aksara_font_details( get_the_ID() ) : array();

// Rentang bobot hanya bermakna kalau ada lebih dari satu nilai. Keluarga satu
// bobot akan menampilkan "400-400", yang terbaca seperti kesalahan.
$fs_wmin = isset( $fs_facts['weight_min'] ) ? (int) $fs_facts['weight_min'] : 0;
$fs_wmax = isset( $fs_facts['weight_max'] ) ? (int) $fs_facts['weight_max'] : 0;
?>

<?php if ( $fs_wmin && $fs_wmax ) : ?>
	<div>
		<dt><?php esc_html_e( 'Weights', 'aksara' ); ?></dt>
		<dd><?php echo esc_html( $fs_wmin === $fs_wmax ? (string) $fs_wmin : $fs_wmin . '–' . $fs_wmax ); ?></dd>
	</div>
<?php endif; ?>

<?php
// Hanya dinyatakan kalau memang ADA style untuk dilihat. Keluarga tanpa satu
// pun style bukan keluarga tanpa italic — ia keluarga yang datanya belum
// diisi, dan "Not included" di situ adalah klaim yang tidak dibuktikan apa pun.
?>
<?php if ( ! empty( $fs_facts['count'] ) && array_key_exists( 'has_italic', $fs_facts ) ) : ?>
	<div>
		<dt><?php esc_html_e( 'Italics', 'aksara' ); ?></dt>
		<dd><?php echo esc_html( $fs_facts['has_italic'] ? __( 'Included', 'aksara' ) : __( 'Not included', 'aksara' ) ); ?></dd>
	</div>
<?php endif; ?>

<?php if ( ! empty( $fs_details['formats'] ) ) : ?>
	<div>
		<dt><?php esc_html_e( 'Formats', 'aksara' ); ?></dt>
		<dd><?php echo esc_html( $fs_details['formats'] ); ?></dd>
	</div>
<?php endif; ?>

<?php if ( ! empty( $fs_details['languages'] ) ) : ?>
	<div>
		<dt><?php esc_html_e( 'Languages', 'aksara' ); ?></dt>
		<dd><?php echo esc_html( $fs_details['languages'] ); ?></dd>
	</div>
<?php endif; ?>

<?php if ( ! empty( $fs_details['version'] ) ) : ?>
	<div>
		<dt><?php esc_html_e( 'Version', 'aksara' ); ?></dt>
		<dd><?php echo esc_html( $fs_details['version'] ); ?></dd>
	</div>
<?php endif; ?>
