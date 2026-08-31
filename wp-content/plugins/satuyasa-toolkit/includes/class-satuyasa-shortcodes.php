<?php
/**
 * Shortcode: grid portofolio & formulir kontak.
 *
 * @package Satuyasa_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Satuyasa_Shortcodes.
 */
class Satuyasa_Shortcodes {

	const CONTACT_NONCE_ACTION = 'satuyasa_contact_form';
	const CONTACT_NONCE_NAME   = 'satuyasa_contact_nonce';

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_shortcode( 'satuyasa_portfolio', array( __CLASS__, 'render_portfolio' ) );
		add_shortcode( 'satuyasa_contact', array( __CLASS__, 'render_contact_form' ) );
	}

	/**
	 * Shortcode [satuyasa_portfolio limit="6" columns="3" category=""].
	 *
	 * @param array $atts Atribut shortcode.
	 * @return string
	 */
	public static function render_portfolio( $atts ) {
		$atts = shortcode_atts( array(
			'limit'    => 6,
			'columns'  => 3,
			'category' => '',
		), $atts, 'satuyasa_portfolio' );

		$columns = max( 1, min( 4, (int) $atts['columns'] ) );

		$query_args = array(
			'post_type'      => 'portfolio',
			'posts_per_page' => (int) $atts['limit'],
			'post_status'    => 'publish',
		);

		if ( ! empty( $atts['category'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'portfolio_category',
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['category'] ),
				),
			);
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<p class="satuyasa-portfolio-empty">' . esc_html__( 'Belum ada portofolio untuk ditampilkan.', 'satuyasa-toolkit' ) . '</p>';
		}

		ob_start();
		?>
		<div class="satuyasa-portfolio-grid" style="--satuyasa-columns: <?php echo esc_attr( $columns ); ?>;">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$client     = get_post_meta( get_the_ID(), '_satuyasa_client', true );
				$project_url = get_post_meta( get_the_ID(), '_satuyasa_project_url', true );
				$link       = $project_url ? $project_url : get_permalink();
				?>
				<div class="satuyasa-portfolio-item">
					<a href="<?php echo esc_url( $link ); ?>" <?php echo $project_url ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="satuyasa-portfolio-thumb">
								<?php the_post_thumbnail( 'medium_large' ); ?>
							</div>
						<?php endif; ?>
						<h3 class="satuyasa-portfolio-title"><?php the_title(); ?></h3>
					</a>
					<?php if ( $client ) : ?>
						<p class="satuyasa-portfolio-client"><?php echo esc_html( $client ); ?></p>
					<?php endif; ?>
				</div>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode [satuyasa_contact].
	 *
	 * @return string
	 */
	public static function render_contact_form() {
		$notice = '';

		if ( isset( $_POST['satuyasa_contact_submit'] ) ) {
			$notice = self::handle_contact_submission();
		}

		ob_start();
		?>
		<div class="satuyasa-contact-form">
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sudah di-escape di handle_contact_submission(). ?>
			<form method="post" action="">
				<?php wp_nonce_field( self::CONTACT_NONCE_ACTION, self::CONTACT_NONCE_NAME ); ?>
				<p style="position:absolute;left:-9999px;" aria-hidden="true">
					<label for="satuyasa_website"><?php esc_html_e( 'Situs Web (kosongkan)', 'satuyasa-toolkit' ); ?></label>
					<input type="text" id="satuyasa_website" name="satuyasa_website" tabindex="-1" autocomplete="off">
				</p>
				<p>
					<label for="satuyasa_name"><?php esc_html_e( 'Nama', 'satuyasa-toolkit' ); ?></label>
					<input type="text" id="satuyasa_name" name="satuyasa_name" required>
				</p>
				<p>
					<label for="satuyasa_email"><?php esc_html_e( 'Email', 'satuyasa-toolkit' ); ?></label>
					<input type="email" id="satuyasa_email" name="satuyasa_email" required>
				</p>
				<p>
					<label for="satuyasa_message"><?php esc_html_e( 'Pesan', 'satuyasa-toolkit' ); ?></label>
					<textarea id="satuyasa_message" name="satuyasa_message" rows="5" required></textarea>
				</p>
				<p>
					<button type="submit" name="satuyasa_contact_submit" value="1"><?php esc_html_e( 'Kirim Pesan', 'satuyasa-toolkit' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Proses kiriman formulir kontak.
	 *
	 * @return string HTML notifikasi (sudah di-escape).
	 */
	private static function handle_contact_submission() {
		if ( ! isset( $_POST[ self::CONTACT_NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::CONTACT_NONCE_NAME ] ), self::CONTACT_NONCE_ACTION ) ) {
			return '<p class="satuyasa-notice satuyasa-notice-error">' . esc_html__( 'Sesi kedaluwarsa, silakan coba lagi.', 'satuyasa-toolkit' ) . '</p>';
		}

		// Honeypot: jika terisi, anggap spam dan diamkan tanpa mengirim email.
		if ( ! empty( $_POST['satuyasa_website'] ) ) {
			return '<p class="satuyasa-notice satuyasa-notice-success">' . esc_html__( 'Terima kasih, pesan Anda telah terkirim.', 'satuyasa-toolkit' ) . '</p>';
		}

		$name    = isset( $_POST['satuyasa_name'] ) ? sanitize_text_field( wp_unslash( $_POST['satuyasa_name'] ) ) : '';
		$email   = isset( $_POST['satuyasa_email'] ) ? sanitize_email( wp_unslash( $_POST['satuyasa_email'] ) ) : '';
		$message = isset( $_POST['satuyasa_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['satuyasa_message'] ) ) : '';

		if ( ! $name || ! $email || ! is_email( $email ) || ! $message ) {
			return '<p class="satuyasa-notice satuyasa-notice-error">' . esc_html__( 'Mohon lengkapi semua kolom dengan benar.', 'satuyasa-toolkit' ) . '</p>';
		}

		$to      = satuyasa_toolkit_get_option( 'contact_email', get_option( 'admin_email' ) );
		$subject = sprintf(
			/* translators: %s: nama situs. */
			__( 'Pesan baru dari formulir kontak %s', 'satuyasa-toolkit' ),
			get_bloginfo( 'name' )
		);
		$body = sprintf(
			"%s: %s\n%s: %s\n\n%s:\n%s",
			__( 'Nama', 'satuyasa-toolkit' ),
			$name,
			__( 'Email', 'satuyasa-toolkit' ),
			$email,
			__( 'Pesan', 'satuyasa-toolkit' ),
			$message
		);
		$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( $sent ) {
			return '<p class="satuyasa-notice satuyasa-notice-success">' . esc_html__( 'Terima kasih, pesan Anda telah terkirim.', 'satuyasa-toolkit' ) . '</p>';
		}

		return '<p class="satuyasa-notice satuyasa-notice-error">' . esc_html__( 'Maaf, pesan gagal terkirim. Silakan coba lagi nanti.', 'satuyasa-toolkit' ) . '</p>';
	}
}
