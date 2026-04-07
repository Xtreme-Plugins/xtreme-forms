<?php
/**
 * Handles plugin activation, deactivation, and database table creation.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Activator
 */
class XF_Activator {

	/**
	 * Run on plugin activation: create DB tables and set default options.
	 *
	 * On WordPress Multisite network-wide activation, this iterates all blogs and
	 * creates tables for each. On single-site activation (or per-site in multisite),
	 * it operates on the current blog only.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			// Create tables for every existing blog in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				self::create_tables();
				self::set_default_options();
				restore_current_blog();
			}
		} else {
			self::create_tables();
			self::set_default_options();
		}
		flush_rewrite_rules();
	}

	/**
	 * Called when a new blog is created in the network — provision its tables.
	 *
	 * @param int|WP_Site $blog Blog ID or WP_Site object.
	 */
	public static function on_new_blog( $blog ): void {
		$blog_id = is_object( $blog ) ? (int) $blog->blog_id : (int) $blog;
		if ( is_plugin_active_for_network( plugin_basename( XTREMEFORMS_PLUGIN_FILE ) ) ) {
			switch_to_blog( $blog_id );
			self::create_tables();
			self::set_default_options();
			restore_current_blog();
		}
	}

	/**
	 * Run on plugins_loaded to apply any DB schema upgrades without re-activation.
	 * Safe to call on every request (uses dbDelta idempotency).
	 */
	public static function maybe_upgrade(): void {
		$current_db_version = get_option( 'xtremeforms_db_version', '' );

		// If DB version doesn't match plugin version, run create_tables() to apply any new tables.
		if ( version_compare( $current_db_version, XTREMEFORMS_VERSION, '<' ) ) {
			self::create_tables();
			self::set_default_options();
		}
	}

