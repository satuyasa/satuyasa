<?php
/**
 * Template Name: Aksara — Daftar Canva Element
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$paged       = max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? get_query_var( 'page' ) : 1 ) );
$current_cat = isset( $_GET['kategori'] ) ? sanitize_title( wp_unslash( $_GET['kategori'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tax_query = array(
	array(
		'taxonomy' => 'product_type',
		'field'    => 'slug',
		'terms'    => 'canva_element',
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

$elements = new WP_Query( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 24,
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
			<a href="<?php echo esc_url( remove_query_arg( 'kategori' ) ); ?>" class="<?php echo '' === $current_cat ? 'active' : ''; ?>"><?php esc_html_e( 'All', 'aksara' ); ?></a>
			<?php foreach ( $categories as $cat ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'kategori', $cat->slug ) ); ?>" class="<?php echo $current_cat === $cat->slug ? 'active' : ''; ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $elements->have_posts() ) : ?>
		<div class="asset-grid">
			<?php
			while ( $elements->have_posts() ) :
				$elements->the_post();
				get_template_part( 'template-parts/asset-card' );
			endwhile;
			?>
		</div>

		<?php
		/* aksara_pagination(), bukan the_posts_pagination(): fungsi bawaan itu
		 * keluar lebih awal kalau query UTAMA hanya punya satu halaman — dan di
		 * Page template memang selalu begitu, jadi selama ini tidak ada paginasi
		 * yang tercetak sama sekali dan produk ke-25 dan seterusnya tidak bisa
		 * dijangkau. Penjelasan lengkapnya di inc/template-tags.php. */
		aksara_pagination( $elements->max_num_pages, $paged );
		wp_reset_postdata();
		?>
	<?php else : ?>
		<p><?php esc_html_e( 'No elements match this filter yet.', 'aksara' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
