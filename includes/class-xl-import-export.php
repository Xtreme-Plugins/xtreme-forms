<?php
/**
 * Plugin Import / Export — full JSON round-trip for settings & form definitions.
 *
 * Export format:
 * {
 * "xl_export_version": "1",
 * "plugin_version": "x.y.z",
 * "exported_at": "<ISO-8601>",
 * "type": "full"|"form",
 * "settings": { ... }, // present in "full" only
 * "forms": [ { ... }, ... ] // present in "full" (all forms) or "form" (single form)
 * }
 *
 * Lead data is intentionally NOT included in any export.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XL_Import_Export
 */
class XL_Import_Export {

	const EXPORT_VERSION = '1';
	const MIN_EXPORT_VERSION = '1'; // Minimum compatible version for imports.

	// ── Export ────────────────────────────────────────────────────────────────

	/**
	 * Build the full export payload (settings + all forms, no lead data).
	 *
	 * @return array Export data array (not yet JSON-encoded).
	 */
	public static function build_full_export(): array {
		$settings = get_option( 'xtremeleads_settings', array() );
		$email_tmpl = get_option( 'xtremeleads_email_template', array() );
		$routing_rules = self::get_routing_rules_export();

		// Strip sensitive keys.
		unset( $settings['recaptcha_secret_key'] );

		$forms_raw = XL_Forms::get_all_forms();
		$forms = array();
		foreach ( $forms_raw as $form ) {
			$forms[] = self::form_to_export_array( $form );
		}

		return array(
			'xl_export_version' => self::EXPORT_VERSION,
			'plugin_version' => XTREMELEADS_VERSION,
			'exported_at' => gmdate( 'c' ),
			'type' => 'full',
			'settings' => $settings,
			'email_template' => $email_tmpl,
			'routing_rules' => $routing_rules,
			'forms' => $forms,
		);
	}

	/**
	 * Build a single-form export payload.
	 *
	 * @param int $form_id Form ID to export.
	 * @return array|WP_Error Export data array or WP_Error on failure.
	 */
	public static function build_form_export( int $form_id ): array|WP_Error {
		$form = XL_Forms::get_form( $form_id );
		if ( ! $form ) {
			return new WP_Error( 'form_not_found', __( 'Form not found.', 'xtremeleads' ) );
		}

		return array(
			'xl_export_version' => self::EXPORT_VERSION,
			'plugin_version' => XTREMELEADS_VERSION,
			'exported_at' => gmdate( 'c' ),
			'type' => 'form',
			'forms' => array( self::form_to_export_array( $form ) ),
		);
	}

	/**
	 * Convert a form DB object to an export-ready array.
	 *
	 * @param object $form Form DB row.
	 * @return array
	 */
	private static function form_to_export_array( object $form ): array {
		return array(
			'id' => (int) $form->id,
			'name' => $form->name,
			'fields' => XL_Forms::decode_fields( $form ),
			'settings' => XL_Forms::decode_settings( $form ),
			'status' => $form->status,
			'activate_at' => $form->activate_at ?? null,
			'expire_at' => $form->expire_at ?? null,
			'closed_message' => $form->closed_message ?? null,
			'created_at' => $form->created_at,
		);
	}

	/**
	 * Get routing rules for export.
	 *
	 * @return array
	 */
	private static function get_routing_rules_export(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_routing_rules';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY rule_order ASC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $rows ? array_map( static fn( $r ) => (array) $r, $rows ) : array();
	}

	// ── Download helpers ──────────────────────────────────────────────────────

