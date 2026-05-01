=== Xtreme Forms ===
Contributors: xtremeplugins
Tags: lead capture, contact form, leads, webhooks, analytics
Tested up to: 6.9
Stable tag: 2.1.0
Requires at least: 6.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lead capture forms with email routing, webhooks, analytics, spam protection, and GDPR tools.

== Description ==

Xtreme Forms is a WordPress lead capture and management plugin. Build custom forms, capture leads into your own database, route notifications to the right team members, track analytics, and use the included GDPR tools (consent checkbox, right-to-erasure, data retention) to help support your own privacy workflows — all from a clean, fast admin interface.

All features listed below work in the plugin you are downloading. The optional Pro add-on offered by the author adds advanced routing, webhook retries, and extended analytics, but is not required for any of the features below.

= Core Features (Free) =

* **Drag-and-drop Form Builder** — text, email, phone, select, checkbox, radio, textarea, date, file, hidden fields with per-field conditional logic
* **Lead Inbox** — searchable, filterable lead list with status management (new, read, contacted, converted, archived, spam)
* **Email Notifications** — route notifications to different recipients based on form field values
* **Auto-Responder** — send a branded confirmation email to the lead on submission
* **Email Templates** — reusable templates with merge tags ({{first_name}}, {{form_name}}, etc.)
* **Webhooks** — fire HTTP POST payloads to external URLs on lead capture with delivery logging
* **Analytics Dashboard** — submission trends, conversion rates, top forms, lead source breakdown
* **UTM Tracking** — automatically capture and store UTM parameters with each lead
* **Duplicate Detection** — configurable duplicate suppression by email/phone within a time window
* **Spam Protection** — honeypot, time-gate, reCAPTCHA v3, and keyword blocklist
* **GDPR Tools** — consent checkbox, right-to-erasure helper, configurable data retention (tools to support your own GDPR workflow; the plugin does not guarantee legal compliance)
* **Activity Timeline** — per-lead event history (submitted, emailed, status changes, notes)
* **Notes** — add internal notes to any lead
* **Tags** — tag and filter leads with custom labels
* **Audit Log** — append-only log of all admin actions
* **Import / Export** — full JSON round-trip export and import
* **Multisite Support** — per-site tables, network-aware activation
* **Gutenberg Block** — embed any form with the Xtreme Forms block (live editor preview)
* **Shortcode** — `[xtreme_forms id="X"]` works everywhere

= Optional Pro Add-On =

An optional paid add-on from the author is available at https://xtremeplugins.com/plugins/xtreme-forms. It is not required for any feature listed above and is not bundled with this plugin.

* Priority routing rules with complex AND/OR conditions
* Webhook retry queue with exponential backoff
* Advanced analytics: cohort analysis, lead value tracking
* Priority email support

== Installation ==

1. Download `xtreme-forms.zip`
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Select the zip file and click **Install Now**
4. Activate the plugin
5. Go to **Xtreme Forms → Forms** to build your first form
6. Embed it with `[xtreme_forms id="X"]` or the Gutenberg block

Alternatively, unzip the archive and upload the `xtreme-forms` folder to `/wp-content/plugins/`, then activate from the Plugins screen.

== Frequently Asked Questions ==

= Is the free version really free? =

Yes. The free version has no artificial limits on forms, leads, or submissions. Pro adds advanced features for power users.

= Does it work without WooCommerce? =

Yes. Fully standalone with zero external dependencies required.

= Does it work with Elementor? =

Yes. Use `[xtreme_forms id="X"]` in Elementor's Shortcode widget, or the native Gutenberg block.

= Is it multisite compatible? =

Yes. Xtreme Forms creates per-site database tables and supports network-wide activation.

= Does it include GDPR tools? =

Yes — it ships a consent checkbox, a right-to-erasure helper, configurable data retention, and an append-only audit log. These are tools to help you build a GDPR-aware workflow. Compliance with GDPR or any other privacy law is your responsibility; the plugin does not guarantee legal compliance.

= Can I export my leads? =

Yes. Full JSON export and import from the Import/Export admin page.

= Where do I report bugs or request features? =

Please use the WordPress.org support forum for this plugin, or file an issue at https://github.com/Xtreme-Plugins/xtreme-forms.

== Screenshots ==

