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

$forms_url   = admin_url( 'admin.php?page=xtreme-forms-forms' );
$upgrade_url = 'https://xtremeplugins.com/plugins/xtreme-forms/pricing';
$docs_url    = 'https://xtremeplugins.com/docs/xtreme-forms/';
$video_url   = '#'; // Replace with actual YouTube URL when available.
?>

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

	<!-- ── Upgrade to Pro ────────────────────────────────────── -->
	<div class="xf-pro-section">
		<div class="xf-pro-inner">

			<div class="xf-pro-features">
				<h2><?php esc_html_e( 'Unlock Even More with Pro', 'xtreme-forms' ); ?></h2>
				<p><?php esc_html_e( 'Supercharge your lead capture with advanced features built for growing businesses.', 'xtreme-forms' ); ?></p>
				<ul class="xf-pro-list">
					<li>
						<span class="xf-checkmark">&#10003;</span>
						<?php esc_html_e( 'Advanced Conditional Logic', 'xtreme-forms' ); ?>
					</li>
					<li>
						<span class="xf-checkmark">&#10003;</span>
						<?php esc_html_e( 'CRM Integrations (HubSpot, Salesforce, Mailchimp)', 'xtreme-forms' ); ?>
					</li>
					<li>
						<span class="xf-checkmark">&#10003;</span>
						<?php esc_html_e( 'Priority Email Support', 'xtreme-forms' ); ?>
					</li>
					<li>
						<span class="xf-checkmark">&#10003;</span>
						<?php esc_html_e( 'Advanced File Upload Fields', 'xtreme-forms' ); ?>
					</li>
					<li>
						<span class="xf-checkmark">&#10003;</span>
						<?php esc_html_e( 'Custom Confirmation Redirects', 'xtreme-forms' ); ?>
					</li>
					<li>
						<span class="xf-checkmark">&#10003;</span>
						<?php esc_html_e( 'White-Label Mode', 'xtreme-forms' ); ?>
					</li>
				</ul>
			</div>

			<div class="xf-pricing-card">
				<div class="xf-pricing-badge"><?php esc_html_e( 'PRO', 'xtreme-forms' ); ?></div>
				<div class="xf-pricing-price"><sup>$</sup>79</div>
				<div class="xf-pricing-period"><?php esc_html_e( 'per year', 'xtreme-forms' ); ?></div>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="xf-btn-orange" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade Now', 'xtreme-forms' ); ?>
				</a>
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
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="xf-link-upgrade" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to Pro &rarr;', 'xtreme-forms' ); ?>
			</a>
		</div>
	</div>

</div><!-- .xf-welcome-wrap -->
