<?php
/**
 * Email Log – records every email dispatched by XtremeLeads.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Email_Log
 */
class XL_Email_Log {

	// Trigger type constants.
	const TRIGGER_NOTIFICATION = 'notification';
	const TRIGGER_AUTO_RESPONDER = 'auto_responder';
	const TRIGGER_ROUTING = 'routing';
	const TRIGGER_TEST = 'test';
	const TRIGGER_RESEND = 'resend';

	// Status constants.
	const STATUS_SENT = 'sent';
	const STATUS_FAILED = 'failed';
	const STATUS_SKIPPED = 'skipped';

	/**
	 * Insert a new log entry.
	 *
	 * @param array $data {
	 * @type int $lead_id Associated lead ID (0 for test).
	 * @type string $recipient Recipient email address.
	 * @type string $subject Email subject.
	 * @type string $body Full HTML email body.
	 * @type string $headers Email headers (joined with newline).
	 * @type string $trigger_type One of the TRIGGER_* constants.
	 * @type string $status One of the STATUS_* constants.
	 * @type string $failure_reason Optional failure description.
	 * }
	 * @return int|false Inserted ID or false on failure.
	 */
	public static function insert( array $data ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_email_log';

		$insert = array(
			'lead_id' => isset( $data['lead_id'] ) ? absint( $data['lead_id'] ) : 0,
			'recipient' => sanitize_email( $data['recipient'] ?? '' ),
			'subject' => sanitize_text_field( $data['subject'] ?? '' ),
			'body' => $data['body'] ?? '',
			'headers' => is_array( $data['headers'] ?? null )
				? implode( "\n", $data['headers'] )
				: (string) ( $data['headers'] ?? '' ),
			'trigger_type' => sanitize_text_field( $data['trigger_type'] ?? self::TRIGGER_NOTIFICATION ),
			'status' => sanitize_text_field( $data['status'] ?? self::STATUS_SENT ),
			'failure_reason' => sanitize_text_field( $data['failure_reason'] ?? '' ),
			'sent_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert );

		if ( false === $result ) {
			error_log( 'XtremeLeads Email Log: failed to insert log entry. DB Error: ' . $wpdb->last_error );
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get paginated log entries.
	 *
	 * @param array $filters Optional filters: lead_id, trigger_type, status.
	 * @param int $page 1-based page number.
	 * @param int $per_page Items per page.
	 * @return array{ logs: array, total: int }
	 */
	public static function get_logs( array $filters = array(), int $page = 1, int $per_page = 25 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_email_log';
		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['lead_id'] ) ) {
			$where[] = 'lead_id = %d';
			$params[] = absint( $filters['lead_id'] );
		}

		if ( ! empty( $filters['trigger_type'] ) ) {
			$where[] = 'trigger_type = %s';
			$params[] = $filters['trigger_type'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $filters['status'];
		}

		$where_sql = implode( ' AND ', $where );
		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		// Count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total = empty( $params )
			? (int) $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Rows.
		$rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY sent_at DESC LIMIT %d OFFSET %d";
		$row_params = array_merge( $params, array( $per_page, $offset ) );
		$logs = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$row_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'logs' => $logs ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @param int $log_id Log ID.
	 * @return object|null
	 */
	public static function get_log( int $log_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_email_log';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?: null;
	}

	/**
	 * Get human-readable trigger type label.
	 *
	 * @param string $type Trigger type constant.
	 * @return string
	 */
	public static function get_trigger_label( string $type ): string {
		$labels = array(
			self::TRIGGER_NOTIFICATION => __( 'Notification', 'xtremeleads' ),
			self::TRIGGER_AUTO_RESPONDER => __( 'Auto-Responder', 'xtremeleads' ),
			self::TRIGGER_ROUTING => __( 'Routing Rule', 'xtremeleads' ),
			self::TRIGGER_TEST => __( 'Test', 'xtremeleads' ),
			self::TRIGGER_RESEND => __( 'Resend', 'xtremeleads' ),
		);

		return $labels[ $type ] ?? ucfirst( $type );
	}

	/**
	 * Get all trigger type labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_trigger_labels(): array {
		return array(
			self::TRIGGER_NOTIFICATION => __( 'Notification', 'xtremeleads' ),
			self::TRIGGER_AUTO_RESPONDER => __( 'Auto-Responder', 'xtremeleads' ),
			self::TRIGGER_ROUTING => __( 'Routing Rule', 'xtremeleads' ),
			self::TRIGGER_TEST => __( 'Test', 'xtremeleads' ),
			self::TRIGGER_RESEND => __( 'Resend', 'xtremeleads' ),
		);
	}
}
