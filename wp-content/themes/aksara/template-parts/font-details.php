<?php
/**
 * Blok tambahan di kolom "Product details": Team, tanggal rilis/pembaruan,
 * dan "Additionally".
 *
 * Datanya diisi dari metabox di layar edit ath_font (inc/font-details.php).
 * Setiap blok muncul HANYA kalau diisi — halaman font yang belum punya data
 * ini tampil persis seperti sebelumnya, tanpa judul kosong menggantung.
 *
 * Judulnya h3 karena induknya, "Product details", sudah h2. Melompat ke h2
 * lagi akan memberi halaman ini empat h2 sederajat padahal tiga di antaranya
 * jelas bagian dari yang pertama.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fd_details = function_exists( 'aksara_font_details' ) ? aksara_font_details( get_the_ID() ) : array();
if ( ! $fd_details ) {
	return;
}
?>

<?php if ( ! empty( $fd_details['team'] ) ) : ?>
	<section class="font-meta-block">
		<h3><?php esc_html_e( 'Team', 'aksara' ); ?></h3>
		<ul class="font-meta-team">
			<?php foreach ( $fd_details['team'] as $fd_member ) : ?>
				<li>
					<span class="font-meta-team__name"><?php echo esc_html( $fd_member['name'] ); ?></span>
					<?php if ( '' !== $fd_member['role'] ) : ?>
						<span class="font-meta-team__role"><?php echo esc_html( $fd_member['role'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( $fd_details['release'] || $fd_details['updated'] || $fd_details['changelog'] ) : ?>
	<section class="font-meta-block">
		<?php
		// Judulnya menyebut tanggal, jadi ia hanya jujur kalau ada tanggal.
		// Font yang baru punya daftar perubahan tanpa tanggal tetap masuk akal;
		// yang tidak masuk akal adalah memberinya judul "Release and update date"
		// lalu tidak menampilkan satu tanggal pun di bawahnya.
		?>
		<h3><?php echo esc_html( $fd_details['release'] || $fd_details['updated'] ? __( 'Release and update date', 'aksara' ) : __( 'Updates', 'aksara' ) ); ?></h3>
		<?php if ( $fd_details['release'] || $fd_details['updated'] ) : ?>
			<dl class="font-meta-dates">
				<?php if ( $fd_details['release'] ) : ?>
					<div><dt><?php esc_html_e( 'Release:', 'aksara' ); ?></dt><dd><?php echo esc_html( aksara_font_details_date( $fd_details['release'] ) ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $fd_details['updated'] ) : ?>
					<div><dt><?php esc_html_e( 'Last update:', 'aksara' ); ?></dt><dd><?php echo esc_html( aksara_font_details_date( $fd_details['updated'] ) ); ?></dd></div>
				<?php endif; ?>
			</dl>
		<?php endif; ?>
		<?php if ( $fd_details['changelog'] ) : ?>
			<p class="font-meta-label"><?php esc_html_e( 'What has changed in the font:', 'aksara' ); ?></p>
			<ul class="font-meta-changelog">
				<?php foreach ( $fd_details['changelog'] as $fd_change ) : ?>
					<li><?php echo esc_html( $fd_change ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( ! empty( $fd_details['extras'] ) ) : ?>
	<section class="font-meta-block">
		<h3><?php esc_html_e( 'Additionally', 'aksara' ); ?></h3>
		<ul class="font-extras">
			<?php foreach ( $fd_details['extras'] as $fd_extra ) : ?>
				<li class="font-extras__item">
					<?php if ( $fd_extra['url'] ) : ?>
						<a class="font-extras__link" href="<?php echo esc_url( $fd_extra['url'] ); ?>"><?php echo esc_html( $fd_extra['label'] ); ?></a>
					<?php else : ?>
						<span class="font-extras__link font-extras__link--plain"><?php echo esc_html( $fd_extra['label'] ); ?></span>
					<?php endif; ?>
					<?php if ( $fd_extra['text'] ) : ?>
						<p class="font-extras__text"><?php echo esc_html( $fd_extra['text'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
