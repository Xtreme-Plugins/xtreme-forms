<?php
/**
 * AJAX handlers for form submission and admin actions.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Ajax
 */
// All admin-only AJAX handlers in this class call $this->require_admin_auth() as their
// first operation, which performs authentication, wp_verify_nonce() verification, and
// capability checks before any $_POST/$_GET data is read. PHPCS cannot trace nonce
// verification through custom method calls, so the NonceVerification sniff is suppressed
// class-wide. The public form submission handler uses its own inline nonce check.
// phpcs:disable WordPress.Security.NonceVerification
class XF_Ajax {

	public function __construct() {
		// Public form submission (logged-in and non-logged-in users).
		add_action( 'wp_ajax_xl_submit_form', array( $this, 'handle_form_submit' ) );
		add_action( 'wp_ajax_nopriv_xl_submit_form', array( $this, 'handle_form_submit' ) );

		// Public: server-side redirect after successful submission.
		add_action( 'wp_ajax_xl_do_form_redirect', array( $this, 'handle_do_form_redirect' ) );
		add_action( 'wp_ajax_nopriv_xl_do_form_redirect', array( $this, 'handle_do_form_redirect' ) );

		// Admin: get lead detail (AJAX flyout).
		add_action( 'wp_ajax_xl_get_lead', array( $this, 'handle_get_lead' ) );

		// Admin: bulk actions.
		add_action( 'wp_ajax_xl_bulk_action', array( $this, 'handle_bulk_action' ) );

		// Notes.
		add_action( 'wp_ajax_xl_add_note', array( $this, 'handle_add_note' ) );

		// Status update.
		add_action( 'wp_ajax_xl_update_status', array( $this, 'handle_update_status' ) );

		// Tags.
		add_action( 'wp_ajax_xl_create_tag', array( $this, 'handle_create_tag' ) );
		add_action( 'wp_ajax_xl_search_tags', array( $this, 'handle_search_tags' ) );
		add_action( 'wp_ajax_xl_apply_tag', array( $this, 'handle_apply_tag' ) );
		add_action( 'wp_ajax_xl_remove_tag', array( $this, 'handle_remove_tag' ) );

		// Assignment.
		add_action( 'wp_ajax_xl_assign_lead', array( $this, 'handle_assign_lead' ) );
		add_action( 'wp_ajax_xl_get_eligible_users', array( $this, 'handle_get_eligible_users' ) );

		// Email templates / log.
		add_action( 'wp_ajax_xl_send_test_email', array( $this, 'handle_send_test_email' ) );
		add_action( 'wp_ajax_xl_resend_email', array( $this, 'handle_resend_email' ) );

		// Form impression beacon (public — no login required).
		add_action( 'wp_ajax_xl_track_impression', array( $this, 'handle_track_impression' ) );
		add_action( 'wp_ajax_nopriv_xl_track_impression', array( $this, 'handle_track_impression' ) );

		// Dashboard data endpoints (admin only).
		add_action( 'wp_ajax_xl_dashboard_stats', array( $this, 'handle_dashboard_stats' ) );
		add_action( 'wp_ajax_xl_chart_leads_by_form', array( $this, 'handle_chart_leads_by_form' ) );
		add_action( 'wp_ajax_xl_chart_leads_over_time', array( $this, 'handle_chart_leads_over_time' ) );
		add_action( 'wp_ajax_xl_utm_report', array( $this, 'handle_utm_report' ) );
		add_action( 'wp_ajax_xl_form_metrics', array( $this, 'handle_form_metrics' ) );

		// Admin-facing duplicate email check (admin only).
		add_action( 'wp_ajax_xl_duplicate_check', array( $this, 'handle_duplicate_check' ) );

		// Webhook CRUD + delivery log.
		add_action( 'wp_ajax_xl_webhook_save', array( $this, 'handle_webhook_save' ) );
		add_action( 'wp_ajax_xl_webhook_delete', array( $this, 'handle_webhook_delete' ) );
		add_action( 'wp_ajax_xl_webhook_test', array( $this, 'handle_webhook_test' ) );
		add_action( 'wp_ajax_xl_webhook_log', array( $this, 'handle_webhook_log' ) );
		add_action( 'wp_ajax_xl_webhook_get', array( $this, 'handle_webhook_get' ) );

		// GDPR Right to Erasure.
		add_action( 'wp_ajax_xl_gdpr_erase', array( $this, 'handle_gdpr_erase' ) );

		// Spam log actions.
		add_action( 'wp_ajax_xl_spam_log_get', array( $this, 'handle_spam_log_get' ) );
		add_action( 'wp_ajax_xl_spam_log_delete', array( $this, 'handle_spam_log_delete' ) );
		add_action( 'wp_ajax_xl_spam_log_clear', array( $this, 'handle_spam_log_clear' ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Shared helpers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Shared security gate for all authenticated admin-only AJAX endpoints.
	 *
	 * Security layers (applied in order):
	 * 1. Authentication check — unauthenticated (logged-out) requests receive HTTP 401.
	 * 2. Nonce validation — missing or invalid nonce receives HTTP 403.
	 * 3. Capability check — insufficient privilege receives HTTP 403.
	 *
	 * This explicit 3-layer check ensures that direct HTTP requests from unauthenticated
	 * users always receive HTTP 401 (not WordPress's default −1/400) and that
	 * authenticated users without the required capability receive HTTP 403.
	 *
	 * @param string $nonce_action The nonce action string to verify against.
	 * @param string $capability WordPress capability required (default: 'manage_options').
	 * @return void Sends JSON error and exits on any security failure.
	 */
	private function require_admin_auth( string $nonce_action, string $capability = 'manage_options' ): void {
		// Layer 1: Authentication — return 401 for logged-out users.
		if ( ! is_user_logged_in() ) {
			status_header( 401 );
			wp_send_json_error(
				array(
					'code'    => 'not_authenticated',
					'message' => __( 'Authentication required.', 'xtreme-forms' ),
				),
				401
			);
		}

		// Layer 2: Nonce validation — return 403 for missing/invalid nonces.
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: ( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '' );

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			status_header( 403 );
			wp_send_json_error(
				array(
					'code'    => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'xtreme-forms' ),
				),
				403
			);
		}

		// Layer 3: Capability check — return 403 for insufficient privilege.
		if ( ! current_user_can( $capability ) ) {
			status_header( 403 );
			wp_send_json_error(
				array(
					'code'    => 'insufficient_permissions',
					'message' => __( 'You do not have permission to perform this action.', 'xtreme-forms' ),
				),
				403
			);
		}
	}

	/**
	 * Verify nonce and capability for admin-only analytics endpoints.
	 * Requires 'manage_options' capability.
	 *
	 * @param string $nonce_action The specific nonce action name for this endpoint.
	 * @return void Sends JSON error and exits on failure.
	 */
	private function check_analytics_ajax( string $nonce_action ): void {
		$this->require_admin_auth( $nonce_action );
	}

	/**
	 * Verify nonce and capability for admin-only AJAX endpoints.
	 *
	 * @param string $nonce_action Nonce action name specific to the endpoint being called.
	 * @return void Sends JSON error with HTTP 401/403 and exits on any security failure.
	 */
	private function check_ajax_auth( string $nonce_action ): void {
		$this->require_admin_auth( $nonce_action );
	}

	/**
	 * Verify nonce and capability for admin-only actions.
	 * Requires 'manage_options' capability to ensure only site admins can manage leads.
	 *
	 * @return void Sends JSON error and exits on failure.
	 */
	private function check_admin_ajax(): void {
		$this->require_admin_auth( 'xf_admin_nonce' );
	}

	// ── Public Form Submission ──────────────────────────────────────────────

	/**
	 * Handle public form submission via AJAX.
	 *
	 * Handles UTM parameter capture, duplicate lead detection, and submit
	 * duration tracking for form performance metrics.
	 */
	public function handle_form_submit(): void {
		// Verify nonce.
		$form_id = isset( $_POST['xf_form_id'] ) ? absint( $_POST['xf_form_id'] ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form.', 'xtreme-forms' ) ), 400 );
		}

		$nonce = isset( $_POST['xf_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['xf_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'xf_form_submit_' . $form_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'xtreme-forms' ) ), 403 );
		}

		// ── Rate limiting via transients ───────────────────────────
		// Limit each IP to 10 submissions per 10 minutes across all forms.
		$visitor_ip_rl = $this->get_visitor_ip();
		// Hash the IP so we don't store raw IPs in option names.
		$ip_key   = 'xf_rl_' . md5( $visitor_ip_rl . '_' . $form_id );
		$rl_count = (int) get_transient( $ip_key );
		$rl_limit = apply_filters( 'xtremeforms_rate_limit_per_form', 10 );

		if ( $rl_count >= $rl_limit ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many submissions. Please wait a moment before trying again.', 'xtreme-forms' ) ),
				429
			);
		}

