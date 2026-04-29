<?php
/**
 * Integrations admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are local to this included partial, not global scope.

// Retrieve saved settings and pass to JS.
$xf_int_saved = XF_Integrations::get_settings();

/**
 * Integration definitions.
 *
 * Keys: slug, name, color, initials, desc, fields (each: name, label, type, options[])
 * Special key 'note' for informational text below description.
 * Special key 'webhooks_link' => true for the webhook redirect card.
 *
 * @var array[]
 */
$xf_integrations = array(
	array(
		'slug'     => 'hubspot',
		'name'     => 'HubSpot',
		'color'    => '#ff7a59',
		'initials' => 'HS',
		'desc'     => __( 'Push new leads directly to HubSpot as Contacts. Requires a HubSpot Private App access token.', 'xtreme-forms' ),
		'fields'   => array(
			array( 'name' => 'access_token',        'label' => __( 'Access Token', 'xtreme-forms' ),         'type' => 'password' ),
			array( 'name' => 'default_owner_email',  'label' => __( 'Default Owner Email', 'xtreme-forms' ),  'type' => 'text' ),
		),
	),
	array(
		'slug'     => 'zoho',
		'name'     => 'Zoho CRM',
		'color'    => '#e42527',
		'initials' => 'ZC',
		'desc'     => __( 'Create Leads or Contacts in Zoho CRM automatically. Requires Zoho OAuth credentials.', 'xtreme-forms' ),
		'fields'   => array(
			array( 'name' => 'client_id',     'label' => __( 'Client ID', 'xtreme-forms' ),     'type' => 'text' ),
			array( 'name' => 'client_secret', 'label' => __( 'Client Secret', 'xtreme-forms' ), 'type' => 'password' ),
			array( 'name' => 'refresh_token', 'label' => __( 'Refresh Token', 'xtreme-forms' ), 'type' => 'password' ),
			array(
				'name'    => 'region',
				'label'   => __( 'Region', 'xtreme-forms' ),
				'type'    => 'select',
				'options' => array(
					'US' => 'US',
					'EU' => 'EU',
					'IN' => 'IN',
					'AU' => 'AU',
					'JP' => 'JP',
				),
			),
		),
	),
	array(
		'slug'     => 'salesforce',
		'name'     => 'Salesforce',
		'color'    => '#00a1e0',
		'initials' => 'SF',
		'desc'     => __( 'Push leads to Salesforce as Leads or Contacts via the REST API.', 'xtreme-forms' ),
		'fields'   => array(
			array( 'name' => 'consumer_key',    'label' => __( 'Consumer Key', 'xtreme-forms' ),                               'type' => 'text' ),
			array( 'name' => 'consumer_secret', 'label' => __( 'Consumer Secret', 'xtreme-forms' ),                            'type' => 'password' ),
			array( 'name' => 'instance_url',    'label' => __( 'Instance URL', 'xtreme-forms' ),                               'type' => 'text', 'placeholder' => 'https://yourorg.salesforce.com' ),
			array( 'name' => 'access_token',    'label' => __( 'Access Token (stored after OAuth)', 'xtreme-forms' ),          'type' => 'password' ),
		),
	),
	array(
		'slug'     => 'pipedrive',
		'name'     => 'Pipedrive',
		'color'    => '#2c2c2c',
		'initials' => 'PD',
		'desc'     => __( 'Automatically create Persons and Deals in Pipedrive when leads are captured.', 'xtreme-forms' ),
		'fields'   => array(
			array( 'name' => 'api_token',      'label' => __( 'API Token', 'xtreme-forms' ),      'type' => 'password' ),
			array( 'name' => 'company_domain', 'label' => __( 'Company Domain', 'xtreme-forms' ), 'type' => 'text', 'placeholder' => 'yourcompany' ),
		),
	),
	array(
		'slug'     => 'groundhogg',
		'name'     => 'Groundhogg',
		'color'    => '#6441a5',
		'initials' => 'GH',
		'desc'     => __( 'Add contacts and apply tags in Groundhogg directly — works natively inside WordPress, no API key required.', 'xtreme-forms' ),
		'note'     => __( 'Groundhogg must be installed and active.', 'xtreme-forms' ),
		'fields'   => array(
			array( 'name' => 'tags', 'label' => __( 'Apply Tag(s) — comma-separated tag names', 'xtreme-forms' ), 'type' => 'text' ),
		),
	),
	array(
		'slug'          => 'webhook',
		'name'          => 'Custom Webhook',
		'color'         => '#0ABAB5',
		'initials'      => 'WH',
		'desc'          => __( 'Send lead data to any URL via HTTP POST. Configure multiple webhooks from the Webhooks page.', 'xtreme-forms' ),
		'webhooks_link' => true,
	),
);
?>
<div class="wrap xf-wrap">

	<!-- ── Page Header ────────────────────────────────────────────────────── -->
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Integrations', 'xtreme-forms' ); ?></h1>
		<div class="xf-header-actions">
			<span class="xf-badge xf-badge-teal"><?php esc_html_e( '6 Available', 'xtreme-forms' ); ?></span>
		</div>
	</div>

	<p class="xf-text-muted" style="margin-bottom:24px;">
		<?php esc_html_e( 'Connect Xtreme Forms to your CRM or marketing tools. When a new lead is captured, data is automatically pushed to your configured integrations.', 'xtreme-forms' ); ?>
	</p>

	<!-- ── Integration cards grid ─────────────────────────────────────────── -->
	<div class="xf-integrations-grid">

		<?php foreach ( $xf_integrations as $int ) :
			$slug       = $int['slug'];
			$is_webhook = ! empty( $int['webhooks_link'] );
			$cfg        = $xf_int_saved[ $slug ] ?? array();
			$is_enabled = ( ! $is_webhook ) && ! empty( $cfg['enabled'] ) && '1' === (string) $cfg['enabled'];
			?>
			<div class="xf-integration-card" data-integration="<?php echo esc_attr( $slug ); ?>">

				<!-- Card header -->
				<div class="xf-integration-card-header">
					<div class="xf-integration-logo" style="background:<?php echo esc_attr( $int['color'] ); ?>">
						<?php echo esc_html( $int['initials'] ); ?>
					</div>
					<div class="xf-integration-meta">
						<h3 class="xf-integration-name"><?php echo esc_html( $int['name'] ); ?></h3>
						<?php if ( $is_enabled ) : ?>
							<span class="xf-integration-status xf-badge xf-badge-default connected">
								<?php esc_html_e( 'Connected', 'xtreme-forms' ); ?>
							</span>
						<?php elseif ( ! $is_webhook ) : ?>
							<span class="xf-integration-status xf-badge xf-badge-default">
								<?php esc_html_e( 'Not Connected', 'xtreme-forms' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( $is_webhook ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-webhooks' ), admin_url( 'admin.php' ) ) ); ?>"
							class="xf-btn xf-btn-secondary xf-btn-sm">
							<?php esc_html_e( 'Manage Webhooks', 'xtreme-forms' ); ?>
						</a>
					<?php else : ?>
						<button type="button"
							class="xf-btn xf-btn-secondary xf-btn-sm xf-integration-toggle-btn"
							data-integration="<?php echo esc_attr( $slug ); ?>"
							aria-expanded="false">
							<?php esc_html_e( 'Configure', 'xtreme-forms' ); ?>
						</button>
					<?php endif; ?>
				</div><!-- .xf-integration-card-header -->

				<p class="xf-integration-desc"><?php echo esc_html( $int['desc'] ); ?></p>

				<?php if ( ! empty( $int['note'] ) ) : ?>
					<p class="xf-text-muted" style="font-size:12px;margin-top:4px;">
						<em><?php echo esc_html( $int['note'] ); ?></em>
					</p>
				<?php endif; ?>

				<!-- Expandable settings panel (hidden until Configure is clicked) -->
				<?php if ( ! $is_webhook ) : ?>
					<div class="xf-integration-panel" style="display:none;" id="xf-int-panel-<?php echo esc_attr( $slug ); ?>">
						<hr class="xf-divider">
						<form class="xf-integration-form" data-integration="<?php echo esc_attr( $slug ); ?>">

							<!-- Enable toggle -->
							<div class="xf-form-row xf-integration-enable-row">
								<label class="xf-toggle-wrap">
									<span class="xf-toggle">
										<input type="checkbox"
											name="enabled"
											class="xf-int-enabled"
											value="1"
											<?php checked( $is_enabled ); ?>>
										<span class="xf-toggle-track"></span>
										<span class="xf-toggle-thumb"></span>
									</span>
									<span class="xf-toggle-label xf-fw-600"><?php esc_html_e( 'Enable Integration', 'xtreme-forms' ); ?></span>
								</label>
							</div>

							<?php foreach ( $int['fields'] as $field ) :
								$field_name  = $field['name'];
								$field_label = $field['label'];
								$field_type  = $field['type'];
								$field_val   = $cfg[ $field_name ] ?? '';
								$field_id    = 'xf-int-' . esc_attr( $slug ) . '-' . esc_attr( $field_name );
								?>
								<div class="xf-form-row">
									<label for="<?php echo esc_attr( $field_id ); ?>" class="xf-label">
										<?php echo esc_html( $field_label ); ?>
									</label>
									<?php if ( 'select' === $field_type ) : ?>
										<select id="<?php echo esc_attr( $field_id ); ?>"
											name="<?php echo esc_attr( $field_name ); ?>"
											class="xf-input">
											<?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
												<option value="<?php echo esc_attr( $opt_val ); ?>"
													<?php selected( $field_val, $opt_val ); ?>>
													<?php echo esc_html( $opt_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input
											type="<?php echo esc_attr( $field_type ); ?>"
											id="<?php echo esc_attr( $field_id ); ?>"
											name="<?php echo esc_attr( $field_name ); ?>"
											class="xf-input"
											value="<?php echo esc_attr( $field_val ); ?>"
											<?php if ( ! empty( $field['placeholder'] ) ) : ?>
												placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
											<?php endif; ?>
											autocomplete="off">
									<?php endif; ?>
								</div>
							<?php endforeach; ?>

							<!-- Actions -->
							<div class="xf-flex-gap-8" style="margin-top:16px;">
								<button type="submit" class="xf-btn xf-btn-primary xf-btn-sm">
									<?php esc_html_e( 'Save Integration', 'xtreme-forms' ); ?>
								</button>
								<button type="button"
									class="xf-btn xf-btn-ghost xf-btn-sm xf-integration-test-btn"
									data-integration="<?php echo esc_attr( $slug ); ?>">
									<?php esc_html_e( 'Test Connection', 'xtreme-forms' ); ?>
								</button>
							</div>

							<p class="xf-integration-save-msg" style="display:none;margin-top:8px;"></p>

						</form>
					</div><!-- .xf-integration-panel -->
				<?php endif; ?>

			</div><!-- .xf-integration-card -->
		<?php endforeach; ?>

	</div><!-- .xf-integrations-grid -->

</div><!-- .wrap.xf-wrap -->

<?php
/*
 * Integrations page bootstrap data + i18n strings.
 *
 * The dedicated JS (admin/js/xf-integrations.js) is enqueued via the shared
 * admin enqueue function. Per-render data and translatable strings are
 * attached here so the WordPress.org Plugin Check sees no inline <script>
 * tags in the rendered HTML.
 */
$xf_int_nonce = wp_create_nonce( 'xf_integrations_nonce' );
wp_localize_script(
	'xf-integrations',
	'xfIntegrationsBootstrap',
	array(
		'saved'   => $xf_int_saved,
		'nonce'   => $xf_int_nonce,
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	)
);
wp_localize_script(
	'xf-integrations',
	'xfIntegrationsI18n',
	array(
		'configure'         => __( 'Configure', 'xtreme-forms' ),
		'close'             => __( 'Close', 'xtreme-forms' ),
		'saving'            => __( 'Saving…', 'xtreme-forms' ),
		'saved'             => __( 'Saved.', 'xtreme-forms' ),
		'saveFailed'        => __( 'Save failed.', 'xtreme-forms' ),
		'connected'         => __( 'Connected', 'xtreme-forms' ),
		'notConnected'      => __( 'Not Connected', 'xtreme-forms' ),
		'requestFailed'     => __( 'Request failed.', 'xtreme-forms' ),
		'testing'           => __( 'Testing…', 'xtreme-forms' ),
		'connectionSuccess' => __( 'Connection successful!', 'xtreme-forms' ),
		'testFailed'        => __( 'Test failed.', 'xtreme-forms' ),
	)
);
?>
