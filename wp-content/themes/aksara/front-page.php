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
		<p class="eyebrow hero-eyebrow"><?php esc_html_e( 'Independent font marketplace', 'aksara' ); ?></p>
		<h1 class="hero-headline"><?php echo esc_html( get_theme_mod( 'aksara_hero_title', __( 'The right type for your work.', 'aksara' ) ) ); ?></h1>
		<p class="hero-sub"><?php echo esc_html( get_theme_mod( 'aksara_hero_subtitle', __( 'Thousands of clearly licensed fonts, ready-made Canva templates, and design elements — all in one place, with a live preview before you buy.', 'aksara' ) ) ); ?></p>

		<form class="hero-search" role="search" method="get" action="<?php echo esc_url( function_exists( 'aksara_authentype_archive_url' ) ? aksara_authentype_archive_url() : home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="home-font-search"><?php esc_html_e( 'Search font families', 'aksara' ); ?></label>
			<input id="home-font-search" type="search" name="q" placeholder="<?php esc_attr_e( 'Search font families…', 'aksara' ); ?>">
			<button type="submit"><?php esc_html_e( 'Search', 'aksara' ); ?></button>
		</form>
		<div class="hero-links" aria-label="<?php esc_attr_e( 'Browse product collections', 'aksara' ); ?>">
			<a href="<?php echo esc_url( aksara_get_listing_url( 'fonts' ) ); ?>"><?php esc_html_e( 'Font library', 'aksara' ); ?></a>
			<a href="<?php echo esc_url( aksara_get_listing_url( 'templates' ) ); ?>"><?php esc_html_e( 'Canva templates', 'aksara' ); ?></a>
			<a href="<?php echo esc_url( aksara_get_listing_url( 'elements' ) ); ?>"><?php esc_html_e( 'Design elements', 'aksara' ); ?></a>
		</div>

		<div class="hero-stats">
			<div class="stat"><b><?php echo esc_html( number_format_i18n( $font_count ) ); ?></b><span><?php esc_html_e( 'Fonts available', 'aksara' ); ?></span></div>
			<div class="stat"><b><?php echo esc_html( number_format_i18n( $template_count ) ); ?></b><span><?php esc_html_e( 'Canva templates', 'aksara' ); ?></span></div>
			<div class="stat"><b><?php echo esc_html( number_format_i18n( $element_count ) ); ?></b><span><?php esc_html_e( 'Design elements', 'aksara' ); ?></span></div>
		</div>
	</div>
</section>

<section class="categories">
	<div class="wrap cat-grid">
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'fonts' ) ); ?>">
			<div><h3><?php esc_html_e( 'Fonts', 'aksara' ); ?></h3><p><?php esc_html_e( 'Buy per style, with the license you actually need — desktop, web, or commercial.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Browse fonts', 'aksara' ); ?></span>
		</a>
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'templates' ) ); ?>">
			<div><h3><?php esc_html_e( 'Canva Templates', 'aksara' ); ?></h3><p><?php esc_html_e( 'Ready-to-edit designs for social media, resumes, presentations, and more.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Browse templates', 'aksara' ); ?></span>
		</a>
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'elements' ) ); ?>">
			<div><h3><?php esc_html_e( 'Canva Elements', 'aksara' ); ?></h3><p><?php esc_html_e( 'Icons, illustrations, and graphic shapes to finish your design.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Browse elements', 'aksara' ); ?></span>
		</a>
	</div>
</section>

<section class="section">
	<div class="wrap">
		<div class="section-head">
			<h2><?php esc_html_e( 'This week\'s featured fonts', 'aksara' ); ?></h2>
			<a href="<?php echo esc_url( aksara_get_listing_url( 'fonts' ) ); ?>"><?php esc_html_e( 'View all fonts', 'aksara' ); ?></a>
		</div>

		<?php
		$font_query = function_exists( 'aksara_query_products_by_type' ) ? aksara_query_products_by_type( 'font', 6 ) : null;
		if ( function_exists( 'aksara_authentype_enqueue_preview' ) ) {
			aksara_authentype_enqueue_preview();
		}
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
			<p><?php esc_html_e( 'No fonts have been published yet.', 'aksara' ); ?></p>
		<?php endif; ?>
	</div>
</section>

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
<section class="section section--last">
	<div class="wrap">
		<div class="section-head">
			<h2><?php esc_html_e( 'Latest templates & elements', 'aksara' ); ?></h2>
			<a href="<?php echo esc_url( aksara_get_listing_url( 'templates' ) ); ?>"><?php esc_html_e( 'View all', 'aksara' ); ?></a>
		</div>
			<div class="asset-grid">
				<?php
				while ( $assets_query->have_posts() ) :
					$assets_query->the_post();
					get_template_part( 'template-parts/asset-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
	</div>
</section>
<?php endif; ?>

<section class="trust">
	<div class="wrap">
		<div>
			<span class="trust-index" aria-hidden="true">01</span>
			<h3><?php esc_html_e( 'Licensing you can actually read', 'aksara' ); ?></h3>
			<p><?php esc_html_e( 'Every font comes with its full licensing terms — desktop, web, app, and commercial — without the confusing jargon.', 'aksara' ); ?></p>
		</div>
		<div>
			<span class="trust-index" aria-hidden="true">02</span>
			<h3><?php esc_html_e( 'Try before you buy', 'aksara' ); ?></h3>
			<p><?php esc_html_e( 'Type your own text and see every style live, without downloading anything.', 'aksara' ); ?></p>
		</div>
		<div>
			<span class="trust-index" aria-hidden="true">03</span>
			<h3><?php esc_html_e( 'Your files stay protected', 'aksara' ); ?></h3>
			<p><?php esc_html_e( 'Original files are only released after successful payment, through a protected download link.', 'aksara' ); ?></p>
		</div>
	</div>
</section>

<?php
get_footer();
