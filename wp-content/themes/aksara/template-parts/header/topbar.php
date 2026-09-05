<?php
/**
 * Bilah pengumuman opsional di atas header.
 *
 * TIDAK ADA APA-APA DI SINI SECARA BAWAAN. Setting-nya mati dan teksnya kosong,
 * jadi situs yang belum menyentuh Customizer menghasilkan HTML yang sama persis
 * seperti sebelum berkas ini ada. Itu disengaja: sebuah bilah pengumuman yang
 * muncul sendiri sesudah update adalah perubahan tampilan yang tidak diminta.
 *
 * Dua syarat harus terpenuhi — sakelar menyala DAN teks terisi. Sakelar saja
 * akan menghasilkan strip hitam kosong setinggi satu baris, dan itu terlihat
 * seperti kerusakan, bukan seperti fitur yang belum diisi.
 *
 * Bilahnya IKUT TERGULUNG, tidak lengket. Yang lengket hanya .site-header,
 * karena navigasi dan keranjang memang perlu selalu terjangkau; sebuah
 * pengumuman tidak, dan menahannya di layar hanya memakan tinggi viewport di
 * ponsel — tepat tempat yang paling sedikit ruangnya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_topbar_text = trim( aksara_mod( 'aksara_topbar_text' ) );

if ( ! aksara_mod( 'aksara_topbar_enabled' ) || '' === $aksara_topbar_text ) {
	return;
}

$aksara_topbar_url = aksara_mod( 'aksara_topbar_url' );
?>
<div class="site-topbar">
	<div class="wrap site-topbar__inner">
		<?php if ( '' !== $aksara_topbar_url ) : ?>
			<a class="site-topbar__link" href="<?php echo esc_url( $aksara_topbar_url ); ?>"><span class="site-topbar__text"><?php echo esc_html( $aksara_topbar_text ); ?></span> <span aria-hidden="true">&#8594;</span></a>
		<?php else : ?>
			<span class="site-topbar__text"><?php echo esc_html( $aksara_topbar_text ); ?></span>
		<?php endif; ?>
	</div>
</div>