1. Frontend form — published lead capture form on a live site with GDPR consent checkbox and custom-styled submit button
2. Form builder — drag-and-drop field palette (textbox, dropdown, date picker, file upload, zip code, slider, etc.) with live canvas preview and editable submit button
3. Lead detail — submitted data, lead metadata (source URL, IP, user agent, GDPR consent), status + assignment controls, tags, and notes timeline
4. Automations — email templates with logo, header color, merge-tag support for subject/body/footer; tabs for Routing Rules, Webhooks, and Integrations
5. Analytics dashboard — all-time / monthly / weekly totals, leads-over-time chart, leads-by-form breakdown, conversion funnel, top source pages, and top performing forms

== Changelog ==

= 2.0.5 =
* WordPress.org submission prep: resolved Plugin Check findings
* Security: added `wp_unslash()` + `sanitize_text_field()` to all submit-layout `$_POST` reads in the admin save handler
* Repository hygiene: removed the plugin-directory `.gitignore` (hidden file not allowed by Plugin Check); dev ignores moved to `.git/info/exclude`
* Removed `phpunit.xml` from the shipped plugin (dev-only)
* Form Builder: floated Submit button now sits inline with the last row of floated fields (width 1/2, 1/3, 1/4)
* Form Builder: when Submit is floated, the admin preview hides the dashed outline, percentage badge, and "Click to edit" hint
* Form Settings: new **Styling** tab with "Remove background" toggle — renders the frontend form without the white card, border, or shadow
* Fix: cleared field labels now persist through reload (empty string no longer reverts to default "Text Field")
* Fix: Forms list, Form Metrics table, Dashboard widget, and Gutenberg block all now emit the correct `[xtreme_forms id="X"]` shortcode (was `[xtremeleads]` in 4 places — block render was broken)
* Docs: README.md, CHANGELOG.md and readme.txt updated with the corrected shortcode

= 2.0.3 =
* Stability release following the XtremeLeads → Xtreme Forms rename
* Ensured all text domain references use xtreme-forms
* Minor admin UI polish

= 2.0.2 =
* Fixed hidden .gitkeep file in assets/img/ (Plugin Check compliance)
* Added .distignore to exclude dev files from WP.org SVN releases
* PHPCS compliance: fixed inline ignore comments on multi-line expressions in admin partials
* Auto-corrected 1461 code formatting issues via phpcbf

= 2.0.1 =
* Added first-activation welcome screen (appears once after activation, dismissed automatically)
* Welcome screen sections: getting started video placeholder, 8-feature grid, Pro upgrade section, testimonials
* Version bump from 1.6.7 to 2.0.1 marks the plugin rename milestone (XtremeLeads → Xtreme Forms)

= 1.6.0 =
* Added full import/export with JSON round-trip (export all forms + leads, re-import on any site)
* Added multisite support — per-site tables, network-aware activation, new blog provisioning
* Added append-only audit log for all admin actions
* Improved settings page organization with tabbed layout

= 1.5.0 =
* Added webhooks with delivery logging and configurable retry logic
* Added GDPR consent checkbox (per form), right-to-erasure data deletion, configurable data retention
* Added spam protection: honeypot, time-gate, reCAPTCHA v3, keyword blocklist

= 1.4.0 =
* Added analytics dashboard: submission trends, lead source breakdown, top forms, conversion rates
* Added UTM parameter capture and storage with each lead submission
* Added duplicate detection: configurable by email/phone within a time window, with admin override

= 1.3.0 =
* Added email templates with merge tags ({{first_name}}, {{form_name}}, {{site_name}}, etc.)
* Added email routing rules: route notifications to different recipients based on field values
* Added email log: full record of all outbound emails per lead

= 1.2.0 =
* Added lead activity timeline — per-lead event history
* Added internal notes on leads
* Added tag management for filtering and organizing leads
* Improved lead inbox with bulk status updates

= 1.1.0 =
* Added auto-responder: branded confirmation email to submitter on capture
* Added lead status workflow: new, read, contacted, converted, archived, spam
* Added per-field conditional logic in form builder
* Improved admin UI

= 1.0.0 =
* Initial production release
* Drag-and-drop form builder with 10 field types
* Lead capture database with searchable inbox
* Email notifications on submission
* Shortcode `[xtreme_forms id="X"]` and Gutenberg block
* Clean uninstall — removes all tables and options

== Upgrade Notice ==

= 2.0.5 =
WordPress.org submission prep + layout/UX fixes. Safe to upgrade from any 2.x version — no database changes.

= 2.0.3 =
Stable release. Safe to upgrade from any 1.x or 2.0.x version — no database changes.

= 2.0.1 =
Plugin renamed from XtremeLeads to Xtreme Forms. Deactivate and delete XtremeLeads before installing if upgrading from the old plugin slug.
