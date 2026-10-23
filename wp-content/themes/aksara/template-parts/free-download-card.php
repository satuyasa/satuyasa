<?php
/**
 * Satu kartu Free Download di Home.
 *
 * KENAPA KARTU BARU, BUKAN template-parts/free-font-row.php
 *
 * Pita spesimen di arsip Free Font hidup di dalam lingkup .foundry: seluruh
 * warna, jarak, dan tipografinya datang dari variabel yang hanya ada di
 * assets/css/foundry.css. Berkas itu SENGAJA hanya dimuat di halaman free
 * download (inc/free-fonts.php), karena ia menarik dua webfont Google. Memakai
 * pita itu di Home berarti membebani halaman paling ramai situs dengan dua
 * permintaan font demi satu bagian — atau merendernya tanpa gaya sama sekali.
 *
 * KENAPA TIDAK MENAMPILKAN SPESIMEN
 *
 * Tepat di atas bagian ini ada enam spesimen font berbayar setinggi layar.
 * Blok spesimen kedua untuk font gratis akan membuat yang gratis terlihat
 * setara dengan yang dijual, di halaman yang tugasnya justru menjual.
 * Spesimen sungguhannya tetap ada — satu klik jauhnya, di arsipnya sendiri.
 *
 * Kelasnya sengaja SAMA dengan .asset-card supaya kartu di sini berbaris
 * persis dengan grid template & element di atasnya, tanpa CSS grid baru.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fd_id      = get_the_ID();
$fd_type    = function_exists( 'aksara_free_type_label' )
	? aksara_free_type_label( aksara_free_meta( $fd_id, '_ath_free_download_type', 'font' ) )
	: '';
$fd_license = function_exists( 'ath_free_download_resolve_license' ) ? ath_free_download_resolve_license( $fd_id ) : array();
$fd_label   = ! empty( $fd_license['label'] ) ? $fd_license['label'] : aksara_free_meta( $fd_id, '_ath_free_download_license_label', '' );

/*
 * "Free" selalu di depan, lalu tipe, lalu lisensi — bagian yang bisa kosong
 * ada di belakang supaya barisnya tidak pernah diawali pemisah menggantung.
 */
$fd_meta = array_filter( array( __( 'Free', 'aksara' ), $fd_type, $fd_label ) );
?>
<div class="asset-card asset-card--free">
	<a href="<?php the_permalink(); ?>">
		<div class="asset-thumb">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'aksara-preview-sm', array( 'loading' => 'lazy', 'sizes' => '(max-width: 560px) 100vw, (max-width: 980px) 50vw, 25vw' ) ); ?>
			<?php else : ?>
				<?php
				/*
				 * Tanpa gambar unggulan, kotaknya TIDAK dibiarkan abu-abu
				 * kosong — nama rilisnya dicetak kecil dan redup. Bukan besar:
				 * ini toko huruf, dan nama font berukuran besar dalam font UI
				 * bisa dikira wujud fontnya. Prinsip yang sama dipakai
				 * .foundry-placeholder dan kartu related di halaman free.
				 */
				?>
				<span class="asset-thumb__fallback" aria-hidden="true"><?php echo esc_html( get_the_title() ); ?></span>
			<?php endif; ?>
		</div>
		<div class="asset-info">
			<h4><?php the_title(); ?></h4>
			<span><?php echo esc_html( implode( ' · ', $fd_meta ) ); ?></span>
		</div>
	</a>
</div>
