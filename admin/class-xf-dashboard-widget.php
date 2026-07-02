<?php
/**
 * "Xtreme Forms — Overview" WordPress dashboard widget.
 *
 * Adds an at-a-glance panel to the wp-admin Dashboard: form/lead counts, quick
 * actions, feature highlights, a Pro upsell (free plan only) and helpful links.
 *
 * @package Xtreme_Forms
 */

defined( 'ABSPATH' ) || exit;

class Xtremeforms_Dashboard_Widget {

	/** Dashboard widget ID (also used to scope the widget CSS). */
	const WIDGET_ID = 'xtremeforms_overview_widget';

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the dashboard widget for administrators.
	 */
	public function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'Xtreme Forms — Overview', 'xtreme-forms' ),
			array( $this, 'render' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Render the widget contents.
	 */
	public function render() {
		$total_forms = class_exists( 'Xtremeforms_Analytics' ) ? (int) Xtremeforms_Analytics::count_active_forms() : 0;
		$leads_month = class_exists( 'Xtremeforms_Analytics' ) ? (int) Xtremeforms_Analytics::count_leads_this_month() : 0;
		$is_pro      = class_exists( 'Xtremeforms_License' ) && Xtremeforms_License::is_active();

		$create_url   = admin_url( 'admin.php?page=xtreme-forms-forms&xtremeforms_action=new' );
		$list_url     = admin_url( 'admin.php?page=xtreme-forms-forms' );
		$settings_url = admin_url( 'admin.php?page=xtreme-forms-settings' );

		// xtremeplugins.com links (routes verified against the live Next.js app).
		$plugin_url  = 'https://xtremeplugins.com/plugins/xtreme-forms';
		$docs_url    = 'https://xtremeplugins.com/docs/xtreme-forms';
		$support_url = 'https://xtremeplugins.com/plugins/xtreme-forms/support';
		$blog_url    = 'https://xtremeplugins.com/blog';
		$buy_url     = 'https://xtremeplugins.com/plugins/xtreme-forms/pricing';

		$features = array(
			array( 'dashicons-forms',         __( 'Drag & Drop Builder', 'xtreme-forms' ) ),
			array( 'dashicons-email-alt',     __( 'Lead Inbox & CRM', 'xtreme-forms' ) ),
			array( 'dashicons-randomize',     __( 'Email Routing & Webhooks', 'xtreme-forms' ) ),
			array( 'dashicons-shield-alt',    __( 'Spam Protection', 'xtreme-forms' ) ),
		);
		$id = self::WIDGET_ID;
		?>
		<style>
			#<?php echo esc_html( $id ); ?> .xfo-stats { display:flex; gap:10px; margin:0 0 14px; }
			#<?php echo esc_html( $id ); ?> .xfo-stat { flex:1; text-align:center; padding:14px 10px; border-radius:8px; background:linear-gradient(135deg,#eef2ff,#f5f3ff); border:1px solid #e0e7ff; }
			#<?php echo esc_html( $id ); ?> .xfo-stat strong { display:block; font-size:24px; font-weight:700; color:#1e1b4b; line-height:1.1; }
			#<?php echo esc_html( $id ); ?> .xfo-stat span { display:block; margin-top:4px; color:#4b5563; font-size:12px; }
			#<?php echo esc_html( $id ); ?> .xfo-actions { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
			#<?php echo esc_html( $id ); ?> h4.xfo-title { margin:0 0 10px; font-size:14px; color:#1d2327; }
			#<?php echo esc_html( $id ); ?> .xfo-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:0 0 16px; }
			#<?php echo esc_html( $id ); ?> .xfo-feature { display:flex; align-items:center; gap:8px; padding:9px 10px; border-radius:8px; background:#f6f7f7; border:1px solid #e2e4e7; font-weight:500; transition:background .15s ease, transform .15s ease; }
			#<?php echo esc_html( $id ); ?> .xfo-feature:hover { background:#eef4ff; transform:translateY(-1px); }
			#<?php echo esc_html( $id ); ?> .xfo-feature .dashicons { color:#4f46e5; width:20px; height:20px; font-size:20px; }
			#<?php echo esc_html( $id ); ?> .xfo-upsell { padding:14px; border-radius:10px; margin:0 0 16px; background:linear-gradient(135deg,#eff6ff,#faf5ff); border:1px solid #dbeafe; }
			#<?php echo esc_html( $id ); ?> .xfo-upsell ul { margin:8px 0 12px; }
			#<?php echo esc_html( $id ); ?> .xfo-upsell li { margin:0 0 4px; color:#374151; }
			#<?php echo esc_html( $id ); ?> .xfo-gopro.button-primary { background:#6d28d9; border-color:#6d28d9; text-shadow:none; box-shadow:none; }
			#<?php echo esc_html( $id ); ?> .xfo-gopro.button-primary:hover { background:#5b21b6; border-color:#5b21b6; }
			#<?php echo esc_html( $id ); ?> .xfo-blog { margin:0 0 12px; }
			#<?php echo esc_html( $id ); ?> .xfo-blog-list { margin:0; padding-left:18px; }
			#<?php echo esc_html( $id ); ?> .xfo-blog-list li { margin:0 0 6px; list-style:disc; }
			#<?php echo esc_html( $id ); ?> .xfo-badge { display:inline-block; background:#6d28d9; color:#fff; font-size:10px; font-weight:600; padding:1px 6px; border-radius:3px; margin-right:6px; vertical-align:1px; }
			#<?php echo esc_html( $id ); ?> .xfo-footer { display:flex; flex-wrap:wrap; gap:14px; margin-top:6px; padding-top:12px; border-top:1px solid #dcdcde; font-size:13px; }
			#<?php echo esc_html( $id ); ?> .xfo-footer a { text-decoration:none; color:#2271b1; display:inline-flex; align-items:center; gap:2px; }
			#<?php echo esc_html( $id ); ?> .xfo-footer a.xfo-gopro { color:#00a32a; font-weight:600; }
			#<?php echo esc_html( $id ); ?> .xfo-footer .dashicons { font-size:15px; width:15px; height:15px; }
		</style>

		<div class="xfo">
			<div class="xfo-stats">
				<div class="xfo-stat">
					<strong><?php echo esc_html( number_format_i18n( $total_forms ) ); ?></strong>
					<span><?php esc_html_e( 'Active Forms', 'xtreme-forms' ); ?></span>
				</div>
				<div class="xfo-stat">
					<strong><?php echo esc_html( number_format_i18n( $leads_month ) ); ?></strong>
					<span><?php esc_html_e( 'Leads this month', 'xtreme-forms' ); ?></span>
				</div>
			</div>

			<div class="xfo-actions">
				<a href="<?php echo esc_url( $create_url ); ?>" class="button button-primary"><?php esc_html_e( 'Create Form', 'xtreme-forms' ); ?></a>
				<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'All Forms', 'xtreme-forms' ); ?></a>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Settings', 'xtreme-forms' ); ?></a>
			</div>

			<h4 class="xfo-title"><?php esc_html_e( 'Popular Features', 'xtreme-forms' ); ?></h4>
			<div class="xfo-grid">
				<?php foreach ( $features as $feature ) : ?>
					<div class="xfo-feature">
						<span class="dashicons <?php echo esc_attr( $feature[0] ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $feature[1] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ! $is_pro ) : ?>
				<div class="xfo-upsell">
					<h4 class="xfo-title"><?php esc_html_e( 'Unlock Pro Features', 'xtreme-forms' ); ?></h4>
					<ul>
						<li><?php esc_html_e( '✔ Payment fields (Stripe / PayPal)', 'xtreme-forms' ); ?></li>
						<li><?php esc_html_e( '✔ File upload fields', 'xtreme-forms' ); ?></li>
						<li><?php esc_html_e( '✔ Zapier & CRM integrations', 'xtreme-forms' ); ?></li>
						<li><?php esc_html_e( '✔ 50+ form templates', 'xtreme-forms' ); ?></li>
					</ul>
					<a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary xfo-gopro"><?php esc_html_e( 'Upgrade Now', 'xtreme-forms' ); ?></a>
				</div>
			<?php endif; ?>

			<div class="xfo-blog">
				<h4 class="xfo-title"><?php esc_html_e( 'Latest from the blog', 'xtreme-forms' ); ?></h4>
				<ul class="xfo-blog-list">
					<li>
						<span class="xfo-badge"><?php esc_html_e( 'NEW', 'xtreme-forms' ); ?></span>
						<a href="<?php echo esc_url( $plugin_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php
							printf(
								/* translators: %s: plugin version number. */
								esc_html__( 'What\'s new in Xtreme Forms %s', 'xtreme-forms' ),
								esc_html( XTREMEFORMS_VERSION )
							);
							?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( $blog_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Tips & tutorials on the Xtreme Plugins blog', 'xtreme-forms' ); ?></a>
					</li>
				</ul>
			</div>

			<div class="xfo-footer">
				<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Docs', 'xtreme-forms' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<a href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support', 'xtreme-forms' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<a href="<?php echo esc_url( $blog_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Blog', 'xtreme-forms' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener noreferrer" class="xfo-gopro"><?php esc_html_e( 'Buy Now', 'xtreme-forms' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
			</div>
		</div>
		<?php
	}
}
