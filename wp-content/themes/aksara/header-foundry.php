<?php
/**
 * Kerangka header untuk halaman Free Font (sistem visual Foundry).
 *
 * KENAPA HEADER TERPISAH, BUKAN header.php BIASA
 *
 * DESIGN3.md menetapkan tata letaknya secara eksplisit: "Fixed-left sidebar
 * (≈200px) holding all primary navigation" dan "Don't center the page
 * layout". Itu bukan variasi warna dari header situs yang ada — itu struktur
 * halaman yang berbeda. Memakai header.php lalu menempelkan kanvas gelap di
 * bawahnya akan menghasilkan bilah terang di atas ruang hitam, persis yang
 * dilarang DESIGN3 ("no alternating light/dark bands").
 *
 * wp_head() dan wp_body_open() tetap dipanggil: keduanya kontrak WordPress,
 * bukan bagian dari desain, dan plugin apa pun berhak menyisipkan di sana.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_free_archive = function_exists( 'aksara_free_fonts_archive_url' ) ? aksara_free_fonts_archive_url() : home_url( '/' );
$aksara_font_archive = function_exists( 'aksara_authentype_archive_url' ) ? aksara_authentype_archive_url() : home_url( '/' );
$aksara_free_count   = function_exists( 'wp_count_posts' ) ? (int) wp_count_posts( 'ath_free_download' )->publish : 0;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="foundry">
	<a class="skip-link screen-reader-text" href="#foundry-main"><?php esc_html_e( 'Skip to content', 'aksara' ); ?></a>

	<div class="foundry-ticker">
		<span><?php esc_html_e( 'Free fonts, released under a clear license', 'aksara' ); ?></span>
		<span><?php esc_html_e( 'New releases every month', 'aksara' ); ?></span>
		<span><?php esc_html_e( 'Read the license before you ship', 'aksara' ); ?></span>
	</div>

	<div class="foundry-shell">
		<aside class="foundry-sidebar">
			<a class="foundry-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span><?php bloginfo( 'name' ); ?></span>
				<span><?php esc_html_e( 'Free.', 'aksara' ); ?></span>
			</a>

			<nav aria-label="<?php esc_attr_e( 'Free font navigation', 'aksara' ); ?>">
				<p class="foundry-nav-title"><?php esc_html_e( 'Catalog', 'aksara' ); ?></p>
				<div class="foundry-nav">
					<a class="foundry-tag" href="<?php echo esc_url( $aksara_free_archive ); ?>"<?php echo is_post_type_archive( 'ath_free_download' ) ? ' aria-current="page"' : ''; ?>>
						<?php esc_html_e( 'Free fonts', 'aksara' ); ?>
						<?php if ( $aksara_free_count ) : ?><em><?php echo esc_html( number_format_i18n( $aksara_free_count ) ); ?></em><?php endif; ?>
					</a>
					<a class="foundry-tag" href="<?php echo esc_url( $aksara_font_archive ); ?>"><?php esc_html_e( 'Retail fonts', 'aksara' ); ?></a>
				</div>
			</nav>

			<nav aria-label="<?php esc_attr_e( 'Site navigation', 'aksara' ); ?>">
				<p class="foundry-nav-title"><?php esc_html_e( 'Elsewhere', 'aksara' ); ?></p>
				<div class="foundry-nav">
					<a class="foundry-tag" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'aksara' ); ?></a>
					<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
						<a class="foundry-tag" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Cart', 'aksara' ); ?></a>
					<?php endif; ?>
				</div>
			</nav>

			<div class="foundry-sidebar-foot">
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php echo esc_html( is_user_logged_in() ? __( 'My account', 'aksara' ) : __( 'Sign in', 'aksara' ) ); ?></a>
				<?php endif; ?>
				<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
			</div>
		</aside>

		<div class="foundry-canvas">
			<main id="foundry-main">