	/**
	 * Stream a JSON export file as a download response.
	 *
	 * @param array $data Export payload array.
	 * @param string $filename Filename for the download.
	 */
	public static function stream_json_download( array $data, string $filename ): void {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			wp_die( esc_html__( 'Failed to encode export data.', 'xtremeleads' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		// phpcs:ignore WordPress.Security.EscapeOutput
		echo $json;
		exit;
	}

	// ── Import ────────────────────────────────────────────────────────────────

	/**
	 * Validate and parse an uploaded/pasted JSON import string.
	 *
	 * @param string $json Raw JSON string.
	 * @return array|WP_Error Parsed data array or WP_Error with a descriptive message.
	 */
	public static function parse_import( string $json ): array|WP_Error {
		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'json_parse_error',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Invalid JSON: %s', 'xtremeleads' ),
					json_last_error_msg()
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_structure', __( 'Import file must contain a JSON object.', 'xtremeleads' ) );
		}

		// Schema validation.
		$export_version = $data['xl_export_version'] ?? '';
		if ( '' === $export_version ) {
			return new WP_Error( 'missing_version', __( 'Import file is missing "xl_export_version". This file was not created by XtremeLeads or is corrupted.', 'xtremeleads' ) );
		}

		if ( version_compare( (string) $export_version, self::MIN_EXPORT_VERSION, '<' ) ) {
			return new WP_Error(
				'incompatible_version',
				sprintf(
					/* translators: 1: file version, 2: minimum supported version */
					__( 'This export file uses format version %1$s which is no longer supported (minimum: %2$s).', 'xtremeleads' ),
					esc_html( $export_version ),
					self::MIN_EXPORT_VERSION
				)
			);
		}

		$type = $data['type'] ?? '';
		if ( ! in_array( $type, array( 'full', 'form' ), true ) ) {
			return new WP_Error( 'invalid_type', __( 'Import file has an unrecognized "type" field. Expected "full" or "form".', 'xtremeleads' ) );
		}

		if ( ! isset( $data['forms'] ) || ! is_array( $data['forms'] ) ) {
			return new WP_Error( 'missing_forms', __( 'Import file is missing the "forms" array.', 'xtremeleads' ) );
		}

		return $data;
	}

	/**
	 * Execute a full import (settings + forms + routing rules).
	 *
	 * @param array $data Validated import data from parse_import().
	 * @return array { imported_forms: int, remapped_shortcuts: array, imported_routing_rules: int }
	 */
	public static function execute_full_import( array $data ): array {
		// Restore global settings (never overwrites recaptcha_secret_key if missing from export).
		if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$existing = get_option( 'xtremeleads_settings', array() );
			$imported = $data['settings'];
			// Always preserve the local secret key.
			if ( ! isset( $imported['recaptcha_secret_key'] ) ) {
				$imported['recaptcha_secret_key'] = $existing['recaptcha_secret_key'] ?? '';
			}
			update_option( 'xtremeleads_settings', array_merge( $existing, $imported ) );
		}

		// Email template.
		if ( ! empty( $data['email_template'] ) && is_array( $data['email_template'] ) ) {
			$existing_tmpl = get_option( 'xtremeleads_email_template', array() );
			update_option( 'xtremeleads_email_template', array_merge( $existing_tmpl, $data['email_template'] ) );
		}

		// Routing rules — replace all existing rules with imported ones.
		$imported_rules = 0;
		if ( isset( $data['routing_rules'] ) && is_array( $data['routing_rules'] ) ) {
			$imported_rules = self::import_routing_rules( $data['routing_rules'] );
		}

		// Forms.
		$result = self::import_forms( $data['forms'] ?? array() );
		$result['imported_routing_rules'] = $imported_rules;

		// Audit.
		if ( class_exists( 'XL_Audit_Log' ) ) {
			XL_Audit_Log::record(
				XL_Audit_Log::ACTION_PLUGIN_DATA_IMPORTED,
				0,
				array(
					'type' => 'full',
					'imported_forms' => $result['imported_forms'],
					'imported_routing_rules' => $imported_rules,
				)
			);
		}

		return $result;
	}

