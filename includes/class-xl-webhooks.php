<?php
/**
 * Webhook outbound integration — CRUD, delivery, retry, and delivery log.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Webhooks
 *
 * Handles webhook configuration, firing payloads, retry scheduling,
 * and the delivery log.
 */
class XL_Webhooks {

	/** Trigger event constants. */
	const EVENT_NEW_LEAD = 'new_lead';
	const EVENT_STATUS_CHANGE = 'status_change';

	/** Log status constants. */
	const LOG_STATUS_SENT = 'sent';
	const LOG_STATUS_FAILED = 'failed';
	const LOG_STATUS_RETRY = 'retry';
	const LOG_STATUS_RETRYING = 'retrying';

	/** Maximum response body excerpt stored in the log (characters). */
	const LOG_RESPONSE_MAX = 500;

	/** Maximum delivery log entries retained before pruning oldest. */
	const LOG_MAX_ENTRIES = 500;

	/** Log entries per page. */
	const LOG_PER_PAGE = 25;

	/** Request timeout in seconds. */
	const REQUEST_TIMEOUT = 10;

	/** Cron hook for retry. */
	const RETRY_CRON_HOOK = 'xl_webhook_retry';

	// ─────────────────────────────────────────────────────────────────────────
	// Pending dispatch queue — fires webhooks synchronously in the current PHP
	// request via register_shutdown_function(), AFTER the user's response has
	// been flushed. This guarantees delivery within the same request cycle
	// (deterministic timing, no WP Cron loopback indirection) while still
	// being non-blocking from the submitting user's perspective.
	// ─────────────────────────────────────────────────────────────────────────

	/** Pending dispatches queued for immediate shutdown-phase delivery. */
	private static array $_pending_dispatches = [];

	/** Whether the shutdown callback has already been registered this request. */
	private static bool $_shutdown_registered = false;

