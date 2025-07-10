<?php
/**
 * Admin class – menus, pages, and asset enqueueing.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Admin
 */
class XL_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
	}

	// ── Menus ────────────────────────────────────────────────────────────────

	public function register_menus(): void {
		// In a network-activated multisite environment, respect per-site opt-out.
		if ( is_multisite() && class_exists( 'XL_Multisite' ) && XL_Multisite::is_site_disabled() ) {
			return;
		}

		add_menu_page(
			__( 'XtremeLeads', 'xtremeleads' ),
			__( 'XtremeLeads', 'xtremeleads' ),
			'manage_options',
			'xtremeleads',
			array( $this, 'page_dashboard' ),
			'dashicons-email-alt',
			25
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Dashboard', 'xtremeleads' ),
			__( 'Dashboard', 'xtremeleads' ),
			'manage_options',
			'xtremeleads',
			array( $this, 'page_dashboard' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Leads Inbox', 'xtremeleads' ),
			__( 'Leads', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-leads',
			array( $this, 'page_leads_inbox' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Form Metrics', 'xtremeleads' ),
			__( 'Form Metrics', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-form-metrics',
			array( $this, 'page_form_metrics' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Forms', 'xtremeleads' ),
			__( 'Forms', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-forms',
			array( $this, 'page_forms' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Tags', 'xtremeleads' ),
			__( 'Tags', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-tags',
			array( $this, 'page_tags' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Email Templates', 'xtremeleads' ),
			__( 'Email Templates', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-email-templates',
			array( $this, 'page_email_templates' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Routing Rules', 'xtremeleads' ),
			__( 'Routing Rules', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-routing-rules',
			array( $this, 'page_routing_rules' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Email Log', 'xtremeleads' ),
			__( 'Email Log', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-email-log',
			array( $this, 'page_email_log' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Webhooks', 'xtremeleads' ),
			__( 'Webhooks', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-webhooks',
			array( $this, 'page_webhooks' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Spam Log', 'xtremeleads' ),
			__( 'Spam Log', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-spam-log',
			array( $this, 'page_spam_log' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Settings', 'xtremeleads' ),
			__( 'Settings', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-settings',
			array( $this, 'page_settings' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Import / Export', 'xtremeleads' ),
			__( 'Import / Export', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-import-export',
			array( $this, 'page_import_export' )
		);

		add_submenu_page(
			'xtremeleads',
			__( 'Audit Log', 'xtremeleads' ),
			__( 'Audit Log', 'xtremeleads' ),
			'manage_options',
			'xtremeleads-audit-log',
			array( $this, 'page_audit_log' )
		);
	}

	// ── Asset enqueueing ─────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'xtremeleads' ) ) {
			return;
		}

		wp_enqueue_style(
			'xl-admin',
			XTREMELEADS_PLUGIN_URL . 'admin/css/xl-admin.css',
			array(),
			XTREMELEADS_VERSION
		);

		wp_enqueue_script(
			'xl-admin',
			XTREMELEADS_PLUGIN_URL . 'admin/js/xl-admin.js',
			array(),
			XTREMELEADS_VERSION,
			true
		);

		// Enqueue Chart.js and dashboard script on dashboard/metrics pages.
		if ( false !== strpos( $hook, 'xtremeleads_page_xtremeleads-form-metrics' )
			|| false !== strpos( $hook, 'toplevel_page_xtremeleads' )
		) {
			wp_enqueue_script(
				'xl-chartjs',
				XTREMELEADS_PLUGIN_URL . 'admin/js/vendor/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);
			wp_enqueue_script(
				'xl-dashboard',
				XTREMELEADS_PLUGIN_URL . 'admin/js/xl-dashboard.js',
				array( 'xl-chartjs' ),
				XTREMELEADS_VERSION,
				true
			);
			wp_localize_script(
				'xl-dashboard',
				'xlDashboardData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					// Legacy fallback nonce (kept for backward compatibility with non-analytics calls).
					'nonce' => wp_create_nonce( 'xl_admin_nonce' ),
					// Per-endpoint nonces — each analytics endpoint verifies its own specific action.
					'nonces' => array(
						'xl_dashboard_stats' => wp_create_nonce( 'xl_dashboard_stats_nonce' ),
						'xl_chart_leads_by_form' => wp_create_nonce( 'xl_chart_leads_by_form_nonce' ),
						'xl_chart_leads_over_time' => wp_create_nonce( 'xl_chart_leads_over_time_nonce' ),
						'xl_utm_report' => wp_create_nonce( 'xl_utm_report_nonce' ),
						'xl_form_metrics' => wp_create_nonce( 'xl_form_metrics_nonce' ),
					),
					'isFormMetrics' => false !== strpos( $hook, 'form-metrics' ),
					'i18n' => array(
						'loading' => __( 'Loading…', 'xtremeleads' ),
						'error' => __( 'Failed to load data. Please try again.', 'xtremeleads' ),
						'noData' => __( 'No data for this period', 'xtremeleads' ),
						'noForms' => __( 'No active forms yet.', 'xtremeleads' ),
						'noSubmissions' => __( 'No submissions yet', 'xtremeleads' ),
						'leadsOverTime' => __( 'Leads Over Time', 'xtremeleads' ),
						'leadsByForm' => __( 'Leads by Form', 'xtremeleads' ),
						'conversionWarning' => __( 'Submission count exceeds recorded impressions — impression data may be incomplete', 'xtremeleads' ),
						'viewAll' => __( 'View all', 'xtremeleads' ),
						'invalidDateRange' => __( 'End date cannot be before start date.', 'xtremeleads' ),
					),
				)
			);
		}

		$statuses = XL_Leads::get_statuses();
		$all_tags = XL_Tags::get_all_tags();

		wp_localize_script(
			'xl-admin',
			'xlAdminData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'xl_admin_nonce' ),
				'webhookNonce' => wp_create_nonce( 'xl_webhook_nonce' ),
				'gdprNonce' => wp_create_nonce( 'xl_gdpr_nonce' ),
				'spamLogNonce' => wp_create_nonce( 'xl_spam_log_nonce' ),
				'pluginUrl' => XTREMELEADS_PLUGIN_URL,
				'statuses' => $statuses,
				'allTags' => array_map(
					static function ( $t ) {
						return array( 'id' => (int) $t->id, 'name' => $t->name );
					},
					$all_tags
				),
				'i18n' => array(
					'confirmDelete' => __( 'Are you sure you want to delete the selected leads? This cannot be undone.', 'xtremeleads' ),
					'noItemsSelected' => __( 'No items selected.', 'xtremeleads' ),
					'loading' => __( 'Loading…', 'xtremeleads' ),
					'error' => __( 'An error occurred. Please try again.', 'xtremeleads' ),
					'close' => __( 'Close', 'xtremeleads' ),
					'save' => __( 'Save', 'xtremeleads' ),
					'saving' => __( 'Saving…', 'xtremeleads' ),
					'unassigned' => __( 'Unassigned', 'xtremeleads' ),
					'addNote' => __( 'Add Note', 'xtremeleads' ),
					'noteContentEmpty' => __( 'Note content cannot be empty.', 'xtremeleads' ),
					'noteAdded' => __( 'Note added.', 'xtremeleads' ),
					'statusUpdated' => __( 'Status updated.', 'xtremeleads' ),
					'assignmentSaved' => __( 'Assignment saved.', 'xtremeleads' ),
					'tagAdded' => __( 'Tag added.', 'xtremeleads' ),
					'tagRemoved' => __( 'Tag removed.', 'xtremeleads' ),
					'addTag' => __( 'Add Tag', 'xtremeleads' ),
					'removeTag' => __( 'Remove tag', 'xtremeleads' ),
					'noTagsFound' => __( 'No tags found.', 'xtremeleads' ),
					'typeToSearch' => __( 'Type to search tags…', 'xtremeleads' ),
					'notesTitle' => __( 'Notes', 'xtremeleads' ),
					'noNotes' => __( 'No notes yet. Add the first note below.', 'xtremeleads' ),
					'activityTitle' => __( 'Activity', 'xtremeleads' ),
					'noActivity' => __( 'No activity recorded yet.', 'xtremeleads' ),
					'tabDetails' => __( 'Details', 'xtremeleads' ),
					'tabNotes' => __( 'Notes', 'xtremeleads' ),
					'tabActivity' => __( 'Activity', 'xtremeleads' ),
					'flyoutLeadId' => __( 'Lead ID', 'xtremeleads' ),
					'flyoutForm' => __( 'Form', 'xtremeleads' ),
					'flyoutStatus' => __( 'Status', 'xtremeleads' ),
					'flyoutDate' => __( 'Date', 'xtremeleads' ),
					'flyoutIpAddress' => __( 'IP Address', 'xtremeleads' ),
					'flyoutSourceUrl' => __( 'Source URL', 'xtremeleads' ),
					'flyoutUserAgent' => __( 'User Agent', 'xtremeleads' ),
					'flyoutSubmitted' => __( 'Submitted Data', 'xtremeleads' ),
					'flyoutAssignedTo' => __( 'Assigned To', 'xtremeleads' ),
					'flyoutTags' => __( 'Tags', 'xtremeleads' ),
					'flyoutNa' => __( 'N/A', 'xtremeleads' ),
				'sendingTest' => __( 'Sending…', 'xtremeleads' ),
				'testEmailSent' => __( 'Test email sent.', 'xtremeleads' ),
				'testEmailFailed' => __( 'Test email failed.', 'xtremeleads' ),
				'resendEmail' => __( 'Resend', 'xtremeleads' ),
				'resending' => __( 'Sending…', 'xtremeleads' ),
				'resendSuccess' => __( 'Sent ✓', 'xtremeleads' ),
				'resendFailed' => __( 'Resend failed.', 'xtremeleads' ),
				),
			)
		);
	}

	// ── Page callbacks ────────────────────────────────────────────────────────

	public function page_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-dashboard.php';
	}

	public function page_form_metrics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-form-metrics.php';
	}

	public function page_leads_inbox(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}

		$xl_action = isset( $_GET['xl_action'] ) ? sanitize_text_field( wp_unslash( $_GET['xl_action'] ) ) : '';
		$lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;

		if ( 'view' === $xl_action && $lead_id ) {
			require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-lead-detail.php';
		} else {
			require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-leads-inbox.php';
		}
	}

	public function page_forms(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}

		$action = isset( $_GET['xl_action'] ) ? sanitize_text_field( wp_unslash( $_GET['xl_action'] ) ) : '';
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-form-builder.php';
		} else {
			require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-forms-list.php';
		}
	}

	public function page_tags(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-tags.php';
	}

	public function page_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-settings.php';
	}

	public function page_email_templates(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-email-templates.php';
	}

	public function page_routing_rules(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-routing-rules.php';
	}

	public function page_email_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-email-log.php';
	}

	public function page_webhooks(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-webhooks.php';
	}

	public function page_spam_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-spam-log.php';
	}

	public function page_import_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-import-export.php';
	}

	public function page_audit_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xtremeleads' ) );
		}
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/partials/xl-admin-audit-log.php';
	}

	// ── Form Handlers ─────────────────────────────────────────────────────────

	/**
	 * Handle settings form save.
	 */
	public function handle_save_settings(): void {
		check_admin_referer( 'xl_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		// Preserve duplicate-behavior settings that are managed by a separate form.
		$existing = get_option( 'xtremeleads_settings', array() );

		$settings = array(
			'recipients' => sanitize_text_field( wp_unslash( $_POST['recipients'] ?? '' ) ),
			'anonymize_ip' => isset( $_POST['anonymize_ip'] ) ? '1' : '0',
			'email_from_name' => sanitize_text_field( wp_unslash( $_POST['email_from_name'] ?? '' ) ),
			'email_from' => sanitize_email( wp_unslash( $_POST['email_from'] ?? '' ) ),
			// Preserve duplicate settings so they are not wiped on main-settings save.
			'duplicate_behavior' => $existing['duplicate_behavior'] ?? 'silent_flag',
			'duplicate_block_message' => $existing['duplicate_block_message'] ?? '',
			// Preserve spam and GDPR settings.
			'recaptcha_enabled' => $existing['recaptcha_enabled'] ?? '0',
			'recaptcha_site_key' => $existing['recaptcha_site_key'] ?? '',
			'recaptcha_secret_key' => $existing['recaptcha_secret_key'] ?? '',
			'recaptcha_threshold' => $existing['recaptcha_threshold'] ?? '0.5',
			'spam_domain_blocklist' => $existing['spam_domain_blocklist'] ?? '',
			'spam_keyword_blocklist' => $existing['spam_keyword_blocklist'] ?? '',
			'retention_days' => $existing['retention_days'] ?? '',
		);

		update_option( 'xtremeleads_settings', $settings );

		// Audit log.
		if ( class_exists( 'XL_Audit_Log' ) ) {
			XL_Audit_Log::record( XL_Audit_Log::ACTION_GLOBAL_SETTINGS_UPDATED, 0, array( 'section' => 'main' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'xtremeleads-settings',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle form builder save.
	 */
	public function handle_save_form(): void {
		check_admin_referer( 'xl_save_form' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$name = sanitize_text_field( wp_unslash( $_POST['form_name'] ?? '' ) );

		if ( '' === $name ) {
			$name = __( 'Untitled Form', 'xtremeleads' );
		}

		$raw_fields = isset( $_POST['xl_fields'] )
			? wp_unslash( $_POST['xl_fields'] )
			: '[]';

		$fields = json_decode( $raw_fields, true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		$validation_errors = array();
		$clean_fields = array();

		foreach ( $fields as $idx => $field ) {
			$field_type = sanitize_text_field( $field['type'] ?? 'text' );
			$field_id = sanitize_key( $field['id'] ?? ( 'field_' . ( $idx + 1 ) ) );
			$field_label = sanitize_text_field( $field['label'] ?? '' );
			$field_ph = sanitize_text_field( $field['placeholder'] ?? '' );
			$field_req = ! empty( $field['required'] );
			$field_opts = array();
			$field_dv = sanitize_text_field( $field['default_value'] ?? '' );

			$requires_options = array( 'dropdown', 'checkbox', 'radio' );
			if ( in_array( $field_type, $requires_options, true ) ) {
				$raw_opts = is_array( $field['options'] ?? null ) ? $field['options'] : array();
				$field_opts = array_values( array_filter( array_map( 'sanitize_text_field', $raw_opts ) ) );
				if ( empty( $field_opts ) ) {
					$validation_errors[] = sprintf(
						/* translators: 1: field label, 2: field type */
						__( 'Field "%1$s" (%2$s) must have at least one option.', 'xtremeleads' ),
						$field_label ?: $field_id,
						$field_type
					);
					continue;
				}
			}

			$clean = array(
				'id' => $field_id,
				'type' => $field_type,
				'label' => $field_label,
				'placeholder' => $field_ph,
				'required' => $field_req,
				'default_value' => $field_dv,
			);

			if ( ! empty( $field_opts ) ) {
				$clean['options'] = $field_opts;
			}

			// Preserve conditional logic rules.
			if ( isset( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) ) {
				$raw_cl = $field['conditional_logic'];
				$cl_enabled = ! empty( $raw_cl['enabled'] );
				$cl_logic = in_array( $raw_cl['logic'] ?? 'and', array( 'and', 'or' ), true )
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
						$cond_op = in_array( $cond['operator'] ?? 'equals', $valid_operators, true )
							? $cond['operator']
							: 'equals';
						$cond_value = sanitize_text_field( $cond['value'] ?? '' );
						if ( '' !== $cond_trigger ) {
							$cl_conditions[] = array(
								'triggerFieldId' => $cond_trigger,
								'operator' => $cond_op,
								'value' => $cond_value,
							);
						}
					}
				}

				$clean['conditional_logic'] = array(
					'enabled' => $cl_enabled,
					'logic' => $cl_logic,
					'conditions' => $cl_conditions,
				);
			} else {
				// Preserve a default empty conditional_logic so the JS builder can read it.
				$clean['conditional_logic'] = array(
					'enabled' => false,
					'logic' => 'and',
					'conditions' => array(),
				);
			}

			$clean_fields[] = $clean;
		}

		if ( ! empty( $validation_errors ) ) {
			$transient_key = 'xl_form_errors_' . get_current_user_id();
			set_transient( $transient_key, $validation_errors, 60 );

			$redirect_args = array(
				'page' => 'xtremeleads-forms',
				'xl_action' => $form_id ? 'edit' : 'new',
				'error' => '1',
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
			$transient_key = 'xl_form_errors_' . get_current_user_id();
			set_transient( $transient_key, array( __( 'Auto-responder reply-to address is not a valid email.', 'xtremeleads' ) ), 60 );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'xtremeleads-forms',
						'xl_action' => $form_id ? 'edit' : 'new',
						'form_id' => $form_id ?: null,
						'error' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$form_settings = array(
			'submit_label' => sanitize_text_field( wp_unslash( $_POST['submit_label'] ?? '' ) ),
			'redirect_url' => esc_url_raw( wp_unslash( $_POST['redirect_url'] ?? '' ) ),
			'thank_you_message' => sanitize_textarea_field( wp_unslash( $_POST['thank_you_message'] ?? '' ) ),
			'email_recipients' => sanitize_text_field( wp_unslash( $_POST['email_recipients'] ?? '' ) ),
			// Auto-responder settings.
			'auto_responder_enabled' => isset( $_POST['auto_responder_enabled'] ) ? '1' : '0',
			'auto_responder_subject' => sanitize_text_field( wp_unslash( $_POST['auto_responder_subject'] ?? '' ) ),
			// GDPR consent checkbox.
			'consent_enabled' => isset( $_POST['consent_enabled'] ) ? '1' : '0',
			'consent_label' => sanitize_textarea_field( wp_unslash( $_POST['consent_label'] ?? '' ) ),
			'consent_url' => esc_url_raw( wp_unslash( $_POST['consent_url'] ?? '' ) ),
			// reCAPTCHA per-form.
			'recaptcha_enabled' => isset( $_POST['form_recaptcha_enabled'] ) ? '1' : '0',
			'auto_responder_body' => sanitize_textarea_field( wp_unslash( $_POST['auto_responder_body'] ?? '' ) ),
			'auto_responder_reply_to' => $ar_reply_to,
			// Scheduling.
			'countdown_timer_enabled' => isset( $_POST['countdown_timer_enabled'] ) ? '1' : '0',
			'closed_message' => sanitize_textarea_field( wp_unslash( $_POST['closed_message'] ?? '' ) ),
		);

		// Scheduling datetime values.
		$activate_at_raw = sanitize_text_field( wp_unslash( $_POST['activate_at'] ?? '' ) );
		$expire_at_raw = sanitize_text_field( wp_unslash( $_POST['expire_at'] ?? '' ) );

		// Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL format.
		$activate_at = $this->parse_datetime_local( $activate_at_raw );
		$expire_at = $this->parse_datetime_local( $expire_at_raw );

		if ( $form_id ) {
			XL_Forms::update_form( $form_id, $name, $clean_fields, $form_settings );
			$is_new = false;
		} else {
			$form_id = XL_Forms::create_form( $name, $clean_fields, $form_settings );
			$is_new = true;
		}

		// Save scheduling columns directly (not in settings JSON).
		if ( $form_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'xtremeleads_forms';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'activate_at' => $activate_at ?: null,
					'expire_at' => $expire_at ?: null,
					'closed_message' => $form_settings['closed_message'],
				),
				array( 'id' => $form_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		// Audit log.
		if ( class_exists( 'XL_Audit_Log' ) && $form_id ) {
			XL_Audit_Log::record(
				$is_new ? XL_Audit_Log::ACTION_FORM_CREATED : XL_Audit_Log::ACTION_FORM_UPDATED,
				$form_id,
				array( 'form_name' => $name )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'xtremeleads-forms',
					'xl_action' => 'edit',
					'form_id' => $form_id,
					'updated' => '1',
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

		check_admin_referer( 'xl_delete_form_' . $form_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		if ( $form_id ) {
			$form = XL_Forms::get_form( $form_id ); // Grab name for audit before delete.
			XL_Forms::delete_form( $form_id );
			// Audit log.
			if ( class_exists( 'XL_Audit_Log' ) ) {
				XL_Audit_Log::record(
					XL_Audit_Log::ACTION_FORM_DELETED,
					$form_id,
					array( 'form_name' => $form ? $form->name : '' )
				);
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'xtremeleads-forms',
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
		check_admin_referer( 'xl_bulk_leads' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
		$ids_raw = isset( $_POST['lead_ids'] ) && is_array( $_POST['lead_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['lead_ids'] ) )
			: array();
		$ids = array_filter( $ids_raw );

		// Preserve filter state in redirect.
		$redirect_args = array( 'page' => 'xtremeleads-leads' );
		foreach ( array( 'xl_status', 'xl_form', 'xl_tags', 'xl_date_from', 'xl_date_to', 'xl_filter' ) as $k ) {
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
				$count = XL_Leads::bulk_delete( $ids );
				wp_safe_redirect( add_query_arg( array( 'bulk_deleted' => $count ), $redirect ) );
				break;

			case 'mark_contacted':
				$count = XL_Leads::bulk_update_status( $ids, XL_Leads::STATUS_CONTACTED );
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
		check_admin_referer( 'xl_save_email_template' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$redirect = add_query_arg(
			array( 'page' => 'xtremeleads-email-templates' ),
			admin_url( 'admin.php' )
		);

		// Handle logo upload.
		$logo_url = sanitize_url( wp_unslash( $_POST['xl_logo_url'] ?? '' ) );

		// Check if admin wants to remove existing logo.
		if ( ! empty( $_POST['xl_remove_logo'] ) ) {
			$logo_url = '';
		}

		// Process file upload if a new file was provided.
		if ( ! empty( $_FILES['xl_logo_file']['name'] ) ) {
			$upload_result = $this->handle_logo_upload();

			if ( is_wp_error( $upload_result ) ) {
				set_transient( 'xl_template_error_' . get_current_user_id(), $upload_result->get_error_message(), 30 );
				wp_safe_redirect( add_query_arg( 'logo_error', '1', $redirect ) );
				exit;
			}

			$logo_url = $upload_result;
		}

		$data = array(
			'logo_url' => $logo_url,
			'header_color' => sanitize_text_field( wp_unslash( $_POST['xl_header_color'] ?? '#1A73E8' ) ),
			'subject' => sanitize_text_field( wp_unslash( $_POST['xl_subject'] ?? '' ) ),
			'body_text' => sanitize_textarea_field( wp_unslash( $_POST['xl_body_text'] ?? '' ) ),
			'footer_text' => sanitize_textarea_field( wp_unslash( $_POST['xl_footer_text'] ?? '' ) ),
		);

		XL_Email_Templates::save_template( $data );

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

		$file = $_FILES['xl_logo_file'] ?? null;

		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed. Please try again.', 'xtremeleads' ) );
		}

		// Validate MIME type before uploading.
		$allowed_mime_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
		);

		// Use finfo for reliable MIME type detection (not just extension).
		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mime_type = $finfo->file( $file['tmp_name'] );

		if ( ! in_array( $mime_type, $allowed_mime_types, true ) ) {
			return new \WP_Error(
				'invalid_type',
				__( 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.', 'xtremeleads' )
			);
		}

		// Validate file size (max 2 MB).
		$max_size = 2 * 1024 * 1024; // 2 MB in bytes.
		if ( (int) $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				__( 'File is too large. Maximum allowed size is 2 MB.', 'xtremeleads' )
			);
		}

		// Validate file extension against MIME type (prevent extension spoofing).
		$ext_to_mime = array(
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
		);

		$original_name = $file['name'] ?? '';
		$ext = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! isset( $ext_to_mime[ $ext ] ) || $ext_to_mime[ $ext ] !== $mime_type ) {
			return new \WP_Error(
				'invalid_type',
				__( 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.', 'xtremeleads' )
			);
		}

		$overrides = array(
			'test_form' => false,
			'mimes' => array(
				'jpg|jpeg' => 'image/jpeg',
				'png' => 'image/png',
				'gif' => 'image/gif',
				'webp' => 'image/webp',
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
		check_admin_referer( 'xl_save_routing_rules' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$mode = sanitize_text_field( wp_unslash( $_POST['routing_mode'] ?? 'match_first' ) );
		$raw_rules = isset( $_POST['rules'] ) && is_array( $_POST['rules'] )
			? wp_unslash( $_POST['rules'] )
			: array();

		$clean_rules = array();
		foreach ( $raw_rules as $rule ) {
			$recipient = sanitize_email( $rule['recipient_email'] ?? '' );
			if ( ! is_email( $recipient ) ) {
				continue; // Skip rules with invalid recipients.
			}

			$clean_rules[] = array(
				'condition_type' => sanitize_text_field( $rule['condition_type'] ?? 'form' ),
				'form_id' => absint( $rule['form_id'] ?? 0 ),
				'field_id' => sanitize_text_field( $rule['field_id'] ?? '' ),
				'field_value' => sanitize_text_field( $rule['field_value'] ?? '' ),
				'recipient_email' => $recipient,
				'is_active' => isset( $rule['is_active'] ) ? 1 : 0,
			);
		}

		XL_Routing_Rules::save_all_rules( $clean_rules, $mode );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'xtremeleads-routing-rules',
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
		check_admin_referer( 'xl_save_tag' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['tag_name'] ?? '' ) );
		$result = XL_Tags::create_tag( $name );
		$redirect = add_query_arg( array( 'page' => 'xtremeleads-tags' ), admin_url( 'admin.php' ) );

		if ( is_wp_error( $result ) ) {
			set_transient(
				'xl_tag_error_' . get_current_user_id(),
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

		check_admin_referer( 'xl_delete_tag_' . $tag_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		if ( $tag_id ) {
			XL_Tags::delete_tag( $tag_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'xtremeleads-tags',
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
		check_admin_referer( 'xl_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$settings = get_option( 'xtremeleads_settings', array() );

		// Merge duplicate detection settings.
		$settings['duplicate_behavior'] = sanitize_text_field( wp_unslash( $_POST['duplicate_behavior'] ?? 'silent_flag' ) );
		$settings['duplicate_block_message'] = sanitize_text_field( wp_unslash( $_POST['duplicate_block_message'] ?? '' ) );

		// Validate behavior value.
		$allowed = array( 'silent_flag', 'block', 'merge' );
		if ( ! in_array( $settings['duplicate_behavior'], $allowed, true ) ) {
			$settings['duplicate_behavior'] = 'silent_flag';
		}

		update_option( 'xtremeleads_settings', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'xtremeleads-settings',
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
		check_admin_referer( 'xl_export_leads' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		// Build filters from GET parameters.
		$filters = array();

		if ( ! empty( $_GET['xl_status'] ) ) {
			$filters['status'] = sanitize_text_field( wp_unslash( $_GET['xl_status'] ) );
		}
		if ( ! empty( $_GET['xl_form'] ) ) {
			$filters['form_id'] = absint( $_GET['xl_form'] );
		}
		if ( ! empty( $_GET['xl_date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['xl_date_from'] ) );
		}
		if ( ! empty( $_GET['xl_date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['xl_date_to'] ) );
		}
		if ( ! empty( $_GET['xl_tags'] ) && is_array( $_GET['xl_tags'] ) ) {
			$filters['tag_ids'] = array_map( 'absint', wp_unslash( $_GET['xl_tags'] ) );
		}
		if ( ! empty( $_GET['xl_filter'] ) && 'my_leads' === $_GET['xl_filter'] ) {
			$filters['assigned_to'] = get_current_user_id();
		}

		// Get all matching leads.
		$result = XL_Leads::get_leads_filtered( $filters, 1, 999999 );
		$leads = $result['leads'];

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
			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
			$leads = XL_Leads::get_leads_by_ids( $ids );
		}

		if ( empty( $leads ) ) {
			wp_die( esc_html__( 'No leads to export.', 'xtremeleads' ) );
		}

		// Pre-load forms to get field labels.
		$form_ids = array_unique( array_map( static fn( $l ) => (int) $l->form_id, $leads ) );
		$forms_cache = XL_Forms::get_forms_by_ids( $form_ids );

		// Pre-load tags for all leads.
		$lead_ids = array_map( static fn( $l ) => (int) $l->id, $leads );
		$tags_by_lead = XL_Tags::get_tags_for_leads( $lead_ids );

		// Collect all unique field definitions across all forms (for column headers).
		$all_field_defs = array(); // keyed by field_id => label.
		foreach ( $forms_cache as $form ) {
			$fields = XL_Forms::decode_fields( $form );
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
		$meta_headers = array(
			__( 'Lead ID', 'xtremeleads' ),
			__( 'Date', 'xtremeleads' ),
			__( 'Source Form', 'xtremeleads' ),
			__( 'Source URL', 'xtremeleads' ),
			__( 'IP Address', 'xtremeleads' ),
			__( 'Status', 'xtremeleads' ),
			__( 'Assigned To', 'xtremeleads' ),
			__( 'Tags', 'xtremeleads' ),
		);
		$field_headers = array_values( $all_field_defs );
		$headers = array_merge( $meta_headers, $field_headers );
		$field_keys = array_keys( $all_field_defs );

		// Stream the CSV.
		$filename = 'xtremeleads-export-' . gmdate( 'Y-m-d' ) . '.csv';

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

		$statuses = XL_Leads::get_statuses();

		foreach ( $leads as $lead ) {
			$field_values = XL_Leads::decode_field_values( $lead );
			$form = $forms_cache[ (int) $lead->form_id ] ?? null;
			$form_name = $form ? $form->name : __( '(deleted form)', 'xtremeleads' );

			// Assigned user.
			$assigned_to = (int) ( $lead->assigned_to ?? 0 );
			$assignee_name = '';
			if ( $assigned_to ) {
				$u = get_userdata( $assigned_to );
				$assignee_name = $u ? $u->display_name : '';
			}

			// Tags (semicolon-delimited within cell).
			$lead_tags = $tags_by_lead[ (int) $lead->id ] ?? array();
			$tag_names = array_map( static fn( $t ) => $t->name, $lead_tags );
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

		fclose( $out );
		exit;
	}

	// ── Settings save ───────────────────────────────────────────────

	/**
	 * Handle spam and GDPR settings (reCAPTCHA, spam blocklists, retention, etc.).
	 */
	public function handle_save_settings_s5(): void {
		check_admin_referer( 'xl_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$existing = get_option( 'xtremeleads_settings', array() );

		// reCAPTCHA settings.
		$recaptcha_enabled = isset( $_POST['recaptcha_enabled'] ) ? '1' : '0';
		$recaptcha_site_key = sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ?? '' ) );
		$recaptcha_secret_key = sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ?? '' ) );
		$recaptcha_threshold = isset( $_POST['recaptcha_threshold'] )
			? max( 0.1, min( 0.9, (float) wp_unslash( $_POST['recaptcha_threshold'] ) ) )
			: 0.5;

		// Spam blocklists.
		$spam_domain_blocklist = sanitize_textarea_field( wp_unslash( $_POST['spam_domain_blocklist'] ?? '' ) );
		$spam_keyword_blocklist = sanitize_textarea_field( wp_unslash( $_POST['spam_keyword_blocklist'] ?? '' ) );

		// Data retention.
		// Distinguish between blank (disabled) and an explicit numeric entry.
		$retention_days_post = isset( $_POST['retention_days'] ) ? trim( wp_unslash( (string) $_POST['retention_days'] ) ) : '';
		if ( '' !== $retention_days_post ) {
			// User supplied a value — must be >= 1. 0 and negatives are invalid.
			$retention_days_raw = (int) $retention_days_post;
			if ( $retention_days_raw < 1 ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'xtremeleads-settings',
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
				'recaptcha_enabled' => $recaptcha_enabled,
				'recaptcha_site_key' => $recaptcha_site_key,
				'recaptcha_secret_key' => $recaptcha_secret_key,
				'recaptcha_threshold' => (string) $recaptcha_threshold,
				'spam_domain_blocklist' => $spam_domain_blocklist,
				'spam_keyword_blocklist' => $spam_keyword_blocklist,
			)
		);

		if ( null !== $retention_days ) {
			$settings['retention_days'] = $retention_days;
			update_option( 'xtremeleads_settings', $settings );
			XL_GDPR::schedule_retention_cron();
		} else {
			unset( $settings['retention_days'] );
			update_option( 'xtremeleads_settings', $settings );
			XL_GDPR::unschedule_retention_cron();
		}

		$redirect_args = array(
			'page' => 'xtremeleads-settings',
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
		check_admin_referer( 'xl_export_data' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$export_type = sanitize_key( wp_unslash( $_POST['export_type'] ?? 'full' ) );
		$form_id = absint( $_POST['form_id'] ?? 0 );

		if ( 'form' === $export_type ) {
			if ( ! $form_id ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'xtremeleads-import-export', 'xl_export_error' => '1' ), admin_url( 'admin.php' ) ) );
				exit;
			}
			$data = XL_Import_Export::build_form_export( $form_id );
			if ( is_wp_error( $data ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'xtremeleads-import-export', 'xl_export_error' => '1' ), admin_url( 'admin.php' ) ) );
				exit;
			}
			$filename = 'xtremeleads-form-' . $form_id . '-' . gmdate( 'Y-m-d' ) . '.json';
		} else {
			$data = XL_Import_Export::build_full_export();
			$filename = 'xtremeleads-export-full-' . gmdate( 'Y-m-d' ) . '.json';
		}

		// Audit log.
		XL_Audit_Log::record(
			XL_Audit_Log::ACTION_PLUGIN_DATA_EXPORTED,
			$form_id,
			array( 'export_type' => $export_type )
		);

		XL_Import_Export::stream_json_download( $data, $filename );
	}

	/**
	 * Handle data import from uploaded JSON file.
	 */
	public function handle_import_data(): void {
		check_admin_referer( 'xl_import_data' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtremeleads' ) );
		}

		$transient_key = 'xl_import_result_' . get_current_user_id();
		$redirect = add_query_arg( array( 'page' => 'xtremeleads-import-export' ), admin_url( 'admin.php' ) );

		// Validate file upload.
		if ( empty( $_FILES['xl_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['xl_import_file']['tmp_name'] ) ) {
			set_transient( $transient_key, new WP_Error( 'no_file', __( 'No file uploaded.', 'xtremeleads' ) ), 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		$file_size = (int) ( $_FILES['xl_import_file']['size'] ?? 0 );
		if ( $file_size > 2 * MB_IN_BYTES ) {
			set_transient( $transient_key, new WP_Error( 'file_too_large', __( 'Import file exceeds 2 MB limit.', 'xtremeleads' ) ), 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		$json = file_get_contents( $_FILES['xl_import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $json ) {
			set_transient( $transient_key, new WP_Error( 'read_error', __( 'Could not read the uploaded file.', 'xtremeleads' ) ), 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		$data = XL_Import_Export::parse_import( $json );
		if ( is_wp_error( $data ) ) {
			set_transient( $transient_key, $data, 60 );
			wp_safe_redirect( $redirect );
			exit;
		}

		// Execute import based on type.
		if ( 'form' === ( $data['type'] ?? '' ) ) {
			$result = XL_Import_Export::execute_form_import( $data );
		} else {
			$result = XL_Import_Export::execute_full_import( $data );
		}

		set_transient( $transient_key, $result, 60 );
		wp_safe_redirect( $redirect );
		exit;
	}
}
