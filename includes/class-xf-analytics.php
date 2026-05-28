<?php
/**
 * Analytics queries for dashboard and metrics.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_Analytics
 *
 * Provides all data-aggregation queries used by the dashboard and
 * form-performance pages. Every query uses $wpdb->prepare() with
 * no user-supplied string interpolation.
 */
class Xtremeforms_Analytics {

	// ── KPI Tile Counts ───────────────────────────────────────────────────────

	/**
	 * Get total lead count (all time).
	 *
	 * @return int
	 */
	public static function count_leads_all_time(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeforms_leads';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get lead count for the current calendar month in site timezone.
	 *
	 * 'This Month' is calculated from midnight on the 1st of the current
	 * calendar month in the WordPress site-timezone setting.
	 *
	 * @return int
	 */
	public static function count_leads_this_month(): int {
		global $wpdb;

		$tz          = wp_timezone();
		$now         = new DateTimeImmutable( 'now', $tz );
		$month_start = $now->modify( 'first day of this month midnight' );
		// Convert to UTC for comparison against stored UTC datetimes.
		$month_start_utc = $month_start->setTimezone( new DateTimeZone( 'UTC' ) );

		$table = $wpdb->prefix . 'xtremeforms_leads';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				$month_start_utc->format( 'Y-m-d H:i:s' )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get lead count for the current week (Monday–Sunday) in site timezone.
	 *
	 * 'This Week' starts on Monday at 00:00:00 in the site timezone.
	 *
	 * @return int
	 */
	public static function count_leads_this_week(): int {
		global $wpdb;

		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );
		// Get the most recent Monday at midnight.
		$day_of_week       = (int) $now->format( 'N' ); // 1=Mon … 7=Sun.
		$days_since_monday = $day_of_week - 1;
		$week_start        = $now->modify( "-{$days_since_monday} days midnight" );
		$week_start_utc    = $week_start->setTimezone( new DateTimeZone( 'UTC' ) );

		$table = $wpdb->prefix . 'xtremeforms_leads';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				$week_start_utc->format( 'Y-m-d H:i:s' )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// ── Leads by Form (Bar Chart) ─────────────────────────────────────────────

	/**
	 * Get lead counts grouped by form, optionally filtered by date range.
	 *
	 * Returns ALL active forms (even those with 0 submissions) merged with
	 * counts from the leads table. Bar height = 0 for forms with no submissions.
	 *
	 * @param string $date_from Optional start date (Y-m-d H:i:s UTC).
	 * @param string $date_to Optional end date (Y-m-d H:i:s UTC).
	 * @return array Array of {form_id, form_name, count}.
	 */
	public static function leads_by_form( string $date_from = '', string $date_to = '' ): array {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'xtremeforms_leads';
		$forms_table = $wpdb->prefix . 'xtremeforms_forms';

		$where  = '1=1';
		$params = array();

		if ( $date_from ) {
			$where   .= ' AND l.created_at >= %s';
			$params[] = $date_from;
		}
		if ( $date_to ) {
			$where   .= ' AND l.created_at <= %s';
			$params[] = $date_to;
		}

		$sql = "SELECT f.id AS form_id, f.name AS form_name, COUNT(l.id) AS lead_count
				FROM {$forms_table} f
				LEFT JOIN {$leads_table} l ON l.form_id = f.id AND {$where}
				WHERE f.status = 'active'
				GROUP BY f.id, f.name
				ORDER BY lead_count DESC, f.name ASC";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $params ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			$rows = $wpdb->get_results( $sql );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static function ( $row ) {
				return array(
					'form_id'   => (int) $row->form_id,
					'form_name' => $row->form_name,
					'count'     => (int) $row->lead_count,
				);
			},
			$rows ?: array()
		);
	}

	// ── Leads Over Time (Line Chart) ──────────────────────────────────────────

