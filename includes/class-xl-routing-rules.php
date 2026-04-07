<?php
/**
 * Email Routing Rules – defines and evaluates recipient routing for lead notifications.
 *
 * Rules can be form-based ("if form = X, notify email") or field-value-based
 * ("if field[service_interest] = 'Enterprise', notify sales@example.com").
 *
 * Evaluation mode:
 * - 'match_first': Stop at the first matching rule and send only to that recipient.
 * - 'match_all': Evaluate every rule; send to all matched recipients.
 *
 * Case-sensitivity: field-value comparisons are case-sensitive (exact match).
 * This is intentional and documented here.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Routing_Rules
 */
class XL_Routing_Rules {

	/** Condition type constants. */
	const CONDITION_FORM = 'form';
	const CONDITION_FIELD_VALUE = 'field_value';

	/** Evaluation mode option key. */
	const MODE_OPTION_KEY = 'xtremeleads_routing_mode';

	/** Evaluation mode values. */
	const MODE_MATCH_FIRST = 'match_first';
	const MODE_MATCH_ALL = 'match_all';

	// ── CRUD ──────────────────────────────────────────────────────────────────

	/**
	 * Get all active routing rules ordered by rule_order ASC.
	 *
	 * @return array List of rule objects from DB.
	 */
	public static function get_all_rules(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$all_rules = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY rule_order ASC, id ASC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $all_rules ?: array();
	}

	/**
	 * Get a single rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return object|null
	 */
	public static function get_rule( int $rule_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rule_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $rule_id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $rule_row ?: null;
	}

