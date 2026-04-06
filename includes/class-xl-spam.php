<?php
/**
 * Spam protection — honeypot, time-gate, reCAPTCHA v3, blocklist, and spam log.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Spam
 */
class XL_Spam {

	/** Rejection reason constants. */
	const REASON_HONEYPOT = 'honeypot';
	const REASON_TIMEGATE = 'time_gate';
	const REASON_RECAPTCHA = 'recaptcha';
	const REASON_RECAPTCHA_API_WARN = 'recaptcha_api_warn'; // API failed; submission allowed through with warning logged.
	const REASON_BLOCKLIST = 'blocklist';

	/** Log entries per page. */
	const LOG_PER_PAGE = 25;

	// ─────────────────────────────────────────────────────────────────────────
	// Spam Log
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Log a blocked submission to the spam log.
	 *
	 * @param int $form_id Form ID.
	 * @param string $rejection_reason One of the REASON_* constants.
	 * @param string $submitted_email Submitted email (may be empty).
	 * @param string $source_url Source URL.
	 * @param string $user_agent User agent string.
	 * @param string $ip_address IP address (possibly anonymized).
	 * @return int|false Log entry ID or false.
	 */
	public static function log_blocked(
		int $form_id,
		string $rejection_reason,
		string $submitted_email,
		string $source_url,
		string $user_agent,
		string $ip_address
	): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_spam_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'form_id' => absint( $form_id ),
				'rejection_reason' => sanitize_key( $rejection_reason ),
				'submitted_email' => sanitize_email( $submitted_email ),
				'source_url' => esc_url_raw( $source_url ),
				'user_agent' => sanitize_text_field( substr( $user_agent, 0, 500 ) ),
				'ip_address' => sanitize_text_field( $ip_address ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get spam log entries (paginated, filterable).
	 *
	 * @param array $args {
	 * @type int $page Page number.
	 * @type string $rejection_reason Filter by reason.
	 * @type int $form_id Filter by form.
	 * }
	 * @return array{items: array, total: int, pages: int}
	 */
	public static function get_log( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_spam_log';
		$page = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset = ( $page - 1 ) * self::LOG_PER_PAGE;

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['rejection_reason'] ) ) {
			$where .= ' AND rejection_reason = %s';
			$params[] = sanitize_key( $args['rejection_reason'] );
		}
		if ( ! empty( $args['form_id'] ) ) {
			$where .= ' AND form_id = %d';
			$params[] = (int) $args['form_id'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var(
			$params
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$params )
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				: "SELECT COUNT(*) FROM {$table} WHERE {$where}"
		);

		$query_params = array_merge( $params, array( self::LOG_PER_PAGE, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$query_params )
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / self::LOG_PER_PAGE ),
		);
	}

	/**
	 * Delete a single spam log entry.
	 *
	 * @param int $id Log entry ID.
	 * @return bool
	 */
	public static function delete_log_entry( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_spam_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Clear all spam log entries.
	 *
	 * @return int Number of deleted entries.
	 */
	public static function clear_log(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_spam_log';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return 1; // TRUNCATE doesn't return row count via wpdb.
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Blocklist
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get the email domain blocklist as an array of domains.
	 *
	 * @return string[]
	 */
	public static function get_domain_blocklist(): array {
		$settings = get_option( 'xtremeleads_settings', array() );
		$raw = $settings['spam_domain_blocklist'] ?? '';
		return self::parse_blocklist_text( $raw );
	}

	/**
	 * Get the keyword blocklist as an array of keywords.
	 *
	 * @return string[]
	 */
	public static function get_keyword_blocklist(): array {
		$settings = get_option( 'xtremeleads_settings', array() );
		$raw = $settings['spam_keyword_blocklist'] ?? '';
		return self::parse_blocklist_text( $raw );
	}

	/**
	 * Parse a newline/comma-separated blocklist text into a unique array.
	 *
	 * @param string $raw Raw blocklist text.
	 * @return string[]
	 */
	private static function parse_blocklist_text( string $raw ): array {
		if ( '' === $raw ) {
			return array();
		}
		// Support both newlines and commas as separators.
		$items = preg_split( '/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		$items = array_map( 'trim', $items );
		$items = array_map( 'strtolower', $items );
		$items = array_filter( $items );
		$items = array_unique( $items );
		return array_values( $items );
	}

	/**
	 * Check if an email address is on the domain blocklist.
	 *
	 * The match is case-insensitive and tests the full domain only.
	 * e.g. blocking 'spam.com' does NOT block 'notspam.com'.
	 *
	 * @param string $email Email address to check.
	 * @return bool True if blocked.
	 */
	public static function is_domain_blocked( string $email ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}
		$parts = explode( '@', strtolower( $email ), 2 );
		$domain = $parts[1] ?? '';
		$blocklist = self::get_domain_blocklist();

		foreach ( $blocklist as $blocked_domain ) {
			// Exact domain match only — not substring match.
			if ( $blocked_domain === $domain ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if any field value contains a blocked keyword.
	 *
	 * Case-insensitive match against all text field values in the submission.
	 *
	 * @param array $field_values Submitted field values (scalar or array of scalars).
	 * @return bool True if any keyword is found.
	 */
	public static function has_blocked_keyword( array $field_values ): bool {
		$keywords = self::get_keyword_blocklist();
		if ( empty( $keywords ) ) {
			return false;
		}

		// Flatten all field values to a single lowercase string for scanning.
		$text = '';
		array_walk_recursive( $field_values, function ( $val ) use ( &$text ) {
			$text .= ' ' . strtolower( (string) $val );
		} );

		foreach ( $keywords as $kw ) {
			if ( '' !== $kw && str_contains( $text, $kw ) ) {
				return true;
			}
		}

		return false;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// reCAPTCHA v3
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get reCAPTCHA v3 settings from the plugin options.
	 *
	 * @return array{enabled: bool, site_key: string, secret_key: string, threshold: float}
	 */
	public static function get_recaptcha_settings(): array {
		$settings = get_option( 'xtremeleads_settings', array() );
		$enabled = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
		$site_key = sanitize_text_field( $settings['recaptcha_site_key'] ?? '' );
		$secret = sanitize_text_field( $settings['recaptcha_secret_key'] ?? '' );
		$threshold = isset( $settings['recaptcha_threshold'] )
			? max( 0.1, min( 0.9, (float) $settings['recaptcha_threshold'] ) )
			: 0.5;

		return array(
			'enabled' => $enabled && '' !== $site_key && '' !== $secret,
			'site_key' => $site_key,
			'secret_key' => $secret,
			'threshold' => $threshold,
		);
	}

	/**
	 * Verify a reCAPTCHA v3 token with Google's API.
	 *
	 * Returns an array with:
	 * - success: bool — whether to allow the submission.
	 * - score: float — score from Google (or null on API failure).
	 * - error: string — human-readable error if failed.
	 * - api_failed: bool — true if the verification API call itself failed
	 * (caller should allow through per spec: "falls back to allowing").
	 *
	 * @param string $token reCAPTCHA token from browser.
	 * @param float $threshold Score threshold (0.1–0.9).
	 * @param string $secret Secret key.
	 * @return array
	 */
	public static function verify_recaptcha( string $token, float $threshold, string $secret ): array {
		if ( '' === $token ) {
			return array(
				'success' => false,
				'score' => null,
				'error' => __( 'reCAPTCHA token missing.', 'xtremeleads' ),
				'api_failed' => false,
			);
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret' => $secret,
					'response' => $token,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			// API failure — fall back to allowing submission per spec.
			return array(
				'success' => true, // Allow through on API failure.
				'score' => null,
				'error' => $response->get_error_message(),
				'api_failed' => true,
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['success'] ) ) {
			return array(
				'success' => false,
				'score' => null,
				'error' => __( 'reCAPTCHA verification failed.', 'xtremeleads' ),
				'api_failed' => false,
			);
		}

		$score = (float) ( $data['score'] ?? 0 );

		return array(
			'success' => $score >= $threshold,
			'score' => $score,
			'error' => $score < $threshold ? __( 'reCAPTCHA score too low.', 'xtremeleads' ) : '',
			'api_failed' => false,
		);
	}

	/**
	 * Check if reCAPTCHA is enabled for a specific form.
	 *
	 * reCAPTCHA must be globally enabled (with valid keys) AND enabled on the form.
	 *
	 * @param array $form_settings Form settings array.
	 * @return bool
	 */
	public static function is_recaptcha_enabled_for_form( array $form_settings ): bool {
		$global = self::get_recaptcha_settings();
		if ( ! $global['enabled'] ) {
			return false;
		}
		return ! empty( $form_settings['recaptcha_enabled'] ) && '1' === (string) $form_settings['recaptcha_enabled'];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Available rejection reasons list (for UI filtering)
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get all rejection reason labels.
	 *
	 * @return array<string, string> reason_key => label
	 */
	public static function get_reason_labels(): array {
		return array(
			self::REASON_HONEYPOT => __( 'Honeypot', 'xtremeleads' ),
			self::REASON_TIMEGATE => __( 'Time Gate', 'xtremeleads' ),
			self::REASON_RECAPTCHA => __( 'reCAPTCHA', 'xtremeleads' ),
			self::REASON_RECAPTCHA_API_WARN => __( 'reCAPTCHA API Warning (allowed)', 'xtremeleads' ),
			self::REASON_BLOCKLIST => __( 'Blocklist', 'xtremeleads' ),
		);
	}
}
