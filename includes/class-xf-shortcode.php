<?php
/**
 * Shortcode rendering for public-facing forms.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Shortcode
 */
class XF_Shortcode {

	/**
	 * Render the [xtremeleads id="X"] shortcode.
	 *
	 * Handles form scheduling (activate_at / expire_at) and optional
	 * countdown timer display before the activation datetime.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			$atts,
			'xtreme-forms'
		);

		$form_id = absint( $atts['id'] );

		if ( ! $form_id ) {
			return '<p class="xf-form-error">' . esc_html__( 'Xtreme Forms: Please specify a valid form ID.', 'xtreme-forms' ) . '</p>';
		}

		$form = XF_Forms::get_form( $form_id );

		if ( ! $form ) {
			return '<p class="xf-form-error">' . esc_html__( 'Xtreme Forms: The requested form could not be found.', 'xtreme-forms' ) . '</p>';
		}

		$fields   = XF_Forms::decode_fields( $form );
		$settings = XF_Forms::decode_settings( $form );

		// ── Scheduling ────────────────────────────────────────────
		$scheduling_result = $this->check_scheduling( $form, $form_id, $settings, $fields );
		if ( null !== $scheduling_result ) {
			return $scheduling_result;
		}

		// Enqueue assets with form settings (needed for reCAPTCHA decision).
		$this->enqueue_assets( $settings );

		// Enqueue conditional logic JS if the form has rules.
		$this->maybe_enqueue_conditional_js( $form_id, $fields );

		return $this->render_form( $form_id, $fields, $settings );
	}

	/**
	 * Check form scheduling and return appropriate output (or null to render form).
	 *
	 * @param object $form Form DB row.
	 * @param int    $form_id Form ID.
	 * @param array  $settings Form settings.
	 * @param array  $fields Form field definitions (used for countdown pre-render).
	 * @return string|null HTML for closed/countdown state, or null to render the form.
	 */
	private function check_scheduling( object $form, int $form_id, array $settings, array $fields = array() ): ?string {
		$activate_at = ! empty( $form->activate_at ) && '0000-00-00 00:00:00' !== $form->activate_at
			? $form->activate_at : null;
		$expire_at   = ! empty( $form->expire_at ) && '0000-00-00 00:00:00' !== $form->expire_at
			? $form->expire_at : null;

		// No scheduling configured — render normally.
		if ( null === $activate_at && null === $expire_at ) {
			return null;
		}

		// Use WordPress site timezone.
		$now    = current_time( 'mysql', false ); // localtime string.
		$now_ts = strtotime( $now );

		$activate_ts = $activate_at ? strtotime( $activate_at ) : null;
		$expire_ts   = $expire_at ? strtotime( $expire_at ) : null;

		$closed_message = ! empty( $form->closed_message ) ? $form->closed_message
			: ( $settings['closed_message'] ?? __( 'This form is currently unavailable.', 'xtreme-forms' ) );

		$closed_html_tag = '<p id="xf-closed-msg-' . esc_attr( $form_id ) . '" class="xf-form-closed-message">'
			. wp_kses_post( $closed_message ) . '</p>';

		// Case 1: Before activation window.
		if ( $activate_ts && $now_ts < $activate_ts ) {
			// Should we show a countdown timer?
			$show_countdown = ! empty( $settings['countdown_timer_enabled'] ) && '1' === (string) $settings['countdown_timer_enabled'];

			if ( $show_countdown ) {
				// Enqueue public assets so the form renders correctly when revealed.
				$this->enqueue_assets( $settings );
				$this->maybe_enqueue_conditional_js( $form_id, $fields );
				$this->enqueue_countdown_js( $form_id, $activate_at );

				// Pre-render the form HTML into the hidden container so the JS can
				// reveal it without a page reload when the countdown reaches zero.
				$form_html = $this->render_form( $form_id, $fields, $settings );
				return $closed_html_tag . $this->render_countdown( $form_id, $activate_at, $form_html );
			}

			return $closed_html_tag;
		}

		// Case 2: After expiry window.
		if ( $expire_ts && $now_ts >= $expire_ts ) {
			return $closed_html_tag;
		}

		// Case 3: Within the active window (or past activation with no expiry) — render form.
		return null;
	}

