<?php
/**
 * Frontend shortcode renderer.
 *
 * @package OFTUploadForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OFTUF_Shortcode {

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'oft_upload_form', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$this->enqueue_assets();

		$flash = $this->get_flash_data();
		$url   = oftuf_get_current_url();
		$atts  = shortcode_atts(
			array(
				'upload' => 'yes',
			),
			$atts,
			'oft_upload_form'
		);
		$show_upload = ! in_array( strtolower( (string) $atts['upload'] ), array( 'no', 'false', '0', 'off' ), true );
		$max_upload_size = oftuf_get_max_upload_size();

		$context = array(
			'action'             => esc_url( $url ),
			'redirect_to'        => esc_url( $url ),
			'max_upload_size'    => $max_upload_size,
			'max_upload_label'   => oftuf_format_file_size( $max_upload_size ),
			'allowed_extensions' => implode( ', ', oftuf_get_allowed_extensions() ),
			'accept_attribute'   => implode( ',', array_map( static function( $extension ) {
				return '.' . $extension;
			}, oftuf_get_allowed_extensions() ) ),
			'show_upload'        => $show_upload,
			'file_required'      => $show_upload && oftuf_is_file_required(),
			'notice_type'        => isset( $flash['type'] ) ? $flash['type'] : '',
			'messages'           => isset( $flash['messages'] ) ? (array) $flash['messages'] : array(),
			'old'                => isset( $flash['old'] ) ? (array) $flash['old'] : array(),
		);

		ob_start();
		include OFTUF_PLUGIN_PATH . 'templates/form.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	protected function enqueue_assets() {
		wp_enqueue_style(
			'oftuf-frontend',
			OFTUF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			OFTUF_VERSION
		);

		$inline_css = $this->get_inline_style_overrides();

		if ( '' !== $inline_css ) {
			wp_add_inline_style( 'oftuf-frontend', $inline_css );
		}

		wp_enqueue_script(
			'oftuf-frontend',
			OFTUF_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			OFTUF_VERSION,
			true
		);
	}

	/**
	 * Build inline style overrides from saved appearance settings.
	 *
	 * @return string
	 */
	protected function get_inline_style_overrides() {
		$declarations = array();
		$text_color = oftuf_get_text_color();
		$button_background_color = oftuf_get_button_background_color();
		$button_text_color = oftuf_get_button_text_color();
		$font_size = oftuf_get_font_size();

		if ( '' !== $font_size ) {
			$declarations[] = '--oftuf-font-size:' . $font_size;
		}

		if ( '' !== $text_color ) {
			$declarations[] = '--oftuf-text-color:' . $text_color;
		}

		if ( '' !== $button_background_color ) {
			$declarations[] = '--oftuf-button-background-color:' . $button_background_color;
			$declarations[] = '--oftuf-button-border-color:' . $button_background_color;
			$declarations[] = '--oftuf-button-hover-background-color:' . oftuf_adjust_hex_brightness( $button_background_color, -0.12 );
		}

		if ( '' !== $button_text_color ) {
			$declarations[] = '--oftuf-button-text-color:' . $button_text_color;
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return '.oftuf-form-wrapper{' . implode( ';', $declarations ) . ';}';
	}

	/**
	 * Read and clear flash data.
	 *
	 * @return array
	 */
	protected function get_flash_data() {
		if ( empty( $_GET['oftuf_notice'] ) ) {
			return array();
		}

		$token = sanitize_key( wp_unslash( $_GET['oftuf_notice'] ) );
		$key   = oftuf_get_flash_transient_key( $token );
		$data  = get_transient( $key );

		if ( false !== $data ) {
			delete_transient( $key );
		}

		return is_array( $data ) ? $data : array();
	}
}


