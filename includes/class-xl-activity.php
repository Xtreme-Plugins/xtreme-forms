<?php
/**
 * Lead activity timeline logging and retrieval.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Activity
 */
class XL_Activity {

	const TYPE_STATUS_CHANGE = 'status_change';
	const TYPE_ASSIGNMENT = 'assignment';
	const TYPE_NOTE_ADDED = 'note_added';
	const TYPE_TAG_ADDED = 'tag_added';
	const TYPE_TAG_REMOVED = 'tag_removed';
	const TYPE_LEAD_CREATED = 'lead_created';

	/**
	 * Log an activity entry for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $user_id WordPress user ID performing the action.
	 * @param string $action_type One of the TYPE_* constants.
	 * @param array $data Arbitrary key/value data for the entry.
	 * @return int|false New activity ID or false on failure.
	 */
	public static function log( int $lead_id, int $user_id, string $action_type, array $data = array() ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_activity';
		$now = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$inserted = $wpdb->insert(
			$table,
			array(
				'lead_id' => absint( $lead_id ),
				'user_id' => absint( $user_id ),
				'action_type' => sanitize_text_field( $action_type ),
				'action_data' => wp_json_encode( $data ),
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the activity timeline for a lead, ordered oldest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array Array of activity objects enriched with user_name and formatted data.
	 */
	public static function get_activity_for_lead( int $lead_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_activity';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at ASC, id ASC",
				$lead_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row->user_id );
			$row->user_name = $user ? $user->display_name : __( 'System', 'xtremeleads' );
			$row->data = json_decode( $row->action_data, true ) ?: array();
			$row->label = self::format_entry( $row );
		}

		return $rows;
	}

	/**
	 * Format a human-readable label for an activity entry.
	 *
	 * @param object $row Activity row with decoded data.
	 * @return string
	 */
	private static function format_entry( object $row ): string {
		$data = $row->data;

		switch ( $row->action_type ) {
			case self::TYPE_STATUS_CHANGE:
				return sprintf(
					/* translators: 1: user name, 2: old status, 3: new status */
					__( '%1$s changed status from <strong>%2$s</strong> to <strong>%3$s</strong>', 'xtremeleads' ),
					esc_html( $row->user_name ),
					esc_html( $data['from_label'] ?? $data['from'] ?? '' ),
					esc_html( $data['to_label'] ?? $data['to'] ?? '' )
				);

			case self::TYPE_ASSIGNMENT:
				return sprintf(
					/* translators: 1: user name, 2: old assignee, 3: new assignee */
					__( '%1$s assigned lead from <strong>%2$s</strong> to <strong>%3$s</strong>', 'xtremeleads' ),
					esc_html( $row->user_name ),
					esc_html( $data['from_name'] ?? __( 'Unassigned', 'xtremeleads' ) ),
					esc_html( $data['to_name'] ?? __( 'Unassigned', 'xtremeleads' ) )
				);

			case self::TYPE_NOTE_ADDED:
				return sprintf(
					/* translators: %s: user name */
					__( '%s added a note', 'xtremeleads' ),
					esc_html( $row->user_name )
				);

			case self::TYPE_TAG_ADDED:
				return sprintf(
					/* translators: 1: user name, 2: tag name */
					__( '%1$s added tag <strong>%2$s</strong>', 'xtremeleads' ),
					esc_html( $row->user_name ),
					esc_html( $data['tag_name'] ?? '' )
				);

			case self::TYPE_TAG_REMOVED:
				return sprintf(
					/* translators: 1: user name, 2: tag name */
					__( '%1$s removed tag <strong>%2$s</strong>', 'xtremeleads' ),
					esc_html( $row->user_name ),
					esc_html( $data['tag_name'] ?? '' )
				);

			case self::TYPE_LEAD_CREATED:
				return sprintf(
					/* translators: %s: form name */
					__( 'Lead created via form <strong>%s</strong>', 'xtremeleads' ),
					esc_html( $data['form_name'] ?? '' )
				);

			default:
				return esc_html( $row->action_type );
		}
	}
}
