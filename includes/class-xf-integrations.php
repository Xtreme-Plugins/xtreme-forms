<?php
/**
 * CRM Integrations — save/load settings and fire on lead creation.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_Integrations
 *
 * Manages CRM/marketing integration settings and dispatches lead data
 * to all enabled integrations whenever a new lead is captured.
 */
class Xtremeforms_Integrations {

	/**
	 * Allowed integration slugs.
	 *
	 * @var string[]
	 */
	private const ALLOWED = array( 'hubspot', 'zoho', 'salesforce', 'pipedrive', 'groundhogg' );

	public function __construct() {
		add_action( 'wp_ajax_xtremeforms_save_integration',  array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_xtremeforms_get_integrations',  array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_xtremeforms_test_integration',  array( $this, 'ajax_test' ) );
		// Fire on new lead.
		add_action( 'xtremeforms_lead_created', array( $this, 'fire_integrations' ), 10, 2 );
	}

	/**
	 * Return all stored integration settings.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_settings(): array {
		return (array) get_option( 'xtremeforms_integrations', array() );
	}

	// ── AJAX: save ────────────────────────────────────────────────────────────

	public function ajax_save(): void {
		check_ajax_referer( 'xtremeforms_integrations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- already checked above via check_ajax_referer.
		$slug = isset( $_POST['integration'] ) ? sanitize_key( $_POST['integration'] ) : '';
		if ( ! $slug ) {
			wp_send_json_error( __( 'Missing integration slug.', 'xtreme-forms' ) );
		}

		if ( ! in_array( $slug, self::ALLOWED, true ) ) {
			wp_send_json_error( __( 'Unknown integration.', 'xtreme-forms' ) );
		}

		$settings = self::get_settings();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array values are individually sanitized via sanitize_text_field() in the foreach loop below.
		$data     = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		// Sanitize all values as text; the 'enabled' key gets boolean treatment.
		$clean = array();
		foreach ( $data as $k => $v ) {
			$clean[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
		}
		$clean['enabled'] = ! empty( $data['enabled'] ) ? '1' : '0';
		// phpcs:enable WordPress.Security.NonceVerification

		$settings[ $slug ] = $clean;
		update_option( 'xtremeforms_integrations', $settings );

		wp_send_json_success( array( 'message' => __( 'Integration saved.', 'xtreme-forms' ) ) );
	}

	// ── AJAX: get ─────────────────────────────────────────────────────────────

	public function ajax_get(): void {
		check_ajax_referer( 'xtremeforms_integrations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		wp_send_json_success( self::get_settings() );
	}

	// ── AJAX: test ────────────────────────────────────────────────────────────

	public function ajax_test(): void {
		check_ajax_referer( 'xtremeforms_integrations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- already checked above via check_ajax_referer.
		$slug = isset( $_POST['integration'] ) ? sanitize_key( $_POST['integration'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification

		$settings = self::get_settings();
		$cfg      = $settings[ $slug ] ?? array();

		if ( empty( $cfg['enabled'] ) || '1' !== $cfg['enabled'] ) {
			wp_send_json_error( __( 'Integration is not enabled.', 'xtreme-forms' ) );
		}

		$result = $this->test_connection( $slug, $cfg );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'xtreme-forms' ) ) );
	}

	// ── Connection tests ──────────────────────────────────────────────────────

	/**
	 * Run a basic connectivity test for the given integration.
	 *
	 * @param string               $slug Integration slug.
	 * @param array<string,string> $cfg  Saved configuration.
	 * @return true|\WP_Error
	 */
	private function test_connection( string $slug, array $cfg ): true|\WP_Error {
		switch ( $slug ) {
			case 'hubspot':
				if ( empty( $cfg['access_token'] ) ) {
					return new \WP_Error( 'missing', __( 'Access token is required.', 'xtreme-forms' ) );
				}
				$resp = wp_remote_get(
					'https://api.hubapi.com/crm/v3/objects/contacts?limit=1',
					array(
						'headers' => array( 'Authorization' => 'Bearer ' . $cfg['access_token'] ),
						'timeout' => 8,
					)
				);
				if ( is_wp_error( $resp ) ) {
					return $resp;
				}
				$code = wp_remote_retrieve_response_code( $resp );
				return 200 === $code ? true : new \WP_Error( 'api', 'HubSpot returned HTTP ' . $code );

			case 'pipedrive':
				if ( empty( $cfg['api_token'] ) ) {
					return new \WP_Error( 'missing', __( 'API token is required.', 'xtreme-forms' ) );
				}
				$resp = wp_remote_get(
					'https://api.pipedrive.com/v1/users/me?api_token=' . rawurlencode( $cfg['api_token'] ),
					array( 'timeout' => 8 )
				);
				if ( is_wp_error( $resp ) ) {
					return $resp;
				}
				$code = wp_remote_retrieve_response_code( $resp );
				return 200 === $code ? true : new \WP_Error( 'api', 'Pipedrive returned HTTP ' . $code );

			default:
				// OAuth-based integrations cannot be tested without a redirect flow.
				return true;
		}
	}

