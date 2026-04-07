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
 * Class XF_Email_Templates
 */
class XF_Email_Templates {

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
		$saved = get_option( self::OPTION_KEY, array() );
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
			'logo_url' => '',
			'header_color' => '#1A73E8',
			'subject' => __( '[{{site_name}}] New Lead: {{lead_name}}', 'xtreme-forms' ),
			'body_text' => __( 'You have received a new lead submission from your website.', 'xtreme-forms' ),
			'footer_text' => __( 'This email was sent by Xtreme Forms. Visit {{site_url}} to manage your leads.', 'xtreme-forms' ),
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
			'logo_url' => esc_url_raw( $data['logo_url'] ?? '' ),
			'header_color' => self::sanitize_hex_color( $data['header_color'] ?? '#1A73E8' ),
			'subject' => sanitize_text_field( $data['subject'] ?? '' ),
			'body_text' => sanitize_textarea_field( $data['body_text'] ?? '' ),
			'footer_text' => sanitize_textarea_field( $data['footer_text'] ?? '' ),
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
	 * @param array $context Key/value pairs used for replacement.
	 * @param bool $is_test If true, use sample placeholder values for empty keys.
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
	 * @param bool $is_test If true, fill empty values with "[Sample X]" placeholders.
	 * @return array<string, string>
	 */
	private static function build_replacements( array $context, bool $is_test = false ): array {
		$site_name = get_bloginfo( 'name' );
		$site_url = get_bloginfo( 'url' );

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
			'site_name' => esc_html( $site_name ),
			'site_url' => esc_url( $site_url ),
			'lead_name' => esc_html( $resolve( 'lead_name', 'Sample Name' ) ),
			'lead_email' => esc_html( $resolve( 'lead_email', 'sample@example.com' ) ),
			'lead_phone' => esc_html( $resolve( 'lead_phone', '555-0100' ) ),
			'form_name' => esc_html( $resolve( 'form_name', 'Sample Form' ) ),
			'submission_date' => esc_html( $resolve( 'submission_date', current_time( 'mysql' ) ) ),
			'source_url' => esc_url( $resolve( 'source_url', $site_url ) ),
			'lead_id' => esc_html( $resolve( 'lead_id', '1' ) ),
			'admin_link' => esc_url( $resolve( 'admin_link', admin_url( 'admin.php?page=xtremeleads' ) ) ),
		);
	}

	/**
	 * Build the full HTML email using the stored template and given data.
	 *
	 * @param array $context Submission context (lead_name, form_name, etc.).
	 * @param array $field_defs Form field definitions.
	 * @param array $field_values Submitted field values.
	 * @param string $admin_link Direct admin link to the lead.
	 * @param bool $is_test Whether this is a test email.
	 * @return array{ subject: string, body: string }
	 */
	public static function build_email(
		array $context,
		array $field_defs = array(),
		array $field_values = array(),
		string $admin_link = '',
		bool $is_test = false
	): array {
		$template = self::get_template();
		$header_color = esc_attr( $template['header_color'] );
		$logo_url = esc_url( $template['logo_url'] );
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url = esc_url( get_bloginfo( 'url' ) );
		$accent_color = '#FF6B35';
		$dark_color = '#0D1B2A';

		// Process merge tags in configurable text.
		$body_intro = nl2br( esc_html( self::process_merge_tags( $template['body_text'], $context, $is_test ) ) );
		$footer_text = nl2br( esc_html( self::process_merge_tags( $template['footer_text'], $context, $is_test ) ) );
		$subject = self::process_merge_tags( $template['subject'], $context, $is_test );

		// Build logo HTML (if logo_url is set).
		$logo_html = '';
		if ( '' !== $logo_url ) {
			$logo_html = '<img src="' . $logo_url . '" alt="' . esc_attr( $site_name ) . '" style="max-height:50px;max-width:200px;display:block;margin-bottom:8px;">';
		}

		// Build field table rows.
		$field_rows_html = '';
		if ( ! empty( $field_defs ) ) {
			foreach ( $field_defs as $field ) {
				$fid = $field['id'] ?? '';
				$ftype = $field['type'] ?? 'text';
				$label = esc_html( $field['label'] ?? $fid );

				if ( 'hidden' === $ftype ) {
					continue;
				}

				$raw_value = $field_values[ $fid ] ?? ( $is_test ? '' : '' );
				if ( is_array( $raw_value ) ) {
					$value = esc_html( implode( ', ', $raw_value ) );
				} else {
					$value = esc_html( (string) $raw_value );
				}

				if ( '' === $value ) {
					if ( $is_test ) {
						$value = '<em style="color:#6C757D;">[' . esc_html__( 'Sample Value', 'xtreme-forms' ) . ']</em>';
					} else {
						$value = '<em style="color:#6C757D;">' . esc_html__( '(not provided)', 'xtreme-forms' ) . '</em>';
					}
				}

				$field_rows_html .= '
				<tr>
					<td style="padding:10px 16px;border-bottom:1px solid #DEE2E6;font-weight:600;width:35%;color:' . esc_attr( $dark_color ) . ';">' . $label . '</td>
					<td style="padding:10px 16px;border-bottom:1px solid #DEE2E6;">' . $value . '</td>
				</tr>';
			}
		}

		if ( '' === $field_rows_html && $is_test ) {
			$field_rows_html = '<tr><td colspan="2" style="padding:16px;color:#6C757D;">' . esc_html__( '[Sample form fields will appear here]', 'xtreme-forms' ) . '</td></tr>';
		} elseif ( '' === $field_rows_html ) {
			$field_rows_html = '<tr><td colspan="2" style="padding:16px;color:#6C757D;">' . esc_html__( 'No field data submitted.', 'xtreme-forms' ) . '</td></tr>';
		}

		// Source URL and admin link rows.
		$source_url = esc_url( $context['source_url'] ?? '' );
		$admin_link = esc_url( $admin_link ?: ( $context['admin_link'] ?? '' ) );
		$label_source = esc_html__( 'Source Page', 'xtreme-forms' );
		$label_view = esc_html__( 'View Lead in Admin', 'xtreme-forms' );
		$label_btn = esc_html__( 'View Lead', 'xtreme-forms' );
		$primary_color = $header_color;

		$meta_rows = '';
		if ( $source_url ) {
			$meta_rows .= '
			<tr>
				<td style="padding:10px 16px;border-bottom:1px solid #DEE2E6;font-weight:600;color:' . esc_attr( $dark_color ) . ';">' . $label_source . '</td>
				<td style="padding:10px 16px;border-bottom:1px solid #DEE2E6;"><a href="' . $source_url . '" style="color:' . esc_attr( $primary_color ) . ';">' . $source_url . '</a></td>
			</tr>';
		}
		if ( $admin_link ) {
			$meta_rows .= '
			<tr>
				<td style="padding:10px 16px;font-weight:600;color:' . esc_attr( $dark_color ) . ';">' . $label_view . '</td>
				<td style="padding:10px 16px;"><a href="' . $admin_link . '" style="color:' . esc_attr( $primary_color ) . ';">' . $admin_link . '</a></td>
			</tr>';
		}

		$btn_html = '';
		if ( $admin_link ) {
			$btn_html = '<tr>
				<td align="center" style="padding:0 32px 32px;">
					<a href="' . $admin_link . '" style="display:inline-block;background:' . esc_attr( $accent_color ) . ';color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:6px;font-weight:700;font-size:15px;letter-spacing:0.5px;">' . $label_btn . '</a>
				</td>
			</tr>';
		}

		$body = '<!DOCTYPE html>' . "\n"
			. '<html lang="en">' . "\n"
			. '<head>' . "\n"
			. '<meta charset="UTF-8">' . "\n"
			. '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
			. '<title>Xtreme Forms Email</title>' . "\n"
			. '</head>' . "\n"
			. '<body style="margin:0;padding:0;background-color:#F8F9FA;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . "\n"
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F9FA;padding:32px 16px;">' . "\n"
			. ' <tr>' . "\n"
			. ' <td align="center">' . "\n"
			. ' <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;border:1px solid #DEE2E6;overflow:hidden;max-width:600px;width:100%;">' . "\n"
			. ' <!-- Header -->' . "\n"
			. ' <tr>' . "\n"
			. ' <td style="background:' . $header_color . ';padding:24px 32px;">' . "\n"
			. ' ' . $logo_html . "\n"
			. ' <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">' . $site_name . '</h1>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. ' <!-- Intro -->' . "\n"
			. ' <tr>' . "\n"
			. ' <td style="padding:24px 32px 16px;">' . "\n"
			. ' <p style="margin:0;font-size:15px;color:' . $dark_color . ';">' . $body_intro . '</p>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. ' <!-- Field values -->' . "\n"
			. ' <tr>' . "\n"
			. ' <td style="padding:0 32px 24px;">' . "\n"
			. ' <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #DEE2E6;border-radius:6px;overflow:hidden;font-size:14px;">' . "\n"
			. ' ' . $field_rows_html . "\n"
			. ' ' . $meta_rows . "\n"
			. ' </table>' . "\n"
			. ' </td>' . "\n"
			. ' </tr>' . "\n"
			. ' ' . $btn_html . "\n"
			. ' <!-- Footer -->' . "\n"
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

		return array(
			'subject' => $subject,
			'body' => $body,
		);
	}

	/**
	 * Extract context data from a lead record and its field values.
	 *
	 * @param object $lead Lead row from DB.
	 * @param array $field_defs Field definitions from the form.
	 * @param array $field_values Submitted values keyed by field ID.
	 * @param string $form_name Name of the form.
	 * @return array
	 */
	public static function build_context_from_lead(
		object $lead,
		array $field_defs,
		array $field_values,
		string $form_name = ''
	): array {
		// Try to find name, email, phone from well-known field types/IDs.
		$lead_name = '';
		$lead_email = '';
		$lead_phone = '';

		foreach ( $field_defs as $field ) {
			$fid = $field['id'] ?? '';
			$ftype = $field['type'] ?? 'text';
			$val = $field_values[ $fid ] ?? '';

			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}

			$val = (string) $val;

			// Auto-detect email field.
			if ( '' === $lead_email && 'email' === $ftype && $val ) {
				$lead_email = $val;
			}

			// Auto-detect phone field.
			if ( '' === $lead_phone && 'phone' === $ftype && $val ) {
				$lead_phone = $val;
			}

			// Auto-detect name field (id or label contains "name").
			if ( '' === $lead_name && $val ) {
				$id_lower = strtolower( $fid );
				$label_lower = strtolower( $field['label'] ?? '' );
				if (
					str_contains( $id_lower, 'name' ) ||
					str_contains( $label_lower, 'name' )
				) {
					$lead_name = $val;
				}
			}
		}

		// Fallback: use email as name if no name field found.
		if ( '' === $lead_name && '' !== $lead_email ) {
			$lead_name = $lead_email;
		}

		$admin_link = admin_url( 'admin.php?page=xtremeleads&xf_action=view&lead_id=' . absint( $lead->id ) );

		return array(
			'lead_name' => $lead_name,
			'lead_email' => $lead_email,
			'lead_phone' => $lead_phone,
			'form_name' => $form_name,
			'submission_date' => $lead->created_at ?? current_time( 'mysql' ),
			'source_url' => $lead->source_url ?? '',
			'lead_id' => (string) ( $lead->id ?? '0' ),
			'admin_link' => $admin_link,
		);
	}
}
