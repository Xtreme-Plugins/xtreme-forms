<?php
/**
 * Automations hub page — aggregates Email Templates, Routing Rules, Webhooks, and Integrations under tabs.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Capability check enforced above; page callback is also registered with 'manage_options'. GET tab param is read-only display routing (sanitized via sanitize_key).

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'email-templates';

$tabs = array(
	'email-templates' => __( 'Email Templates', 'xtreme-forms' ),
	'routing-rules'   => __( 'Routing Rules', 'xtreme-forms' ),
	'webhooks'        => __( 'Webhooks', 'xtreme-forms' ),
	'integrations'    => __( 'Integrations', 'xtreme-forms' ),
);

$partial_map = array(
	'email-templates' => 'xf-admin-email-templates.php',
	'routing-rules'   => 'xf-admin-routing-rules.php',
	'webhooks'        => 'xf-admin-webhooks.php',
	'integrations'    => 'xf-admin-integrations.php',
);

// Validate active tab falls within the allowed set; default to first tab.
if ( ! array_key_exists( $active_tab, $partial_map ) ) {
	$active_tab = 'email-templates';
}

$partial = $partial_map[ $active_tab ];
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Automations', 'xtreme-forms' ); ?></h1>
	</div>

	<div class="xf-hub-tabs">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-automations', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
			   class="xf-hub-tab<?php echo $active_tab === $slug ? ' active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="xf-hub-tab-content">
		<?php require XTREMEFORMS_PLUGIN_DIR . 'admin/partials/' . $partial; ?>
	</div>
</div>