	/**
	 * Render the countdown timer HTML scaffold.
	 *
	 * The $form_html is pre-rendered and placed inside a hidden container div.
	 * When the countdown JS reaches zero it simply un-hides that container —
	 * no page reload or additional AJAX request is required.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $activate_at MySQL datetime (site timezone).
	 * @param string $form_html Pre-rendered form HTML to inject when timer expires.
	 * @return string HTML.
	 */
	private function render_countdown( int $form_id, string $activate_at, string $form_html = '' ): string {
		// Convert site-timezone datetime to UTC ISO-8601 for JS.
		$tz = get_option( 'timezone_string' ) ?: 'UTC';
		try {
			$dt = new DateTime( $activate_at, new DateTimeZone( $tz ) );
			$dt->setTimezone( new DateTimeZone( 'UTC' ) );
			$iso = $dt->format( 'c' );
		} catch ( Exception $e ) {
			$iso = '';
		}

		if ( ! $iso ) {
			return '';
		}

		$html  = '<div class="xf-countdown-wrap" data-form-id="' . esc_attr( $form_id ) . '">';
		$html .= '<p class="xf-countdown-label">' . esc_html__( 'Form opens in:', 'xtreme-forms' ) . '</p>';
		$html .= '<div class="xf-countdown-timer" aria-live="polite"></div>';
		$html .= '</div>';
		// Hidden form container — pre-rendered so JS only needs to un-hide it
		// (no page reload or AJAX call required when countdown reaches zero).
		$html .= '<div id="xf-form-wrap-' . esc_attr( $form_id ) . '" style="display:none;">' . $form_html . '</div>';

		return $html;
	}

	/**
	 * Enqueue the countdown timer JS and pass activation timestamp.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $activate_at MySQL datetime (site timezone).
	 */
	private function enqueue_countdown_js( int $form_id, string $activate_at ): void {
		if ( ! wp_script_is( 'xf-countdown', 'enqueued' ) ) {
			wp_enqueue_script(
				'xf-countdown',
				XTREMEFORMS_PLUGIN_URL . 'public/js/xf-countdown.js',
				array(),
				XTREMEFORMS_VERSION,
				true // footer = true, no render-blocking.
			);
		}

		// Convert to UTC ISO-8601.
		$tz  = get_option( 'timezone_string' ) ?: 'UTC';
		$iso = '';
		try {
			$dt = new DateTime( $activate_at, new DateTimeZone( $tz ) );
			$dt->setTimezone( new DateTimeZone( 'UTC' ) );
			$iso = $dt->format( 'c' );
		} catch ( Exception $e ) {
			$iso = '';
		}

		if ( ! $iso ) {
			return;
		}

		// Merge into xlCountdownData (multiple forms on same page).
		// We use wp_add_inline_script to accumulate data per form.
		$inline = 'if(typeof xlCountdownData === "undefined"){window.xlCountdownData={};}' .
			'xlCountdownData[' . (int) $form_id . ']=' . wp_json_encode(
				array(
					'activateAt' => $iso,
					'i18n'       => array(
						'days'    => __( 'd', 'xtreme-forms' ),
						'hours'   => __( 'h', 'xtreme-forms' ),
						'minutes' => __( 'm', 'xtreme-forms' ),
						'seconds' => __( 's', 'xtreme-forms' ),
					),
				)
			) . ';';

		wp_add_inline_script( 'xf-countdown', $inline, 'before' );
	}

