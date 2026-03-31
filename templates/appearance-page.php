<?php
/**
 * Appearance page template.
 *
 * @package OFTUploadForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap oftuf-admin-page">
	<h1><?php esc_html_e( 'Customize Your Form', 'oft-upload-form' ); ?></h1>

	<?php if ( 'saved' === $appearance_status ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Appearance settings saved.', 'oft-upload-form' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="card">
		<h2><?php esc_html_e( 'Choose Your Form Style', 'oft-upload-form' ); ?></h2>
		<p><?php esc_html_e( 'Choose optional frontend styles for form text and plugin buttons. Leave any field on its default option to keep the plugin defaults.', 'oft-upload-form' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oftuf-appearance' ) ); ?>">
			<?php wp_nonce_field( 'oftuf_save_appearance_settings' ); ?>
			<input type="hidden" name="page" value="oftuf-appearance">
			<input type="hidden" name="oftuf_save_appearance_settings" value="1">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="oftuf-font-size"><?php esc_html_e( 'Font size', 'oft-upload-form' ); ?></label>
					</th>
					<td>
						<select id="oftuf-font-size" name="oftuf_font_size">
							<?php foreach ( $font_size_choices as $size_value => $size_label ) : ?>
								<option value="<?php echo esc_attr( $size_value ); ?>" <?php selected( $font_size, $size_value ); ?>>
									<?php echo esc_html( $size_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oftuf-text-color"><?php esc_html_e( 'Label color', 'oft-upload-form' ); ?></label>
					</th>
					<td>
						<input id="oftuf-text-color" class="oftuf-color-picker" name="oftuf_text_color" type="text" value="<?php echo esc_attr( $text_color ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oftuf-button-background-color"><?php esc_html_e( 'Button background color', 'oft-upload-form' ); ?></label>
					</th>
					<td>
						<input id="oftuf-button-background-color" class="oftuf-color-picker" name="oftuf_button_background_color" type="text" value="<?php echo esc_attr( $button_background_color ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oftuf-button-text-color"><?php esc_html_e( 'Button text color', 'oft-upload-form' ); ?></label>
					</th>
					<td>
						<input id="oftuf-button-text-color" class="oftuf-color-picker" name="oftuf_button_text_color" type="text" value="<?php echo esc_attr( $button_text_color ); ?>">
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Appearance Settings', 'oft-upload-form' ) ); ?>
		</form>
	</div>
</div>
