<?php
/**
 * Baris paling bawah footer: hak cipta dan satu baris penutup.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="footer-bottom">
	<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.</span>
	<span><?php esc_html_e( 'Made for Indonesian creators.', 'aksara' ); ?></span>
</div>
