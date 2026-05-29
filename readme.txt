=== Xtreme Forms ===
Contributors: loanpartnership, xtremeplugins
Tags: contact form, form builder, lead generation, forms, webhooks
Tested up to: 7.0
Stable tag: 2.5.9
Requires at least: 6.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop contact form & lead capture plugin. Email routing, webhooks, analytics, spam protection, GDPR. Fast, free, no upsells.

== Description ==

**Xtreme Forms** is a modern, drag-and-drop **contact form and lead capture plugin** for WordPress. Build any form you need — contact forms, quote requests, event registrations, newsletter signups, multi-step lead qualification — then capture every submission into a built-in lead inbox, route email notifications to the right person, fire webhooks to your CRM, and track conversions on a clean analytics dashboard.

**No upsells. No "Pro-only" features hidden inside the free download.** Every feature listed below ships in the free plugin and is fully functional out of the box. The optional Pro add-on (sold separately at xtremeplugins.com) only adds advanced routing rules, retry queues, and extended analytics — it never gates or limits any feature you see on this page.

= Why Xtreme Forms? =

If you've used the other popular form plugins (WPForms, Contact Form 7, Gravity Forms, Ninja Forms, Formidable Forms) you already know the trade-offs: free versions lock the features you actually need behind a Pro upgrade, the lighter ones don't manage your leads after the email is sent, and the heavier ones drag down page speed with bloated assets. Xtreme Forms is built differently:

* **Real lead management, not just form-to-email.** Every submission lands in a searchable, filterable inbox with a full status workflow (new → read → contacted → converted), per-lead notes, tags, assignment to team members, and an append-only audit log. Treat your leads like a pipeline, not like inbox clutter.
* **Everything in the free download is actually free.** Drag-and-drop builder, conditional logic, webhooks, analytics, GDPR tools, multisite, audit log, JSON import/export — no popup nag screens, no "upgrade to Pro" overlay on settings pages.
* **Lightweight on the page.** No jQuery dependency on the frontend, no third-party font request, no external service call unless you explicitly enable and configure one.
* **Modern WordPress stack.** Tested up to WordPress 7.0, requires PHP 8.1+, ships a native Gutenberg block alongside the classic shortcode, and works inside any block-theme template / Elementor / Bricks / Beaver Builder widget that accepts shortcodes.
* **WordPress.org-compliant.** Fully GPL, no obfuscated code, no telemetry, no auto-update side-channels, and every external-service touchpoint is disclosed below.

A solid free alternative to **WPForms** for site owners who want lead management built in, not an afterthought.

= Built-in Form Templates =

Skip the blank canvas. Xtreme Forms ships ready-to-go templates for the forms you actually need:

* **Simple Contact Form** — name, email, message
* **Quote Request** — name, email, phone, project description, budget range
* **Event Registration** — full name, email, phone, company, attendee count, date picker
* **Newsletter Signup** — name, email, GDPR consent
* **Multi-step lead qualification** — built using the conditional-logic engine

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

== External services ==

Xtreme Forms is fully self-hosted by default. It only contacts third-party services when a site administrator explicitly enables and configures the corresponding feature. Each service below is opt-in: nothing is sent until you turn it on and provide credentials.

= Google reCAPTCHA v3 (optional spam protection) =

If you enable reCAPTCHA v3 in **Xtreme Forms → Settings → Spam Protection** and enter your site/secret keys, the public-facing form page loads the reCAPTCHA JavaScript from `https://www.google.com/recaptcha/api.js`, which executes in the visitor's browser to generate a token. On submit, the plugin then sends a server-to-server request from your site to `https://www.google.com/recaptcha/api/siteverify` containing the token, your secret key, and the visitor's IP address so Google can return a spam score.

Service provider: Google LLC.
Terms of service: https://policies.google.com/terms
Privacy policy: https://policies.google.com/privacy
reCAPTCHA-specific terms: https://www.google.com/recaptcha/about/

= Cloudflare Turnstile (optional spam protection) =

If you enable Cloudflare Turnstile in **Xtreme Forms → Settings → Spam Protection** and enter your site/secret keys, the public-facing form page loads the Turnstile widget from `https://challenges.cloudflare.com/turnstile/v0/api.js`. On submit, the plugin sends a server-to-server request from your site to `https://challenges.cloudflare.com/turnstile/v0/siteverify` containing the widget token, your secret key, and the visitor's IP address so Cloudflare can validate the challenge.

