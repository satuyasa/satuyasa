<?php
/**
 * Penulis PDF minimal, TANPA dependency eksternal (bukan dompdf/mpdf/TCPDF).
 *
 * Keputusan arsitektur: plugin ini sengaja dibangun tanpa Composer/vendor
 * directory (murni PHP, konsisten dengan seluruh kode lain di repo ini).
 * Menambahkan library PDF-dari-HTML sungguhan (dompdf dkk.) berarti
 * menambah beberapa MB dependency untuk kebutuhan yang sebenarnya sangat
 * sederhana: satu halaman teks berisi bukti lisensi. Class ini menulis
 * PDF 1.4 yang valid secara langsung — satu halaman, font standar
 * (Helvetica/Helvetica-Bold, bagian dari 14 font standar yang WAJIB
 * didukung setiap pembaca PDF sesuai spesifikasi, jadi tidak perlu
 * embed font sama sekali) — cukup untuk sertifikat lisensi, tidak untuk
 * kebutuhan PDF umum (tidak ada gambar, tabel kompleks, multi-halaman,
 * atau non-Latin script di luar cakupan WinAnsiEncoding).
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Simple_Pdf.
 */
class Aksara_Simple_Pdf {

	/**
	 * @var float
	 */
	private $width;

	/**
	 * @var float
	 */
	private $height;

	/**
	 * Potongan operator content stream yang sudah ditulis.
	 *
	 * @var string[]
	 */
	private $ops = array();

	/**
	 * @param float $width  Lebar halaman dalam poin (A4 = 595.28).
	 * @param float $height Tinggi halaman dalam poin (A4 = 841.89).
	 */
	public function __construct( $width = 595.28, $height = 841.89 ) {
		$this->width  = $width;
		$this->height = $height;
	}

	/**
	 * Tulis satu baris teks pada posisi absolut (dari kiri-bawah halaman,
	 * sesuai sistem koordinat PDF).
	 *
	 * @param float  $x     Posisi X (poin).
	 * @param float  $y     Posisi Y (poin, dari bawah).
	 * @param string $text  Teks (UTF-8).
	 * @param float  $size  Ukuran font (poin).
	 * @param bool   $bold  Pakai Helvetica-Bold kalau true.
	 */
	public function text( $x, $y, $text, $size = 11, $bold = false ) {
		$font = $bold ? 'F2' : 'F1';
		$this->ops[] = sprintf(
			"BT /%s %s Tf 1 0 0 1 %s %s Tm (%s) Tj ET",
			$font,
			$this->num( $size ),
			$this->num( $x ),
			$this->num( $y ),
			$this->escape_text( $text )
		);
	}

	/**
	 * Gambar garis horizontal tipis — dipakai sebagai pemisah section.
	 *
	 * @param float $x1 X awal.
	 * @param float $y  Y (sama untuk kedua titik).
	 * @param float $x2 X akhir.
	 */
	public function hr( $x1, $y, $x2 ) {
		$this->ops[] = sprintf( '0.7 G %s %s m %s %s l S', $this->num( $x1 ), $this->num( $y ), $this->num( $x2 ), $this->num( $y ) );
	}

	/**
	 * Gambar kotak bingkai tipis di sekeliling halaman (dekorasi sertifikat).
	 *
	 * @param float $margin Jarak dari tepi halaman (poin).
	 */
	public function border( $margin = 24 ) {
		$this->ops[] = sprintf(
			'0.55 G 1.2 w %s %s %s %s re S',
			$this->num( $margin ),
			$this->num( $margin ),
			$this->num( $this->width - ( 2 * $margin ) ),
			$this->num( $this->height - ( 2 * $margin ) )
		);
	}

	/**
	 * Rakit & kembalikan isi PDF sebagai string biner.
	 *
	 * @return string
	 */
	public function output() {
		$content_stream = implode( "\n", $this->ops );

		$objects = array();

		$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
		$objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
		$objects[3] = sprintf(
			"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>",
			$this->num( $this->width ),
			$this->num( $this->height )
		);
		$objects[4] = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", strlen( $content_stream ), $content_stream );
		$objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
		$objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array( 0 => 0 );

		foreach ( $objects as $num => $body ) {
			$offsets[ $num ] = strlen( $pdf );
			$pdf            .= "{$num} 0 obj\n{$body}\nendobj\n";
		}

		$xref_offset = strlen( $pdf );
		$count       = count( $objects ) + 1;

		$pdf .= "xref\n0 {$count}\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $i = 1; $i < $count; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}

		$pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";

		return $pdf;
	}

	/**
	 * Format angka tanpa desimal berlebihan (PDF tidak butuh notasi ilmiah/presisi panjang).
	 *
	 * @param float $value Nilai.
	 * @return string
	 */
	private function num( $value ) {
		return rtrim( rtrim( sprintf( '%.2F', $value ), '0' ), '.' );
	}

	/**
	 * Konversi UTF-8 ke WinAnsiEncoding (~Windows-1252) & escape karakter
	 * spesial string PDF ( ) \ .
	 *
	 * @param string $text Teks UTF-8.
	 * @return string
	 */
	private function escape_text( $text ) {
		$converted = function_exists( 'mb_convert_encoding' )
			? @mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' ) // phpcs:ignore WordPress.PHP.NoSilencedErrors -- karakter di luar Windows-1252 sengaja diabaikan agar tidak fatal, cukup untuk konten sertifikat berbahasa Indonesia.
			: $text;

		if ( false === $converted || null === $converted ) {
			$converted = preg_replace( '/[^\x20-\x7E]/', '?', $text );
		}

		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $converted );
	}
}