	/**
	 * Insert a new routing rule.
	 *
	 * @param array $data {
	 * @type string $condition_type 'form' or 'field_value'.
	 * @type int $form_id Required for both types.
	 * @type string $field_id Required for 'field_value' type.
	 * @type string $field_value Required for 'field_value' type. Case-sensitive.
	 * @type string $recipient_email Notification recipient.
	 * @type int $rule_order Display/evaluation order (lower = higher priority).
	 * @type int $is_active 1 or 0.
	 * }
	 * @return int|false New rule ID or false on failure.
	 */
	public static function create_rule( array $data ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		// Auto-assign order if not provided.
		if ( ! isset( $data['rule_order'] ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$max_order = (int) $wpdb->get_var( "SELECT MAX(rule_order) FROM {$table}" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$data['rule_order'] = $max_order + 1;
		}

		$insert = array(
			'condition_type' => sanitize_text_field( $data['condition_type'] ?? self::CONDITION_FORM ),
			'form_id' => absint( $data['form_id'] ?? 0 ),
			'field_id' => sanitize_text_field( $data['field_id'] ?? '' ),
			'field_value' => sanitize_text_field( $data['field_value'] ?? '' ),
			'recipient_email' => sanitize_email( $data['recipient_email'] ?? '' ),
			'rule_order' => absint( $data['rule_order'] ),
			'is_active' => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->insert( $table, $insert );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @param array $data Fields to update (same shape as create_rule $data).
	 * @return bool True on success.
	 */
	public static function update_rule( int $rule_id, array $data ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		$update = array( 'updated_at' => current_time( 'mysql' ) );

		$allowed = array( 'condition_type', 'form_id', 'field_id', 'field_value', 'recipient_email', 'rule_order', 'is_active' );

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			switch ( $key ) {
				case 'condition_type':
				case 'field_id':
				case 'field_value':
					$update[ $key ] = sanitize_text_field( $data[ $key ] );
					break;
				case 'recipient_email':
					$update[ $key ] = sanitize_email( $data[ $key ] );
					break;
				case 'form_id':
				case 'rule_order':
					$update[ $key ] = absint( $data[ $key ] );
					break;
				case 'is_active':
					$update[ $key ] = (int) (bool) $data[ $key ];
					break;
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->update( $table, $update, array( 'id' => $rule_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return false !== $result;
	}

	/**
	 * Delete a rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public static function delete_rule( int $rule_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->delete( $table, array( 'id' => $rule_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return false !== $result;
	}

	/**
	 * Bulk reorder rules by providing an ordered array of rule IDs.
	 *
	 * @param array<int> $ordered_ids Rule IDs in desired order.
	 * @return bool
	 */
	public static function reorder_rules( array $ordered_ids ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		foreach ( $ordered_ids as $position => $rule_id ) {
			$rule_id = absint( $rule_id );
			if ( ! $rule_id ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->update(
				$table,
				array(
					'rule_order' => (int) $position,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $rule_id )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		return true;
	}

	/**
	 * Save multiple rules from the admin UI in one batch (replaces all existing rules).
	 *
	 * @param array $rules_data Array of rule data arrays.
	 * @param string $mode Evaluation mode ('match_first' or 'match_all').
	 * @return bool
	 */
	public static function save_all_rules( array $rules_data, string $mode ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		// Delete all existing rules.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "TRUNCATE TABLE {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Save the evaluation mode.
		$mode = in_array( $mode, array( self::MODE_MATCH_FIRST, self::MODE_MATCH_ALL ), true )
			? $mode
			: self::MODE_MATCH_FIRST;

		update_option( self::MODE_OPTION_KEY, $mode );

		// Insert each rule with correct order.
		foreach ( $rules_data as $order => $rule ) {
			$rule['rule_order'] = (int) $order;
			self::create_rule( $rule );
		}

		return true;
	}

	// ── Evaluation ────────────────────────────────────────────────────────────

	/**
	 * Get the current evaluation mode.
	 *
	 * @return string 'match_first' or 'match_all'.
	 */
	public static function get_mode(): string {
		$mode = get_option( self::MODE_OPTION_KEY, self::MODE_MATCH_FIRST );
		return in_array( $mode, array( self::MODE_MATCH_FIRST, self::MODE_MATCH_ALL ), true )
			? $mode
			: self::MODE_MATCH_FIRST;
	}

	/**
	 * Evaluate all active rules against a submission and return matching recipients.
	 *
	 * Field-value comparisons are CASE-SENSITIVE (exact string match).
	 *
	 * @param int $form_id The form ID the submission came from.
	 * @param array $field_values Submitted field values (field_id => value).
	 * @return array<string> List of recipient email addresses (may be empty).
	 */
	public static function get_matching_recipients( int $form_id, array $field_values ): array {
		$rules = self::get_all_rules();
		$mode = self::get_mode();

		$recipients = array();

		foreach ( $rules as $rule ) {
			if ( ! (int) $rule->is_active ) {
				continue;
			}

			$matched = false;

			if ( self::CONDITION_FORM === $rule->condition_type ) {
				// Form-based condition: match if form_id matches.
				$matched = ( (int) $rule->form_id === $form_id );

			} elseif ( self::CONDITION_FIELD_VALUE === $rule->condition_type ) {
				// Field-value condition: match if form matches AND field value is an exact case-sensitive match.
				if ( (int) $rule->form_id !== $form_id ) {
					continue;
				}

				$field_id = (string) $rule->field_id;
				$expected_val = (string) $rule->field_value;
				$actual_val = $field_values[ $field_id ] ?? '';

				if ( is_array( $actual_val ) ) {
					// For multi-select / checkbox fields, check if any option matches.
					$matched = in_array( $expected_val, $actual_val, true );
				} else {
					// Exact, case-sensitive string comparison.
					$matched = ( (string) $actual_val === $expected_val );
				}
			}

			if ( $matched && is_email( $rule->recipient_email ) ) {
				$recipients[] = sanitize_email( $rule->recipient_email );

				if ( self::MODE_MATCH_FIRST === $mode ) {
					// Stop after first match.
					break;
				}
			}
		}

		return array_values( array_unique( $recipients ) );
	}

	/**
	 * Check whether any active routing rules exist for a given form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function has_rules_for_form( int $form_id ): bool {
		$rules = self::get_all_rules();

		foreach ( $rules as $rule ) {
			if ( ! (int) $rule->is_active ) {
				continue;
			}
			if ( (int) $rule->form_id === $form_id ) {
				return true;
			}
		}

		return false;
	}
}
