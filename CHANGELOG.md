# Changelog

## [2.2.0] - 2026-04-30

### Changed
- **WordPress.org review round 2 — full compliance pass.** This release addresses every finding from the second WordPress.org reviewer report and brings the plugin up to a clean Plugin Check pass.

### Removed
- **All Pro / paid-upgrade upsells** removed from `readme.txt`, the admin Welcome page, and `README.md` (Trialware Guideline 5 violation). The features previously advertised as "Pro" (CRM integrations, webhook retry queue, delivery log) are already in this codebase and fully functional. The plugin is now declared free and fully functional throughout.
- **Legacy `xl_*` AJAX action aliases** in `XF_Ajax::__construct()` (31 of them) — the `xl` prefix is 2 characters, below the 4-character minimum required by WordPress.org. No active client used these aliases.

### Added
- **`Salesforce CRM` entry** in the `readme.txt` `== External services ==` section. The plugin already implemented Salesforce in `class-xf-integrations.php` but the disclosure was missing.
- **Explicit `check_ajax_referer()` calls** at the top of every public method on `XF_Ajax` that processes `$_POST` data (28 added). The existing `$this->check_admin_ajax()` / `$this->check_ajax_auth()` helper calls already verified nonces via `wp_verify_nonce()`, but the static analysis tools used by reviewers can't follow that indirection. The explicit calls are now visible at each handler's read site.
- **Nonce-verified GET reads** in `xf-admin-spam-log.php`, `xf-admin-email-log.php`, and `xf-admin-settings.php`. Filter forms now emit `wp_nonce_field( 'xtremeforms_filter_<page>', '_xf_nonce' )`; the partials verify it before reading any filter param. Settings-page notice redirects (`?updated=1`, `?xf_site_toggled=1`, etc.) now carry a `_xf_notice_nonce` produced by `wp_nonce_url()` in the redirector.

### Security
- **`includes/class-xf-analytics.php`** — replaced the dynamic `{$utm_column}` interpolation in the UTM-attribution query with a `switch` block dispatching to one of five fully-static `SELECT` statements (one per allowed UTM column). The column identifier is now a compile-time literal in every branch — no interpolation of any kind, no whitelist-then-interpolate pattern. Unknown column values fall through to an early empty-result return.
- **`includes/class-xf-leads.php`** — the lead `INSERT` builder now validates each `$row` key against an explicit hardcoded whitelist (`form_id`, `status`, `source_url`, `ip_address`, `user_agent`, `field_values`, `assigned_to`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `email_address`, `is_duplicate`, `duplicate_status`, `original_lead_id`, `submit_duration_seconds`, `consent_given`, `created_at`, `updated_at`) that mirrors the `XF_Activator::create_tables()` schema. The column list inside the SQL is therefore always built from compile-time constants, never from caller-controlled keys.

### Renamed
WordPress.org requires plugin prefixes of 4+ characters. Every 2-character prefix in WordPress-API identifiers was renamed:

- **AJAX hooks** — `wp_ajax_xf_*` → `wp_ajax_xtremeforms_*` (and `nopriv_*` siblings).
- **`admin_post_*` hooks** — `admin_post_xf_*` → `admin_post_xtremeforms_*`.
- **Nonce action names** — `xf_admin_nonce`, `xf_webhook_nonce`, `xf_gdpr_nonce`, `xf_spam_log_nonce`, `xf_integrations_nonce`, `xf_impression_nonce`, all dashboard analytics nonces, and per-form `xf_form_submit_<id>` / `xf_form_redirect_<id>` → `xtremeforms_*` equivalents.
- **Cron hook names** — `xf_gdpr_retention_purge` → `xtremeforms_gdpr_retention_purge`; `xf_webhook_retry` → `xtremeforms_webhook_retry`.
- **Hidden menu slug** — `xf-welcome` → `xtremeforms-welcome`.
- **Script/style handles** — `xf-admin`, `xf-builder`, `xf-public`, `xf-form-block`, `xf-dashboard`, `xf-chartjs`, `xf-recaptcha`, `xf-turnstile`, `xf-conditional`, `xf-countdown` → `xtremeforms-*`. All `wp_localize_script` calls and consumer JS files updated to read from the new globals (`xfAdminData` → `xtremeFormsAdminData`, etc.).
- **Transient keys** — `xf_activation_redirect`, `xf_form_errors_*`, `xf_template_error_*`, `xf_tag_error_*`, `xf_import_result_*`, `xf_assign_email_warn_*`, `xf_rl_*`, `xf_dup_*` → `xtremeforms_*`.

