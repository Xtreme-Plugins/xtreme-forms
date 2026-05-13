<?php
/**
 * Tag management admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Capability check enforced above; page callback is also registered with 'manage_options'. GET params are read-only notice flags.

$all_tags = Xtremeforms_Tags::get_all_tags();

// Admin notices.
$notice_html = '';
if ( ! empty( $_GET['tag_created'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tag created successfully.', 'xtreme-forms' ) . '</p></div>';
} elseif ( ! empty( $_GET['tag_deleted'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tag deleted successfully.', 'xtreme-forms' ) . '</p></div>';
} elseif ( ! empty( $_GET['tag_error'] ) ) {
	$error_msg = get_transient( 'xtremeforms_tag_error_' . get_current_user_id() );
	if ( $error_msg ) {
		delete_transient( 'xtremeforms_tag_error_' . get_current_user_id() );
		$notice_html = '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
	}
}
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Tags', 'xtreme-forms' ); ?></h1>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

	<div class="xf-tags-layout">

		<!-- Create New Tag -->
		<div class="xf-settings-card xf-tags-create-card">
			<h2><?php esc_html_e( 'Add New Tag', 'xtreme-forms' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="xtremeforms_save_tag">
				<?php wp_nonce_field( 'xtremeforms_save_tag' ); ?>

				<div class="xf-field-group">
					<label class="xf-label" for="tag_name">
						<?php esc_html_e( 'Tag Name', 'xtreme-forms' ); ?>
						<span class="xf-required">*</span>
					</label>
					<input
						type="text"
						id="tag_name"
						name="tag_name"
						class="xf-input xf-input-full"
						placeholder="<?php esc_attr_e( 'e.g. Hot Lead, Enterprise, Referral…', 'xtreme-forms' ); ?>"
						required
					>
					<p class="xf-help-text"><?php esc_html_e( 'Tag names must be unique.', 'xtreme-forms' ); ?></p>
				</div>

				<button type="submit" class="button button-primary xf-btn-primary">
					<?php esc_html_e( 'Add Tag', 'xtreme-forms' ); ?>
				</button>
			</form>
		</div>

		<!-- Tags List -->
		<div class="xf-settings-card xf-tags-list-card">
			<h2><?php esc_html_e( 'All Tags', 'xtreme-forms' ); ?></h2>

			<?php if ( empty( $all_tags ) ) : ?>
				<div class="xf-empty-state xf-empty-state-inline">
					<p><?php esc_html_e( 'No tags created yet. Add your first tag using the form.', 'xtreme-forms' ); ?></p>
				</div>
			<?php else : ?>
				<table class="xf-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'xtreme-forms' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'xtreme-forms' ); ?></th>
							<th><?php esc_html_e( 'Created', 'xtreme-forms' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
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
										href="
										<?php
										echo esc_url(
											wp_nonce_url(
												add_query_arg(
													array(
														'action' => 'xtremeforms_delete_tag',
														'tag_id' => $tag->id,
													),
													admin_url( 'admin-post.php' )
												),
												'xtremeforms_delete_tag_' . $tag->id
											)
										);
										?>
												"
										class="xf-btn-danger button"
										onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this tag? This will remove it from all leads.', 'xtreme-forms' ) ); ?>')"
									>
										<?php esc_html_e( 'Delete', 'xtreme-forms' ); ?>
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
