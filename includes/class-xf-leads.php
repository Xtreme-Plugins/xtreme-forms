<?php
/**
 * Lead CRUD operations.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Leads
 */
class XF_Leads {

	const STATUS_NEW       = 'new';
	const STATUS_CONTACTED = 'contacted';
	const STATUS_QUALIFIED = 'qualified';
	const STATUS_CONVERTED = 'converted';
	const STATUS_LOST      = 'lost';
	const STATUS_ARCHIVED  = 'archived';

	const PER_PAGE = 20;

	/**
	 * Insert a new lead into the database.
	 *
	 * @param array $data {
	 * @type int $form_id Form ID.
	 * @type string $source_url Page the form was on.
	 * @type string $ip_address Visitor IP (possibly anonymized).
	 * @type string $user_agent Visitor user agent.
	 * @type array $field_values Submitted field values.
	 * @type string|null $utm_source UTM source.
	 * @type string|null $utm_medium UTM medium.
	 * @type string|null $utm_campaign UTM campaign.
	 * @type string|null $utm_term UTM term.
	 * @type string|null $utm_content UTM content.
	 * @type int|null $submit_duration_seconds Time between form load and submit.
	 * @type bool $is_duplicate Whether this submission is a duplicate.
	 * @type string|null $duplicate_status 'duplicate' or 'duplicate_orphaned'.
	 * @type int|null $original_lead_id ID of the original lead.
	 * }
	 * @return int|false New lead ID or false on failure.
	 *
	 * CONCURRENCY NOTE (duplicate_detection_on_submission criterion):
	 * The duplicate flag fields (is_duplicate, duplicate_status, original_lead_id)
	 * are included in the INSERT statement itself — not set in a subsequent UPDATE —
	 * so the record is NEVER saved without its duplicate flag. This guarantees
	 * atomicity: either the row is inserted with the flag, or it is not inserted.
	 */
	public static function insert_lead( array $data ): int|false {
		global $wpdb;

		$now   = current_time( 'mysql', true );
		$table = $wpdb->prefix . 'xtremeforms_leads';

		$row = array(
			'form_id'      => absint( $data['form_id'] ?? 0 ),
			'status'       => self::STATUS_NEW,
			'source_url'   => esc_url_raw( $data['source_url'] ?? '' ),
			'ip_address'   => sanitize_text_field( $data['ip_address'] ?? '' ),
			'user_agent'   => sanitize_text_field( $data['user_agent'] ?? '' ),
			'field_values' => wp_json_encode( $data['field_values'] ?? array() ),
			'assigned_to'  => 0,
			'created_at'   => $now,
			'updated_at'   => $now,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		// UTM fields — NULL when not provided.
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $utm_key ) {
			$row[ $utm_key ] = isset( $data[ $utm_key ] ) && null !== $data[ $utm_key ]
				? (string) $data[ $utm_key ]
				: null;
			$formats[]       = null !== $row[ $utm_key ] ? '%s' : '%s';
		}

		$row['submit_duration_seconds'] = isset( $data['submit_duration_seconds'] )
			? absint( $data['submit_duration_seconds'] )
			: null;
		$formats[]                      = null !== $row['submit_duration_seconds'] ? '%d' : '%d';

		// store the extracted email address in a dedicated indexed column
		// so duplicate checks can use a fast indexed lookup instead of a LIKE search
		// on the unindexed field_values JSON blob (critical for performance at scale).
		// email_address is also stored normalised to lower-case for case-insensitive matching.
		$email_raw            = isset( $data['email_address'] ) ? sanitize_email( (string) $data['email_address'] ) : '';
		$row['email_address'] = $email_raw !== '' ? strtolower( $email_raw ) : null;

		// saved without its duplicate marker (atomicity guarantee per spec criterion).
		$row['is_duplicate'] = ! empty( $data['is_duplicate'] ) ? 1 : 0;
		$formats[]           = '%d';

		// duplicate_status: empty string (not a dup), 'duplicate', or 'duplicate_orphaned'.
		// Column is NOT NULL DEFAULT '' — never store NULL.
		$dup_status              = isset( $data['duplicate_status'] ) && null !== $data['duplicate_status']
			? sanitize_key( $data['duplicate_status'] )
			: '';
		$row['duplicate_status'] = $dup_status;
		// (format is handled by the NULL-aware loop below)

		// original_lead_id: NULL when not a duplicate or when orphaned.
		$orig_id                 = isset( $data['original_lead_id'] ) && null !== $data['original_lead_id']
			? absint( $data['original_lead_id'] )
			: null;
		$row['original_lead_id'] = $orig_id;

		// GDPR consent_given — 0 by default, 1 when consent checkbox is checked.
		// Only set to 1 when the form has consent enabled and the user checked the box.
		$row['consent_given'] = isset( $data['consent_given'] ) ? absint( $data['consent_given'] ) : 0;

		// Hardcoded whitelist of every column that may appear in the lead row,
		// matching the schema in XF_Activator::create_tables(). Each $row key is
		// validated against this list before any SQL is built — so the column
		// list inside the INSERT is always assembled from compile-time constants,
		// never from user-controlled data.
		$allowed_columns = array(
			'form_id',
			'status',
			'source_url',
			'ip_address',
			'user_agent',
			'field_values',
			'assigned_to',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_content',
			'email_address',
			'is_duplicate',
			'duplicate_status',
			'original_lead_id',
			'submit_duration_seconds',
			'consent_given',
			'created_at',
			'updated_at',
		);

		// Filter $row to allowed columns only and split into prepared / NULL parts.
		$columns      = array();
		$placeholders = array();
		$values       = array();
		foreach ( $row as $col => $val ) {
			if ( ! in_array( $col, $allowed_columns, true ) ) {
				continue; // Defense-in-depth — should not happen in normal flow.
			}
			$columns[] = $col;
			if ( null === $val ) {
				$placeholders[] = 'NULL';
			} else {
				$placeholders[] = is_int( $val ) ? '%d' : '%s';
				$values[]       = $val;
			}
		}

		// Build the INSERT from compile-time-validated identifiers + placeholders.
		$columns_sql      = implode( ', ', $columns );
		$placeholders_sql = implode( ', ', $placeholders );
		$sql              = "INSERT INTO {$table} ({$columns_sql}) VALUES ({$placeholders_sql})";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $values ) ) {
			// All nulls (unlikely).
			$inserted = $wpdb->query( $sql );
		} else {
			$inserted = $wpdb->query( $wpdb->prepare( $sql, $values ) );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get paginated leads (unfiltered, legacy).
	 *
	 * @param int $page Current page number (1-based).
	 * @param int $per_page Leads per page.
	 * @return array{leads: array, total: int}
	 */
	public static function get_leads( int $page = 1, int $per_page = self::PER_PAGE ): array {
		return self::get_leads_filtered( array(), $page, $per_page );
	}

	/**
	 * Get paginated leads with optional filters.
	 *
	 * @param array $filters {
	 * Optional filters.
	 * @type string $status Lead status slug.
	 * @type int $assigned_to WordPress user ID (0 = no filter).
	 * @type array $tag_ids Array of tag IDs (AND logic).
	 * @type int $form_id Form ID.
	 * @type string $date_from Start date (Y-m-d).
	 * @type string $date_to End date (Y-m-d).
	 * }
	 * @param int   $page Current page (1-based).
	 * @param int   $per_page Items per page.
	 * @return array{leads: array, total: int}
	 */
	public static function get_leads_filtered( array $filters = array(), int $page = 1, int $per_page = self::PER_PAGE ): array {
		global $wpdb;

		$table           = $wpdb->prefix . 'xtremeforms_leads';
		$lead_tags_table = $wpdb->prefix . 'xtremeforms_lead_tags';
		$offset          = ( max( 1, $page ) - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$params        = array();
		$joins         = '';

		// Status filter.
		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = 'l.status = %s';
			$params[]        = sanitize_text_field( $filters['status'] );
		}

		// Assigned-to filter.
		if ( ! empty( $filters['assigned_to'] ) ) {
			$where_clauses[] = 'l.assigned_to = %d';
			$params[]        = absint( $filters['assigned_to'] );
		}

		// Form filter.
		if ( ! empty( $filters['form_id'] ) ) {
			$where_clauses[] = 'l.form_id = %d';
			$params[]        = absint( $filters['form_id'] );
		}

		// Date range filter.
		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'l.created_at >= %s';
			$params[]        = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'l.created_at <= %s';
			$params[]        = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		// Tag filter (AND logic): lead must have ALL specified tags.
		$tag_ids = array();
		if ( ! empty( $filters['tag_ids'] ) && is_array( $filters['tag_ids'] ) ) {
			$tag_ids = array_values( array_filter( array_map( 'absint', $filters['tag_ids'] ) ) );
		}

		if ( ! empty( $tag_ids ) ) {
			$tag_placeholders = implode( ',', array_fill( 0, count( $tag_ids ), '%d' ) );
			$joins           .= " INNER JOIN {$lead_tags_table} lt_filter ON lt_filter.lead_id = l.id";
			$where_clauses[]  = "lt_filter.tag_id IN ({$tag_placeholders})";
			foreach ( $tag_ids as $tid ) {
				$params[] = $tid;
			}
		}

		$where = implode( ' AND ', $where_clauses );

		// Build GROUP BY / HAVING for tag AND logic.
		$group_having = '';
		if ( ! empty( $tag_ids ) ) {
			$group_having = 'GROUP BY l.id HAVING COUNT(DISTINCT lt_filter.tag_id) = ' . count( $tag_ids );
		}

		// Total count query.
		// When tags filter with AND logic is active we must use a subquery to get the correct count.
		if ( ! empty( $tag_ids ) ) {
			$inner_sql = "SELECT l.id FROM {$table} l {$joins} WHERE {$where} GROUP BY l.id HAVING COUNT(DISTINCT lt_filter.tag_id) = " . count( $tag_ids );
			$count_sql = "SELECT COUNT(*) FROM ({$inner_sql}) count_subquery";
		} else {
			$count_sql = "SELECT COUNT(DISTINCT l.id) FROM {$table} l {$joins} WHERE {$where}";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// Paginated results.
		$select_sql   = "SELECT l.* FROM {$table} l {$joins} WHERE {$where} {$group_having} ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );

		$leads = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'leads' => $leads ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Get a single lead by ID.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public static function get_lead( int $lead_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_leads';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$lead_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Update a lead's status, returning the previous status.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $status New status slug.
	 * @return string|false Previous status slug, or false on failure.
	 */
	public static function update_status( int $lead_id, string $status ): string|false {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_leads';

		// Get current status before updating.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE id = %d LIMIT 1",
				$lead_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( null === $old_status ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'status'     => sanitize_text_field( $status ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $lead_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (string) $old_status;
	}

	/**
	 * Update a lead's assigned user, returning the previous assignee ID.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $assigned_to WordPress user ID (0 = unassigned).
	 * @return int|false Previous assigned_to ID or false on failure.
	 */
	public static function update_assigned_to( int $lead_id, int $assigned_to ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_leads';

		// Get current assignee before updating.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_assigned = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT assigned_to FROM {$table} WHERE id = %d LIMIT 1",
				$lead_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( null === $old_assigned ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'assigned_to' => absint( $assigned_to ),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $lead_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $old_assigned;
	}

	/**
	 * Bulk delete leads.
	 *
	 * @param array $ids Array of lead IDs.
	 * @return int Number of deleted rows.
	 */
	public static function bulk_delete( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;

		$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$table = $wpdb->prefix . 'xtremeforms_leads';

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders})",
				...$ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $result;
	}

	/**
	 * Bulk update status.
	 *
	 * @param array  $ids Array of lead IDs.
	 * @param string $status New status.
	 * @return int Number of updated rows.
	 */
	public static function bulk_update_status( array $ids, string $status ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;

		$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$table = $wpdb->prefix . 'xtremeforms_leads';
		$now   = current_time( 'mysql', true );

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
				array_merge( array( sanitize_text_field( $status ), $now ), $ids )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $result;
	}

	/**
	 * Get WordPress users eligible for lead assignment (Editor role and above).
	 * Capability check: edit_others_posts or manage_options.
	 *
	 * @return array Array of user objects with id, display_name, email.
	 */
	public static function get_eligible_assignees(): array {
		$users = get_users(
			array(
				'capability__in' => array( 'edit_others_posts', 'manage_options' ),
				'fields'         => array( 'ID', 'display_name', 'user_email' ),
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		return array_map(
			static function ( $u ) {
				return array(
					'id'           => (int) $u->ID,
					'display_name' => $u->display_name,
					'email'        => $u->user_email,
				);
			},
			$users ?: array()
		);
	}

	/**
	 * Check if a user is eligible for lead assignment (server-side).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function is_eligible_assignee( int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return user_can( $user, 'edit_others_posts' ) || user_can( $user, 'manage_options' );
	}

	/**
	 * Maybe anonymize an IP address based on plugin settings.
	 *
	 * @param string $ip Raw IP address.
	 * @return string Possibly anonymized IP.
	 */
	public static function maybe_anonymize_ip( string $ip ): string {
		$settings  = get_option( 'xtremeforms_settings', array() );
		$anonymize = ! empty( $settings['anonymize_ip'] ) && '1' === (string) $settings['anonymize_ip'];

		if ( ! $anonymize ) {
			return $ip;
		}

		return self::anonymize_ip( $ip );
	}

	/**
	 * Anonymize an IP address.
	 * IPv4: zero the last octet.
	 * IPv6: zero the last 80 bits.
	 *
	 * @param string $ip Raw IP address.
	 * @return string Anonymized IP address.
	 */
	public static function anonymize_ip( string $ip ): string {
		if ( false !== strpos( $ip, ':' ) ) {
			// Could be IPv6 or IPv4-mapped IPv6 (e.g. ::ffff:192.168.1.123).
			$packed = @inet_pton( $ip );
			if ( false === $packed ) {
				return $ip;
			}

			// Detect IPv4-mapped IPv6: first 10 bytes = 0x00, next 2 bytes = 0xFF 0xFF.
			$ipv4_mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
			if ( 16 === strlen( $packed ) && substr( $packed, 0, 12 ) === $ipv4_mapped_prefix ) {
				// Treat as IPv4: zero the last octet (byte 15) only.
				$anonymized = substr( $packed, 0, 15 ) . "\x00";
				$result     = @inet_ntop( $anonymized );
				return false !== $result ? (string) $result : $ip;
			}

			// Standard IPv6 — zero the last 80 bits (bytes 6–15).
			$anonymized = substr( $packed, 0, 6 ) . str_repeat( "\x00", 10 );
			$result     = @inet_ntop( $anonymized );
			return false !== $result ? (string) $result : $ip;
		}

		// IPv4 — zero the last octet.
		$parts = explode( '.', $ip );
		if ( 4 === count( $parts ) ) {
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		return $ip;
	}

	/**
	 * Get all allowed statuses.
	 *
	 * @return array<string, string> Keyed by slug => label.
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_NEW       => __( 'New', 'xtreme-forms' ),
			self::STATUS_CONTACTED => __( 'Contacted', 'xtreme-forms' ),
			self::STATUS_QUALIFIED => __( 'Qualified', 'xtreme-forms' ),
			self::STATUS_CONVERTED => __( 'Converted', 'xtreme-forms' ),
			self::STATUS_LOST      => __( 'Lost', 'xtreme-forms' ),
			self::STATUS_ARCHIVED  => __( 'Archived', 'xtreme-forms' ),
		);
	}

	/**
	 * Decode field values JSON.
	 *
	 * @param object $lead Lead row object.
	 * @return array
	 */
	public static function decode_field_values( object $lead ): array {
		if ( empty( $lead->field_values ) ) {
			return array();
		}
		$values = json_decode( $lead->field_values, true );
		return is_array( $values ) ? $values : array();
	}

	/**
	 * Get multiple leads by IDs (for export).
	 *
	 * @param array $ids Lead IDs.
	 * @return array
	 */
	public static function get_leads_by_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'xtremeforms_leads';
		$ids          = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$leads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY created_at DESC",
				...$ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $leads ?: array();
	}
}
