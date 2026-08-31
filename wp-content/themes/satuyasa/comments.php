<?php
/**
 * Template komentar.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$satuyasa_comment_count = get_comments_number();
			if ( 1 === (int) $satuyasa_comment_count ) {
				esc_html_e( '1 Komentar', 'satuyasa' );
			} else {
				printf(
					/* translators: %s: jumlah komentar. */
					esc_html( _n( '%s Komentar', '%s Komentar', $satuyasa_comment_count, 'satuyasa' ) ),
					esc_html( number_format_i18n( $satuyasa_comment_count ) )
				);
			}
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'      => 'ol',
				'short_ping' => true,
			) );
			?>
		</ol>

		<?php
		the_comments_pagination( array(
			'prev_text' => esc_html__( '&laquo; Sebelumnya', 'satuyasa' ),
			'next_text' => esc_html__( 'Berikutnya &raquo;', 'satuyasa' ),
		) );
		?>

	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="no-comments"><?php esc_html_e( 'Komentar sudah ditutup.', 'satuyasa' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'class_submit' => 'button',
	) );
	?>

</div>
