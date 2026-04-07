<?php
/**
 * Plugin Name: XtremeLeads
 * Plugin URI:  https://xtremeplugins.com/plugins/xtreme-leads/
 * Description: Powerful lead capture forms with database storage, email routing, webhooks, analytics, spam protection, GDPR compliance, and multisite support. Free forever with optional Pro upgrade.
 * Version:     1.6.5
 * Author:      XtremePlugins
 * Author URI:  https://xtremeplugins.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * Text Domain: xtremeleads
 * Domain Path: /languages
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'XTREMELEADS_VERSION', '1.6.5' );
define( 'XTREMELEADS_PLUGIN_FILE', __FILE__ );
define( 'XTREMELEADS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XTREMELEADS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XTREMELEADS_TEXT_DOMAIN', 'xtremeleads' );

// Activation / deactivation hooks — load activator early.
require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-activator.php';

register_activation_hook( __FILE__, array( 'XL_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'XL_Activator', 'deactivate' ) );

// Multisite: provision tables for newly created blogs.
add_action( 'wp_initialize_site', array( 'XL_Activator', 'on_new_blog' ), 10, 1 );

// Run DB upgrade checks on every request (uses dbDelta idempotency).
add_action( 'plugins_loaded', array( 'XL_Activator', 'maybe_upgrade' ), 5 );

/**
 * Bootstrap all plugin components after WordPress has loaded.
 */
function xtremeleads_init(): void {
	// Core classes.
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-forms.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-leads.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-email-log.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-email-templates.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-routing-rules.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-email.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-shortcode.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-notes.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-activity.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-tags.php';
	// Analytics, UTM, Duplicates.
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-analytics.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-utm.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-duplicates.php';
	// Webhooks, GDPR, Spam.
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-webhooks.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-gdpr.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-spam.php';
	XL_GDPR::init();
	// Audit Log, Multisite, Import/Export.
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-audit-log.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-multisite.php';
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-import-export.php';
	XL_Multisite::init();
	require_once XTREMELEADS_PLUGIN_DIR . 'includes/class-xl-ajax.php';

	// Admin.
	if ( is_admin() ) {
		require_once XTREMELEADS_PLUGIN_DIR . 'admin/class-xl-admin.php';
		new XL_Admin();
	}

	// Shortcode.
	$shortcode = new XL_Shortcode();
	add_shortcode( 'xtremeleads', array( $shortcode, 'render' ) );

	// AJAX handlers.
	new XL_Ajax();

	// Gutenberg block.
	add_action( 'init', 'xtremeleads_register_block' );
}
add_action( 'plugins_loaded', 'xtremeleads_init' );

/**
 * Register the Gutenberg block (server-side rendered, no build step needed).
 *
 * Uses block.json (apiVersion 3) for full FSE / Site Editor compatibility.
 * The block is server-side rendered via xtremeleads_block_render(), which means
 * it works correctly inside template parts, page templates, and query loops in
 * all FSE-compatible themes (Twenty Twenty-Three, Blockbase, Frost, etc.).
 * save() returns null in the JS, which is the required pattern for dynamic blocks.
 */
function xtremeleads_register_block(): void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	// Register the editor-facing script with the block dependencies.
	// Loaded only inside the block editor — not on the front end.
	wp_register_script(
		'xl-form-block',
		XTREMELEADS_PLUGIN_URL . 'blocks/xl-form-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
		XTREMELEADS_VERSION,
		true
	);

	// Provide form list (with field definitions) to the block editor.
	// Data is localized into the editor-script only (requires editor login).
	if ( class_exists( 'XL_Forms' ) ) {
		$forms = XL_Forms::get_all_forms();
		$forms_data = array_map(
			static function ( $form ) {
				// Include field definitions for live preview rendering.
				$fields = XL_Forms::decode_fields( $form );
				// Strip down to just what the editor preview needs.
				$fields_preview = array_values( array_filter(
					array_map( static function ( $field ) {
						if ( ! is_array( $field ) ) {
							return null;
						}
						$type = $field['type'] ?? 'text';
						if ( 'hidden' === $type ) {
							return null;
						}
						return array(
							'id' => $field['id'] ?? '',
							'type' => $type,
							'label' => $field['label'] ?? '',
							'required' => ! empty( $field['required'] ),
							'options' => $field['options'] ?? array(),
						);
					}, $fields )
				) );
				return array(
					'id' => (int) $form->id,
					'name' => isset( $form->name ) ? (string) $form->name : '',
					'fields' => $fields_preview,
				);
			},
			$forms
		);
	} else {
		$forms_data = array();
	}

	wp_localize_script(
		'xl-form-block',
		'xlBlockData',
		array(
			'forms' => $forms_data,
			'pluginUrl' => esc_url( XTREMELEADS_PLUGIN_URL ),
		)
	);

	// Register via block.json (apiVersion 3) for FSE compatibility.
	// The block.json file declares apiVersion 3, supports.__experimentalLayout,
	// and other FSE-required metadata. render_callback is set here to override
	// the block.json `editorScript`-only output with server-side PHP rendering.
	register_block_type(
		XTREMELEADS_PLUGIN_DIR . 'blocks',
		array(
			'render_callback' => 'xtremeleads_block_render',
		)
	);
}

