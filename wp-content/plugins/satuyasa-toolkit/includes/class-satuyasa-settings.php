<?php
/**
 * Halaman pengaturan: tautan sosial media, email kontak, teks footer.
 *
 * @package Satuyasa_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Satuyasa_Settings.
 */
class Satuyasa_Settings {

	const OPTION_KEY = 'satuyasa_toolkit_options';

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Tambahkan halaman pengaturan di bawah menu Pengaturan.
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'Satuyasa Toolkit', 'satuyasa-toolkit' ),
			__( 'Satuyasa Toolkit', 'satuyasa-toolkit' ),
			'manage_options',
			'satuyasa-toolkit',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Daftarkan setting, section, dan field.
	 */
	public static function register_settings() {
		register_setting( 'satuyasa_toolkit_group', self::OPTION_KEY, array(
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => array(),
		) );

		add_settings_section(
			'satuyasa_toolkit_main',
			__( 'Kontak & Sosial Media', 'satuyasa-toolkit' ),
			'__return_false',
			'satuyasa-toolkit'
		);

		$fields = array(
			'contact_email'    => __( 'Email Tujuan Kontak', 'satuyasa-toolkit' ),
			'facebook_url'     => __( 'URL Facebook', 'satuyasa-toolkit' ),
			'instagram_url'    => __( 'URL Instagram', 'satuyasa-toolkit' ),
			'whatsapp_number'  => __( 'Nomor WhatsApp (misal 6281234567890)', 'satuyasa-toolkit' ),
			'footer_text'      => __( 'Teks Tambahan Footer', 'satuyasa-toolkit' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( __CLASS__, 'render_field' ),
				'satuyasa-toolkit',
				'satuyasa_toolkit_main',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Cetak satu field teks.
	 *
	 * @param array $args Argumen field (berisi 'key').
	 */
	public static function render_field( $args ) {
		$options = self::get_options();
		$key     = $args['key'];
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	/**
	 * Bersihkan input sebelum disimpan.
	 *
	 * @param array $input Data mentah dari form.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$output = array();

		$output['contact_email']   = isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '';
		$output['facebook_url']    = isset( $input['facebook_url'] ) ? esc_url_raw( $input['facebook_url'] ) : '';
		$output['instagram_url']   = isset( $input['instagram_url'] ) ? esc_url_raw( $input['instagram_url'] ) : '';
		$output['whatsapp_number'] = isset( $input['whatsapp_number'] ) ? sanitize_text_field( $input['whatsapp_number'] ) : '';
		$output['footer_text']     = isset( $input['footer_text'] ) ? sanitize_text_field( $input['footer_text'] ) : '';

		return $output;
	}

	/**
	 * Cetak halaman pengaturan.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Satuyasa Toolkit', 'satuyasa-toolkit' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'satuyasa_toolkit_group' );
				do_settings_sections( 'satuyasa-toolkit' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Ambil seluruh opsi tersimpan.
	 *
	 * @return array
	 */
	public static function get_options() {
		$defaults = array(
			'contact_email'   => get_option( 'admin_email' ),
			'facebook_url'    => '',
			'instagram_url'   => '',
			'whatsapp_number' => '',
			'footer_text'     => '',
		);

		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}
}

/**
 * Fungsi bantuan global untuk mengambil satu opsi Satuyasa Toolkit.
 *
 * @param string $key     Nama opsi.
 * @param string $default Nilai default jika opsi kosong.
 * @return string
 */
function satuyasa_toolkit_get_option( $key, $default = '' ) {
	$options = Satuyasa_Settings::get_options();
	return isset( $options[ $key ] ) && '' !== $options[ $key ] ? $options[ $key ] : $default;
}
