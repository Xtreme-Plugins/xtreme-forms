<?php
/**
 * Lead notes CRUD operations.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Notes
 */
class XL_Notes {

	/**
	 * Insert a new note for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $author_id WordPress user ID of the note author.
	 * @param string $content Plain-text note content.
	 * @return int|WP_Error New note ID on success, WP_Error on failure.
	 */
	public static function insert_note( int $lead_id, int $author_id, string $content ): int|WP_Error {
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'empty_note', __( 'Note content cannot be empty.', 'xtremeleads' ) );
		}

		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_notes';
		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'lead_id' => absint( $lead_id ),
				'author_id' => absint( $author_id ),
				'note_content' => sanitize_textarea_field( $content ),
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to save note.', 'xtremeleads' ) );
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

		$table = $wpdb->prefix . 'xtremeleads_notes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$notes = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at ASC, id ASC",
				$lead_id
			)
		);

		if ( empty( $notes ) ) {
			return array();
		}

		// Enrich with author display name.
		foreach ( $notes as $note ) {
			$author = get_userdata( (int) $note->author_id );
			$note->author_name = $author ? $author->display_name : __( 'Unknown', 'xtremeleads' );
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

		$table = $wpdb->prefix . 'xtremeleads_notes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$note_id
			)
		);
	}
}
