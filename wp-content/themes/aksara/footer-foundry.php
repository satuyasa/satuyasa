<?php
/**
 * Penutup kerangka halaman Free Font (sistem visual Foundry).
 *
 * Pasangan dari header-foundry.php: ia menutup <main>, .foundry-canvas,
 * .foundry-shell dan .foundry. wp_footer() wajib dipanggil — di situlah
 * WordPress dan plugin mencetak skripnya, termasuk specimen.js milik
 * Authentype yang menggerakkan canvas spesimen dan formulir unduhan.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			</main>
		</div>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
