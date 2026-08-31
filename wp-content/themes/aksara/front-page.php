<?php
/**
 * Halaman depan (Home) — mengikuti struktur mockup-home.html: hero,
 * kategori, daftar font pilihan, grid template/element terbaru, trust section.
 *
 * Versi Fase 1: statis, TANPA animasi font-cycling & TANPA merender font
 * asli produk di daftar spesimen (lihat template-parts/font-specimen-row.php
 * untuk alasan keamanannya) — sesuai cakupan Breakdown Task Fase 1.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$font_count     = function_exists( 'aksara_count_products_by_type' ) ? aksara_count_products_by_type( 'font' ) : 0;
$template_count = function_exists( 'aksara_count_products_by_type' ) ? aksara_count_products_by_type( 'canva_template' ) : 0;
$element_count  = function_exists( 'aksara_count_products_by_type' ) ? aksara_count_products_by_type( 'canva_element' ) : 0;
?>

<section class="hero">
	<div class="wrap">
		<h1 class="hero-headline"><?php echo esc_html( get_theme_mod( 'aksara_hero_title', __( 'Huruf yang tepat untuk karyamu.', 'aksara' ) ) ); ?></h1>
		<p class="hero-sub"><?php echo esc_html( get_theme_mod( 'aksara_hero_subtitle', __( 'Ribuan font berlisensi jelas, template Canva siap pakai, dan elemen desain — semua dalam satu tempat, dengan pratinjau langsung sebelum kamu beli.', 'aksara' ) ) ); ?></p>

		<form class="hero-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="text" name="s" placeholder="<?php esc_attr_e( 'Cari nama font, gaya, atau kategori…', 'aksara' ); ?>">
			<button type="submit"><?php esc_html_e( 'Cari', 'aksara' ); ?></button>
		</form>

		<div class="hero-stats">
			<div class="stat"><b><?php echo esc_html( number_format_i18n( $font_count ) ); ?></b><span><?php esc_html_e( 'Font tersedia', 'aksara' ); ?></span></div>
			<div class="stat"><b><?php echo esc_html( number_format_i18n( $template_count ) ); ?></b><span><?php esc_html_e( 'Template Canva', 'aksara' ); ?></span></div>
			<div class="stat"><b><?php echo esc_html( number_format_i18n( $element_count ) ); ?></b><span><?php esc_html_e( 'Elemen desain', 'aksara' ); ?></span></div>
		</div>
	</div>
</section>

<section class="categories">
	<div class="wrap cat-grid">
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'fonts' ) ); ?>">
			<div><h3><?php esc_html_e( 'Fonts', 'aksara' ); ?></h3><p><?php esc_html_e( 'Beli per style, dengan lisensi sesuai kebutuhanmu — desktop, web, atau komersial.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Jelajahi font', 'aksara' ); ?></span>
		</a>
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'templates' ) ); ?>">
			<div><h3><?php esc_html_e( 'Canva Templates', 'aksara' ); ?></h3><p><?php esc_html_e( 'Desain siap edit untuk media sosial, resume, presentasi, dan lainnya.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Jelajahi template', 'aksara' ); ?></span>
		</a>
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'elements' ) ); ?>">
			<div><h3><?php esc_html_e( 'Canva Elements', 'aksara' ); ?></h3><p><?php esc_html_e( 'Ikon, ilustrasi, dan bentuk grafis untuk melengkapi desainmu.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Jelajahi elemen', 'aksara' ); ?></span>
		</a>
	</div>
</section>

<section class="section">
	<div class="wrap">
		<div class="section-head">
			<h2><?php esc_html_e( 'Font pilihan minggu ini', 'aksara' ); ?></h2>
			<a href="<?php echo esc_url( aksara_get_listing_url( 'fonts' ) ); ?>"><?php esc_html_e( 'Lihat semua font', 'aksara' ); ?></a>
		</div>

		<?php
		$font_query = function_exists( 'aksara_query_products_by_type' ) ? aksara_query_products_by_type( 'font', 6 ) : null;
		if ( $font_query && $font_query->have_posts() ) :
			?>
			<div class="specimen-list">
				<?php
				while ( $font_query->have_posts() ) :
					$font_query->the_post();
					get_template_part( 'template-parts/font-specimen-row' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Belum ada font yang dipublikasikan.', 'aksara' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="section section--last">
	<div class="wrap">
		<div class="section-head">
			<h2><?php esc_html_e( 'Template & elemen terbaru', 'aksara' ); ?></h2>
			<a href="<?php echo esc_url( aksara_get_listing_url( 'templates' ) ); ?>"><?php esc_html_e( 'Lihat semua', 'aksara' ); ?></a>
		</div>

		<?php
		$assets_query = null;
		if ( function_exists( 'aksara_query_products_by_type' ) ) {
			$assets_query = new WP_Query( array(
				'post_type'      => 'product',
				'posts_per_page' => 8,
				'post_status'    => 'publish',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => array( 'canva_template', 'canva_element' ),
					),
				),
			) );
		}

		if ( $assets_query && $assets_query->have_posts() ) :
			?>
			<div class="asset-grid">
				<?php
				while ( $assets_query->have_posts() ) :
					$assets_query->the_post();
					get_template_part( 'template-parts/asset-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Belum ada template atau elemen yang dipublikasikan.', 'aksara' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="trust">
	<div class="wrap">
		<div>
			<h3><?php esc_html_e( 'Lisensi yang jelas', 'aksara' ); ?></h3>
			<p><?php esc_html_e( 'Setiap font punya rincian lisensi lengkap — desktop, web, aplikasi, hingga komersial — tanpa istilah membingungkan.', 'aksara' ); ?></p>
		</div>
		<div>
			<h3><?php esc_html_e( 'Coba sebelum beli', 'aksara' ); ?></h3>
			<p><?php esc_html_e( 'Ketik teksmu sendiri dan lihat langsung tampilan tiap style font, tanpa perlu mengunduh apa pun.', 'aksara' ); ?></p>
		</div>
		<div>
			<h3><?php esc_html_e( 'File aman terjaga', 'aksara' ); ?></h3>
			<p><?php esc_html_e( 'File asli hanya dikirim setelah pembayaran berhasil, dengan tautan unduh yang terlindungi.', 'aksara' ); ?></p>
		</div>
	</div>
</section>

<?php
get_footer();