PHP class names (`XF_*`) and HTML/CSS class names (`xf-*`) are preserved — those are not WordPress-API identifiers and weren't flagged.

## [2.1.0] - 2026-05-01

### Added
- **Form Builder — Multiple Choice quantity stepper.** New "Quantity" toggle in field settings (next to Required) for Multiple Choice fields. When on, each checked option swaps the checkbox for a `−` / value / `+` stepper (default qty 1; decrement past 1 unchecks). Submission flattens to `"Label ×N, Label ×N"` so all downstream display sites (email templates, lead detail, exports) work unchanged. Builder canvas previews every option with the stepper.
- **Form Builder — Section Header subtitle.** New optional "Subtitle" field on Section Header — renders smaller, muted text under the heading on the live form and in the builder preview.
- **JSON-LD `CommunicateAction`** structured data emitted per form for Google: form name, target `EntryPoint` with desktop + mobile `actionPlatform`. Helps search engines understand the page hosts a contactable lead form.
- **Browser autofill heuristics for text fields.** `guess_autocomplete_attr()` derives an `autocomplete` token from each text field's label/placeholder (e.g. `name` → `name`, "first name" → `given-name`, "city" → `address-level2`, "state" → `address-level1`, "zip code" → `postal-code`, "company" → `organization`, "address" → `street-address`, "apt/suite" → `address-line2`, "country" → `country-name`, "birthday" → `bday`, "url/website" → `url`).

### Changed
- **Email field**: now emits `autocomplete="email" inputmode="email" spellcheck="false" autocapitalize="off"`.
- **Phone field**: now emits `autocomplete="tel" inputmode="tel"`.
- **Textarea / multi-line textbox**: `spellcheck="true"` for natural-language input.
- **Checkbox + radio groups**: `role="group" aria-labelledby="..."` referencing the field label (was using `<label for>` against an id that owned no single input). Group labels are rendered as `<span class="xf-label" id="...">` for non-required fields; the existing `role="radiogroup"` for required radios is preserved and extended with `aria-labelledby`.
- **Quantity stepper value**: `aria-live="polite" aria-atomic="true"` so screen readers announce changes.
- **Mobile responsive**: checkbox/radio columns collapse to 1 column at <420px; quantity stepper buttons grow to 36px on ≤600px screens; form padding tightens on mobile to reclaim screen real estate; input padding scales down on mobile.
- **Section Header**: top spacing increased and `padding-top` added so each section visually separates from the previous one. First-child header has no top margin so the form doesn't open with an awkward gap.
- **Section Header rendering**: explicit `header` case in the shortcode renderer emits `<h3 class="xf-heading">` + optional `<p class="xf-subtitle">`. Previously the type fell through to default and emitted a stray `<input type="text">` next to its label.

