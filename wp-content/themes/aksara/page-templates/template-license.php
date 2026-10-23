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

/* Enam slug yang ketentuannya SUDAH ditulis di Customizer. Ini bukan daftar
 * yang dirender — ini kamus nama tampilan. Yang dirender ditentukan toko. */
$aksara_license_names = array(
	'desktop'  => __( 'Desktop', 'aksara' ),
	'webfont'  => __( 'Webfont', 'aksara' ),
	'app'      => __( 'App', 'aksara' ),
	'epub'     => __( 'ePub', 'aksara' ),
	'server'   => __( 'Server', 'aksara' ),
	'extended' => __( 'Extended', 'aksara' ),
);

/* Daftar kartu mengikuti lisensi yang benar-benar ditawarkan keluarga font
 * terbit (lihat aksara_authentype_sold_licenses()). Yang ketentuannya sudah
 * ditulis tapi tidak dijual di mana pun TIDAK dirender — halaman ini berjanji
 * "what you are actually buying", jadi memuat sesuatu yang tidak bisa dibeli
 * adalah janji yang dilanggar. Teksnya tidak hilang: begitu lisensinya
 * ditawarkan lagi, kartunya kembali sendiri.
 *
 * Kalau toko tidak bisa dibaca sama sekali — Authentype nonaktif, atau belum
 * ada font terbit — daftar terdokumentasi dipakai sebagai cadangan. Situs baru
 * lebih baik menampilkan enam lisensi standar daripada halaman kosong. */
$aksara_sold = function_exists( 'aksara_authentype_sold_licenses' ) ? aksara_authentype_sold_licenses() : array();

/* URUTAN. aksara_authentype_sold_licenses() mengurutkan menurut jumlah keluarga
 * lalu alfabet — masuk akal untuk daftar admin, salah untuk halaman ini.
 * Alfabet menghasilkan App, Desktop, ePub, Extended, Server, Webfont, yang
 * mematahkan urutan cakupan yang disengaja (paling sempit ke paling luas) dan
 * bertentangan dengan paragraf panduan di atasnya, yang menyebut keenamnya
 * dalam urutan itu. Jadi yang sudah terdokumentasi tampil sesuai urutan
 * dokumennya, dan lisensi yang belum dikenal menyusul di belakang — tempat
 * paling terlihat untuk sesuatu yang baru dan belum ditulis ketentuannya. */
$aksara_ordered = array();
foreach ( array_keys( $aksara_license_names ) as $slug ) {
	if ( isset( $aksara_sold[ $slug ] ) ) {
		$aksara_ordered[ $slug ] = $aksara_sold[ $slug ];
	}
}
foreach ( $aksara_sold as $slug => $entry ) {
	if ( ! isset( $aksara_ordered[ $slug ] ) ) {
		$aksara_ordered[ $slug ] = $entry;
	}
}
$aksara_sold = $aksara_ordered;

