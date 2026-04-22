<?php
/**
 * Forms list admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- GET parameters on this admin display page are read-only filter params.

$forms = XF_Forms::get_all_forms();

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form saved successfully.', 'xtreme-forms' ) . '</p></div>';
} elseif ( ! empty( $_GET['deleted'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form deleted successfully.', 'xtreme-forms' ) . '</p></div>';
}
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Forms', 'xtreme-forms' ); ?></h1>
		<div class="xf-header-actions">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-form-metrics' ), admin_url( 'admin.php' ) ) ); ?>" class="xf-btn xf-btn-secondary">
				<?php esc_html_e( 'View Metrics', 'xtreme-forms' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-forms', 'xf_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="xf-btn xf-btn-primary">
				<?php esc_html_e( 'Add New Form', 'xtreme-forms' ); ?>
			</a>
		</div>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

	<?php if ( empty( $forms ) ) : ?>
		<div class="xf-empty-state">
			<span class="dashicons dashicons-feedback xf-empty-icon"></span>
			<h2><?php esc_html_e( 'No forms yet', 'xtreme-forms' ); ?></h2>
			<p><?php esc_html_e( 'Create your first lead capture form to get started.', 'xtreme-forms' ); ?></p>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'      => 'xtreme-forms-forms',
						'xf_action' => 'new',
					),
					admin_url( 'admin.php' )
				)
			);
			?>
						" class="button button-primary xf-btn-primary">
				<?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="xf-table widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Form Name', 'xtreme-forms' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'xtreme-forms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
					<th><?php esc_html_e( 'Created', 'xtreme-forms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $forms as $form ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $form->name ); ?></strong></td>
						<td>
							<code class="xf-shortcode" title="<?php esc_attr_e( 'Click to copy', 'xtreme-forms' ); ?>">[xtreme_forms id="<?php echo esc_attr( $form->id ); ?>"]</code>
						</td>
						<td>
							<span class="xf-status-badge xf-status-<?php echo esc_attr( $form->status ); ?>">
								<?php echo esc_html( ucfirst( $form->status ) ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $form->created_at . ' UTC' ) ) ); ?>
						</td>
						<td>
							<a href="
							<?php
							echo esc_url(
								add_query_arg(
									array(
										'page'      => 'xtreme-forms-forms',
										'xf_action' => 'edit',
										'form_id'   => $form->id,
									),
									admin_url( 'admin.php' )
								)
							);
							?>
										" class="button button-small">
								<?php esc_html_e( 'Edit', 'xtreme-forms' ); ?>
							</a>
							<a href="
							<?php
							echo esc_url(
								wp_nonce_url(
									add_query_arg(
										array(
											'action'  => 'xf_delete_form',
											'form_id' => $form->id,
										),
										admin_url( 'admin-post.php' )
									),
									'xf_delete_form_' . $form->id
								)
							);
							?>
										"
							class="button button-small xf-btn-danger"
							onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this form? This cannot be undone.', 'xtreme-forms' ) ); ?>')">
								<?php esc_html_e( 'Delete', 'xtreme-forms' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
