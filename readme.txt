=== Xtreme Forms ===
Contributors: loanpartnership
Tags: lead capture, contact form, leads, webhooks, analytics
Tested up to: 6.9
Stable tag: 2.2.1
Requires at least: 6.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lead capture forms with email routing, webhooks, analytics, spam protection, and GDPR tools.

== Description ==

Xtreme Forms is a WordPress lead capture and management plugin. Build custom forms, capture leads into your own database, route notifications to the right team members, track analytics, and use the included GDPR tools (consent checkbox, right-to-erasure, data retention) to help support your own privacy workflows — all from a clean, fast admin interface.

Every feature listed below ships in this plugin and is fully functional. There is no license, trial, or paywall — the plugin you download is the complete plugin.

= Features =

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
* **CRM Integrations** — HubSpot, Pipedrive, Zoho, and Salesforce. Configure once under **Automations → Integrations**; new leads are forwarded automatically. All integrations are opt-in.

== Installation ==

1. Download `xtreme-forms.zip`
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Select the zip file and click **Install Now**
4. Activate the plugin
5. Go to **Xtreme Forms → Forms** to build your first form
6. Embed it with `[xtreme_forms id="X"]` or the Gutenberg block

Alternatively, unzip the archive and upload the `xtreme-forms` folder to `/wp-content/plugins/`, then activate from the Plugins screen.

== Frequently Asked Questions ==

= Is this plugin really free? =

Yes. There are no license keys, no usage limits, no time limits, and no paywalled features. Every feature in the plugin works on every install.

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

Please use the WordPress.org support forum for this plugin.

== External services ==

This plugin can connect to several third-party services. Every service listed below is **opt-in** — none are contacted unless a site administrator explicitly enables and configures them. The default install of the plugin does not contact any external service.

= Google reCAPTCHA v3 (optional spam protection) =

If a site administrator enables reCAPTCHA in **Xtreme Forms → Settings → Spam Protection** and provides their own Site Key and Secret Key, the plugin will:

