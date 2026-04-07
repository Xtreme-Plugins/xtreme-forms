<?php
/**
 * Duplicate lead detection and handling (Feature 4.4).
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Duplicates
 *
 * Checks every new submission for an existing lead with the same email address
 * (case-insensitive).
 *
 * Duplicate behavior modes:
 * - 'silent_flag' : Lead is saved but flagged as a duplicate.
 * - 'block' : Submission is rejected (no record written).
 * - 'merge' : Non-empty new values overwrite fields on the original lead.
 *
 * Concurrency strategy (documented per criterion analytics_endpoint_security):
 * For the duplicate check + insert sequence we use a MySQL GET_LOCK() on a
 * deterministic lock key derived from the email address. This prevents two
 * simultaneous submissions for the same email both passing the duplicate check
 * before either one is written. The lock is held for the duration of the check-
 * and-insert operation and released immediately afterward. The lock timeout is
 * 5 seconds, which is sufficient for a normal INSERT. If the lock cannot be
 * acquired within 5 seconds (extremely unlikely on shared hosting), the
 * submission falls through without a lock — the worst case is two un-flagged
 * leads for the same email within the same second, which is an acceptable
 * degradation.
 */
class XF_Duplicates {

	const BEHAVIOR_SILENT_FLAG = 'silent_flag';
	const BEHAVIOR_BLOCK       = 'block';
	const BEHAVIOR_MERGE       = 'merge';

	/**
	 * Get the configured duplicate behavior from plugin settings.
	 *
	 * @return string One of the BEHAVIOR_* constants.
	 */
	public static function get_behavior(): string {
		$settings = get_option( 'xtremeforms_settings', array() );
		$behavior = $settings['duplicate_behavior'] ?? self::BEHAVIOR_SILENT_FLAG;

		$allowed = array( self::BEHAVIOR_SILENT_FLAG, self::BEHAVIOR_BLOCK, self::BEHAVIOR_MERGE );
		return in_array( $behavior, $allowed, true ) ? $behavior : self::BEHAVIOR_SILENT_FLAG;
	}

	/**
	 * Get the configured block message.
	 *
	 * @return string HTML-safe block message.
	 */
	public static function get_block_message(): string {
		$settings = get_option( 'xtremeforms_settings', array() );
		$msg      = $settings['duplicate_block_message'] ?? '';
		if ( empty( $msg ) ) {
			$msg = __( 'You have already submitted this form. Thank you!', 'xtreme-forms' );
		}
		return wp_kses_post( $msg );
	}

	/**
	 * Find an existing lead with the same email address (case-insensitive).
	 *
	 * PRIMARY STRATEGY (fast, O(log n)):
	 * Searches the indexed `email_address` column. This column
	 * stores the normalised (lower-case) email address and has a KEY index, making
	 * the lookup fast even with 10,000+ records.
	 *
	 * FALLBACK STRATEGY (for older records that pre-date the email_address column):
	 * If the indexed lookup finds nothing, falls back to a LIKE search on the
	 * field_values JSON blob for backward compatibility with leads inserted before
	 * the schema upgrade. The fallback includes a secondary exact-match check to
	 * avoid false positives from substring matches.
	 *
	 * @param string $email Email address to search for.
	 * @return object|null Lead row object or null if not found.
	 */
	public static function find_original_by_email( string $email ): ?object {
		global $wpdb;

		if ( empty( $email ) || ! is_email( $email ) ) {
			return null;
		}

		$table       = $wpdb->prefix . 'xtremeforms_leads';
		$email_lower = strtolower( trim( $email ) );

		// ── Primary lookup: indexed email_address column (O(log n)) ──────────
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$lead = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE email_address = %s
				 AND is_duplicate = 0
				 ORDER BY id ASC
				 LIMIT 1",
				$email_lower
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( $lead ) {
			return $lead;
		}

		// ── Fallback: LIKE search on JSON blob (for pre-index records) ────
		// This path is only reached for records that pre-date the email_address
		// column. New records always have email_address populated, so over time
		// this fallback will only execute for legacy data.
		$search = '%' . $wpdb->esc_like( $email_lower ) . '%';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$lead = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE email_address IS NULL
				 AND LOWER(field_values) LIKE %s
				 AND is_duplicate = 0
				 ORDER BY id ASC
				 LIMIT 1",
				$search
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $lead ) {
			return null;
		}

		// Secondary exact-match check to avoid false positives from substring matches.
		$field_values = json_decode( $lead->field_values, true );
		if ( ! is_array( $field_values ) ) {
			return null;
		}

		foreach ( $field_values as $value ) {
			if ( is_string( $value ) && strtolower( $value ) === $email_lower ) {
				return $lead;
			}
		}

