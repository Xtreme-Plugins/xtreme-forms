<?php
/**
 * Welcome / onboarding screen — shown once on first activation.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file included inside a method; variables are local scope.

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

$forms_url = admin_url( 'admin.php?page=xtreme-forms-forms' );
$docs_url  = 'https://xtremeplugins.com/docs/xtreme-forms/';
$video_url = '#'; // Replace with actual YouTube URL when available.
?>
<style>
/* ── Xtreme Forms Welcome Screen styles (scoped to .xf-welcome-wrap) ── */
.xf-welcome-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	color: #1d2327;
	max-width: 1000px;
	margin: 20px auto 40px;
	padding: 0 20px;
}

/* Header */
.xf-welcome-header {
	background: #fff;
	border-radius: 10px;
	box-shadow: 0 2px 12px rgba(0,0,0,.08);
	padding: 48px 40px 40px;
	text-align: center;
	margin-bottom: 24px;
}
.xf-welcome-logo {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: #e8f0fe;
	border-radius: 50%;
	width: 72px;
	height: 72px;
	margin: 0 auto 20px;
}
.xf-welcome-logo svg {
	width: 38px;
	height: 38px;
}
.xf-welcome-header h1 {
	font-size: 32px;
	font-weight: 700;
	margin: 0 0 12px;
	color: #1a1a2e;
	line-height: 1.2;
}
.xf-welcome-header p {
	font-size: 16px;
	color: #50575e;
	margin: 0;
	line-height: 1.6;
}

/* Card base */
.xf-welcome-card {
	background: #fff;
	border-radius: 10px;
	box-shadow: 0 2px 12px rgba(0,0,0,.08);
	padding: 36px 40px;
	margin-bottom: 24px;
}
.xf-welcome-card h2 {
	font-size: 20px;
	font-weight: 700;
	margin: 0 0 24px;
	color: #1a1a2e;
}

/* Getting Started */
.xf-video-placeholder {
	background: #1a1a2e;
	border-radius: 8px;
	height: 280px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	text-decoration: none;
	transition: opacity .2s;
	margin-bottom: 24px;
	position: relative;
	overflow: hidden;
}
.xf-video-placeholder:hover {
	opacity: .9;
}
.xf-video-play-btn {
	width: 64px;
	height: 64px;
	background: rgba(255,255,255,.15);
	border: 3px solid rgba(255,255,255,.8);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 16px;
	transition: background .2s;
}
.xf-video-placeholder:hover .xf-video-play-btn {
	background: rgba(255,255,255,.25);
}
.xf-video-play-btn svg {
	margin-left: 5px;
}
.xf-video-label {
	color: rgba(255,255,255,.9);
	font-size: 15px;
	font-weight: 500;
	letter-spacing: .3px;
}
.xf-welcome-ctas {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
}
.xf-btn-primary {
	display: inline-block;
	background: #2271b1;
	color: #fff !important;
	padding: 11px 22px;
	border-radius: 5px;
	text-decoration: none;
	font-size: 14px;
	font-weight: 600;
	transition: background .2s;
	border: none;
	cursor: pointer;
}
.xf-btn-primary:hover {
	background: #135e96;
	color: #fff !important;
}
.xf-btn-secondary {
	display: inline-block;
	background: #fff;
	color: #2271b1 !important;
	padding: 11px 22px;
	border-radius: 5px;
	text-decoration: none;
	font-size: 14px;
	font-weight: 600;
	border: 1.5px solid #2271b1;
	transition: background .2s, color .2s;
	cursor: pointer;
}
.xf-btn-secondary:hover {
	background: #f0f6fc;
}

/* Features Grid */
.xf-features-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}
@media (max-width: 640px) {
	.xf-features-grid { grid-template-columns: 1fr; }
	.xf-pro-inner { flex-direction: column !important; }
	.xf-welcome-ctas { flex-direction: column; }
}
.xf-feature-item {
	display: flex;
	align-items: flex-start;
	gap: 14px;
}
.xf-feature-icon {
	flex-shrink: 0;
	width: 40px;
	height: 40px;
	background: #e8f0fe;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 18px;
	line-height: 1;
}
.xf-feature-text h3 {
	font-size: 14px;
	font-weight: 700;
	margin: 0 0 4px;
	color: #1a1a2e;
}
.xf-feature-text p {
	font-size: 13px;
	color: #50575e;
	margin: 0;
	line-height: 1.55;
}

