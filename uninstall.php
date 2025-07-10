<?php
/**
 * XtremeLeads Uninstall
 *
 * Runs only when the plugin is deleted via WordPress admin.
 * Drops all custom tables and removes all plugin options.
 *
 * @package XtremeLeads
 */

// If uninstall not called from WordPress, exit.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'xtremeleads_leads`' );
$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'xtremeleads_forms`' );
// phpcs:enable

// Delete plugin options.
delete_option( 'xtremeleads_settings' );
delete_option( 'xtremeleads_db_version' );

// Clear any cached data.
wp_cache_flush();
