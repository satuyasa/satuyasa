<?php
/**
 * Template Name: Aksara — Halaman License
 *
 * Presentasi publik lisensi. Template ini hanya menampilkan ketentuan; ia
 * tidak membuat, mengubah, atau menggantikan data lisensi pada Authentype /
 * WooCommerce.
 *
 * PERHATIAN — enam lisensi di bawah DIPATOK di berkas ini, bukan dibaca dari
 * yang benar-benar dijual toko (_ath_license_options per keluarga font).
 * Itu keputusan sadar: struktur Overview / Allowed / Prohibited / Limitations
 * tidak disimpan Authentype di mana pun, jadi tidak ada yang bisa dibaca.
 * Konsekuensinya harus diketahui: kalau daftar lisensi yang dijual berubah,
 * halaman ini TIDAK ikut berubah dan akan diam-diam salah. Nama merek dan
 * seluruh teksnya bisa diedit di Customizer; daftar enamnya tidak.
 *
 * @package Aksara
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$licenses = array(
	'desktop' => array( 'name' => 'Desktop', 'number' => '01', 'overview' => aksara_mod( 'aksara_license_desktop_overview' ), 'allowed' => aksara_mod( 'aksara_license_desktop_allowed' ), 'prohibited' => aksara_mod( 'aksara_license_desktop_prohibited' ), 'limitations' => aksara_mod( 'aksara_license_desktop_limitations' ) ),
	'webfont' => array( 'name' => 'Webfont', 'number' => '02', 'overview' => aksara_mod( 'aksara_license_webfont_overview' ), 'allowed' => aksara_mod( 'aksara_license_webfont_allowed' ), 'prohibited' => aksara_mod( 'aksara_license_webfont_prohibited' ), 'limitations' => aksara_mod( 'aksara_license_webfont_limitations' ) ),
	'app' => array( 'name' => 'App', 'number' => '03', 'overview' => aksara_mod( 'aksara_license_app_overview' ), 'allowed' => aksara_mod( 'aksara_license_app_allowed' ), 'prohibited' => aksara_mod( 'aksara_license_app_prohibited' ), 'limitations' => aksara_mod( 'aksara_license_app_limitations' ) ),
	'epub' => array( 'name' => 'ePub', 'number' => '04', 'overview' => aksara_mod( 'aksara_license_epub_overview' ), 'allowed' => aksara_mod( 'aksara_license_epub_allowed' ), 'prohibited' => aksara_mod( 'aksara_license_epub_prohibited' ), 'limitations' => aksara_mod( 'aksara_license_epub_limitations' ) ),
	'server' => array( 'name' => 'Server', 'number' => '05', 'overview' => aksara_mod( 'aksara_license_server_overview' ), 'allowed' => aksara_mod( 'aksara_license_server_allowed' ), 'prohibited' => aksara_mod( 'aksara_license_server_prohibited' ), 'limitations' => aksara_mod( 'aksara_license_server_limitations' ) ),
	'extended' => array( 'name' => 'Extended', 'number' => '06', 'overview' => aksara_mod( 'aksara_license_extended_overview' ), 'allowed' => aksara_mod( 'aksara_license_extended_allowed' ), 'prohibited' => aksara_mod( 'aksara_license_extended_prohibited' ), 'limitations' => aksara_mod( 'aksara_license_extended_limitations' ) ),
);

if ( ! function_exists( 'aksara_license_bullets' ) ) {
	/** Render a newline-separated editable list safely. */
	function aksara_license_bullets( $value ) {
		$items = preg_split( '/\r\n|\r|\n/', trim( (string) $value ) );
		$items = array_filter( array_map( 'trim', $items ) );
		if ( ! $items ) { return; }
		echo '<ul class="license-page__list">';
		foreach ( $items as $item ) { echo '<li>' . esc_html( $item ) . '</li>'; }
		echo '</ul>';
	}
}
?>

