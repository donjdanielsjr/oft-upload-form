<?php
/**
 * Admin page template.
 *
 * @package LightweightUploadForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap luf-admin-page">
	<h1><?php esc_html_e( 'Submissions', 'lightweight-upload-form' ); ?></h1>

	<?php if ( 'delete' === $bulk_status && $deleted > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of deleted submissions. */
						_n( '%d submission deleted.', '%d submissions deleted.', $deleted, 'lightweight-upload-form' ),
						$deleted
					)
				);
				?>
			</p>
		</div>
	<?php elseif ( 'none_selected' === $bulk_status ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Select at least one submission to delete.', 'lightweight-upload-form' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="luf-admin-actions">
		<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=luf-submissions&luf_export=1' ), 'luf_export_csv' ) ); ?>">
			<?php esc_html_e( 'Export CSV', 'lightweight-upload-form' ); ?>
		</a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=luf-submissions' ) ); ?>">
		<input type="hidden" name="page" value="luf-submissions">
		<?php wp_nonce_field( 'luf_bulk_submissions_action' ); ?>

		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e( 'Select bulk action', 'lightweight-upload-form' ); ?></label>
				<select name="action" id="bulk-action-selector-top">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'lightweight-upload-form' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'lightweight-upload-form' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'lightweight-upload-form' ), 'action', 'doaction', false ); ?>
			</div>
		</div>

		<table class="widefat fixed striped">
			<thead>
				<tr>
					<td class="check-column">
						<input id="cb-select-all-1" type="checkbox" class="luf-select-all">
					</td>
					<th><?php esc_html_e( 'Name', 'lightweight-upload-form' ); ?></th>
					<th><?php esc_html_e( 'Email', 'lightweight-upload-form' ); ?></th>
					<th><?php esc_html_e( 'Message', 'lightweight-upload-form' ); ?></th>
					<th><?php esc_html_e( 'File', 'lightweight-upload-form' ); ?></th>
					<th><?php esc_html_e( 'Date', 'lightweight-upload-form' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $submissions ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No submissions found.', 'lightweight-upload-form' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $submissions as $submission ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="submission_ids[]" value="<?php echo esc_attr( $submission['id'] ); ?>" class="luf-submission-checkbox">
							</th>
							<td><?php echo esc_html( $submission['name'] ); ?></td>
							<td>
								<a href="mailto:<?php echo esc_attr( $submission['email'] ); ?>">
									<?php echo esc_html( $submission['email'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( wp_trim_words( $submission['message'], 20, '...' ) ); ?></td>
							<td>
								<?php if ( ! empty( $submission['file_url'] ) ) : ?>
									<a href="<?php echo esc_url( $submission['file_url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $submission['file_url'] ); ?>
									</a>
									<?php if ( ! empty( $submission['attachment_id'] ) ) : ?>
										<div class="luf-attachment-id">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: attachment ID. */
													__( 'Attachment ID: %d', 'lightweight-upload-form' ),
													(int) $submission['attachment_id']
												)
											);
											?>
										</div>
									<?php endif; ?>
								<?php else : ?>
									<?php esc_html_e( 'No file', 'lightweight-upload-form' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submission['created_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft actions bulkactions">
				<label class="screen-reader-text" for="bulk-action-selector-bottom"><?php esc_html_e( 'Select bulk action', 'lightweight-upload-form' ); ?></label>
				<select name="action2" id="bulk-action-selector-bottom">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'lightweight-upload-form' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'lightweight-upload-form' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'lightweight-upload-form' ), 'action', 'doaction2', false ); ?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $total_pages,
								'prev_text' => __( '&laquo;', 'lightweight-upload-form' ),
								'next_text' => __( '&raquo;', 'lightweight-upload-form' ),
							)
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</form>
</div>