/* Pro upgrade section */
.xf-pro-section {
	background: #1a1a2e;
	border-radius: 10px;
	box-shadow: 0 2px 12px rgba(0,0,0,.18);
	padding: 40px;
	margin-bottom: 24px;
	color: #fff;
}
.xf-pro-inner {
	display: flex;
	gap: 40px;
	align-items: flex-start;
}
.xf-pro-features {
	flex: 1;
}
.xf-pro-features h2 {
	font-size: 22px;
	font-weight: 700;
	margin: 0 0 8px;
	color: #fff;
}
.xf-pro-features p {
	font-size: 14px;
	color: rgba(255,255,255,.65);
	margin: 0 0 24px;
}
.xf-pro-list {
	list-style: none;
	padding: 0;
	margin: 0;
}
.xf-pro-list li {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 14px;
	color: rgba(255,255,255,.9);
	margin-bottom: 12px;
}
.xf-pro-list li:last-child {
	margin-bottom: 0;
}
.xf-checkmark {
	width: 20px;
	height: 20px;
	background: #4caf50;
	border-radius: 50%;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	font-size: 11px;
	color: #fff;
	font-weight: 700;
}
.xf-pricing-card {
	flex-shrink: 0;
	background: #fff;
	border-radius: 10px;
	padding: 32px 28px;
	text-align: center;
	min-width: 200px;
}
.xf-pricing-badge {
	display: inline-block;
	background: #2271b1;
	color: #fff;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 1.5px;
	padding: 4px 12px;
	border-radius: 20px;
	margin-bottom: 14px;
	text-transform: uppercase;
}
.xf-pricing-price {
	font-size: 46px;
	font-weight: 800;
	color: #1a1a2e;
	line-height: 1;
	margin-bottom: 4px;
}
.xf-pricing-price sup {
	font-size: 22px;
	font-weight: 700;
	vertical-align: top;
	margin-top: 8px;
	display: inline-block;
}
.xf-pricing-period {
	font-size: 13px;
	color: #50575e;
	margin-bottom: 20px;
}
.xf-btn-orange {
	display: inline-block;
	background: #e86400;
	color: #fff !important;
	padding: 12px 24px;
	border-radius: 5px;
	text-decoration: none;
	font-size: 14px;
	font-weight: 700;
	transition: background .2s;
	width: 100%;
	box-sizing: border-box;
	text-align: center;
}
.xf-btn-orange:hover {
	background: #c75700;
	color: #fff !important;
}

