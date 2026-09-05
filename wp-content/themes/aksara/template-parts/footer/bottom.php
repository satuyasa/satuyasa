<?php
/**
 * Baris paling bawah footer: hak cipta dan satu baris penutup.
 *
 * Hak ciptanya tidak bisa diubah dari admin dan memang tidak perlu: tahunnya
 * dari date_i18n() dan namanya dari nama situs, jadi keduanya sudah mengikuti
 * pengaturan yang ada. Yang bisa diubah cuma baris penutup di kanan.
 *
 * Baris penutup yang dikosongkan menghilangkan <span>-nya, bukan menyisakan
 * span kosong: .footer-bottom memakai justify-content: space-between, jadi span
 * kosong tetap menahan ruang di kanan dan membuat hak cipta seolah tidak rata.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_footer_note = trim( aksara_mod( 'aksara_footer_note' ) );
?>
<div class="footer-bottom">
	<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.</span>
	<?php if ( '' !== $aksara_footer_note ) : ?>
		<span><?php echo esc_html( $aksara_footer_note ); ?></span>
	<?php endif; ?>
</div>
