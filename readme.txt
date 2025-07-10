=== XtremeLeads ===
Contributors: xtremeplugins
Tags: lead capture, contact form, leads, email notifications, webhooks, analytics, GDPR, spam protection, multisite
Tested up to: 6.9
Stable tag: 1.6.0
Requires at least: 6.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Powerful lead capture forms with email routing, webhooks, analytics, spam protection, GDPR tools, and multisite support. Free with optional Pro upgrade.

== Description ==

XtremeLeads is a comprehensive WordPress lead capture and management plugin. Build custom forms, capture leads into a database, route notifications to the right team members, track analytics, and stay GDPR-compliant — all from a clean, fast admin interface.

The free tier is fully featured for most use cases. An optional Pro upgrade unlocks advanced routing, webhook retries, extended analytics, and white-label mode.

= Core Features (Free) =

* **Drag-and-drop Form Builder** — text, email, phone, select, checkbox, radio, textarea, date, file, hidden fields
* **Lead Inbox** — searchable, filterable lead list with status management (new, read, contacted, converted, archived, spam)
* **Email Notifications** — route notifications to different recipients based on form field values
* **Auto-Responder** — send a branded confirmation email to the lead on submission
* **Email Templates** — reusable templates with merge tags ({{first_name}}, {{form_name}}, etc.)
* **Webhooks** — fire HTTP POST payloads to external URLs on lead capture
* **Analytics Dashboard** — submission trends, conversion rates, top forms, lead source breakdown
* **UTM Tracking** — automatically capture and store UTM parameters with each lead
* **Duplicate Detection** — configurable duplicate suppression by email/phone within a time window
* **Spam Protection** — honeypot, time-gate, reCAPTCHA v3, and keyword blocklist
* **GDPR Tools** — consent checkbox, right to erasure, configurable data retention
* **Activity Timeline** — per-lead event history (submitted, emailed, status changes, notes)
* **Notes** — add internal notes to any lead
* **Tags** — tag and filter leads with custom labels
* **Audit Log** — append-only log of all admin actions
* **Import / Export** — full JSON round-trip export and import
* **Multisite Support** — per-site tables, network-aware activation
* **Gutenberg Block** — embed any form with the XtremeLeads block (live editor preview)
* **Shortcode** — `[xtremeleads id="X"]` works everywhere

= Pro Upgrade =

Unlock advanced features with a Pro license from https://xtremeplugins.com/plugins/xtreme-leads/pro/:

* Priority routing rules with complex conditions
* Webhook retry queue with exponential backoff
* Advanced analytics: cohort analysis, lead value tracking
* White-label mode (remove XtremeLeads branding from front-end forms)
* Priority email support

== Support ==

Please submit bugs, patches, and feature requests to:

https://github.com/Xtreme-Plugins/xtreme-leads

== Installation ==

1. Download `xtreme-leads.zip`
1. Unzip
1. Upload the `xtremeleads` directory to `/wp-content/plugins`
1. Activate the plugin
1. Go to **XtremeLeads → Forms** to build your first form
1. Embed it with `[xtremeleads id="X"]` or the Gutenberg block

== Screenshots ==

1. Lead inbox — filterable, searchable list with status badges and bulk actions
2. Form builder — drag-and-drop field editor with live preview
3. Analytics dashboard — submission trends and lead source breakdown
4. Lead detail — activity timeline, notes, tags, and audit trail

== Frequently Asked Questions ==

= Is the free version really free? =

Yes. The free version has no artificial limits on forms, leads, or submissions. Pro adds advanced features for power users.

= Does it work without WooCommerce? =

Yes. Fully standalone with zero external dependencies required.

= Does it work with Elementor? =

Yes. Use `[xtremeleads id="X"]` in Elementor's Shortcode widget, or the native Gutenberg block.

= Is it multisite compatible? =

Yes. XtremeLeads creates per-site database tables and supports network-wide activation.

= Does it work with GDPR / privacy laws? =

Yes. Includes consent checkbox, right-to-erasure data deletion, configurable data retention, and an append-only audit log.

= Can I export my leads? =

Yes. Full JSON export and import from the Import/Export admin page.

== Changelog ==

= 1.6.0 - 28th February 2026 =
* Added full import/export with JSON round-trip (export all forms + leads, re-import on any site)
* Added multisite support — per-site tables, network-aware activation, new blog provisioning
* Added append-only audit log for all admin actions
* Improved settings page organization with tabbed layout

= 1.5.0 - 21st January 2026 =
* Added webhooks with delivery logging and configurable retry logic
* Added GDPR consent checkbox (per form), right-to-erasure data deletion, configurable data retention
* Added spam protection: honeypot, time-gate, reCAPTCHA v3, keyword blocklist
* Fixed edge case where duplicate detection could fire on first submission in high-concurrency scenarios

= 1.4.0 - 17th December 2025 =
* Added analytics dashboard: submission trends, lead source breakdown, top forms, conversion rates
* Added UTM parameter capture and storage with each lead submission
* Added duplicate detection: configurable by email/phone within a time window, with admin override

= 1.3.0 - 26th November 2025 =
* Added email templates with merge tags ({{first_name}}, {{form_name}}, {{site_name}}, etc.)
* Added email routing rules: route notifications to different recipients based on field values
* Added email log: full record of all outbound emails per lead

= 1.2.0 - 22nd October 2025 =
* Added lead activity timeline — per-lead event history
* Added internal notes on leads
* Added tag management for filtering and organizing leads
* Improved lead inbox with bulk status updates

= 1.1.0 - 18th September 2025 =
* Added auto-responder: branded confirmation email to submitter on capture
* Added lead status workflow: new, read, contacted, converted, archived, spam
* Added per-field conditional logic in form builder
* Improved admin UI — neumorphic design refresh

= 1.0.0 - 14th August 2025 =
* Initial production release
* Drag-and-drop form builder with 10 field types
* Lead capture database with searchable inbox
* Email notifications on submission
* Shortcode `[xtremeleads id="X"]` and Gutenberg block
* Clean uninstall — removes all tables and options

= 0.9.0 - 10th July 2025 =
* Beta release for internal testing
* Basic form builder and lead capture
* Email notification on submission
* Admin lead list
