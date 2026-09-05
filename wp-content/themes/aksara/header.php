<?php
/**
 * Kerangka header situs.
 *
 * Berkas ini SENGAJA hanya menyusun, tidak berisi markup komponen. Isinya
 * ada di template-parts/header/, satu berkas per komponen.
 *
 * KENAPA BEGINI — ini bukan selera, ini pelajaran dari kode ini sendiri.
 * Dulu ada header-foundry.php: sebuah header kedua untuk halaman Free Font
 * yang MENGGANDAKAN branding, navigasi, Sign in dan Cart dari berkas ini.
 * Begitu header utama berubah, salinannya tidak ikut. Ia melenceng, lalu
 * ditinggalkan sama sekali (dan kini dihapus). Dengan pemecahan ini, varian
 * header apa pun cukup menyusun ulang PART YANG SAMA — jadi perubahan pada
 * penghitung keranjang, misalnya, mendarat di semua tempat sekaligus dan
 * tidak bisa lagi melenceng diam-diam.
 *
 * Kalau suatu saat perlu header berbeda untuk sebagian halaman: buat
 * header-<nama>.php yang memanggil part yang sama, lalu panggil
 * get_header( '<nama>' ). Tapi lakukan itu hanya kalau KERANGKA halamannya
 * memang berbeda — landmark lain, tata letak lain. Kalau bedanya cuma warna,
 * itu urusan body class dan CSS, bukan berkas header baru.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'aksara' ); ?></a>

	<?php get_template_part( 'template-parts/header/topbar' ); ?>

	<header id="masthead" class="site-header">
		<div class="wrap site-header-inner">
			<?php get_template_part( 'template-parts/header/branding' ); ?>
			<?php get_template_part( 'template-parts/header/nav-primary' ); ?>
			<?php get_template_part( 'template-parts/header/actions' ); ?>
		</div>
	</header>

	<div id="content" class="site-content">
