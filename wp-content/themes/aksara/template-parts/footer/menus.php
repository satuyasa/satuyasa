<?php
/**
 * Empat kolom footer: identitas situs + tiga menu.
 *
 * Judul kolom tetap dicetak walau menunya belum diisi, supaya kerangka
 * footernya tidak berubah bentuk begitu admin menambahkan menu. Perilaku itu
 * diambil apa adanya dari footer.php lama.
 *
 * Judulnya sendiri kini datang dari Customizer, dengan nilai bawaan yang persis
 * sama seperti sebelumnya. Yang bisa diubah hanya judulnya — JUMLAH KOLOMNYA
 * TETAP TIGA, karena .footer-grid punya lebar kolom yang ditetapkan
 * (1.4fr 1fr 1fr 1fr) beserta titik putusnya di 782px dan 560px. Kolom yang
 * bisa ditambah-kurang dari admin akan membuat semua itu meleset.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_footer_menus = array(
	'footer_shop'  => aksara_mod( 'aksara_footer_heading_shop' ),
	'footer_help'  => aksara_mod( 'aksara_footer_heading_help' ),
	'footer_about' => aksara_mod( 'aksara_footer_heading_about' ),
);
?>
<div class="footer-grid">
	<div>
		<p class="site-title"><?php bloginfo( 'name' ); ?></p>
		<p class="footer-tagline"><?php bloginfo( 'description' ); ?></p>
		<?php get_template_part( 'template-parts/footer/social' ); ?>
	</div>
	<?php foreach ( $aksara_footer_menus as $aksara_location => $aksara_heading ) : ?>
		<div>
			<?php
			/*
			 * <h2>, bukan <h5>. Footer ada di SETIAP halaman, dan judul
			 * kolomnya dulu <h5> — sehingga pada halaman yang judul
			 * terdalamnya h2, struktur judul melompat 2->5 (di halaman 404
			 * bahkan 1->5). Pembaca layar memakai daftar judul untuk
			 * melompat antar bagian, dan tingkat yang dilewati membuat
			 * daftar itu berbohong soal susunan halaman.
			 *
			 * Ukuran visualnya tidak berubah sedikit pun: .footer-grid h5
			 * sudah menetapkan 12px sendiri, dan selektornya tinggal
			 * mengikuti elemen barunya.
			 */
			?>
			<h2><?php echo esc_html( $aksara_heading ); ?></h2>
			<?php
			if ( has_nav_menu( $aksara_location ) ) {
				wp_nav_menu( array( 'theme_location' => $aksara_location, 'container' => false, 'depth' => 1 ) );
			}
			?>
		</div>
	<?php endforeach; ?>
</div>
