<?php
/**
 * Xtreme Forms Uninstall
 *
 * Runs only when the plugin is deleted via WordPress admin.
 * Drops all custom tables and removes all plugin options.
 *
 * @package Xtreme Forms
 */

// If uninstall not called from WordPress, exit.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'xtremeforms_leads`' );
$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'xtremeforms_forms`' );
// phpcs:enable

// Delete plugin options.
delete_option( 'xtremeforms_settings' );
delete_option( 'xtremeforms_db_version' );

// Clear any cached data.
wp_cache_flush();
