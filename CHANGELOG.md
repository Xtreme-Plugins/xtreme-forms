# Changelog

All notable changes to XtremeLeads are documented here.

## [1.6.5] - 2026-04-07

### Fixed
- PHPCS compliance: wrapped all remaining `$wpdb->` calls in `phpcs:disable/enable` blocks covering `InterpolatedNotPrepared`, `DirectQuery`, `NoCaching`, `NotPrepared`, and `UnescapedDBParameter` rules across `class-xl-activity.php`, `class-xl-notes.php`, `class-xl-duplicates.php`, `class-xl-import-export.php`, and `class-xl-routing-rules.php`
- PHPCS compliance: replaced trailing inline `phpcs:ignore` comments on multi-line `$wpdb->` call blocks with correct disable/enable block pairs
- PHPCS compliance: added `phpcs:disable WordPress.Security.NonceVerification` to read-only admin display partials (`xl-admin-dashboard.php`, `xl-admin-network-dashboard.php`, `xl-admin-network-settings.php`)

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
- Shortcode `[xtremeleads id="X"]` for embedding forms in any page, post, or widget
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
