<?php
/**
 * Email sending – uses customizable templates, routing rules, and logs all dispatches.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_Email
 */
class Xtremeforms_Email {

	// ── Main notification entry-point ─────────────────────────────────────────

	/**
	 * Send a new-lead notification email (admin notification + auto-responder).
	 *
	 * Routing logic:
	 * 1. Evaluate routing rules. If any match, send to matched recipients ONLY.
	 * 2. If no routing rules match, fall back to global/per-form recipients.
	 *
	 * @param int         $lead_id ID of the newly created lead.
	 * @param array       $field_values Submitted field values (id => value).
	 * @param array       $field_defs Form field definitions (for labels).
	 * @param array       $form_settings Per-form settings (may contain recipient override, auto-responder config).
	 * @param string      $source_url URL the form was submitted from.
	 * @param object|null $lead_obj Full lead object (if already fetched). May be null; we re-query if needed.
	 * @return bool True if at least one notification email was sent successfully.
	 */
	public static function send_new_lead_notification(
		int $lead_id,
		array $field_values,
		array $field_defs,
		array $form_settings,
		string $source_url,
		?object $lead_obj = null
	): bool {
		$settings  = get_option( 'xtremeforms_settings', array() );
		$form_id   = (int) ( $form_settings['_form_id'] ?? 0 );
		$form_name = (string) ( $form_settings['_form_name'] ?? '' );

		// Build the lead object stub if not provided.
		if ( null === $lead_obj ) {
			$lead_obj = (object) array(
				'id'         => $lead_id,
				'source_url' => $source_url,
				'created_at' => current_time( 'mysql' ),
			);
		}

		// Build email context from lead data.
		$context = Xtremeforms_Email_Templates::build_context_from_lead(
			$lead_obj,
			$field_defs,
			$field_values,
			$form_name
		);

		$admin_link = admin_url( 'admin.php?page=xtreme-forms-leads&xf_action=view&lead_id=' . $lead_id );

		// Build the email body from the template.
		$email_data = Xtremeforms_Email_Templates::build_email(
			$context,
			$field_defs,
			$field_values,
			$admin_link,
			false
		);

		$subject = $email_data['subject'];
		$body    = $email_data['body'];

		// Per-form custom subject override (with merge-tag processing).
		$custom_subject = trim( (string) ( $form_settings['email_subject'] ?? '' ) );
		if ( '' !== $custom_subject ) {
			$subject = Xtremeforms_Email_Templates::process_merge_tags( $custom_subject, $context, false );
		}

		// Determine notification recipients via routing rules.
		$routing_recipients = Xtremeforms_Routing_Rules::get_matching_recipients( $form_id, $field_values );

		$any_sent     = false;
		$used_routing = false;

		if ( ! empty( $routing_recipients ) ) {
			// Send to each routing-rule recipient.
			$used_routing = true;

			foreach ( $routing_recipients as $recipient ) {
				$headers = self::build_headers( $settings );
				$sent    = wp_mail( $recipient, $subject, $body, $headers );

				$log_id = Xtremeforms_Email_Log::insert(
					array(
						'lead_id'        => $lead_id,
						'recipient'      => $recipient,
						'subject'        => $subject,
						'body'           => $body,
						'headers'        => $headers,
						'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_ROUTING,
						'status'         => $sent ? Xtremeforms_Email_Log::STATUS_SENT : Xtremeforms_Email_Log::STATUS_FAILED,
						'failure_reason' => $sent ? '' : 'wp_mail() returned false',
					)
				);

				// Audit log for sent emails.
				if ( $sent && class_exists( 'Xtremeforms_Audit_Log' ) ) {
					Xtremeforms_Audit_Log::record(
						Xtremeforms_Audit_Log::ACTION_EMAIL_SENT,
						$lead_id,
						array(
							'recipient'    => $recipient,
							'trigger_type' => Xtremeforms_Email_Log::TRIGGER_ROUTING,
							'log_id'       => (int) $log_id,
						)
					);
				}

				if ( $sent ) {
					$any_sent = true;
				} else {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Xtreme Forms: wp_mail() failed for routing rule — lead #' . $lead_id . ', recipient: ' . $recipient );
				}
			}
		}

		if ( ! $used_routing ) {
			// Fallback to global / per-form recipients.
			$recipients = self::get_recipients( $form_settings, $settings );

			if ( empty( $recipients ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Xtreme Forms: No email recipients configured for lead #' . $lead_id );
			} else {
				foreach ( $recipients as $recipient ) {
					$headers = self::build_headers( $settings );
					$sent    = wp_mail( $recipient, $subject, $body, $headers );

					$log_id = Xtremeforms_Email_Log::insert(
						array(
							'lead_id'        => $lead_id,
							'recipient'      => $recipient,
							'subject'        => $subject,
							'body'           => $body,
							'headers'        => $headers,
							'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_NOTIFICATION,
							'status'         => $sent ? Xtremeforms_Email_Log::STATUS_SENT : Xtremeforms_Email_Log::STATUS_FAILED,
							'failure_reason' => $sent ? '' : 'wp_mail() returned false',
						)
					);

					// Audit log for sent emails.
					if ( $sent && class_exists( 'Xtremeforms_Audit_Log' ) ) {
						Xtremeforms_Audit_Log::record(
							Xtremeforms_Audit_Log::ACTION_EMAIL_SENT,
							$lead_id,
							array(
								'recipient'    => $recipient,
								'trigger_type' => Xtremeforms_Email_Log::TRIGGER_NOTIFICATION,
								'log_id'       => (int) $log_id,
							)
						);
					}

					if ( $sent ) {
						$any_sent = true;
					} else {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'Xtreme Forms: wp_mail() failed for lead #' . $lead_id . ', recipient: ' . $recipient );
					}
				}
			}
		}

		// Auto-responder (send to the lead, not the admin).
		self::maybe_send_auto_responder( $lead_id, $lead_obj, $field_defs, $field_values, $form_settings, $context );

		return $any_sent;
	}