Service provider: Cloudflare, Inc.
Terms of service: https://www.cloudflare.com/website-terms/
Privacy policy: https://www.cloudflare.com/privacypolicy/

= Zoho CRM (optional integration) =

If you enable the Zoho integration in **Xtreme Forms → Automations → Integrations** and enter your OAuth client ID, client secret, refresh token, and data-center region, then for every new lead capture the plugin makes two server-to-server requests from your site to Zoho:

1. A token exchange request to `https://accounts.zoho.<tld>/oauth/v2/token` (where `<tld>` is `com`, `eu`, `in`, `au`, or `jp` based on the region you select) containing your refresh token, client ID, and client secret.
2. A lead-create request to `https://www.zohoapis.<tld>/crm/v2/Leads` containing the lead's name, email address, phone number, and company name (only the fields that were submitted in the form).

Service provider: Zoho Corporation Pvt. Ltd.
Terms of service: https://www.zoho.com/terms.html
Privacy policy: https://www.zoho.com/privacy.html

= HubSpot CRM (optional integration) =

If you enable the HubSpot integration in **Xtreme Forms → Automations → Integrations** and enter a Private App access token, then for every new lead capture the plugin makes a server-to-server request from your site to `https://api.hubapi.com/crm/v3/objects/contacts` containing the lead's email, first/last name, phone number, and company name (only the fields that were submitted in the form). When you click the **Test** button on the integrations page, the plugin also sends a single `GET` request to the same host to verify the token.

Service provider: HubSpot, Inc.
Terms of service: https://legal.hubspot.com/terms-of-service
Privacy policy: https://legal.hubspot.com/privacy-policy

= Salesforce (optional integration) =

If you enable the Salesforce integration in **Xtreme Forms → Automations → Integrations** and enter your consumer key, consumer secret, instance URL, and access token, then for every new lead capture the plugin makes a server-to-server request from your site to `<your_instance_url>/services/data/v57.0/sobjects/Lead/` containing the lead's last name, email, phone number, and company name (only the fields that were submitted in the form).

Service provider: Salesforce, Inc.
Terms of service: https://www.salesforce.com/company/legal/agreements/
Privacy policy: https://www.salesforce.com/company/privacy/

= Pipedrive (optional integration) =

If you enable the Pipedrive integration in **Xtreme Forms → Automations → Integrations** and enter an API token, then for every new lead capture the plugin makes two server-to-server requests from your site to `https://api.pipedrive.com/v1/persons` and `https://api.pipedrive.com/v1/leads` containing the lead's name, email, and phone number (only the fields that were submitted in the form). The **Test** button sends one `GET` request to `https://api.pipedrive.com/v1/users/me` to verify the token.

Service provider: Pipedrive OÜ.
Terms of service: https://www.pipedrive.com/en/terms-of-service
Privacy policy: https://www.pipedrive.com/en/privacy

= Webhooks (optional, user-defined destination) =

If you create one or more webhooks in **Xtreme Forms → Automations → Webhooks**, the plugin will send the captured lead's data as a JSON `POST` request to the URL(s) you configure for every new lead. These URLs are arbitrary endpoints that you (the site administrator) choose; the plugin itself is not affiliated with any particular webhook destination. Review the terms/privacy policy of whichever service you point your webhooks to.

= Xtreme Plugins licensing API (optional, only for the paid Pro add-on) =

The free Xtreme Forms plugin on WordPress.org is fully functional with no license required. A separately-sold Pro add-on is available from xtremeplugins.com; if (and only if) a site administrator chooses to buy it, the free plugin includes a "License" tab under **Xtreme Forms → Settings → License** that activates the Pro key.

The licensing API is **only contacted when the administrator actively clicks a button on that tab**:

1. Clicking **Activate** sends a server-to-server `POST` from your site to `https://xtremeplugins.com/api/v1/license/activate` containing the license key the admin entered and your site URL (`home_url()`), so the licensing server can validate the key and bind a seat to this site.
2. Clicking **Deactivate** sends a server-to-server `POST` from your site to `https://xtremeplugins.com/api/v1/license/deactivate` containing the same two values, so the licensing server can release the seat.

