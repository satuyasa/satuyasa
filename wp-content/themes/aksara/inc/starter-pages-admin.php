<?php
/**
 * Layar admin untuk membuat halaman bawaan: Appearance > Aksara Pages.
 *
 * Idempoten dan tidak merusak. Halaman yang slug-nya SUDAH ADA dilewati,
 * apa pun statusnya dan siapa pun yang membuatnya — tema tidak boleh menimpa
 * tulisan orang. Yang dibuat cuma yang benar-benar belum ada, dan hasilnya
 * dilaporkan satu per satu supaya tidak ada yang terjadi diam-diam.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Daftarkan layar di bawah menu Appearance. */
function aksara_starter_pages_menu() {
	add_theme_page(
		__( 'Aksara Pages', 'aksara' ),
		__( 'Aksara Pages', 'aksara' ),
		'edit_pages',
		'aksara-pages',
		'aksara_starter_pages_screen'
	);
}
add_action( 'admin_menu', 'aksara_starter_pages_menu' );

/**
 * Cari halaman yang sudah ada berdasarkan slug.
 *
 * get_page_by_path() dipakai, BUKAN pencarian judul: judul boleh diganti
 * pemilik situs kapan saja ("About" jadi "About the studio"), sedangkan slug
 * adalah yang menentukan alamatnya. Mencocokkan judul akan membuat halaman
 * yang sudah diedit terlihat seperti belum ada, lalu dibuat dua kali.
 *
 * @param string $slug Slug halaman.
 * @return WP_Post|null
 */
function aksara_starter_find_page( $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page instanceof WP_Post ? $page : null;
}

/** Kerjakan pembuatan halaman. */
function aksara_starter_pages_create() {
	$results = array();

	foreach ( aksara_starter_pages() as $slug => $page ) {
		$existing = aksara_starter_find_page( $slug );

		if ( $existing ) {
			$results[] = array(
				'title'  => $page['title'],
				'state'  => 'skipped',
				'id'     => $existing->ID,
				'status' => $existing->post_status,
			);
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => $page['title'],
			'post_content' => $page['content'],
			'post_status'  => $page['status'],
		), true );

		if ( is_wp_error( $post_id ) ) {
			$results[] = array( 'title' => $page['title'], 'state' => 'error', 'message' => $post_id->get_error_message() );
			continue;
		}

		if ( ! empty( $page['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', $page['template'] );
		}

		$results[] = array(
			'title'  => $page['title'],
			'state'  => 'created',
			'id'     => $post_id,
			'status' => $page['status'],
		);
	}

	return $results;
}

/** Render layarnya. */
function aksara_starter_pages_screen() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'aksara' ) );
	}

	$results = null;

	if ( isset( $_POST['aksara_create_pages'] ) ) {
		check_admin_referer( 'aksara_create_pages' );
		// publish_pages diperiksa terpisah dari edit_pages: membuat halaman
		// berstatus publish adalah tindakan menerbitkan, dan seorang
		// Contributor tidak boleh melakukannya lewat pintu belakang ini.
		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to publish pages.', 'aksara' ) );
		}
		$results = aksara_starter_pages_create();
	}

	$pages = aksara_starter_pages();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Aksara Pages', 'aksara' ); ?></h1>
		<p><?php esc_html_e( 'Creates the standard pages this theme expects, with drafted content you then edit like any other page. Pages whose slug already exists are skipped, never overwritten.', 'aksara' ); ?></p>
		<p><strong><?php esc_html_e( 'Privacy Policy, Terms of Use, and Refund Policy are created as drafts.', 'aksara' ); ?></strong>
			<?php esc_html_e( 'They are outlines with values in square brackets for you to fill in, not finished legal text, and each carries a notice to delete before publishing. Have them reviewed by a lawyer in your jurisdiction.', 'aksara' ); ?></p>

		<?php if ( null !== $results ) : ?>
			<h2><?php esc_html_e( 'Result', 'aksara' ); ?></h2>
			<table class="widefat striped" style="max-width:52em">
				<tbody>
				<?php foreach ( $results as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
						<td>
							<?php
							if ( 'created' === $row['state'] ) {
								printf(
									/* translators: %s: status halaman (publish/draft). */
									esc_html__( 'Created as %s', 'aksara' ),
									esc_html( $row['status'] )
								);
							} elseif ( 'skipped' === $row['state'] ) {
								esc_html_e( 'Already exists — left untouched', 'aksara' );
							} else {
								echo esc_html( $row['message'] );
							}
							?>
						</td>
						<td>
							<?php if ( ! empty( $row['id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'aksara' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><?php esc_html_e( 'Next: add the pages you want to the footer menus under Appearance → Menus.', 'aksara' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Pages', 'aksara' ); ?></h2>
		<table class="widefat striped" style="max-width:52em">
			<thead><tr>
				<th><?php esc_html_e( 'Page', 'aksara' ); ?></th>
				<th><?php esc_html_e( 'Will be created as', 'aksara' ); ?></th>
				<th><?php esc_html_e( 'Status now', 'aksara' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $pages as $slug => $page ) : ?>
				<?php $existing = aksara_starter_find_page( $slug ); ?>
				<tr>
					<td><strong><?php echo esc_html( $page['title'] ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
					<td><?php echo esc_html( $page['status'] ); ?></td>
					<td>
						<?php if ( $existing ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $existing->ID ) ); ?>"><?php esc_html_e( 'Exists', 'aksara' ); ?></a>
						<?php else : ?>
							<?php esc_html_e( 'Not created yet', 'aksara' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" style="margin-top:1.5em">
			<?php wp_nonce_field( 'aksara_create_pages' ); ?>
			<?php submit_button( __( 'Create the missing pages', 'aksara' ), 'primary', 'aksara_create_pages' ); ?>
		</form>
	</div>
	<?php
}
