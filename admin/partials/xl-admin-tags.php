<?php
/**
 * Tag management admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$all_tags = XL_Tags::get_all_tags();

// Admin notices.
$notice_html = '';
if ( ! empty( $_GET['tag_created'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tag created successfully.', 'xtremeleads' ) . '</p></div>';
} elseif ( ! empty( $_GET['tag_deleted'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tag deleted successfully.', 'xtremeleads' ) . '</p></div>';
} elseif ( ! empty( $_GET['tag_error'] ) ) {
	$error_msg = get_transient( 'xl_tag_error_' . get_current_user_id() );
	if ( $error_msg ) {
		delete_transient( 'xl_tag_error_' . get_current_user_id() );
		$notice_html = '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
	}
}
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title"><?php esc_html_e( 'Tags', 'xtremeleads' ); ?></h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<div class="xl-tags-layout">

		<!-- Create New Tag -->
		<div class="xl-settings-card xl-tags-create-card">
			<h2><?php esc_html_e( 'Add New Tag', 'xtremeleads' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="xl_save_tag">
				<?php wp_nonce_field( 'xl_save_tag' ); ?>

				<div class="xl-field-group">
					<label class="xl-label" for="tag_name">
						<?php esc_html_e( 'Tag Name', 'xtremeleads' ); ?>
						<span class="xl-required">*</span>
					</label>
					<input
						type="text"
						id="tag_name"
						name="tag_name"
						class="xl-input xl-input-full"
						placeholder="<?php esc_attr_e( 'e.g. Hot Lead, Enterprise, Referral…', 'xtremeleads' ); ?>"
						required
					>
					<p class="xl-help-text"><?php esc_html_e( 'Tag names must be unique.', 'xtremeleads' ); ?></p>
				</div>

				<button type="submit" class="button button-primary xl-btn-primary">
					<?php esc_html_e( 'Add Tag', 'xtremeleads' ); ?>
				</button>
			</form>
		</div>

		<!-- Tags List -->
		<div class="xl-settings-card xl-tags-list-card">
			<h2><?php esc_html_e( 'All Tags', 'xtremeleads' ); ?></h2>

			<?php if ( empty( $all_tags ) ) : ?>
				<div class="xl-empty-state xl-empty-state-inline">
					<p><?php esc_html_e( 'No tags created yet. Add your first tag using the form.', 'xtremeleads' ); ?></p>
				</div>
			<?php else : ?>
				<table class="xl-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'xtremeleads' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'xtremeleads' ); ?></th>
							<th><?php esc_html_e( 'Created', 'xtremeleads' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'xtremeleads' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_tags as $tag ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $tag->name ); ?></strong></td>
								<td><code><?php echo esc_html( $tag->slug ); ?></code></td>
								<td>
									<?php
									echo esc_html(
										wp_date(
											get_option( 'date_format' ),
											strtotime( $tag->created_at . ' UTC' )
										)
									);
									?>
								</td>
								<td>
									<a
										href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'xl_delete_tag', 'tag_id' => $tag->id ), admin_url( 'admin-post.php' ) ), 'xl_delete_tag_' . $tag->id ) ); ?>"
										class="xl-btn-danger button"
										onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this tag? This will remove it from all leads.', 'xtremeleads' ) ); ?>')"
									>
										<?php esc_html_e( 'Delete', 'xtremeleads' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>
</div>