No request is ever made on form submissions, page loads, or in the background — the endpoints are only hit on the two explicit button clicks above, and only after an administrator has typed in a license key. If you never use the License tab, the plugin never contacts xtremeplugins.com. The endpoint URLs can be overridden (for example to point at a self-hosted licensing server or a staging environment) via the `xtremeforms_license_activate_url` and `xtremeforms_license_deactivate_url` filters.

Service provider: XtremePlugins (xtremeplugins.com).
Terms of service: https://xtremeplugins.com/terms
Privacy policy: https://xtremeplugins.com/privacy

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

= 2.5.9 =
* **Form builder:** the "Click to edit button" hint now sits cleanly *outside* the dashed Submit-card box on the right, with a small curved arrow pointing back into the button. Previously the hint was positioned `right: 14px` inside the box, which clipped or overlapped the button text whenever Width was set to 100%. Hidden automatically on narrow canvases (<980px) so it doesn't push the canvas wider than the form card.
* **Public form:** the four button-size variants (Small / Medium / Large / XL) and the Full-width toggle now win against theme / Elementor / Bricks button rules — the size classes were getting silently overridden by higher-specificity selectors like `.elementor-form button[type="submit"]`. Scoped the variants under `.xf-form-wrap` and marked them `!important` so they consistently produce the same dimensions on every theme.
* **Public form:** Cloudflare Turnstile widget now sits in a flex container with a small `transform: scale(0.92)` and a slight rounding, so it lines up with the form fields instead of floating sloppily off to one side. Min-height is reserved so the layout doesn't jump when the widget finishes loading.

= 2.5.8 =
* **Public form now renders in Manrope** (same as the admin since 2.5.5). The font was previously "Fira Sans" with a system fallback chain. The `@font-face` declarations point at the same `assets/fonts/manrope/` bundle the admin uses, so no second copy of the font files is shipped and no request is made to `fonts.googleapis.com` or `fonts.gstatic.com`. All hardcoded `font-family: "Fira Sans"` declarations in `public/css/xf-public.css` (5 of them) were swapped to `var(--xf-font)` so they inherit the Manrope-first stack.

= 2.5.7 =
* **Public form — phone fields auto-format as `(555) 123-4567`.** As the visitor types digits into a phone field the value live-formats US-style. International numbers (anything beginning with `+`) are left alone, so a `+44 7700 900123` still goes through unchanged. The field placeholder defaults to `(555) 123-4567` only when the form admin hasn't configured a different one.
* **Public form — date fields open the picker on a single click anywhere in the field.** Previously the browser's date picker only opened when the visitor clicked the small calendar icon at the far right of the input. Now clicking anywhere on the field (or pressing Enter/Space when focused) calls `input.showPicker()`. The cursor turns into a pointer and the calendar icon is enlarged + brighter on hover so it actually reads as a target.
* Also added the `Save Form` button white-checkmark fix from the previous local commit (the icon was inheriting the dashicon default blue and was invisible on the blue button).

= 2.5.6 =
* **Form builder — Submit card styling now actually loads.** The Submit-button preview, the "Click to edit button" hint, the width badge, and the dashed outline were all defined in a `<style>` block at the bottom of the form-builder partial that was being attached via `wp_add_inline_style()` *after* the page `<head>` had already shipped the parent stylesheet. On some hosts the inline CSS was being dropped entirely, leaving the Submit row with default browser styling (small blue button, hint sitting flush against it). Moved the ~300 lines of builder CSS into the properly-enqueued `admin/css/xf-builder.css` so they load with the rest of the builder stylesheet.
* **Form builder — preset chips refreshed.** The HEIGHT (lines), WIDTH (Full / 1/2 / 1/3 / 1/4), and BUTTON SIZE (Small / Medium / Large / XL) chip buttons in the right-side field settings panel now use a cleaner light-on-white style with a clear blue-tinted "selected" state, matching the original 2.0.6 design. Active state is also wired up on first render — clicking a chip toggles the highlight and the slider (for HEIGHT) stays in sync.

