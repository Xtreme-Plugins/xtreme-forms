<?php
/**
 * Form Template Picker — shown when "Add New Form" is clicked with no template yet selected.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are local to this included partial, not global scope.

/**
 * Template definitions for the picker UI.
 *
 * @var array<array{slug:string, name:string, icon:string, desc:string, categories:string[]}>
 */
$xf_picker_templates = array(
	array(
		'slug'       => 'blank',
		'name'       => __( 'Blank Form', 'xtreme-forms' ),
		'icon'       => 'dashicons-plus-alt',
		'desc'       => __( 'Start from scratch with a completely empty form.', 'xtreme-forms' ),
		'categories' => array( 'blank' ),
	),
	array(
		'slug'       => 'contact',
		'name'       => __( 'Simple Contact Form', 'xtreme-forms' ),
		'icon'       => 'dashicons-email-alt',
		'desc'       => __( 'Name, email and message — the classic contact form.', 'xtreme-forms' ),
		'categories' => array( 'lead_generation' ),
	),
	array(
		'slug'       => 'lead',
		'name'       => __( 'Lead Capture Form', 'xtreme-forms' ),
		'icon'       => 'dashicons-businessman',
		'desc'       => __( 'Capture name, email, phone, and company for sales leads.', 'xtreme-forms' ),
		'categories' => array( 'lead_generation' ),
	),
	array(
		'slug'       => 'newsletter',
		'name'       => __( 'Newsletter Signup', 'xtreme-forms' ),
		'icon'       => 'dashicons-megaphone',
		'desc'       => __( 'Build your mailing list with a simple opt-in form.', 'xtreme-forms' ),
		'categories' => array( 'marketing', 'registration' ),
	),
	array(
		'slug'       => 'quote',
		'name'       => __( 'Quote Request', 'xtreme-forms' ),
		'icon'       => 'dashicons-money-alt',
		'desc'       => __( 'Let prospects describe their project and budget.', 'xtreme-forms' ),
		'categories' => array( 'lead_generation', 'marketing' ),
	),
	array(
		'slug'       => 'event',
		'name'       => __( 'Event Registration', 'xtreme-forms' ),
		'icon'       => 'dashicons-calendar-alt',
		'desc'       => __( 'Collect attendee info for webinars and events.', 'xtreme-forms' ),
		'categories' => array( 'registration' ),
	),
);

/**
 * Sidebar category definitions: slug => [ label, count ].
 *
 * @var array<string, array{label:string, count:int}>
 */
$xf_picker_categories = array(
	'all'             => array( 'label' => __( 'All Templates', 'xtreme-forms' ),  'count' => 6 ),
	'blank'           => array( 'label' => __( 'Blank', 'xtreme-forms' ),           'count' => 1 ),
	'lead_generation' => array( 'label' => __( 'Lead Generation', 'xtreme-forms' ), 'count' => 2 ),
	'registration'    => array( 'label' => __( 'Registration', 'xtreme-forms' ),    'count' => 2 ),
	'marketing'       => array( 'label' => __( 'Marketing', 'xtreme-forms' ),       'count' => 1 ),
);
?>
<div class="wrap xf-wrap">

	<!-- ── Page Header ────────────────────────────────────────────────────── -->
	<div class="xf-page-header">
		<div class="xf-flex-center xf-gap-8">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="xf-back-link">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Forms', 'xtreme-forms' ); ?>
			</a>
			<span class="xf-breadcrumb-sep">/</span>
			<h1 class="xf-page-title"><?php esc_html_e( 'Create a New Form', 'xtreme-forms' ); ?></h1>
		</div>
	</div>

	<!-- ── Template picker layout ─────────────────────────────────────────── -->
	<div class="xf-tpl-layout">

		<!-- Sidebar -->
		<aside class="xf-tpl-sidebar" id="xf-tpl-sidebar">
			<p class="xf-tpl-sidebar-title"><?php esc_html_e( 'Categories', 'xtreme-forms' ); ?></p>
			<?php foreach ( $xf_picker_categories as $cat_slug => $cat ) : ?>
				<div class="xf-tpl-cat<?php echo 'all' === $cat_slug ? ' active' : ''; ?>"
					data-cat="<?php echo esc_attr( $cat_slug ); ?>"
					role="button"
					tabindex="0">
					<?php echo esc_html( $cat['label'] ); ?>
					<span class="xf-tpl-cat-count"><?php echo absint( $cat['count'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</aside>

		<!-- Template grid -->
		<div class="xf-tpl-grid" id="xf-tpl-grid">
			<?php foreach ( $xf_picker_templates as $tpl ) :
				$use_url = add_query_arg(
					array(
						'page'        => 'xtreme-forms-forms',
						'xf_action'  => 'new',
						'xf_template' => $tpl['slug'],
					),
					admin_url( 'admin.php' )
				);
				?>
				<div class="xf-tpl-card"
					data-template="<?php echo esc_attr( $tpl['slug'] ); ?>"
					data-categories="<?php echo esc_attr( implode( ' ', $tpl['categories'] ) ); ?>">

					<div class="xf-tpl-card-preview">
						<div class="xf-tpl-icon">
							<span class="dashicons <?php echo esc_attr( $tpl['icon'] ); ?>"></span>
						</div>
					</div>

					<div class="xf-tpl-card-body">
						<h3 class="xf-tpl-name"><?php echo esc_html( $tpl['name'] ); ?></h3>
						<p class="xf-tpl-desc"><?php echo esc_html( $tpl['desc'] ); ?></p>
					</div>

					<div class="xf-tpl-card-footer">
						<a href="<?php echo esc_url( $use_url ); ?>" class="xf-btn xf-btn-primary xf-btn-sm">
							<?php esc_html_e( 'Use Template', 'xtreme-forms' ); ?>
						</a>
					</div>

				</div><!-- .xf-tpl-card -->
			<?php endforeach; ?>
		</div><!-- .xf-tpl-grid -->

	</div><!-- .xf-tpl-layout -->

</div><!-- .wrap.xf-wrap -->

