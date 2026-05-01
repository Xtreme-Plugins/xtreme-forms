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

		return $this->render_form( $form_id, $fields, $settings, array(), array(), (string) ( $form->name ?? '' ) );
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
				$form_html = $this->render_form( $form_id, $fields, $settings, array(), array(), (string) ( $form->name ?? '' ) );
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
		array $values = array(),
		string $form_name = ''
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

		$center_form  = ! empty( $settings['center_form'] ) && '1' === (string) $settings['center_form'];
		$center_class = $center_form ? ' xf-form-centered' : '';

		$remove_bg    = ! empty( $settings['remove_background'] ) && '1' === (string) $settings['remove_background'];
		$bg_class     = $remove_bg ? ' xf-form-no-bg' : '';

		// Accent color — shared by submit button + slider thumb/fill/value.
		$accent_color = ! empty( $settings['submit_bg_color'] ) ? (string) $settings['submit_bg_color'] : '#1A73E8';
		$wrap_style   = ' style="--xf-accent: ' . esc_attr( $accent_color ) . ';"';

		$html  = '<div class="xf-form-wrap' . $center_class . $bg_class . '" data-form-id="' . esc_attr( $form_id ) . '"' . $wrap_style . '>';
		$html .= self::build_form_jsonld( $form_id, $form_name, $source_url );
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
		// Submit button layout.
		$submit_float      = ! empty( $settings['submit_float'] ) && '1' === (string) $settings['submit_float'];
		$submit_width      = isset( $settings['submit_width'] ) ? (float) $settings['submit_width'] : 100;
		$submit_align      = in_array( $settings['submit_align'] ?? 'left', array( 'left', 'center', 'right' ), true )
			? $settings['submit_align']
			: 'left';
		$submit_bg_color   = ! empty( $settings['submit_bg_color'] ) ? $settings['submit_bg_color'] : '#1A73E8';
		$submit_text_color = ! empty( $settings['submit_text_color'] ) ? $settings['submit_text_color'] : '#ffffff';
		$submit_btn_size   = in_array( $settings['submit_btn_size'] ?? 'md', array( 'sm', 'md', 'lg', 'xl' ), true )
			? $settings['submit_btn_size']
			: 'md';

		$submit_classes = 'xf-form-submit';
		$submit_style   = '';
		if ( $submit_float ) {
			$submit_classes .= ' xf-field-float';
			$submit_style    = ' style="width:' . $submit_width . '%;"';
		}
		if ( 'center' === $submit_align && ! $submit_float ) {
			$submit_classes .= ' xf-submit-center';
		} elseif ( 'right' === $submit_align && ! $submit_float ) {
			$submit_classes .= ' xf-submit-right';
		}

		$submit_full_width = ! empty( $settings['submit_full_width'] ) && '1' === (string) $settings['submit_full_width'];

		$btn_style      = 'background:' . esc_attr( $submit_bg_color ) . ';color:' . esc_attr( $submit_text_color ) . ';';
		$btn_size_class = 'xf-btn-size-' . esc_attr( $submit_btn_size );
		if ( $submit_full_width ) {
			$btn_size_class .= ' xf-btn-full-width';
		}

		$html .= '<div class="' . esc_attr( $submit_classes ) . '"' . $submit_style . '>';
		$html .= '<button type="submit" class="xf-btn-submit ' . $btn_size_class . '" style="' . $btn_style . '">' . $submit_label . '</button>';
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

		// Section header: heading text + optional subtitle. No label/input scaffolding.
		if ( 'header' === $field_type ) {
			$subtitle = isset( $field['subtitle'] ) ? (string) $field['subtitle'] : '';
			$out  = '<div class="xf-field-wrap xf-field-header" data-field-id="' . esc_attr( $field['id'] ?? '' ) . '">';
			$out .= '<h3 class="xf-heading">' . esc_html( $field_label ) . '</h3>';
			if ( '' !== trim( $subtitle ) ) {
				$out .= '<p class="xf-subtitle">' . esc_html( $subtitle ) . '</p>';
			}
			$out .= '</div>';
			return $out;
		}

		$required_attr = $required ? ' required aria-required="true"' : '';
		$error_id      = 'xf-error-' . $field_id;
		$error_class   = $error ? ' xf-field-error' : '';
		$aria_desc     = $error ? ' aria-describedby="' . esc_attr( $error_id ) . '"' : '';

		$float_class  = ! empty( $field['float'] ) ? ' xf-field-float' : '';
		$width_style  = '';
		if ( ! empty( $field['float'] ) && isset( $field['width'] ) ) {
			$w = (float) $field['width'];
			if ( $w > 0 && $w <= 100 ) {
				$width_style = ' style="width:' . $w . '%;"';
			}
		}

		$html = '<div class="xf-field-wrap xf-field-' . esc_attr( $field_type ) . $float_class . $error_class . '"' . $width_style . ' data-field-id="' . esc_attr( $field['id'] ?? '' ) . '"' . ( $required ? ' data-required="1"' : '' ) . '>';

		// Field label. For group fields (radio / checkbox), use a span with an id
		// instead of <label for> — labels can only target a single input, and the
		// group is referenced via aria-labelledby below.
		$is_group_field = in_array( $field_type, array( 'radio', 'checkbox' ), true );
		$label_id       = 'xf-label-' . $field_id;
		if ( '' !== $field_label ) {
			$required_star = $required ? ' <span class="xf-required" aria-hidden="true">*</span>' : '';
			if ( $is_group_field ) {
				$html .= '<span class="xf-label" id="' . esc_attr( $label_id ) . '">' . esc_html( $field_label ) . $required_star . '</span>';
			} else {
				$html .= '<label for="' . esc_attr( $input_id ) . '" class="xf-label">' . esc_html( $field_label ) . $required_star . '</label>';
			}
		}

		// For groups, also expose the label via aria-labelledby on the group
		// container; rendered cases inject this fragment before $aria_desc.
		$aria_group = ( $is_group_field && '' !== $field_label )
			? ' role="group" aria-labelledby="' . esc_attr( $label_id ) . '"'
			: '';

		switch ( $field_type ) {
			case 'text': {
				$rows = max( 1, (int) ( $field['rows'] ?? 1 ) );
				// Heuristic: pick a sensible autocomplete token based on the
				// label / placeholder so browsers can autofill. Helps form-fill
				// UX significantly on mobile.
				$ac_attr = self::guess_autocomplete_attr( $field_label, $placeholder );
				if ( $rows > 1 ) {
					$html .= '<textarea id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-textarea" rows="' . $rows . '" spellcheck="true">' . esc_textarea( $value ) . '</textarea>';
				} else {
					$html .= '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input"' . $ac_attr . '>';
				}
				break;
			}

			case 'email':
				$html .= '<input type="email" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input" autocomplete="email" inputmode="email" spellcheck="false" autocapitalize="off">';
				break;

			case 'phone':
				$html .= '<input type="tel" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input" autocomplete="tel" inputmode="tel">';
				break;

			case 'textarea': {
				$rows = max( 1, (int) ( $field['rows'] ?? 4 ) );
				$html .= '<textarea id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-textarea" rows="' . $rows . '" spellcheck="true">' . esc_textarea( $value ) . '</textarea>';
				break;
			}

			case 'date':
				$html .= '<input type="date" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '"' . $required_attr . $aria_desc . ' class="xf-input">';
				break;

			case 'zipcode':
				$zip_placeholder = '' !== $placeholder ? $placeholder : '12345';
				$html           .= '<input type="text" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $zip_placeholder ) . '"' . $required_attr . $aria_desc . ' class="xf-input" inputmode="numeric" pattern="\d{5}(-\d{4})?" maxlength="10" autocomplete="postal-code">';
				break;

			case 'slider': {
				$slider_min  = isset( $field['min'] )  ? (float) $field['min']  : 0.0;
				$slider_max  = isset( $field['max'] )  ? (float) $field['max']  : 10.0;
				$slider_step = isset( $field['step'] ) ? (float) $field['step'] : 1.0;
				if ( $slider_max <= $slider_min ) { $slider_max = $slider_min + 1.0; }
				if ( $slider_step <= 0 )          { $slider_step = 1.0; }

				$slider_default = (string) ( $field['default_value'] ?? ( $field['defaultValue'] ?? '' ) );
				if ( '' === $slider_default ) { $slider_default = (string) $slider_min; }

				// Effective value = submitted value if present, else the configured default.
				$slider_val = ( '' !== (string) $value ) ? (string) $value : $slider_default;

				$fmt = function ( $n ) {
					return rtrim( rtrim( number_format( (float) $n, 4, '.', '' ), '0' ), '.' );
				};

				$html .= '<div class="xf-slider" data-xf-slider>';
				$html .= '<div class="xf-slider-row">';
				$html .= '<input type="range" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '" ';
				$html .= 'min="' . esc_attr( $fmt( $slider_min ) ) . '" max="' . esc_attr( $fmt( $slider_max ) ) . '" step="' . esc_attr( $fmt( $slider_step ) ) . '" ';
				$html .= 'value="' . esc_attr( $fmt( $slider_val ) ) . '"' . $required_attr . $aria_desc . ' class="xf-input-slider" data-xf-slider-input>';
				$html .= '</div>';
				$html .= '<div class="xf-slider-readout">' . esc_html__( 'Selected:', 'xtreme-forms' ) . ' <span class="xf-slider-value" data-xf-slider-value>' . esc_html( $fmt( $slider_val ) ) . '</span></div>';
				$html .= '</div>';
				break;
			}

			case 'dropdown':
				$html         .= '<select id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '"' . $required_attr . $aria_desc . ' class="xf-select">';
				$placeholder_text = '' !== $placeholder ? $placeholder : esc_html__( '-- Select --', 'xtreme-forms' );
				$html            .= '<option value="">' . esc_html( $placeholder_text ) . '</option>';
				$options          = $field['options'] ?? array();
				$default_value    = (string) ( $field['default_value'] ?? ( $field['defaultValue'] ?? '' ) );
				// If no user-submitted value, fall back to the configured default.
				$effective_value  = ( '' === (string) $value && '' !== $default_value ) ? $default_value : $value;
				foreach ( $options as $option ) {
					$option_val = esc_attr( $option );
					$selected   = selected( $effective_value, $option, false );
					$html      .= '<option value="' . $option_val . '"' . $selected . '>' . esc_html( $option ) . '</option>';
				}
				$html .= '</select>';
				break;

			case 'checkbox': {
				$options       = $field['options'] ?? array();
				$qty_mode      = ! empty( $field['quantity'] );

				// Re-hydrate previously submitted state.
				// In qty mode the saved value is a "Label ×N, Label ×N" string; we
				// parse it back into an option => qty map so reloads preserve state.
				$selected_qty = array();
				$selected_vals = array();
				if ( $qty_mode ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $k => $v ) {
							if ( is_string( $k ) ) {
								$selected_qty[ $k ] = max( 1, (int) $v );
							}
						}
					} elseif ( is_string( $value ) && '' !== $value ) {
						foreach ( explode( ',', $value ) as $part ) {
							$part = trim( $part );
							if ( '' === $part ) { continue; }
							if ( preg_match( '/^(.*?)\s*[×x]\s*(\d+)$/u', $part, $m ) ) {
								$selected_qty[ trim( $m[1] ) ] = max( 1, (int) $m[2] );
							} else {
								$selected_qty[ $part ] = 1;
							}
						}
					}
				} else {
					$selected_vals = is_array( $value ) ? $value : ( '' !== $value ? array( $value ) : array() );
				}

				$cols       = max( 1, min( 4, (int) ( $field['columns'] ?? 1 ) ) );
				$cols_class = $cols > 1 ? ' xf-cols-' . $cols : '';
				$qty_class  = $qty_mode ? ' xf-with-qty' : '';
				$html      .= '<div class="xf-checkbox-group' . $cols_class . $qty_class . '"' . $aria_group . $aria_desc . ( $qty_mode ? ' data-xf-qty-group' : '' ) . '>';

				foreach ( $options as $idx => $option ) {
					$cb_id = esc_attr( $input_id . '-' . $idx );

					if ( $qty_mode ) {
						$is_checked = array_key_exists( $option, $selected_qty );
						$qty_val    = $is_checked ? (int) $selected_qty[ $option ] : 1;
						$row_class  = 'xf-qty-row' . ( $is_checked ? ' is-checked' : '' );
						// Hidden input is enabled only when checked, named like xf_field[fid][option]=qty.
						$hidden_name    = esc_attr( $input_name ) . '[' . esc_attr( $option ) . ']';
						$hidden_attrs   = $is_checked ? '' : ' disabled';
						$stepper_hidden = $is_checked ? '' : ' hidden';

						$html .= '<div class="' . $row_class . '" data-xf-qty-row>';
						$html .= '<input type="checkbox" class="xf-qty-cb" id="' . $cb_id . '" data-xf-qty-cb' . ( $is_checked ? ' checked' : '' ) . '>';
						$html .= '<label class="xf-qty-label" for="' . $cb_id . '">' . esc_html( $option ) . '</label>';
						$html .= '<span class="xf-qty-stepper"' . $stepper_hidden . ' data-xf-qty-stepper>';
						$html .= '<button type="button" class="xf-qty-btn xf-qty-dec" data-xf-qty-dec aria-label="' . esc_attr__( 'Decrease quantity', 'xtreme-forms' ) . '">−</button>';
						$html .= '<span class="xf-qty-val" data-xf-qty-val aria-live="polite" aria-atomic="true">' . (int) $qty_val . '</span>';
						$html .= '<button type="button" class="xf-qty-btn xf-qty-inc" data-xf-qty-inc aria-label="' . esc_attr__( 'Increase quantity', 'xtreme-forms' ) . '">+</button>';
						$html .= '</span>';
						$html .= '<input type="hidden" class="xf-qty-input" name="' . $hidden_name . '" value="' . (int) $qty_val . '"' . $hidden_attrs . ' data-xf-qty-input>';
						$html .= '</div>';
					} else {
						$cb_checked = in_array( $option, $selected_vals, true ) ? ' checked' : '';
						$html      .= '<label class="xf-checkbox-label"><input type="checkbox" id="' . $cb_id . '" name="' . esc_attr( $input_name ) . '[]" value="' . esc_attr( $option ) . '"' . $cb_checked . '> ' . esc_html( $option ) . '</label>';
					}
				}
				$html .= '</div>';
				break;
			}

			case 'radio':
				$options = $field['options'] ?? array();
				// radiogroup role takes precedence over generic group when required.
				$radio_role = $required
					? ' role="radiogroup"' . ( '' !== $field_label ? ' aria-labelledby="' . esc_attr( $label_id ) . '"' : '' )
					: $aria_group;
				$html      .= '<div class="xf-radio-group"' . $radio_role . $aria_desc . '>';
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

	/**
	 * Build a JSON-LD <script> snippet describing the form as a CommunicateAction.
	 * Helps search engines understand the page hosts a contactable / lead form.
	 *
	 * @param int    $form_id    Form ID.
	 * @param string $form_name  Human-readable form name.
	 * @param string $source_url URL of the page hosting the form.
	 * @return string <script> tag (or empty string if name missing).
	 */
	private static function build_form_jsonld( int $form_id, string $form_name, string $source_url ): string {
		$name = trim( $form_name );
		if ( '' === $name ) {
			return '';
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'CommunicateAction',
			'@id'      => $source_url . '#xf-form-' . $form_id,
			'name'     => $name,
			'target'   => array(
				'@type'          => 'EntryPoint',
				'urlTemplate'    => $source_url,
				'actionPlatform' => array(
					'http://schema.org/DesktopWebPlatform',
					'http://schema.org/MobileWebPlatform',
				),
			),
		);

		// JSON_UNESCAPED_SLASHES keeps URLs readable; JSON_UNESCAPED_UNICODE for i18n.
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return '';
		}

		// </script> escape: defence-in-depth — wp_json_encode escapes "<" only via
		// JSON_HEX_TAG which we're not using to keep URLs readable, so manually
		// guard against an embedded "</script>" sequence.
		$json = str_replace( '</', '<\/', $json );

		return '<script type="application/ld+json" class="xf-jsonld">' . $json . '</script>';
	}

	/**
	 * Guess an HTML autocomplete attribute for a generic text field, based on
	 * its label / placeholder. Lets the browser autofill without requiring
	 * the form author to configure each field.
	 *
	 * @param string $label       Field label.
	 * @param string $placeholder Field placeholder.
	 * @return string Attribute fragment ready to concatenate (e.g. ' autocomplete="given-name"'), or '' if no match.
	 */
	private static function guess_autocomplete_attr( string $label, string $placeholder ): string {
		$hay = strtolower( trim( $label . ' ' . $placeholder ) );
		if ( '' === $hay ) {
			return '';
		}

		// Order matters: more specific patterns first.
		$rules = array(
			'given-name'      => array( 'first name', 'firstname', 'given name' ),
			'family-name'     => array( 'last name', 'lastname', 'surname', 'family name' ),
			'name'            => array( 'full name', 'your name', 'company name', 'business name' ),
			'organization'    => array( 'company', 'organization', 'organisation', 'business' ),
			'street-address'  => array( 'street address', 'address line', 'mailing address' ),
			'address-line1'   => array( 'address 1', 'address1', 'address line 1' ),
			'address-line2'   => array( 'address 2', 'address2', 'apt', 'apartment', 'suite', 'unit' ),
			'address-level2'  => array( 'city', 'town' ),
			'address-level1'  => array( 'state', 'province', 'region' ),
			'country-name'    => array( 'country' ),
			'postal-code'     => array( 'zip code', 'zipcode', 'postal code', 'post code' ),
			'bday'            => array( 'birthday', 'birth date', 'date of birth', 'dob' ),
			'url'             => array( 'website', 'website url', 'url' ),
		);
		foreach ( $rules as $token => $needles ) {
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $hay, $needle ) ) {
					return ' autocomplete="' . $token . '"';
				}
			}
		}

		// Fallback: bare "name" word — only if it's a likely person name field.
		if ( preg_match( '/\bname\b/', $hay ) ) {
			return ' autocomplete="name"';
		}

		return '';
	}
}