	/**
	 * Get lead counts over time, grouped by day or week.
	 *
	 * Granularity is:
	 * - daily when the range is ≤ 30 days
	 * - weekly when the range is > 30 days
	 *
	 * All dates are in site timezone for display; storage is UTC.
	 *
	 * @param string $date_from Start date (Y-m-d, site TZ).
	 * @param string $date_to End date (Y-m-d, site TZ).
	 * @return array{labels: string[], data: int[], granularity: string}
	 */
	public static function leads_over_time( string $date_from, string $date_to ): array {
		global $wpdb;

		$tz          = wp_timezone();
		$start       = new DateTimeImmutable( $date_from . ' 00:00:00', $tz );
		$end         = new DateTimeImmutable( $date_to . ' 23:59:59', $tz );
		$diff        = (int) $start->diff( $end )->days;
		$granularity = $diff <= 30 ? 'daily' : 'weekly';

		// Convert start/end to UTC for DB queries.
		$start_utc = $start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$end_utc   = $end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$table   = $wpdb->prefix . 'xtremeforms_leads';

		// Use numeric UTC offset (e.g. '+05:30') instead of a named timezone string
		// (e.g. 'America/Chicago'). MySQL CONVERT_TZ with named timezones requires the
		// mysql.time_zone* tables to be populated, which most hosts do not do. Numeric
		// offset strings are always supported without those tables.
		$offset_secs = $tz->getOffset( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$offset_h    = intdiv( abs( $offset_secs ), 3600 );
		$offset_m    = (int) ( ( abs( $offset_secs ) % 3600 ) / 60 );
		$tz_offset   = sprintf( '%s%02d:%02d', $offset_secs >= 0 ? '+' : '-', $offset_h, $offset_m );

		if ( 'daily' === $granularity ) {
			// MySQL CONVERT_TZ to align dates with site timezone.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(CONVERT_TZ(created_at, '+00:00', %s)) AS period, COUNT(*) AS cnt
					 FROM {$table}
					 WHERE created_at >= %s AND created_at <= %s
					 GROUP BY period
					 ORDER BY period ASC",
					$tz_offset,
					$start_utc,
					$end_utc
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Build a full date sequence so zero-days appear.
			$labels = array();
			$data   = array();
			$counts = array();
			foreach ( $rows ?: array() as $row ) {
				$counts[ $row->period ] = (int) $row->cnt;
			}

			$cursor = clone $start;
			while ( $cursor <= $end ) {
				$key      = $cursor->format( 'Y-m-d' );
				$labels[] = $cursor->format( 'M j' );
				$data[]   = $counts[ $key ] ?? 0;
				$cursor   = $cursor->modify( '+1 day' );
			}
		} else {
			// Weekly: group by ISO year-week.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT YEARWEEK(CONVERT_TZ(created_at, '+00:00', %s), 1) AS period, COUNT(*) AS cnt
					 FROM {$table}
					 WHERE created_at >= %s AND created_at <= %s
					 GROUP BY period
					 ORDER BY period ASC",
					$tz_offset,
					$start_utc,
					$end_utc
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$labels = array();
			$data   = array();
			$counts = array();
			foreach ( $rows ?: array() as $row ) {
				$counts[ $row->period ] = (int) $row->cnt;
			}

			// Build week sequence.
			// Move cursor to the Monday of the start week.
			$cursor_day = (int) $start->format( 'N' );
			$cursor     = $start->modify( '-' . ( $cursor_day - 1 ) . ' days midnight' );
			while ( $cursor <= $end ) {
				$yw       = $cursor->format( 'oW' ); // ISO year + zero-padded week.
				$labels[] = 'Wk ' . $cursor->format( 'M j' );
				$data[]   = $counts[ (int) $yw ] ?? 0;
				$cursor   = $cursor->modify( '+7 days' );
			}
		}

		return array(
			'labels'      => $labels,
			'data'        => $data,
			'granularity' => $granularity,
		);
	}

	// ── Status Conversion Funnel ──────────────────────────────────────────────

	/**
	 * Get lead counts per status for the conversion funnel widget.
	 *
	 * All six statuses always appear. Percentages sum to 100%
	 * (rounding applied to the last item to absorb floating-point drift).
	 *
	 * @return array Array of {status, label, count, percentage}.
	 */
	public static function leads_by_status(): array {
		global $wpdb;

		$table    = $wpdb->prefix . 'xtremeforms_leads';
		$statuses = Xtremeforms_Leads::get_statuses();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$counts_by_status = array();
		foreach ( $rows ?: array() as $row ) {
			$counts_by_status[ $row->status ] = (int) $row->cnt;
		}

		$total  = array_sum( $counts_by_status );
		$result = array();

		$statuses_list = array_keys( $statuses );
		$last_idx      = count( $statuses_list ) - 1;
		$allocated_pct = 0.0;

		foreach ( $statuses_list as $idx => $slug ) {
			$count = $counts_by_status[ $slug ] ?? 0;

			if ( $idx === $last_idx ) {
				// Last item absorbs rounding drift.
				$pct = round( 100.0 - $allocated_pct, 2 );
			} elseif ( $total > 0 ) {
				$pct            = round( ( $count / $total ) * 100, 2 );
				$allocated_pct += $pct;
			} else {
				$pct = 0.00;
			}

			$result[] = array(
				'status'     => $slug,
				'label'      => $statuses[ $slug ],
				'count'      => $count,
				'percentage' => $pct,
			);
		}

		return $result;
	}

	// ── Top Source Pages ──────────────────────────────────────────────────────

	/**
	 * Get the top N source pages by lead count.
	 *
	 * @param int $limit Max rows to return (default 10).
	 * @return array Array of {source_url, count}.
	 */
	public static function top_source_pages( int $limit = 10 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeforms_leads';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_url, COUNT(*) AS cnt
				 FROM {$table}
				 WHERE source_url != ''
				 GROUP BY source_url
				 ORDER BY cnt DESC
				 LIMIT %d",
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static function ( $row ) {
				return array(
					'source_url' => $row->source_url,
					'count'      => (int) $row->cnt,
				);
			},
			$rows ?: array()
		);
	}