<main id="primary" class="license-page">
	<section class="license-page__hero">
		<p class="license-page__eyebrow"><?php echo esc_html( aksara_mod( 'aksara_license_eyebrow' ) ); ?></p>
		<h1><?php the_title(); ?></h1>
		<p class="license-page__intro"><?php echo esc_html( aksara_mod( 'aksara_license_intro' ) ); ?></p>
		<div class="license-page__meta"><span><?php echo esc_html( aksara_mod( 'aksara_license_brand' ) ); ?></span><span aria-hidden="true">/</span><span><?php printf( esc_html__( 'Issued by %s', 'aksara' ), esc_html( aksara_mod( 'aksara_license_brand' ) ) ); ?></span><span aria-hidden="true">/</span><span><?php printf( esc_html__( 'Type Foundry: %s', 'aksara' ), esc_html( aksara_mod( 'aksara_license_foundry' ) ) ); ?></span></div>
	</section>

	<?php while ( have_posts() ) : the_post(); ?>
		<?php if ( trim( get_the_content() ) ) : ?><section class="license-page__editorial entry-content"><?php the_content(); ?></section><?php endif; ?>
	<?php endwhile; ?>

	<section class="license-page__guide" aria-labelledby="license-guide-title">
		<div class="license-page__section-label">00 / <?php esc_html_e( 'How to choose', 'aksara' ); ?></div>
		<div><h2 id="license-guide-title"><?php echo esc_html( aksara_mod( 'aksara_license_guide_title' ) ); ?></h2><p><?php echo esc_html( aksara_mod( 'aksara_license_guide_text' ) ); ?></p></div>
	</section>

	<section class="license-page__catalogue" aria-labelledby="license-catalogue-title">
		<header class="license-page__section-head"><div class="license-page__section-label">01 / <?php esc_html_e( 'License catalogue', 'aksara' ); ?></div><div><h2 id="license-catalogue-title"><?php echo esc_html( aksara_mod( 'aksara_license_catalogue_title' ) ); ?></h2><p><?php echo esc_html( aksara_mod( 'aksara_license_catalogue_note' ) ); ?></p></div></header>
		<div class="license-page__items">
			<?php foreach ( $licenses as $license ) : ?>
				<article class="license-page-item">
					<div class="license-page-item__index"><?php echo esc_html( $license['number'] ); ?></div>
					<div class="license-page-item__title"><h3><?php echo esc_html( $license['name'] ); ?></h3></div>
					<div class="license-page-item__body"><div class="license-page__overview"><?php echo nl2br( esc_html( $license['overview'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sudah di-escape sebelum nl2br. ?></div><div class="license-page__detail-grid"><div><h4><?php esc_html_e( 'Allowed Uses', 'aksara' ); ?></h4><?php aksara_license_bullets( $license['allowed'] ); ?></div><div><h4><?php esc_html_e( 'Prohibited Uses', 'aksara' ); ?></h4><?php aksara_license_bullets( $license['prohibited'] ); ?></div><div><h4><?php esc_html_e( 'Limitations', 'aksara' ); ?></h4><p><?php echo nl2br( esc_html( $license['limitations'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sudah di-escape sebelum nl2br. ?></p></div></div></div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="license-page__ip" aria-labelledby="license-ip-title"><div class="license-page__section-label">02 / <?php esc_html_e( 'Ownership', 'aksara' ); ?></div><div><h2 id="license-ip-title"><?php esc_html_e( 'Intellectual Property', 'aksara' ); ?></h2><p><?php echo esc_html( aksara_mod( 'aksara_license_ip_text' ) ); ?></p></div></section>
	<section class="license-page__contact" aria-labelledby="license-contact-title"><div><p class="license-page__eyebrow"><?php esc_html_e( 'Need a different scope?', 'aksara' ); ?></p><h2 id="license-contact-title"><?php echo esc_html( aksara_mod( 'aksara_license_contact_title' ) ); ?></h2></div><div><p><?php echo esc_html( aksara_mod( 'aksara_license_contact_text' ) ); ?></p><p class="license-page__contact-meta">Email: <?php echo antispambot( esc_html( sanitize_email( aksara_mod( 'aksara_contact_email' ) ? aksara_mod( 'aksara_contact_email' ) : get_option( 'admin_email' ) ) ) ); ?><br><?php if ( aksara_mod( 'aksara_license_website' ) ) : ?>Website: <a href="<?php echo esc_url( aksara_mod( 'aksara_license_website' ) ); ?>"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( aksara_mod( 'aksara_license_website' ) ) ) ); ?></a><br><?php endif; ?><?php printf( esc_html__( 'License issued by %1$s &middot; Type Foundry: %2$s', 'aksara' ), esc_html( aksara_mod( 'aksara_license_brand' ) ), esc_html( aksara_mod( 'aksara_license_foundry' ) ) ); ?></p><a class="button button--solid" href="mailto:<?php echo antispambot( esc_attr( sanitize_email( aksara_mod( 'aksara_contact_email' ) ? aksara_mod( 'aksara_contact_email' ) : get_option( 'admin_email' ) ) ) ); ?>"><?php echo esc_html( aksara_mod( 'aksara_license_contact_label' ) ); ?></a></div></section>
</main>

<?php get_footer(); ?>