$licenses = array();
if ( $aksara_sold ) {
	foreach ( $aksara_sold as $slug => $entry ) {
		$licenses[] = array(
			'name'     => isset( $aksara_license_names[ $slug ] ) ? $aksara_license_names[ $slug ] : $entry['label'],
			'overview' => aksara_mod( 'aksara_license_' . $slug . '_overview' ),
			'allowed'  => aksara_mod( 'aksara_license_' . $slug . '_allowed' ),
			'prohibited' => aksara_mod( 'aksara_license_' . $slug . '_prohibited' ),
			'limitations' => aksara_mod( 'aksara_license_' . $slug . '_limitations' ),
			// Dipakai hanya kalau ketentuannya belum ditulis sama sekali.
			'shop_note' => $entry['description'],
		);
	}
} else {
	foreach ( $aksara_license_names as $slug => $name ) {
		$licenses[] = array(
			'name'     => $name,
			'overview' => aksara_mod( 'aksara_license_' . $slug . '_overview' ),
			'allowed'  => aksara_mod( 'aksara_license_' . $slug . '_allowed' ),
			'prohibited' => aksara_mod( 'aksara_license_' . $slug . '_prohibited' ),
			'limitations' => aksara_mod( 'aksara_license_' . $slug . '_limitations' ),
			'shop_note' => '',
		);
	}
}

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
			<?php foreach ( $licenses as $aksara_index => $license ) : ?>
				<?php
				// Ketentuan dianggap "belum ditulis" hanya kalau KEEMPAT ruasnya
				// kosong. Satu ruas terisi berarti penyunting sudah mulai, dan
				// kartunya harus tampil apa adanya — bukan diganti ringkasan toko.
				$aksara_written = '' !== trim( $license['overview'] . $license['allowed'] . $license['prohibited'] . $license['limitations'] );
				?>
				<article class="license-page-item<?php echo $aksara_written ? '' : ' license-page-item--brief'; ?>">
					<div class="license-page-item__index"><?php echo esc_html( sprintf( '%02d', $aksara_index + 1 ) ); ?></div>
					<div class="license-page-item__title"><h3><?php echo esc_html( $license['name'] ); ?></h3></div>
					<div class="license-page-item__body">
						<?php if ( $aksara_written ) : ?>
							<div class="license-page__overview"><?php echo nl2br( esc_html( $license['overview'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sudah di-escape sebelum nl2br. ?></div><div class="license-page__detail-grid"><div><h4><?php esc_html_e( 'Allowed Uses', 'aksara' ); ?></h4><?php aksara_license_bullets( $license['allowed'] ); ?></div><div><h4><?php esc_html_e( 'Prohibited Uses', 'aksara' ); ?></h4><?php aksara_license_bullets( $license['prohibited'] ); ?></div><div><h4><?php esc_html_e( 'Limitations', 'aksara' ); ?></h4><p><?php echo nl2br( esc_html( $license['limitations'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sudah di-escape sebelum nl2br. ?></p></div></div>
						<?php else : ?>
							<div class="license-page__overview"><?php echo esc_html( $license['shop_note'] ); ?></div>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="license-page__ip" aria-labelledby="license-ip-title"><div class="license-page__section-label">02 / <?php esc_html_e( 'Ownership', 'aksara' ); ?></div><div><h2 id="license-ip-title"><?php esc_html_e( 'Intellectual Property', 'aksara' ); ?></h2><p><?php echo esc_html( aksara_mod( 'aksara_license_ip_text' ) ); ?></p></div></section>
	<section class="license-page__contact" aria-labelledby="license-contact-title"><div><p class="license-page__eyebrow"><?php esc_html_e( 'Need a different scope?', 'aksara' ); ?></p><h2 id="license-contact-title"><?php echo esc_html( aksara_mod( 'aksara_license_contact_title' ) ); ?></h2></div><div><p><?php echo esc_html( aksara_mod( 'aksara_license_contact_text' ) ); ?></p><p class="license-page__contact-meta">Email: <?php echo antispambot( esc_html( sanitize_email( aksara_mod( 'aksara_contact_email' ) ? aksara_mod( 'aksara_contact_email' ) : get_option( 'admin_email' ) ) ) ); ?><br><?php if ( aksara_mod( 'aksara_license_website' ) ) : ?>Website: <a href="<?php echo esc_url( aksara_mod( 'aksara_license_website' ) ); ?>"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( aksara_mod( 'aksara_license_website' ) ) ) ); ?></a><br><?php endif; ?><?php printf( esc_html__( 'License issued by %1$s &middot; Type Foundry: %2$s', 'aksara' ), esc_html( aksara_mod( 'aksara_license_brand' ) ), esc_html( aksara_mod( 'aksara_license_foundry' ) ) ); ?></p><a class="button button--solid" href="mailto:<?php echo antispambot( esc_attr( sanitize_email( aksara_mod( 'aksara_contact_email' ) ? aksara_mod( 'aksara_contact_email' ) : get_option( 'admin_email' ) ) ) ); ?>"><?php echo esc_html( aksara_mod( 'aksara_license_contact_label' ) ); ?></a></div></section>
</main>

<?php get_footer(); ?>
