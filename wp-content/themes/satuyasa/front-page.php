<?php
/**
 * Template halaman depan.
 *
 * Menampilkan hero, grid portofolio (jika plugin Satuyasa Toolkit aktif),
 * dan tulisan terbaru.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$satuyasa_hero_title    = get_theme_mod( 'satuyasa_hero_title', get_bloginfo( 'name' ) );
$satuyasa_hero_subtitle = get_theme_mod( 'satuyasa_hero_subtitle', get_bloginfo( 'description' ) );
$satuyasa_hero_btn_text = get_theme_mod( 'satuyasa_hero_button_text', __( 'Hubungi Kami', 'satuyasa' ) );
$satuyasa_hero_btn_url  = get_theme_mod( 'satuyasa_hero_button_url', '#' );
?>

<section class="satuyasa-hero">
	<div class="container">
		<h1><?php echo esc_html( $satuyasa_hero_title ); ?></h1>
		<?php if ( $satuyasa_hero_subtitle ) : ?>
			<p><?php echo esc_html( $satuyasa_hero_subtitle ); ?></p>
		<?php endif; ?>
		<?php if ( $satuyasa_hero_btn_text && $satuyasa_hero_btn_url ) : ?>
			<a class="button" href="<?php echo esc_url( $satuyasa_hero_btn_url ); ?>"><?php echo esc_html( $satuyasa_hero_btn_text ); ?></a>
		<?php endif; ?>
	</div>
</section>

<?php if ( post_type_exists( 'portfolio' ) ) : ?>
	<section class="satuyasa-section satuyasa-portfolio-section">
		<div class="container">
			<h2 class="satuyasa-section-title"><?php esc_html_e( 'Portofolio Kami', 'satuyasa' ); ?></h2>
			<?php echo do_shortcode( '[satuyasa_portfolio limit="6" columns="3"]' ); ?>
		</div>
	</section>
<?php endif; ?>

<section class="satuyasa-section satuyasa-latest-posts">
	<div class="container">
		<h2 class="satuyasa-section-title"><?php esc_html_e( 'Tulisan Terbaru', 'satuyasa' ); ?></h2>

		<?php
		$satuyasa_latest = new WP_Query( array(
			'post_type'           => 'post',
			'posts_per_page'      => 3,
			'ignore_sticky_posts' => true,
		) );

		if ( $satuyasa_latest->have_posts() ) :
			while ( $satuyasa_latest->have_posts() ) :
				$satuyasa_latest->the_post();
				get_template_part( 'template-parts/content' );
			endwhile;
			wp_reset_postdata();
		else :
			echo '<p>' . esc_html__( 'Belum ada tulisan.', 'satuyasa' ) . '</p>';
		endif;
		?>
	</div>
</section>

<?php
get_footer();
