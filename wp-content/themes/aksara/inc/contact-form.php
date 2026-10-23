<?php
/**
 * Formulir kontak: shortcode [aksara_contact_form].
 *
 * KENAPA DIBUAT SENDIRI DAN BUKAN MEMASANG PLUGIN FORMULIR
 *
 * Yang dibutuhkan halaman Contact cuma empat kolom dan satu email. Plugin
 * formulir umum membawa pembuat formulir, penyimpanan entri, integrasi, dan
 * asetnya sendiri di setiap halaman — jauh lebih besar dari kebutuhannya, dan
 * setiap kolom yang disimpan di basis data adalah data pribadi yang harus
 * ikut dijaga dan dijelaskan di Privacy Policy. Di sini TIDAK ADA yang
 * disimpan: pesan dikirim lewat wp_mail() lalu selesai.
 *
 * URUTAN PERTAHANANNYA, dari yang paling murah ke yang paling mahal:
 *
 * 1. Honeypot. Kolom yang disembunyikan dari mata manusia lewat CSS. Bot yang
 *    mengisi semua kolom akan mengisinya juga, dan permintaannya dibuang
 *    tanpa biaya apa pun. Disembunyikan dengan CSS, bukan type="hidden",
 *    karena bot membaca type="hidden" dan justru melewatinya.
 * 2. Nonce. Menghentikan pengiriman dari luar halaman ini.
 * 3. Batas laju per IP. Tiga pesan per jam. Ini yang menahan bot yang cukup
 *    pintar untuk melewati dua lapis pertama, dan juga menahan orang yang
 *    menekan kirim berulang kali karena kesal.
 *
 * DAN SATU HAL YANG SENGAJA TIDAK DILAKUKAN: alamat email pengirim TIDAK
 * pernah dipakai sebagai header From. Itu jalan masuk pemalsuan header, dan
 * juga membuat email ditolak SPF/DKIM domain penerima. From tetap alamat
 * situs sendiri; alamat pengirim ditaruh di Reply-To sesudah lolos is_email().
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Alamat tujuan; bisa diubah di Customizer, bawaannya email admin. */
function aksara_contact_recipient() {
	$to = trim( (string) get_theme_mod( 'aksara_contact_email', '' ) );
	if ( '' === $to || ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}
	return $to;
}

/** Kunci transient batas laju untuk IP saat ini. */
function aksara_contact_rate_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	// Di-hash, bukan disimpan apa adanya: kunci transient tersimpan di
	// basis data, dan alamat IP mentah di sana adalah data pribadi yang
	// disimpan tanpa alasan.
	return 'aksara_contact_' . md5( $ip . wp_salt() );
}

/**
 * Proses pengiriman sebelum halaman dirender.
 *
 * Pola Post/Redirect/Get: sesudah diproses selalu redirect, jadi menekan
 * muat-ulang tidak mengirim pesan yang sama dua kali.
 */
