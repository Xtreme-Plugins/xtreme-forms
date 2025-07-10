<?php
/**
 * UTM parameter capture and storage for lead records.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_UTM
 *
 * Extracts and sanitizes UTM parameters from a URL.
 * Provides a fallback to cookie-supplied values when URL params are absent.
 *
 * Storage rules:
 * - Present param → stored, sanitized, truncated to 255 chars.
 * - Absent param → stored as NULL (not empty string).
 * - Overlong value → truncated to 255 chars without error.
 */
class XL_UTM {

	/**
	 * The five standard UTM parameter names.
	 */
	const PARAMS = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	);

	/**
	 * Maximum character length for any UTM value (255 to match DB column).
	 */
	const MAX_LENGTH = 255;

	/**
	 * Extract UTM parameters from a URL.
	 *
	 * Returns an associative array of utm_* => value|null.
	 * Missing parameters are set to NULL; present ones are sanitized + truncated.
	 *
	 * @param string $url Source URL.
	 * @return array<string, string|null>
	 */
	public static function extract_from_url( string $url ): array {
		$result = array_fill_keys( self::PARAMS, null );

		if ( empty( $url ) ) {
			return $result;
		}

		$parsed_query = array();
		$query_string = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $query_string ) {
			wp_parse_str( $query_string, $parsed_query );
		}

		foreach ( self::PARAMS as $param ) {
			if ( isset( $parsed_query[ $param ] ) && '' !== $parsed_query[ $param ] ) {
				$result[ $param ] = self::sanitize_value( (string) $parsed_query[ $param ] );
			}
		}

		return $result;
	}

	/**
	 * Extract UTM parameters from a cookie-supplied JSON string.
	 *
	 * The cookie value is expected to be JSON, e.g.:
	 * {"utm_source":"google","utm_medium":"cpc",...}
	 *
	 * @param string $cookie_json Raw cookie value (JSON).
	 * @return array<string, string|null>
	 */
	public static function extract_from_cookie( string $cookie_json ): array {
		$result = array_fill_keys( self::PARAMS, null );

		if ( empty( $cookie_json ) ) {
			return $result;
		}

		$decoded = json_decode( wp_unslash( $cookie_json ), true );
		if ( ! is_array( $decoded ) ) {
			return $result;
		}

		foreach ( self::PARAMS as $param ) {
			if ( isset( $decoded[ $param ] ) && '' !== $decoded[ $param ] ) {
				$result[ $param ] = self::sanitize_value( (string) $decoded[ $param ] );
			}
		}

		return $result;
	}

	/**
	 * Merge URL-based UTM values with cookie-based fallback.
	 *
	 * URL values take precedence; cookie values fill in only where URL gave NULL.
	 *
	 * @param array $from_url Output of extract_from_url().
	 * @param array $from_cookie Output of extract_from_cookie().
	 * @return array<string, string|null>
	 */
	public static function merge_with_fallback( array $from_url, array $from_cookie ): array {
		$merged = array_fill_keys( self::PARAMS, null );

		foreach ( self::PARAMS as $param ) {
			if ( null !== ( $from_url[ $param ] ?? null ) ) {
				$merged[ $param ] = $from_url[ $param ];
			} elseif ( null !== ( $from_cookie[ $param ] ?? null ) ) {
				$merged[ $param ] = $from_cookie[ $param ];
			}
		}

		return $merged;
	}

	/**
	 * Sanitize a single UTM value.
	 *
	 * Strips HTML tags, trims whitespace, truncates to MAX_LENGTH.
	 *
	 * @param string $value Raw value.
	 * @return string|null Sanitized value, or null if empty after sanitization.
	 */
	public static function sanitize_value( string $value ): ?string {
		$clean = strip_tags( trim( $value ) );
		if ( '' === $clean ) {
			return null;
		}
		if ( mb_strlen( $clean ) > self::MAX_LENGTH ) {
			$clean = mb_substr( $clean, 0, self::MAX_LENGTH );
		}
		return $clean;
	}

	/**
	 * Check whether a UTM array has at least one non-NULL value.
	 *
	 * @param array $utm UTM array.
	 * @return bool
	 */
	public static function has_any_value( array $utm ): bool {
		foreach ( self::PARAMS as $param ) {
			if ( null !== ( $utm[ $param ] ?? null ) ) {
				return true;
			}
		}
		return false;
	}
}
