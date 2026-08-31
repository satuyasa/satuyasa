<?php
/**
 * Tambahkan tautan unduh & lampiran sertifikat lisensi ke email order
 * WooCommerce yang dikirim ke pembeli — TANPA meng-override seluruh
 * template email (cukup 2 hook ekstensi resmi WooCommerce).
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Order_Emails.
 */
class Aksara_Order_Emails {

	/**
	 * ID email WooCommerce yang relevan (dikirim ke pembeli, bukan admin).
	 *
	 * @var string[]
	 */
	const CUSTOMER_EMAIL_IDS = array( 'customer_completed_order', 'customer_processing_order' );

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_download_section' ), 10, 4 );
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attach_certificate' ), 10, 4 );
	}

	/**
	 * Sisipkan daftar tautan unduh (font & Canva) setelah tabel item order.
	 *
	 * @param WC_Order $order         Order terkait.
	 * @param bool     $sent_to_admin Apakah email ini untuk admin (bukan pembeli).
	 * @param bool     $plain_text    Apakah email versi plain-text.
	 * @param WC_Email $email         Objek email WooCommerce.
	 */
	public static function add_download_section( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! in_array( $email->id, self::CUSTOMER_EMAIL_IDS, true ) ) {
			return;
		}

		$tokens = Aksara_Download_Tokens_Repository::get_by_order( $order->get_id() );
		if ( empty( $tokens ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Tautan Unduh:', 'aksara-marketplace' ) . "\n";
			foreach ( $tokens as $token_row ) {
				echo '- ' . esc_url( Aksara_Download_Manager::get_download_url( $token_row->token ) ) . "\n";
			}
			return;
		}

		echo '<h2>' . esc_html__( 'Tautan Unduh', 'aksara-marketplace' ) . '</h2>';
		echo '<ul style="margin:0 0 16px;padding:0 0 0 20px;">';
		foreach ( $tokens as $token_row ) {
			$label = Aksara_Download_Tokens_Repository::RESOURCE_FONT_STYLE === $token_row->resource_type
				? ( ( $style = Aksara_Font_Styles_Repository::get( $token_row->resource_id ) ) ? $style->style_name : __( 'Berkas', 'aksara-marketplace' ) )
				: ( get_the_title( $token_row->resource_id ) ?: __( 'Berkas', 'aksara-marketplace' ) );

			printf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( Aksara_Download_Manager::get_download_url( $token_row->token ) ),
				esc_html( $label )
			);
		}
		echo '</ul>';

		echo '<p>' . sprintf(
			/* translators: %s: tautan ke My Account. */
			wp_kses(
				__( 'Anda juga bisa mengunduh ulang kapan saja lewat <a href="%s">My Account &gt; Unduhan Saya</a>.', 'aksara-marketplace' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( wc_get_endpoint_url( 'aksara-downloads', '', wc_get_page_permalink( 'myaccount' ) ) )
		) . '</p>';
	}

	/**
	 * Lampirkan PDF sertifikat lisensi (kalau ada) ke email order pembeli.
	 *
	 * @param string[] $attachments Daftar path lampiran yang sudah ada.
	 * @param string   $email_id    ID email WooCommerce.
	 * @param mixed    $object      Order (atau objek lain, tergantung email).
	 * @param WC_Email $email       Objek email.
	 * @return string[]
	 */
	public static function attach_certificate( $attachments, $email_id, $object, $email = null ) {
		if ( ! in_array( $email_id, self::CUSTOMER_EMAIL_IDS, true ) || ! ( $object instanceof WC_Order ) ) {
			return $attachments;
		}

		$certificate = Aksara_License_Certificates_Repository::get_by_order( $object->get_id() );
		if ( ! $certificate ) {
			return $attachments;
		}

		$path = Aksara_File_Storage::get_absolute_path( $certificate->file_path );
		if ( file_exists( $path ) ) {
			$attachments[] = $path;
		}

		return $attachments;
	}
}
