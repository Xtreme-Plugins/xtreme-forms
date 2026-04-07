<?php
/**
 * Network Admin — Aggregated Leads Dashboard.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- Read-only display page; all data rendered server-side via WP functions.

$data = XF_Multisite::get_aggregated_data();
$total_leads = $data['total_leads'];
$by_site = $data['by_site'];
$top_forms = $data['top_forms'];
?>
<div class="wrap xf-wrap">
	<h1 class="xf-page-title"><?php esc_html_e( 'Xtreme Forms — Network Dashboard', 'xtreme-forms' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Aggregated lead data across all active subsites in this network.', 'xtreme-forms' ); ?></p>

	<!-- Summary card -->
	<div style="display:flex;gap:16px;margin:24px 0;flex-wrap:wrap;">
		<div class="xf-stat-card" style="min-width:180px;">
			<div class="xf-stat-value"><?php echo esc_html( number_format_i18n( $total_leads ) ); ?></div>
			<div class="xf-stat-label"><?php esc_html_e( 'Total Leads (All Sites)', 'xtreme-forms' ); ?></div>
		</div>
		<div class="xf-stat-card" style="min-width:180px;">
			<div class="xf-stat-value"><?php echo esc_html( number_format_i18n( count( $by_site ) ) ); ?></div>
			<div class="xf-stat-label"><?php esc_html_e( 'Active Sites', 'xtreme-forms' ); ?></div>
		</div>
	</div>

	<!-- Per-site breakdown -->
	<h2><?php esc_html_e( 'Leads by Site', 'xtreme-forms' ); ?></h2>
	<table class="wp-list-table widefat fixed striped xf-table">
		<thead>
			<tr>
				<th style="width:60px;"><?php esc_html_e( 'Site ID', 'xtreme-forms' ); ?></th>
				<th><?php esc_html_e( 'Site Name', 'xtreme-forms' ); ?></th>
				<th><?php esc_html_e( 'URL', 'xtreme-forms' ); ?></th>
				<th style="width:120px;text-align:right;"><?php esc_html_e( 'Lead Count', 'xtreme-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $by_site ) ) : ?>
				<tr><td colspan="4" style="text-align:center;color:#6C757D;"><?php esc_html_e( 'No sites found.', 'xtreme-forms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $by_site as $blog_id => $site ) : ?>
					<tr>
						<td><code><?php echo esc_html( (string) $blog_id ); ?></code></td>
						<td><?php echo esc_html( $site['blog_name'] ); ?></td>
						<td><a href="<?php echo esc_url( $site['blog_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $site['blog_url'] ); ?></a></td>
						<td style="text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( $site['count'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<?php if ( ! empty( $by_site ) ) : ?>
		<tfoot>
			<tr>
				<th colspan="3" style="text-align:right;font-weight:700;"><?php esc_html_e( 'Network Total:', 'xtreme-forms' ); ?></th>
				<th style="text-align:right;font-weight:700;"><?php echo esc_html( number_format_i18n( $total_leads ) ); ?></th>
			</tr>
		</tfoot>
		<?php endif; ?>
	</table>

	<!-- Top forms -->
	<?php if ( ! empty( $top_forms ) ) : ?>
	<h2 style="margin-top:32px;"><?php esc_html_e( 'Top Performing Forms (Network)', 'xtreme-forms' ); ?></h2>
	<table class="wp-list-table widefat fixed striped xf-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Form Name', 'xtreme-forms' ); ?></th>
				<th><?php esc_html_e( 'Site', 'xtreme-forms' ); ?></th>
				<th style="width:120px;text-align:right;"><?php esc_html_e( 'Lead Count', 'xtreme-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $top_forms as $form_row ) : ?>
				<tr>
					<td><?php echo esc_html( $form_row['form_name'] ); ?></td>
					<td><?php echo esc_html( $form_row['blog_name'] ); ?></td>
					<td style="text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( $form_row['count'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<p style="margin-top:24px;">
		<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=xtremeleads-network-settings' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Manage Global Settings', 'xtreme-forms' ); ?>
		</a>
	</p>
</div>
