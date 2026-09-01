<?php
/**
 * Blok: hasil pencarian yang sadar jenis konten.
 *
 * Kenapa tidak memakai blok Query Loop bawaan: CPT ath_font ikut terindeks
 * pencarian (public => true, tanpa exclude_from_search), tapi Query Loop
 * merender SATU markup yang sama untuk semua hasil. Akibatnya mencari nama
 * font menghasilkan daftar teks polos — tanpa satu pun wujud huruf, di situs
 * yang seluruh nilainya justru ada pada wujud huruf.
 *
 * Blok ini memakai query utama (hasil pencarian yang sudah dihitung
 * WordPress, termasuk paginasinya) lalu memilih markup per jenis: font
 * memakai baris spesimen yang sama dengan katalog, sisanya kartu entri biasa.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

if ( ! have_posts() ) {
	?>
	<p><?php esc_html_e( 'Sorry, nothing matched your search.', 'aksara' ); ?></p>
	<?php
	return;
}

$fonts = array();
$other = array();

while ( have_posts() ) {
	the_post();
	if ( 'ath_font' === get_post_type() ) {
		$fonts[] = get_the_ID();
	} else {
		$other[] = get_the_ID();
	}
}
wp_reset_postdata();

if ( $fonts ) :
	?>
	<h2 class="search-group-title"><?php esc_html_e( 'Font families', 'aksara' ); ?></h2>
	<div class="specimen-list">
		<?php
		foreach ( $fonts as $font_id ) {
			// setup_postdata() supaya the_title()/get_the_ID() di dalam
			// template part menunjuk ke post yang benar.
			$GLOBALS['post'] = get_post( $font_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $GLOBALS['post'] );
			get_template_part( 'template-parts/font-specimen-row' );
		}
		wp_reset_postdata();
		?>
	</div>
	<?php
endif;

if ( $other ) :
	?>
	<?php if ( $fonts ) : ?>
		<h2 class="search-group-title"><?php esc_html_e( 'Other results', 'aksara' ); ?></h2>
	<?php endif; ?>
	<?php
	foreach ( $other as $post_id ) {
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $GLOBALS['post'] );
		?>
		<article class="entry-card">
			<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<div class="entry-meta"><?php echo esc_html( get_the_date() ); ?></div>
			<div class="entry-content"><?php the_excerpt(); ?></div>
		</article>
		<?php
	}
	wp_reset_postdata();
endif;

// Paginasi memakai hitungan query utama, jadi tetap konsisten dengan
// jumlah hasil yang sebenarnya.
$pagination = paginate_links(
	array(
		'total'   => (int) $wp_query->max_num_pages,
		'current' => max( 1, (int) get_query_var( 'paged' ) ),
		'type'    => 'list',
	)
);

if ( $pagination ) {
	echo '<nav class="pagination">' . wp_kses_post( $pagination ) . '</nav>';
}
