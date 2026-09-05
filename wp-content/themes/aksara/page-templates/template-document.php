<?php
/**
 * Template Name: Aksara — Document
 *
 * Untuk halaman dokumen: Privacy Policy, Terms of Use, Licenses, dan
 * sejenisnya. Bedanya dari page.php cuma satu hal, tapi hal itu penting:
 * baris "Last updated" yang diambil dari post_modified.
 *
 * KENAPA DARI post_modified, BUKAN DIKETIK DI ISI HALAMAN
 *
 * Tanggal yang diketik manual di dalam teks adalah tanggal yang PASTI basi:
 * ia hanya berubah kalau penyuntingnya ingat mengubahnya, dan yang paling
 * sering terjadi justru sebaliknya — kebijakannya direvisi, tanggalnya
 * tertinggal. Di halaman hukum itu bukan detail kosmetik; tanggal berlaku
 * adalah bagian dari isi dokumennya.
 *
 * post_modified diperbarui WordPress setiap kali halaman disimpan, jadi ia
 * tidak bisa berbohong ke arah itu. Yang bisa terjadi sebaliknya: menyimpan
 * halaman untuk perbaikan salah ketik ikut memajukan tanggalnya. Itu
 * konsekuensi yang diterima — terlalu baru lebih aman daripada terlalu lama
 * untuk dokumen semacam ini.
 *
 * Halaman yang TIDAK butuh baris itu (About, Contact, FAQ) cukup memakai
 * template bawaan; berkas ini tidak dipaksakan ke semuanya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="wrap content-area">
	<main id="primary">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
					<p class="doc-meta">
						<?php
						printf(
							/* translators: %s: tanggal perubahan terakhir. */
							esc_html__( 'Last updated %s', 'aksara' ),
							esc_html( get_the_modified_date() )
						);
						?>
					</p>
				</header>

				<div class="entry-content">
					<?php
					the_content();
					wp_link_pages();
					?>
				</div>

				<?php if ( get_edit_post_link() ) : ?>
					<footer class="entry-footer">
						<?php edit_post_link( esc_html__( 'Edit this page', 'aksara' ) ); ?>
					</footer>
				<?php endif; ?>
			</article>
			<?php
		endwhile;
		?>
	</main>
</div>

<?php
get_footer();
