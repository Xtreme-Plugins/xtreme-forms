<?php
/**
 * Activity Audit Log — append-only, read-only log of all significant plugin actions.
 *
 * Action type slugs:
 * lead_status_changed, lead_note_added, lead_assignment_changed, lead_data_deleted,
 * form_created, form_updated, form_deleted,
 * global_settings_updated, email_sent,
 * plugin_data_exported, plugin_data_imported
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Audit_Log
 */
class XF_Audit_Log {

	// ── Action type constants ──────────────────────────────────────────────
	const ACTION_LEAD_STATUS_CHANGED = 'lead_status_changed';
	const ACTION_LEAD_NOTE_ADDED = 'lead_note_added';
	const ACTION_LEAD_ASSIGNMENT_CHANGED = 'lead_assignment_changed';
	const ACTION_LEAD_DATA_DELETED = 'lead_data_deleted';
	const ACTION_FORM_CREATED = 'form_created';
	const ACTION_FORM_UPDATED = 'form_updated';
	const ACTION_FORM_DELETED = 'form_deleted';
	const ACTION_GLOBAL_SETTINGS_UPDATED = 'global_settings_updated';
	const ACTION_EMAIL_SENT = 'email_sent';
	const ACTION_PLUGIN_DATA_EXPORTED = 'plugin_data_exported';
	const ACTION_PLUGIN_DATA_IMPORTED = 'plugin_data_imported';

	/**
	 * Write a new audit log entry.
	 *
	 * @param string $action_type One of the ACTION_* constants.
	 * @param int $record_id ID of the affected record (lead ID, form ID, 0 for global).
	 * @param array $context Additional contextual data (stored as JSON).
	 * @param int $user_id WP user ID; defaults to current_user_id().
	 * @return int|false New entry ID or false on failure.
	 */
	public static function record(
		string $action_type,
		int $record_id = 0,
		array $context = array(),
		int $user_id = 0
	): int|false {
		global $wpdb;

		if ( 0 === $user_id ) {
			$user_id = (int) get_current_user_id();
		}

		$user = get_userdata( $user_id );
		$user_display = $user ? $user->display_name : __( 'System', 'xtreme-forms' );

		$table = $wpdb->prefix . 'xtremeforms_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id' => $user_id,
				'user_display' => sanitize_text_field( $user_display ),
				'action_type' => sanitize_text_field( $action_type ),
				'record_id' => absint( $record_id ),
				'context' => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieve audit log entries with optional filtering and pagination.
	 *
	 * @param array $args {
	 * @type string $action_type Filter by action type slug.
	 * @type int $record_id Filter by record ID.
	 * @type int $user_id Filter by user.
	 * @type string $date_from MySQL datetime (start).
	 * @type string $date_to MySQL datetime (end).
	 * @type int $per_page Rows per page (default 50).
	 * @type int $paged Page number (default 1).
	 * }
	 * @return array { entries: object[], total: int }
	 */
	public static function get_entries( array $args = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_audit_log';
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		$paged = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['action_type'] ) ) {
			$where[] = 'action_type = %s';
			$params[] = sanitize_text_field( $args['action_type'] );
		}

		if ( ! empty( $args['record_id'] ) ) {
			$where[] = 'record_id = %d';
			$params[] = absint( $args['record_id'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$params[] = sanitize_text_field( $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$params[] = sanitize_text_field( $args['date_to'] );
		}

		$where_sql = implode( ' AND ', $where );

		// Total count (no prepare needed when where_sql built from literals + %s/%d).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( $total_query, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $total_query );
		}

		// Entries query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows_query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$row_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$entries = $wpdb->get_results( $wpdb->prepare( $rows_query, $row_params ) );

		return array(
			'entries' => $entries ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Get a human-readable label for an action type slug.
	 *
	 * @param string $action_type
	 * @return string
	 */
	public static function get_action_label( string $action_type ): string {
		$labels = array(
			self::ACTION_LEAD_STATUS_CHANGED => __( 'Lead Status Changed', 'xtreme-forms' ),
			self::ACTION_LEAD_NOTE_ADDED => __( 'Lead Note Added', 'xtreme-forms' ),
			self::ACTION_LEAD_ASSIGNMENT_CHANGED => __( 'Lead Assignment Changed', 'xtreme-forms' ),
			self::ACTION_LEAD_DATA_DELETED => __( 'Lead Data Deleted', 'xtreme-forms' ),
			self::ACTION_FORM_CREATED => __( 'Form Created', 'xtreme-forms' ),
			self::ACTION_FORM_UPDATED => __( 'Form Updated', 'xtreme-forms' ),
			self::ACTION_FORM_DELETED => __( 'Form Deleted', 'xtreme-forms' ),
			self::ACTION_GLOBAL_SETTINGS_UPDATED => __( 'Global Settings Updated', 'xtreme-forms' ),
			self::ACTION_EMAIL_SENT => __( 'Email Sent', 'xtreme-forms' ),
			self::ACTION_PLUGIN_DATA_EXPORTED => __( 'Plugin Data Exported', 'xtreme-forms' ),
			self::ACTION_PLUGIN_DATA_IMPORTED => __( 'Plugin Data Imported', 'xtreme-forms' ),
		);

		return $labels[ $action_type ] ?? esc_html( $action_type );
	}

	/**
	 * All known action type slugs (for filter dropdowns etc.).
	 *
	 * @return array<string>
	 */
	public static function get_all_action_types(): array {
		return array(
			self::ACTION_LEAD_STATUS_CHANGED,
			self::ACTION_LEAD_NOTE_ADDED,
			self::ACTION_LEAD_ASSIGNMENT_CHANGED,
			self::ACTION_LEAD_DATA_DELETED,
			self::ACTION_FORM_CREATED,
			self::ACTION_FORM_UPDATED,
			self::ACTION_FORM_DELETED,
			self::ACTION_GLOBAL_SETTINGS_UPDATED,
			self::ACTION_EMAIL_SENT,
			self::ACTION_PLUGIN_DATA_EXPORTED,
			self::ACTION_PLUGIN_DATA_IMPORTED,
		);
	}
}