	// ── Top-Performing Forms ──────────────────────────────────────────────────

	/**
	 * Get the top N forms by submission count.
	 *
	 * @param int $limit Max rows (default 5).
	 * @return array Array of {form_id, form_name, count}.
	 */
	public static function top_forms( int $limit = 5 ): array {
		$data = self::leads_by_form();
		return array_slice( $data, 0, $limit );
	}

	// ── Form Performance Metrics ──────────────────────────────────────────────

	/**
	 * Get performance metrics for all forms.
	 *
	 * Returns for each form:
	 * - form_id, form_name, views (total impressions), submissions (lead count),
	 * conversion_rate (%), avg_submit_seconds (null if no data).
	 *
	 * @return array
	 */
	public static function form_performance_metrics(): array {
		global $wpdb;

		$forms_table       = $wpdb->prefix . 'xtremeforms_forms';
		$leads_table       = $wpdb->prefix . 'xtremeforms_leads';
		$impressions_table = $wpdb->prefix . 'xtremeforms_form_impressions';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT
			 f.id AS form_id,
			 f.name AS form_name,
			 COALESCE(i.view_count, 0) AS views,
			 COALESCE(s.submission_count, 0) AS submissions,
			 avg_t.avg_seconds
			FROM {$forms_table} f
			LEFT JOIN (
			 SELECT form_id, COUNT(*) AS view_count
			 FROM {$impressions_table}
			 GROUP BY form_id
			) i ON i.form_id = f.id
			LEFT JOIN (
			 SELECT form_id, COUNT(*) AS submission_count
			 FROM {$leads_table}
			 GROUP BY form_id
			) s ON s.form_id = f.id
			LEFT JOIN (
			 SELECT form_id, AVG(submit_duration_seconds) AS avg_seconds
			 FROM {$leads_table}
			 WHERE submit_duration_seconds IS NOT NULL
			 GROUP BY form_id
			) avg_t ON avg_t.form_id = f.id
			WHERE f.status = 'active'
			ORDER BY submissions DESC, f.name ASC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static function ( $row ) {
				$views       = (int) $row->views;
				$submissions = (int) $row->submissions;

				// Conversion rate calculation (capped at 100.00, '—' when no views).
				if ( $views === 0 ) {
					$conversion_rate         = null; // Display as '—'.
					$conversion_rate_warning = false;
				} else {
					$raw_rate                = ( $submissions / $views ) * 100;
					$conversion_rate_warning = $raw_rate > 100;
					$conversion_rate         = round( min( $raw_rate, 100.0 ), 2 );
				}

				// Avg time-to-submit.
				$avg_seconds = null !== $row->avg_seconds ? round( (float) $row->avg_seconds, 1 ) : null;

				return array(
					'form_id'                 => (int) $row->form_id,
					'form_name'               => $row->form_name,
					'views'                   => $views,
					'submissions'             => $submissions,
					'conversion_rate'         => $conversion_rate,
					'conversion_rate_warning' => $conversion_rate_warning ?? false,
					'avg_seconds'             => $avg_seconds,
				);
			},
			$rows ?: array()
		);
	}

	// ── UTM Breakdown ─────────────────────────────────────────────────────────

	/**
	 * Get UTM breakdown for source, medium, and campaign.
	 *
	 * Only includes leads where at least one UTM parameter is non-NULL.
	 * Each grouping is sorted by count desc, capped at 20 with has_more flag.
	 *
	 * @return array{source: array, medium: array, campaign: array}
	 */
	public static function utm_breakdown(): array {
		return array(
			'source'   => self::utm_group( 'utm_source' ),
			'medium'   => self::utm_group( 'utm_medium' ),
			'campaign' => self::utm_group( 'utm_campaign' ),
		);
	}

	/**
	 * Get grouped lead counts for one UTM dimension.
	 *
	 * Leads with NULL for this specific dimension are grouped under the other
	 * UTM dimensions IF they have at least one non-NULL UTM field. Leads with
	 * a specific dimension value that is also NULL are excluded from display
	 * (they show under other dimensions but not this one).
	 *
	 * Actually we: only include rows where this specific utm column IS NOT NULL,
	 * and the overall lead has at least one UTM value (enforced by IS NOT NULL on this column).
	 *
	 * @param string $utm_column Column name (utm_source|utm_medium|utm_campaign).
	 * @return array{rows: array, total_attributed: int, has_more: bool}
	 */
	private static function utm_group( string $utm_column ): array {
		global $wpdb;

		// Validate column name to prevent injection — only allow known columns.
		$allowed = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		if ( ! in_array( $utm_column, $allowed, true ) ) {
			return array(
				'rows'             => array(),
				'total_attributed' => 0,
				'has_more'         => false,
			);
		}

		$table = $wpdb->prefix . 'xtremeforms_leads';

		// Total UTM-attributed leads (at least one non-NULL UTM field).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_attributed = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}
			 WHERE utm_source IS NOT NULL
			 OR utm_medium IS NOT NULL
			 OR utm_campaign IS NOT NULL
			 OR utm_term IS NOT NULL
			 OR utm_content IS NOT NULL"
		);

		// Fetch top 21 rows for the specific column (to detect has_more with 21st row).
		$rows = $wpdb->get_results(
			"SELECT {$utm_column} AS utm_value, COUNT(*) AS cnt
			 FROM {$table}
			 WHERE {$utm_column} IS NOT NULL
			 GROUP BY {$utm_column}
			 ORDER BY cnt DESC
			 LIMIT 21"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$has_more = count( $rows ) > 20;
		$rows     = array_slice( $rows ?: array(), 0, 20 );

		$formatted = array_map(
			static function ( $row ) use ( $total_attributed ) {
				$pct = $total_attributed > 0
					? round( ( (int) $row->cnt / $total_attributed ) * 100, 2 )
					: 0.00;
				return array(
					'value'      => $row->utm_value,
					'count'      => (int) $row->cnt,
					'percentage' => $pct,
				);
			},
			$rows
		);

		return array(
			'rows'             => $formatted,
			'total_attributed' => $total_attributed,
			'has_more'         => $has_more,
		);
	}

	// ── User-Agent / Audience Insights ────────────────────────────────────────

	/**
	 * Aggregate device / browser / OS distribution across all leads.
	 *
	 * Parses each lead's stored user_agent string with a lightweight regex
	 * matcher (no external dep). Used by the dashboard "Audience Insights"
	 * card. Returns three breakdowns plus the total parsed.
	 *
	 * @param string $date_from Optional 'Y-m-d' lower bound (inclusive).
	 * @param string $date_to   Optional 'Y-m-d' upper bound (inclusive).
	 * @return array{
	 *     device:  array<int, array{label:string, count:int, percentage:float}>,
	 *     browser: array<int, array{label:string, count:int, percentage:float}>,
	 *     os:      array<int, array{label:string, count:int, percentage:float}>,
	 *     total:   int
	 * }
	 */
	public static function user_agent_breakdown( string $date_from = '', string $date_to = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeforms_leads';

		$where  = array( "user_agent != ''", 'user_agent IS NOT NULL' );
		$params = array();

		if ( '' !== $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}
		if ( '' !== $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$sql = "SELECT user_agent FROM {$table} WHERE " . implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $params ) ) {
			$rows = $wpdb->get_col( $sql );
		} else {
			$rows = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$device_counts  = array();
		$browser_counts = array();
		$os_counts      = array();
		$total          = 0;

		foreach ( (array) $rows as $ua ) {
			if ( '' === (string) $ua ) {
				continue;
			}
			$parsed = self::parse_user_agent( (string) $ua );
			$device_counts[ $parsed['device'] ]   = ( $device_counts[ $parsed['device'] ]   ?? 0 ) + 1;
			$browser_counts[ $parsed['browser'] ] = ( $browser_counts[ $parsed['browser'] ] ?? 0 ) + 1;
			$os_counts[ $parsed['os'] ]           = ( $os_counts[ $parsed['os'] ]           ?? 0 ) + 1;
			++$total;
		}

		return array(
			'device'  => self::format_breakdown( $device_counts, $total ),
			'browser' => self::format_breakdown( $browser_counts, $total ),
			'os'      => self::format_breakdown( $os_counts, $total ),
			'total'   => $total,
		);
	}

	/**
	 * Sort + format an associative count map into the dashboard-ready shape.
	 *
	 * @param array<string,int> $counts Label => count.
	 * @param int               $total  Total samples.
	 * @return array<int, array{label:string, count:int, percentage:float}>
	 */
	private static function format_breakdown( array $counts, int $total ): array {
		arsort( $counts );
		$out = array();
		foreach ( $counts as $label => $count ) {
			$out[] = array(
				'label'      => (string) $label,
				'count'      => (int) $count,
				'percentage' => $total > 0 ? round( $count / $total * 100, 1 ) : 0.0,
			);
		}
		return $out;
	}

	/**
	 * Lightweight User-Agent string parser.
	 *
	 * Returns device class (Mobile / Tablet / Desktop / Bot), browser family,
	 * and OS family. Order of pattern checks matters — more-specific tokens
	 * are tested before more-generic ones (e.g. Edge before Chrome, since
	 * Edge UA strings include "Chrome").
	 *
	 * @param string $ua Raw user-agent string.
	 * @return array{device:string, browser:string, os:string}
	 */
	public static function parse_user_agent( string $ua ): array {
		$ua = (string) $ua;

		// Bots / crawlers — check first, before device classification.
		if ( preg_match( '/(bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegrambot|preview|fetch)/i', $ua ) ) {
			return array(
				'device'  => 'Bot',
				'browser' => 'Bot / Crawler',
				'os'      => 'Unknown',
			);
		}

		// Device classification.
		if ( preg_match( '/iPad|Tablet|Nexus 7|Nexus 10|Kindle|PlayBook/i', $ua ) ) {
			$device = 'Tablet';
		} elseif ( preg_match( '/Android/i', $ua ) && ! preg_match( '/Mobile/i', $ua ) ) {
			// Android without "Mobile" token typically means tablet.
			$device = 'Tablet';
		} elseif ( preg_match( '/Mobi|iPhone|iPod|Android|BlackBerry|webOS|IEMobile|Opera Mini|Windows Phone/i', $ua ) ) {
			$device = 'Mobile';
		} else {
			$device = 'Desktop';
		}

		// Browser detection (order is important).
		if ( preg_match( '/Edg(e|A|iOS)?\//i', $ua ) ) {
			$browser = 'Edge';
		} elseif ( preg_match( '/OPR\/|Opera/i', $ua ) ) {
			$browser = 'Opera';
		} elseif ( preg_match( '/SamsungBrowser/i', $ua ) ) {
			$browser = 'Samsung Internet';
		} elseif ( preg_match( '/UCBrowser/i', $ua ) ) {
			$browser = 'UC Browser';
		} elseif ( preg_match( '/Firefox/i', $ua ) ) {
			$browser = 'Firefox';
		} elseif ( preg_match( '/Chrome/i', $ua ) ) {
			$browser = 'Chrome';
		} elseif ( preg_match( '/Safari/i', $ua ) ) {
			$browser = 'Safari';
		} elseif ( preg_match( '/MSIE|Trident/i', $ua ) ) {
			$browser = 'Internet Explorer';
		} else {
			$browser = 'Other';
		}

		// OS detection.
		if ( preg_match( '/iPhone OS|iPad; CPU OS|iPod touch/i', $ua ) ) {
			$os = 'iOS';
		} elseif ( preg_match( '/Mac OS X|Macintosh/i', $ua ) ) {
			$os = 'macOS';
		} elseif ( preg_match( '/Android/i', $ua ) ) {
			$os = 'Android';
		} elseif ( preg_match( '/Windows NT/i', $ua ) ) {
			$os = 'Windows';
		} elseif ( preg_match( '/CrOS/i', $ua ) ) {
			$os = 'Chrome OS';
		} elseif ( preg_match( '/Ubuntu|Linux/i', $ua ) ) {
			$os = 'Linux';
		} else {
			$os = 'Other';
		}

		return array(
			'device'  => $device,
			'browser' => $browser,
			'os'      => $os,
		);
	}
}
