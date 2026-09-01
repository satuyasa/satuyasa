<?php
/** Compact canonical font card for related-family sections. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$font_id = get_the_ID();
$product = function_exists( 'aksara_authentype_linked_product' ) ? aksara_authentype_linked_product( $font_id ) : null;
$styles  = function_exists( 'aksara_authentype_styles' ) ? aksara_authentype_styles( $font_id ) : array();
$images  = function_exists( 'aksara_authentype_product_gallery_ids' ) ? aksara_authentype_product_gallery_ids( $font_id, 1 ) : array();

/*
 * Kartu ini muncul di bagian "Related font families" pada halaman produk
 * font — justru tempat pembeli membandingkan wujud huruf. Sebelumnya kartu
 * hanya menampilkan gambar galeri, atau judul dalam font TEMA kalau gambar
 * itu tidak ada; artinya tidak ada satu pun huruf keluarga terkait yang
 * benar-benar terlihat. Ditambahkan baris spesimen ringkas memakai mesin
 * render yang sama dengan katalog (Authentype, canvas + token).
 */
$default = ! empty( $styles[0] ) ? $styles[0] : null;
if ( $default && ! empty( $default['token'] ) && function_exists( 'aksara_authentype_enqueue_preview' ) ) {
	aksara_authentype_enqueue_preview();
}
?>
<article class="font-product-card" data-font-post-id="<?php echo esc_attr( $font_id ); ?>" data-text-color="#000000" data-bg-color="#ffffff">
	<a class="font-product-card__image" href="<?php the_permalink(); ?>">
		<?php if ( $images ) : ?>
			<?php echo wp_get_attachment_image( $images[0], 'aksara-preview-md', false, array( 'loading' => 'lazy', 'sizes' => '(max-width: 760px) calc(100vw - 32px), 33vw' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?><span><?php the_title(); ?></span><?php endif; ?>
	</a>
	<div class="font-product-card__body">
		<div>
			<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
			<?php if ( $default && ! empty( $default['token'] ) ) : ?>
				<?php
				/*
				 * aria-hidden: nama keluarga sudah dibacakan lewat <h3> di
				 * atas, jadi canvas ini murni pengulangan visual. Menandainya
				 * tersembunyi mencegah screen reader menyebut nama yang sama
				 * dua kali berturut-turut.
				 */
				?>
				<canvas class="ath-server-canvas font-product-card__specimen"
					data-font-token="<?php echo esc_attr( $default['token'] ); ?>"
					data-mode="style-text"
					data-text="<?php echo esc_attr( get_the_title( $font_id ) ); ?>"
					data-font-size="44"
					data-fit-single-line="1"
					aria-hidden="true"></canvas>
			<?php endif; ?>
			<p><?php printf( esc_html( _n( '%d style', '%d styles', count( $styles ), 'aksara' ) ), count( $styles ) ); ?></p>
		</div>
		<div class="font-product-card__price"><?php echo $product ? wp_kses_post( $product->get_price_html() ) : ''; ?><?php echo $product && function_exists( 'aksara_product_discount_badge' ) ? wp_kses_post( aksara_product_discount_badge( $product ) ) : ''; ?></div>
	</div>
</article>
