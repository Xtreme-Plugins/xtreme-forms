<?php
/**
 * License storage + activation against the Xtreme Plugins licensing API.
 *
 * The free plugin owns this UI and the stored credentials. The optional
 * Xtreme Forms Pro add-on (sold from xtremeplugins.com) reads the activated
 * license via {@see Xtremeforms_License::is_active()} / get_plan() to decide
 * whether to enable its own features. No core free-plugin feature is gated
 * by license status — WordPress.org guideline 5 (trialware) compliance.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_License
 */
class Xtremeforms_License {

	const OPTION_KEY = 'xtremeforms_license';
	const API_BASE   = 'https://xtremeplugins.com/api/v1';

	/**
	 * Return the full stored license record, with defaults applied.
	 *
	 * @return array{key:string,status:string,plan:string,expires_at:?string,last_checked:int,last_message:string}
	 */
	public static function get_data(): array {
		$defaults = array(
			'key'          => '',
			'status'       => 'inactive', // inactive | active | expired | invalid
			'plan'         => 'free',     // free | pro | agency | ...
			'expires_at'   => null,
			'last_checked' => 0,
			'last_message' => '',
		);
		$stored   = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Whether a Pro license is currently valid.
	 * Used by the Pro add-on to decide whether to enable its features.
	 */
	public static function is_active(): bool {
		$data = self::get_data();
		if ( 'active' !== $data['status'] ) {
			return false;
		}
		if ( ! empty( $data['expires_at'] ) ) {
			$expires_ts = is_numeric( $data['expires_at'] )
				? (int) $data['expires_at']
				: strtotime( (string) $data['expires_at'] );
			if ( $expires_ts && time() > $expires_ts ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Currently activated plan slug, or 'free' if no active license.
	 */
	public static function get_plan(): string {
		return self::is_active() ? (string) self::get_data()['plan'] : 'free';
	}

	/**
	 * Mask a license key for display: keep first 4 + last 4 chars, dot out the rest.
	 *
	 * @param string $key Raw license key.
	 */
	public static function mask( string $key ): string {
		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '•', $len );
		}
		return substr( $key, 0, 4 ) . str_repeat( '•', max( 4, $len - 8 ) ) . substr( $key, -4 );
	}

	/**
	 * Activate a license key against the xtremeplugins.com licensing API.
	 *
	 * On success, persists key + status + plan + expiry to wp_options. On
	 * failure, leaves stored state untouched and returns the error message.
	 *
	 * @param string $key License key entered by the admin.
	 * @return array{success:bool,message:string,data?:array}
	 */
	public static function activate( string $key ): array {
		$key = trim( $key );
		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a license key.', 'xtreme-forms' ),
			);
		}

		/**
		 * Filter the activation endpoint URL (useful for self-hosted licensing
		 * servers or staging environments).
		 *
		 * @param string $url Default activation endpoint.
		 */
		$api_url = apply_filters( 'xtremeforms_license_activate_url', self::API_BASE . '/license/activate' );

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 15,
				'body'    => array(
					'license_key' => $key,
					'site_url'    => home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message from wp_remote_post */
					__( 'Could not reach the licensing server: %s', 'xtreme-forms' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['message'] )
				? (string) $body['message']
				: sprintf(
					/* translators: %d: HTTP status code from licensing server */
					__( 'Activation failed (HTTP %d). Please verify your key and try again.', 'xtreme-forms' ),
					$code
				);
			return array(
				'success' => false,
				'message' => $msg,
			);
		}

		$record = array(
			'key'          => $key,
			'status'       => isset( $body['status'] ) ? sanitize_key( $body['status'] ) : 'active',
			'plan'         => isset( $body['plan'] ) ? sanitize_key( $body['plan'] ) : 'pro',
			'expires_at'   => isset( $body['expires_at'] ) ? sanitize_text_field( $body['expires_at'] ) : null,
			'last_checked' => time(),
			'last_message' => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '',
		);
		update_option( self::OPTION_KEY, $record );

		return array(
			'success' => true,
			'message' => __( 'License activated.', 'xtreme-forms' ),
			'data'    => $record,
		);
	}

	/**
	 * Deactivate the locally-stored license and ask the licensing server to
	 * release the site slot. Local state is always cleared, even if the
	 * remote call fails — the admin can re-activate later.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function deactivate(): array {
		$data = self::get_data();
		$key  = $data['key'];

		if ( '' === $key ) {
			delete_option( self::OPTION_KEY );
			return array(
				'success' => true,
				'message' => __( 'No active license to deactivate.', 'xtreme-forms' ),
			);
		}

		/** @see xtremeforms_license_activate_url */
		$api_url = apply_filters( 'xtremeforms_license_deactivate_url', self::API_BASE . '/license/deactivate' );

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 15,
				'body'    => array(
					'license_key' => $key,
					'site_url'    => home_url(),
				),
			)
		);

		delete_option( self::OPTION_KEY );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => true,
				'message' => __( 'License removed from this site. The licensing server was unreachable, so the seat may still be held remotely — re-try deactivation later if you need to free it.', 'xtreme-forms' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'License deactivated.', 'xtreme-forms' ),
		);
	}
}