= 2.5.5 =
* **Restored the Manrope font on the admin UI, self-hosted.** The Manrope `@import` from Google Fonts was removed in 2.4.0 for WordPress.org compliance; the admin has been running on the system font stack since. The font is now back, bundled inside the plugin under `assets/fonts/manrope/` (six woff2 subset files, ~92 KB total, plus the OFL 1.1 license), with the `@font-face` declarations inlined at the top of `admin/css/xf-admin.css`. **No request is ever made to `fonts.googleapis.com` or `fonts.gstatic.com`** — the font ships locally so this remains compliant with the WordPress.org "no undisclosed external services" guideline.
* **Form builder:** restored the always-visible "Click to edit button" hint on the Submit-button preview card. The hover-only `::after` affordance introduced in 2.5.2 was too subtle in practice — users were missing that the Submit area is editable. Back to the pre-2.5.2 behavior: the dashed outline, width badge (when floated), and italic gray hint all show by default; they still hide automatically when the Submit button is floated at 1/2 / 1/3 / 1/4 width so it can sit cleanly inline with the last row of fields.

= 2.5.4 =
* **Dashboard:** added a one-click "Copy shortcode" button next to each form in the **Top Performing Forms** card. Click the clipboard icon and the shortcode (`[xtreme_forms id="X"]`) lands on your clipboard — no need to open the form's edit screen first. The icon briefly turns green ✓ on success.

= 2.5.3 =
* **Dashboard:** added a fourth **Total Forms** KPI tile to the top row, linking to the Forms list and showing how many forms are currently active. The four KPI icons now use distinct colors (teal / blue / purple / orange) so the eye can pick out a tile by color.
* **Dashboard:** the **Leads by Form** chart is now a doughnut with a side legend instead of a bar chart. The center of the doughnut shows the total leads in the selected range; the legend lists each form with its lead count and percentage share. Hovering a legend row highlights the corresponding doughnut slice.

= 2.5.2 =
* **Fixed:** five built-in form templates (`Quote Request`, `Newsletter`, `Integrations`) shipped placeholder / option strings containing the literal characters `…`, `–`, and `—` instead of the intended ellipsis / en-dash / em-dash. They were typed inside single-quoted PHP strings where PHP does not interpret unicode escapes, so end users saw raw `…` in the *Project Description* placeholder of the Quote Request form, in the Budget Range dropdown options, and in two "Saving…" / "Testing…" admin labels. Replaced with the actual UTF-8 characters.
* **Improved:** the form-builder canvas no longer renders a permanent "Click to edit button" hint next to the Submit button. The affordance is now a CSS `::after` label that fades in only on hover or when the submit card is selected, so the default canvas view stays clean.

= 2.5.1 =
* WordPress.org review round 4 — readme-only fix. Added the Xtreme Plugins licensing API to the **External services** section to disclose that the License tab's Activate / Deactivate buttons make server-to-server calls to `https://xtremeplugins.com/api/v1/license/{activate,deactivate}` (license key + site URL only, on button click only). Includes terms-of-service and privacy-policy links for xtremeplugins.com. No code changes.

