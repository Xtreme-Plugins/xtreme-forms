<?php
/**
 * GDPR & Compliance Toolkit — Right to Erasure, retention purge, IP anonymization helpers.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_GDPR
 */
class Xtremeforms_GDPR {

	/** Cron hook for daily retention purge. */
	const RETENTION_CRON_HOOK = 'xtremeforms_gdpr_retention_purge';

	/**
	 * Register cron actions on class load.
	 */
	public static function init(): void {
		add_action( self::RETENTION_CRON_HOOK, array( __CLASS__, 'run_retention_purge' ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Right to Erasure
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Permanently delete all data associated with a given email address.
	 *
	 * Removes from:
	 * - leads (wp_xtremeforms_leads)
	 * - notes (wp_xtremeforms_notes)
	 * - lead_tags pivot (wp_xtremeforms_lead_tags)
	 * - email_log (wp_xtremeforms_email_log)
	 * - activity (wp_xtremeforms_activity)
	 *
	 * @param string $email Email address to erase.
	 * @return array{deleted_leads: int, found: bool, deleted_ids: int[]}
	 */
	public static function erase_by_email( string $email ): array {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return array(
				'deleted_leads' => 0,
				'found'         => false,
				'deleted_ids'   => array(),
			);
		}

		// Find all lead IDs for this email.
		$leads_table = $wpdb->prefix . 'xtremeforms_leads';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lead_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$leads_table} WHERE email_address = %s",
				strtolower( $email )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $lead_ids ) ) {
			return array(
				'deleted_leads' => 0,
				'found'         => false,
				'deleted_ids'   => array(),
			);
		}

		$int_ids = array_map( 'intval', $lead_ids );
		$count   = count( $int_ids );

		// Delete associated records via cascade helper.
		self::delete_leads_cascade( $lead_ids );

		return array(
			'deleted_leads' => $count,
			'found'         => true,
			'deleted_ids'   => $int_ids,
		);
	}

	/**
	 * Delete leads by ID array, including all associated data.
	 *
	 * @param int[] $lead_ids Array of lead IDs to delete.
	 */
	public static function delete_leads_cascade( array $lead_ids ): void {
		global $wpdb;

		if ( empty( $lead_ids ) ) {
			return;
		}

		$lead_ids     = array_map( 'absint', $lead_ids );
		$placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );

		$tables = array(
			$wpdb->prefix . 'xtremeforms_notes'     => 'lead_id',
			$wpdb->prefix . 'xtremeforms_lead_tags' => 'lead_id',
			$wpdb->prefix . 'xtremeforms_email_log' => 'lead_id',
			$wpdb->prefix . 'xtremeforms_activity'  => 'lead_id',
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $tables as $table => $col ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$col} IN ({$placeholders})", ...$lead_ids ) );
		}

		// Delete leads.
		$leads_table = $wpdb->prefix . 'xtremeforms_leads';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$leads_table} WHERE id IN ({$placeholders})", ...$lead_ids ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Data Retention / Purge Policy
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get the retention period in days from settings.
	 *
	 * @return int|null Number of days, or null if not configured.
	 */
	public static function get_retention_days(): ?int {
		$settings = get_option( 'xtremeforms_settings', array() );
		$days     = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 0;
		return $days >= 1 ? $days : null;
	}

	/**
	 * Update the retention policy setting and schedule/unschedule cron accordingly.
	 *
	 * @param int|null $days Number of days (null or 0 to disable).
	 */
	public static function update_retention_policy( ?int $days ): void {
		$settings = get_option( 'xtremeforms_settings', array() );

		if ( null === $days || $days < 1 ) {
			unset( $settings['retention_days'] );
			update_option( 'xtremeforms_settings', $settings );
			self::unschedule_retention_cron();
		} else {
			$settings['retention_days'] = (int) $days;
			update_option( 'xtremeforms_settings', $settings );
			self::schedule_retention_cron();
		}
	}

	/**
	 * Schedule the daily retention purge cron if not already scheduled.
	 */
	public static function schedule_retention_cron(): void {
		if ( ! wp_next_scheduled( self::RETENTION_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::RETENTION_CRON_HOOK );
		}
	}

	/**
	 * Unschedule the retention purge cron.
	 */
	public static function unschedule_retention_cron(): void {
		$timestamp = wp_next_scheduled( self::RETENTION_CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::RETENTION_CRON_HOOK );
		}
	}

	/**
	 * Run the retention purge — delete leads older than the configured N days.
	 * Called by WP Cron daily.
	 */
	public static function run_retention_purge(): void {
		$days = self::get_retention_days();
		if ( null === $days ) {
			return;
		}

		global $wpdb;
		$leads_table = $wpdb->prefix . 'xtremeforms_leads';
		$cutoff      = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Find all lead IDs older than the cutoff.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lead_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$leads_table} WHERE created_at < %s",
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $lead_ids ) ) {
			self::delete_leads_cascade( array_map( 'intval', $lead_ids ) );
		}
	}

	/**
	 * Get the next scheduled retention purge timestamp (human-readable).
	 *
	 * @return string|null Human-readable time or null if not scheduled.
	 */
	public static function get_next_purge_time(): ?string {
		$ts = wp_next_scheduled( self::RETENTION_CRON_HOOK );
		if ( ! $ts ) {
			return null;
		}
		// Format in site timezone.
		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