	/**
	 * Enqueue conditional logic JS if the form has conditional rules.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $fields Form field definitions.
	 */
	private function maybe_enqueue_conditional_js( int $form_id, array $fields ): void {
		// Collect all conditional_logic rules from all fields.
		$rules = array();
		foreach ( $fields as $field ) {
			if ( empty( $field['conditional_logic'] ) || ! is_array( $field['conditional_logic'] ) ) {
				continue;
			}
			$cl = $field['conditional_logic'];
			if ( empty( $cl['enabled'] ) || empty( $cl['conditions'] ) || ! is_array( $cl['conditions'] ) ) {
				continue;
			}
			$rules[] = array(
				'fieldId'    => $field['id'] ?? '',
				'logic'      => in_array( $cl['logic'] ?? 'and', array( 'and', 'or' ), true ) ? $cl['logic'] : 'and',
				'conditions' => array_values(
					array_filter(
						array_map(
							static function ( $cond ) {
								if ( empty( $cond['triggerFieldId'] ) ) {
									return null;
								}
								return array(
									'triggerFieldId' => sanitize_text_field( $cond['triggerFieldId'] ),
									'operator'       => in_array( $cond['operator'] ?? 'equals', array( 'equals', 'not_equals', 'contains', 'not_empty', 'is_empty' ), true ) ? $cond['operator'] : 'equals',
									'value'          => $cond['value'] ?? '',
								);
							},
							$cl['conditions']
						)
					)
				),
			);
		}

		if ( empty( $rules ) ) {
			return;
		}

		if ( ! wp_script_is( 'xf-conditional', 'enqueued' ) ) {
			wp_enqueue_script(
				'xf-conditional',
				XTREMEFORMS_PLUGIN_URL . 'public/js/xf-conditional.js',
				array(),
				XTREMEFORMS_VERSION,
				true // footer = true, no render-blocking.
			);

			// Initialise data object before the script.
			wp_add_inline_script( 'xf-conditional', 'window.xlCondLogicData = {rules:[]};', 'before' );
		}

		// Merge rules for this form into the global data object.
		$inline = 'if(typeof xlCondLogicData !== "undefined"){' .
			'xlCondLogicData.rules = xlCondLogicData.rules.concat(' . wp_json_encode( $rules ) . ');' .
			'}';
		wp_add_inline_script( 'xf-conditional', $inline, 'before' );
	}

	/**
	 * Enqueue CSS and JS for the public form.
	 *
	 * @param array $form_settings Form settings (used for reCAPTCHA detection).
	 */
	private function enqueue_assets( array $form_settings = array() ): void {
		if ( ! wp_script_is( 'xf-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'xf-public',
				XTREMEFORMS_PLUGIN_URL . 'public/css/xf-public.css',
				array(),
				XTREMEFORMS_VERSION
			);

			wp_enqueue_script(
				'xf-public',
				XTREMEFORMS_PLUGIN_URL . 'public/js/xf-public.js',
				array(),
				XTREMEFORMS_VERSION,
				true // in_footer = true (no render-blocking).
			);

			// Determine if reCAPTCHA is enabled for this form.
			$recaptcha     = class_exists( 'XF_Spam' ) ? XF_Spam::get_recaptcha_settings() : array(
				'enabled'  => false,
				'site_key' => '',
			);
			$use_recaptcha = $recaptcha['enabled'] && ! empty( $form_settings['recaptcha_enabled'] ) && '1' === (string) $form_settings['recaptcha_enabled'];

			wp_localize_script(
				'xf-public',
				'xfPublicData',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					// Nonce for the impression beacon endpoint (xf_track_impression).
					'impressionNonce'  => wp_create_nonce( 'xf_impression_nonce' ),
					// Current post/page ID so the beacon can store post_id accurately.
					'postId'           => get_the_ID() ? (int) get_the_ID() : 0,
					'recaptchaEnabled' => $use_recaptcha ? '1' : '0',
					'recaptchaSiteKey' => $use_recaptcha ? esc_js( $recaptcha['site_key'] ) : '',
					'i18n'             => array(
						'submitting'    => __( 'Submitting…', 'xtreme-forms' ),
						'submit'        => __( 'Submit', 'xtreme-forms' ),
						'errorGeneric'  => __( 'Something went wrong. Please try again.', 'xtreme-forms' ),
						'fieldRequired' => __( 'This field is required.', 'xtreme-forms' ),
						'invalidEmail'  => __( 'Please enter a valid email address.', 'xtreme-forms' ),
					),
				)
			);
		}

