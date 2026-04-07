<?php
/**
 * Network Admin — Global Settings + Push to All Sites.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- Read-only display page; all data rendered server-side via WP functions.

$network_settings = get_site_option( XL_Multisite::NETWORK_SETTINGS_KEY, array() );

$email_template_body = $network_settings['email_template_body'] ?? '';
$email_header_color = $network_settings['email_header_color'] ?? '#1A73E8';
$retention_days = isset( $network_settings['retention_days'] ) && '' !== $network_settings['retention_days']
	? (int) $network_settings['retention_days'] : '';
$anonymize_ip = ! empty( $network_settings['anonymize_ip'] ) && '1' === (string) $network_settings['anonymize_ip'];
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title"><?php esc_html_e( 'XtremeLeads — Network Global Settings', 'xtremeleads' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Configure settings here and click "Push Settings to All Sites" to apply them to every subsite in the network. Each subsite retains its own independent lead data.', 'xtremeleads' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="xl_network_push_settings">
		<?php wp_nonce_field( 'xl_network_push_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="xl-email-header-color"><?php esc_html_e( 'Email Header Color', 'xtremeleads' ); ?></label>
				</th>
				<td>
					<input type="color" id="xl-email-header-color" name="xl_email_header_color"
						value="<?php echo esc_attr( $email_header_color ); ?>">
					<p class="description"><?php esc_html_e( 'Applied to notification email headers on all subsites.', 'xtremeleads' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xl-email-template-body"><?php esc_html_e( 'Email Template Body', 'xtremeleads' ); ?></label>
				</th>
				<td>
					<textarea id="xl-email-template-body" name="xl_email_template_body"
						rows="6" style="width:100%;max-width:600px;"><?php echo esc_textarea( $email_template_body ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Leave blank to keep existing per-site templates. Supported merge tags: {{lead_name}}, {{form_name}}, {{site_url}}', 'xtremeleads' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xl-retention-days"><?php esc_html_e( 'Data Retention (days)', 'xtremeleads' ); ?></label>
				</th>
				<td>
					<input type="number" id="xl-retention-days" name="xl_retention_days"
						value="<?php echo esc_attr( (string) $retention_days ); ?>" min="1" style="width:100px;">
					<p class="description"><?php esc_html_e( 'Auto-delete leads older than N days. Leave blank to disable.', 'xtremeleads' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'IP Anonymization', 'xtremeleads' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="xl_anonymize_ip" value="1" <?php checked( $anonymize_ip ); ?>>
						<?php esc_html_e( 'Anonymize IP addresses (mask last octet) on all subsites', 'xtremeleads' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" style="background:#FF6B35;border-color:#e05a25;">
				<?php esc_html_e( 'Push Settings to All Sites', 'xtremeleads' ); ?>
			</button>
		</p>

		<p class="description" style="color:#DC3545;">
			<?php esc_html_e( 'Warning: this will overwrite IP anonymization, data retention, and email template settings on ALL subsites in the network.', 'xtremeleads' ); ?>
		</p>
	</form>
</div>