/* Testimonials */
.xf-testimonials-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}
@media (max-width: 640px) {
	.xf-testimonials-grid { grid-template-columns: 1fr; }
}
.xf-testimonial {
	background: #f8f9fa;
	border-radius: 8px;
	padding: 24px;
	border-left: 4px solid #2271b1;
}
.xf-testimonial blockquote {
	margin: 0 0 16px;
	padding: 0;
	font-size: 14px;
	color: #3c434a;
	font-style: italic;
	line-height: 1.65;
	border: none;
	box-shadow: none;
}
.xf-testimonial-author {
	display: flex;
	align-items: center;
	gap: 12px;
}
.xf-avatar {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
	font-weight: 700;
	color: #fff;
	flex-shrink: 0;
}
.xf-avatar-sm { background: #7b5ea7; }
.xf-avatar-jk { background: #2271b1; }
.xf-testimonial-meta strong {
	display: block;
	font-size: 13px;
	color: #1d2327;
}
.xf-testimonial-meta span {
	font-size: 12px;
	color: #50575e;
}

/* Footer CTA strip */
.xf-welcome-footer {
	background: #f0f6fc;
	border: 1.5px solid #c3d9f5;
	border-radius: 10px;
	padding: 28px 40px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 16px;
}
.xf-welcome-footer p {
	margin: 0;
	font-size: 15px;
	font-weight: 600;
	color: #1a1a2e;
}
.xf-footer-actions {
	display: flex;
	align-items: center;
	gap: 20px;
	flex-wrap: wrap;
}
.xf-link-upgrade {
	font-size: 14px;
	font-weight: 600;
	color: #e86400;
	text-decoration: none;
}
.xf-link-upgrade:hover {
	text-decoration: underline;
	color: #c75700;
}
</style>

<div class="xf-welcome-wrap">

	<!-- ── Header ─────────────────────────────────────────────── -->
	<div class="xf-welcome-header">
		<div class="xf-welcome-logo">
			<!-- Inline SVG: envelope + form lines icon -->
			<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<rect x="3" y="8" width="34" height="24" rx="3" fill="#2271b1"/>
				<path d="M3 11l17 11L37 11" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
				<rect x="11" y="20" width="10" height="2" rx="1" fill="rgba(255,255,255,0.55)"/>
				<rect x="11" y="24" width="7" height="2" rx="1" fill="rgba(255,255,255,0.55)"/>
			</svg>
		</div>
		<h1><?php esc_html_e( 'Welcome to Xtreme Forms', 'xtreme-forms' ); ?></h1>
		<p><?php esc_html_e( 'Thank you for choosing Xtreme Forms — the most powerful lead capture plugin for WordPress.', 'xtreme-forms' ); ?></p>
	</div>

	<!-- ── Getting Started ───────────────────────────────────── -->
	<div class="xf-welcome-card">
		<h2><?php esc_html_e( 'How to Create Your First Form', 'xtreme-forms' ); ?></h2>
		<a href="<?php echo esc_url( $video_url ); ?>" class="xf-video-placeholder" target="_blank" rel="noopener noreferrer">
			<div class="xf-video-play-btn">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<polygon points="6,3 21,12 6,21" fill="rgba(255,255,255,0.95)"/>
				</svg>
			</div>
			<span class="xf-video-label"><?php esc_html_e( 'Click Here to Watch Video', 'xtreme-forms' ); ?></span>
		</a>
		<div class="xf-welcome-ctas">
			<a href="<?php echo esc_url( $forms_url ); ?>" class="xf-btn-primary">
				<?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?>
			</a>
			<a href="<?php echo esc_url( $docs_url ); ?>" class="xf-btn-secondary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Read the Documentation', 'xtreme-forms' ); ?>
			</a>
		</div>
	</div>

	<!-- ── Features Grid ─────────────────────────────────────── -->
	<div class="xf-welcome-card">
		<h2><?php esc_html_e( 'Everything You Need to Capture Leads', 'xtreme-forms' ); ?></h2>
		<div class="xf-features-grid">

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#9998;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Drag &amp; Drop Form Builder', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Build beautiful forms in minutes with our intuitive drag-and-drop interface. No coding required.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#128230;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Lead Management', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'View, filter, tag, and manage all your leads in one powerful inbox.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#8594;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Email Routing', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Automatically route leads to the right team member based on form fields and rules.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#128279;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Webhooks &amp; Integrations', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Connect to any CRM, Zapier, or third-party service via webhooks.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#128200;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Analytics &amp; UTM Tracking', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Track form performance, conversion rates, and marketing campaign data.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#128737;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Spam Protection', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Built-in honeypot, Google reCAPTCHA v3, and Cloudflare Turnstile support.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#128274;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'GDPR Compliance', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Data consent checkboxes, retention policies, and right-to-erasure built in.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

			<div class="xf-feature-item">
				<div class="xf-feature-icon">&#127760;</div>
				<div class="xf-feature-text">
					<h3><?php esc_html_e( 'Multisite Support', 'xtreme-forms' ); ?></h3>
					<p><?php esc_html_e( 'Works seamlessly across WordPress multisite networks.', 'xtreme-forms' ); ?></p>
				</div>
			</div>

		</div>
	</div>

	<!-- ── Testimonials ──────────────────────────────────────── -->
	<div class="xf-welcome-card">
		<h2><?php esc_html_e( 'What Our Users Are Saying', 'xtreme-forms' ); ?></h2>
		<div class="xf-testimonials-grid">

			<div class="xf-testimonial">
				<blockquote>
					&ldquo;<?php esc_html_e( 'Xtreme Forms replaced three different plugins for us. The lead routing alone saves our team hours every week.', 'xtreme-forms' ); ?>&rdquo;
				</blockquote>
				<div class="xf-testimonial-author">
					<div class="xf-avatar xf-avatar-sm">SM</div>
					<div class="xf-testimonial-meta">
						<strong><?php esc_html_e( 'Sarah Mitchell', 'xtreme-forms' ); ?></strong>
						<span><?php esc_html_e( 'Digital Agency Owner', 'xtreme-forms' ); ?></span>
					</div>
				</div>
			</div>

			<div class="xf-testimonial">
				<blockquote>
					&ldquo;<?php esc_html_e( 'The analytics and UTM tracking features are incredible. I finally know exactly which campaigns are driving leads.', 'xtreme-forms' ); ?>&rdquo;
				</blockquote>
				<div class="xf-testimonial-author">
					<div class="xf-avatar xf-avatar-jk">JK</div>
					<div class="xf-testimonial-meta">
						<strong><?php esc_html_e( 'James Kowalski', 'xtreme-forms' ); ?></strong>
						<span><?php esc_html_e( 'Marketing Director', 'xtreme-forms' ); ?></span>
					</div>
				</div>
			</div>

		</div>
	</div>

	<!-- ── Footer CTA strip ─────────────────────────────────── -->
	<div class="xf-welcome-footer">
		<p><?php esc_html_e( 'Ready to get started?', 'xtreme-forms' ); ?></p>
		<div class="xf-footer-actions">
			<a href="<?php echo esc_url( $forms_url ); ?>" class="xf-btn-primary">
				<?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?>
			</a>
		</div>
	</div>

</div><!-- .xf-welcome-wrap -->