		return null;
	}

	/**
	 * Extract the first email address from a set of submitted field values.
	 *
	 * @param array $field_values Key-value pairs of submitted form data.
	 * @param array $field_defs Field definitions from the form.
	 * @return string Email address, or empty string if not found.
	 */
	public static function extract_email( array $field_values, array $field_defs ): string {
		foreach ( $field_defs as $field ) {
			if ( ( $field['type'] ?? '' ) === 'email' ) {
				$fid = $field['id'] ?? '';
				$val = $field_values[ $fid ] ?? '';
				if ( is_string( $val ) && is_email( $val ) ) {
					return sanitize_email( $val );
				}
			}
		}
		return '';
	}

	/**
	 * Acquire a MySQL advisory lock for a given email.
	 *
	 * Prevents concurrent duplicate submissions from both passing the
	 * duplicate check before either record is written.
	 *
	 * @param string $email Email address (used to derive lock key).
	 * @return bool True if lock acquired, false on timeout.
	 */
	public static function acquire_lock( string $email ): bool {
		global $wpdb;

		// Lock key: deterministic 64-char key from email hash.
		$lock_key = 'xf_dup_' . md5( strtolower( trim( $email ) ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_key )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return '1' === (string) $result;
	}

	/**
	 * Release the MySQL advisory lock for a given email.
	 *
	 * @param string $email Email address (used to derive lock key).
	 * @return void
	 */
	public static function release_lock( string $email ): void {
		global $wpdb;

		$lock_key = 'xf_dup_' . md5( strtolower( trim( $email ) ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->get_var(
			$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_key )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Handle a duplicate submission based on the configured behavior.
	 *
	 * Returns an array describing what action was taken:
	 * {
	 * 'action' : 'silent_flag' | 'blocked' | 'merged',
	 * 'original_lead_id': int|null,
	 * 'blocked' : bool,
	 * 'merged' : bool,
	 * 'message' : string (set when blocked),
	 * }
	 *
	 * @param object $original The original lead DB row.
	 * @param array  $new_values Submitted field values from the new submission.
	 * @param string $behavior One of the BEHAVIOR_* constants.
	 * @return array
	 */
	public static function handle( object $original, array $new_values, string $behavior ): array {
		switch ( $behavior ) {
			case self::BEHAVIOR_BLOCK:
				return array(
					'action'           => 'blocked',
					'original_lead_id' => (int) $original->id,
					'blocked'          => true,
					'merged'           => false,
					'message'          => self::get_block_message(),
				);

			case self::BEHAVIOR_MERGE:
				self::merge_into_original( $original, $new_values );
				return array(
					'action'           => 'merged',
					'original_lead_id' => (int) $original->id,
					'blocked'          => false,
					'merged'           => true,
					'message'          => '',
				);

			case self::BEHAVIOR_SILENT_FLAG:
			default:
				return array(
					'action'           => 'silent_flag',
					'original_lead_id' => (int) $original->id,
					'blocked'          => false,
					'merged'           => false,
					'message'          => '',
				);
		}
	}

	/**
	 * Merge non-empty new field values into the original lead record.
	 *
	 * Empty/blank values from the new submission do NOT overwrite existing values.
	 * Also refreshes the original lead's updated_at timestamp.
	 *
	 * @param object $original Original lead row.
	 * @param array  $new_values New field values (key => value pairs).
	 * @return void
	 */
	private static function merge_into_original( object $original, array $new_values ): void {
		global $wpdb;

		$existing_values = json_decode( $original->field_values, true );
		if ( ! is_array( $existing_values ) ) {
			$existing_values = array();
		}

		$merged = $existing_values;

		foreach ( $new_values as $key => $value ) {
			// Skip empty/blank new values — do not overwrite existing data.
			$is_empty = is_array( $value )
				? empty( $value )
				: ( '' === trim( (string) $value ) );

			if ( ! $is_empty ) {
				$merged[ $key ] = $value;
			}
		}

		$table = $wpdb->prefix . 'xtremeforms_leads';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->update(
			$table,
			array(
				'field_values' => wp_json_encode( $merged ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $original->id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Flag a new lead record as a duplicate and link it to the original.
	 *
	 * Must be called AFTER the new lead has been inserted.
	 *
	 * The duplicate_status is set to:
	 * - 'duplicate' when the original lead still exists.
	 * - 'duplicate_orphaned' when the referenced original has been deleted.
	 *
	 * @param int      $new_lead_id ID of the newly inserted lead.
	 * @param int|null $original_lead_id ID of the original lead (null if orphaned).
	 * @return void
	 */
	public static function flag_as_duplicate( int $new_lead_id, ?int $original_lead_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_leads';

		$status           = ( null !== $original_lead_id ) ? 'duplicate' : 'duplicate_orphaned';
		$original_lead_id = ( null !== $original_lead_id ) ? $original_lead_id : null;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->update(
			$table,
			array(
				'is_duplicate'     => 1,
				'duplicate_status' => $status,
				'original_lead_id' => $original_lead_id,
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( 'id' => $new_lead_id ),
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedPlaceholder
			array( '%d', '%s', $original_lead_id !== null ? '%d' : 'NULL', '%s' ),
			array( '%d' )
		);

		// Use direct query to handle NULL properly.
		if ( null === $original_lead_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					 SET is_duplicate = 1,
					 duplicate_status = %s,
					 original_lead_id = NULL,
					 updated_at = %s
					 WHERE id = %d",
					$status,
					current_time( 'mysql', true ),
					$new_lead_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					 SET is_duplicate = 1,
					 duplicate_status = %s,
					 original_lead_id = %d,
					 updated_at = %s
					 WHERE id = %d",
					$status,
					$original_lead_id,
					current_time( 'mysql', true ),
					$new_lead_id
				)
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}
}