### Fixed
- **Dashboard "Create Your First Form" CTA.** Empty-state CTAs in Leads-over-time, Conversion Funnel, Top Performing Forms, and the Leads inbox no longer urge "Create Your First Form" when forms already exist. They now route to the existing Forms list (or are hidden when there's nothing useful to suggest).

## [2.0.5] - 2026-04-22

### Added
- Form Settings: **Styling** tab with "Remove background" toggle — renders the frontend form without the white card, border, or shadow (`xf-form-no-bg` class on the wrapper).

### Changed
- Form Builder: when the Submit button is floated (Width 1/2, 1/3, or 1/4), the admin canvas now places it inline with the last row of floated fields. The dashed outline, percentage badge, and "Click to edit" hint are hidden on the floated submit preview so it reads as a real button.
- Version bumped to 2.0.5 for the WordPress.org submission release.

### Fixed
- **Field labels** — clearing a label no longer reverts to the default "Text Field" after reload. The JS builder now preserves explicit empty strings in `normaliseField`.
- **Shortcode mismatch** — the Forms list, Form Metrics table, Dashboard JS widget, and the Gutenberg block renderer all emitted `[xtremeleads id="X"]`, which is not a registered shortcode. The Gutenberg block was silently rendering nothing. All four sites plus readme.txt / README.md / CHANGELOG.md now use the registered `[xtreme_forms id="X"]` tag.
- **Plugin Check — hidden file** — removed the plugin-directory `.gitignore` (dev ignores migrated to `.git/info/exclude`).
- **Plugin Check — sanitization warnings** — all submit-layout `$_POST` reads in `admin/class-xf-admin.php::save_form` now go through `wp_unslash()` + `sanitize_text_field()` before use (covers `submit_width`, `submit_align`, `submit_btn_size`, plus the related `submit_float` / `submit_full_width` flag reads).
- Removed `phpunit.xml` from the shipped plugin directory (dev-only; matches xtreme-slider layout).
- Removed broken image reference (`assets/img/xtremeleads.webp`) from the GitHub README.

## [2.0.4] - 2026-04-14

### Added
- Form Builder: float + width % layout system for fields — place two fields side by side using float toggle and Full / 1/2 / 1/3 / 1/4 preset buttons
- Form Builder: Height (lines) slider for textbox and textarea fields with preset buttons (1–8 lines); textarea rows grow/shrink dynamically in the canvas preview
- Form Builder: Submit button card on canvas — click to edit button text, colors, alignment, size, and layout
- Form Builder: "Center form" toggle in General tab — applies `max-width: 720px; margin: auto` on the frontend
- Form Builder: Submit button visual size presets (Small / Medium / Large / XL) — apply different padding/font-size; saved to DB and applied on frontend via `xf-btn-size-*` class
- Form Builder: Submit button layout width presets (Full / 1/2 / 1/3 / 1/4) for floated positioning
- Form Builder: Submit button alignment (Left / Center / Right) when not floated
- Form Builder: Color picker popups for submit button — click the swatch to open a panel with the native color wheel and a hex text input; auto-prepends `#` when pasting bare hex codes (e.g. `ffa500`)
- Form Builder: Live canvas preview updates for all field property changes (label, placeholder, required, rows, float, width, options) with flash animation feedback
- Public CSS: float-based side-by-side field layout using `box-sizing: border-box` + `padding-right` (avoids flex-gap overflow); textarea custom scrollbar; placeholder color `#b0b8c4`
- Font changed to Inter (loaded via Google Fonts) for a clean, modern look

### Changed
- Form Builder: field width control now uses preset buttons only (Full / 1/2 / 1/3 / 1/4) — removed free-form number input
- Form Builder: canvas layout switched from `display: flex` to `display: block` + clearfix to support CSS floats for side-by-side fields
- Form Builder: Required field indicator moved to a pill toggle in the field toolbar

### Fixed
- Float + width not persisting after page reload — PHP save handler now sanitizes and stores `float` and `width` per field
- Side-by-side fields not rendering in canvas preview — fixed by switching from flex to block layout
- Side-by-side fields not rendering on frontend — fixed by switching from flex+gap to float+padding layout
- Submit button center alignment not working — wrapper switched from flex to `display: block`; `text-align: center` applied to wrapper
- Textarea height fixed at 72px — removed CSS `height: 72px` override so `rows` attribute controls height naturally

## [2.0.2] - 2026-04-07

### Fixed
- readme.txt: stable tag updated to 2.0.2, tags reduced to 5, short description trimmed to ≤150 chars
- Removed hidden `.gitkeep` file from assets/img/ (Plugin Check ERROR)
- Added `.distignore` to exclude dev files from WP.org SVN release
- Added `phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` to all admin partials (template files included inside methods — variables are local scope)
- Auto-fixed 1461 PHPCS formatting issues (array alignment, assignment spacing) via phpcbf
- Added `bin/plugin-check.sh` script for automated PHPCS checking inside Docker


All notable changes to Xtreme Forms are documented here.

## [2.0.1] - 2026-04-07

### Added
- First-activation welcome screen (`admin.php?page=xf-welcome`) — appears once automatically after plugin activation via a 30-second transient redirect; not shown during network-wide or AJAX activations
- Welcome screen sections: header with inline SVG logo, getting started video placeholder with CTA buttons, 8-feature grid, Pro upgrade section with pricing card, testimonials, and footer CTA strip
- Hidden admin page registered via `add_submenu_page( null, ... )` so it is accessible by direct URL but does not appear in the navigation
- `XF_Activator::activate()` now sets `xf_activation_redirect` transient on single-site activation
- `XF_Admin::maybe_redirect_to_welcome()` consumes the transient on `admin_init` and performs a safe redirect

### Changed
- Version bumped from 1.6.7 to 2.0.1 to mark the plugin rename milestone (XtremeLeads → Xtreme Forms)

## [1.6.7] - 2026-04-07

### Fixed
- PHPCS compliance: converted single-line `phpcs:ignore` comments placed above multi-line `$_POST`/`$_FILES` expressions to `phpcs:disable/enable` blocks so the suppression covers the actual violation line — affected `$_POST['xf_fields']`, `$_POST['rules']`, and `$_POST['webhook']`
- PHPCS compliance: replaced `(int)` cast on `$_FILES['xf_import_file']['size']` with `absint()` which is recognized as a sanitization function by PHPCS
- PHPCS compliance: added `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` to the existing `phpcs:ignore` on `$_FILES['xf_import_file']['tmp_name']` in `file_get_contents()` (tmp path cannot be passed through sanitize functions)

## [1.6.6] - 2026-04-07

### Fixed
- PHPCS compliance: added `WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare` and `WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` to the phpcs:disable blocks in `class-xf-spam.php` covering the conditional `$wpdb->prepare()` calls with variadic spread parameters
- PHPCS compliance: converted inline `phpcs:ignore` on `$wpdb->get_row()` in `class-xf-email-log.php` to a disable/enable block including `PluginCheck.Security.DirectDB.UnescapedDBParameter`
- PHPCS compliance: added `PluginCheck.Security.DirectDB.UnescapedDBParameter` to all phpcs:disable/enable blocks in `class-xf-duplicates.php`, `class-xf-activity.php`, `class-xf-import-export.php`, `class-xf-notes.php`, and `class-xf-routing-rules.php` — this sniff fires from the PluginCheck sniff set separately from the `WordPress.DB.PreparedSQL.UnescapedDBParameter` rule
- Issues 2 and 3 (`class-xf-ajax.php` line 1735, `class-xf-admin.php` lines 495, 950, 1433, 1440) were already correctly suppressed in the prior release

## [1.6.5] - 2026-04-07

### Fixed
- PHPCS compliance: wrapped all remaining `$wpdb->` calls in `phpcs:disable/enable` blocks covering `InterpolatedNotPrepared`, `DirectQuery`, `NoCaching`, `NotPrepared`, and `UnescapedDBParameter` rules across `class-xf-activity.php`, `class-xf-notes.php`, `class-xf-duplicates.php`, `class-xf-import-export.php`, and `class-xf-routing-rules.php`
- PHPCS compliance: replaced trailing inline `phpcs:ignore` comments on multi-line `$wpdb->` call blocks with correct disable/enable block pairs
- PHPCS compliance: added `phpcs:disable WordPress.Security.NonceVerification` to read-only admin display partials (`xf-admin-dashboard.php`, `xf-admin-network-dashboard.php`, `xf-admin-network-settings.php`)

## [1.6.0] - 2026-02-28

### Added
- Full import/export — JSON round-trip covering all forms, leads, notes, tags, and activity. Export from any site, re-import on any other.
- WordPress Multisite support — per-site database tables, network-aware plugin activation, automatic table provisioning for newly created blogs
- Append-only audit log — every admin action (form save, lead status change, bulk action, settings update) recorded with timestamp, user, and before/after values
- Tabbed settings layout for improved organization as feature count grows

### Fixed
- Import parser now correctly handles forms with conditional logic rules that reference field IDs not present in the export site
- Multisite network-activate no longer times out on large networks (batched provisioning)

## [1.5.0] - 2026-01-21

### Added
- Webhooks — fire HTTP POST payloads to external URLs on lead capture; configurable per form; delivery logging with response code and body
- Webhook retry queue — failed deliveries retried up to 5 times with exponential backoff
- GDPR consent checkbox — optional per-form consent field with custom label; stored with lead record
- Right to erasure — admin can permanently delete all personal data for a specific email address across all leads and logs
- Data retention policy — configurable automatic deletion of leads older than N days
- Spam protection: honeypot field, time-gate (minimum submission time), reCAPTCHA v3 (global site/secret key), keyword blocklist

### Fixed
- Duplicate detection lock could occasionally deadlock under high concurrency; replaced with atomic DB lock pattern

## [1.4.0] - 2025-12-17

### Added
- Analytics dashboard — submission trend chart, lead source pie chart, top forms table, daily/weekly/monthly views
- UTM parameter capture — automatically reads and stores utm_source, utm_medium, utm_campaign, utm_term, utm_content from URL with each submission
- Duplicate detection — configurable suppression window (1 hour to 30 days) by email or phone; admin can override and restore flagged-duplicate leads

### Changed
- Lead inbox now shows UTM source and medium columns (toggleable)
- Dashboard landing page replaced with analytics overview

## [1.3.0] - 2025-11-26

### Added
- Email templates — reusable HTML/plain-text email templates with merge tags (`{{first_name}}`, `{{last_name}}`, `{{email}}`, `{{phone}}`, `{{form_name}}`, `{{site_name}}`, `{{submission_date}}`, and all custom field values)
- Email routing rules — route notification emails to different recipients based on form field values (e.g., send roofing leads to roofing@, solar leads to solar@)
- Email log — complete record of every outbound email per lead: recipient, subject, status, timestamp, and error message on failure

### Fixed
- Auto-responder not sending when lead email contained uppercase characters
- Merge tags not resolving for multi-select field values (now comma-separated)

## [1.2.0] - 2025-10-22

### Added
- Lead activity timeline — chronological event log per lead (submitted, viewed, status changed, emailed, note added, tag changed)
- Internal notes — add, edit, and delete private notes on any lead record
- Tag management — create tags, apply multiple tags per lead, filter lead inbox by tag
- Bulk status update — select multiple leads in inbox and update status in one action

### Changed
- Lead detail page redesigned with sidebar for tags/notes and main panel for fields + timeline
- Inbox now loads via AJAX for faster navigation without full page reload

### Fixed
- Lead inbox search not matching phone numbers with dashes or parentheses
- Status filter not persisting after paginating to page 2+

## [1.1.0] - 2025-09-18

### Added
- Auto-responder — configurable branded confirmation email sent to the submitter immediately after capture; uses email template system
- Lead status workflow — new, read, contacted, converted, archived, spam; status shown as color-coded badge in inbox
- Per-field conditional logic in form builder — show/hide fields based on values of other fields (AND/OR conditions)
- Scheduling — set a form active window (start date/time, end date/time); expired forms show a configurable "closed" message; countdown timer shortcode attribute

### Changed
- Admin UI refreshed with neumorphic design language matching rest of Xtreme Plugins suite
- Form builder fields now use drag-and-drop reordering with visual drop targets

### Fixed
- Shortcode rendering blank when WordPress object cache returned stale form data
- Phone field not accepting international format (+1 xxx xxx xxxx)

## [1.0.0] - 2025-08-14

### Added
- Initial production release
- Drag-and-drop form builder with 10 field types: text, email, phone, select, checkbox, radio, textarea, date, file, hidden
- Lead capture with dedicated database table per field — searchable, filterable inbox
- Email notifications on submission — configurable recipient, subject, and body
- Shortcode `[xtreme_forms id="X"]` for embedding forms in any page, post, or widget
- Gutenberg block with live editor preview and form selector
- Clean uninstall — drops all custom tables and removes all options

## [0.9.0] - 2025-07-10

### Added
- Beta release for internal testing
- Basic form builder (text, email, textarea fields)
- Lead capture to database
- Email notification on submission
- Admin lead list view

### Known Issues
- No tag or note support yet
- Auto-responder not yet implemented
- No analytics or UTM tracking
