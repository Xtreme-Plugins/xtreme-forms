<?php
/**
 * Lead notes CRUD operations.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xtremeforms_Notes
 */
class Xtremeforms_Notes {

	/**
	 * Insert a new note for a lead.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param int    $author_id WordPress user ID of the note author.
	 * @param string $content Plain-text note content.
	 * @return int|WP_Error New note ID on success, WP_Error on failure.
	 */
	public static function insert_note( int $lead_id, int $author_id, string $content ): int|WP_Error {
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'empty_note', __( 'Note content cannot be empty.', 'xtreme-forms' ) );
		}

		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_notes';
		$now   = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$inserted = $wpdb->insert(
			$table,
			array(
				'lead_id'      => absint( $lead_id ),
				'author_id'    => absint( $author_id ),
				'note_content' => sanitize_textarea_field( $content ),
				'created_at'   => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to save note.', 'xtreme-forms' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all notes for a lead, ordered oldest first (ascending).
	 *
	 * @param int $lead_id Lead ID.
	 * @return array Array of note objects with author_name appended.
	 */
	public static function get_notes_for_lead( int $lead_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_notes';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at ASC, id ASC",
				$lead_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $notes ) ) {
			return array();
		}

		// Enrich with author display name.
		foreach ( $notes as $note ) {
			$author            = get_userdata( (int) $note->author_id );
			$note->author_name = $author ? $author->display_name : __( 'Unknown', 'xtreme-forms' );
		}

		return $notes;
	}

	/**
	 * Get a single note by ID.
	 *
	 * @param int $note_id Note ID.
	 * @return object|null
	 */
	public static function get_note( int $note_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_notes';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$note_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}
}
