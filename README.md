<p align="center">
  <img src="assets/img/xtremeleads.webp" alt="XtremeLeads" width="400">
</p>

<h1 align="center">XtremeLeads</h1>
<p align="center">A powerful, free WordPress lead capture plugin with form builder, email routing, webhooks, analytics, GDPR tools, and multisite support.</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-1.6.0-blue" alt="Version 1.6.0">
  <img src="https://img.shields.io/badge/license-GPL--2.0-green" alt="GPL-2.0">
  <img src="https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress" alt="WordPress 6.0+">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-purple?logo=php" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/price-free%20%2B%20pro-brightgreen" alt="Free + Pro">
</p>

<p align="center">
  <a href="https://xtremeplugins.com/plugins/xtreme-leads"><img src="assets/img/btn-download.svg" alt="Download Free" height="50"></a>
  &nbsp;&nbsp;
  <a href="https://xtremeplugins.com/plugins/xtreme-leads/pro"><img src="assets/img/btn-upgrade.svg" alt="Upgrade to Pro" height="50"></a>
</p>

---

## Features

### Core (Free)

- **Form Builder** — 10 field types (text, email, phone, select, checkbox, radio, textarea, date, file, hidden) with drag-and-drop reordering and per-field conditional logic
- **Lead Inbox** — searchable, filterable lead list with status workflow (new → contacted → converted), bulk actions, and tag filtering
- **Email Notifications** — route notifications to different recipients based on field values; configurable subject and body with merge tags
- **Auto-Responder** — branded confirmation email to the submitter immediately on capture
- **Email Templates** — reusable templates with merge tags: `{{first_name}}`, `{{email}}`, `{{form_name}}`, all custom fields
- **Webhooks** — fire HTTP POST payloads to external endpoints on lead capture; delivery logging with retry on failure
- **Analytics** — submission trend chart, lead source breakdown, top forms, daily/weekly/monthly views
- **UTM Tracking** — capture and store utm_source, utm_medium, utm_campaign, utm_term, utm_content with every lead
- **Duplicate Detection** — configurable suppression window by email or phone; admin override available
- **Spam Protection** — honeypot, time-gate, reCAPTCHA v3, keyword blocklist
- **GDPR** — consent checkbox, right to erasure, configurable data retention
- **Activity Timeline** — per-lead event history (submitted, viewed, status change, email sent, note, tag)
- **Notes & Tags** — internal notes and custom tags on every lead
- **Audit Log** — append-only record of all admin actions
- **Import / Export** — full JSON round-trip; export forms + leads, import to any site
- **Multisite** — per-site tables, network-aware activation
- **Gutenberg Block** — live editor preview with form selector
- **Shortcode** — `[xtremeleads id="X"]` works everywhere

### Pro Upgrade

Available at [xtremeplugins.com/plugins/xtreme-leads/pro](https://xtremeplugins.com/plugins/xtreme-leads/pro):

- Priority routing rules with complex AND/OR conditions
- Webhook retry queue with exponential backoff and delivery dashboard
- Advanced analytics: cohort analysis, lead value tracking
- White-label mode — remove XtremeLeads branding from forms
- Priority email support

---

## Requirements

- WordPress 6.0+
- PHP 8.1+

## Installation

1. Download `xtreme-leads.zip` from [https://xtremeplugins.com/plugins/xtreme-leads](https://xtremeplugins.com/plugins/xtreme-leads)
2. Upload via **Plugins → Add New → Upload Plugin** or unzip to `/wp-content/plugins/`
3. Activate the plugin
4. Go to **XtremeLeads → Forms** to create your first form
5. Embed it with `[xtremeleads id="X"]` or the Gutenberg block

## Compatibility

Works with Elementor, Gutenberg, Classic Editor, and any page builder that supports shortcodes. Multisite compatible.

## License

GPL-2.0-or-later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## Links

- [Plugin Page](https://xtremeplugins.com/plugins/xtreme-leads)
- [Pro Upgrade](https://xtremeplugins.com/plugins/xtreme-leads/pro)
- [Changelog](CHANGELOG.md)
- [Report Issues](https://github.com/Xtreme-Plugins/xtreme-leads/issues)
