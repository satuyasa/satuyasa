<?php
/**
 * Halaman tunggal Free Font — sistem visual Foundry (docs/DESIGN3.md).
 *
 * Urutannya: judul besar, spesimen selebar penuh, tabel spec, deskripsi,
 * lalu blok unduhan MILIK PLUGIN. Blok terakhir itu sengaja tidak ditulis
 * ulang di tema — di dalamnya ada nonce, license fingerprint, honeypot, dan
 * gerbang email yang seluruhnya bagian dari kontrak keamanan Authentype.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="primary" class="foundry freefonts-archive freefonts-single">
<div class="freefonts-archive__inner">
<?php

while ( have_posts() ) :
	the_post();

	$fd_id       = get_the_ID();
	$fd_type     = aksara_free_meta( $fd_id, '_ath_free_download_type', 'font' );
	$fd_specimen = aksara_free_font_specimen( $fd_id );
	$fd_note     = aksara_free_meta( $fd_id, '_ath_free_download_note', '' );
	$fd_license  = function_exists( 'ath_free_download_resolve_license' ) ? ath_free_download_resolve_license( $fd_id ) : array();
	$fd_label    = ! empty( $fd_license['label'] ) ? $fd_license['label'] : aksara_free_meta( $fd_id, '_ath_free_download_license_label', '' );
	$fd_version  = ! empty( $fd_license['version'] ) ? $fd_license['version'] : aksara_free_meta( $fd_id, '_ath_free_download_license_version', '' );
	$fd_summary  = ! empty( $fd_license['summary'] ) ? $fd_license['summary'] : aksara_free_meta( $fd_id, '_ath_free_download_license_summary', '' );
	$fd_doc      = ! empty( $fd_license['document_url'] ) ? $fd_license['document_url'] : aksara_free_meta( $fd_id, '_ath_free_download_license_document_url', '' );
	$fd_styles   = ( $fd_specimen && function_exists( 'aksara_authentype_styles' ) ) ? count( aksara_authentype_styles( $fd_specimen['font_id'] ) ) : 0;
	$fd_retail   = $fd_specimen ? get_permalink( $fd_specimen['font_id'] ) : '';
	?>

	<article <?php post_class(); ?>>
		<header class="foundry-single-head">
			<p class="foundry-kicker">
				<a href="<?php echo esc_url( aksara_free_fonts_archive_url() ); ?>"><?php esc_html_e( 'Free downloads', 'aksara' ); ?></a>
				<span aria-hidden="true">/</span>
				<?php echo esc_html( aksara_free_type_label( $fd_type ) ); ?>
			</p>
			<h1><?php the_title(); ?></h1>
		</header>

		<?php
		/*
		 * Canvas langsung + tester, dipulihkan dari 0.9.19. Lihat catatan
		 * lengkapnya di template-parts/free-font-row.php: shortcode
		 * authentype_free_font_preview yang dipakai 0.9.20-0.9.30 tidak
		 * pernah ada di plugin, jadi halaman ini selalu menampilkan
		 * placeholder.
		 *
		 * Kontrol testernya MILIK PLUGIN (.ath-master-text dan .ath-size);
		 * tema hanya menatanya. Seluruh mesinnya — debounce, antrian render,
		 * cache — sudah ada di specimen.js, jadi tidak ada satu baris JS pun
		 * yang perlu ditulis di sini.
		 */
		?>
		<div class="foundry-specimen">
			<?php if ( $fd_specimen ) : ?>
				<div class="ath-specimen ath-specimen-v7" data-font-post-id="<?php echo esc_attr( $fd_specimen['font_id'] ); ?>">
					<div class="ath-preview-toolbar foundry-tester">
						<label class="foundry-tester__field">
							<span class="foundry-tester__label"><?php esc_html_e( 'Type to test', 'aksara' ); ?></span>
							<input class="ath-master-text foundry-tester__text" type="text"
								value="<?php echo esc_attr( get_the_title() ); ?>"
								maxlength="120"
								placeholder="<?php esc_attr_e( 'Type something…', 'aksara' ); ?>"
								autocomplete="off" spellcheck="false">
						</label>
						<label class="foundry-tester__field foundry-tester__field--size">
							<span class="foundry-tester__label"><?php esc_html_e( 'Size', 'aksara' ); ?></span>
							<input class="ath-size foundry-tester__size" type="range" min="24" max="200" step="2" value="150">
						</label>
					</div>

					<canvas class="ath-server-canvas"
						data-sync-master="1"
						data-font-token="<?php echo esc_attr( $fd_specimen['token'] ); ?>"
						data-mode="style-text"
						data-text="<?php echo esc_attr( get_the_title() ); ?>"
						data-font-size="150"
						data-fit-single-line="1"
						aria-label="<?php echo esc_attr( sprintf( __( '%s specimen', 'aksara' ), get_the_title() ) ); ?>"></canvas>
					<?php aksara_foundry_placeholder( get_the_title(), __( 'Preview unavailable', 'aksara' ), true ); ?>
					<noscript><?php aksara_foundry_placeholder( get_the_title(), __( 'Preview needs JavaScript', 'aksara' ) ); ?></noscript>
				</div>
			<?php else : ?>
				<?php aksara_foundry_placeholder( get_the_title(), __( 'No specimen linked', 'aksara' ) ); ?>
			<?php endif; ?>
		</div>

		<dl class="foundry-specs">
			<div class="foundry-spec">
				<dt><?php esc_html_e( 'Type', 'aksara' ); ?></dt>
				<dd><?php echo esc_html( aksara_free_type_label( $fd_type ) ); ?></dd>
			</div>
			<?php if ( $fd_styles ) : ?>
				<div class="foundry-spec">
					<dt><?php esc_html_e( 'Styles', 'aksara' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $fd_styles ) ); ?></dd>
				</div>
			<?php endif; ?>
			<?php if ( $fd_label ) : ?>
				<div class="foundry-spec">
					<dt><?php esc_html_e( 'License', 'aksara' ); ?></dt>
					<dd>
						<?php echo esc_html( $fd_label ); ?><?php echo $fd_version ? ' ' . esc_html( $fd_version ) : ''; ?>
						<?php if ( $fd_doc ) : ?><br><a href="<?php echo esc_url( $fd_doc ); ?>"><?php esc_html_e( 'Read the license', 'aksara' ); ?></a><?php endif; ?>
					</dd>
				</div>
			<?php endif; ?>
			<div class="foundry-spec">
				<dt><?php esc_html_e( 'Released', 'aksara' ); ?></dt>
				<dd><?php echo esc_html( get_the_date() ); ?></dd>
			</div>
		</dl>

		<?php if ( $fd_summary ) : ?>
			<div class="foundry-meta"><span class="muted"><?php echo esc_html( $fd_summary ); ?></span></div>
		<?php endif; ?>

		<?php
		/*
		 * Deskripsi + gambar unggulan, berdampingan: teks kiri, gambar kanan.
		 *
		 * Gambar unggulannya sendiri BARU ditampilkan di sini — sebelumnya
		 * halaman ini tidak pernah mencetaknya sama sekali, jadi gambar yang
		 * sudah dipasang admin di editor tidak muncul di mana pun.
		 *
		 * Kalau salah satunya tidak ada, blok ini tidak memaksakan dua kolom:
		 * tanpa gambar, teks memakai lebar bacanya sendiri seperti dulu, dan
		 * tanpa teks, gambar berdiri sendiri. Kelas pembeda dipasang di PHP,
		 * bukan lewat :has() di CSS, supaya perilakunya sama di peramban yang
		 * belum mendukung :has().
		 */
		$fd_text  = trim( (string) get_the_content() );
		$fd_thumb = has_post_thumbnail( $fd_id )
			? get_the_post_thumbnail( $fd_id, 'aksara-preview-md', array( 'class' => 'foundry-figure__img', 'loading' => 'lazy' ) )
			: '';
		$fd_cap   = $fd_thumb ? get_the_post_thumbnail_caption( $fd_id ) : '';
		?>
		<?php if ( $fd_text || $fd_thumb ) : ?>
			<div class="foundry-about<?php echo ( $fd_text && $fd_thumb ) ? ' foundry-about--split' : ''; ?>">
				<?php if ( $fd_text ) : ?>
					<div class="foundry-body"><?php the_content(); ?></div>
				<?php endif; ?>
				<?php if ( $fd_thumb ) : ?>
					<figure class="foundry-figure">
						<?php echo $fd_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- keluaran inti WordPress. ?>
						<?php if ( $fd_cap ) : ?>
							<figcaption><?php echo esc_html( $fd_cap ); ?></figcaption>
						<?php endif; ?>
					</figure>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $fd_note ) : ?>
			<p class="foundry-band__note"><?php echo esc_html( $fd_note ); ?></p>
		<?php endif; ?>

		<?php
		/*
		 * Blok unduhan plugin. Dipanggil lewat aksara_free_download_block()
		 * yang mempersempit query shortcode ke post ini saja — lihat
		 * inc/free-fonts.php untuk alasan kenapa querynya yang dipersempit
		 * dan bukan markupnya yang disalin.
		 */
		$fd_block = function_exists( 'aksara_free_download_block' ) ? aksara_free_download_block( $fd_id ) : '';
		?>
		<?php if ( $fd_block ) : ?>
			<div class="foundry-download"><?php echo $fd_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dihasilkan shortcode plugin yang sudah meng-escape sendiri. ?></div>
		<?php else : ?>
			<div class="foundry-empty">
				<p><?php esc_html_e( 'This download is not available right now.', 'aksara' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $fd_retail ) : ?>
			<div class="foundry-meta">
				<span><?php esc_html_e( 'Full family', 'aksara' ); ?></span>
				<span class="foundry-actions">
					<a class="foundry-btn" href="<?php echo esc_url( $fd_retail ); ?>"><?php esc_html_e( 'See the retail release', 'aksara' ); ?></a>
				</span>
			</div>
		<?php endif; ?>
	</article>

	<?php
	/*
	 * "Lainnya" — dirender sebagai grid kartu, bukan pita spesimen, supaya
	 * tidak berebut perhatian dengan spesimen utama di atasnya. Query terpisah
	 * dan bukan bagian dari loop utama.
	 */
	$fd_more = new WP_Query( array(
		'post_type'           => 'ath_free_download',
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'post__not_in'        => array( $fd_id ),
		'orderby'             => 'rand',
		'ignore_sticky_posts' => true,
	) );
	?>
	<?php if ( $fd_more->have_posts() ) : ?>
		<div class="foundry-meta"><span><?php esc_html_e( 'More free releases', 'aksara' ); ?></span></div>
		<div class="foundry-grid">
			<?php
			while ( $fd_more->have_posts() ) :
				$fd_more->the_post();
				?>
				<a class="foundry-card" href="<?php the_permalink(); ?>">
					<?php
					/*
					 * Gambar unggulan, dengan cadangan teks kalau belum ada —
					 * pola yang sama persis dengan kartu font retail di
					 * template-parts/font-product-card.php. Sebelumnya kartu di
					 * sini tidak pernah punya gambar sama sekali, jadi blok
					 * "More free releases" terlihat berbeda dari blok "Related
					 * font families" di halaman font berbayar padahal keduanya
					 * mengerjakan hal yang sama.
					 *
					 * Rasio 3:2 dikunci lewat CSS, bukan lewat ukuran gambar:
					 * gambar unggulan diunggah admin dengan rasio apa pun, dan
					 * kartu yang tingginya berbeda-beda akan merusak grid.
					 */
					?>
					<span class="foundry-card__image">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'aksara-preview-sm', array( 'loading' => 'lazy', 'sizes' => '(max-width: 900px) 50vw, 240px' ) ); ?>
						<?php else : ?>
							<span class="foundry-card__image-fallback" aria-hidden="true"><?php echo esc_html( get_the_title() ); ?></span>
						<?php endif; ?>
					</span>
					<span class="foundry-kicker"><?php echo esc_html( aksara_free_type_label( aksara_free_meta( get_the_ID(), '_ath_free_download_type', 'font' ) ) ); ?></span>
					<h3><?php the_title(); ?></h3>
					<?php if ( has_excerpt() ) : ?><span class="muted"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 14 ) ); ?></span><?php endif; ?>
				</a>
			<?php endwhile; ?>
		</div>
		<?php wp_reset_postdata(); ?>
	<?php endif; ?>

	<?php
endwhile;
?>
</div>
</main>
<?php get_footer();