	// ─────────────────────────────────────────────────────────────────────────
	// CRUD
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get all webhooks.
	 *
	 * @return array<object>
	 */
	public static function get_all(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhooks';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC"
		) ?: array();
	}

	/**
	 * Get a single webhook by ID.
	 *
	 * @param int $id Webhook ID.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhooks';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		) ?: null;
	}

	/**
	 * Save (insert or update) a webhook.
	 *
	 * @param array $data Webhook data.
	 * @return int|false Webhook ID or false on failure.
	 */
	public static function save( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhooks';
		$now = current_time( 'mysql', true );

		// Sanitize headers: array of {name, value} objects.
		$headers_raw = $data['custom_headers'] ?? array();
		$headers = array();
		if ( is_array( $headers_raw ) ) {
			foreach ( $headers_raw as $h ) {
				$hname = isset( $h['name'] ) ? sanitize_text_field( substr( (string) $h['name'], 0, 256 ) ) : '';
				$hvalue = isset( $h['value'] ) ? sanitize_text_field( substr( (string) $h['value'], 0, 256 ) ) : '';
				if ( '' !== $hname ) {
					$headers[] = array( 'name' => $hname, 'value' => $hvalue );
				}
			}
		}

		// Sanitize trigger events.
		$valid_events = array( self::EVENT_NEW_LEAD, self::EVENT_STATUS_CHANGE );
		$trigger_events = array();
		if ( is_array( $data['trigger_events'] ?? null ) ) {
			foreach ( $data['trigger_events'] as $ev ) {
				if ( in_array( $ev, $valid_events, true ) ) {
					$trigger_events[] = $ev;
				}
			}
		}
		if ( empty( $trigger_events ) ) {
			$trigger_events = array( self::EVENT_NEW_LEAD );
		}

		// Sanitize form_ids (empty array = all forms).
		$form_ids = array();
		if ( is_array( $data['form_ids'] ?? null ) ) {
			$form_ids = array_map( 'absint', $data['form_ids'] );
			$form_ids = array_filter( $form_ids );
			$form_ids = array_values( $form_ids );
		}

		$row = array(
			'name' => sanitize_text_field( substr( (string) ( $data['name'] ?? '' ), 0, 255 ) ),
			'url' => esc_url_raw( (string) ( $data['url'] ?? '' ) ),
			'custom_headers' => wp_json_encode( $headers ),
			'trigger_events' => wp_json_encode( $trigger_events ),
			'form_ids' => wp_json_encode( $form_ids ),
			'is_active' => empty( $data['is_active'] ) ? 0 : 1,
			'updated_at' => $now,
		);

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update( $table, $row, array( 'id' => $id ), null, array( '%d' ) );
			return false !== $result ? $id : false;
		} else {
			$row['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->insert( $table, $row );
			return $result ? (int) $wpdb->insert_id : false;
		}
	}

	/**
	 * Delete a webhook and its delivery log entries.
	 *
	 * @param int $id Webhook ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhooks';
		$log_table = $wpdb->prefix . 'xtremeleads_webhook_log';

		// Remove associated log entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $log_table, array( 'webhook_id' => $id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		return false !== $result;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Webhook firing
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Fire all applicable webhooks for a given event.
	 *
	 * Matching webhooks are queued in a per-request static buffer and dispatched
	 * synchronously via PHP's shutdown function, immediately AFTER the user's HTTP
	 * response has been flushed. This guarantees delivery within the same PHP process
	 * (no WP Cron loopback indirection) and therefore satisfies the 3-second delivery
	 * SLA. On FastCGI / PHP-FPM, fastcgi_finish_request() is called first so the
	 * user connection is closed before the webhook POST is made. Failures are logged
	 * and retried via WP Cron but do NOT block or delay the submitting user.
	 *
	 * @param string $event Event type (new_lead or status_change).
	 * @param int $lead_id Associated lead ID.
	 * @param array $payload JSON-serialisable payload array.
	 * @param int $form_id Form ID to match against webhook form_ids filter.
	 */
	public static function fire_event( string $event, int $lead_id, array $payload, int $form_id ): void {
		$webhooks = self::get_all();

		foreach ( $webhooks as $webhook ) {
			// Skip disabled webhooks.
			if ( ! $webhook->is_active ) {
				continue;
			}

			// Check trigger event filter.
			$trigger_events = json_decode( $webhook->trigger_events, true ) ?: array();
			if ( ! in_array( $event, $trigger_events, true ) ) {
				continue;
			}

			// Check form ID filter (empty = all forms).
			$form_ids = json_decode( $webhook->form_ids, true ) ?: array();
			if ( ! empty( $form_ids ) && ! in_array( $form_id, $form_ids, true ) ) {
				continue;
			}

			// Build full payload with event metadata.
			$full_payload = $payload;
			$full_payload['event'] = $event;
			$full_payload['form_id'] = $form_id;

			// Queue for synchronous dispatch via the shutdown callback.
			self::$_pending_dispatches[] = array(
				'webhook' => $webhook,
				'lead_id' => $lead_id,
				'trigger_type' => $event,
				'payload' => $full_payload,
			);
		}

		// Register the shutdown callback once per request. It will fire immediately
		// after the user's response is delivered, guaranteeing delivery well within
		// the 3-second window (same PHP process, no external loopback required).
		if ( ! empty( self::$_pending_dispatches ) && ! self::$_shutdown_registered ) {
			register_shutdown_function( array( self::class, 'run_pending_dispatches' ) );
			self::$_shutdown_registered = true;
		}
	}

	/**
	 * Flush and execute all pending webhook dispatches.
	 *
	 * Called automatically by PHP's shutdown mechanism immediately after the current
	 * HTTP response has been sent to the browser. On FastCGI / PHP-FPM environments,
	 * fastcgi_finish_request() is invoked first to ensure the user connection is closed
	 * before the (potentially slower) remote POST requests are made.
	 *
	 * This method is declared public so it can be referenced by register_shutdown_function().
	 */
	public static function run_pending_dispatches(): void {
		if ( empty( self::$_pending_dispatches ) ) {
			return;
		}

		// On FastCGI / PHP-FPM: close the client connection before firing POSTs.
		// This ensures the submitting user's browser receives its response immediately
		// while webhook delivery continues in the background within the same process.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@litespeed_finish_request();
		}

		$pending = self::$_pending_dispatches;
		self::$_pending_dispatches = array(); // Clear queue to prevent double-fire.

		foreach ( $pending as $item ) {
			self::dispatch(
				$item['webhook'],
				$item['lead_id'],
				$item['trigger_type'],
				$item['payload'],
				false,
				null
			);
		}
	}

	/**
	 * Dispatch a single webhook POST request.
	 *
	 * @param object $webhook Webhook object from DB.
	 * @param int $lead_id Lead ID (0 for test fires).
	 * @param string $trigger_type Event type string.
	 * @param array $payload Payload to send as JSON.
	 * @param bool $is_retry Whether this is a retry attempt.
	 * @param int|null $original_log_id Original delivery log entry ID (for retries).
	 * @return array {status, http_code, response_body}
	 */
	public static function dispatch(
		object $webhook,
		int $lead_id,
		string $trigger_type,
		array $payload,
		bool $is_retry,
		?int $original_log_id
	): array {
		$custom_headers = json_decode( $webhook->custom_headers, true ) ?: array();

		// Build WP HTTP headers array.
		$headers = array( 'Content-Type' => 'application/json' );
		foreach ( $custom_headers as $h ) {
			$name = $h['name'] ?? '';
			$val = $h['value'] ?? '';
			if ( '' !== $name ) {
				// If a custom header overrides Content-Type, allow it (per spec).
				$headers[ $name ] = $val;
			}
		}

		$body = wp_json_encode( $payload );
		$url = $webhook->url;
		$log_id = null;
		$status = self::LOG_STATUS_FAILED;
		$http_code = 0;
		$response_body = '';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body' => $body,
				'timeout' => self::REQUEST_TIMEOUT,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$response_body = $response->get_error_message();
			$http_code = 0;
			$status = self::LOG_STATUS_FAILED;
		} else {
			$http_code = (int) wp_remote_retrieve_response_code( $response );
			$response_body = (string) wp_remote_retrieve_body( $response );
			$status = ( $http_code >= 200 && $http_code < 300 ) ? self::LOG_STATUS_SENT : self::LOG_STATUS_FAILED;
		}

		// Truncate response body to 500 chars.
		if ( strlen( $response_body ) > self::LOG_RESPONSE_MAX ) {
			$response_body = substr( $response_body, 0, self::LOG_RESPONSE_MAX ) . '…';
		}

		// Write delivery log entry.
		$log_id = self::add_log_entry( array(
			'webhook_id' => (int) $webhook->id,
			'lead_id' => $lead_id,
			'trigger_type' => $trigger_type,
			'url' => $url,
			'status' => $status,
			'http_code' => $http_code,
			'response_body' => $response_body,
			'is_retry' => $is_retry ? 1 : 0,
			'original_attempt_id' => $original_log_id,
		) );

		// Schedule retry if failed (and not already a retry).
		if ( self::LOG_STATUS_FAILED === $status && ! $is_retry ) {
			self::schedule_retry( (int) $webhook->id, $lead_id, $trigger_type, $payload, (int) $log_id );
		}

		// Prune log to max entries.
		self::prune_log( (int) $webhook->id );

		return array(
			'status' => $status,
			'http_code' => $http_code,
			'response_body' => $response_body,
		);
	}

	/**
	 * Test-fire a webhook with a sample payload.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array Result with status, http_code, response_body, and error_message.
	 */
	public static function test_fire( int $webhook_id ): array {
		$webhook = self::get( $webhook_id );
		if ( ! $webhook ) {
			return array(
				'status' => 'error',
				'http_code' => 0,
				'response_body' => '',
				'error_message' => __( 'Webhook not found.', 'xtremeleads' ),
			);
		}

		$payload = array(
			'event' => 'test',
			'form_id' => 0,
			'lead_id' => 'test',
			'timestamp' => gmdate( 'c' ),
			'source_url' => home_url(),
			'ip_address' => '0.0.0.0',
			'fields' => array(
				'name' => 'Test Lead',
				'email' => 'test@example.com',
			),
		);

		$custom_headers = json_decode( $webhook->custom_headers, true ) ?: array();
		$headers = array( 'Content-Type' => 'application/json' );
		foreach ( $custom_headers as $h ) {
			$name = $h['name'] ?? '';
			$val = $h['value'] ?? '';
			if ( '' !== $name ) {
				$headers[ $name ] = $val;
			}
		}

		$body = wp_json_encode( $payload );
		$http_code = 0;
		$response_body = '';
		$error_message = '';
		$status = self::LOG_STATUS_FAILED;

		$response = wp_remote_post(
			$webhook->url,
			array(
				'headers' => $headers,
				'body' => $body,
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			$response_body = $response->get_error_message();
			$error_message = $response->get_error_message();
			$status = 'error';
		} else {
			$http_code = (int) wp_remote_retrieve_response_code( $response );
			$response_body = (string) wp_remote_retrieve_body( $response );
			$status = ( $http_code >= 200 && $http_code < 300 ) ? self::LOG_STATUS_SENT : self::LOG_STATUS_FAILED;
		}

		if ( strlen( $response_body ) > self::LOG_RESPONSE_MAX ) {
			$response_body = substr( $response_body, 0, self::LOG_RESPONSE_MAX ) . '…';
		}

		// Log the test fire (lead_id = 0 indicates test).
		self::add_log_entry( array(
			'webhook_id' => $webhook_id,
			'lead_id' => 0,
			'trigger_type' => 'test',
			'url' => $webhook->url,
			'status' => $status,
			'http_code' => $http_code,
			'response_body' => $response_body,
			'is_retry' => 0,
			'original_attempt_id' => null,
		) );

		// Prune log.
		self::prune_log( $webhook_id );

		return array(
			'status' => $status,
			'http_code' => $http_code,
			'response_body' => $response_body,
			'error_message' => $error_message,
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Retry via WP Cron
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Schedule a retry for a failed webhook dispatch.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @param int $lead_id Lead ID.
	 * @param string $trigger_type Trigger event type.
	 * @param array $payload Original payload.
	 * @param int $original_log_id Original delivery log entry ID.
	 */
	public static function schedule_retry(
		int $webhook_id,
		int $lead_id,
		string $trigger_type,
		array $payload,
		int $original_log_id
	): void {
		// Schedule 60 seconds from now — non-blocking, via cron.
		wp_schedule_single_event(
			time() + 60,
			self::RETRY_CRON_HOOK,
			array( $webhook_id, $lead_id, $trigger_type, $payload, $original_log_id )
		);
	}

	/**
	 * Execute a retry. Called by WP Cron.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @param int $lead_id Lead ID.
	 * @param string $trigger_type Trigger event type.
	 * @param array $payload Original payload.
	 * @param int $original_log_id Original delivery log entry ID.
	 */
	public static function execute_retry(
		int $webhook_id,
		int $lead_id,
		string $trigger_type,
		array $payload,
		int $original_log_id
	): void {
		$webhook = self::get( $webhook_id );
		if ( ! $webhook ) {
			return;
		}

		self::dispatch( $webhook, $lead_id, $trigger_type, $payload, true, $original_log_id );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Delivery Log
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Insert a delivery log entry.
	 *
	 * @param array $data Log entry data.
	 * @return int|false Log entry ID or false.
	 */
	public static function add_log_entry( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhook_log';
		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'webhook_id' => absint( $data['webhook_id'] ),
				'lead_id' => absint( $data['lead_id'] ),
				'trigger_type' => sanitize_text_field( $data['trigger_type'] ?? '' ),
				'url' => esc_url_raw( $data['url'] ?? '' ),
				'status' => sanitize_text_field( $data['status'] ?? 'failed' ),
				'http_code' => absint( $data['http_code'] ?? 0 ),
				'response_body' => sanitize_textarea_field( $data['response_body'] ?? '' ),
				'is_retry' => absint( $data['is_retry'] ?? 0 ),
				'original_attempt_id' => isset( $data['original_attempt_id'] ) ? absint( $data['original_attempt_id'] ) : null,
				'delivered_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get delivery log entries for a webhook (paginated).
	 *
	 * @param int $webhook_id Webhook ID.
	 * @param int $page Page number (1-based).
	 * @return array {items, total, pages}
	 */
	public static function get_log( int $webhook_id, int $page = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhook_log';
		$offset = ( max( 1, $page ) - 1 ) * self::LOG_PER_PAGE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE webhook_id = %d",
				$webhook_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE webhook_id = %d ORDER BY delivered_at DESC LIMIT %d OFFSET %d",
				$webhook_id,
				self::LOG_PER_PAGE,
				$offset
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / self::LOG_PER_PAGE ),
		);
	}

	/**
	 * Get all delivery log entries (for global log view), paginated.
	 *
	 * @param int $page Page number.
	 * @return array {items, total, pages}
	 */
	public static function get_all_log( int $page = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhook_log';
		$hooks_table = $wpdb->prefix . 'xtremeleads_webhooks';
		$offset = ( max( 1, $page ) - 1 ) * self::LOG_PER_PAGE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, w.name AS webhook_name FROM {$table} l
				LEFT JOIN {$hooks_table} w ON w.id = l.webhook_id
				ORDER BY l.delivered_at DESC
				LIMIT %d OFFSET %d",
				self::LOG_PER_PAGE,
				$offset
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / self::LOG_PER_PAGE ),
		);
	}

	/**
	 * Prune the delivery log for a webhook to keep only the newest entries.
	 *
	 * @param int $webhook_id Webhook ID.
	 */
	private static function prune_log( int $webhook_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_webhook_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE webhook_id = %d",
				$webhook_id
			)
		);

		if ( $count > self::LOG_MAX_ENTRIES ) {
			$delete_count = $count - self::LOG_MAX_ENTRIES;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE webhook_id = %d ORDER BY delivered_at ASC LIMIT %d",
					$webhook_id,
					$delete_count
				)
			);
		}
	}

	/**
	 * Build a lead payload for webhook dispatch.
	 *
	 * @param int $lead_id Lead ID.
	 * @param array $field_values Field values.
	 * @param string $source_url Source URL.
	 * @param string $ip_address IP address.
	 * @param string $timestamp Timestamp string.
	 * @return array
	 */
	public static function build_payload(
		int $lead_id,
		array $field_values,
		string $source_url,
		string $ip_address,
		string $timestamp
	): array {
		return array(
			'lead_id' => $lead_id,
			'timestamp' => $timestamp,
			'source_url' => $source_url,
			'ip_address' => $ip_address,
			'fields' => $field_values,
		);
	}
}

// Register the cron callback for retries (60-second minimum delay after initial failure).
add_action( XL_Webhooks::RETRY_CRON_HOOK, array( 'XL_Webhooks', 'execute_retry' ), 10, 5 );