	/**
	 * Import routing rules from an export payload.
	 * Replaces all existing routing rules with the imported set.
	 * Also restores the routing evaluation mode if present in the export settings.
	 *
	 * @param array $rules_data Array of routing rule arrays from export.
	 * @return int Number of rules imported.
	 */
	private static function import_routing_rules( array $rules_data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'xtremeleads_routing_rules';

		// Clear existing rules before importing.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "TRUNCATE TABLE {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$imported = 0;
		$valid_condition_types = array( 'form', 'field_value' );

		foreach ( $rules_data as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$condition_type = sanitize_text_field( $rule['condition_type'] ?? 'form' );
			if ( ! in_array( $condition_type, $valid_condition_types, true ) ) {
				$condition_type = 'form';
			}

			$recipient_email = sanitize_email( $rule['recipient_email'] ?? '' );
			if ( ! $recipient_email ) {
				continue; // Skip rules with invalid recipient email.
			}

			$insert = array(
				'condition_type' => $condition_type,
				'form_id' => absint( $rule['form_id'] ?? 0 ),
				'field_id' => sanitize_text_field( $rule['field_id'] ?? '' ),
				'field_value' => sanitize_text_field( $rule['field_value'] ?? '' ),
				'recipient_email' => $recipient_email,
				'rule_order' => absint( $rule['rule_order'] ?? $imported ),
				'is_active' => isset( $rule['is_active'] ) ? (int) (bool) $rule['is_active'] : 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			);

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->insert(
				$table,
				$insert,
				array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( $wpdb->insert_id ) {
				++$imported;
			}
		}

		return $imported;
	}

	/**
	 * Execute a single-form import.
	 *
	 * @param array $data Validated import data from parse_import().
	 * @return array { imported_forms: int, remapped_shortcuts: array }
	 */
	public static function execute_form_import( array $data ): array {
		$result = self::import_forms( $data['forms'] ?? array() );

		if ( class_exists( 'XL_Audit_Log' ) ) {
			XL_Audit_Log::record(
				XL_Audit_Log::ACTION_PLUGIN_DATA_IMPORTED,
				0,
				array( 'type' => 'form', 'imported_forms' => $result['imported_forms'] )
			);
		}

		return $result;
	}

	/**
	 * Insert forms from an import payload.
	 *
	 * Each form always receives a NEW unique ID. Any shortcode conflicts are detected
	 * and reported in the return value.
	 *
	 * @param array $forms Array of form export arrays.
	 * @return array { imported_forms: int, remapped_shortcuts: array }
	 */
	private static function import_forms( array $forms ): array {
		$imported = 0;
		$remapped = array();
		$existing_form_ids = self::get_all_form_ids();

		foreach ( $forms as $form_data ) {
			if ( ! is_array( $form_data ) ) {
				continue;
			}

			$name = sanitize_text_field( $form_data['name'] ?? __( 'Imported Form', 'xtremeleads' ) );
			$fields = is_array( $form_data['fields'] ?? null ) ? $form_data['fields'] : array();
			$settings = is_array( $form_data['settings'] ?? null ) ? $form_data['settings'] : array();
			$status = in_array( $form_data['status'] ?? 'active', array( 'active', 'inactive' ), true )
				? $form_data['status']
				: 'active';

			$new_form_id = XL_Forms::create_form( $name, $fields, $settings );

			if ( ! $new_form_id ) {
				continue;
			}

			// Update scheduling columns if present.
			if ( isset( $form_data['activate_at'] ) || isset( $form_data['expire_at'] ) || isset( $form_data['closed_message'] ) ) {
				global $wpdb;
				$table = $wpdb->prefix . 'xtremeleads_forms';
				$update_data = array( 'status' => $status );
				$update_format = array( '%s' );
				if ( isset( $form_data['activate_at'] ) && $form_data['activate_at'] ) {
					$update_data['activate_at'] = sanitize_text_field( $form_data['activate_at'] );
					$update_format[] = '%s';
				}
				if ( isset( $form_data['expire_at'] ) && $form_data['expire_at'] ) {
					$update_data['expire_at'] = sanitize_text_field( $form_data['expire_at'] );
					$update_format[] = '%s';
				}
				if ( isset( $form_data['closed_message'] ) ) {
					$update_data['closed_message'] = sanitize_textarea_field( $form_data['closed_message'] );
					$update_format[] = '%s';
				}
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->update( $table, $update_data, array( 'id' => $new_form_id ), $update_format, array( '%d' ) );
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}

			// Check for shortcode conflicts in form settings (form ids differ, so just re-note).
			$old_id = $form_data['id'] ?? null;
			if ( $old_id && in_array( (int) $old_id, $existing_form_ids, true ) ) {
				$remapped[] = array(
					'original_id' => (int) $old_id,
					'new_id' => $new_form_id,
					'name' => $name,
				);
			}

			if ( class_exists( 'XL_Audit_Log' ) ) {
				XL_Audit_Log::record(
					XL_Audit_Log::ACTION_FORM_CREATED,
					$new_form_id,
					array( 'source' => 'import', 'original_name' => $name )
				);
			}

			++$imported;
		}

		return array(
			'imported_forms' => $imported,
			'remapped_shortcuts' => $remapped,
		);
	}

	/**
	 * Get all existing form IDs.
	 *
	 * @return int[]
	 */
	private static function get_all_form_ids(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'xtremeleads_forms';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$ids = $wpdb->get_col( "SELECT id FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.UnescapedDBParameter, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return array_map( 'intval', $ids ?: array() );
	}
}