/**
 * Register XtremeLeads block category.
 *
 * @param array $categories Existing block categories.
 * @return array Modified categories.
 */
function xtremeleads_register_block_category( array $categories ): array {
	return array_merge(
		array(
			array(
				'slug' => 'xtremeleads',
				'title' => __( 'XtremeLeads', 'xtremeleads' ),
				'icon' => 'email-alt',
			),
		),
		$categories
	);
}
add_filter( 'block_categories_all', 'xtremeleads_register_block_category', 10, 1 );

/**
 * Server-side render callback for the Gutenberg block.
 *
 * This callback is invoked both in the standard post/page editor and inside the
 * Full Site Editor (FSE) when the block is used in template parts or page templates.
 * It is deliberately defensive — all attributes are validated before use and no PHP
 * notices or warnings will be emitted when attributes are missing or null, which can
 * occur in FSE template contexts where attributes may not always be fully hydrated.
 *
 * @param array $attributes Block attributes (may be partial in FSE template context).
 * @param string $content Inner block content (unused — forms are shortcode-rendered).
 * @param WP_Block|null $block Block instance for context (FSE provides this).
 * @return string HTML output.
 */
function xtremeleads_block_render( array $attributes, string $content = '', $block = null ): string {
	// Guard against null/non-array attributes — can happen in FSE template rendering.
	if ( ! is_array( $attributes ) ) {
		$attributes = array();
	}

	$form_id = isset( $attributes['formId'] ) ? absint( $attributes['formId'] ) : 0;
	if ( ! $form_id ) {
		// In the editor this shows a placeholder message; on the front end with no
		// form selected it renders nothing to avoid cluttering FSE templates.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return '<p class="xl-block-no-form" style="padding:16px;border:1px dashed #DEE2E6;border-radius:6px;">'.
				esc_html__( 'Please select a form in the block settings panel.', 'xtremeleads' ).
				'</p>';
		}
		return '';
	}

	// Build wrapper styles — all values sanitized before use.
	$style_parts = array();

	// Width: sanitize and allow only safe CSS values.
	$width = isset( $attributes['width'] ) ? sanitize_text_field( (string) $attributes['width'] ) : '100%';
	if ( '' !== $width && preg_match( '/^(?:\d+(?:\.\d+)?(?:px|%)|100%)$/', $width ) ) {
		$style_parts[] = 'max-width:' . $width;
	}

	// Background color: sanitize to safe hex or rgb value.
	$bg_color = isset( $attributes['backgroundColor'] ) ? sanitize_text_field( (string) $attributes['backgroundColor'] ) : '';
	if ( '' !== $bg_color && preg_match( '/^#[0-9A-Fa-f]{3,8}$/', $bg_color ) ) {
		$style_parts[] = 'background-color:' . $bg_color;
	}

	// Alignment: whitelist valid values.
	$alignment = isset( $attributes['alignment'] ) ? sanitize_key( (string) $attributes['alignment'] ) : 'left';
	$alignment = in_array( $alignment, array( 'left', 'center', 'right', 'full' ), true ) ? $alignment : 'left';

	if ( 'center' === $alignment ) {
		$style_parts[] = 'margin-left:auto';
		$style_parts[] = 'margin-right:auto';
	} elseif ( 'right' === $alignment ) {
		$style_parts[] = 'margin-left:auto';
	} elseif ( 'full' === $alignment ) {
		$style_parts[] = 'max-width:100%';
		$style_parts[] = 'width:100%';
	}

	$style_attr = ! empty( $style_parts ) ? ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"' : '';
	$align_class = 'xl-block-align-' . $alignment; // Already validated to safe value.

	// Render the form via the shortcode engine, which works in all WordPress contexts
	// including FSE template parts, query loops, and classic themes.
	// do_shortcode() is safe to call here — XtremeLeads shortcode is registered on init.
	$inner = function_exists( 'do_shortcode' )
		? do_shortcode( '[xtremeleads id="' . $form_id . '"]' )
		: '';

	return '<div class="xl-block-wrapper ' . esc_attr( $align_class ) . '"' . $style_attr . '>' . $inner . '</div>';
}