		// Increment counter. Transient expires in 10 minutes (600 seconds).
		set_transient( $ip_key, $rl_count + 1, 600 );

		// ── Gather common metadata (needed for spam logging) ──────────────────
		$source_url_early = isset( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			: ( isset( $_POST['xf_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['xf_source_url'] ) ) : '' );
		$ip_early         = $this->get_visitor_ip();
		$ip_early         = XF_Leads::maybe_anonymize_ip( $ip_early );
		$ua_early         = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
			: '';
		$email_early      = isset( $_POST['xf_field']['email'] )
			? sanitize_email( wp_unslash( $_POST['xf_field']['email'] ) )
			: '';

		// ── Spam protection ────────────────────────────────────────────────────

		// Honeypot check: the xf_website_url field must be blank.
		// Fail silently — return a success-like response to avoid revealing
		// spam detection to bots. Log in spam_log table.
		$honeypot_value = isset( $_POST['xf_website_url'] )
			? sanitize_text_field( wp_unslash( $_POST['xf_website_url'] ) )
			: '';
		if ( '' !== $honeypot_value ) {
			if ( class_exists( 'XF_Spam' ) ) {
				XF_Spam::log_blocked( $form_id, XF_Spam::REASON_HONEYPOT, $email_early, $source_url_early, $ua_early, $ip_early );
			}
			// Silent success — bots must not learn they were blocked.
			wp_send_json_success(
				array(
					'message'  => __( 'Thank you for your submission!', 'xtreme-forms' ),
					'redirect' => '',
					'spam'     => true,
				)
			);
		}

		// Time-gate check: reject submissions under 2 seconds from form render.
		$form_time = isset( $_POST['xf_form_time'] ) ? absint( $_POST['xf_form_time'] ) : 0;
		if ( $form_time > 0 && ( time() - $form_time ) < 2 ) {
			if ( class_exists( 'XF_Spam' ) ) {
				XF_Spam::log_blocked( $form_id, XF_Spam::REASON_TIMEGATE, $email_early, $source_url_early, $ua_early, $ip_early );
			}
			wp_send_json_success(
				array(
					'message'  => __( 'Thank you for your submission!', 'xtreme-forms' ),
					'redirect' => '',
					'spam'     => true,
				)
			);
		}

		// ── End early spam checks — load form now ──────────────────────────────

		// Load the form.
		$form = XF_Forms::get_form( $form_id );
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'xtreme-forms' ) ), 404 );
		}

		$fields   = XF_Forms::decode_fields( $form );
		$settings = XF_Forms::decode_settings( $form );