* Load the reCAPTCHA JavaScript from `https://www.google.com/recaptcha/api.js` on every page where a form is rendered.
* Generate an anonymous reCAPTCHA token in the visitor's browser when the form is submitted.
* Send that token (plus the site's secret key) to `https://www.google.com/recaptcha/api/siteverify` from your server to verify the submission is not spam. No form-field data is transmitted.

Service provider: Google. [Terms of Service](https://policies.google.com/terms) · [Privacy Policy](https://policies.google.com/privacy)

= Cloudflare Turnstile (optional spam protection) =

If a site administrator enables Turnstile in **Xtreme Forms → Settings → Spam Protection** and provides their own Site Key and Secret Key, the plugin will:

* Load the Turnstile JavaScript from `https://challenges.cloudflare.com/turnstile/v0/api.js` on every page where a form is rendered.
* Generate an anonymous Turnstile token in the visitor's browser when the form is submitted.
* Send that token (plus the site's secret key) to `https://challenges.cloudflare.com/turnstile/v0/siteverify` from your server to verify the submission is not spam. No form-field data is transmitted.

Service provider: Cloudflare, Inc. [Terms of Service](https://www.cloudflare.com/website-terms/) · [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

= HubSpot CRM (optional lead forwarding) =

If a site administrator enables the HubSpot integration in **Xtreme Forms → Automations → Integrations** and supplies a HubSpot Private App access token, then on every form submission the plugin sends the submitted lead's contact data (e.g. name, email, phone, plus any custom fields the admin maps) to `https://api.hubapi.com/crm/v3/objects/contacts`.

Service provider: HubSpot, Inc. [Terms of Service](https://legal.hubspot.com/terms-of-service) · [Privacy Policy](https://legal.hubspot.com/privacy-policy)

= Pipedrive CRM (optional lead forwarding) =

If a site administrator enables the Pipedrive integration in **Xtreme Forms → Automations → Integrations** and supplies a Pipedrive API token, then on every form submission the plugin sends the submitted lead's contact data (e.g. name, email, phone, plus any custom fields the admin maps) to `https://api.pipedrive.com/v1/persons` and `https://api.pipedrive.com/v1/leads`. The token is also validated once at save-time against `https://api.pipedrive.com/v1/users/me`.

Service provider: Pipedrive Inc. [Terms of Service](https://www.pipedrive.com/en/terms-of-service) · [Privacy Policy](https://www.pipedrive.com/en/privacy)

= Zoho CRM (optional lead forwarding) =

If a site administrator enables the Zoho integration in **Xtreme Forms → Automations → Integrations** and completes the Zoho OAuth handshake (providing client ID, client secret, refresh token, and data-center region), then on every form submission the plugin will:

* Refresh the Zoho OAuth access token by calling `https://accounts.zoho.<region>/oauth/v2/token` (where `<region>` is the data-center suffix the admin configured, e.g. `com`, `eu`, `in`).
* Send the submitted lead's contact data (e.g. name, email, phone, plus any custom fields the admin maps) to `https://www.zohoapis.<region>/crm/v2/Leads`.

Service provider: Zoho Corporation. [Terms of Service](https://www.zoho.com/terms.html) · [Privacy Policy](https://www.zoho.com/privacy.html)

= Salesforce CRM (optional lead forwarding) =

If a site administrator enables the Salesforce integration in **Xtreme Forms → Automations → Integrations** and completes the Salesforce OAuth handshake (providing the consumer key/secret and instance URL), then on every form submission the plugin sends the submitted lead's contact data (e.g. name, email, phone, plus any custom fields the admin maps) to `<instance_url>/services/data/v57.0/sobjects/Lead/` (where `<instance_url>` is the Salesforce instance URL the admin configured, typically of the form `https://<your-org>.my.salesforce.com`).

Service provider: Salesforce, Inc. [Terms of Service](https://www.salesforce.com/company/legal/sfdc-website-terms-of-service/) · [Privacy Policy](https://www.salesforce.com/company/privacy/)

= Webhooks (optional lead forwarding) =

If a site administrator configures one or more webhook URLs in **Xtreme Forms → Automations → Webhooks**, the plugin will POST submitted form data (the field values, plus optional metadata such as form ID, submission timestamp, and source URL) to each configured URL. The destination is fully controlled by the site administrator.

== Screenshots ==

1. Frontend form — published lead capture form on a live site with GDPR consent checkbox and custom-styled submit button
2. Form builder — drag-and-drop field palette (textbox, dropdown, date picker, file upload, zip code, slider, etc.) with live canvas preview and editable submit button
3. Lead detail — submitted data, lead metadata (source URL, IP, user agent, GDPR consent), status + assignment controls, tags, and notes timeline
4. Automations — email templates with logo, header color, merge-tag support for subject/body/footer; tabs for Routing Rules, Webhooks, and Integrations
5. Analytics dashboard — all-time / monthly / weekly totals, leads-over-time chart, leads-by-form breakdown, conversion funnel, top source pages, and top performing forms

== Changelog ==

= 2.2.1 =
* WordPress.org review round 3 — activation / DB migration fixes.
* **No more raw `ALTER TABLE` queries** (`includes/class-xf-activator.php`) — the four columns previously added by conditional `ALTER TABLE ADD COLUMN` statements (`activate_at`, `expire_at`, `closed_message` on the forms table; `consent_given` on the leads table) are now declared inline in the `CREATE TABLE` strings, so all schema changes flow through `dbDelta()`. On existing installs `dbDelta` emits the same ALTER itself, idempotently. Resolves "plugin repeatedly tries to create existing tables/columns" reported by reviewers.
* **`maybe_upgrade()` is now an option-read fast path** — on a fully-migrated site it returns after a single `get_option()` + `version_compare()`, with no schema queries at all. Adds a per-request re-entrancy guard so `plugins_loaded` firing more than once cannot double-fire `dbDelta`.
* **`PRIMARY KEY` uses two spaces** in every `CREATE TABLE` string (per `dbDelta` documentation) — eliminates dbDelta misparses that could re-emit `ADD PRIMARY KEY` on each run.
* **wpdb errors are silenced around `dbDelta`** with `hide_errors()` + `suppress_errors()`, restored on exit — activation/upgrade never prints DB notices to the admin even if a host returns a benign warning.

= 2.2.0 =
* WordPress.org review round 2 — full compliance pass.
* **Trialware (Guideline 5)** — removed every "Pro Add-On" / "Upgrade to Pro" upsell from `readme.txt`, the in-admin Welcome page, and `README.md`. The features that were advertised as Pro (CRM integrations, webhook retry queue, delivery log) are already implemented in this codebase, so advertising them as locked was non-compliant. The plugin is now declared free and fully functional everywhere.
* **External services** — added Salesforce CRM disclosure to the `== External services ==` section (was previously missing). All six third-party endpoints (Google reCAPTCHA, Cloudflare Turnstile, HubSpot, Pipedrive, Zoho, Salesforce) plus user-configured webhooks are now documented with what data is sent, when, and links to each provider's Terms of Service + Privacy Policy.
* **Nonces visible to static analysis** — added explicit `check_ajax_referer()` at the top of every public AJAX handler in `XF_Ajax` (28 added, alongside the existing helper-based verification that the static checker couldn't follow). Filter-form GET reads in `xf-admin-spam-log.php`, `xf-admin-email-log.php`, and `xf-admin-settings.php` now go through `wp_verify_nonce()` on a `_xf_nonce` field added to each `<form method="get">`. Notice-trigger redirects in `class-xf-admin.php` and `class-xf-multisite.php` now attach a nonce via `wp_nonce_url()`.
* **Prefix `xl_` removed** — every legacy `xl_` AJAX action registration deleted (31 of them). `xl` was a 2-character prefix, below the 4-character minimum. The legacy aliases were not used by any current client.
* **Prefix `xf_` → `xtremeforms_`** — every WordPress identifier 2-character prefix renamed: `wp_ajax_xf_*` action hooks, nonce action names (`xf_admin_nonce` → `xtremeforms_admin_nonce`, plus 10+ others), `admin_post_xf_*` handlers, transient keys, cron hooks (`xf_gdpr_retention_purge` → `xtremeforms_gdpr_retention_purge`, `xf_webhook_retry` → `xtremeforms_webhook_retry`), the hidden `xf-welcome` menu slug, all `wp_register_script`/`style` handles (`xf-admin` → `xtremeforms-admin`, `xf-builder` → `xtremeforms-builder`, etc.), and the corresponding `wp_localize_script` JS globals (`xfAdminData` → `xtremeFormsAdminData`, `xfBuilderData` → `xtremeFormsBuilderData`, etc.). All consumer JS files updated to read from the new global names.
* **SQL hardening — UTM column query** (`includes/class-xf-analytics.php`) — replaced the dynamic `{$utm_column}` interpolation with a `switch` block that dispatches to one of five fully-static prepared queries (one per allowed column). Eliminates any column-name interpolation.
* **SQL hardening — lead INSERT** (`includes/class-xf-leads.php`) — added an explicit hardcoded whitelist of column names (matching the schema in `XF_Activator::create_tables()`). Each `$row` key is validated against the whitelist before being added to the INSERT, so the column list is always assembled from compile-time constants.

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

= 2.2.1 =
Fixes the activation / DB migration warnings reported in the WordPress.org review round 3. All schema changes now flow through `dbDelta()` (no more raw `ALTER TABLE` queries), and the upgrade check is a single option read on a fully-migrated site. Safe to upgrade — no destructive schema changes.

= 2.2.0 =
WordPress.org review round 2 — full compliance pass. Removed all Pro upsells (the plugin is now fully free), added Salesforce to External Services, added explicit nonces, renamed 2-character `xl_` and `xf_` prefixes to `xtremeforms_`, and hardened two SQL queries. Safe to upgrade — no database schema changes.

= 2.0.5 =
WordPress.org submission prep + layout/UX fixes. Safe to upgrade from any 2.x version — no database changes.

= 2.0.3 =
Stable release. Safe to upgrade from any 1.x or 2.0.x version — no database changes.

= 2.0.1 =
Plugin renamed from XtremeLeads to Xtreme Forms. Deactivate and delete XtremeLeads before installing if upgrading from the old plugin slug.
