<?php
/**
 * Template Name: Aksara — Halaman License
 *
 * Referensi hukum untuk semua jenis lisensi font. Kalimat pembuka halaman ini
 * berjanji: "generated from the same data the shop uses — so what you read
 * here is what you are actually buying." Templat ini yang harus menjaga janji
 * itu tetap benar.
 *
 * DUA SUMBER DATA, DAN KENAPA URUTANNYA BEGINI
 *
 * 1. Authentype (_ath_license_options per keluarga font). Ini yang dipakai
 *    halaman produk sungguhan, jadi ia didahulukan.
 * 2. Tabel aksara_font_licenses milik plugin Aksara Marketplace. Ini sumber
 *    lama; masih dipakai kalau situs berjalan tanpa Authentype (engine
 *    "aksara"). Begitu Authentype aktif, layar admin yang mengisi tabel ini
 *    tidak lagi didaftarkan — jadi ia hanya boleh jadi cadangan, tidak boleh
 *    jadi yang utama.
 *
 * Lihat aksara_authentype_license_catalogue() untuk uraian lengkapnya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$licenses = array();
if ( function_exists( 'aksara_authentype_license_catalogue' ) ) {
	foreach ( aksara_authentype_license_catalogue() as $entry ) {
		$licenses[] = array(
			'name'        => $entry['name'],
			'description' => $entry['description'],
		);
	}
}
if ( ! $licenses && class_exists( 'Aksara_Font_Licenses_Repository' ) ) {
	foreach ( Aksara_Font_Licenses_Repository::get_all() as $entry ) {
		$licenses[] = array(
			'name'        => (string) $entry->name,
			'description' => (string) $entry->description,
		);
	}
}
?>

<div class="wrap content-area">
	<header class="page-header">
		<h1 class="page-title"><?php the_title(); ?></h1>
	</header>

	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>

	<?php if ( ! $licenses ) : ?>
		<p><?php esc_html_e( 'No license types have been set up yet.', 'aksara' ); ?></p>
	<?php else : ?>
		<?php foreach ( $licenses as $license ) : ?>
			<div class="license-page-item">
				<h3><?php echo esc_html( $license['name'] ); ?></h3>
				<?php if ( '' !== $license['description'] ) : ?>
					<div class="license-description"><?php echo wp_kses_post( wpautop( $license['description'] ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<?php
get_footer();
