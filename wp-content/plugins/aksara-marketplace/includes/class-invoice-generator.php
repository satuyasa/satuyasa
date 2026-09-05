<?php
/**
 * Generate sertifikat lisensi PDF otomatis saat order selesai dibayar.
 *
 * Satu order = satu sertifikat, berisi seluruh item FONT di order tersebut
 * (produk Canva Template/Element tidak punya "lisensi" dalam pengertian
 * sistem ini — mereka pakai syarat penggunaan Canva sendiri). Dipanggil
 * dari Aksara_Download_Manager::generate_tokens_for_order() supaya
 * sertifikat & token unduh selalu dibuat dalam satu alur yang sama.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Invoice_Generator.
 */
class Aksara_Invoice_Generator {

	/**
	 * Maksimal baris item yang muat di 1 halaman sertifikat (batasan
	 * Aksara_Simple_Pdf yang cuma menulis 1 halaman, lihat catatan di sana).
	 */
	const MAX_ITEMS_PER_CERTIFICATE = 14;

	/**
	 * Buat sertifikat untuk sebuah order kalau order itu berisi produk font
	 * dan sertifikatnya belum pernah dibuat.
	 *
	 * @param WC_Order $order Order WooCommerce.
	 */
	public static function maybe_generate_for_order( $order ) {
		if ( Aksara_License_Certificates_Repository::get_by_order( $order->get_id() ) ) {
			return; // Sudah ada, jangan generate ulang & timpa file lama.
		}

		$font_items = self::collect_font_items( $order );
		if ( empty( $font_items ) ) {
			return;
		}

		$pdf_bytes = self::build_pdf( $order, $font_items );

		$filename = sprintf(
			'order-%d-%s.pdf',
			$order->get_id(),
			substr( md5( $order->get_id() . $order->get_order_key() ), 0, 10 )
		);

		$relative_path = Aksara_File_Storage::store_generated_file( 'certificates', $filename, $pdf_bytes );
		if ( is_wp_error( $relative_path ) ) {
			return;
		}

		Aksara_License_Certificates_Repository::save( $order->get_id(), $relative_path );
	}

	/**
	 * Kumpulkan item font dari order (nama produk, style, lisensi) sebagai
	 * data siap-cetak — dibaca dari meta mentah (_aksara_style_ids,
	 * _aksara_license_id), bukan dari label tampilan yang bisa berubah
	 * mengikuti bahasa situs.
	 *
	 * @param WC_Order $order Order WooCommerce.
	 * @return array
	 */
	private static function collect_font_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$style_ids_raw = $item->get_meta( '_aksara_style_ids' );
			if ( ! $style_ids_raw ) {
				continue;
			}

			$license = Aksara_Font_Licenses_Repository::get( (int) $item->get_meta( '_aksara_license_id' ) );
			if ( ! $license ) {
				continue;
			}

			$style_names = array();
			foreach ( array_filter( explode( ',', $style_ids_raw ) ) as $style_id ) {
				$style = Aksara_Font_Styles_Repository::get( (int) $style_id );
				if ( $style ) {
					$style_names[] = $style->style_name;
				}
			}

			if ( empty( $style_names ) ) {
				continue;
			}

			$items[] = array(
				'product_name' => $item->get_name(),
				'style_names'  => $style_names,
				'license_name' => $license->name,
			);
		}

		return $items;
	}

	/**
	 * Susun konten sertifikat lewat Aksara_Simple_Pdf.
	 *
	 * @param WC_Order $order      Order WooCommerce.
	 * @param array    $font_items Hasil dari collect_font_items().
	 * @return string Isi biner PDF.
	 */
	private static function build_pdf( $order, $font_items ) {
		$pdf = new Aksara_Simple_Pdf();
		$pdf->border( 24 );

		$y = 780;
		$pdf->text( 60, $y, __( 'FONT LICENSE CERTIFICATE', 'aksara-marketplace' ), 20, true );
		$y -= 14;
		$pdf->hr( 60, $y, 535 );
		$y -= 26;

		$buyer_name = trim( $order->get_formatted_billing_full_name() );
		if ( ! $buyer_name ) {
			$buyer_name = __( 'Customer', 'aksara-marketplace' );
		}

		$paid_date = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();

		/* translators: %s: order number. */
		$pdf->text( 60, $y, sprintf( __( 'Order number: #%s', 'aksara-marketplace' ), $order->get_order_number() ), 11 );
		$y -= 18;
		/* translators: %s: payment date. */
		$pdf->text( 60, $y, sprintf( __( 'Date: %s', 'aksara-marketplace' ), $paid_date ? $paid_date->date_i18n( 'j F Y' ) : '-' ), 11 );
		$y -= 18;
		/* translators: 1: buyer name, 2: buyer email address. */
		$pdf->text( 60, $y, sprintf( __( 'Buyer: %1$s (%2$s)', 'aksara-marketplace' ), $buyer_name, $order->get_billing_email() ), 11 );
		$y -= 32;

		$pdf->text( 60, $y, __( 'License Details', 'aksara-marketplace' ), 13, true );
		$y -= 22;

		$shown = array_slice( $font_items, 0, self::MAX_ITEMS_PER_CERTIFICATE );

		foreach ( $shown as $entry ) {
			$pdf->text( 60, $y, $entry['product_name'], 11, true );
			$y -= 16;
			$pdf->text( 72, $y, 'Style: ' . implode( ', ', $entry['style_names'] ), 10 );
			$y -= 14;
			$pdf->text( 72, $y, __( 'License: ', 'aksara-marketplace' ) . $entry['license_name'], 10 );
			$y -= 20;
		}

		$remaining = count( $font_items ) - count( $shown );
		if ( $remaining > 0 ) {
			$pdf->text(
				60,
				$y,
				sprintf(
					/* translators: %d: jumlah item tambahan yang tidak muat di halaman. */
					__( '+%d more license items — see the full details in your order confirmation email.', 'aksara-marketplace' ),
					$remaining
				),
				9
			);
			$y -= 20;
		}

		$y = max( $y, 70 );
		$pdf->hr( 60, $y, 535 );
		$y -= 18;
		$pdf->text( 60, $y, __( 'This document is the official proof of license for the fonts listed above.', 'aksara-marketplace' ), 9 );
		$y -= 12;
		$pdf->text( 60, $y, __( 'Keep this document as a record of your usage rights.', 'aksara-marketplace' ), 9 );

		return $pdf->output();
	}
}
