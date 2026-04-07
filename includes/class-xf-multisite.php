<?php
/**
 * WordPress Multisite / Network support for Xtreme Forms.
 *
 * Responsibilities:
 * - Register network admin menu and aggregated leads dashboard.
 * - Handle "Push Settings to All Sites" action.
 * - Expose helper methods for cross-site data aggregation.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Multisite
 */
class XF_Multisite {

	/**
	 * Network-level option key for global settings that are pushed to subsites.
	 */
	const NETWORK_SETTINGS_KEY = 'xtremeforms_network_settings';

	/**
	 * Per-site option key: when set to '1', the XL plugin menu is hidden for this subsite.
	 * This implements the per-site deactivation requirement for network-activated plugins.
	 */
	const SITE_DISABLED_KEY = 'xtremeforms_site_disabled';

	/**
	 * Initialize network admin hooks.
	 */
	public static function init(): void {
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'network_admin_menu', array( static::class, 'register_network_menu' ) );
		add_action( 'network_admin_notices', array( static::class, 'display_network_notices' ) );
		add_action( 'admin_post_xl_network_push_settings', array( static::class, 'handle_push_settings' ) );
		// Per-site opt-out toggle (available to site admins).
		add_action( 'admin_post_xl_toggle_site_disabled', array( static::class, 'handle_toggle_site_disabled' ) );
	}

	/**
	 * Check whether Xtreme Forms is disabled for the current (or specified) subsite.
	 *
	 * @param int $blog_id Blog ID (0 = current blog).
	 * @return bool True if disabled/opted-out.
	 */
	public static function is_site_disabled( int $blog_id = 0 ): bool {
		if ( $blog_id && $blog_id !== get_current_blog_id() ) {
			return '1' === get_blog_option( $blog_id, self::SITE_DISABLED_KEY, '0' );
		}
		return '1' === get_option( self::SITE_DISABLED_KEY, '0' );
	}

	/**
	 * Handle the toggle-disable admin post action (site admin only).
	 * Allows a site admin to hide/show Xtreme Forms for their subsite.
	 */
	public static function handle_toggle_site_disabled(): void {
		check_admin_referer( 'xf_toggle_site_disabled' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		$current = get_option( self::SITE_DISABLED_KEY, '0' );
		$new_val = ( '1' === $current ) ? '0' : '1';
		update_option( self::SITE_DISABLED_KEY, $new_val );

		wp_safe_redirect(
			add_query_arg(
				array(
					'xf_site_toggled'  => '1',
					'xf_site_disabled' => $new_val,
				),
				admin_url( 'admin.php?page=xtremeleads-settings' )
			)
		);
		exit;
	}

	/**
	 * Register the Network Admin menu item.
	 */
	public static function register_network_menu(): void {
		add_menu_page(
			__( 'Xtreme Forms Network', 'xtreme-forms' ),
			__( 'Xtreme Forms', 'xtreme-forms' ),
			'manage_network',
			'xtremeleads-network',
			array( static::class, 'page_network_dashboard' ),
			'dashicons-email-alt',
			25
		);

		add_submenu_page(
			'xtremeleads-network',
			__( 'Network Dashboard', 'xtreme-forms' ),
			__( 'Dashboard', 'xtreme-forms' ),
			'manage_network',
			'xtremeleads-network',
			array( static::class, 'page_network_dashboard' )
		);

		add_submenu_page(
			'xtremeleads-network',
			__( 'Global Settings', 'xtreme-forms' ),
			__( 'Global Settings', 'xtreme-forms' ),
			'manage_network',
			'xtremeleads-network-settings',
			array( static::class, 'page_network_settings' )
		);
	}

	/**
	 * Render the network aggregated dashboard.
	 */
	public static function page_network_dashboard(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-network-dashboard.php';
	}

	/**
	 * Render the network global settings page.
	 */
	public static function page_network_settings(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}
		require_once XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-network-settings.php';
	}

	/**
	 * Display network-level admin notices.
	 */
	public static function display_network_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'xtremeleads-network' ) ) {
			return;
		}

		if ( ! empty( $_GET['xf_push_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$success = absint( $_GET['xf_push_success'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			$failed  = absint( $_GET['xf_push_failed'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: 1: number of sites updated, 2: number of sites failed */
				esc_html__( 'Settings pushed. %1$d site(s) updated successfully. %2$d site(s) failed.', 'xtreme-forms' ),
				(int) $success,
				(int) $failed
			);
			echo '</p>';

			if ( $failed > 0 && ! empty( $_GET['xf_push_failed_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$failed_ids = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['xf_push_failed_ids'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
				echo '<p>' . esc_html__( 'Failed site IDs:', 'xtreme-forms' ) . ' ';
				echo esc_html( implode( ', ', $failed_ids ) );
				echo '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Handle the "Push Settings to All Sites" admin post action.
	 */
	public static function handle_push_settings(): void {
		check_admin_referer( 'xf_network_push_settings' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'xtreme-forms' ) );
		}

		// Save network-level settings.
		$email_template     = sanitize_textarea_field( wp_unslash( $_POST['xf_email_template_body'] ?? '' ) );
		$email_header_color = sanitize_hex_color( wp_unslash( $_POST['xf_email_header_color'] ?? '#1A73E8' ) );
		$retention_days     = isset( $_POST['xf_retention_days'] ) && '' !== $_POST['xf_retention_days']
			? absint( $_POST['xf_retention_days'] )
			: '';
		$anonymize_ip       = isset( $_POST['xf_anonymize_ip'] ) ? '1' : '0';

		$network_settings = array(
			'email_template_body' => $email_template,
			'email_header_color'  => $email_header_color ?: '#1A73E8',
			'retention_days'      => $retention_days,
			'anonymize_ip'        => $anonymize_ip,
		);
		update_site_option( self::NETWORK_SETTINGS_KEY, $network_settings );

		// Push to all subsites.
		$blog_ids   = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		$success    = 0;
		$failed     = 0;
		$failed_ids = array();

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );

			// Merge network settings into per-site settings.
			$site_settings = get_option( 'xtremeforms_settings', array() );

			$site_settings['anonymize_ip']   = $network_settings['anonymize_ip'];
			$site_settings['retention_days'] = $network_settings['retention_days'];

			// Email template settings.
			$email_tmpl                 = get_option( 'xtremeforms_email_template', array() );
			$email_tmpl['header_color'] = $network_settings['email_header_color'];
			if ( ! empty( $network_settings['email_template_body'] ) ) {
				$email_tmpl['body'] = $network_settings['email_template_body'];
			}

			$settings_updated = update_option( 'xtremeforms_settings', $site_settings );
			$template_updated = update_option( 'xtremeforms_email_template', $email_tmpl );

			// Treat as success even if values were already identical (update_option returns false when unchanged).
			$result = ( false !== $settings_updated || $site_settings === get_option( 'xtremeforms_settings' ) )
				&& ( false !== $template_updated || $email_tmpl === get_option( 'xtremeforms_email_template' ) );

			if ( $result ) {
				++$success;
			} else {
				++$failed;
				$failed_ids[] = (int) $blog_id;
			}

			restore_current_blog();
		}

		// Audit log on current (main) site.
		if ( class_exists( 'XF_Audit_Log' ) ) {
			XF_Audit_Log::record(
				XF_Audit_Log::ACTION_GLOBAL_SETTINGS_UPDATED,
				0,
				array(
					'scope'   => 'network_push',
					'success' => $success,
					'failed'  => $failed,
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'xtremeleads-network-settings',
					'xf_push_done'       => '1',
					'xf_push_success'    => $success,
					'xf_push_failed'     => $failed,
					'xf_push_failed_ids' => implode( ',', $failed_ids ),
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get aggregated lead data across all active subsites.
	 *
	 * @return array {
	 * total_leads: int,
	 * by_site: array[ blog_id => { blog_name, blog_url, count } ],
	 * top_forms: array[ { form_name, blog_name, count } ],
	 * }
	 */
	public static function get_aggregated_data(): array {
		if ( ! is_multisite() ) {
			return array(
				'total_leads' => 0,
				'by_site'     => array(),
				'top_forms'   => array(),
			);
		}

		$plugin_basename = plugin_basename( XTREMEFORMS_PLUGIN_FILE );
		$blog_ids        = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		$total_leads = 0;
		$by_site     = array();
		$top_forms   = array();

		foreach ( $blog_ids as $blog_id ) {
			$blog_id = (int) $blog_id;

			// Skip sites where plugin is not individually active (only if not network-activated).
			$is_network_active = is_plugin_active_for_network( $plugin_basename );
			if ( ! $is_network_active ) {
				// phpcs:ignore WordPress.WP.DiscouragedFunctions
				$active_plugins = get_blog_option( $blog_id, 'active_plugins', array() );
				if ( ! in_array( $plugin_basename, $active_plugins, true ) ) {
					continue;
				}
			}

			// Skip sites that have opted out via the per-site disable mechanism.
			if ( self::is_site_disabled( $blog_id ) ) {
				continue;
			}

			switch_to_blog( $blog_id );

			global $wpdb;
			$leads_table = $wpdb->prefix . 'xtremeforms_leads';
			$forms_table = $wpdb->prefix . 'xtremeforms_forms';

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$site_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$leads_table}"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$blog_name = get_bloginfo( 'name' );
			$blog_url  = get_bloginfo( 'url' );

			$by_site[ $blog_id ] = array(
				'blog_name' => $blog_name,
				'blog_url'  => $blog_url,
				'count'     => $site_count,
			);
			$total_leads        += $site_count;

			// Top forms for this site (top 5).
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$site_forms = $wpdb->get_results(
				"SELECT f.name AS form_name, COUNT(l.id) AS lead_count
				FROM {$leads_table} l
				LEFT JOIN {$forms_table} f ON f.id = l.form_id
				GROUP BY l.form_id
				ORDER BY lead_count DESC
				LIMIT 5"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( (array) $site_forms as $row ) {
				$top_forms[] = array(
					'form_name' => $row->form_name ?: __( '(Deleted Form)', 'xtreme-forms' ),
					'blog_name' => $blog_name,
					'count'     => (int) $row->lead_count,
				);
			}

			restore_current_blog();
		}

		// Sort top forms globally by count desc, take top 10.
		usort( $top_forms, static fn( $a, $b ) => $b['count'] - $a['count'] );
		$top_forms = array_slice( $top_forms, 0, 10 );

		return array(
			'total_leads' => $total_leads,
			'by_site'     => $by_site,
			'top_forms'   => $top_forms,
		);
	}
}
