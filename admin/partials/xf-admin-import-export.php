<?php
/**
 * Import / Export admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Capability check enforced above; page callback is also registered with 'manage_options'. Notice flags are read from a transient that is keyed to the current user ID.

$notice_html   = '';
$transient_key = 'xtremeforms_import_result_' . get_current_user_id();
$import_result = get_transient( $transient_key );
if ( false !== $import_result ) {
	delete_transient( $transient_key );
	if ( is_wp_error( $import_result ) ) {
		$notice_html = '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'xtreme-forms' ) . '</strong> ' . esc_html( $import_result->get_error_message() ) . '</p></div>';
	} elseif ( is_array( $import_result ) ) {
		$msg = sprintf(
			/* translators: %d: number of forms imported */
			_n( 'Import successful. %d form imported.', 'Import successful. %d forms imported.', $import_result['imported_forms'], 'xtreme-forms' ),
			$import_result['imported_forms']
		);
		$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p>';
		if ( ! empty( $import_result['remapped_shortcuts'] ) ) {
			$notice_html .= '<p>' . esc_html__( 'Note: The following forms had ID conflicts and were assigned new IDs:', 'xtreme-forms' ) . '</p><ul>';
			foreach ( $import_result['remapped_shortcuts'] as $remap ) {
				$notice_html .= '<li>' . sprintf(
					/* translators: 1: form name, 2: original ID, 3: new ID */
					esc_html__( '"%1$s" (original ID %2$d → new ID %3$d)', 'xtreme-forms' ),
					esc_html( $remap['name'] ),
					(int) $remap['original_id'],
					(int) $remap['new_id']
				) . '</li>';
			}
			$notice_html .= '</ul>';
		}
		$notice_html .= '</div>';
	}
}
if ( ! empty( $_GET['xtremeforms_export_error'] ) ) {
	$notice_html .= '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Export failed. Please try again.', 'xtreme-forms' ) . '</p></div>';
}

// Build forms list for per-form export.
$all_forms = Xtremeforms_Forms::get_all_forms();
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Import / Export', 'xtreme-forms' ); ?></h1>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

	<!-- ── Export Section ── -->
	<div class="xf-settings-card" style="margin-bottom:24px;">
		<h2><?php esc_html_e( 'Export', 'xtreme-forms' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Export your plugin settings and form definitions as a JSON file. Lead data is never included.', 'xtreme-forms' ); ?>
		</p>

		<!-- Full export -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
			<input type="hidden" name="action" value="xtremeforms_export_data">
			<input type="hidden" name="export_type" value="full">
			<?php wp_nonce_field( 'xtremeforms_export_data' ); ?>
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Export All Settings & Forms', 'xtreme-forms' ); ?>
			</button>
			<span style="margin-left:8px;color:#6C757D;font-size:13px;">
				<?php esc_html_e( 'Downloads xtreme-forms-export-full-[date].json', 'xtreme-forms' ); ?>
			</span>
		</form>

		<!-- Per-form export -->
		<?php if ( ! empty( $all_forms ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="xtremeforms_export_data">
			<input type="hidden" name="export_type" value="form">
			<?php wp_nonce_field( 'xtremeforms_export_data' ); ?>
			<label for="xf-export-form-id" style="font-weight:600;"><?php esc_html_e( 'Export Single Form:', 'xtreme-forms' ); ?></label>
			<select id="xf-export-form-id" name="form_id" style="margin:0 8px;">
				<option value=""><?php esc_html_e( '— Select a form —', 'xtreme-forms' ); ?></option>
				<?php foreach ( $all_forms as $form ) : ?>
					<option value="<?php echo esc_attr( (string) $form->id ); ?>">
						<?php echo esc_html( $form->name ); ?> (ID: <?php echo esc_html( (string) $form->id ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button">
				<?php esc_html_e( 'Export Form', 'xtreme-forms' ); ?>
			</button>
		</form>
		<?php endif; ?>
	</div>

	<!-- ── Import Section ── -->
	<div class="xf-settings-card">
		<h2><?php esc_html_e( 'Import', 'xtreme-forms' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Import settings and/or forms from a previously exported JSON file. Existing settings will be merged; existing forms will NOT be overwritten (imported forms always receive new IDs).', 'xtreme-forms' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="xtremeforms_import_data">
			<?php wp_nonce_field( 'xtremeforms_import_data' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="xf-import-file"><?php esc_html_e( 'Import File (.json)', 'xtreme-forms' ); ?></label>
					</th>
					<td>
						<input type="file" id="xf-import-file" name="xtremeforms_import_file" accept=".json,application/json" required>
						<p class="description"><?php esc_html_e( 'Select a JSON file exported by Xtreme Forms. Maximum file size: 2 MB.', 'xtreme-forms' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Import', 'xtreme-forms' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>
