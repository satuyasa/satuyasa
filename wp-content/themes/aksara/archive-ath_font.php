<?php
/** Authentype font archive with Aksara storefront UI. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$paged  = max( 1, get_query_var( 'paged' ) );
$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$fonts  = function_exists( 'aksara_query_authentype_fonts' ) ? aksara_query_authentype_fonts( 20, $paged, $search ) : null;
if ( function_exists( 'aksara_authentype_enqueue_preview' ) ) { aksara_authentype_enqueue_preview(); }
?>
<main id="primary" class="site-main font-library"><div class="wrap">
	<header class="font-library-header"><div><p class="eyebrow"><?php esc_html_e( 'Font library', 'aksara' ); ?></p><h1><?php esc_html_e( 'Find the right voice.', 'aksara' ); ?></h1></div><p><?php esc_html_e( 'Secure server-rendered previews. Clear style and license choices. Original font files are delivered only after checkout.', 'aksara' ); ?></p></header>
	<form class="catalog-toolbar" method="get" action="<?php echo esc_url( aksara_authentype_archive_url() ); ?>">
		<label class="screen-reader-text" for="font-search"><?php esc_html_e( 'Search fonts', 'aksara' ); ?></label><input id="font-search" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search font families', 'aksara' ); ?>"><button type="submit"><?php esc_html_e( 'Search', 'aksara' ); ?></button><span class="catalog-count"><?php echo esc_html( number_format_i18n( $fonts ? $fonts->found_posts : 0 ) ); ?> <?php esc_html_e( 'families', 'aksara' ); ?></span>
	</form>
	<?php if ( $fonts && $fonts->have_posts() ) : ?><div class="specimen-list"><?php while ( $fonts->have_posts() ) : $fonts->the_post(); get_template_part( 'template-parts/font-specimen-row' ); endwhile; ?></div><?php aksara_pagination( $fonts->max_num_pages, $paged, array( 'add_args' => $search ? array( 'q' => $search ) : false ) ); wp_reset_postdata(); else : ?><div class="catalog-empty"><h2><?php esc_html_e( 'No fonts found.', 'aksara' ); ?></h2><p><?php esc_html_e( 'Try a different family name.', 'aksara' ); ?></p></div><?php endif; ?>
</div></main>
<?php get_footer(); ?>
