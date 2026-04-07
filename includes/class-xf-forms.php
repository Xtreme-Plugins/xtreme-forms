<?php
/**
 * Form CRUD operations.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XF_Forms
 */
class XF_Forms {

	/**
	 * Get all forms ordered by name.
	 *
	 * @return array<object> Array of form objects.
	 */
	public static function get_all_forms(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_forms';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT id, name, status, created_at FROM {$table} ORDER BY name ASC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results ?: array();
	}

	/**
	 * Get a single form by ID.
	 *
	 * @param int $form_id Form ID.
	 * @return object|null Form object or null.
	 */
	public static function get_form( int $form_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_forms';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$form_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Create a new form.
	 *
	 * @param string $name Form name.
	 * @param array  $fields Field definitions.
	 * @param array  $settings Form settings.
	 * @return int|false New form ID or false on failure.
	 */
	public static function create_form( string $name, array $fields = array(), array $settings = array() ): int|false {
		global $wpdb;

		$now   = current_time( 'mysql', true );
		$table = $wpdb->prefix . 'xtremeforms_forms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'name'       => sanitize_text_field( $name ),
				'fields'     => wp_json_encode( $fields ),
				'settings'   => wp_json_encode( $settings ),
				'status'     => 'active',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing form.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $name Form name.
	 * @param array  $fields Field definitions.
	 * @param array  $settings Form settings.
	 * @return bool True on success.
	 */
	public static function update_form( int $form_id, string $name, array $fields, array $settings ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_forms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			$table,
			array(
				'name'       => sanitize_text_field( $name ),
				'fields'     => wp_json_encode( $fields ),
				'settings'   => wp_json_encode( $settings ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $form_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete a form by ID.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function delete_form( int $form_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeforms_forms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $table, array( 'id' => $form_id ), array( '%d' ) );
	}

	/**
	 * Decode a form's fields JSON into an array.
	 *
	 * @param object $form Form row object.
	 * @return array
	 */
	public static function decode_fields( object $form ): array {
		if ( empty( $form->fields ) ) {
			return array();
		}
		$fields = json_decode( $form->fields, true );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Decode a form's settings JSON into an array.
	 *
	 * @param object $form Form row object.
	 * @return array
	 */
	public static function decode_settings( object $form ): array {
		if ( empty( $form->settings ) ) {
			return array();
		}
		$settings = json_decode( $form->settings, true );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Get forms by IDs (for inbox performance).
	 *
	 * @param array $ids Array of form IDs.
	 * @return array Keyed by form ID.
	 */
	public static function get_forms_by_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;

		$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$table = $wpdb->prefix . 'xtremeforms_forms';

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, fields FROM {$table} WHERE id IN ({$placeholders})",
				...$ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$keyed = array();
		foreach ( $results as $row ) {
			$keyed[ (int) $row->id ] = $row;
		}
		return $keyed;
	}

	/**
	 * Validate field data before save.
	 *
	 * @param array $field Field config array.
	 * @return string|null Error message or null if valid.
	 */
	public static function validate_field( array $field ): ?string {
		$requires_options = array( 'dropdown', 'checkbox', 'radio' );
		$type             = $field['type'] ?? '';

		if ( in_array( $type, $requires_options, true ) ) {
			$options = $field['options'] ?? array();
			$options = array_filter( array_map( 'trim', (array) $options ) );
			if ( empty( $options ) ) {
				return sprintf(
					/* translators: %s: field type */
					__( 'The "%s" field type requires at least one option.', 'xtreme-forms' ),
					ucfirst( $type )
				);
			}
		}

		return null;
	}
}
