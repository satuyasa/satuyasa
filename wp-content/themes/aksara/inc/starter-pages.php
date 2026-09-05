<?php
/**
 * Halaman bawaan situs: About, Contact, FAQ, Licenses, Privacy, Terms, dll.
 *
 * KENAPA DIBUAT SEBAGAI PAGE, BUKAN DITULIS DI TEMPLATE
 *
 * Isi halaman-halaman ini milik pemilik situs, bukan milik tema. Alamat
 * berubah, kebijakan direvisi, pertanyaan yang sering masuk bergeser. Kalau
 * teksnya ditulis di berkas PHP, setiap perubahan kecil jadi pekerjaan
 * pengembang — dan yang lebih buruk, ia akan HILANG saat tema diperbarui.
 * Jadi tema hanya MENYIAPKAN halamannya sekali; sesudah itu semuanya diedit
 * lewat editor WordPress biasa.
 *
 * KENAPA LEWAT TOMBOL, BUKAN OTOMATIS SAAT AKTIVASI
 *
 * Membuat delapan halaman diam-diam saat tema diaktifkan adalah perubahan
 * besar pada situs orang tanpa diminta — apalagi kalau sebagian sudah ada
 * dengan isi mereka sendiri. Di sini pemilik situs menekan tombolnya sendiri,
 * melihat daftar apa yang akan dibuat lebih dulu, dan halaman yang slug-nya
 * sudah ada DILEWATI, tidak ditimpa.
 *
 * DOKUMEN HUKUM DIBUAT SEBAGAI DRAF, DAN ITU DISENGAJA
 *
 * Privacy Policy, Terms of Use, dan Refund Policy di sini adalah KERANGKA
 * dengan tanda kurung siku yang harus diisi, bukan naskah hukum siap pakai.
 * Saya tidak bisa tahu badan hukum, yurisdiksi, atau prosesor pembayaran
 * situs ini, dan teks hukum yang salah lebih berbahaya daripada tidak ada.
 * Karena itu ketiganya berstatus draf: menerbitkannya butuh tindakan sadar,
 * dan di paling atas ada peringatan yang harus dihapus dulu.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Peringatan yang dipasang di atas setiap dokumen hukum.
 *
 * @return string
 */
function aksara_starter_legal_notice() {
	return '<blockquote><p><strong>' . esc_html__( 'Draft — not legal advice.', 'aksara' ) . '</strong> '
		. esc_html__( 'Replace every value in square brackets with your own details, then have this reviewed by a lawyer in your jurisdiction. Delete this notice before publishing.', 'aksara' )
		. '</p></blockquote>';
}

/**
 * Definisi seluruh halaman bawaan.
 *
 * Dipisah dari kode yang membuatnya supaya isinya bisa dibaca dan diubah
 * tanpa menyentuh logika sama sekali.
 *
 * @return array<string,array<string,mixed>>
 */