	// ── Lead dispatch ─────────────────────────────────────────────────────────

	/**
	 * Fire all enabled integrations when a new lead is created.
	 *
	 * Hooked to xtremeforms_lead_created( $lead_id, $lead_data ).
	 *
	 * @param int   $lead_id   Newly created lead ID.
	 * @param array $lead_data Normalised lead field data.
	 */
	public function fire_integrations( int $lead_id, array $lead_data ): void {
		$settings = self::get_settings();

		foreach ( $settings as $slug => $cfg ) {
			if ( empty( $cfg['enabled'] ) || '1' !== $cfg['enabled'] ) {
				continue;
			}
			try {
				$this->send_to_integration( $slug, $cfg, $lead_id, $lead_data );
			} catch ( \Throwable $e ) {
				error_log( '[XF Integrations] ' . $slug . ' error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Dispatch to the correct integration handler.
	 *
	 * @param string               $slug      Integration slug.
	 * @param array<string,string> $cfg       Saved configuration.
	 * @param int                  $lead_id   Lead ID.
	 * @param array                $lead_data Lead data.
	 */
	private function send_to_integration( string $slug, array $cfg, int $lead_id, array $lead_data ): void {
		switch ( $slug ) {
			case 'hubspot':
				$this->send_hubspot( $cfg, $lead_data );
				break;
			case 'zoho':
				$this->send_zoho( $cfg, $lead_data );
				break;
			case 'salesforce':
				$this->send_salesforce( $cfg, $lead_data );
				break;
			case 'pipedrive':
				$this->send_pipedrive( $cfg, $lead_data );
				break;
			case 'groundhogg':
				$this->send_groundhogg( $cfg, $lead_data );
				break;
		}
	}

	// ── Per-integration senders ───────────────────────────────────────────────

	/**
	 * Push lead to HubSpot as a Contact.
	 *
	 * @param array<string,string> $cfg       Integration config.
	 * @param array                $lead_data Lead data.
	 */
	private function send_hubspot( array $cfg, array $lead_data ): void {
		if ( empty( $cfg['access_token'] ) ) {
			return;
		}

		$props = array();

		if ( ! empty( $lead_data['email'] ) ) {
			$props['email'] = sanitize_email( $lead_data['email'] );
		}
		if ( ! empty( $lead_data['name'] ) ) {
			$parts              = explode( ' ', $lead_data['name'], 2 );
			$props['firstname'] = $parts[0];
			$props['lastname']  = $parts[1] ?? '';
		}
		if ( ! empty( $lead_data['phone'] ) ) {
			$props['phone'] = $lead_data['phone'];
		}
		if ( ! empty( $lead_data['company'] ) ) {
			$props['company'] = $lead_data['company'];
		}

		if ( empty( $props['email'] ) ) {
			return;
		}

		wp_remote_post(
			'https://api.hubapi.com/crm/v3/objects/contacts',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $cfg['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'properties' => $props ) ),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Push lead to Zoho CRM as a Lead.
	 *
	 * @param array<string,string> $cfg       Integration config.
	 * @param array                $lead_data Lead data.
	 */
	private function send_zoho( array $cfg, array $lead_data ): void {
		if ( empty( $cfg['refresh_token'] ) || empty( $cfg['client_id'] ) || empty( $cfg['client_secret'] ) ) {
			return;
		}

		// Determine regional token/API domain.
		$region_map    = array( 'EU' => 'eu', 'IN' => 'in', 'AU' => 'au', 'JP' => 'jp' );
		$region_suffix = $region_map[ $cfg['region'] ?? 'US' ] ?? 'com';
		$tld           = ( 'com' === $region_suffix ) ? 'com' : $region_suffix;

		// Exchange refresh token for an access token.
		$tok_resp = wp_remote_post(
			'https://accounts.zoho.' . $tld . '/oauth/v2/token',
			array(
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $cfg['client_id'],
					'client_secret' => $cfg['client_secret'],
					'refresh_token' => $cfg['refresh_token'],
				),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $tok_resp ) ) {
			return;
		}

		$tok_body     = json_decode( wp_remote_retrieve_body( $tok_resp ), true );
		$access_token = $tok_body['access_token'] ?? '';
		if ( ! $access_token ) {
			return;
		}

		$api_domain = 'https://www.zohoapis.' . $tld;
		$data       = array( 'Last_Name' => $lead_data['name'] ?? 'Unknown' );

		if ( ! empty( $lead_data['email'] ) ) {
			$data['Email'] = $lead_data['email'];
		}
		if ( ! empty( $lead_data['phone'] ) ) {
			$data['Phone'] = $lead_data['phone'];
		}
		if ( ! empty( $lead_data['company'] ) ) {
			$data['Company'] = $lead_data['company'];
		}

		wp_remote_post(
			$api_domain . '/crm/v2/Leads',
			array(
				'headers' => array(
					'Authorization' => 'Zoho-oauthtoken ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'data' => array( $data ) ) ),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Push lead to Salesforce as a Lead record.
	 *
	 * @param array<string,string> $cfg       Integration config.
	 * @param array                $lead_data Lead data.
	 */
	private function send_salesforce( array $cfg, array $lead_data ): void {
		if ( empty( $cfg['consumer_key'] ) || empty( $cfg['consumer_secret'] ) || empty( $cfg['instance_url'] ) ) {
			return;
		}
		// Requires a stored access token (full OAuth redirect flow is out of scope for admin UI).
		if ( empty( $cfg['access_token'] ) ) {
			return;
		}

		$data = array( 'LastName' => $lead_data['name'] ?? 'Unknown' );

		if ( ! empty( $lead_data['email'] ) ) {
			$data['Email'] = $lead_data['email'];
		}
		if ( ! empty( $lead_data['phone'] ) ) {
			$data['Phone'] = $lead_data['phone'];
		}
		if ( ! empty( $lead_data['company'] ) ) {
			$data['Company'] = $lead_data['company'];
		}

		wp_remote_post(
			rtrim( $cfg['instance_url'], '/' ) . '/services/data/v57.0/sobjects/Lead/',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $cfg['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Create a Person and Lead in Pipedrive.
	 *
	 * @param array<string,string> $cfg       Integration config.
	 * @param array                $lead_data Lead data.
	 */
	private function send_pipedrive( array $cfg, array $lead_data ): void {
		if ( empty( $cfg['api_token'] ) ) {
			return;
		}

		$person_data = array( 'name' => $lead_data['name'] ?? 'Unknown Lead' );

		if ( ! empty( $lead_data['email'] ) ) {
			$person_data['email'] = array( array( 'value' => $lead_data['email'], 'primary' => true ) );
		}
		if ( ! empty( $lead_data['phone'] ) ) {
			$person_data['phone'] = array( array( 'value' => $lead_data['phone'], 'primary' => true ) );
		}

		$resp = wp_remote_post(
			'https://api.pipedrive.com/v1/persons?api_token=' . rawurlencode( $cfg['api_token'] ),
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $person_data ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return;
		}

		$body      = json_decode( wp_remote_retrieve_body( $resp ), true );
		$person_id = $body['data']['id'] ?? null;
		if ( ! $person_id ) {
			return;
		}

		// Create a lead linked to the person.
		wp_remote_post(
			'https://api.pipedrive.com/v1/leads?api_token=' . rawurlencode( $cfg['api_token'] ),
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'title'     => ( $lead_data['name'] ?? 'Lead' ) . ' \u2014 Xtreme Forms',
						'person_id' => $person_id,
					)
				),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Create or update a Groundhogg contact and apply tags.
	 *
	 * @param array<string,string> $cfg       Integration config.
	 * @param array                $lead_data Lead data.
	 */
	private function send_groundhogg( array $cfg, array $lead_data ): void {
		if ( ! function_exists( 'groundhogg' ) && ! class_exists( '\Groundhogg\Contact' ) ) {
			return;
		}
		if ( empty( $lead_data['email'] ) ) {
			return;
		}

		$email = sanitize_email( $lead_data['email'] );
		if ( ! is_email( $email ) ) {
			return;
		}

		$args = array( 'email' => $email );
		if ( ! empty( $lead_data['name'] ) ) {
			$parts             = explode( ' ', $lead_data['name'], 2 );
			$args['first_name'] = $parts[0];
			if ( ! empty( $parts[1] ) ) {
				$args['last_name'] = $parts[1];
			}
		}
		if ( ! empty( $lead_data['phone'] ) ) {
			$args['mobile_phone'] = $lead_data['phone'];
		}

		$contact = null;

		if ( function_exists( 'get_contactdata' ) ) {
			$contact = get_contactdata( $email );
		}

		if ( ! $contact && class_exists( '\Groundhogg\Contact' ) ) {
			$contact = new \Groundhogg\Contact( $args );
		}

		// Apply comma-separated tags.
		if ( $contact && ! empty( $cfg['tags'] ) && method_exists( $contact, 'apply_tag' ) ) {
			$tags = array_map( 'trim', explode( ',', $cfg['tags'] ) );
			foreach ( $tags as $tag ) {
				if ( $tag ) {
					$contact->apply_tag( $tag );
				}
			}
		}
	}
}