	// ── Auto-Responder ────────────────────────────────────────────────────────

	/**
	 * Maybe send an auto-responder email to the lead's submitted email address.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead_obj Lead row object.
	 * @param array  $field_defs Form field definitions.
	 * @param array  $field_values Submitted values.
	 * @param array  $form_settings Per-form settings (contains auto-responder config).
	 * @param array  $context Already-resolved merge-tag context.
	 * @return bool
	 */
	private static function maybe_send_auto_responder(
		int $lead_id,
		object $lead_obj,
		array $field_defs,
		array $field_values,
		array $form_settings,
		array $context
	): bool {
		// Check if auto-responder is enabled.
		$enabled = ! empty( $form_settings['auto_responder_enabled'] ) &&
			'1' === (string) $form_settings['auto_responder_enabled'];

		if ( ! $enabled ) {
			return false;
		}

		// Get the lead's email address.
		$lead_email = $context['lead_email'] ?? '';

		// Graceful failure: no email or invalid email.
		if ( '' === $lead_email || ! is_email( $lead_email ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Xtreme Forms: Auto-responder skipped for lead #' . $lead_id . ' — missing or invalid email: "' . $lead_email . '"' );

			Xtremeforms_Email_Log::insert(
				array(
					'lead_id'        => $lead_id,
					'recipient'      => $lead_email,
					'subject'        => '',
					'body'           => '',
					'headers'        => '',
					'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_AUTO_RESPONDER,
					'status'         => Xtremeforms_Email_Log::STATUS_SKIPPED,
					'failure_reason' => 'Missing or invalid lead email address: "' . $lead_email . '"',
				)
			);

			return false;
		}

		// Build auto-responder email content.
		$ar_subject = $form_settings['auto_responder_subject'] ?? '';
		$ar_body    = $form_settings['auto_responder_body'] ?? '';

		if ( '' === $ar_subject ) {
			$ar_subject = sprintf(
				/* translators: %s: site name */
				__( 'Thank you for contacting %s', 'xtreme-forms' ),
				get_bloginfo( 'name' )
			);
		}

		if ( '' === $ar_body ) {
			$ar_body = __( 'Thank you for your submission. We will be in touch soon.', 'xtreme-forms' );
		}

		// Process merge tags in auto-responder content.
		$ar_subject = Xtremeforms_Email_Templates::process_merge_tags( $ar_subject, $context, false );
		$ar_body    = Xtremeforms_Email_Templates::process_merge_tags( $ar_body, $context, false );

		// Build full HTML using template (no field table for auto-responder).
		$template     = Xtremeforms_Email_Templates::get_template();
		$header_color = esc_attr( $template['header_color'] );
		$logo_url     = esc_url( $template['logo_url'] );
		$site_name    = esc_html( get_bloginfo( 'name' ) );
		$dark_color   = '#0D1B2A';
		$footer_text  = esc_html( Xtremeforms_Email_Templates::process_merge_tags( $template['footer_text'], $context, false ) );

		$logo_html = '';
		if ( '' !== $logo_url ) {
			$logo_html = '<img src="' . $logo_url . '" alt="' . esc_attr( $site_name ) . '" style="max-height:50px;max-width:200px;display:block;margin-bottom:8px;">';
		}

		$body_content = nl2br( esc_html( $ar_body ) );

		$html_body = '<!DOCTYPE html>' . "\n"
			. '<html lang="en">' . "\n"
			. '<head>' . "\n"
			. '<meta charset="UTF-8">' . "\n"
			. '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
			. '<title>Xtreme Forms</title>' . "\n"
			. '</head>' . "\n"
			. '<body style="margin:0;padding:0;background-color:#F8F9FA;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . "\n"
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F9FA;padding:32px 16px;">' . "\n"
			. ' <tr>' . "\n"
			. ' <td align="center">' . "\n"
			. ' <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;border:1px solid #DEE2E6;overflow:hidden;max-width:600px;width:100%;">' . "\n"
			. ' <tr>' . "\n"
			. ' <td style="background:' . $header_color . ';padding:24px 32px;">' . "\n"
			. ' ' . $logo_html . "\n"
			. ' <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">' . $site_name . '</h1>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. ' <tr>' . "\n"
			. ' <td style="padding:32px;">' . "\n"
			. ' <p style="margin:0;font-size:15px;color:' . $dark_color . ';line-height:1.6;">' . $body_content . '</p>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. ' <tr>' . "\n"
			. ' <td style="background:#F8F9FA;padding:16px 32px;border-top:1px solid #DEE2E6;">' . "\n"
			. ' <p style="margin:0;font-size:12px;color:#6C757D;text-align:center;">' . $footer_text . '</p>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. ' </table>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. '</table>' . "\n"
			. '</body>' . "\n"
			. '</html>';

		// Build headers.
		$settings = get_option( 'xtremeforms_settings', array() );
		$headers  = self::build_headers( $settings );

		// Apply custom reply-to.
		$reply_to = sanitize_email( $form_settings['auto_responder_reply_to'] ?? '' );
		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$sent = wp_mail( $lead_email, $ar_subject, $html_body, $headers );

		$log_id = Xtremeforms_Email_Log::insert(
			array(
				'lead_id'        => $lead_id,
				'recipient'      => $lead_email,
				'subject'        => $ar_subject,
				'body'           => $html_body,
				'headers'        => $headers,
				'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_AUTO_RESPONDER,
				'status'         => $sent ? Xtremeforms_Email_Log::STATUS_SENT : Xtremeforms_Email_Log::STATUS_FAILED,
				'failure_reason' => $sent ? '' : 'wp_mail() returned false',
			)
		);

		// Audit log for auto-responder emails.
		if ( $sent && class_exists( 'Xtremeforms_Audit_Log' ) ) {
			Xtremeforms_Audit_Log::record(
				Xtremeforms_Audit_Log::ACTION_EMAIL_SENT,
				$lead_id,
				array(
					'recipient'    => $lead_email,
					'trigger_type' => Xtremeforms_Email_Log::TRIGGER_AUTO_RESPONDER,
					'log_id'       => (int) $log_id,
				)
			);
		}

		if ( ! $sent ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Xtreme Forms: Auto-responder wp_mail() failed for lead #' . $lead_id . ', recipient: ' . $lead_email );
		}

		return $sent;
	}

	// ── Test Email ────────────────────────────────────────────────────────────

	/**
	 * Send a test email to the currently logged-in admin.
	 *
	 * @return array{ success: bool, message: string, log_id: int }
	 */
	public static function send_test_email(): array {
		$current_user = wp_get_current_user();
		$recipient    = $current_user->user_email ?? get_option( 'admin_email' );

		if ( ! is_email( $recipient ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not determine a valid recipient email.', 'xtreme-forms' ),
				'log_id'  => 0,
			);
		}

		// Build test context with sample values.
		$test_context = array(
			'lead_name'       => '[Sample Name]',
			'lead_email'      => 'sample@example.com',
			'lead_phone'      => '555-0100',
			'form_name'       => '[Sample Form]',
			'submission_date' => current_time( 'mysql' ),
			'source_url'      => home_url( '/contact' ),
			'lead_id'         => '999',
			'admin_link'      => admin_url( 'admin.php?page=xtremeleads' ),
		);

		// Sample field defs for a realistic preview.
		$test_field_defs = array(
			array(
				'id'       => 'full_name',
				'type'     => 'text',
				'label'    => 'Full Name',
				'required' => true,
			),
			array(
				'id'       => 'email',
				'type'     => 'email',
				'label'    => 'Email',
				'required' => true,
			),
			array(
				'id'       => 'message',
				'type'     => 'textarea',
				'label'    => 'Message',
				'required' => false,
			),
		);

		$test_field_values = array(
			'full_name' => '[Sample Name]',
			'email'     => 'sample@example.com',
			'message'   => '[This is a sample message to preview how your email will look.]',
		);

		$admin_link = admin_url( 'admin.php?page=xtremeleads' );

		$email_data = Xtremeforms_Email_Templates::build_email(
			$test_context,
			$test_field_defs,
			$test_field_values,
			$admin_link,
			true
		);

		$subject = '[Test] ' . $email_data['subject'];
		$body    = $email_data['body'];

		$settings = get_option( 'xtremeforms_settings', array() );
		$headers  = self::build_headers( $settings );

		$sent = wp_mail( $recipient, $subject, $body, $headers );

		$log_id = Xtremeforms_Email_Log::insert(
			array(
				'lead_id'        => 0,
				'recipient'      => $recipient,
				'subject'        => $subject,
				'body'           => $body,
				'headers'        => $headers,
				'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_TEST,
				'status'         => $sent ? Xtremeforms_Email_Log::STATUS_SENT : Xtremeforms_Email_Log::STATUS_FAILED,
				'failure_reason' => $sent ? '' : 'wp_mail() returned false',
			)
		);

		if ( $sent ) {
			return array(
				'success' => true,
				/* translators: %s: recipient email address */
				'message' => sprintf( __( 'Test email sent to %s.', 'xtreme-forms' ), esc_html( $recipient ) ),
				'log_id'  => (int) $log_id,
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Failed to send test email. Please check your WordPress email configuration.', 'xtreme-forms' ),
				'log_id'  => (int) $log_id,
			);
		}
	}

	// ── Resend (lead-level) ───────────────────────────────────────────────────

	/**
	 * Re-dispatch the new-lead notification for an existing lead.
	 *
	 * Used from the admin UI when an email failed to deliver (e.g. SMTP outage,
	 * misconfiguration) and the admin wants to retry after fixing the issue.
	 * The email body is rebuilt from the lead's stored field values rather than
	 * pulled from the log, so it always reflects the current template settings.
	 *
	 * Recipient resolution mirrors send_new_lead_notification(): routing rules
	 * take precedence; if none match, fall back to per-form / global recipients.
	 * If $override_recipient is provided, only that address is used.
	 *
	 * @param int    $lead_id            Existing lead ID.
	 * @param string $override_recipient Optional: send only to this address (admin-typed).
	 * @return array{ success: bool, message: string, sent_count: int, failed_count: int }
	 */
	public static function resend_lead_notification( int $lead_id, string $override_recipient = '' ): array {
		$lead = Xtremeforms_Leads::get_lead( $lead_id );
		if ( ! $lead ) {
			return array(
				'success'      => false,
				'message'      => __( 'Lead not found.', 'xtreme-forms' ),
				'sent_count'   => 0,
				'failed_count' => 0,
			);
		}

		$form         = Xtremeforms_Forms::get_form( (int) $lead->form_id );
		$field_values = Xtremeforms_Leads::decode_field_values( $lead );
		$field_defs   = $form ? Xtremeforms_Forms::decode_fields( $form ) : array();
		$form_name    = $form ? (string) $form->name : '';

		$form_settings              = $form ? Xtremeforms_Forms::decode_settings( $form ) : array();
		$form_settings['_form_id']   = $form ? (int) $form->id : 0;
		$form_settings['_form_name'] = $form_name;

		$context = Xtremeforms_Email_Templates::build_context_from_lead(
			$lead,
			$field_defs,
			$field_values,
			$form_name
		);

		$admin_link = admin_url( 'admin.php?page=xtreme-forms-leads&xf_action=view&lead_id=' . $lead_id );

		$email_data = Xtremeforms_Email_Templates::build_email(
			$context,
			$field_defs,
			$field_values,
			$admin_link,
			false
		);

		$subject = $email_data['subject'];
		$body    = $email_data['body'];

		// Per-form custom subject override (with merge-tag processing).
		$custom_subject = trim( (string) ( $form_settings['email_subject'] ?? '' ) );
		if ( '' !== $custom_subject ) {
			$subject = Xtremeforms_Email_Templates::process_merge_tags( $custom_subject, $context, false );
		}

		// Resolve recipients.
		$override_recipient = trim( $override_recipient );
		if ( '' !== $override_recipient ) {
			if ( ! is_email( $override_recipient ) ) {
				return array(
					'success'      => false,
					'message'      => __( 'Invalid recipient email address.', 'xtreme-forms' ),
					'sent_count'   => 0,
					'failed_count' => 0,
				);
			}
			$recipients = array( sanitize_email( $override_recipient ) );
		} else {
			$routing = Xtremeforms_Routing_Rules::get_matching_recipients(
				(int) $form_settings['_form_id'],
				$field_values
			);
			if ( ! empty( $routing ) ) {
				$recipients = $routing;
			} else {
				$global_settings = get_option( 'xtremeforms_settings', array() );
				$recipients      = self::get_recipients( $form_settings, $global_settings );
			}
		}

		if ( empty( $recipients ) ) {
			return array(
				'success'      => false,
				'message'      => __( 'No recipients configured for this form. Add a recipient in form or plugin settings, or specify one when resending.', 'xtreme-forms' ),
				'sent_count'   => 0,
				'failed_count' => 0,
			);
		}

		$global_settings = get_option( 'xtremeforms_settings', array() );

		$sent_count   = 0;
		$failed_count = 0;

		foreach ( $recipients as $recipient ) {
			$headers = self::build_headers( $global_settings );
			$sent    = wp_mail( $recipient, $subject, $body, $headers );

			$log_id = Xtremeforms_Email_Log::insert(
				array(
					'lead_id'        => $lead_id,
					'recipient'      => $recipient,
					'subject'        => $subject,
					'body'           => $body,
					'headers'        => $headers,
					'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_RESEND,
					'status'         => $sent ? Xtremeforms_Email_Log::STATUS_SENT : Xtremeforms_Email_Log::STATUS_FAILED,
					'failure_reason' => $sent ? '' : 'wp_mail() returned false on lead resend',
				)
			);

			if ( $sent && class_exists( 'Xtremeforms_Audit_Log' ) ) {
				Xtremeforms_Audit_Log::record(
					Xtremeforms_Audit_Log::ACTION_EMAIL_SENT,
					$lead_id,
					array(
						'recipient'    => $recipient,
						'trigger_type' => Xtremeforms_Email_Log::TRIGGER_RESEND,
						'log_id'       => (int) $log_id,
					)
				);
			}

			if ( $sent ) {
				++$sent_count;
			} else {
				++$failed_count;
			}
		}

		$success = $sent_count > 0;
		if ( $success && 0 === $failed_count ) {
			$message = sprintf(
				/* translators: %d: recipient count */
				_n( 'Notification resent to %d recipient.', 'Notification resent to %d recipients.', $sent_count, 'xtreme-forms' ),
				$sent_count
			);
		} elseif ( $success ) {
			$message = sprintf(
				/* translators: 1: sent count, 2: failed count */
				__( 'Resent to %1$d recipient(s); %2$d failed.', 'xtreme-forms' ),
				$sent_count,
				$failed_count
			);
		} else {
			$message = __( 'Failed to resend notification. Check your email configuration.', 'xtreme-forms' );
		}

		return array(
			'success'      => $success,
			'message'      => $message,
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
		);
	}

	// ── Resend (from log) ─────────────────────────────────────────────────────

	/**
	 * Re-dispatch an email from a log entry.
	 *
	 * Creates a new log entry rather than mutating the original.
	 *
	 * @param int $log_id Email log entry ID.
	 * @return array{ success: bool, message: string, new_log_id: int }
	 */
	public static function resend_from_log( int $log_id ): array {
		$entry = Xtremeforms_Email_Log::get_log( $log_id );

		if ( ! $entry ) {
			return array(
				'success'    => false,
				'message'    => __( 'Email log entry not found.', 'xtreme-forms' ),
				'new_log_id' => 0,
			);
		}

		$recipient = $entry->recipient;

		if ( ! is_email( $recipient ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'Invalid recipient address in log entry.', 'xtreme-forms' ),
				'new_log_id' => 0,
			);
		}

		$headers_raw = $entry->headers ?? '';
		$headers     = '' !== $headers_raw ? explode( "\n", $headers_raw ) : array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $recipient, $entry->subject, $entry->body, $headers );