function aksara_contact_handle() {
	if ( ! isset( $_POST['aksara_contact_submit'] ) ) {
		return;
	}

	$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! isset( $_POST['aksara_contact_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aksara_contact_nonce'] ) ), 'aksara_contact' ) ) {
		wp_safe_redirect( add_query_arg( 'contact', 'error', $redirect ) );
		exit;
	}

	// Honeypot terisi: berpura-pura berhasil. Memberi tahu bot bahwa ia
	// ketahuan hanya membantunya memperbaiki diri.
	if ( ! empty( $_POST['aksara_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'contact', 'sent', $redirect ) );
		exit;
	}

	$key   = aksara_contact_rate_key();
	$count = (int) get_transient( $key );
	if ( $count >= 3 ) {
		wp_safe_redirect( add_query_arg( 'contact', 'throttled', $redirect ) );
		exit;
	}

	$name    = isset( $_POST['aksara_name'] ) ? sanitize_text_field( wp_unslash( $_POST['aksara_name'] ) ) : '';
	$email   = isset( $_POST['aksara_email'] ) ? sanitize_email( wp_unslash( $_POST['aksara_email'] ) ) : '';
	$subject = isset( $_POST['aksara_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['aksara_subject'] ) ) : '';
	$message = isset( $_POST['aksara_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['aksara_message'] ) ) : '';

	if ( '' === $name || '' === $message || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'contact', 'invalid', $redirect ) );
		exit;
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	$body = sprintf(
		/* translators: 1: nama, 2: email, 3: halaman asal, 4: isi pesan. */
		__( "From: %1\$s <%2\$s>\nSent from: %3\$s\n\n%4\$s", 'aksara' ),
		$name,
		$email,
		$redirect,
		$message
	);

	$sent = wp_mail(
		aksara_contact_recipient(),
		sprintf(
			/* translators: 1: nama situs, 2: subjek dari pengirim. */
			__( '[%1$s] %2$s', 'aksara' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'' !== $subject ? $subject : __( 'Message from the contact form', 'aksara' )
		),
		$body,
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	wp_safe_redirect( add_query_arg( 'contact', $sent ? 'sent' : 'failed', $redirect ) );
	exit;
}
add_action( 'template_redirect', 'aksara_contact_handle' );

/**
 * Render formulirnya.
 *
 * @return string
 */
function aksara_contact_form_shortcode() {
	$state = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$notices = array(
		'sent'      => array( 'ok',    __( 'Thank you — your message is on its way. We reply within two working days.', 'aksara' ) ),
		'invalid'   => array( 'error', __( 'Please fill in your name, a valid email address, and a message.', 'aksara' ) ),
		'throttled' => array( 'error', __( 'That is three messages in an hour from this connection. Please email us directly instead.', 'aksara' ) ),
		'failed'    => array( 'error', __( 'The message could not be sent. Please email us directly instead.', 'aksara' ) ),
		'error'     => array( 'error', __( 'The form expired. Please try again.', 'aksara' ) ),
	);

	ob_start();

	if ( isset( $notices[ $state ] ) ) {
		printf(
			'<p class="contact-notice contact-notice--%1$s" role="status">%2$s</p>',
			esc_attr( $notices[ $state ][0] ),
			esc_html( $notices[ $state ][1] )
		);
	}

	// Sesudah berhasil, formulirnya tidak dicetak lagi: yang baru saja
	// mengirim tidak sedang mencari kotak isian, dan menampilkannya kembali
	// dalam keadaan kosong membuat orang ragu apakah pesannya benar terkirim.
	if ( 'sent' === $state ) {
		return ob_get_clean();
	}
	?>
	<form class="contact-form" method="post" action="">
		<?php wp_nonce_field( 'aksara_contact', 'aksara_contact_nonce' ); ?>

		<p class="contact-form__field">
			<label for="aksara-name"><?php esc_html_e( 'Your name', 'aksara' ); ?></label>
			<input id="aksara-name" name="aksara_name" type="text" required>
		</p>
		<p class="contact-form__field">
			<label for="aksara-email"><?php esc_html_e( 'Your email', 'aksara' ); ?></label>
			<input id="aksara-email" name="aksara_email" type="email" required>
		</p>
		<p class="contact-form__field">
			<label for="aksara-subject"><?php esc_html_e( 'Subject', 'aksara' ); ?></label>
			<input id="aksara-subject" name="aksara_subject" type="text">
		</p>
		<p class="contact-form__field">
			<label for="aksara-message"><?php esc_html_e( 'Message', 'aksara' ); ?></label>
			<textarea id="aksara-message" name="aksara_message" rows="7" required></textarea>
		</p>

		<?php
		/*
		 * Honeypot. Disembunyikan lewat .screen-reader-text milik tema —
		 * satu pola sembunyi untuk seluruh tema, bukan salinan kedua yang
		 * bisa melenceng (lihat catatannya di style.css).
		 *
		 * aria-hidden + tabindex="-1" supaya pembaca layar dan keyboard
		 * melewatinya sepenuhnya: kolom ini tidak boleh menjebak manusia,
		 * hanya bot yang membaca HTML dan mengisi semuanya. Perhatikan bahwa
		 * .screen-reader-text sendiri justru dibuat AGAR terbaca pembaca
		 * layar; aria-hidden di sini yang membatalkannya, dan pasangan itu
		 * memang disengaja. autocomplete="off" mencegah peramban
		 * mengisikannya sendiri.
		 */
		?>
		<p class="contact-form__trap screen-reader-text" aria-hidden="true">
			<label for="aksara-website"><?php esc_html_e( 'Leave this field empty', 'aksara' ); ?></label>
			<input id="aksara-website" name="aksara_website" type="text" tabindex="-1" autocomplete="off">
		</p>

		<p><button type="submit" name="aksara_contact_submit" value="1"><?php esc_html_e( 'Send message', 'aksara' ); ?></button></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'aksara_contact_form', 'aksara_contact_form_shortcode' );