function aksara_starter_pages() {
	$site = get_bloginfo( 'name' );

	$pages = array();

	$pages['about'] = array(
		'title'   => __( 'About', 'aksara' ),
		'status'  => 'publish',
		'content' =>
			'<p>' . esc_html__( 'We publish original typefaces, Canva templates, and design elements for people who make things. Everything here is drawn or built in-house, licensed in plain language, and delivered the moment you pay for it.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'What we make', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Type comes first. Each family is drawn for a job — signage, long reading, headlines — and released only when it holds up at the size it was made for. Templates and elements exist for the same reason: to get good work out the door faster, without starting from a blank canvas.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'How we license', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'One flat price for a web license, with the terms written so you can read them in a minute. No per-seat maths, no annual renewal, no clause that quietly expires. If a licence does not cover what you need, write to us and we will say so plainly.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Where we are', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'We work from [city], Indonesia, and sell to anyone, anywhere. Support is handled by the same people who draw the fonts.', 'aksara' ) . '</p>',
	);

	$pages['contact'] = array(
		'title'   => __( 'Contact', 'aksara' ),
		'status'  => 'publish',
		'content' =>
			'<p>' . esc_html__( 'Questions about a licence, a file that will not install, or a custom commission — send them here. We answer every message ourselves.', 'aksara' ) . '</p>'
			. '[aksara_contact_form]'
			. '<h2>' . esc_html__( 'Other ways to reach us', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Email: [you@example.com]', 'aksara' ) . '<br>'
			. esc_html__( 'Post: [street address], [city], Indonesia', 'aksara' ) . '</p>'
			. '<p>' . esc_html__( 'We reply within two working days. Licence and refund questions are answered by a person, not a template.', 'aksara' ) . '</p>',
	);

	$pages['faq'] = array(
		'title'   => __( 'Frequently Asked Questions', 'aksara' ),
		'status'  => 'publish',
		'content' =>
			'<p>' . esc_html__( 'The questions we are asked most, answered directly. If yours is not here, write to us.', 'aksara' ) . '</p>'
			. aksara_starter_faq_item(
				__( 'What do I actually get when I buy a font?', 'aksara' ),
				__( 'A download containing the font files for the styles you bought, plus a licence certificate as a PDF. The download link appears in your account and in your order email straight after payment.', 'aksara' )
			)
			. aksara_starter_faq_item(
				__( 'Can I use these fonts for client work?', 'aksara' ),
				__( 'Yes. The licence covers work you produce for clients, including logos and printed material. What it does not cover is passing the font files themselves to the client — they need their own licence for that.', 'aksara' )
			)
			. aksara_starter_faq_item(
				__( 'Do the free fonts have different terms?', 'aksara' ),
				__( 'Yes, and each one states its own licence on its page. Most are released under the SIL Open Font License, which allows commercial use and modification provided derivatives keep the same licence. Read the licence on the release you are downloading — they are not all identical.', 'aksara' )
			)
			. aksara_starter_faq_item(
				__( 'How do I install a font?', 'aksara' ),
				__( 'Unzip the download, then double-click each font file and confirm. On Windows you can also right-click and choose Install. Restart any application that was already open before it will see the new family.', 'aksara' )
			)
			. aksara_starter_faq_item(
				__( 'Can I get a refund?', 'aksara' ),
				__( 'Digital files cannot be returned once downloaded, so we do not refund by default. If a file is broken, incomplete, or not what the page described, tell us and we will fix it or refund you. See the Refund Policy for the full terms.', 'aksara' )
			)
			. aksara_starter_faq_item(
				__( 'Which payment methods do you accept?', 'aksara' ),
				__( 'PayPal, which also handles card payments. Your card details never reach our servers.', 'aksara' )
			),
	);

	$pages['licenses'] = array(
		'title'    => __( 'Licenses', 'aksara' ),
		'status'   => 'publish',
		'template' => 'page-templates/template-license.php',
		'content'  =>
			'<p>' . esc_html__( 'Every licence we sell is listed below, generated from the same data the shop uses — so what you read here is what you are actually buying. Terms are written to be read in a minute, not skimmed and hoped over.', 'aksara' ) . '</p>',
	);

	$pages['font-installation'] = array(
		'title'   => __( 'Installing your fonts', 'aksara' ),
		'status'  => 'publish',
		'content' =>
			'<p>' . esc_html__( 'A short guide for getting a purchased family onto your machine and into your software.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'macOS', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Unzip the download. Select every font file, then double-click and choose Install Font. Font Book will warn you about duplicates if you already own an earlier version — resolve those before installing.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Windows', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Unzip the download, select every font file, right-click, and choose Install for all users. Installing for all users avoids the case where a font is visible in one application but missing in another.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'The font is installed but my software cannot see it', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Applications read the font list once at launch. Quit the application completely and open it again — not just the document.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Which file format should I install?', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Install the OTF or TTF files for desktop use. The WOFF2 files in the same download are for websites and are uploaded to your server, not installed on your machine.', 'aksara' ) . '</p>',
	);

	/* --- Dokumen hukum: draf, dengan peringatan di paling atas ------------ */

	$pages['privacy-policy'] = array(
		'title'    => __( 'Privacy Policy', 'aksara' ),
		'status'   => 'draft',
		'template' => 'page-templates/template-document.php',
		'content'  => aksara_starter_legal_notice()
			. '<p>' . sprintf(
				/* translators: %s: nama situs. */
				esc_html__( 'This policy explains what %s collects, why, and what you can ask us to do with it.', 'aksara' ),
				esc_html( $site )
			) . '</p>'
			. '<h2>' . esc_html__( 'Who we are', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'The data controller is [legal entity name], [address], Indonesia. Reach us at [you@example.com].', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'What we collect', 'aksara' ) . '</h2>'
			. '<ul>'
			. '<li>' . esc_html__( 'Account details you give us: name, email address, and password (stored hashed, never in readable form).', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Order details: what you bought, when, and the billing address required for the invoice.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Payment data is handled entirely by [payment processor]. Card numbers never reach our servers.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Server logs, including IP address, kept to detect abuse of download links.', 'aksara' ) . '</li>'
			. '</ul>'
			. '<h2>' . esc_html__( 'Why we collect it', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'To deliver what you bought, to issue invoices and licence certificates, to answer support requests, and to meet the record-keeping our tax law requires. We do not sell your data and we do not use it to profile you.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'How long we keep it', 'aksara' ) . '</h2>'
			. '<ul>'
			. '<li>' . esc_html__( 'Order and invoice records: [7] years, because [tax regulation] requires it.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Support correspondence: [2] years.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Server logs: [90] days.', 'aksara' ) . '</li>'
			. '</ul>'
			. '<h2>' . esc_html__( 'Your rights', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'You can ask for a copy of your data, ask us to correct it, or ask us to delete it — except records we are legally required to keep. Write to [you@example.com] and we will answer within [30] days.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Cookies', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'We set cookies to keep you logged in and to remember your cart. [Describe any analytics or advertising cookies here, or state that there are none.]', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Changes', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'When this policy changes, the date at the top of this page changes with it.', 'aksara' ) . '</p>',
	);

	$pages['terms-of-use'] = array(
		'title'    => __( 'Terms of Use', 'aksara' ),
		'status'   => 'draft',
		'template' => 'page-templates/template-document.php',
		'content'  => aksara_starter_legal_notice()
			. '<p>' . esc_html__( 'By using this site or buying from it, you agree to these terms.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'What you are buying', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'You are buying a licence to use the files, not ownership of the design. Copyright stays with us. What each licence permits is set out in full on the Licenses page, which forms part of these terms.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'What you may not do', 'aksara' ) . '</h2>'
			. '<ul>'
			. '<li>' . esc_html__( 'Redistribute, resell, or share the files, including to clients or colleagues who do not hold their own licence.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Publish the files where they can be downloaded by others, including in a public repository or an unprotected web directory.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'Claim authorship of the designs, or register them as your own trademark.', 'aksara' ) . '</li>'
			. '</ul>'
			. '<h2>' . esc_html__( 'Accounts', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'You are responsible for what happens under your account. Tell us immediately if you believe someone else has access to it.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Availability', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'We aim to keep the site and your download links available, but we do not guarantee uninterrupted service. Download links may expire; write to us and we will reissue them.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Liability', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'To the extent permitted by [applicable law], our liability is limited to what you paid for the item in question. [Have a lawyer check this clause — limitation of liability is the clause most often unenforceable when copied from another site.]', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Governing law', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'These terms are governed by the laws of [jurisdiction], and disputes are heard by the courts of [venue].', 'aksara' ) . '</p>',
	);

	$pages['refund-policy'] = array(
		'title'    => __( 'Refund Policy', 'aksara' ),
		'status'   => 'draft',
		'template' => 'page-templates/template-document.php',
		'content'  => aksara_starter_legal_notice()
			. '<p>' . esc_html__( 'This page is not optional decoration: selling digital goods without a stated refund position is what turns a small complaint into a payment dispute.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'The general rule', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Because files are delivered immediately and cannot be returned, purchases are not refundable once the download has been accessed.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'When we do refund', 'aksara' ) . '</h2>'
			. '<ul>'
			. '<li>' . esc_html__( 'The files are corrupt, incomplete, or will not install, and we cannot fix it.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'What arrived is materially different from what the product page described.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'You were charged twice for the same item.', 'aksara' ) . '</li>'
			. '<li>' . esc_html__( 'You bought by mistake and have not downloaded the files yet — tell us within [14] days.', 'aksara' ) . '</li>'
			. '</ul>'
			. '<h2>' . esc_html__( 'How to ask', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Email [you@example.com] with your order number and what went wrong. We answer within two working days, and refunds are returned to the original payment method within [10] working days.', 'aksara' ) . '</p>'
			. '<h2>' . esc_html__( 'Your statutory rights', 'aksara' ) . '</h2>'
			. '<p>' . esc_html__( 'Nothing here removes rights you have under [applicable consumer law].', 'aksara' ) . '</p>',
	);

	/**
	 * Filter daftar halaman bawaan.
	 *
	 * @param array $pages Definisi halaman.
	 */
	return apply_filters( 'aksara_starter_pages', $pages );
}

/**
 * Satu butir FAQ sebagai <details> asli.
 *
 * @param string $question Pertanyaan.
 * @param string $answer   Jawaban.
 * @return string
 */
function aksara_starter_faq_item( $question, $answer ) {
	return '<details class="faq"><summary>' . esc_html( $question ) . '</summary><p>' . esc_html( $answer ) . '</p></details>';
}
