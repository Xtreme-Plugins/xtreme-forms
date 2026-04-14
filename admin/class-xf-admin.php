<?php
/**
 * Admin class – menus, pages, and asset enqueueing.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Admin
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- XF_ is the registered plugin prefix.
class XF_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_welcome' ) );
		add_action( 'admin_post_xl_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_xl_save_form', array( $this, 'handle_save_form' ) );
		add_action( 'admin_post_xl_delete_form', array( $this, 'handle_delete_form' ) );
		add_action( 'admin_post_xl_bulk_leads', array( $this, 'handle_bulk_leads_post' ) );
		add_action( 'admin_post_xl_save_tag', array( $this, 'handle_save_tag' ) );
		add_action( 'admin_post_xl_delete_tag', array( $this, 'handle_delete_tag' ) );
		add_action( 'admin_post_xl_export_leads', array( $this, 'handle_export_leads' ) );
		add_action( 'admin_post_xl_save_email_template', array( $this, 'handle_save_email_template' ) );
		add_action( 'admin_post_xl_save_routing_rules', array( $this, 'handle_save_routing_rules' ) );
		add_action( 'admin_post_xl_save_settings_s4', array( $this, 'handle_save_settings_s4' ) );
		add_action( 'admin_post_xl_save_settings_s5', array( $this, 'handle_save_settings_s5' ) );
		add_action( 'admin_post_xl_export_data', array( $this, 'handle_export_data' ) );
		add_action( 'admin_post_xl_import_data', array( $this, 'handle_import_data' ) );
		// xf_ prefix aliases for admin-post actions (form action names use xf_ prefix).
		add_action( 'admin_post_xf_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_xf_save_settings_s4', array( $this, 'handle_save_settings_s4' ) );
		add_action( 'admin_post_xf_save_settings_s5', array( $this, 'handle_save_settings_s5' ) );
	}

	// ── Menus ────────────────────────────────────────────────────────────────

	public function register_menus(): void {
		// In a network-activated multisite environment, respect per-site opt-out.
		if ( is_multisite() && class_exists( 'XF_Multisite' ) && XF_Multisite::is_site_disabled() ) {
			return;
		}

		add_menu_page(
			__( 'Xtreme Forms', 'xtreme-forms' ),
			__( 'Xtreme Forms', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms',
			array( $this, 'page_dashboard' ),
			'dashicons-email-alt',
			25
		);

		add_submenu_page(
			'xtreme-forms',
			__( 'Dashboard', 'xtreme-forms' ),
			__( 'Dashboard', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms',
			array( $this, 'page_dashboard' )
		);

		add_submenu_page(
			'xtreme-forms',
			__( 'Leads Inbox', 'xtreme-forms' ),
			__( 'Leads', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-leads',
			array( $this, 'page_leads_inbox' )
		);

		add_submenu_page(
			'xtreme-forms',
			__( 'Forms', 'xtreme-forms' ),
			__( 'Forms', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-forms',
			array( $this, 'page_forms' )
		);

		add_submenu_page(
			'xtreme-forms',
			__( 'Automations', 'xtreme-forms' ),
			__( 'Automations', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-automations',
			array( $this, 'page_automations' )
		);

		add_submenu_page(
			'xtreme-forms',
			__( 'Settings', 'xtreme-forms' ),
			__( 'Settings', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-settings',
			array( $this, 'page_settings' )
		);

		// Hidden pages — accessible by URL but not shown in the nav.
		add_submenu_page(
			null,
			__( 'Welcome to Xtreme Forms', 'xtreme-forms' ),
			__( 'Welcome', 'xtreme-forms' ),
			'manage_options',
			'xf-welcome',
			array( $this, 'page_welcome' )
		);

		add_submenu_page(
			null,
			__( 'Form Metrics', 'xtreme-forms' ),
			__( 'Form Metrics', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-form-metrics',
			array( $this, 'page_form_metrics' )
		);

		add_submenu_page(
			null,
			__( 'Tags', 'xtreme-forms' ),
			__( 'Tags', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-tags',
			array( $this, 'page_tags' )
		);

		add_submenu_page(
			null,
			__( 'Email Templates', 'xtreme-forms' ),
			__( 'Email Templates', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-email-templates',
			array( $this, 'page_email_templates' )
		);

		add_submenu_page(
			null,
			__( 'Routing Rules', 'xtreme-forms' ),
			__( 'Routing Rules', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-routing-rules',
			array( $this, 'page_routing_rules' )
		);

		add_submenu_page(
			null,
			__( 'Email Log', 'xtreme-forms' ),
			__( 'Email Log', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-email-log',
			array( $this, 'page_email_log' )
		);

		add_submenu_page(
			null,
			__( 'Webhooks', 'xtreme-forms' ),
			__( 'Webhooks', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-webhooks',
			array( $this, 'page_webhooks' )
		);

		add_submenu_page(
			null,
			__( 'Integrations', 'xtreme-forms' ),
			__( 'Integrations', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-integrations',
			array( $this, 'page_integrations' )
		);

		add_submenu_page(
			null,
			__( 'Spam Log', 'xtreme-forms' ),
			__( 'Spam Log', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-spam-log',
			array( $this, 'page_spam_log' )
		);

		add_submenu_page(
			null,
			__( 'Import / Export', 'xtreme-forms' ),
			__( 'Import / Export', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-import-export',
			array( $this, 'page_import_export' )
		);

		add_submenu_page(
			null,
			__( 'Audit Log', 'xtreme-forms' ),
			__( 'Audit Log', 'xtreme-forms' ),
			'manage_options',
			'xtreme-forms-audit-log',
			array( $this, 'page_audit_log' )
		);
	}

	// ── Asset enqueueing ─────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'xtreme-forms' ) ) {
			return;
		}

		wp_enqueue_style(
			'xf-admin',
			XTREMEFORMS_PLUGIN_URL . 'admin/css/xf-admin.css',
			array(),
			XTREMEFORMS_VERSION
		);

		wp_enqueue_script(
			'xf-admin',
			XTREMEFORMS_PLUGIN_URL . 'admin/js/xf-admin.js',
			array(),
			XTREMEFORMS_VERSION,
			true
		);

		// Builder-specific assets (only on the form builder page: new or edit actions).
		if ( false !== strpos( $hook, 'xtreme-forms-forms' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification -- Read-only URL param for page routing, no state change.
			$xf_action = isset( $_GET['xf_action'] ) ? sanitize_text_field( wp_unslash( $_GET['xf_action'] ) ) : '';
			if ( in_array( $xf_action, array( 'new', 'edit' ), true ) ) {
				wp_enqueue_style(
					'xf-builder',
					XTREMEFORMS_PLUGIN_URL . 'admin/css/xf-builder.css',
					array( 'xf-admin' ),
					XTREMEFORMS_VERSION
				);
				wp_enqueue_script(
					'xf-builder',
					XTREMEFORMS_PLUGIN_URL . 'admin/js/xf-builder.js',
					array( 'xf-admin' ),
					XTREMEFORMS_VERSION,
					true
				);
			}
		}

		// Enqueue Chart.js and dashboard script on dashboard/metrics pages.
		if ( false !== strpos( $hook, 'xtreme-forms-form-metrics' )
			|| false !== strpos( $hook, 'toplevel_page_xtreme-forms' )
		) {
			wp_enqueue_script(
				'xf-chartjs',
				XTREMEFORMS_PLUGIN_URL . 'admin/js/vendor/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);
			wp_enqueue_script(
				'xf-dashboard',
				XTREMEFORMS_PLUGIN_URL . 'admin/js/xf-dashboard.js',
				array( 'xf-chartjs' ),
				XTREMEFORMS_VERSION,
				true
			);
			wp_localize_script(
				'xf-dashboard',
				'xlDashboardData',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					// Legacy fallback nonce (kept for backward compatibility with non-analytics calls).
					'nonce'         => wp_create_nonce( 'xf_admin_nonce' ),
					// Per-endpoint nonces — each analytics endpoint verifies its own specific action.
					'nonces'        => array(
						'xf_dashboard_stats'       => wp_create_nonce( 'xf_dashboard_stats_nonce' ),
						'xf_chart_leads_by_form'   => wp_create_nonce( 'xf_chart_leads_by_form_nonce' ),
						'xf_chart_leads_over_time' => wp_create_nonce( 'xf_chart_leads_over_time_nonce' ),
						'xf_utm_report'            => wp_create_nonce( 'xf_utm_report_nonce' ),
						'xf_form_metrics'          => wp_create_nonce( 'xf_form_metrics_nonce' ),
					),
					'isFormMetrics' => false !== strpos( $hook, 'form-metrics' ),
					'i18n'          => array(
						'loading'           => __( 'Loading…', 'xtreme-forms' ),
						'error'             => __( 'Failed to load data. Please try again.', 'xtreme-forms' ),
						'noData'            => __( 'No data for this period', 'xtreme-forms' ),
						'noForms'           => __( 'No active forms yet.', 'xtreme-forms' ),
						'noSubmissions'     => __( 'No submissions yet', 'xtreme-forms' ),
						'leadsOverTime'     => __( 'Leads Over Time', 'xtreme-forms' ),
						'leadsByForm'       => __( 'Leads by Form', 'xtreme-forms' ),
						'conversionWarning' => __( 'Submission count exceeds recorded impressions — impression data may be incomplete', 'xtreme-forms' ),
						'viewAll'           => __( 'View all', 'xtreme-forms' ),
						'invalidDateRange'  => __( 'End date cannot be before start date.', 'xtreme-forms' ),
					),
				)
			);
		}

		$statuses = XF_Leads::get_statuses();
		$all_tags = XF_Tags::get_all_tags();

		wp_localize_script(
			'xf-admin',
			'xfAdminData',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'xf_admin_nonce' ),
				'webhookNonce'       => wp_create_nonce( 'xf_webhook_nonce' ),
				'gdprNonce'          => wp_create_nonce( 'xf_gdpr_nonce' ),
				'spamLogNonce'       => wp_create_nonce( 'xf_spam_log_nonce' ),
				'integrationsNonce'  => wp_create_nonce( 'xf_integrations_nonce' ),
				'pluginUrl'    => XTREMEFORMS_PLUGIN_URL,
				'statuses'     => $statuses,
				'allTags'      => array_map(
					static function ( $t ) {
						return array(
							'id'   => (int) $t->id,
							'name' => $t->name,
						);
					},
					$all_tags
				),
				'i18n'         => array(
					'confirmDelete'    => __( 'Are you sure you want to delete the selected leads? This cannot be undone.', 'xtreme-forms' ),
					'noItemsSelected'  => __( 'No items selected.', 'xtreme-forms' ),
					'loading'          => __( 'Loading…', 'xtreme-forms' ),
					'error'            => __( 'An error occurred. Please try again.', 'xtreme-forms' ),
					'close'            => __( 'Close', 'xtreme-forms' ),
					'save'             => __( 'Save', 'xtreme-forms' ),
					'saving'           => __( 'Saving…', 'xtreme-forms' ),
					'unassigned'       => __( 'Unassigned', 'xtreme-forms' ),
					'addNote'          => __( 'Add Note', 'xtreme-forms' ),
					'noteContentEmpty' => __( 'Note content cannot be empty.', 'xtreme-forms' ),
					'noteAdded'        => __( 'Note added.', 'xtreme-forms' ),
					'statusUpdated'    => __( 'Status updated.', 'xtreme-forms' ),
					'assignmentSaved'  => __( 'Assignment saved.', 'xtreme-forms' ),
					'tagAdded'         => __( 'Tag added.', 'xtreme-forms' ),
					'tagRemoved'       => __( 'Tag removed.', 'xtreme-forms' ),
					'addTag'           => __( 'Add Tag', 'xtreme-forms' ),
					'removeTag'        => __( 'Remove tag', 'xtreme-forms' ),
					'noTagsFound'      => __( 'No tags found.', 'xtreme-forms' ),
					'typeToSearch'     => __( 'Type to search tags…', 'xtreme-forms' ),
					'notesTitle'       => __( 'Notes', 'xtreme-forms' ),
					'noNotes'          => __( 'No notes yet. Add the first note below.', 'xtreme-forms' ),
					'activityTitle'    => __( 'Activity', 'xtreme-forms' ),
					'noActivity'       => __( 'No activity recorded yet.', 'xtreme-forms' ),
					'tabDetails'       => __( 'Details', 'xtreme-forms' ),
					'tabNotes'         => __( 'Notes', 'xtreme-forms' ),
					'tabActivity'      => __( 'Activity', 'xtreme-forms' ),
					'flyoutLeadId'     => __( 'Lead ID', 'xtreme-forms' ),
					'flyoutForm'       => __( 'Form', 'xtreme-forms' ),
					'flyoutStatus'     => __( 'Status', 'xtreme-forms' ),
					'flyoutDate'       => __( 'Date', 'xtreme-forms' ),
					'flyoutIpAddress'  => __( 'IP Address', 'xtreme-forms' ),
					'flyoutSourceUrl'  => __( 'Source URL', 'xtreme-forms' ),
					'flyoutUserAgent'  => __( 'User Agent', 'xtreme-forms' ),
					'flyoutSubmitted'  => __( 'Submitted Data', 'xtreme-forms' ),
					'flyoutAssignedTo' => __( 'Assigned To', 'xtreme-forms' ),
					'flyoutTags'       => __( 'Tags', 'xtreme-forms' ),
					'flyoutNa'         => __( 'N/A', 'xtreme-forms' ),
					'sendingTest'      => __( 'Sending…', 'xtreme-forms' ),
					'testEmailSent'    => __( 'Test email sent.', 'xtreme-forms' ),
					'testEmailFailed'  => __( 'Test email failed.', 'xtreme-forms' ),
					'resendEmail'      => __( 'Resend', 'xtreme-forms' ),
					'resending'        => __( 'Sending…', 'xtreme-forms' ),
					'resendSuccess'    => __( 'Sent ✓', 'xtreme-forms' ),
					'resendFailed'     => __( 'Resend failed.', 'xtreme-forms' ),
				),
			)
		);
	}

	// ── Activation redirect ───────────────────────────────────────────────────

	/**
	 * Redirect to the welcome screen once after plugin activation.
	 *
	 * Runs on admin_init. Skips during AJAX requests, network activation,
	 * and when the current user cannot manage options.
	 */
	public function maybe_redirect_to_welcome(): void {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_transient( 'xf_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'xf_activation_redirect' );

		wp_safe_redirect( admin_url( 'admin.php?page=xf-welcome' ) );
		exit;
	}

	// ── Page callbacks ────────────────────────────────────────────────────────

	public function page_welcome(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-welcome.php';
	}

	public function page_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-dashboard.php';
	}

	public function page_form_metrics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-form-metrics.php';
	}

	public function page_leads_inbox(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Read-only URL params for admin page routing, no state change.
		$xf_action = isset( $_GET['xf_action'] ) ? sanitize_text_field( wp_unslash( $_GET['xf_action'] ) ) : '';
		$lead_id   = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification

		if ( 'view' === $xf_action && $lead_id ) {
			require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-lead-detail.php';
		} else {
			require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-leads-inbox.php';
		}
	}

	public function page_forms(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Read-only URL params for admin page routing, no state change.
		$action      = isset( $_GET['xf_action'] ) ? sanitize_text_field( wp_unslash( $_GET['xf_action'] ) ) : '';
		$form_id     = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$xf_template = isset( $_GET['xf_template'] ) ? sanitize_key( $_GET['xf_template'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification

		if ( 'new' === $action && ! $xf_template ) {
			// Show template picker before the builder.
			require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-form-template-picker.php';
		} elseif ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-form-builder.php';
		} else {
			require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-forms-list.php';
		}
	}

	public function page_integrations(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-integrations.php';
	}

	public function page_tags(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-tags.php';
	}

	public function page_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-settings.php';
	}

	public function page_automations(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-automations.php';
	}

	public function page_email_templates(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-email-templates.php';
	}

	public function page_routing_rules(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-routing-rules.php';
	}

	public function page_email_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-email-log.php';
	}

	public function page_webhooks(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-webhooks.php';
	}

	public function page_spam_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-spam-log.php';
	}

	public function page_import_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-import-export.php';
	}

	public function page_audit_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-audit-log.php';
	}

	// ── Form Handlers ─────────────────────────────────────────────────────────

	/**
	 * Handle settings form save.
	 */
	public function handle_save_settings(): void {
		check_admin_referer( 'xf_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$existing = get_option( 'xtremeforms_settings', array() );

		// ── Email Notifications ───────────────────────────────────────────────
		$recipients    = sanitize_text_field( wp_unslash( $_POST['recipients'] ?? '' ) );
		$email_from_name = sanitize_text_field( wp_unslash( $_POST['email_from_name'] ?? '' ) );
		$email_from    = sanitize_email( wp_unslash( $_POST['email_from'] ?? '' ) );

		// ── Bot Protection: reCAPTCHA ─────────────────────────────────────────
		$recaptcha_enabled    = isset( $_POST['recaptcha_enabled'] ) ? '1' : '0';
		$recaptcha_site_key   = sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ?? '' ) );
		$recaptcha_secret_key = sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ?? '' ) );
		$recaptcha_threshold  = isset( $_POST['recaptcha_threshold'] )
			? max( 0.1, min( 0.9, floatval( wp_unslash( $_POST['recaptcha_threshold'] ) ) ) )
			: 0.5;

		// reCAPTCHA key validation.
		$recaptcha_key_warning = '';
		if ( '1' === $recaptcha_enabled ) {
			if ( '' === $recaptcha_site_key || '' === $recaptcha_secret_key ) {
				$recaptcha_key_warning = 'missing';
			} elseif (
				! preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $recaptcha_site_key ) ||
				! preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $recaptcha_secret_key )
			) {
				$recaptcha_key_warning = 'invalid';
			}
		}

		// ── Bot Protection: Turnstile ─────────────────────────────────────────
		$turnstile_enabled    = isset( $_POST['turnstile_enabled'] ) ? '1' : '0';
		$turnstile_site_key   = sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ?? '' ) );
		$turnstile_secret_key = sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ?? '' ) );
		$turnstile_theme      = sanitize_key( wp_unslash( $_POST['turnstile_theme'] ?? 'auto' ) );
		$turnstile_size       = sanitize_key( wp_unslash( $_POST['turnstile_size'] ?? 'normal' ) );

		// ── Spam Blocklists ───────────────────────────────────────────────────
		$spam_domain_blocklist  = sanitize_textarea_field( wp_unslash( $_POST['spam_domain_blocklist'] ?? '' ) );
		$spam_keyword_blocklist = sanitize_textarea_field( wp_unslash( $_POST['spam_keyword_blocklist'] ?? '' ) );

		// ── Duplicate Detection ───────────────────────────────────────────────
		$duplicate_behavior      = sanitize_text_field( wp_unslash( $_POST['duplicate_behavior'] ?? 'silent_flag' ) );
		$duplicate_block_message = sanitize_text_field( wp_unslash( $_POST['duplicate_block_message'] ?? '' ) );
		$allowed_behaviors       = array( 'silent_flag', 'block', 'merge' );
		if ( ! in_array( $duplicate_behavior, $allowed_behaviors, true ) ) {
			$duplicate_behavior = 'silent_flag';
		}

		// ── Privacy & GDPR ────────────────────────────────────────────────────
		$anonymize_ip = isset( $_POST['anonymize_ip'] ) ? '1' : '0';

		// Data retention: blank = disabled, numeric >= 1 = enabled.
		$retention_days_post = isset( $_POST['retention_days'] ) ? sanitize_text_field( wp_unslash( $_POST['retention_days'] ) ) : '';
		$retention_days      = null;
		if ( '' !== $retention_days_post ) {
			$retention_days_raw = (int) $retention_days_post;
			if ( $retention_days_raw < 1 ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'  => 'xtreme-forms-settings',
							'error' => 'retention_min',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			$retention_days = $retention_days_raw;
		}

		// ── Build & persist settings array ────────────────────────────────────
		$settings = array_merge(
			$existing,
			array(
				'recipients'             => $recipients,
				'email_from_name'        => $email_from_name,
				'email_from'             => $email_from,
				'anonymize_ip'           => $anonymize_ip,
				'recaptcha_enabled'      => $recaptcha_enabled,
				'recaptcha_site_key'     => $recaptcha_site_key,
				'recaptcha_secret_key'   => $recaptcha_secret_key,
				'recaptcha_threshold'    => (string) $recaptcha_threshold,
				'turnstile_enabled'      => $turnstile_enabled,
				'turnstile_site_key'     => $turnstile_site_key,
				'turnstile_secret_key'   => $turnstile_secret_key,
				'turnstile_theme'        => $turnstile_theme,
				'turnstile_size'         => $turnstile_size,
				'spam_domain_blocklist'  => $spam_domain_blocklist,
				'spam_keyword_blocklist' => $spam_keyword_blocklist,
				'duplicate_behavior'     => $duplicate_behavior,
				'duplicate_block_message' => $duplicate_block_message,
			)
		);

		if ( null !== $retention_days ) {
			$settings['retention_days'] = $retention_days;
			update_option( 'xtremeforms_settings', $settings );
			XF_GDPR::schedule_retention_cron();
		} else {
			unset( $settings['retention_days'] );
			update_option( 'xtremeforms_settings', $settings );
			XF_GDPR::unschedule_retention_cron();
		}

		// Audit log.
		if ( class_exists( 'XF_Audit_Log' ) ) {
			XF_Audit_Log::record( XF_Audit_Log::ACTION_GLOBAL_SETTINGS_UPDATED, 0, array( 'section' => 'all' ) );
		}

		$redirect_args = array(
			'page'    => 'xtreme-forms-settings',
			'updated' => '1',
		);
		if ( '' !== $recaptcha_key_warning ) {
			$redirect_args['recaptcha_warning'] = $recaptcha_key_warning;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle form builder save.
	 */
	public function handle_save_form(): void {
		check_admin_referer( 'xf_save_form' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$name    = sanitize_text_field( wp_unslash( $_POST['form_name'] ?? '' ) );

		if ( '' === $name ) {
			$name = __( 'Untitled Form', 'xtreme-forms' );
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string; each field is sanitized individually after json_decode().
		$raw_fields = isset( $_POST['xf_fields'] )
			? wp_unslash( $_POST['xf_fields'] )
			: '[]';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$fields = json_decode( $raw_fields, true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		// v2 multi-page format: { v: 2, pages: [ { id, name, fields: [...] } ] }
		// Flatten all pages' fields into a single sequential array for storage.
		if ( isset( $fields['v'] ) && 2 === (int) $fields['v'] && isset( $fields['pages'] ) && is_array( $fields['pages'] ) ) {
			$flat = array();
			foreach ( $fields['pages'] as $pg ) {
				if ( is_array( $pg['fields'] ?? null ) ) {
					foreach ( $pg['fields'] as $pf ) {
						$flat[] = $pf;
					}
				}
			}
			$fields = $flat;
		}

		$validation_errors = array();
		$clean_fields      = array();

		foreach ( $fields as $idx => $field ) {
			$field_type  = sanitize_text_field( $field['type'] ?? 'text' );
			$field_id    = sanitize_key( $field['id'] ?? ( 'field_' . ( $idx + 1 ) ) );
			$field_label = sanitize_text_field( $field['label'] ?? '' );
			$field_ph    = sanitize_text_field( $field['placeholder'] ?? '' );
			$field_req   = ! empty( $field['required'] );
			$field_opts  = array();
			$field_dv    = sanitize_text_field( $field['default_value'] ?? '' );

			$requires_options = array( 'dropdown', 'checkbox', 'radio' );
			if ( in_array( $field_type, $requires_options, true ) ) {
				$raw_opts   = is_array( $field['options'] ?? null ) ? $field['options'] : array();
				$field_opts = array_values( array_filter( array_map( 'sanitize_text_field', $raw_opts ) ) );
				if ( empty( $field_opts ) ) {
					$validation_errors[] = sprintf(
						/* translators: 1: field label, 2: field type */
						__( 'Field "%1$s" (%2$s) must have at least one option.', 'xtreme-forms' ),
						$field_label ?: $field_id,
						$field_type
					);
					continue;
				}
			}

			$field_float = ! empty( $field['float'] );
			$field_width = isset( $field['width'] ) ? (float) $field['width'] : 100.0;
			if ( $field_width <= 0 || $field_width > 100 ) {
				$field_width = 100.0;
			}

			$field_rows = isset( $field['rows'] ) ? max( 1, min( 20, (int) $field['rows'] ) ) : 1;

			$clean = array(
				'id'            => $field_id,
				'type'          => $field_type,
				'label'         => $field_label,
				'placeholder'   => $field_ph,
				'required'      => $field_req,
				'default_value' => $field_dv,
				'float'         => $field_float,
				'width'         => $field_width,
				'rows'          => $field_rows,
			);

			if ( ! empty( $field_opts ) ) {
				$clean['options'] = $field_opts;
			}

			// Preserve conditional logic rules.
			if ( isset( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) ) {
				$raw_cl     = $field['conditional_logic'];
				$cl_enabled = ! empty( $raw_cl['enabled'] );
				$cl_logic   = in_array( $raw_cl['logic'] ?? 'and', array( 'and', 'or' ), true )
					? $raw_cl['logic']
					: 'and';

				$cl_conditions = array();
				if ( is_array( $raw_cl['conditions'] ?? null ) ) {
					$valid_operators = array( 'equals', 'not_equals', 'contains', 'not_empty', 'is_empty' );
					foreach ( $raw_cl['conditions'] as $cond ) {
						if ( ! is_array( $cond ) ) {
							continue;
						}
						$cond_trigger = sanitize_key( $cond['triggerFieldId'] ?? '' );
						$cond_op      = in_array( $cond['operator'] ?? 'equals', $valid_operators, true )
							? $cond['operator']
							: 'equals';
						$cond_value   = sanitize_text_field( $cond['value'] ?? '' );
						if ( '' !== $cond_trigger ) {
							$cl_conditions[] = array(
								'triggerFieldId' => $cond_trigger,
								'operator'       => $cond_op,
								'value'          => $cond_value,
							);
						}
					}
				}

				$clean['conditional_logic'] = array(
					'enabled'    => $cl_enabled,
					'logic'      => $cl_logic,
					'conditions' => $cl_conditions,
				);
			} else {
				// Preserve a default empty conditional_logic so the JS builder can read it.
				$clean['conditional_logic'] = array(
					'enabled'    => false,
					'logic'      => 'and',
					'conditions' => array(),
				);
			}

			$clean_fields[] = $clean;
		}

		if ( ! empty( $validation_errors ) ) {
			$transient_key = 'xf_form_errors_' . get_current_user_id();
			set_transient( $transient_key, $validation_errors, 60 );

			$redirect_args = array(
				'page'      => 'xtreme-forms-forms',
				'xf_action' => $form_id ? 'edit' : 'new',
				'error'     => '1',
			);
			if ( $form_id ) {
				$redirect_args['form_id'] = $form_id;
			}

			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// Validate auto-responder reply-to if provided.
		$ar_reply_to = sanitize_email( wp_unslash( $_POST['auto_responder_reply_to'] ?? '' ) );
		if ( '' !== $ar_reply_to && ! is_email( $ar_reply_to ) ) {
			// Invalid reply-to — store error and redirect back.
			$transient_key = 'xf_form_errors_' . get_current_user_id();
			set_transient( $transient_key, array( __( 'Auto-responder reply-to address is not a valid email.', 'xtreme-forms' ) ), 60 );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'xtreme-forms-forms',
						'xf_action' => $form_id ? 'edit' : 'new',
						'form_id'   => $form_id ?: null,
						'error'     => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$form_settings = array(
			'submit_label'            => sanitize_text_field( wp_unslash( $_POST['submit_label'] ?? '' ) ),
			'redirect_url'            => esc_url_raw( wp_unslash( $_POST['redirect_url'] ?? '' ) ),
			'thank_you_message'       => sanitize_textarea_field( wp_unslash( $_POST['thank_you_message'] ?? '' ) ),
			'email_recipients'        => sanitize_text_field( wp_unslash( $_POST['email_recipients'] ?? '' ) ),
			// Auto-responder settings.
			'auto_responder_enabled'  => isset( $_POST['auto_responder_enabled'] ) ? '1' : '0',
			'auto_responder_subject'  => sanitize_text_field( wp_unslash( $_POST['auto_responder_subject'] ?? '' ) ),
			// GDPR consent checkbox.
			'consent_enabled'         => isset( $_POST['consent_enabled'] ) ? '1' : '0',
			'consent_label'           => sanitize_textarea_field( wp_unslash( $_POST['consent_label'] ?? '' ) ),
			'consent_url'             => esc_url_raw( wp_unslash( $_POST['consent_url'] ?? '' ) ),
			// reCAPTCHA per-form.
			'recaptcha_enabled'       => isset( $_POST['form_recaptcha_enabled'] ) ? '1' : '0',
			'auto_responder_body'     => sanitize_textarea_field( wp_unslash( $_POST['auto_responder_body'] ?? '' ) ),
			'auto_responder_reply_to' => $ar_reply_to,
			// Scheduling.
			'countdown_timer_enabled' => isset( $_POST['countdown_timer_enabled'] ) ? '1' : '0',
			'closed_message'          => sanitize_textarea_field( wp_unslash( $_POST['closed_message'] ?? '' ) ),
			'center_form'             => isset( $_POST['center_form'] ) ? '1' : '0',
			'submit_float'            => isset( $_POST['submit_float'] ) && '1' === $_POST['submit_float'] ? '1' : '0',
			'submit_width'            => (string) max( 10, min( 100, (int) ( $_POST['submit_width'] ?? 100 ) ) ),
			'submit_align'            => in_array( $_POST['submit_align'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? sanitize_text_field( $_POST['submit_align'] ) : 'left',
			'submit_bg_color'         => sanitize_hex_color( wp_unslash( $_POST['submit_bg_color'] ?? '#1A73E8' ) ) ?: '#1A73E8',
			'submit_text_color'       => sanitize_hex_color( wp_unslash( $_POST['submit_text_color'] ?? '#ffffff' ) ) ?: '#ffffff',
			'submit_btn_size'         => in_array( $_POST['submit_btn_size'] ?? 'md', array( 'sm', 'md', 'lg', 'xl' ), true ) ? sanitize_text_field( $_POST['submit_btn_size'] ) : 'md',
		);

		// Scheduling datetime values.
		$activate_at_raw = sanitize_text_field( wp_unslash( $_POST['activate_at'] ?? '' ) );
		$expire_at_raw   = sanitize_text_field( wp_unslash( $_POST['expire_at'] ?? '' ) );

		// Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL format.
		$activate_at = $this->parse_datetime_local( $activate_at_raw );
		$expire_at   = $this->parse_datetime_local( $expire_at_raw );

		if ( $form_id ) {
			XF_Forms::update_form( $form_id, $name, $clean_fields, $form_settings );
			$is_new = false;
		} else {
			$form_id = XF_Forms::create_form( $name, $clean_fields, $form_settings );
			$is_new  = true;
		}

		// Save scheduling columns directly (not in settings JSON).
		if ( $form_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'xtremeforms_forms';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'activate_at'    => $activate_at ?: null,
					'expire_at'      => $expire_at ?: null,
					'closed_message' => $form_settings['closed_message'],
				),
				array( 'id' => $form_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		// Audit log.
		if ( class_exists( 'XF_Audit_Log' ) && $form_id ) {
			XF_Audit_Log::record(
				$is_new ? XF_Audit_Log::ACTION_FORM_CREATED : XF_Audit_Log::ACTION_FORM_UPDATED,
				$form_id,
				array( 'form_name' => $name )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'xtreme-forms-forms',
					'xf_action' => 'edit',
					'form_id'   => $form_id,
					'updated'   => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle form deletion.
	 */
	public function handle_delete_form(): void {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		check_admin_referer( 'xf_delete_form_' . $form_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		if ( $form_id ) {
			$form = XF_Forms::get_form( $form_id ); // Grab name for audit before delete.
			XF_Forms::delete_form( $form_id );
			// Audit log.
			if ( class_exists( 'XF_Audit_Log' ) ) {
				XF_Audit_Log::record(
					XF_Audit_Log::ACTION_FORM_DELETED,
					$form_id,
					array( 'form_name' => $form ? $form->name : '' )
				);
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'xtreme-forms-forms',
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle bulk lead actions via POST form.
	 */
	public function handle_bulk_leads_post(): void {
		check_admin_referer( 'xf_bulk_leads' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$action  = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
		$ids_raw = isset( $_POST['lead_ids'] ) && is_array( $_POST['lead_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['lead_ids'] ) )
			: array();
		$ids     = array_filter( $ids_raw );

		// Preserve filter state in redirect.
		$redirect_args = array( 'page' => 'xtreme-forms-leads' );
		foreach ( array( 'xf_status', 'xf_form', 'xf_tags', 'xf_date_from', 'xf_date_to', 'xf_filter' ) as $k ) {
			if ( ! empty( $_POST[ $k ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$redirect_args[ $k ] = is_array( $_POST[ $k ] )
					? array_map( 'absint', $_POST[ $k ] )
					: sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
			}
		}

		$redirect = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );

		if ( empty( $ids ) ) {
			wp_safe_redirect( add_query_arg( 'bulk_error', 'no_selection', $redirect ) );
			exit;
		}

		switch ( $action ) {
			case 'delete':
				$count = XF_Leads::bulk_delete( $ids );
				wp_safe_redirect( add_query_arg( array( 'bulk_deleted' => $count ), $redirect ) );
				break;

			case 'mark_contacted':
				$count = XF_Leads::bulk_update_status( $ids, XF_Leads::STATUS_CONTACTED );
				wp_safe_redirect( add_query_arg( array( 'bulk_contacted' => $count ), $redirect ) );
				break;

			case 'export_selected':
				// Hand off to the export handler with specific IDs.
				$this->do_csv_export( $ids );
				exit;

			default:
				wp_safe_redirect( $redirect );
		}
		exit;
	}

	// ── Email Templates ─────────────────────────────────────────────

	/**
	 * Handle email template settings save.
	 */
	public function handle_save_email_template(): void {
		check_admin_referer( 'xf_save_email_template' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$redirect = add_query_arg(
			array( 'page' => 'xtreme-forms-email-templates' ),
			admin_url( 'admin.php' )
		);

		// Handle logo upload.
		$logo_url = sanitize_url( wp_unslash( $_POST['xf_logo_url'] ?? '' ) );

		// Check if admin wants to remove existing logo.
		if ( ! empty( $_POST['xf_remove_logo'] ) ) {
			$logo_url = '';
		}

		// Process file upload if a new file was provided.
		if ( ! empty( $_FILES['xf_logo_file']['name'] ) ) {
			$upload_result = $this->handle_logo_upload();

			if ( is_wp_error( $upload_result ) ) {
				set_transient( 'xf_template_error_' . get_current_user_id(), $upload_result->get_error_message(), 30 );
				wp_safe_redirect( add_query_arg( 'logo_error', '1', $redirect ) );
				exit;
			}

			$logo_url = $upload_result;
		}

		$data = array(
			'logo_url'     => $logo_url,
			'header_color' => sanitize_text_field( wp_unslash( $_POST['xf_header_color'] ?? '#1A73E8' ) ),
			'subject'      => sanitize_text_field( wp_unslash( $_POST['xf_subject'] ?? '' ) ),
			'body_text'    => sanitize_textarea_field( wp_unslash( $_POST['xf_body_text'] ?? '' ) ),
			'footer_text'  => sanitize_textarea_field( wp_unslash( $_POST['xf_footer_text'] ?? '' ) ),
		);

		XF_Email_Templates::save_template( $data );

		wp_safe_redirect( add_query_arg( 'updated', '1', $redirect ) );
		exit;
	}

	/**
	 * Handle logo file upload via WordPress media upload API.
	 *
	 * Validates file type (JPEG, PNG, GIF, WebP only) and size (max 2 MB).
	 *
	 * @return string|WP_Error Uploaded file URL on success, or WP_Error on failure.
	 */
	private function handle_logo_upload(): string|\WP_Error {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by check_admin_referer() in calling handler; file upload data processed by wp_handle_upload().
		$file = $_FILES['xf_logo_file'] ?? null;

		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed. Please try again.', 'xtreme-forms' ) );
		}

		// Validate MIME type before uploading.
		$allowed_mime_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
		);

		// Use finfo for reliable MIME type detection (not just extension).
		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$mime_type = $finfo->file( $file['tmp_name'] );

		if ( ! in_array( $mime_type, $allowed_mime_types, true ) ) {
			return new \WP_Error(
				'invalid_type',
				__( 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.', 'xtreme-forms' )
			);
		}

		// Validate file size (max 2 MB).
		$max_size = 2 * 1024 * 1024; // 2 MB in bytes.
		if ( (int) $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				__( 'File is too large. Maximum allowed size is 2 MB.', 'xtreme-forms' )
			);
		}

		// Validate file extension against MIME type (prevent extension spoofing).
		$ext_to_mime = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);

		$original_name = $file['name'] ?? '';
		$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! isset( $ext_to_mime[ $ext ] ) || $ext_to_mime[ $ext ] !== $mime_type ) {
			return new \WP_Error(
				'invalid_type',
				__( 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.', 'xtreme-forms' )
			);
		}

		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'gif'      => 'image/gif',
				'webp'     => 'image/webp',
			),
		);

		$upload = wp_handle_upload( $file, $overrides );

		if ( isset( $upload['error'] ) ) {
			return new \WP_Error( 'wp_upload_error', $upload['error'] );
		}

		return $upload['url'] ?? '';
	}

	// ── Routing Rules ───────────────────────────────────────────────

	/**
	 * Handle routing rules save.
	 */
	public function handle_save_routing_rules(): void {
		check_admin_referer( 'xf_save_routing_rules' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$mode = sanitize_text_field( wp_unslash( $_POST['routing_mode'] ?? 'match_first' ) );
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- rules array; each element is sanitized individually in the foreach loop below.
		$raw_rules = isset( $_POST['rules'] ) && is_array( $_POST['rules'] )
			? wp_unslash( $_POST['rules'] )
			: array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$clean_rules = array();
		foreach ( $raw_rules as $rule ) {
			$recipient = sanitize_email( $rule['recipient_email'] ?? '' );
			if ( ! is_email( $recipient ) ) {
				continue; // Skip rules with invalid recipients.
			}

			$clean_rules[] = array(
				'condition_type'  => sanitize_text_field( $rule['condition_type'] ?? 'form' ),
				'form_id'         => absint( $rule['form_id'] ?? 0 ),
				'field_id'        => sanitize_text_field( $rule['field_id'] ?? '' ),
				'field_value'     => sanitize_text_field( $rule['field_value'] ?? '' ),
				'recipient_email' => $recipient,
				'is_active'       => isset( $rule['is_active'] ) ? 1 : 0,
			);
		}

		XF_Routing_Rules::save_all_rules( $clean_rules, $mode );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'xtreme-forms-routing-rules',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ── Tag Management ────────────────────────────────────────────────────────

	/**
	 * Handle tag creation from the tag management page.
	 */
	public function handle_save_tag(): void {
		check_admin_referer( 'xf_save_tag' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$name     = sanitize_text_field( wp_unslash( $_POST['tag_name'] ?? '' ) );
		$result   = XF_Tags::create_tag( $name );
		$redirect = add_query_arg( array( 'page' => 'xtreme-forms-tags' ), admin_url( 'admin.php' ) );

		if ( is_wp_error( $result ) ) {
			set_transient(
				'xf_tag_error_' . get_current_user_id(),
				$result->get_error_message(),
				30
			);
			wp_safe_redirect( add_query_arg( 'tag_error', '1', $redirect ) );
		} else {
			wp_safe_redirect( add_query_arg( 'tag_created', '1', $redirect ) );
		}
		exit;
	}

	/**
	 * Handle tag deletion from the tag management page.
	 */
	public function handle_delete_tag(): void {
		$tag_id = isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0;

		check_admin_referer( 'xf_delete_tag_' . $tag_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		if ( $tag_id ) {
			XF_Tags::delete_tag( $tag_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'xtreme-forms-tags',
					'tag_deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ── Save duplicate/analytics settings ───────────────────────────

	/**
	 * Handle saving duplicate behavior settings.
	 * This extends the main settings save to include the new fields.
	 */
	public function handle_save_settings_s4(): void {
		check_admin_referer( 'xf_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$settings = get_option( 'xtremeforms_settings', array() );

		// Merge duplicate detection settings.
		$settings['duplicate_behavior']      = sanitize_text_field( wp_unslash( $_POST['duplicate_behavior'] ?? 'silent_flag' ) );
		$settings['duplicate_block_message'] = sanitize_text_field( wp_unslash( $_POST['duplicate_block_message'] ?? '' ) );

		// Validate behavior value.
		$allowed = array( 'silent_flag', 'block', 'merge' );
		if ( ! in_array( $settings['duplicate_behavior'], $allowed, true ) ) {
			$settings['duplicate_behavior'] = 'silent_flag';
		}

		update_option( 'xtremeforms_settings', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'xtreme-forms-settings',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ── CSV Export ────────────────────────────────────────────────────────────

	/**
	 * Handle CSV export via admin-post.
	 */
	public function handle_export_leads(): void {
		check_admin_referer( 'xf_export_leads' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		// Build filters from GET parameters.
		$filters = array();

		if ( ! empty( $_GET['xf_status'] ) ) {
			$filters['status'] = sanitize_text_field( wp_unslash( $_GET['xf_status'] ) );
		}
		if ( ! empty( $_GET['xf_form'] ) ) {
			$filters['form_id'] = absint( $_GET['xf_form'] );
		}
		if ( ! empty( $_GET['xf_date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['xf_date_from'] ) );
		}
		if ( ! empty( $_GET['xf_date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['xf_date_to'] ) );
		}
		if ( ! empty( $_GET['xf_tags'] ) && is_array( $_GET['xf_tags'] ) ) {
			$filters['tag_ids'] = array_map( 'absint', wp_unslash( $_GET['xf_tags'] ) );
		}
		if ( ! empty( $_GET['xf_filter'] ) && 'my_leads' === $_GET['xf_filter'] ) {
			$filters['assigned_to'] = get_current_user_id();
		}

		// Get all matching leads.
		$result = XF_Leads::get_leads_filtered( $filters, 1, 999999 );
		$leads  = $result['leads'];

		$this->do_csv_export( array(), $leads );
	}

	/**
	 * Generate and stream a CSV export.
	 *
	 * @param array $ids Specific lead IDs (for "Export Selected"). If empty, uses $leads.
	 * @param array $leads Pre-fetched lead rows (for filtered export). If empty, fetches by IDs.
	 */
	private function do_csv_export( array $ids = array(), array $leads = array() ): void {
		if ( ! empty( $ids ) ) {
			// Security: verify each ID belongs to an actual lead.
			$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
			$leads = XF_Leads::get_leads_by_ids( $ids );
		}

		if ( empty( $leads ) ) {
			wp_die( esc_html__( 'No leads to export.', 'xtreme-forms' ) );
		}

		// Pre-load forms to get field labels.
		$form_ids    = array_unique( array_map( static fn( $l ) => (int) $l->form_id, $leads ) );
		$forms_cache = XF_Forms::get_forms_by_ids( $form_ids );

		// Pre-load tags for all leads.
		$lead_ids     = array_map( static fn( $l ) => (int) $l->id, $leads );
		$tags_by_lead = XF_Tags::get_tags_for_leads( $lead_ids );

		// Collect all unique field definitions across all forms (for column headers).
		$all_field_defs = array(); // keyed by field_id => label.
		foreach ( $forms_cache as $form ) {
			$fields = XF_Forms::decode_fields( $form );
			foreach ( $fields as $f ) {
				$fid = $f['id'] ?? '';
				if ( '' === $fid || 'hidden' === ( $f['type'] ?? '' ) ) {
					continue;
				}
				if ( ! isset( $all_field_defs[ $fid ] ) ) {
					$all_field_defs[ $fid ] = $f['label'] ?? $fid;
				}
			}
		}

		// Build CSV headers.
		$meta_headers  = array(
			__( 'Lead ID', 'xtreme-forms' ),
			__( 'Date', 'xtreme-forms' ),
			__( 'Source Form', 'xtreme-forms' ),
			__( 'Source URL', 'xtreme-forms' ),
			__( 'IP Address', 'xtreme-forms' ),
			__( 'Status', 'xtreme-forms' ),
			__( 'Assigned To', 'xtreme-forms' ),
			__( 'Tags', 'xtreme-forms' ),
		);
		$field_headers = array_values( $all_field_defs );
		$headers       = array_merge( $meta_headers, $field_headers );
		$field_keys    = array_keys( $all_field_defs );

		// Stream the CSV.
		$filename = 'xtreme-forms-export-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output BOM for UTF-8 Excel compatibility.
		echo "\xEF\xBB\xBF";

		$out = fopen( 'php://output', 'w' );

		// Headers row.
		fputcsv( $out, $headers );

		$statuses = XF_Leads::get_statuses();

		foreach ( $leads as $lead ) {
			$field_values = XF_Leads::decode_field_values( $lead );
			$form         = $forms_cache[ (int) $lead->form_id ] ?? null;
			$form_name    = $form ? $form->name : __( '(deleted form)', 'xtreme-forms' );

			// Assigned user.
			$assigned_to   = (int) ( $lead->assigned_to ?? 0 );
			$assignee_name = '';
			if ( $assigned_to ) {
				$u             = get_userdata( $assigned_to );
				$assignee_name = $u ? $u->display_name : '';
			}

			// Tags (semicolon-delimited within cell).
			$lead_tags   = $tags_by_lead[ (int) $lead->id ] ?? array();
			$tag_names   = array_map( static fn( $t ) => $t->name, $lead_tags );
			$tags_string = implode( ';', $tag_names );

			// Status label.
			$status_label = $statuses[ $lead->status ?? 'new' ] ?? ucfirst( $lead->status ?? 'new' );

			// Build row.
			$row = array(
				(int) $lead->id,
				(string) $lead->created_at,
				(string) $form_name,
				(string) $lead->source_url,
				(string) $lead->ip_address,
				(string) $status_label,
				(string) $assignee_name,
				(string) $tags_string,
			);

			// Append form field values in column order.
			foreach ( $field_keys as $fid ) {
				$val = $field_values[ $fid ] ?? '';
				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}
				$row[] = (string) $val;
			}

			fputcsv( $out, $row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, WP_Filesystem not applicable for CSV export.
		fclose( $out );
		exit;
	}

	// ── Settings save ───────────────────────────────────────────────

	/**
	 * Handle spam and GDPR settings (reCAPTCHA, spam blocklists, retention, etc.).
	 */
	public function handle_save_settings_s5(): void {
		check_admin_referer( 'xf_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$existing = get_option( 'xtremeforms_settings', array() );

		// reCAPTCHA settings.
		$recaptcha_enabled    = isset( $_POST['recaptcha_enabled'] ) ? '1' : '0';
		$recaptcha_site_key   = sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ?? '' ) );
		$recaptcha_secret_key = sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ?? '' ) );
		$recaptcha_threshold  = isset( $_POST['recaptcha_threshold'] )
			? max( 0.1, min( 0.9, floatval( wp_unslash( $_POST['recaptcha_threshold'] ) ) ) )
			: 0.5;

		// Turnstile settings.
		$turnstile_enabled    = isset( $_POST['turnstile_enabled'] ) ? '1' : '0';
		$turnstile_site_key   = sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ?? '' ) );
		$turnstile_secret_key = sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ?? '' ) );
		$turnstile_theme      = sanitize_key( wp_unslash( $_POST['turnstile_theme'] ?? 'auto' ) );
		$turnstile_size       = sanitize_key( wp_unslash( $_POST['turnstile_size'] ?? 'normal' ) );

		// Spam blocklists.
		$spam_domain_blocklist  = sanitize_textarea_field( wp_unslash( $_POST['spam_domain_blocklist'] ?? '' ) );
		$spam_keyword_blocklist = sanitize_textarea_field( wp_unslash( $_POST['spam_keyword_blocklist'] ?? '' ) );

		// Data retention.
		// Distinguish between blank (disabled) and an explicit numeric entry.
		$retention_days_post = isset( $_POST['retention_days'] ) ? trim( (string) absint( wp_unslash( $_POST['retention_days'] ) ) ) : '';
		if ( '' !== $retention_days_post ) {
			// User supplied a value — must be >= 1. 0 and negatives are invalid.
			$retention_days_raw = (int) $retention_days_post;
			if ( $retention_days_raw < 1 ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'  => 'xtreme-forms-settings',
							'error' => 'retention_min',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			$retention_days = $retention_days_raw;
		} else {
			// Blank means "no retention policy / disabled".
			$retention_days = null;
		}

		// ── reCAPTCHA key validation ───────────────────────────────────────────
		// Detect blank or malformed keys when reCAPTCHA is enabled.
		// reCAPTCHA v3 keys are base64url strings: [A-Za-z0-9_\-], minimum 20 chars.
		$recaptcha_key_warning = '';
		if ( '1' === $recaptcha_enabled ) {
			if ( '' === $recaptcha_site_key || '' === $recaptcha_secret_key ) {
				$recaptcha_key_warning = 'missing';
			} elseif (
				! preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $recaptcha_site_key ) ||
				! preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $recaptcha_secret_key )
			) {
				// Non-blank but doesn't match expected reCAPTCHA key format.
				$recaptcha_key_warning = 'invalid';
			}
		}

		$settings = array_merge(
			$existing,
			array(
				'recaptcha_enabled'      => $recaptcha_enabled,
				'recaptcha_site_key'     => $recaptcha_site_key,
				'recaptcha_secret_key'   => $recaptcha_secret_key,
				'recaptcha_threshold'    => (string) $recaptcha_threshold,
				'turnstile_enabled'      => $turnstile_enabled,
				'turnstile_site_key'     => $turnstile_site_key,
				'turnstile_secret_key'   => $turnstile_secret_key,
				'turnstile_theme'        => $turnstile_theme,
				'turnstile_size'         => $turnstile_size,
				'spam_domain_blocklist'  => $spam_domain_blocklist,
				'spam_keyword_blocklist' => $spam_keyword_blocklist,
			)
		);

		if ( null !== $retention_days ) {
			$settings['retention_days'] = $retention_days;
			update_option( 'xtremeforms_settings', $settings );
			XF_GDPR::schedule_retention_cron();
		} else {
			unset( $settings['retention_days'] );
			update_option( 'xtremeforms_settings', $settings );
			XF_GDPR::unschedule_retention_cron();
		}

		$redirect_args = array(
			'page'    => 'xtreme-forms-settings',
			'updated' => '1',
		);

		if ( '' !== $recaptcha_key_warning ) {
			$redirect_args['recaptcha_warning'] = $recaptcha_key_warning;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Parse a datetime-local input value (YYYY-MM-DDTHH:MM) into a MySQL datetime string.
	 *
	 * @param string $raw Raw input value.
	 * @return string MySQL datetime or empty string.
	 */
	private function parse_datetime_local( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		// datetime-local format: "2026-04-06T14:30" — convert to "2026-04-06 14:30:00".
		$raw = str_replace( 'T', ' ', $raw );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $raw ) ) {
			// Ensure seconds are included.
			if ( strlen( $raw ) === 16 ) {
				$raw .= ':00';
			}
			return $raw;
		}
		return '';
	}

	// ── Export / Import handlers ────────────────────────────────────

	/**
	 * Handle data export (full or per-form).
	 */
	public function handle_export_data(): void {
		check_admin_referer( 'xf_export_data' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$export_type = sanitize_key( wp_unslash( $_POST['export_type'] ?? 'full' ) );
		$form_id     = absint( $_POST['form_id'] ?? 0 );

		if ( 'form' === $export_type ) {
			if ( ! $form_id ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'            => 'xtreme-forms-import-export',
							'xf_export_error' => '1',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			$data = XF_Import_Export::build_form_export( $form_id );
			if ( is_wp_error( $data ) ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'            => 'xtreme-forms-import-export',
							'xf_export_error' => '1',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
			$filename = 'xtreme-forms-form-' . $form_id . '-' . gmdate( 'Y-m-d' ) . '.json';
		} else {
			$data     = XF_Import_Export::build_full_export();
			$filename = 'xtreme-forms-export-full-' . gmdate( 'Y-m-d' ) . '.json';
		}

		// Audit log.
		XF_Audit_Log::record(
			XF_Audit_Log::ACTION_PLUGIN_DATA_EXPORTED,
			$form_id,
			array( 'export_type' => $export_type )
		);

		XF_Import_Export::stream_json_download( $data, $filename );
	}

	/**
	 * Handle data import from uploaded JSON file.
	 */
	public function handle_import_data(): void {
		check_admin_referer( 'xf_import_data' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$transient_key = 'xf_import_result_' . get_current_user_id();
		$redirect      = add_query_arg( array( 'page' => 'xtreme-forms-import-export' ), admin_url( 'admin.php' ) );

		// Validate file upload.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file upload; tmp_name used only with is_uploaded_file() and file_get_contents() on a temporary server path.
		if ( empty( $_FILES['xf_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['xf_import_file']['tmp_name'] ) ) {
			set_transient( $transient_key, new WP_Error( 'no_file', __( 'No file uploaded.', 'xtreme-forms' ) ), 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		$file_size = absint( $_FILES['xf_import_file']['size'] ?? 0 );
		if ( $file_size > 2 * MB_IN_BYTES ) {
			set_transient( $transient_key, new WP_Error( 'file_too_large', __( 'Import file exceeds 2 MB limit.', 'xtreme-forms' ) ), 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		$json = file_get_contents( $_FILES['xf_import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( false === $json ) {
			set_transient( $transient_key, new WP_Error( 'read_error', __( 'Could not read the uploaded file.', 'xtreme-forms' ) ), 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		$data = XF_Import_Export::parse_import( $json );
		if ( is_wp_error( $data ) ) {
			set_transient( $transient_key, $data, 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		// Execute import based on type.
		if ( 'form' === ( $data['type'] ?? '' ) ) {
			$result = XF_Import_Export::execute_form_import( $data );
		} else {
			$result = XF_Import_Export::execute_full_import( $data );
		}

		set_transient( $transient_key, $result, 60 );
		wp_safe_redirect( $redirect );
		exit;
	}
}
