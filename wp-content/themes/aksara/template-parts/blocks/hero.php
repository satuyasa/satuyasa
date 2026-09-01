<?php
/**
 * Blok: Hero beranda. Judul & subjudul kini atribut blok (bisa disunting
 * langsung di editor), menggantikan get_theme_mod() yang hanya bisa diubah
 * lewat Customizer atau kode. Hitungan katalog tetap dinamis.
 *
 * @package Aksara
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow  = ! empty( $attributes['eyebrow'] ) ? $attributes['eyebrow'] : __( 'Independent font marketplace', 'aksara' );
$headline = ! empty( $attributes['headline'] ) ? $attributes['headline'] : __( 'The right type for your work.', 'aksara' );
$subtitle = ! empty( $attributes['subtitle'] ) ? $attributes['subtitle'] : __( 'Thousands of clearly licensed fonts, ready-made Canva templates, and design elements — all in one place, with a live preview before you buy.', 'aksara' );

$font_count     = function_exists( 'aksara_authentype_font_count' ) ? aksara_authentype_font_count() : 0;
$template_count = function_exists( 'aksara_count_products_by_type' ) ? aksara_count_products_by_type( 'canva_template' ) : 0;
$element_count  = function_exists( 'aksara_count_products_by_type' ) ? aksara_count_products_by_type( 'canva_element' ) : 0;
$search_action  = function_exists( 'aksara_authentype_archive_url' ) ? aksara_authentype_archive_url() : home_url( '/' );
?>
<section class="hero">
	<div class="wrap">
		<p class="eyebrow hero-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<h1 class="hero-headline"><?php echo esc_html( $headline ); ?></h1>
		<p class="hero-sub"><?php echo esc_html( $subtitle ); ?></p>

		<form class="hero-search" role="search" method="get" action="<?php echo esc_url( $search_action ); ?>">
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