		// ── reCAPTCHA v3 check ────────────────────────────────────────────────
		if ( class_exists( 'XF_Spam' ) && XF_Spam::is_recaptcha_enabled_for_form( $settings ) ) {
			$recaptcha_token = isset( $_POST['xf_recaptcha_token'] )
				? sanitize_text_field( wp_unslash( $_POST['xf_recaptcha_token'] ) )
				: '';
			$rc_settings     = XF_Spam::get_recaptcha_settings();
			$rc_result       = XF_Spam::verify_recaptcha( $recaptcha_token, $rc_settings['threshold'], $rc_settings['secret_key'] );

			if ( ! $rc_result['success'] && ! $rc_result['api_failed'] ) {
				XF_Spam::log_blocked( $form_id, XF_Spam::REASON_RECAPTCHA, $email_early, $source_url_early, $ua_early, $ip_early );
				wp_send_json_success(
					array(
						'message'  => __( 'Thank you for your submission!', 'xtreme-forms' ),
						'redirect' => '',
						'spam'     => true,
					)
				);
			}

			// If api_failed = true: allow through per spec ("falls back to allowing the
			// submission through and logs a warning rather than blocking all submissions").
			// Log to PHP error log AND record in spam log so admins can review API issues.
			if ( $rc_result['api_failed'] ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					'Xtreme Forms: reCAPTCHA v3 API verification failed for form #' . $form_id .
					' — allowing submission through. Error: ' . ( $rc_result['error'] ?? 'unknown' )
				);
				// Also store a spam-log warning entry so admins can see API failures in the UI.
				if ( class_exists( 'XF_Spam' ) ) {
					XF_Spam::log_blocked(
						$form_id,
						XF_Spam::REASON_RECAPTCHA_API_WARN,
						$email_early,
						$source_url_early,
						$ua_early,
						$ip_early
					);
				}
			}
		}

		// Retrieve raw submitted values.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_fields = isset( $_POST['xf_field'] ) && is_array( $_POST['xf_field'] )
			? wp_unslash( $_POST['xf_field'] )
			: array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Source URL.
		$source_url = isset( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			: '';
		if ( ! $source_url && isset( $_POST['xf_source_url'] ) ) {
			$source_url = esc_url_raw( wp_unslash( $_POST['xf_source_url'] ) );
		}

		// Validate fields.
		$errors       = array();
		$field_values = array();

		foreach ( $fields as $field ) {
			$fid      = $field['id'] ?? '';
			$ftype    = $field['type'] ?? 'text';
			$required = ! empty( $field['required'] );
			$raw      = $raw_fields[ $fid ] ?? null;

			if ( 'hidden' === $ftype ) {
				$field_values[ $fid ] = sanitize_text_field( $field['default_value'] ?? '' );
				continue;
			}

			// Sanitize value.
			if ( is_array( $raw ) ) {
				$sanitized = array_map( 'sanitize_text_field', $raw );
			} elseif ( 'textarea' === $ftype ) {
				$sanitized = sanitize_textarea_field( (string) $raw );
			} else {
				$sanitized = sanitize_text_field( (string) $raw );
			}

			// Required check.
			if ( $required ) {
				$is_empty = is_array( $sanitized ) ? empty( $sanitized ) : ( '' === trim( $sanitized ) );
				if ( $is_empty ) {
					/* translators: %s: field label */
					$errors[ $fid ] = sprintf( __( '%s is required.', 'xtreme-forms' ), $field['label'] ?? $fid );
					continue;
				}
			}

			// Type-specific validation.
			if ( 'email' === $ftype && '' !== trim( (string) $sanitized ) ) {
				if ( ! is_email( $sanitized ) ) {
					$errors[ $fid ] = __( 'Please enter a valid email address.', 'xtreme-forms' );
					continue;
				}
				$sanitized = sanitize_email( $sanitized );
			}

			if ( 'phone' === $ftype && '' !== trim( (string) $sanitized ) ) {
				if ( ! preg_match( '/^[0-9\s\+\-\(\)]+$/', $sanitized ) ) {
					$errors[ $fid ] = __( 'Please enter a valid phone number.', 'xtreme-forms' );
					continue;
				}
			}

			$field_values[ $fid ] = $sanitized;
		}

		// ── Consent checkbox validation (server-side) ───────────────
		// Must validate independently of client-side HTML validation.
		if ( ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'] ) {
			$consent_given = isset( $_POST['xf_consent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['xf_consent'] ) );
			if ( ! $consent_given ) {
				$errors['_consent'] = __( 'You must accept the consent checkbox to submit this form.', 'xtreme-forms' );
			}
		}

		// ── Blocklist checks ─────────────────────────────────────────
		// Done after field parsing so we have sanitised values.
		if ( class_exists( 'XF_Spam' ) && empty( $errors ) ) {
			// Email domain blocklist.
			$email_for_blocklist = '';
			foreach ( $fields as $fl ) {
				if ( ( $fl['type'] ?? '' ) === 'email' ) {
					$email_for_blocklist = $field_values[ $fl['id'] ?? '' ] ?? '';
					if ( '' !== $email_for_blocklist ) {
						break;
					}
				}
			}
			if ( '' !== $email_for_blocklist && XF_Spam::is_domain_blocked( $email_for_blocklist ) ) {
				XF_Spam::log_blocked( $form_id, XF_Spam::REASON_BLOCKLIST, $email_for_blocklist, $source_url_early, $ua_early, $ip_early );
				// Silent success — don't reveal blocklist to submitter.
				wp_send_json_success(
					array(
						'message'  => __( 'Thank you for your submission!', 'xtreme-forms' ),
						'redirect' => '',
						'spam'     => true,
					)
				);
			}

			// Keyword blocklist.
			if ( XF_Spam::has_blocked_keyword( $field_values ) ) {
				XF_Spam::log_blocked( $form_id, XF_Spam::REASON_BLOCKLIST, $email_for_blocklist, $source_url_early, $ua_early, $ip_early );
				wp_send_json_success(
					array(
						'message'  => __( 'Thank you for your submission!', 'xtreme-forms' ),
						'redirect' => '',
						'spam'     => true,
					)
				);
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please correct the errors below.', 'xtreme-forms' ),
					'errors'  => $errors,
				),
				422
			);
		}

		// Determine IP address.
		$ip = $this->get_visitor_ip();
		$ip = XF_Leads::maybe_anonymize_ip( $ip );

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
			: '';

		// ── UTM parameter capture ──────────────────────────────────
		// Extract from the source URL first.
		$utm_from_url = XF_UTM::extract_from_url( $source_url );

		// Cookie fallback: the front-end sets an xf_utm cookie with UTM data.
		$utm_cookie_json = isset( $_POST['xf_utm_cookie'] )
			? sanitize_text_field( wp_unslash( $_POST['xf_utm_cookie'] ) )
			: '';
		$utm_from_cookie = XF_UTM::extract_from_cookie( $utm_cookie_json );

		// Merge: URL params take precedence; cookie fills in gaps.
		$utm_data = XF_UTM::merge_with_fallback( $utm_from_url, $utm_from_cookie );

		// ── Submit duration (time-to-submit in seconds) ─────────────
		$submit_duration = isset( $_POST['xf_submit_duration'] )
			? absint( $_POST['xf_submit_duration'] )
			: null;
		if ( 0 === $submit_duration ) {
			$submit_duration = null; // 0 is not a valid duration.
		}

		// ── Duplicate lead detection ───────────────────────────────
		// Extract email from submitted field values for duplicate check.
		$submitted_email  = XF_Duplicates::extract_email( $field_values, $fields );
		$duplicate_result = null;
		$lock_acquired    = false;

		if ( $submitted_email ) {
			// Acquire per-email advisory lock to prevent concurrent duplicate
			// submissions for the same email both passing the check before
			// either record is written (see XF_Duplicates class for full docs).
			$lock_acquired = XF_Duplicates::acquire_lock( $submitted_email );

			$original_lead = XF_Duplicates::find_original_by_email( $submitted_email );

			if ( $original_lead ) {
				$behavior         = XF_Duplicates::get_behavior();
				$duplicate_result = XF_Duplicates::handle( $original_lead, $field_values, $behavior );

				if ( $duplicate_result['blocked'] ) {
					// Release lock before returning.
					if ( $lock_acquired ) {
						XF_Duplicates::release_lock( $submitted_email );
					}
					// Block mode: HTTP 200 with JSON error payload so the JS
					// _handleError() path displays the message in the form's
					// existing error container — the form is NOT replaced.
					wp_send_json_error(
						array(
							'message'           => $duplicate_result['message'],
							'duplicate_blocked' => true,
						)
					);
					// wp_send_json_error exits, but PHP requires an explicit return for clarity.
					return;
				}

				if ( $duplicate_result['merged'] ) {
					// Merge mode: no new record, just update original.
					if ( $lock_acquired ) {
						XF_Duplicates::release_lock( $submitted_email );
					}

					$redirect_url = ! empty( $settings['redirect_url'] )
						? esc_url_raw( $settings['redirect_url'] )
						: '';

					$thank_you = ! empty( $settings['thank_you_message'] )
						? wp_kses_post( $settings['thank_you_message'] )
						: esc_html__( 'Thank you! Your submission has been received.', 'xtreme-forms' );

					$redirect_nonce = $redirect_url ? wp_create_nonce( 'xf_form_redirect_' . $form_id ) : '';

					wp_send_json_success(
						array(
							'lead_id'        => (int) $original_lead->id,
							'redirect_url'   => $redirect_url,
							'redirect_nonce' => $redirect_nonce,
							'form_id'        => $form_id,
							'thank_you'      => $thank_you,
						)
					);
				}
			}
		}

		// ── Prepare duplicate flags for the INSERT ────────────────────────────
		// Per spec criterion duplicate_detection_on_submission:
		// "the duplicate flag and the foreign-key reference to the original lead ID
		// must be set on the new record before it is written, ensuring they are
		// never saved without the flag."
		// We achieve this by passing the flag fields directly into insert_lead()
		// so they are part of the INSERT statement — not a subsequent UPDATE.
		$insert_is_duplicate     = false;
		$insert_duplicate_status = null;
		$insert_original_lead_id = null;

		if ( null !== $duplicate_result && ! $duplicate_result['blocked'] && ! $duplicate_result['merged'] ) {
			// Silent-flag mode: mark as duplicate in the INSERT itself.
			$insert_is_duplicate = true;
			$orig_id_candidate   = $duplicate_result['original_lead_id'] ?? null;

			// Verify the original lead still exists (orphan check).
			if ( $orig_id_candidate ) {
				$still_exists = XF_Leads::get_lead( (int) $orig_id_candidate );
				if ( $still_exists ) {
					$insert_duplicate_status = 'duplicate';
					$insert_original_lead_id = (int) $orig_id_candidate;
				} else {
					// Original was deleted between find and insert — orphaned duplicate.
					$insert_duplicate_status = 'duplicate_orphaned';
					$insert_original_lead_id = null;
				}
			} else {
				$insert_duplicate_status = 'duplicate_orphaned';
				$insert_original_lead_id = null;
			}
		}

		// ── Consent status for lead record ──────────────────────────
		$consent_given_val = 0;
		if ( ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'] ) {
			$consent_given_val = ( isset( $_POST['xf_consent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['xf_consent'] ) ) ) ? 1 : 0;
		}

		// Insert lead (duplicate flags included in the INSERT statement).
		// email_address is stored in its own indexed column for fast duplicate lookups.
		$lead_id = XF_Leads::insert_lead(
			array(
				'form_id'                 => $form_id,
				'source_url'              => $source_url,
				'ip_address'              => $ip,
				'user_agent'              => $user_agent,
				'field_values'            => $field_values,
				'email_address'           => $submitted_email, // Indexed column for fast duplicate checks.
				'utm_source'              => $utm_data['utm_source'] ?? null,
				'utm_medium'              => $utm_data['utm_medium'] ?? null,
				'utm_campaign'            => $utm_data['utm_campaign'] ?? null,
				'utm_term'                => $utm_data['utm_term'] ?? null,
				'utm_content'             => $utm_data['utm_content'] ?? null,
				'submit_duration_seconds' => $submit_duration,
				'is_duplicate'            => $insert_is_duplicate,
				'duplicate_status'        => $insert_duplicate_status,
				'original_lead_id'        => $insert_original_lead_id,
				'consent_given'           => $consent_given_val,
			)
		);

		if ( false === $lead_id ) {
			if ( $lock_acquired && $submitted_email ) {
				XF_Duplicates::release_lock( $submitted_email );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Xtreme Forms: Failed to insert lead for form #' . $form_id );
			wp_send_json_error(
				array( 'message' => __( 'We were unable to save your submission. Please try again later.', 'xtreme-forms' ) ),
				500
			);
		}

		// Release per-email advisory lock.
		if ( $lock_acquired && $submitted_email ) {
			XF_Duplicates::release_lock( $submitted_email );
		}

		// Log lead created activity.
		XF_Activity::log(
			$lead_id,
			0,
			XF_Activity::TYPE_LEAD_CREATED,
			array( 'form_name' => $form->name ?? '' )
		);

		// Augment settings with form metadata for routing/auto-responder.
		$settings['_form_id']   = $form_id;
		$settings['_form_name'] = $form->name ?? '';

		// Build the lead object stub for context.
		$lead_obj = (object) array(
			'id'         => $lead_id,
			'source_url' => $source_url,
			'created_at' => current_time( 'mysql' ),
		);

		// Send email notification.
		XF_Email::send_new_lead_notification(
			$lead_id,
			$field_values,
			$fields,
			$settings,
			$source_url,
			$lead_obj
		);

		// ── Fire webhooks for new lead event ─────────────────────
		if ( class_exists( 'XF_Webhooks' ) ) {
			$webhook_payload = XF_Webhooks::build_payload(
				$lead_id,
				$field_values,
				$source_url,
				$ip,
				current_time( 'c' )
			);
			XF_Webhooks::fire_event( XF_Webhooks::EVENT_NEW_LEAD, $lead_id, $webhook_payload, $form_id );
		}

		// Build success response.
		$redirect_url = ! empty( $settings['redirect_url'] )
			? esc_url_raw( $settings['redirect_url'] )
			: '';

		$thank_you = ! empty( $settings['thank_you_message'] )
			? wp_kses_post( $settings['thank_you_message'] )
			: esc_html__( 'Thank you! Your submission has been received.', 'xtreme-forms' );

		$redirect_nonce = $redirect_url ? wp_create_nonce( 'xf_form_redirect_' . $form_id ) : '';

		wp_send_json_success(
			array(
				'lead_id'        => $lead_id,
				'redirect_url'   => $redirect_url,
				'redirect_nonce' => $redirect_nonce,
				'form_id'        => $form_id,
				'thank_you'      => $thank_you,
			)
		);
	}

	// ── Public: Server-side form redirect ──────────────────────────────────

	/**
	 * Perform a server-side wp_safe_redirect() after a successful form submission.
	 */
	public function handle_do_form_redirect(): void {
		$form_id = isset( $_POST['xf_form_id'] ) ? absint( $_POST['xf_form_id'] ) : 0;

		if ( ! $form_id ) {
			wp_die( esc_html__( 'Invalid form.', 'xtreme-forms' ) );
		}

		$nonce = isset( $_POST['xf_redirect_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['xf_redirect_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'xf_form_redirect_' . $form_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'xtreme-forms' ) );
		}

		$form = XF_Forms::get_form( $form_id );
		if ( ! $form ) {
			wp_die( esc_html__( 'Form not found.', 'xtreme-forms' ) );
		}

		$settings     = XF_Forms::decode_settings( $form );
		$redirect_url = ! empty( $settings['redirect_url'] ) ? $settings['redirect_url'] : '';

		if ( $redirect_url ) {
			wp_safe_redirect( $redirect_url );
		} else {
			wp_safe_redirect( home_url( '/' ) );
		}
		exit;
	}

	/**
	 * Get the visitor's IP address.
	 *
	 * @return string
	 */
	private function get_visitor_ip(): string {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}

	// ── Admin: Lead Detail ─────────────────────────────────────────────────

	/**
	 * Return full lead detail data as JSON (includes notes, activity, tags).
	 */
	public function handle_get_lead(): void {
		$this->check_admin_ajax();

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		if ( ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lead ID.', 'xtreme-forms' ) ), 400 );
		}

		$lead = XF_Leads::get_lead( $lead_id );
		if ( ! $lead ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found. It may have been deleted.', 'xtreme-forms' ) ), 404 );
		}

		// Get form for field labels.
		$field_values       = XF_Leads::decode_field_values( $lead );
		$form               = XF_Forms::get_form( (int) $lead->form_id );
		$fields_with_labels = array();

		if ( $form ) {
			$field_defs = XF_Forms::decode_fields( $form );
			foreach ( $field_defs as $fd ) {
				$fid   = $fd['id'] ?? '';
				$ftype = $fd['type'] ?? 'text';
				if ( 'hidden' === $ftype ) {
					continue;
				}
				$label = $fd['label'] ?? $fid;
				$val   = array_key_exists( $fid, $field_values ) ? $field_values[ $fid ] : null;
				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}
				$fields_with_labels[] = array(
					'label'    => (string) $label,
					'value'    => null !== $val ? (string) $val : null,
					'is_empty' => ( null === $val || '' === (string) $val ),
				);
			}
		} else {
			foreach ( $field_values as $key => $val ) {
				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}
				$fields_with_labels[] = array(
					'label'    => $key,
					'value'    => (string) $val,
					'is_empty' => '' === (string) $val,
				);
			}
		}

		$statuses     = XF_Leads::get_statuses();
		$status_label = $statuses[ $lead->status ] ?? ucfirst( $lead->status );

		// Assignee info.
		$assigned_to       = (int) ( $lead->assigned_to ?? 0 );
		$assignee_name     = __( 'Unassigned', 'xtreme-forms' );
		$assignee_no_email = false;
		if ( $assigned_to ) {
			$assignee = get_userdata( $assigned_to );
			if ( $assignee ) {
				$assignee_name     = $assignee->display_name;
				$assignee_no_email = empty( $assignee->user_email );
			}
		}

		// Tags.
		$tags      = XF_Tags::get_tags_for_lead( $lead_id );
		$tags_data = array_map(
			static function ( $t ) {
				return array(
					'id'   => (int) $t->id,
					'name' => (string) $t->name,
				);
			},
			$tags
		);

		// Notes (oldest first).
		$notes_raw  = XF_Notes::get_notes_for_lead( $lead_id );
		$notes_data = array_map(
			static function ( $n ) {
				return array(
					'id'          => (int) $n->id,
					'content'     => (string) $n->note_content,
					'author_name' => (string) $n->author_name,
					'created_at'  => (string) $n->created_at,
				);
			},
			$notes_raw
		);

		// Activity (oldest first).
		$activity_raw  = XF_Activity::get_activity_for_lead( $lead_id );
		$activity_data = array_map(
			static function ( $a ) {
				return array(
					'id'          => (int) $a->id,
					'action_type' => (string) $a->action_type,
					'label'       => (string) $a->label,
					'user_name'   => (string) $a->user_name,
					'created_at'  => (string) $a->created_at,
				);
			},
			$activity_raw
		);

		// Eligible users for assignment.
		$eligible_users = XF_Leads::get_eligible_assignees();

		wp_send_json_success(
			array(
				'id'             => (int) $lead->id,
				'form_id'        => (int) $lead->form_id,
				'form_name'      => $form ? (string) $form->name : __( '(deleted form)', 'xtreme-forms' ),
				'status'         => (string) $lead->status,
				'status_label'   => (string) $status_label,
				'statuses'       => $statuses,
				'source_url'     => (string) $lead->source_url,
				'ip_address'     => (string) $lead->ip_address,
				'user_agent'     => (string) $lead->user_agent,
				'created_at'     => (string) $lead->created_at,
				'consent_given'  => isset( $lead->consent_given ) ? (bool) $lead->consent_given : null,
				'fields'         => $fields_with_labels,
				'assigned_to'    => $assigned_to,
				'assignee_name'  => $assignee_name,
				'eligible_users' => $eligible_users,
				'tags'           => $tags_data,
				'notes'          => $notes_data,
				'activity'       => $activity_data,
			)
		);
	}

	// ── Admin: Bulk Actions ─────────────────────────────────────────────────

	/**
	 * Handle bulk actions from the leads inbox.
	 */
	public function handle_bulk_action(): void {
		$this->check_admin_ajax();

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids_raw = isset( $_POST['lead_ids'] ) && is_array( $_POST['lead_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['lead_ids'] ) )
			: array();

		$ids = array_filter( $ids_raw );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No items selected.', 'xtreme-forms' ) ), 422 );
		}

		switch ( $action ) {
			case 'delete':
				// Audit log one entry per deleted lead.
				if ( class_exists( 'XF_Audit_Log' ) ) {
					foreach ( $ids as $del_id ) {
						XF_Audit_Log::record(
							XF_Audit_Log::ACTION_LEAD_DATA_DELETED,
							(int) $del_id,
							array( 'method' => 'bulk_delete' )
						);
					}
				}
				$count = XF_Leads::bulk_delete( $ids );
				wp_send_json_success(
					array(
						/* translators: %d: number of deleted leads */
						'message' => sprintf( _n( '%d lead deleted.', '%d leads deleted.', $count, 'xtreme-forms' ), $count ),
						'count'   => $count,
					)
				);
				break;

			case 'mark_contacted':
				$count = XF_Leads::bulk_update_status( $ids, XF_Leads::STATUS_CONTACTED );
				wp_send_json_success(
					array(
						/* translators: %d: number of updated leads */
						'message' => sprintf( _n( '%d lead marked as contacted.', '%d leads marked as contacted.', $count, 'xtreme-forms' ), $count ),
						'count'   => $count,
					)
				);
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'xtreme-forms' ) ), 400 );
		}
	}

	// ── Notes ─────────────────────────────────────────────────────

	/**
	 * Add a note to a lead.
	 */
	public function handle_add_note(): void {
		$this->check_admin_ajax();

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		if ( ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lead ID.', 'xtreme-forms' ) ), 400 );
		}

		$lead = XF_Leads::get_lead( $lead_id );
		if ( ! $lead ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found.', 'xtreme-forms' ) ), 404 );
		}

		$content = isset( $_POST['note_content'] )
			? sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) )
			: '';

		// Validate: no empty or whitespace-only notes.
		if ( '' === trim( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Note content cannot be empty.', 'xtreme-forms' ) ), 422 );
		}

		$author_id = get_current_user_id();
		$result    = XF_Notes::insert_note( $lead_id, $author_id, $content );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		// Log activity.
		XF_Activity::log(
			$lead_id,
			$author_id,
			XF_Activity::TYPE_NOTE_ADDED,
			array( 'note_id' => $result )
		);

		// Audit log.
		if ( class_exists( 'XF_Audit_Log' ) ) {
			XF_Audit_Log::record( XF_Audit_Log::ACTION_LEAD_NOTE_ADDED, $lead_id );
		}

		$author = get_userdata( $author_id );

		wp_send_json_success(
			array(
				'note' => array(
					'id'          => $result,
					'content'     => sanitize_textarea_field( $content ),
					'author_name' => $author ? $author->display_name : __( 'Unknown', 'xtreme-forms' ),
					'created_at'  => current_time( 'mysql', true ),
				),
			)
		);
	}

	// ── Status Update ──────────────────────────────────────────────

	/**
	 * Update a lead's status.
	 */
	public function handle_update_status(): void {
		$this->check_admin_ajax();

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		if ( ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lead ID.', 'xtreme-forms' ) ), 400 );
		}

		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$statuses   = XF_Leads::get_statuses();

		if ( ! array_key_exists( $new_status, $statuses ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'xtreme-forms' ) ), 400 );
		}

		$old_status = XF_Leads::update_status( $lead_id, $new_status );

		if ( false === $old_status ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'xtreme-forms' ) ), 500 );
		}

		$user_id = get_current_user_id();

		// Log activity.
		XF_Activity::log(
			$lead_id,
			$user_id,
			XF_Activity::TYPE_STATUS_CHANGE,
			array(
				'from'       => $old_status,
				'to'         => $new_status,
				'from_label' => $statuses[ $old_status ] ?? ucfirst( $old_status ),
				'to_label'   => $statuses[ $new_status ] ?? ucfirst( $new_status ),
			)
		);

		// Audit log.
		if ( class_exists( 'XF_Audit_Log' ) ) {
			XF_Audit_Log::record(
				XF_Audit_Log::ACTION_LEAD_STATUS_CHANGED,
				$lead_id,
				array(
					'from' => $old_status,
					'to'   => $new_status,
				)
			);
		}

		// ── Fire webhooks for status change event ───────────────
		if ( class_exists( 'XF_Webhooks' ) ) {
			$lead_obj_sc = XF_Leads::get_lead( $lead_id );
			if ( $lead_obj_sc ) {
				$fv_sc                            = XF_Leads::decode_field_values( $lead_obj_sc );
				$webhook_payload_sc               = XF_Webhooks::build_payload(
					$lead_id,
					$fv_sc,
					(string) $lead_obj_sc->source_url,
					(string) $lead_obj_sc->ip_address,
					(string) $lead_obj_sc->created_at
				);
				$webhook_payload_sc['old_status'] = $old_status;
				$webhook_payload_sc['new_status'] = $new_status;
				XF_Webhooks::fire_event(
					XF_Webhooks::EVENT_STATUS_CHANGE,
					$lead_id,
					$webhook_payload_sc,
					(int) $lead_obj_sc->form_id
				);
			}
		}

		wp_send_json_success(
			array(
				'status'       => $new_status,
				'status_label' => $statuses[ $new_status ],
			)
		);
	}

	// ── Tags ─────────────────────────────────────────────────────

	/**
	 * Create a new tag.
	 */
	public function handle_create_tag(): void {
		$this->check_admin_ajax();

		$name = isset( $_POST['name'] )
			? sanitize_text_field( wp_unslash( $_POST['name'] ) )
			: '';

		$result = XF_Tags::create_tag( $name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		$tag = XF_Tags::get_tag( $result );
		wp_send_json_success(
			array(
				'tag' => array(
					'id'   => (int) $tag->id,
					'name' => esc_html( $tag->name ),
				),
			)
		);
	}

	/**
	 * Search tags by name (autocomplete).
	 */
	public function handle_search_tags(): void {
		$this->check_admin_ajax();

		$query = isset( $_POST['query'] )
			? sanitize_text_field( wp_unslash( $_POST['query'] ) )
			: '';

		$tags      = XF_Tags::search_tags( $query );
		$tags_data = array_map(
			static function ( $t ) {
				return array(
					'id'   => (int) $t->id,
					'name' => (string) $t->name,
				);
			},
			$tags
		);

		wp_send_json_success( array( 'tags' => $tags_data ) );
	}

	/**
	 * Apply a tag to a lead.
	 */
	public function handle_apply_tag(): void {
		$this->check_admin_ajax();

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$tag_id  = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;

		if ( ! $lead_id || ! $tag_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'xtreme-forms' ) ), 400 );
		}

		// Verify lead exists.
		$lead = XF_Leads::get_lead( $lead_id );
		if ( ! $lead ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found.', 'xtreme-forms' ) ), 404 );
		}

		// Verify tag exists.
		$tag = XF_Tags::get_tag( $tag_id );
		if ( ! $tag ) {
			wp_send_json_error( array( 'message' => __( 'Tag not found.', 'xtreme-forms' ) ), 404 );
		}

		XF_Tags::apply_tag_to_lead( $lead_id, $tag_id );

		// Log activity.
		XF_Activity::log(
			$lead_id,
			get_current_user_id(),
			XF_Activity::TYPE_TAG_ADDED,
			array(
				'tag_id'   => $tag_id,
				'tag_name' => $tag->name,
			)
		);

		wp_send_json_success(
			array(
				'tag' => array(
					'id'   => (int) $tag->id,
					'name' => (string) $tag->name,
				),
			)
		);
	}

	/**
	 * Remove a tag from a lead.
	 */
	public function handle_remove_tag(): void {
		$this->check_admin_ajax();

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$tag_id  = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;

		if ( ! $lead_id || ! $tag_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'xtreme-forms' ) ), 400 );
		}

		$tag = XF_Tags::get_tag( $tag_id );

		XF_Tags::remove_tag_from_lead( $lead_id, $tag_id );

		// Log activity.
		if ( $tag ) {
			XF_Activity::log(
				$lead_id,
				get_current_user_id(),
				XF_Activity::TYPE_TAG_REMOVED,
				array(
					'tag_id'   => $tag_id,
					'tag_name' => $tag->name,
				)
			);
		}

		wp_send_json_success( array( 'removed' => true ) );
	}

	// ── Lead Assignment ───────────────────────────────────────────

	/**
	 * Assign a lead to a WordPress user.
	 */
	public function handle_assign_lead(): void {
		$this->check_admin_ajax();

		$lead_id     = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$assigned_to = isset( $_POST['assigned_to'] ) ? absint( $_POST['assigned_to'] ) : 0;

		if ( ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lead ID.', 'xtreme-forms' ) ), 400 );
		}

		$lead = XF_Leads::get_lead( $lead_id );
		if ( ! $lead ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found.', 'xtreme-forms' ) ), 404 );
		}

		// Server-side role check: user must be eligible (Editor+) or assignment is 0 (unassign).
		if ( $assigned_to > 0 && ! XF_Leads::is_eligible_assignee( $assigned_to ) ) {
			wp_send_json_error(
				array( 'message' => __( 'The selected user is not eligible for lead assignment.', 'xtreme-forms' ) ),
				403
			);
		}

		$actor_id     = get_current_user_id();
		$old_assignee = (int) ( $lead->assigned_to ?? 0 );
		$old_user     = $old_assignee ? get_userdata( $old_assignee ) : null;
		$old_name     = $old_user ? $old_user->display_name : __( 'Unassigned', 'xtreme-forms' );

		$result = XF_Leads::update_assigned_to( $lead_id, $assigned_to );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update assignment.', 'xtreme-forms' ) ), 500 );
		}

		// New assignee info.
		$new_user = $assigned_to ? get_userdata( $assigned_to ) : null;
		$new_name = $new_user ? $new_user->display_name : __( 'Unassigned', 'xtreme-forms' );

		// Log activity.
		XF_Activity::log(
			$lead_id,
			$actor_id,
			XF_Activity::TYPE_ASSIGNMENT,
			array(
				'from_id'   => $old_assignee,
				'from_name' => $old_name,
				'to_id'     => $assigned_to,
				'to_name'   => $new_name,
			)
		);

		// Audit log.
		if ( class_exists( 'XF_Audit_Log' ) ) {
			XF_Audit_Log::record(
				XF_Audit_Log::ACTION_LEAD_ASSIGNMENT_CHANGED,
				$lead_id,
				array(
					'from_id'   => $old_assignee,
					'from_name' => $old_name,
					'to_id'     => $assigned_to,
					'to_name'   => $new_name,
				)
			);
		}

		// Email notification to new assignee.
		$email_warning = '';
		if ( $assigned_to && $new_user ) {
			$email_sent = $this->send_assignment_email( $lead_id, $lead, $new_user, $actor_id );
			if ( ! $email_sent ) {
				$email_warning = __( 'Assignment saved, but the notification email could not be sent (the user may not have an email address).', 'xtreme-forms' );
			}
		}

		wp_send_json_success(
			array(
				'assigned_to'   => $assigned_to,
				'assignee_name' => $new_name,
				'email_warning' => $email_warning,
			)
		);
	}

	/**
	 * Send assignment notification email to the new assignee.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead Lead row object.
	 * @param object $new_user WordPress user object of new assignee.
	 * @param int    $actor_id WordPress user ID of the person making the assignment.
	 * @return bool Whether the email was sent.
	 */
	private function send_assignment_email( int $lead_id, object $lead, object $new_user, int $actor_id ): bool {
		if ( empty( $new_user->user_email ) || ! is_email( $new_user->user_email ) ) {
			return false;
		}

		$actor      = get_userdata( $actor_id );
		$actor_name = $actor ? $actor->display_name : __( 'An administrator', 'xtreme-forms' );

		// Determine lead name for email.
		$field_values = XF_Leads::decode_field_values( $lead );
		$lead_name    = $new_user->display_name; // fallback.
		foreach ( $field_values as $key => $val ) {
			if ( is_string( $val ) && stripos( $key, 'email' ) !== false ) {
				$lead_name = $val;
				break;
			}
		}
		// Try to find a name field.
		foreach ( $field_values as $key => $val ) {
			if ( is_string( $val ) && '' !== $val && stripos( $key, 'name' ) !== false ) {
				$lead_name = $val;
				break;
			}
		}

		$admin_link = add_query_arg(
			array(
				'page'      => 'xtreme-forms',
				'xf_action' => 'view',
				'lead_id'   => $lead_id,
			),
			admin_url( 'admin.php' )
		);

		$subject = sprintf(
			/* translators: %d: lead ID */
			__( '[Xtreme Forms] Lead #%d has been assigned to you', 'xtreme-forms' ),
			$lead_id
		);

		$body  = '<h2>' . esc_html__( 'New Lead Assignment', 'xtreme-forms' ) . '</h2>';
		$body .= '<p>' . sprintf(
			/* translators: 1: lead ID, 2: actor name */
			esc_html__( 'Lead #%1$d has been assigned to you by %2$s.', 'xtreme-forms' ),
			$lead_id,
			esc_html( $actor_name )
		) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Lead:', 'xtreme-forms' ) . '</strong> ' . esc_html( $lead_name ) . '</p>';
		$body .= '<p><a href="' . esc_url( $admin_link ) . '">' . esc_html__( 'View Lead in Admin', 'xtreme-forms' ) . '</a></p>';

		$settings   = get_option( 'xtremeforms_settings', array() );
		$from_name  = ! empty( $settings['email_from_name'] ) ? $settings['email_from_name'] : get_bloginfo( 'name' );
		$from_email = ! empty( $settings['email_from'] ) ? $settings['email_from'] : get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>',
		);

		return wp_mail( $new_user->user_email, $subject, $body, $headers );
	}

	/**
	 * Get eligible users for lead assignment.
	 */
	public function handle_get_eligible_users(): void {
		$this->check_admin_ajax();

		$users = XF_Leads::get_eligible_assignees();
		wp_send_json_success( array( 'users' => $users ) );
	}

	// ── Email Template Actions ──────────────────────────────────────

	/**
	 * Send a test email using the current saved template settings.
	 */
	public function handle_send_test_email(): void {
		// Verify nonce and manage_options capability.
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'xf_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'xtreme-forms' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'xtreme-forms' ) ), 403 );
		}

		$result = XF_Email::send_test_email();

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message' => $result['message'],
					'log_id'  => $result['log_id'],
				)
			);
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Resend an email from the Email Log.
	 */
	public function handle_resend_email(): void {
		// Verify nonce and manage_options capability.
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'xf_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'xtreme-forms' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'xtreme-forms' ) ), 403 );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'xtreme-forms' ) ) );
		}

		$result = XF_Email::resend_from_log( $log_id );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'    => $result['message'],
					'new_log_id' => $result['new_log_id'],
				)
			);
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	// ── Form Impression Beacon ──────────────────────────────────────

	/**
	 * Record a form impression.
	 *
	 * Endpoint: wp_ajax_xl_track_impression / wp_ajax_nopriv_xl_track_impression
	 * Method: POST (via fetch with keepalive:true or sendBeacon)
	 * Auth: Nonce-verified (tied to action 'xf_impression_nonce').
	 * The form_id must correspond to an existing, published form.
	 * Response: HTTP 204 No Content on success.
	 */
	public function handle_track_impression(): void {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'xf_impression_nonce' ) ) {
			// Return JSON error so test environments can detect rejection via wp_send_json_error.
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'xtreme-forms' ) ), 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form ID.', 'xtreme-forms' ) ), 400 );
		}

		// Validate form_id corresponds to an existing, active form.
		$form = XF_Forms::get_form( $form_id );
		if ( ! $form || 'active' !== $form->status ) {
			wp_send_json_error( array( 'message' => __( 'Form not found or inactive.', 'xtreme-forms' ) ), 422 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'xtremeforms_form_impressions';

		// Session hash: non-personally-identifying, used for deduplication diagnostics only.
		$session_hash = md5( $this->get_visitor_ip() . sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) . wp_date( 'Ymd' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'form_id'      => $form_id,
				'post_id'      => $post_id,
				'session_hash' => $session_hash,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		// HTTP 204 No Content — the JS beacon doesn't need a response body.
		status_header( 204 );
		exit;
	}

	// ── Dashboard Stats ─────────────────────────────────────────────

	/**
	 * Return KPI tiles, funnel data, top source pages, and top forms.
	 */
	public function handle_dashboard_stats(): void {
		$this->check_analytics_ajax( 'xf_dashboard_stats_nonce' );

		wp_send_json_success(
			array(
				'kpi'              => array(
					'all_time'   => XF_Analytics::count_leads_all_time(),
					'this_month' => XF_Analytics::count_leads_this_month(),
					'this_week'  => XF_Analytics::count_leads_this_week(),
				),
				'funnel'           => XF_Analytics::leads_by_status(),
				'top_source_pages' => XF_Analytics::top_source_pages( 10 ),
				'top_forms'        => XF_Analytics::top_forms( 5 ),
			)
		);
	}

	// ── Leads by Form Chart ─────────────────────────────────────────

	/**
	 * Return leads-by-form data for the bar chart.
	 * Supports optional date_from / date_to filters (ISO Y-m-d strings).
	 */
	public function handle_chart_leads_by_form(): void {
		$this->check_analytics_ajax( 'xf_chart_leads_by_form_nonce' );

		$date_from = isset( $_POST['date_from'] )
			? sanitize_text_field( wp_unslash( $_POST['date_from'] ) )
			: '';
		$date_to   = isset( $_POST['date_to'] )
			? sanitize_text_field( wp_unslash( $_POST['date_to'] ) )
			: '';

		// Convert to UTC datetime for DB queries.
		$from_utc = '';
		$to_utc   = '';
		if ( $date_from ) {
			$tz      = wp_timezone();
			$from_dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_from, $tz );
			if ( $from_dt ) {
				$from_utc = $from_dt->setTime( 0, 0, 0 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		if ( $date_to ) {
			$tz    = wp_timezone();
			$to_dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_to, $tz );
			if ( $to_dt ) {
				$to_utc = $to_dt->setTime( 23, 59, 59 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}

		$data = XF_Analytics::leads_by_form( $from_utc, $to_utc );

		wp_send_json_success(
			array(
				'labels' => array_column( $data, 'form_name' ),
				'values' => array_column( $data, 'count' ),
				'data'   => $data,
			)
		);
	}

	// ── Leads Over Time Chart ───────────────────────────────────────

	/**
	 * Return leads-over-time data for the line chart.
	 *
	 * Required POST params:
	 * range : '7d' | '30d' | '90d' | 'custom'
	 * date_from : Y-m-d (required for 'custom')
	 * date_to : Y-m-d (required for 'custom')
	 *
	 * Validation: if custom end < start, returns 400 error.
	 */
	public function handle_chart_leads_over_time(): void {
		$this->check_analytics_ajax( 'xf_chart_leads_over_time_nonce' );

		$range     = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : '30d';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		switch ( $range ) {
			case '7d':
				$date_from = $now->modify( '-6 days' )->format( 'Y-m-d' );
				$date_to   = $now->format( 'Y-m-d' );
				break;
			case '90d':
				$date_from = $now->modify( '-89 days' )->format( 'Y-m-d' );
				$date_to   = $now->format( 'Y-m-d' );
				break;
			case 'custom':
				if ( empty( $date_from ) || empty( $date_to ) ) {
					wp_send_json_error(
						array( 'message' => __( 'Start and end dates are required for a custom range.', 'xtreme-forms' ) ),
						400
					);
				}
				// Validate: end must not be before start.
				$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_from, $tz );
				$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_to, $tz );
				if ( ! $start_dt || ! $end_dt ) {
					wp_send_json_error( array( 'message' => __( 'Invalid date format.', 'xtreme-forms' ) ), 400 );
				}
				if ( $end_dt < $start_dt ) {
					wp_send_json_error(
						array( 'message' => __( 'End date cannot be before start date.', 'xtreme-forms' ) ),
						400
					);
				}
				break;
			case '30d':
			default:
				$date_from = $now->modify( '-29 days' )->format( 'Y-m-d' );
				$date_to   = $now->format( 'Y-m-d' );
				break;
		}

		$result = XF_Analytics::leads_over_time( $date_from, $date_to );

		wp_send_json_success(
			array(
				'labels'      => $result['labels'],
				'data'        => $result['data'],
				'granularity' => $result['granularity'],
				'date_from'   => $date_from,
				'date_to'     => $date_to,
			)
		);
	}

	// ── UTM Report ──────────────────────────────────────────────────

	/**
	 * Return UTM breakdown data (source, medium, campaign).
	 */
	public function handle_utm_report(): void {
		$this->check_analytics_ajax( 'xf_utm_report_nonce' );

		$data = XF_Analytics::utm_breakdown();

		wp_send_json_success( $data );
	}

	// ── Form Metrics ────────────────────────────────────────────────

	/**
	 * Return per-form performance metrics for the comparison table.
	 */
	public function handle_form_metrics(): void {
		$this->check_analytics_ajax( 'xf_form_metrics_nonce' );

		$metrics = XF_Analytics::form_performance_metrics();

		wp_send_json_success( array( 'metrics' => $metrics ) );
	}

	// ── Admin Duplicate Check ───────────────────────────────────────

	/**
	 * Admin-facing endpoint to check whether a given email address already exists
	 * in the leads database. Returns the original lead ID when a duplicate is found.
	 *
	 * Endpoint: wp_ajax_xl_duplicate_check (admin only — no nopriv variant)
	 * Method: POST
	 * Auth: Nonce-verified (tied to action 'xf_duplicate_check_nonce').
	 * Requires manage_options capability.
	 * Params: email (string) — the email address to check.
	 * Response: JSON with is_duplicate (bool) and original_lead_id (int|null).
	 *
	 * Security: nonce + capability enforced via check_analytics_ajax().
	 * Email is sanitized with sanitize_email() and all DB queries use
	 * $wpdb->prepare() — no user-supplied values are interpolated directly.
	 */
	public function handle_duplicate_check(): void {
		// Nonce + manage_options capability check.
		$this->check_analytics_ajax( 'xf_duplicate_check_nonce' );

		$email = isset( $_POST['email'] )
			? sanitize_email( wp_unslash( $_POST['email'] ) )
			: '';

		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email address is required.', 'xtreme-forms' ) ), 400 );
		}

		global $wpdb;
		$leads_table = $wpdb->prefix . 'xtremeforms_leads';

		// Case-insensitive lookup via LOWER() comparison — same strategy used in XF_Leads duplicate detection.
		// Table name is built exclusively from $wpdb->prefix + a hardcoded string, making interpolation safe.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$original = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$leads_table}
				WHERE LOWER(email_address) = LOWER(%s)
				 AND (is_duplicate = 0 OR is_duplicate IS NULL)
				ORDER BY id ASC
				LIMIT 1",
				$email
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $original ) {
			wp_send_json_success(
				array(
					'is_duplicate'     => true,
					'original_lead_id' => (int) $original->id,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'is_duplicate'     => false,
					'original_lead_id' => null,
				)
			);
		}
	}

	// ── Webhook CRUD ──────────────────────────────────────────────

	/**
	 * Save (insert or update) a webhook endpoint.
	 * Requires manage_options capability.
	 */
	public function handle_webhook_save(): void {
		$this->check_ajax_auth( 'xf_webhook_nonce' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- webhook array; each key sanitized individually after decoding.
		$raw = isset( $_POST['webhook'] ) ? wp_unslash( $_POST['webhook'] ) : array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true ) ?: array();
		}

		$id = XF_Webhooks::save( $raw );
		if ( false === $id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save webhook.', 'xtreme-forms' ) ), 500 );
		}

		$webhook = XF_Webhooks::get( $id );
		wp_send_json_success(
			array(
				'id'      => $id,
				'webhook' => $webhook,
			)
		);
	}

	/**
	 * Delete a webhook endpoint and its log.
	 * Requires manage_options capability.
	 */
	public function handle_webhook_delete(): void {
		$this->check_ajax_auth( 'xf_webhook_nonce' );

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'xtreme-forms' ) ), 400 );
		}

		$result = XF_Webhooks::delete( $webhook_id );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete webhook.', 'xtreme-forms' ) ), 500 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Test-fire a webhook and return the result inline.
	 * Requires manage_options capability.
	 */
	public function handle_webhook_test(): void {
		$this->check_ajax_auth( 'xf_webhook_nonce' );

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'xtreme-forms' ) ), 400 );
		}

		$result = XF_Webhooks::test_fire( $webhook_id );
		wp_send_json_success( $result );
	}

	/**
	 * Get the delivery log for a webhook (paginated).
	 * Requires manage_options capability.
	 */
	public function handle_webhook_log(): void {
		$this->check_ajax_auth( 'xf_webhook_nonce' );

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
		$page       = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		if ( $webhook_id ) {
			$result = XF_Webhooks::get_log( $webhook_id, $page );
		} else {
			$result = XF_Webhooks::get_all_log( $page );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get a single webhook by ID (for editing).
	 * Requires manage_options capability.
	 */
	public function handle_webhook_get(): void {
		$this->check_ajax_auth( 'xf_webhook_nonce' );

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( $_POST['webhook_id'] ) : 0;
		$webhook    = $webhook_id ? XF_Webhooks::get( $webhook_id ) : null;

		if ( ! $webhook ) {
			// Return all webhooks if no specific ID.
			wp_send_json_success( array( 'webhooks' => XF_Webhooks::get_all() ) );
			return;
		}

		wp_send_json_success( array( 'webhook' => $webhook ) );
	}

	// ── GDPR ────────────────────────────────────────────────────

	/**
	 * GDPR Right to Erasure — permanently delete all data for an email address.
	 * Requires manage_options capability.
	 */
	public function handle_gdpr_erase(): void {
		$this->check_ajax_auth( 'xf_gdpr_nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'xtreme-forms' ) ), 400 );
		}

		$result = XF_GDPR::erase_by_email( $email );

		// Audit log per deleted lead record.
		if ( class_exists( 'XF_Audit_Log' ) && ! empty( $result['deleted_ids'] ) && is_array( $result['deleted_ids'] ) ) {
			foreach ( $result['deleted_ids'] as $del_id ) {
				XF_Audit_Log::record(
					XF_Audit_Log::ACTION_LEAD_DATA_DELETED,
					(int) $del_id,
					array(
						'method'     => 'gdpr_erase',
						'email_hash' => md5( strtolower( $email ) ),
					)
				);
			}
		}

		if ( ! $result['found'] ) {
			wp_send_json_error(
				array( 'message' => __( 'No records found for this email address.', 'xtreme-forms' ) ),
				404
			);
		}

		wp_send_json_success(
			array(
				'deleted_leads' => (int) $result['deleted_leads'],
				'message'       => sprintf(
					/* translators: %d: number of deleted lead records */
					_n(
						'%d lead record permanently deleted.',
						'%d lead records permanently deleted.',
						(int) $result['deleted_leads'],
						'xtreme-forms'
					),
					(int) $result['deleted_leads']
				),
			)
		);
	}

	// ── Spam Log ────────────────────────────────────────────────

	/**
	 * Get the spam log (paginated, filterable).
	 * Requires manage_options capability.
	 */
	public function handle_spam_log_get(): void {
		$this->check_ajax_auth( 'xf_spam_log_nonce' );

		$args = array(
			'page'             => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'rejection_reason' => isset( $_POST['rejection_reason'] ) ? sanitize_key( wp_unslash( $_POST['rejection_reason'] ) ) : '',
			'form_id'          => isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0,
		);

		$result = XF_Spam::get_log( $args );
		wp_send_json_success( $result );
	}

	/**
	 * Delete a single spam log entry.
	 * Requires manage_options capability.
	 */
	public function handle_spam_log_delete(): void {
		$this->check_ajax_auth( 'xf_spam_log_nonce' );

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entry ID.', 'xtreme-forms' ) ), 400 );
		}

		$result = XF_Spam::delete_log_entry( $entry_id );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete entry.', 'xtreme-forms' ) ), 500 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Clear the entire spam log.
	 * Requires manage_options capability.
	 */
	public function handle_spam_log_clear(): void {
		$this->check_ajax_auth( 'xf_spam_log_nonce' );

		XF_Spam::clear_log();
		wp_send_json_success(
			array(
				'cleared' => true,
				'message' => __( 'Spam log cleared.', 'xtreme-forms' ),
			)
		);
	}
}
// phpcs:enable WordPress.Security.NonceVerification