		// Enqueue reCAPTCHA v3 JS only on pages with reCAPTCHA-enabled forms.
		// Only loaded if not already enqueued (multiple forms on same page).
		if ( class_exists( 'XF_Spam' ) ) {
			$recaptcha     = XF_Spam::get_recaptcha_settings();
			$use_recaptcha = $recaptcha['enabled'] && ! empty( $form_settings['recaptcha_enabled'] ) && '1' === (string) $form_settings['recaptcha_enabled'];

			if ( $use_recaptcha && ! wp_script_is( 'xf-recaptcha', 'enqueued' ) ) {
				wp_enqueue_script(
					'xf-recaptcha',
					'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recaptcha['site_key'] ),
					array(),
					null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
					true
				);
			}
		}

		// Enqueue Cloudflare Turnstile script if enabled globally.
		if ( class_exists( 'XF_Spam' ) ) {
			$turnstile = XF_Spam::get_turnstile_settings();
			if ( $turnstile['enabled'] && ! wp_script_is( 'xf-turnstile', 'enqueued' ) ) {
				// phpcs:disable PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Cloudflare Turnstile must be served from Cloudflare's CDN; self-hosting is not supported by Cloudflare.
				wp_enqueue_script(
					'xf-turnstile',
					'https://challenges.cloudflare.com/turnstile/v0/api.js',
					array(),
					null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
					true
				);
				// phpcs:enable PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
			}
		}
	}

	/**
	 * Render the HTML form.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $fields Field definitions.
	 * @param array $settings Form settings.
	 * @param array $errors Validation errors (field_id => message).
	 * @param array $values Previously submitted values.
	 * @return string HTML.
	 */
	public function render_form(
		int $form_id,
		array $fields,
		array $settings,
		array $errors = array(),
		array $values = array()
	): string {
		$submit_label = ! empty( $settings['submit_label'] )
			? esc_html( $settings['submit_label'] )
			: esc_html__( 'Submit', 'xtreme-forms' );

		$form_id_attr = 'xf-form-' . $form_id;
		$nonce        = wp_create_nonce( 'xf_form_submit_' . $form_id );

		$fields_html = '';
		foreach ( $fields as $field ) {
			$fields_html .= $this->render_field( $field, $errors, $values );
		}

		// Global error message (e.g. DB failure).
		$global_error_html = '';
		if ( ! empty( $errors['_global'] ) ) {
			$global_error_html = '<div class="xf-form-global-error" role="alert">' . esc_html( $errors['_global'] ) . '</div>';
		}

		// Honeypot: a visually hidden text field that humans won't fill but bots will.
		// Named to look like a real field to attract bots.
		$honeypot_name = 'xf_website_url';

		// Time-gate: record form render timestamp so server can reject sub-2-second submissions.
		$form_time = time();

		// Consent checkbox (GDPR).
		$consent_html = '';
		if ( ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'] ) {
			$consent_label   = ! empty( $settings['consent_label'] ) ? $settings['consent_label'] : __( 'I agree to the Privacy Policy', 'xtreme-forms' );
			$consent_url     = ! empty( $settings['consent_url'] ) ? esc_url( $settings['consent_url'] ) : '';
			$consent_error   = $errors['_consent'] ?? '';
			$consent_checked = ! empty( $values['_consent'] ) ? ' checked' : '';

			$label_html = '';
			if ( $consent_url ) {
				// Wrap the label text in a link to the privacy policy.
				$label_html = '<a href="' . $consent_url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $consent_label ) . '</a>';
			} else {
				$label_html = esc_html( $consent_label );
			}

			$consent_html  = '<div class="xf-field-wrap xf-field-consent' . ( $consent_error ? ' xf-field-error' : '' ) . '">';
			$consent_html .= '<label class="xf-consent-label">';
			$consent_html .= '<input type="checkbox" name="xf_consent" value="1"' . $consent_checked . ' required aria-required="true" id="xf-consent-' . esc_attr( $form_id ) . '">';
			$consent_html .= ' ' . $label_html;
			$consent_html .= '</label>';
			if ( $consent_error ) {
				$consent_html .= '<span class="xf-field-error-msg" role="alert">' . esc_html( $consent_error ) . '</span>';
			}
			$consent_html .= '</div>';
		}

		// Strip query string from current URL so POST data from a previous submission
		// does not appear in the recorded source URL (form has method="post" but
		// the source URL should always be the clean page path).
		$clean_uri    = strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), '?' );
		$source_url   = esc_url( home_url( $clean_uri ) );

		$html  = '<div class="xf-form-wrap" data-form-id="' . esc_attr( $form_id ) . '">';
		$html .= $global_error_html;
		$html .= '<form id="' . esc_attr( $form_id_attr ) . '" class="xf-form" method="post">';
		$html .= '<input type="hidden" name="action" value="xl_submit_form">';
		$html .= '<input type="hidden" name="xf_form_id" value="' . esc_attr( $form_id ) . '">';
		$html .= '<input type="hidden" name="xf_nonce" value="' . esc_attr( $nonce ) . '">';
		$html .= '<input type="hidden" name="xf_source_url" value="' . $source_url . '">';
		$html .= '<input type="hidden" name="xf_form_time" value="' . esc_attr( $form_time ) . '">';
		// reCAPTCHA token field — populated by JS before submit.
		$html .= '<input type="hidden" name="xf_recaptcha_token" id="xf-recaptcha-token-' . esc_attr( $form_id ) . '" value="">';
		// Honeypot field: visually hidden using CSS (position:absolute, clip, 1px dimensions).
		// aria-hidden is intentionally NOT used so that screen readers and bots still
		// encounter the field — only sighted users won't see it (per spec requirement).
		$html .= '<div class="xf-hp-field">';
		$html .= '<label for="xf-hp-' . esc_attr( $form_id ) . '" class="xf-hp-label"></label>';
		$html .= '<input type="text" id="xf-hp-' . esc_attr( $form_id ) . '" name="' . esc_attr( $honeypot_name ) . '" value="" autocomplete="off" tabindex="-1">';
		$html .= '</div>';
		$html .= $fields_html;
		$html .= $consent_html;
		// Cloudflare Turnstile widget — rendered automatically when Turnstile JS loads.
		if ( class_exists( 'XF_Spam' ) ) {
			$xf_turnstile = XF_Spam::get_turnstile_settings();
			if ( $xf_turnstile['enabled'] ) {
				$html .= '<div class="xf-turnstile-wrap">';
				$html .= '<div class="cf-turnstile"';
				$html .= ' data-sitekey="' . esc_attr( $xf_turnstile['site_key'] ) . '"';
				$html .= ' data-response-field-name="xf_turnstile_token"';
				$html .= ' data-theme="' . esc_attr( $xf_turnstile['theme'] ) . '"';
				$html .= ' data-size="' . esc_attr( $xf_turnstile['size'] ) . '"';
				$html .= '></div>';
				$html .= '</div>';
			}
		}
		$html .= '<div class="xf-form-submit">';
		$html .= '<button type="submit" class="xf-btn-submit">' . $submit_label . '</button>';
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a single form field.
	 *
	 * @param array $field Field definition.
	 * @param array $errors Validation errors.
	 * @param array $values Previously submitted values.
	 * @return string HTML.
	 */
	private function render_field( array $field, array $errors, array $values ): string {
		$field_id    = esc_attr( $field['id'] ?? '' );
		$field_type  = $field['type'] ?? 'text';
		$field_label = $field['label'] ?? '';
		$placeholder = $field['placeholder'] ?? '';
		$required    = ! empty( $field['required'] );
		$input_name  = 'xf_field[' . $field_id . ']';
		$input_id    = 'xf-field-' . $field_id;
		$value       = $values[ $field['id'] ?? '' ] ?? '';
		$error       = $errors[ $field['id'] ?? '' ] ?? '';

		// Hidden fields render without wrapper/label.
		if ( 'hidden' === $field_type ) {
			return '<input type="hidden" name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( $field['default_value'] ?? '' ) . '">';
		}

		$required_attr = $required ? ' required aria-required="true"' : '';
		$error_id      = 'xf-error-' . $field_id;
		$error_class   = $error ? ' xf-field-error' : '';
		$aria_desc     = $error ? ' aria-describedby="' . esc_attr( $error_id ) . '"' : '';

		$html = '<div class="xf-field-wrap xf-field-' . esc_attr( $field_type ) . $error_class . '" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '"' . ( $required ? ' data-required="1"' : '' ) . '>';

		if ( '' !== $field_label ) {
			$required_star = $required ? ' <span class="xf-required" aria-hidden="true">*</span>' : '';
			$html         .= '<label for="' . esc_attr( $input_id ) . '" class="xf-label">' . esc_html( $field_label ) . $required_star . '</label>';
		}

		switch ( $field_type ) {
			case 'text':
				$html .= '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input">';
				break;

			case 'email':
				$html .= '<input type="email" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input">';
				break;

			case 'phone':
				$html .= '<input type="tel" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input">';
				break;

			case 'textarea':
				$html .= '<textarea id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-textarea" rows="4">' . esc_textarea( $value ) . '</textarea>';
				break;

			case 'date':
				$html .= '<input type="date" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '"' . $required_attr . $aria_desc . ' class="xf-input">';
				break;

			case 'dropdown':
				$html   .= '<select id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '"' . $required_attr . $aria_desc . ' class="xf-select">';
				$html   .= '<option value="">' . esc_html__( '-- Select --', 'xtreme-forms' ) . '</option>';
				$options = $field['options'] ?? array();
				foreach ( $options as $option ) {
					$option_val = esc_attr( $option );
					$selected   = selected( $value, $option, false );
					$html      .= '<option value="' . $option_val . '"' . $selected . '>' . esc_html( $option ) . '</option>';
				}
				$html .= '</select>';
				break;

			case 'checkbox':
				$options       = $field['options'] ?? array();
				$selected_vals = is_array( $value ) ? $value : ( '' !== $value ? array( $value ) : array() );
				$html         .= '<div class="xf-checkbox-group"' . $aria_desc . '>';
				foreach ( $options as $idx => $option ) {
					$cb_id      = esc_attr( $input_id . '-' . $idx );
					$cb_checked = in_array( $option, $selected_vals, true ) ? ' checked' : '';
					$html      .= '<label class="xf-checkbox-label"><input type="checkbox" id="' . $cb_id . '" name="' . esc_attr( $input_name ) . '[]" value="' . esc_attr( $option ) . '"' . $cb_checked . '> ' . esc_html( $option ) . '</label>';
				}
				$html .= '</div>';
				break;

			case 'radio':
				$options = $field['options'] ?? array();
				$html   .= '<div class="xf-radio-group"' . $aria_desc . ( $required ? ' role="radiogroup"' : '' ) . '>';
				foreach ( $options as $idx => $option ) {
					$rb_id      = esc_attr( $input_id . '-' . $idx );
					$rb_checked = ( $value === $option ) ? ' checked' : '';
					$html      .= '<label class="xf-radio-label"><input type="radio" id="' . $rb_id . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $option ) . '"' . $rb_checked . ( $required ? ' required' : '' ) . '> ' . esc_html( $option ) . '</label>';
				}
				$html .= '</div>';
				break;

			default:
				$html .= '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input">';
		}

		if ( $error ) {
			$html .= '<span id="' . esc_attr( $error_id ) . '" class="xf-field-error-msg" role="alert">' . esc_html( $error ) . '</span>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the inline thank-you message (replaces form).
	 *
	 * @param string $message Thank-you message text.
	 * @return string HTML.
	 */
	public static function render_thank_you( string $message ): string {
		return '<div class="xf-thank-you" role="status"><p>' . wp_kses_post( $message ) . '</p></div>';
	}
}
