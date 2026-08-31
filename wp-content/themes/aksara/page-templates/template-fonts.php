<?php
/**
 * Template Name: Aksara — Daftar Font
 *
 * Halaman "Fonts" dari sitemap PRD Bagian 5: listing semua font, dengan
 * filter dasar per kategori (product_cat bawaan WooCommerce). Filter
 * lanjutan (jumlah style, lisensi tersedia, rentang harga) menyusul di
 * fase berikutnya bersama pencarian lanjutan.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$paged        = max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? get_query_var( 'page' ) : 1 ) );
$current_cat  = isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, no state change.

$tax_query = array(
	array(
		'taxonomy' => 'product_type',
		'field'    => 'slug',
		'terms'    => 'font',
	),
);

if ( $current_cat ) {
	$tax_query['relation'] = 'AND';
	$tax_query[]           = array(
		'taxonomy' => 'product_cat',
		'field'    => 'slug',
		'terms'    => $current_cat,
	);
}

$fonts = new WP_Query( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 20,
	'paged'          => $paged,
	'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
) );

$categories = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
) );
?>

<div class="wrap content-area">
	<header class="page-header">
		<h1 class="page-title"><?php the_title(); ?></h1>
	</header>

	<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
		<div class="filter-bar">
			<a href="<?php echo esc_url( remove_query_arg( 'kategori' ) ); ?>" class="<?php echo '' === $current_cat ? 'active' : ''; ?>"><?php esc_html_e( 'Semua', 'aksara' ); ?></a>
			<?php foreach ( $categories as $cat ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'kategori', $cat->slug ) ); ?>" class="<?php echo $current_cat === $cat->slug ? 'active' : ''; ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $fonts->have_posts() ) : ?>
		<div class="specimen-list">
			<?php
			while ( $fonts->have_posts() ) :
				$fonts->the_post();
				get_template_part( 'template-parts/font-specimen-row' );
			endwhile;
			?>
		</div>

		<?php
		the_posts_pagination( array(
			'total' => $fonts->max_num_pages,
		) );
		wp_reset_postdata();
		?>
	<?php else : ?>
		<p><?php esc_html_e( 'Belum ada font yang cocok dengan filter ini.', 'aksara' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
