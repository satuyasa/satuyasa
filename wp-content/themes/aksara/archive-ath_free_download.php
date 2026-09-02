<?php
/**
 * Arsip Free Font — sistem visual Foundry (docs/DESIGN3.md).
 *
 * Susunannya mengikuti Layout di DESIGN3: ticker, lalu hero selebar kolom
 * konten dengan spesimen besar dan catatan singkat, lalu tumpukan pita
 * spesimen selebar penuh yang dipisahkan garis rambut 1px. Sidebar dan
 * ticker-nya ada di header-foundry.php.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'foundry' );

$paged = max( 1, (int) get_query_var( 'paged' ) );
$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'font'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$items = function_exists( 'aksara_query_free_fonts' ) ? aksara_query_free_fonts( 10, $paged, $type ) : null;
$total = $items ? (int) $items->found_posts : 0;
$types = function_exists( 'ath_free_download_types' ) ? ath_free_download_types() : array();
?>

<section class="foundry-hero">
	<p class="foundry-kicker"><?php esc_html_e( 'Free downloads', 'aksara' ); ?></p>
	<h1><?php esc_html_e( 'Take a face', 'aksara' ); ?> <b><?php esc_html_e( 'for free.', 'aksara' ); ?></b></h1>
	<p class="foundry-lede"><?php esc_html_e( 'Complete families released at no cost, each with a license you can read in a minute. Download the file, check what it allows, then ship.', 'aksara' ); ?></p>
</section>

<?php
/*
 * Penyaring tipe. Dirender sebagai tag, bukan <select>: DESIGN3 menyusun
 * seluruh navigasinya sebagai tag berlabel, dan sebuah <select> di kanvas
 * gelap akan membawa gaya bawaan sistem operasi yang tidak bisa diselaraskan
 * dengan andal di semua platform.
 */
?>
<div class="foundry-meta">
	<span><?php esc_html_e( 'Filter', 'aksara' ); ?></span>
	<span class="foundry-meta-group">
		<?php foreach ( $types as $key => $label ) : ?>
			<a class="foundry-tag" href="<?php echo esc_url( add_query_arg( 'type', $key, aksara_free_fonts_archive_url() ) ); ?>"<?php echo $key === $type ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</span>
	<span class="muted">
		<?php
		/* translators: %s: number of items. */
		printf( esc_html( _n( '%s release', '%s releases', $total, 'aksara' ) ), esc_html( number_format_i18n( $total ) ) );
		?>
	</span>
</div>

<?php if ( $items && $items->have_posts() ) : ?>
	<?php
	while ( $items->have_posts() ) :
		$items->the_post();
		get_template_part( 'template-parts/free-font-row' );
	endwhile;
	?>

	<?php
	$links = paginate_links( array(
		'total'    => (int) $items->max_num_pages,
		'current'  => $paged,
		'type'     => 'list',
		'add_args' => $type ? array( 'type' => $type ) : false,
	) );
	?>
	<?php if ( $links ) : ?>
		<nav class="foundry-pagination" aria-label="<?php esc_attr_e( 'Free font pages', 'aksara' ); ?>"><?php echo wp_kses_post( $links ); ?></nav>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>
<?php else : ?>
	<div class="foundry-empty">
		<h2><?php esc_html_e( 'Nothing released here yet.', 'aksara' ); ?></h2>
		<p><?php esc_html_e( 'Try another category, or browse the retail library.', 'aksara' ); ?></p>
		<p class="foundry-actions">
			<a class="foundry-btn-ghost" href="<?php echo esc_url( function_exists( 'aksara_authentype_archive_url' ) ? aksara_authentype_archive_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Retail fonts', 'aksara' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<?php get_footer( 'foundry' ); ?>
