<?php
/**
 * Network Admin — Global Settings + Push to All Sites.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Read-only display page; all data rendered server-side via WP functions.

$network_settings = get_site_option( XF_Multisite::NETWORK_SETTINGS_KEY, array() );

$email_template_body = $network_settings['email_template_body'] ?? '';
$email_header_color  = $network_settings['email_header_color'] ?? '#1A73E8';
$retention_days      = isset( $network_settings['retention_days'] ) && '' !== $network_settings['retention_days']
	? (int) $network_settings['retention_days'] : '';
$anonymize_ip        = ! empty( $network_settings['anonymize_ip'] ) && '1' === (string) $network_settings['anonymize_ip'];
?>
<div class="wrap xf-wrap">
	<h1 class="xf-page-title"><?php esc_html_e( 'Xtreme Forms — Network Global Settings', 'xtreme-forms' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Configure settings here and click "Push Settings to All Sites" to apply them to every subsite in the network. Each subsite retains its own independent lead data.', 'xtreme-forms' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="xf_network_push_settings">
		<?php wp_nonce_field( 'xf_network_push_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="xf-email-header-color"><?php esc_html_e( 'Email Header Color', 'xtreme-forms' ); ?></label>
				</th>
				<td>
					<input type="color" id="xf-email-header-color" name="xf_email_header_color"
						value="<?php echo esc_attr( $email_header_color ); ?>">
					<p class="description"><?php esc_html_e( 'Applied to notification email headers on all subsites.', 'xtreme-forms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xf-email-template-body"><?php esc_html_e( 'Email Template Body', 'xtreme-forms' ); ?></label>
				</th>
				<td>
					<textarea id="xf-email-template-body" name="xf_email_template_body"
						rows="6" style="width:100%;max-width:600px;"><?php echo esc_textarea( $email_template_body ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Leave blank to keep existing per-site templates. Supported merge tags: {{lead_name}}, {{form_name}}, {{site_url}}', 'xtreme-forms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xf-retention-days"><?php esc_html_e( 'Data Retention (days)', 'xtreme-forms' ); ?></label>
				</th>
				<td>
					<input type="number" id="xf-retention-days" name="xf_retention_days"
						value="<?php echo esc_attr( (string) $retention_days ); ?>" min="1" style="width:100px;">
					<p class="description"><?php esc_html_e( 'Auto-delete leads older than N days. Leave blank to disable.', 'xtreme-forms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'IP Anonymization', 'xtreme-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="xf_anonymize_ip" value="1" <?php checked( $anonymize_ip ); ?>>
						<?php esc_html_e( 'Anonymize IP addresses (mask last octet) on all subsites', 'xtreme-forms' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" style="background:#FF6B35;border-color:#e05a25;">
				<?php esc_html_e( 'Push Settings to All Sites', 'xtreme-forms' ); ?>
			</button>
		</p>

		<p class="description" style="color:#DC3545;">
			<?php esc_html_e( 'Warning: this will overwrite IP anonymization, data retention, and email template settings on ALL subsites in the network.', 'xtreme-forms' ); ?>
		</p>
	</form>
</div>