		$new_log_id = Xtremeforms_Email_Log::insert(
			array(
				'lead_id'        => (int) $entry->lead_id,
				'recipient'      => $recipient,
				'subject'        => $entry->subject,
				'body'           => $entry->body,
				'headers'        => $headers,
				'trigger_type'   => Xtremeforms_Email_Log::TRIGGER_RESEND,
				'status'         => $sent ? Xtremeforms_Email_Log::STATUS_SENT : Xtremeforms_Email_Log::STATUS_FAILED,
				'failure_reason' => $sent ? '' : 'wp_mail() returned false on resend',
			)
		);

		return array(
			'success'    => $sent,
			'message'    => $sent
				? __( 'Email resent successfully.', 'xtreme-forms' )
				: __( 'Failed to resend email.', 'xtreme-forms' ),
			'new_log_id' => (int) $new_log_id,
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Determine the recipient list for a notification.
	 *
	 * If the form has an override, use ONLY the override recipients.
	 * Otherwise fall back to the global plugin recipients.
	 *
	 * @param array $form_settings Per-form settings.
	 * @param array $global_settings Plugin-level settings.
	 * @return array<string> List of email addresses.
	 */
	public static function get_recipients( array $form_settings, array $global_settings ): array {
		$override = trim( $form_settings['email_recipients'] ?? '' );

		if ( '' !== $override ) {
			return self::parse_recipients( $override );
		}

		$global = trim( $global_settings['recipients'] ?? '' );
		return self::parse_recipients( $global );
	}

	/**
	 * Parse a comma-separated recipient string into an array of valid email addresses.
	 *
	 * @param string $raw Comma-separated emails.
	 * @return array<string>
	 */
	private static function parse_recipients( string $raw ): array {
		$emails = array_map( 'trim', explode( ',', $raw ) );
		$valid  = array();

		foreach ( $emails as $email ) {
			if ( is_email( $email ) ) {
				$valid[] = sanitize_email( $email );
			}
		}

		return $valid;
	}

	/**
	 * Build common email headers array.
	 *
	 * @param array $settings Global plugin settings.
	 * @return array<string>
	 */
	private static function build_headers( array $settings ): array {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( ! empty( $settings['email_from'] ) && ! empty( $settings['email_from_name'] ) ) {
			$headers[] = 'From: ' . sanitize_text_field( $settings['email_from_name'] )
				. ' <' . sanitize_email( $settings['email_from'] ) . '>';
		}

		return $headers;
	}
}
