<?php
/**
 * Tag management for leads.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Tags
 */
class XL_Tags {

	/**
	 * Create a new tag.
	 *
	 * @param string $name Tag name.
	 * @return int|WP_Error New tag ID or WP_Error.
	 */
	public static function create_tag( string $name ): int|WP_Error {
		$name = trim( $name );

		if ( '' === $name ) {
			return new WP_Error( 'empty_name', __( 'Tag name cannot be empty.', 'xtremeleads' ) );
		}

		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			return new WP_Error( 'invalid_name', __( 'Tag name is invalid.', 'xtremeleads' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_tags';

		// Check for duplicate slug.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
				$slug
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'duplicate_tag',
				/* translators: %s: tag name */
				sprintf( __( 'A tag named "%s" already exists.', 'xtremeleads' ), sanitize_text_field( $name ) )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'name' => sanitize_text_field( $name ),
				'slug' => $slug,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create tag.', 'xtremeleads' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all tags.
	 *
	 * @return array
	 */
	public static function get_all_tags(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tags = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$table} ORDER BY name ASC"
		);

		return $tags ?: array();
	}

	/**
	 * Get a single tag by ID.
	 *
	 * @param int $tag_id Tag ID.
	 * @return object|null
	 */
	public static function get_tag( int $tag_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$tag_id
			)
		);
	}

	/**
	 * Delete a tag and remove all its lead associations.
	 *
	 * @param int $tag_id Tag ID.
	 * @return bool
	 */
	public static function delete_tag( int $tag_id ): bool {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'xtremeleads_tags';
		$lead_tags_table = $wpdb->prefix . 'xtremeleads_lead_tags';

		// Remove all lead associations first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$lead_tags_table,
			array( 'tag_id' => absint( $tag_id ) ),
			array( '%d' )
		);

		// Delete the tag itself.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$tags_table,
			array( 'id' => absint( $tag_id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Apply a tag to a lead. Silently succeeds if already applied.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $tag_id Tag ID.
	 * @return bool
	 */
	public static function apply_tag_to_lead( int $lead_id, int $tag_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_lead_tags';

		// Use REPLACE INTO or INSERT IGNORE to handle duplicates.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT IGNORE INTO {$table} (lead_id, tag_id) VALUES (%d, %d)",
				$lead_id,
				$tag_id
			)
		);

		return false !== $result;
	}

	/**
	 * Remove a tag from a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $tag_id Tag ID.
	 * @return bool
	 */
	public static function remove_tag_from_lead( int $lead_id, int $tag_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_lead_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$table,
			array(
				'lead_id' => absint( $lead_id ),
				'tag_id' => absint( $tag_id ),
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all tags for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array Array of tag objects.
	 */
	public static function get_tags_for_lead( int $lead_id ): array {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'xtremeleads_tags';
		$lead_tags_table = $wpdb->prefix . 'xtremeleads_lead_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tags = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT t.* FROM {$tags_table} t
				 INNER JOIN {$lead_tags_table} lt ON lt.tag_id = t.id
				 WHERE lt.lead_id = %d
				 ORDER BY t.name ASC",
				$lead_id
			)
		);

		return $tags ?: array();
	}

	/**
	 * Search tags by name (for autocomplete).
	 *
	 * @param string $query Partial tag name.
	 * @param int $limit Max results.
	 * @return array
	 */
	public static function search_tags( string $query, int $limit = 10 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_tags';

		$like = '%' . $wpdb->esc_like( sanitize_text_field( $query ) ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tags = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, name FROM {$table} WHERE name LIKE %s ORDER BY name ASC LIMIT %d",
				$like,
				$limit
			)
		);

		return $tags ?: array();
	}

	/**
	 * Get tag IDs for multiple leads at once (for inbox display).
	 *
	 * @param array $lead_ids Array of lead IDs.
	 * @return array Keyed by lead_id, values are arrays of tag objects.
	 */
	public static function get_tags_for_leads( array $lead_ids ): array {
		if ( empty( $lead_ids ) ) {
			return array();
		}

		global $wpdb;

		$lead_ids = array_values( array_filter( array_map( 'absint', $lead_ids ) ) );
		$tags_table = $wpdb->prefix . 'xtremeleads_tags';
		$lead_tags_table = $wpdb->prefix . 'xtremeleads_lead_tags';
		$placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT lt.lead_id, t.id, t.name FROM {$tags_table} t
				 INNER JOIN {$lead_tags_table} lt ON lt.tag_id = t.id
				 WHERE lt.lead_id IN ({$placeholders})
				 ORDER BY t.name ASC",
				...$lead_ids
			)
		);

		$result = array();
		foreach ( $rows as $row ) {
			$result[ (int) $row->lead_id ][] = (object) array(
				'id' => (int) $row->id,
				'name' => $row->name,
			);
		}

		return $result;
	}
}
