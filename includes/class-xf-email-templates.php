<?php
/**
 * Email Template management – stores and renders customizable email templates.
 *
 * Supports merge tags: {{lead_name}}, {{lead_email}}, {{lead_phone}},
 * {{form_name}}, {{site_url}}, {{submission_date}}, {{source_url}},
 * {{lead_id}}, {{admin_link}}, {{all_fields}}.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_Email_Templates
 */
class Xtremeforms_Email_Templates {

	/** Option key where the global template settings are stored. */
	const OPTION_KEY = 'xtremeforms_email_template';

	/**
	 * Get the saved template settings with defaults.
	 *
	 * @return array{
	 * logo_url: string,
	 * header_color: string,
	 * subject: string,
	 * body_text: string,
	 * footer_text: string
	 * }
	 */
	public static function get_template(): array {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Default template values.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			'logo_url'     => '',
			'header_color' => '#1A73E8',
			'subject'      => __( '[{{site_name}}] New Lead: {{lead_name}}', 'xtreme-forms' ),
			'body_text'    => __( 'You have received a new lead submission from your website.', 'xtreme-forms' ),
			'footer_text'  => __( 'This email was sent by Xtreme Forms. Visit {{site_url}} to manage your leads.', 'xtreme-forms' ),
		);
	}

	/**
	 * Save template settings.
	 *
	 * @param array $data Raw data (will be sanitized internally).
	 * @return bool True on success.
	 */
	public static function save_template( array $data ): bool {
		$template = array(
			'logo_url'     => esc_url_raw( $data['logo_url'] ?? '' ),
			'header_color' => self::sanitize_hex_color( $data['header_color'] ?? '#1A73E8' ),
			'subject'      => sanitize_text_field( $data['subject'] ?? '' ),
			'body_text'    => sanitize_textarea_field( $data['body_text'] ?? '' ),
			'footer_text'  => sanitize_textarea_field( $data['footer_text'] ?? '' ),
		);

		return update_option( self::OPTION_KEY, $template );
	}

	/**
	 * Sanitize a hex color value, returning the default if invalid.
	 *
	 * @param string $color Raw hex color string (with or without #).
	 * @param string $default Fallback color.
	 * @return string
	 */
	private static function sanitize_hex_color( string $color, string $default = '#1A73E8' ): string {
		$color = trim( $color );

		if ( '' === $color ) {
			return $default;
		}

		// Ensure # prefix.
		if ( '#' !== $color[0] ) {
			$color = '#' . $color;
		}

		// 3 or 6 hex digits.
		if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
			return strtoupper( $color );
		}

		return $default;
	}

	/**
	 * Process merge tags in a string.
	 *
	 * @param string $content Template content with {{tag}} placeholders.
	 * @param array  $context Key/value pairs used for replacement.
	 * @param bool   $is_test If true, use sample placeholder values for empty keys.
	 * @return string
	 */
	public static function process_merge_tags( string $content, array $context = array(), bool $is_test = false ): string {
		$replacements = self::build_replacements( $context, $is_test );

		foreach ( $replacements as $tag => $value ) {
			$content = str_replace( '{{' . $tag . '}}', $value, $content );
		}

		// Remove any remaining unreplaced tags (replace with empty string, not literal tag).
		$content = preg_replace( '/\{\{[a-z_]+\}\}/', '', $content );

		return $content;
	}

	/**
	 * Build the full replacements map from submission context.
	 *
	 * @param array $context Keyed context data from the submission.
	 * @param bool  $is_test If true, fill empty values with "[Sample X]" placeholders.
	 * @return array<string, string>
	 */
	private static function build_replacements( array $context, bool $is_test = false ): array {
		$site_name = get_bloginfo( 'name' );
		$site_url  = get_bloginfo( 'url' );

		/**
		 * Helper to resolve a context value, with optional test placeholder.
		 *
		 * @param string $key Context key.
		 * @param string $sample_label Human-readable sample label for test mode.
		 * @return string
		 */
		$resolve = static function ( string $key, string $sample_label ) use ( $context, $is_test ): string {
			$value = $context[ $key ] ?? '';
			if ( '' !== (string) $value ) {
				return (string) $value;
			}
			if ( $is_test ) {
				/* translators: %s is a placeholder label for test email */
				return sprintf( '[%s]', $sample_label );
			}
			return '';
		};

		return array(
			'site_name'       => esc_html( $site_name ),
			'site_url'        => esc_url( $site_url ),
			'lead_name'       => esc_html( $resolve( 'lead_name', 'Sample Name' ) ),
			'lead_email'      => esc_html( $resolve( 'lead_email', 'sample@example.com' ) ),
			'lead_phone'      => esc_html( $resolve( 'lead_phone', '555-0100' ) ),
			'form_name'       => esc_html( $resolve( 'form_name', 'Sample Form' ) ),
			'submission_date' => esc_html( $resolve( 'submission_date', current_time( 'mysql' ) ) ),
			'source_url'      => esc_url( $resolve( 'source_url', $site_url ) ),
			'lead_id'         => esc_html( $resolve( 'lead_id', '1' ) ),
			'admin_link'      => esc_url( $resolve( 'admin_link', admin_url( 'admin.php?page=xtremeleads' ) ) ),
		);
	}

	/**
	 * Build the full HTML email using the stored template and given data.
	 *
	 * @param array  $context Submission context (lead_name, form_name, etc.).
	 * @param array  $field_defs Form field definitions.
	 * @param array  $field_values Submitted field values.
	 * @param string $admin_link Direct admin link to the lead.
	 * @param bool   $is_test Whether this is a test email.
	 * @return array{ subject: string, body: string }
	 */
	public static function build_email(
		array $context,
		array $field_defs = array(),
		array $field_values = array(),
		string $admin_link = '',
		bool $is_test = false
	): array {
		$template     = self::get_template();
		$header_color = esc_attr( $template['header_color'] );
		$logo_url     = esc_url( $template['logo_url'] );
		$site_name    = esc_html( get_bloginfo( 'name' ) );
		$site_url     = esc_url( get_bloginfo( 'url' ) );
		$accent_color = '#FF6B35';
		$dark_color   = '#0D1B2A';

		// Process merge tags in configurable text.
		$body_intro  = nl2br( esc_html( self::process_merge_tags( $template['body_text'], $context, $is_test ) ) );
		$footer_text = nl2br( esc_html( self::process_merge_tags( $template['footer_text'], $context, $is_test ) ) );
		$subject     = self::process_merge_tags( $template['subject'], $context, $is_test );

		// Build logo HTML (if logo_url is set).
		$logo_html = '';
		if ( '' !== $logo_url ) {
			$logo_html = '<img src="' . $logo_url . '" alt="' . esc_attr( $site_name ) . '" style="max-height:50px;max-width:200px;display:block;margin-bottom:8px;">';
		}

		// Build field rows — single-column stacked layout (label above value),
		// matching the form's on-site styling and reading well on mobile.
		$field_rows_html = '';
		if ( ! empty( $field_defs ) ) {
			$first = true;
			foreach ( $field_defs as $field ) {
				$fid   = $field['id'] ?? '';
				$ftype = $field['type'] ?? 'text';
				$label = esc_html( $field['label'] ?? $fid );

				if ( 'hidden' === $ftype ) {
					continue;
				}

				$raw_value = $field_values[ $fid ] ?? '';
				$is_empty  = false;

				// Render multi-value fields as a stacked list, single values as plain text.
				if ( is_array( $raw_value ) ) {
					$items = array_filter(
						array_map( static fn( $v ) => trim( (string) $v ), $raw_value ),
						static fn( $v ) => '' !== $v
					);
					if ( empty( $items ) ) {
						$is_empty   = true;
						$value_html = '';
					} else {
						$value_html = '<ul style="margin:0;padding:0;list-style:none;">';
						foreach ( $items as $item ) {
							$value_html .= '<li style="padding:3px 0;font-size:15px;color:#111827;line-height:1.55;">' . esc_html( $item ) . '</li>';
						}
						$value_html .= '</ul>';
					}
				} else {
					$value_str = trim( (string) $raw_value );
					if ( '' === $value_str ) {
						$is_empty   = true;
						$value_html = '';
					} elseif ( 'textarea' === $ftype ) {
						$value_html = '<div style="font-size:15px;color:#111827;line-height:1.55;white-space:pre-wrap;">' . nl2br( esc_html( $value_str ) ) . '</div>';
					} elseif ( false !== strpos( $value_str, ',' ) && in_array( $ftype, array( 'checkbox', 'multiselect' ), true ) ) {
						// Values stored as comma-joined strings — split back into a list.
						$items      = array_filter( array_map( 'trim', explode( ',', $value_str ) ) );
						$value_html = '<ul style="margin:0;padding:0;list-style:none;">';
						foreach ( $items as $item ) {
							$value_html .= '<li style="padding:3px 0;font-size:15px;color:#111827;line-height:1.55;">' . esc_html( $item ) . '</li>';
						}
						$value_html .= '</ul>';
					} else {
						$value_html = '<div style="font-size:15px;color:#111827;line-height:1.55;word-break:break-word;">' . esc_html( $value_str ) . '</div>';
					}
				}

				if ( $is_empty ) {
					$value_html = '<div style="font-size:14px;color:#9ca3af;font-style:italic;">' .
						( $is_test
							? esc_html__( '[Sample Value]', 'xtreme-forms' )
							: esc_html__( '(not provided)', 'xtreme-forms' )
						) .
						'</div>';
				}

				$top_border = $first ? 'none' : '1px solid #eef0f3';
				$first      = false;

				$field_rows_html .= '
				<tr>
					<td style="padding:18px 24px;border-top:' . $top_border . ';">
						<div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">' . $label . '</div>
						' . $value_html . '
					</td>
				</tr>';
			}
		}

		if ( '' === $field_rows_html && $is_test ) {
			$field_rows_html = '<tr><td style="padding:18px 24px;color:#6C757D;">' . esc_html__( '[Sample form fields will appear here]', 'xtreme-forms' ) . '</td></tr>';
		} elseif ( '' === $field_rows_html ) {
			$field_rows_html = '<tr><td style="padding:18px 24px;color:#6C757D;">' . esc_html__( 'No field data submitted.', 'xtreme-forms' ) . '</td></tr>';
		}

		// Source URL — strip query string so only the clean page path is shown.
		$raw_source    = $context['source_url'] ?? '';
		$source_url    = esc_url( $raw_source );
		$source_label  = '';
		if ( $raw_source ) {
			$parsed       = wp_parse_url( $raw_source );
			$source_label = ( $parsed['path'] ?? '/' );
			// Add host for context but keep it short.
			if ( ! empty( $parsed['host'] ) ) {
				$source_label = $parsed['host'] . $source_label;
			}
			$source_label = rtrim( $source_label, '/' ) ?: '/';
		}

		$admin_link = esc_url( $admin_link ?: ( $context['admin_link'] ?? '' ) );

		// Build source-page row in the same stacked single-column style.
		$meta_rows = '';
		if ( $source_url && $source_label ) {
			$meta_rows .= '<tr>
				<td style="padding:18px 24px;border-top:1px solid #eef0f3;background:#fafbfc;">
					<div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">' . esc_html__( 'Source Page', 'xtreme-forms' ) . '</div>
					<a href="' . $source_url . '" style="font-size:14px;color:#2563eb;text-decoration:none;word-break:break-all;">' . esc_html( $source_label ) . '</a>
				</td>
			</tr>';
		}

		// CTA button.
		$btn_html = '';
		if ( $admin_link ) {
			$btn_html = '<tr>
				<td align="center" class="xf-em-cta xf-em-pad" style="padding:24px 28px 32px;">
					<a href="' . $admin_link . '" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:600;font-size:14px;letter-spacing:0.2px;">' . esc_html__( 'View Lead', 'xtreme-forms' ) . ' &rarr;</a>
				</td>
			</tr>';
		}

		// Powered-by footer line — only shown if the site administrator has
		// explicitly opted in via Settings → Email → "Show 'Sent by Xtreme Forms'
		// credit in outgoing emails". Default is OFF to comply with the
		// WordPress.org plugin directory guidelines on attribution.
		$powered_by_enabled = (bool) get_option( 'xtremeforms_show_powered_by', false );
		$powered_by         = '';
		if ( $powered_by_enabled ) {
			$powered_by = 'Sent by <a href="https://xtremeplugins.com/plugins/xtreme-forms" style="color:#6b7280;text-decoration:underline;">Xtreme Forms</a>';
		}

		$body = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<title>New Lead</title>
<style>
@media only screen and (max-width: 600px) {
	.xf-em-wrapper { width:100% !important; max-width:100% !important; }
	.xf-em-pad     { padding-left:18px !important; padding-right:18px !important; }
	.xf-em-pad-sm  { padding-left:16px !important; padding-right:16px !important; }
	.xf-em-h1      { font-size:20px !important; }
	.xf-em-cta a   { display:block !important; width:100% !important; box-sizing:border-box !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 12px;">
<tr><td align="center">

  <table role="presentation" class="xf-em-wrapper" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

    <!-- Brand bar -->
    <tr>
      <td style="padding:0 4px 16px;">
        ' . $logo_html . '
        <span style="font-size:13px;font-weight:700;color:#374151;letter-spacing:.3px;">' . $site_name . '</span>
      </td>
    </tr>

    <!-- Card -->
    <tr>
      <td style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr><td style="height:4px;background:#111827;border-radius:12px 12px 0 0;line-height:4px;font-size:0;">&nbsp;</td></tr>

          <!-- Heading -->
          <tr>
            <td class="xf-em-pad" style="padding:26px 28px 6px;">
              <p style="margin:0;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">' . esc_html__( 'New Submission', 'xtreme-forms' ) . '</p>
              <h1 class="xf-em-h1" style="margin:6px 0 0;font-size:22px;font-weight:700;color:#111827;letter-spacing:-.3px;line-height:1.3;">' . esc_html( $context['form_name'] ?? 'Form Submission' ) . '</h1>
            </td>
          </tr>

          <!-- Intro -->
          <tr>
            <td class="xf-em-pad" style="padding:14px 28px 4px;">
              <p style="margin:0;font-size:14px;color:#6b7280;line-height:1.6;">' . $body_intro . '</p>
            </td>
          </tr>

          <!-- Fields (stacked single column, mobile-first) -->
          <tr>
            <td class="xf-em-pad-sm" style="padding:18px 20px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:10px;border:1px solid #eef0f3;font-size:14px;background:#ffffff;">
                ' . $field_rows_html . '
                ' . $meta_rows . '
              </table>
            </td>
          </tr>

          <!-- CTA -->
          ' . $btn_html . '

        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:18px 8px 0;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5;">' . ( '' !== $powered_by ? $powered_by . ' &middot; ' : '' ) . $footer_text . '</p>
      </td>
    </tr>

  </table>
</td></tr>
</table>
</body>
</html>';

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Extract context data from a lead record and its field values.
	 *
	 * @param object $lead Lead row from DB.
	 * @param array  $field_defs Field definitions from the form.
	 * @param array  $field_values Submitted values keyed by field ID.
	 * @param string $form_name Name of the form.
	 * @return array
	 */
	public static function build_context_from_lead(
		object $lead,
		array $field_defs,
		array $field_values,
		string $form_name = ''
	): array {
		// Smart detection for email/phone — handles forms where the field type was
		// set to "text" instead of "email"/"phone" (common in user-built forms).
		$lead_email = Xtremeforms_Leads::detect_email( $field_defs, $field_values );
		$lead_phone = Xtremeforms_Leads::detect_phone( $field_defs, $field_values );

		// Auto-detect name field (id or label contains "name", excluding email-named fields).
		$lead_name = '';
		foreach ( $field_defs as $field ) {
			$fid = $field['id'] ?? '';
			$val = $field_values[ $fid ] ?? '';
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}
			$val = (string) $val;
			if ( '' === $val ) {
				continue;
			}
			$haystack = strtolower( ( $field['label'] ?? '' ) . ' ' . $fid );
			// Skip fields whose label/id is the email/phone we already detected.
			if ( false !== strpos( $haystack, 'email' ) || false !== strpos( $haystack, 'phone' ) || false !== strpos( $haystack, 'cell' ) ) {
				continue;
			}
			if ( str_contains( $haystack, 'name' ) ) {
				$lead_name = $val;
				break;
			}
		}

		// Fallback: use email as name if no name field found.
		if ( '' === $lead_name && '' !== $lead_email ) {
			$lead_name = $lead_email;
		}

		$admin_link = admin_url( 'admin.php?page=xtremeleads&xf_action=view&lead_id=' . absint( $lead->id ) );

		return array(
			'lead_name'       => $lead_name,
			'lead_email'      => $lead_email,
			'lead_phone'      => $lead_phone,
			'form_name'       => $form_name,
			'submission_date' => $lead->created_at ?? current_time( 'mysql' ),
			'source_url'      => $lead->source_url ?? '',
			'lead_id'         => (string) ( $lead->id ?? '0' ),
			'admin_link'      => $admin_link,
		);
	}
}
