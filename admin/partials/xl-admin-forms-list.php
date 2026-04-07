<?php
/**
 * Forms list admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- GET parameters on this admin display page are read-only filter params.

$forms = XL_Forms::get_all_forms();

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form saved successfully.', 'xtremeleads' ) . '</p></div>';
} elseif ( ! empty( $_GET['deleted'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form deleted successfully.', 'xtremeleads' ) . '</p></div>';
}
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title">
		<?php esc_html_e( 'Forms', 'xtremeleads' ); ?>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms', 'xl_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action xl-btn-primary">
			<?php esc_html_e( 'Add New Form', 'xtremeleads' ); ?>
		</a>
	</h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<?php if ( empty( $forms ) ) : ?>
		<div class="xl-empty-state">
			<span class="dashicons dashicons-feedback xl-empty-icon"></span>
			<h2><?php esc_html_e( 'No forms yet', 'xtremeleads' ); ?></h2>
			<p><?php esc_html_e( 'Create your first lead capture form to get started.', 'xtremeleads' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms', 'xl_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary xl-btn-primary">
				<?php esc_html_e( 'Create Your First Form', 'xtremeleads' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="xl-table widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Form Name', 'xtremeleads' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'xtremeleads' ); ?></th>
					<th><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th>
					<th><?php esc_html_e( 'Created', 'xtremeleads' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'xtremeleads' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $forms as $form ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $form->name ); ?></strong></td>
						<td>
							<code class="xl-shortcode" title="<?php esc_attr_e( 'Click to copy', 'xtremeleads' ); ?>">[xtremeleads id="<?php echo esc_attr( $form->id ); ?>"]</code>
						</td>
						<td>
							<span class="xl-status-badge xl-status-<?php echo esc_attr( $form->status ); ?>">
								<?php echo esc_html( ucfirst( $form->status ) ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $form->created_at . ' UTC' ) ) ); ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms', 'xl_action' => 'edit', 'form_id' => $form->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'xtremeleads' ); ?>
							</a>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'xl_delete_form', 'form_id' => $form->id ), admin_url( 'admin-post.php' ) ), 'xl_delete_form_' . $form->id ) ); ?>"
							 class="button button-small xl-btn-danger"
							 onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this form? This cannot be undone.', 'xtremeleads' ) ); ?>')">
								<?php esc_html_e( 'Delete', 'xtremeleads' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