= 2.5.0 =
* **License tab for the optional Pro add-on.** New "License" tab under **Xtreme Forms → Settings** lets administrators paste their Pro license key, click Activate, and see status / plan / expiry. Deactivation releases the seat. Free plugin functionality is unchanged — no feature is gated by license status (per WP.org guideline 5, trialware compliance). The Pro add-on (sold separately at https://xtremeplugins.com/plugins/xtreme-forms) reads the activated license via the new `Xtremeforms_License::is_active()` / `get_plan()` public API to decide whether to enable its own features.
* New `includes/class-xf-license.php` — license storage, activation/deactivation against the xtremeplugins.com licensing API (`https://xtremeplugins.com/api/v1/license/*`, overridable via the `xtremeforms_license_activate_url` / `xtremeforms_license_deactivate_url` filters).
* New `admin/partials/xf-admin-license.php` — License tab UI with masked key display, status pill, plan code, expiry date (when returned), and an "View Pricing →" CTA linking to xtremeplugins.com when no license is active. Inline JS is registered through `wp_add_inline_script( 'xtremeforms-admin', ... )` (consistent with the 2.4.0 review fix).
* Two new AJAX handlers: `xtremeforms_activate_license` and `xtremeforms_deactivate_license` (both require `manage_options` + nonce).

= 2.4.4 =
* Fix duplicated "Assignment saved" text in the lead-assign feedback banner. When the assigned WordPress user has no email on file, the inline feedback used to read *"Assignment saved. Assignment saved, but the notification email could not be sent..."* — the JS already prepends "Assignment saved." so the server-side warning is now just *"Notification email could not be sent — the assigned user has no email address on file."* Result: *"Assignment saved. Notification email could not be sent — the assigned user has no email address on file."*

= 2.4.3 =
* WordPress.org automated scan: bumped the `Tested up to:` header from `6.9` to `7.0` to match the current WordPress release. No code changes.

= 2.4.2 =
* WordPress.org review round 3 — activation / DB migration fixes (`includes/class-xf-activator.php`).
* **No more raw `ALTER TABLE` queries.** The four columns previously added by conditional `ALTER TABLE ADD COLUMN` statements after `dbDelta()` (`activate_at`, `expire_at`, `closed_message` on the forms table; `consent_given` on the leads table) are now declared inline in the `CREATE TABLE` strings. All schema mutation flows through `dbDelta()`, which is idempotent. Resolves the reviewer report that the plugin "repeatedly tries to create existing tables/columns" during activation and update checks, reproducible on WordPress Playground.
* **`maybe_upgrade()` is now an option-read fast path.** On a fully-migrated site it returns after a single `get_option()` + `version_compare()` — no schema queries at all. Adds a per-request static guard so `plugins_loaded` firing more than once cannot double-invoke `dbDelta()`.
* **`PRIMARY KEY` uses two spaces** in every `CREATE TABLE` string (per the `dbDelta()` docs), eliminating a known dbDelta parsing quirk that can re-emit `ADD PRIMARY KEY` on each run.
* **wpdb errors silenced around the `dbDelta` block** with `hide_errors()` + `suppress_errors()`, restored on exit — activation never prints DB notices to the admin even if a host returns a benign warning.

= 2.4.1 =
* Fixed form submission validation false-positive — required Name / Email / Phone / etc. were rejected even when filled because the public form rendered inputs as `name="xf_field[ID]"` while the AJAX handler read `$_POST['xtremeforms_field']`. Server now reads `$_POST['xf_field']`, matching the rendered names. Affects every form / every field type.
* Fixed UTM cookie fallback never captured (server `xtremeforms_utm_cookie` vs JS `xf_utm_cookie`).
* Fixed `submit_duration` always null (server `xtremeforms_submit_duration` vs JS `xf_submit_duration`).
* Fixed server-side redirect-after-submit broken on forms with a configured Redirect URL.

= 2.4.0 =
* WordPress.org review compliance round 2:
  * Attribution: removed the hard-coded "Sent by Xtreme Forms" credit link from outgoing emails. The link is now strictly opt-in via a new checkbox under **Xtreme Forms → Email Templates → Plugin Attribution** and defaults to OFF.
  * Third-party requests: removed the Google Fonts (`fonts.googleapis.com`) `@import` from the admin stylesheet. The admin UI now uses the operating-system native font stack only — no third-party font requests are made.
  * Naming: renamed the short `xf_` / `xl_` AJAX action prefixes to the unique `xtremeforms_` prefix across all hooks, nonces, and localized data objects (40+ endpoints) to satisfy the 4+ character prefix requirement.
  * Assets: moved all inline `<script>` and `<style>` blocks out of admin partials and into properly enqueued files registered through `wp_enqueue_script()` / `wp_enqueue_style()` / `wp_add_inline_script()` / `wp_add_inline_style()`.
  * Security: added explicit nonce verification (`wp_verify_nonce` / `check_ajax_referer`) to every `$_GET` / `$_POST` / `$_REQUEST` read flagged by Plugin Check, in addition to the existing `current_user_can()` capability gates.
  * Vendor: upgraded the bundled Chart.js library to v4.5.1 (latest stable).
  * Contributors: added the plugin owner's WordPress.org username (`loanpartnership`) to the readme contributors list.

= 2.3.3 =
* WordPress.org review: documented all third-party / external services (Google reCAPTCHA, Cloudflare Turnstile, Zoho CRM, HubSpot CRM, Salesforce, Pipedrive, custom webhooks, Google Fonts) under a new "External services" section in readme.txt
* WordPress.org review: lowered admin menu position — Xtreme Forms now appears at the bottom of the admin menu rather than alongside core items
* WordPress.org review: added explicit `current_user_can()` capability checks at the top of every admin partial that reads `$_GET` (defence in depth — the page callbacks were already capability-gated, but partials now self-guard)
* WordPress.org review: renamed the short `XF_` class prefix to `Xtremeforms_` across all 23 classes (`XF_Forms` → `Xtremeforms_Forms`, etc.) to satisfy the 4+ character prefix requirement and prevent collisions with other plugins
* PHP 8.1+: replaced `null` parent_slug with empty string in the 11 hidden `add_submenu_page()` calls — silences the "Passing null to parameter of type string is deprecated" notices that WP core emits inside `plugin_basename()`

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

= 2.5.9 =
Form builder Submit-card hint no longer overlaps the button at 100% width. Public form: button-size variants now beat theme CSS, Cloudflare Turnstile widget aligned cleanly. Safe drop-in upgrade.

= 2.5.8 =
Public form now renders in Manrope (same font as the admin, served from the bundled `assets/fonts/manrope/` files — no external request). Safe drop-in upgrade.

= 2.5.7 =
Public form: phone fields auto-format as (555) 123-4567 while the visitor types, and date fields now open the browser picker on a single click anywhere in the field. Safe drop-in upgrade.

= 2.5.6 =
Fixes a long-standing form-builder styling regression: the Submit-button preview card now reliably renders its dashed outline, hint, and width badge, and the WIDTH / HEIGHT / BUTTON SIZE preset chips show a clear selected state. Safe drop-in upgrade.

= 2.5.5 =
Restores the Manrope font on the admin UI (now bundled locally — no Google Fonts request) and the always-visible "Click to edit button" hint on the Submit-button preview. Safe drop-in upgrade.

= 2.5.4 =
Adds a one-click Copy shortcode button next to each form in the Top Performing Forms list on the dashboard. Safe drop-in upgrade.

= 2.5.3 =
Dashboard refresh: adds a Total Forms KPI tile and converts Leads by Form to a doughnut chart with a side legend. No database changes; safe to upgrade.

= 2.5.2 =
Fixes the raw `…` / `–` / `—` text shipped in the Quote Request placeholder and Budget Range options of the built-in form templates, and cleans up the Submit button preview on the form-builder canvas. Safe to upgrade.

= 2.5.1 =
WordPress.org review round 4 — readme-only fix that adds the Xtreme Plugins licensing API to the External services disclosure (Activate / Deactivate buttons on the License tab). No code changes; safe to upgrade.

= 2.5.0 =
Adds a "License" tab under Settings for activating the optional Xtreme Forms Pro add-on. The free plugin remains fully functional with no caps — only the Pro add-on (sold separately at xtremeplugins.com) consumes the license. Safe drop-in upgrade.

= 2.4.4 =
Cosmetic fix: the lead-assignment feedback no longer duplicates "Assignment saved" when the assigned user has no email on file. No code-path changes.

= 2.4.3 =
Bumps the `Tested up to` header to WordPress 7.0 to satisfy the WordPress.org automated submission scan. No code changes.

= 2.4.2 =
Fixes the activation / DB migration warnings from WordPress.org review round 3. All schema changes now go through `dbDelta()` (no raw `ALTER TABLE`) and the upgrade check is a single option read on a fully-migrated site. Safe to upgrade — no destructive schema changes.

= 2.4.1 =
Critical bug-fix release: required-field validation was rejecting filled fields on every submission due to a server/JS field-name mismatch. UTM cookie fallback, submit-duration timing, and post-submit redirects were affected by the same mismatch. Upgrade immediately if you ship forms on live sites.

= 2.4.0 =
WordPress.org review compliance round 2: opt-in attribution (default off), removal of third-party Google Fonts, rename of `xf_`/`xl_` AJAX prefixes to `xtremeforms_`, inline admin assets moved to `wp_enqueue`, extra nonce checks, Chart.js bundle update. No database changes.

= 2.3.3 =
WordPress.org review compliance: external-service disclosures in readme, lower admin menu position, capability checks on every admin partial, and rename of the short `XF_` class prefix to `Xtremeforms_`. No database changes.

= 2.0.5 =
WordPress.org submission prep + layout/UX fixes. Safe to upgrade from any 2.x version — no database changes.

= 2.0.3 =
Stable release. Safe to upgrade from any 1.x or 2.0.x version — no database changes.

= 2.0.1 =
Plugin renamed from XtremeLeads to Xtreme Forms. Deactivate and delete XtremeLeads before installing if upgrading from the old plugin slug.
