<?php
/**
 * Setup page template.
 *
 * @package OFTUploadForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap oftuf-admin-page">
	<h1><?php esc_html_e( 'Set Up Your Form', 'oft-upload-form' ); ?></h1>

	<?php if ( 'success' === $test_status ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Test email sent. Check your inbox for the message.', 'oft-upload-form' ); ?></p>
		</div>
	<?php elseif ( 'error' === $test_status ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'The test email could not be sent. Your site may need an SMTP email service.', 'oft-upload-form' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'saved' === $settings_status ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Upload settings saved.', 'oft-upload-form' ); ?></p>
		</div>
	<?php elseif ( 'missing_types' === $settings_status ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Select at least one file type.', 'oft-upload-form' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="card">
		<h2><?php esc_html_e( 'Choose Your Shortcode', 'oft-upload-form' ); ?></h2>
		<p><?php esc_html_e( 'Choose the shortcode that fits the kind of contact form you want to show.', 'oft-upload-form' ); ?></p>
		<p><strong><?php esc_html_e( 'Contact form with upload', 'oft-upload-form' ); ?></strong></p>
		<code>[oft_upload_form]</code>
		<p><?php esc_html_e( 'Use this when you want visitors to send a message and attach a file.', 'oft-upload-form' ); ?></p>
		<p><strong><?php esc_html_e( 'Contact form without upload', 'oft-upload-form' ); ?></strong></p>
		<code>[oft_upload_form upload="no"]</code>
		<p><?php esc_html_e( 'Use this when you only want a simple contact form with name, email, and message fields.', 'oft-upload-form' ); ?></p>
		<p>
			<?php
			printf(
				/* translators: 1: opening submissions link, 2: closing link tag. */
				wp_kses(
					__( 'New submissions are saved in %1$sOFT Upload Form > Submissions%2$s.', 'oft-upload-form' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				'<a href="' . esc_url( admin_url( 'admin.php?page=oftuf-submissions' ) ) . '">',
				'</a>'
			);
			?>
		</p>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Choose Upload Settings', 'oft-upload-form' ); ?></h2>
		<p><?php esc_html_e( 'Choose the file types your form accepts. Keep this list as small as possible.', 'oft-upload-form' ); ?></p>
		<p><?php esc_html_e( 'Risk note: Office documents and ZIP files can carry malware or unsafe content. PDFs and images are generally the safest options.', 'oft-upload-form' ); ?></p>
		<p><?php esc_html_e( 'Choose the largest file size you want to allow. Available options are automatically limited by your hosting setup.', 'oft-upload-form' ); ?></p>
		<?php if ( $host_limit_notice ) : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: host upload limit. */
						__( 'Your current hosting setup allows file uploads up to %s, so larger options are hidden here.', 'oft-upload-form' ),
						oftuf_format_file_size( $server_upload_limit )
					)
				);
				?>
			</p>
			<p><?php esc_html_e( 'If you want to allow larger files, ask your host to raise the site upload limit or update your PHP/server upload settings.', 'oft-upload-form' ); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oftuf-settings' ) ); ?>">
			<input type="hidden" name="page" value="oftuf-settings">
			<?php wp_nonce_field( 'oftuf_save_settings' ); ?>
			<input type="hidden" name="oftuf_save_settings" value="1">

			<fieldset>
				<?php foreach ( $file_type_labels as $extension => $label ) : ?>
					<p>
						<label>
							<input type="checkbox" name="oftuf_allowed_extensions[]" value="<?php echo esc_attr( $extension ); ?>" <?php checked( in_array( $extension, $allowed_extensions, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					</p>
				<?php endforeach; ?>
			</fieldset>

			<p>
				<label for="oftuf-max-upload-size"><strong><?php esc_html_e( 'Maximum file size', 'oft-upload-form' ); ?></strong></label><br>
				<select id="oftuf-max-upload-size" name="oftuf_max_upload_size">
					<?php foreach ( $upload_size_choices as $size_value => $size_label ) : ?>
						<option value="<?php echo esc_attr( $size_value ); ?>" <?php selected( $selected_upload_size, (int) $size_value ); ?>>
							<?php echo esc_html( $size_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php submit_button( __( 'Save Upload Settings', 'oft-upload-form' ) ); ?>
		</form>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Check Email Delivery', 'oft-upload-form' ); ?></h2>
		<p><?php esc_html_e( 'This plugin uses WordPress\'s built-in email system.', 'oft-upload-form' ); ?></p>
		<p><?php esc_html_e( 'On most hosting providers, emails will send automatically.', 'oft-upload-form' ); ?></p>
		<p><?php esc_html_e( 'If emails are not arriving, you may need to connect your site to an SMTP email service.', 'oft-upload-form' ); ?></p>
		<p><?php esc_html_e( 'This helps improve delivery reliability and reduces the chance of messages going to spam.', 'oft-upload-form' ); ?></p>
		<p><?php esc_html_e( 'Uploaded files are stored privately and can only be downloaded from wp-admin by administrators.', 'oft-upload-form' ); ?></p>
		<p>
			<?php
			printf(
				/* translators: 1: opening admin settings link, 2: closing link tag, 3: admin email address. */
				wp_kses(
					__( 'Test emails are sent to the email address set in %1$sGeneral Settings%2$s: %3$s', 'oft-upload-form' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">',
				'</a>',
				esc_html( oftuf_get_recipient_email() )
			);
			?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=oftuf-settings&oftuf_test_email_action=1' ), 'oftuf_send_test_email' ) ); ?>">
				<?php esc_html_e( 'Send Test Email', 'oft-upload-form' ); ?>
			</a>
		</p>
	</div>

	<?php
	if ( isset( $updater ) && $updater instanceof OFT_Plugin_Updater ) {
		$updater->render_channel_settings_card(
			array(
				'heading'   => __( 'Choose Your Update Track', 'oft-upload-form' ),
				'help_text' => __( 'Choose whether this site should follow stable releases or beta builds for OFT Upload Form.', 'oft-upload-form' ),
			)
		);
	}
	?>
</div>