	/**
	 * Run on plugin deactivation: flush rewrites only — DO NOT drop tables.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Create (or upgrade) all required database tables using dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// ── Forms table ────────────────────────────────────────────────────
		$forms_table = $wpdb->prefix . 'xtremeforms_forms';
		$sql_forms = "CREATE TABLE {$forms_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 name varchar(255) NOT NULL DEFAULT '',
 fields longtext NOT NULL,
 settings longtext NOT NULL,
 status varchar(20) NOT NULL DEFAULT 'active',
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY status (status)
) {$charset_collate};";

		// ── Leads table ─────────────────────────────────────────────────────
		$leads_table = $wpdb->prefix . 'xtremeforms_leads';
		$sql_leads = "CREATE TABLE {$leads_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 form_id bigint(20) unsigned NOT NULL DEFAULT 0,
 status varchar(50) NOT NULL DEFAULT 'new',
 source_url text NOT NULL,
 ip_address varchar(100) NOT NULL DEFAULT '',
 user_agent text NOT NULL,
 field_values longtext NOT NULL,
 assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
 utm_source varchar(255) DEFAULT NULL,
 utm_medium varchar(255) DEFAULT NULL,
 utm_campaign varchar(255) DEFAULT NULL,
 utm_term varchar(255) DEFAULT NULL,
 utm_content varchar(255) DEFAULT NULL,
 email_address varchar(255) DEFAULT NULL,
 is_duplicate tinyint(1) NOT NULL DEFAULT 0,
 duplicate_status varchar(50) NOT NULL DEFAULT '',
 original_lead_id bigint(20) unsigned DEFAULT NULL,
 submit_duration_seconds int(10) unsigned DEFAULT NULL,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY form_id (form_id),
 KEY status (status),
 KEY assigned_to (assigned_to),
 KEY created_at (created_at),
 KEY is_duplicate (is_duplicate),
 KEY original_lead_id (original_lead_id),
 KEY email_address (email_address)
) {$charset_collate};";

		// ── Notes table ──────────────────────────────────────────────────────
		$notes_table = $wpdb->prefix . 'xtremeforms_notes';
		$sql_notes = "CREATE TABLE {$notes_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
 author_id bigint(20) unsigned NOT NULL DEFAULT 0,
 note_content text NOT NULL,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY lead_id (lead_id),
 KEY created_at (created_at)
) {$charset_collate};";

		// ── Activity table ───────────────────────────────────────────────────
		$activity_table = $wpdb->prefix . 'xtremeforms_activity';
		$sql_activity = "CREATE TABLE {$activity_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
 user_id bigint(20) unsigned NOT NULL DEFAULT 0,
 action_type varchar(50) NOT NULL DEFAULT '',
 action_data longtext NOT NULL,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY lead_id (lead_id),
 KEY created_at (created_at)
) {$charset_collate};";

		// ── Tags table ───────────────────────────────────────────────────────
		$tags_table = $wpdb->prefix . 'xtremeforms_tags';
		$sql_tags = "CREATE TABLE {$tags_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 name varchar(100) NOT NULL DEFAULT '',
 slug varchar(100) NOT NULL DEFAULT '',
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 UNIQUE KEY slug (slug)
) {$charset_collate};";

		// ── Lead Tags pivot table ────────────────────────────────────────────
		$lead_tags_table = $wpdb->prefix . 'xtremeforms_lead_tags';
		$sql_lead_tags = "CREATE TABLE {$lead_tags_table} (
 lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
 tag_id bigint(20) unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY (lead_id,tag_id),
 KEY tag_id (tag_id)
) {$charset_collate};";

		// ── Email Log table ────────────────────────────────────────
		$email_log_table = $wpdb->prefix . 'xtremeforms_email_log';
		$sql_email_log = "CREATE TABLE {$email_log_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
 recipient varchar(255) NOT NULL DEFAULT '',
 subject varchar(500) NOT NULL DEFAULT '',
 body longtext NOT NULL,
 headers text NOT NULL,
 trigger_type varchar(50) NOT NULL DEFAULT '',
 status varchar(20) NOT NULL DEFAULT 'sent',
 failure_reason text NOT NULL,
 sent_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY lead_id (lead_id),
 KEY trigger_type (trigger_type),
 KEY status (status),
 KEY sent_at (sent_at)
) {$charset_collate};";

		// ── Routing Rules table ────────────────────────────────────
		$routing_rules_table = $wpdb->prefix . 'xtremeforms_routing_rules';
		$sql_routing_rules = "CREATE TABLE {$routing_rules_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 rule_order int(11) NOT NULL DEFAULT 0,
 condition_type varchar(50) NOT NULL DEFAULT 'form',
 form_id bigint(20) unsigned NOT NULL DEFAULT 0,
 field_id varchar(100) NOT NULL DEFAULT '',
 field_value varchar(500) NOT NULL DEFAULT '',
 recipient_email varchar(255) NOT NULL DEFAULT '',
 is_active tinyint(1) NOT NULL DEFAULT 1,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY rule_order (rule_order),
 KEY is_active (is_active)
) {$charset_collate};";

		// ── Form Impressions table ────────────────────────────────
		$impressions_table = $wpdb->prefix . 'xtremeforms_form_impressions';
		$sql_impressions = "CREATE TABLE {$impressions_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 form_id bigint(20) unsigned NOT NULL DEFAULT 0,
 post_id bigint(20) unsigned NOT NULL DEFAULT 0,
 session_hash varchar(64) NOT NULL DEFAULT '',
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY form_id (form_id),
 KEY post_id (post_id),
 KEY created_at (created_at)
) {$charset_collate};";

		// ── Webhooks table ─────────────────────────────────────────
		$webhooks_table = $wpdb->prefix . 'xtremeforms_webhooks';
		$sql_webhooks = "CREATE TABLE {$webhooks_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 name varchar(255) NOT NULL DEFAULT '',
 url text NOT NULL,
 custom_headers longtext NOT NULL,
 trigger_events longtext NOT NULL,
 form_ids longtext NOT NULL,
 is_active tinyint(1) NOT NULL DEFAULT 1,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY is_active (is_active)
) {$charset_collate};";

		// ── Webhook Delivery Log table ─────────────────────────────
		$webhook_log_table = $wpdb->prefix . 'xtremeforms_webhook_log';
		$sql_webhook_log = "CREATE TABLE {$webhook_log_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 webhook_id bigint(20) unsigned NOT NULL DEFAULT 0,
 lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
 trigger_type varchar(50) NOT NULL DEFAULT '',
 url text NOT NULL,
 status varchar(20) NOT NULL DEFAULT 'sent',
 http_code smallint(5) NOT NULL DEFAULT 0,
 response_body text NOT NULL,
 is_retry tinyint(1) NOT NULL DEFAULT 0,
 original_attempt_id bigint(20) unsigned DEFAULT NULL,
 delivered_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY webhook_id (webhook_id),
 KEY lead_id (lead_id),
 KEY status (status),
 KEY delivered_at (delivered_at)
) {$charset_collate};";

		// ── Spam Log table ─────────────────────────────────────────
		$spam_log_table = $wpdb->prefix . 'xtremeforms_spam_log';
		$sql_spam_log = "CREATE TABLE {$spam_log_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 form_id bigint(20) unsigned NOT NULL DEFAULT 0,
 rejection_reason varchar(50) NOT NULL DEFAULT '',
 submitted_email varchar(255) NOT NULL DEFAULT '',
 source_url text NOT NULL,
 user_agent text NOT NULL,
 ip_address varchar(100) NOT NULL DEFAULT '',
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY form_id (form_id),
 KEY rejection_reason (rejection_reason),
 KEY created_at (created_at)
) {$charset_collate};";

		// ── Audit Log table ────────────────────────────────────────
		$audit_log_table = $wpdb->prefix . 'xtremeforms_audit_log';
		$sql_audit_log = "CREATE TABLE {$audit_log_table} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 user_id bigint(20) unsigned NOT NULL DEFAULT 0,
 user_display varchar(255) NOT NULL DEFAULT '',
 action_type varchar(100) NOT NULL DEFAULT '',
 record_id bigint(20) unsigned NOT NULL DEFAULT 0,
 context longtext NOT NULL,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (id),
 KEY action_type (action_type),
 KEY record_id (record_id),
 KEY user_id (user_id),
 KEY created_at (created_at)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_forms );
		dbDelta( $sql_leads );
		dbDelta( $sql_notes );
		dbDelta( $sql_activity );
		dbDelta( $sql_tags );
		dbDelta( $sql_lead_tags );
		dbDelta( $sql_email_log );
		dbDelta( $sql_routing_rules );
		dbDelta( $sql_impressions );
		dbDelta( $sql_webhooks );
		dbDelta( $sql_webhook_log );
		dbDelta( $sql_spam_log );
		dbDelta( $sql_audit_log );

		// Add scheduling columns to forms table if they don't exist.
		$forms_table_check = $wpdb->prefix . 'xtremeforms_forms';
		foreach ( array( 'activate_at', 'expire_at' ) as $col_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$col_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
					DB_NAME,
					$forms_table_check,
					$col_name
				)
			);
			if ( empty( $col_exists ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query( "ALTER TABLE {$forms_table_check} ADD COLUMN {$col_name} datetime DEFAULT NULL" );
			}
		}

		// Add context (for per-blog scheduling) column to forms table if needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$closed_col_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'closed_message'",
				DB_NAME,
				$forms_table_check
			)
		);
		if ( empty( $closed_col_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$forms_table_check} ADD COLUMN closed_message text DEFAULT NULL" );
		}

		// Add consent_given column to leads table if it doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'consent_given'",
				DB_NAME,
				$wpdb->prefix . 'xtremeforms_leads'
			)
		);
		if ( empty( $col_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}xtremeforms_leads ADD COLUMN consent_given tinyint(1) NOT NULL DEFAULT 0" );
		}

		update_option( 'xtremeforms_db_version', XTREMEFORMS_VERSION );
	}

	/**
	 * Set default plugin options if they don't exist yet.
	 */
	private static function set_default_options(): void {
		$defaults = array(
			'recipients' => get_option( 'admin_email', '' ),
			'anonymize_ip' => '0',
			'email_from_name' => get_bloginfo( 'name' ),
			'email_from' => get_option( 'admin_email', '' ),
		);

		if ( false === get_option( 'xtremeforms_settings' ) ) {
			update_option( 'xtremeforms_settings', $defaults );
		}
	}
}
