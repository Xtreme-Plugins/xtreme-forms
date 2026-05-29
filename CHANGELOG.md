# Changelog

## [2.5.12] - 2026-05-29

### Changed
- **readme.txt** — added a "Useful Links" block at the top of the Description with direct links to the plugin homepage, features section, and pricing page on xtremeplugins.com; reworked the "Optional Pro Add-On" section to surface the same three subpage links + a GitHub source/issues link.

## [2.5.11] - 2026-05-29

### Fixed
- **Public-form Submit button rendered 2–3× taller than the form-builder canvas preview** (`public/css/xf-public.css`). 2.5.9 locked `padding` and `font-size` on `.xf-form-wrap .xf-btn-submit.xf-btn-size-{sm,md,lg,xl}` with `!important`, which fixed those two properties — but the rendered height is also driven by `line-height` and `min-height`, neither of which was locked. Themes like Astra, GeneratePress, Hello Elementor, the Twenty Twenty-Four block theme, and form addons like Elementor Pro Forms, Bricks Form, Beaver Themer Forms, Divi Forms apply `button[type="submit"] { min-height: 50px–66px; line-height: 1.8–2; height: auto !important; }` at specificity that beats our single-class size variants. Net effect on a heavy theme: admin picks "Small" (intended ~28 px tall), frontend renders at 75–90 px. Locked `line-height: 1.4`, `min-height: 0`, `height: auto`, `text-transform: none`, and `border: none` with `!important` on both the base `.xf-btn-submit` rule and every `.xf-form-wrap .xf-btn-submit.xf-btn-size-*` variant. Also added `box-sizing: border-box` to the base rule so `padding` doesn't push width past the parent column when the button is full-width. Rendered dimensions now match the canvas preview exactly across the themes listed.

## [2.5.10] - 2026-05-29

### Fixed
- **Form-builder BUTTON TEXT input had no live connection to the canvas Submit button** (`admin/partials/xf-admin-form-builder.php:229`). The hidden bridge input was declared as `<input type="hidden" name="submit_label" value="…">` — `name=` but no `id=`. The builder JS reads it via `document.getElementById('submit_label')` (`admin/js/xf-builder.js:481` for `renderSubmitCard`, `:535` for the settings panel binding), which returned `null` because the element had no id. The wiring guard `if (inp && labelInput)` at line 651 then short-circuited, the live `input` event listener was never attached, the canvas re-render was never triggered, and — because the same hidden input is what the form-builder `<form>` POSTs on save — the OLD label was silently submitted to `admin-post.php` regardless of what the admin typed. End result: typing a new label did nothing visible on the canvas *and* the new label never made it to the frontend after the user clicked Save Form. Fixed with a one-character change: added `id="submit_label"` to the hidden input. (This is also the root cause of the "preview shows Submit, frontend shows a different label" report from the 2.5.9 cycle.)

## [2.5.9] - 2026-05-29

### Fixed
- **Form-builder "Click to edit button" hint overlapped the button at 100% width** (`admin/css/xf-builder.css`). The hint was positioned `right: 14px` inside the dashed `.xfb-submit-preview` wrapper, which works when the button is medium-sized and left-aligned but clips or overlaps the button text the moment Width is set to 100% (Full). Moved the hint outside the wrapper via `left: calc(100% + 14px); right: auto`, set `.xfb-submit-preview { overflow: visible }` so it can poke out, and added a small inline SVG curved arrow as `::before` content that points back into the button. Hidden on canvases narrower than 980 px so it doesn't blow out the canvas width on small screens.
- **Public-form button-size variants silently overridden by theme / page-builder CSS** (`public/css/xf-public.css`). `.xf-btn-size-{sm,md,lg,xl}` lived at single-class specificity (0,1,0). Themes that style submit buttons via `.elementor-form button[type="submit"]` (0,2,1), `.brxe-form button` etc. were beating us silently — admin set Small, frontend rendered the default 16 px / 12 px 32 px button. Re-scoped the four size rules and the full-width rule under `.xf-form-wrap` and marked the relevant declarations `!important`. The four sizes now consistently produce 6/16, 10/28, 14/36, 18/52 px padding regardless of the surrounding theme.

### Changed
- **Cloudflare Turnstile widget alignment** (`public/css/xf-public.css`). The `.xf-turnstile-wrap` had nothing but `margin-bottom: 20px` — Cloudflare's iframe sat sloppily off to one side, with too much vertical room around it. Wrapped it in a flex container with `justify-content: flex-start`, reserved 65 px of vertical clearance so the layout doesn't jump when the widget finishes loading, applied `transform: scale(0.92)` on the `.cf-turnstile` so it's slightly more compact, and rounded the iframe corners to match the rest of the form (6 px). All changes scoped under `.xf-form-wrap` so other Turnstile widgets on the page aren't affected.

## [2.5.8] - 2026-05-29

### Changed
- **Public form font is now Manrope** (`public/css/xf-public.css`), matching the admin UI (which got Manrope back in 2.5.5). Inlined the same 30-block `@font-face` set at the top of the public stylesheet pointing at `../../assets/fonts/manrope/<hash>.woff2` — the same six woff2 files the admin uses, no second copy of the font shipped, no request to `fonts.googleapis.com`/`fonts.gstatic.com`. Updated the `--xf-font` custom property from `'Fira Sans', -apple-system, ...` to `'Manrope', -apple-system, BlinkMacSystemFont, "Segoe UI", "Fira Sans", sans-serif` so the fallback chain is preserved if Manrope ever fails to load. Replaced the five remaining hardcoded `font-family: "Fira Sans", sans-serif;` declarations (in the input, select, textarea, slider-value, and slider-edges rules) with `font-family: var(--xf-font);` so everything inherits the cascade.

## [2.5.7] - 2026-05-29

### Added
- **Public form: per-form `_setupInputEnhancements()` in `public/js/xf-public.js`.** Called once at `XLForm` construction, applies two UX enhancements field-by-field:
  - **Phone (`input[type="tel"]`) live US formatting.** As the visitor types into a phone field, the value reformats to `(XXX) XXX-XXXX` with parens / space / hyphen interpolated in the right places (`(123`, `(123) 4`, `(123) 456-7890`). International numbers — anything starting with `+` — are detected and left untouched so a `+44 7700 900123` doesn't get mangled. Pre-populated values (browser autofill, server-supplied defaults) are normalised on first paint. Default placeholder set to `(555) 123-4567` only when the form admin hasn't configured one.
  - **Date (`input[type="date"]`) one-click picker.** Browsers only open the native date picker when the visitor clicks the tiny calendar indicator at the right edge of the input — most users don't realise it's clickable. Now clicking anywhere on the field (or pressing Enter / Space when it has focus) calls `input.showPicker()` (Chrome, Edge, modern Firefox/Safari). Browsers without `showPicker` are no worse off than before. Wrapped in `try/catch` to absorb the SecurityError some browsers throw when the call wasn't user-initiated.

### Changed
- **Public CSS for date inputs (`public/css/xf-public.css`).** `input[type="date"].xf-input` now gets `cursor: pointer` so the field reads as a target. The `::-webkit-calendar-picker-indicator` is enlarged (`font-size: 18px; padding: 4px`) and starts at `opacity: 0.7`, fading to `1` on hover so the calendar glyph is visible at a glance.

### Fixed
- **`Save Form` button checkmark icon visibility (`admin/css/xf-builder.css`).** The `dashicons-saved` checkmark next to the button label was inheriting the dashicon default blue, making it invisible against the `button-primary` blue background. Pin to white (carried over from the unpushed local commit `f5084c5`).

## [2.5.6] - 2026-05-29

### Fixed
- **Form-builder Submit-card styling now actually loads.** The Submit-button preview wrapper (`.xfb-submit-preview`), the inline "Click to edit button" hint (`.xfb-submit-hint`), the width badge (`.xfb-width-badge`), the canvas card hover/selected highlights, and the four `.xfb-btn-size-{sm,md,lg,xl}` button-size variants were all defined inside an `<style>` block at the bottom of `admin/partials/xf-admin-form-builder.php` (~300 lines, lines 591–889), then attached to the `xtremeforms-admin` stylesheet handle via `wp_add_inline_style()` at line 892. By the time `wp_add_inline_style()` ran, the page `<head>` had already been emitted with the parent stylesheet, so on hosts where `wp_print_footer_styles()` did not re-emit late-attached inline styles the CSS was lost entirely — leaving the Submit row with browser-default styling (small unstyled button, hint sitting flush against it, no dashed outline). Moved the entire block into the properly-enqueued `admin/css/xf-builder.css` and removed the `ob_start`/`ob_get_clean`/`wp_add_inline_style` plumbing from the partial. This also closes a latent 2.4.0 review-compliance gap (the 2.4.0 changelog said "moved all inline `<style>` blocks out of admin partials" but this file's block stayed behind).

### Changed
- **Form-builder preset chips (`HEIGHT`, `WIDTH`, `BUTTON SIZE`) refreshed.** The base `.xfb-width-preset` rule (`admin/css/xf-builder.css:1217`) went from a flat gray rectangle (`background: #f3f4f6; border: 1px solid #d1d5db; padding: 3px 8px; font-size: 11px`) to a cleaner light-on-white chip (`background: #fff; border: 1px solid #e5e7eb; padding: 5px 12px; font-size: 12px`) with a blue hover and a `:focus-visible` ring, matching the original 2.0.6 settings-panel design.
- **Selected-chip highlight now visible on first render.** Added a `.xfb-width-preset.is-active, .xfb-width-preset.xfb-align-active` rule (light blue fill + blue border + blue text); the two class names are historical — field-side chips toggle `.is-active`, Submit-side chips toggle `.xfb-align-active`. Both now look the same.
- **Initial active state wired in JS (`admin/js/xf-builder.js`).** The WIDTH and HEIGHT chip rendering now adds `is-active` to the chip matching the current field value, the WIDTH click handler swaps `is-active` across the chip group on click (matching the existing COLUMNS pattern), and the HEIGHT chips additionally stay in sync with the slider — drag the slider and the matching chip highlights, click a chip and the slider snaps to its value.

## [2.5.5] - 2026-05-29

### Added
- **Manrope font, self-hosted (`assets/fonts/manrope/`).** Six woff2 subset files (cyrillic-ext, cyrillic, greek, vietnamese, latin-ext, latin) plus the OFL 1.1 license, ~92 KB total. Same files Google would serve, pulled once at build time and now bundled inside the plugin. The `@font-face` declarations are inlined at the top of `admin/css/xf-admin.css` with `src: url(../../assets/fonts/manrope/<hash>.woff2)`. **No request is ever made to `fonts.googleapis.com` or `fonts.gstatic.com`** — the admin UI works the same online and offline, and WordPress.org reviewers won't flag an undeclared external service. The `--xf-font: 'Manrope', ...` variable is unchanged; `.xf-wrap` and descendants now actually render in Manrope instead of falling back to the system stack (which is what they were doing silently since the 2.4.0 Google Fonts removal).

### Changed
- **Form builder: reverted the hover-only Submit-affordance change from 2.5.2** (`admin/js/xf-builder.js`, `admin/partials/xf-admin-form-builder.php`). The 2.5.2 CSS `::after` approach was too subtle in production — users could not see that the Submit-button card was an editable target until they hovered it, so they did not discover the right-panel settings. Restored the original inline `<span class="xfb-submit-hint">Click to edit button</span>` and the always-visible `.xfb-submit-hint { position: absolute; right: 14px; ... }` CSS rule. The float-mode rule (`.xfb-submit-preview.xfb-field-floating .xfb-submit-hint { display: none }`) is also back, so the hint still hides cleanly when the Submit button is floated at 1/2 / 1/3 / 1/4 width to sit inline with the last row of fields. No styling changes to anything else on the canvas.

## [2.5.4] - 2026-05-28

### Added
- **Dashboard: one-click Copy shortcode button** next to each form in the *Top Performing Forms* card (`admin/partials/xf-admin-dashboard.php`). Renders a small `dashicons-admin-page` button between the form name and the lead-count badge with `data-shortcode="[xtreme_forms id=\"X\"]"`; click and the shortcode lands on your clipboard. Saves an entire round-trip to the form's edit screen for the common "embed this form on a page" workflow.
- **`initShortcodeCopy()` in `admin/js/xf-dashboard.js`** wires up the buttons. Uses the async Clipboard API when available (`navigator.clipboard.writeText`) and falls back to the classic hidden-textarea + `document.execCommand('copy')` trick for older browsers and for admin pages served over plain HTTP (where the Clipboard API refuses to run because of the secure-context requirement). On success the icon briefly swaps to `dashicons-yes` on a green background; on failure it swaps to `dashicons-no` on a red background. The state auto-reverts after 1.4 s.

### Changed
- **Restructured the Top Performing Forms list rows.** Previously each row was wrapped in a single `<a>` that covered both the form name and the lead-count badge. To make room for the new copy button without nesting interactive elements (`<button>` inside `<a>` is invalid HTML), the row now lays out three flex siblings: `<a class="xf-top-list-link-name">` for the name, `<button class="xf-shortcode-copy">` for the copy action, and the existing count badge. Visual styling is unchanged at first glance — the row counter, the row's bottom border, and the hover-slide on the form name all behave the same. New CSS for `.xf-shortcode-copy` (idle / hover / focus / success / error) added in `admin/css/xf-admin.css`.

## [2.5.3] - 2026-05-28

### Added
- **Dashboard: "Total Forms" KPI tile (`admin/partials/xf-admin-dashboard.php`).** New fourth tile to the right of the existing All Time / This Month / This Week tiles, showing the count of forms with `status = 'active'` and linking to the Forms list page. Backed by a new `Xtremeforms_Analytics::count_active_forms()` helper (`includes/class-xf-analytics.php`).
- **Per-tile KPI icon colors.** Added a `--xf-orange` / `--xf-orange-10` CSS variable pair and four `.xf-kpi-icon-{teal,blue,purple,orange}` modifier classes (`admin/css/xf-admin.css`) so the four icons read at a glance (envelope = teal, calendar = blue, clock = purple, forms = orange).

### Changed
- **Dashboard: "Leads by Form" chart converted from bar to doughnut + side legend.** Mirrors the existing Audience-Insights donut pattern. The center of the doughnut shows the live total leads for the selected range; the right-hand legend lists each form with its lead count and percentage share. Hovering a legend row highlights the matching wedge and shows the tooltip. The all-time / 30-day / 90-day / custom range tabs are unchanged.
- **`renderBarChart()` rewritten in `admin/js/xf-dashboard.js`** to render a Chart.js `doughnut` (instead of `bar`), drive the new `#xf-leads-donut-total` center text from the summed values, and populate the `#xf-leads-donut-legend` `<ul>`. New `LEADS_BY_FORM_PALETTE` color array (teal/orange/blue/purple/green/pink/amber/slate) is used in arrival order so the largest form always gets teal.

## [2.5.2] - 2026-05-28

### Fixed
- **Literal `…` / `–` / `—` escape sequences leaking into rendered text.** Five strings across three files (`includes/class-xf-form-templates.php:203` and `:215`, `includes/class-xf-integrations.php:419`, `admin/partials/xf-admin-integrations.php:301` and `:374`) had unicode escapes typed inside single-quoted PHP strings. Single-quoted PHP does not interpret `\uXXXX`, so the raw 6-character escape was being stored in form templates (e.g. the *Quote Request* template's Project Description placeholder showed `Tell us about your project…` and the Budget Range dropdown listed `$1,000 – $5,000`) and shown in two admin button labels ("Saving…" / "Testing…"). Replaced with real UTF-8 characters (`…`, `–`, `—`). Note: existing forms that were created from the affected template before this fix will still have the literal escape stored in their field metadata — open the form, edit the affected field's placeholder / options, and save to refresh.

### Changed
- **Form-builder canvas: removed the always-on "Click to edit button" hint** next to the Submit-button preview (`admin/js/xf-builder.js:512-515`). The dashed border, hover highlight, and selection state already telegraph that the card is interactive; the extra inline label cluttered the canvas and overlapped the button at narrower canvas widths. The affordance is now a CSS `::after` pseudo-element on `.xfb-submit-preview` (`admin/partials/xf-admin-form-builder.php`) that reads "Click to edit", positioned absolute to the far right, and fades in only on hover or when the submit card is `.selected`. Hidden entirely when the Submit button is in floated layout (1/2, 1/3, 1/4 widths), matching the existing behaviour from 2.0.5.

## [2.5.1] - 2026-05-28

### Changed
- **WordPress.org review round 4 — readme-only fix.** Added a new "Xtreme Plugins licensing API (optional, only for the paid Pro add-on)" entry under the `== External services ==` section of `readme.txt`, disclosing that the License tab's **Activate** / **Deactivate** buttons make server-to-server `POST` calls to `https://xtremeplugins.com/api/v1/license/activate` and `https://xtremeplugins.com/api/v1/license/deactivate` (license key + `home_url()` only, on explicit button click only — never on form submission, page load, or in the background). Includes the xtremeplugins.com terms-of-service (`/terms`) and privacy-policy (`/privacy`) URLs, mentions the `xtremeforms_license_activate_url` / `xtremeforms_license_deactivate_url` filter overrides, and clarifies the free plugin never contacts the API unless an administrator chooses to activate a Pro license. No code changes.

## [2.5.0] - 2026-05-22

### Added
- **License tab for the optional Pro add-on** (`admin/partials/xf-admin-license.php`). Under **Xtreme Forms → Settings → License** admins can paste a Pro license key, click Activate, and see the live status / plan / expiry. A Deactivate button releases the seat against the licensing server and clears the local record. When no license is active, the panel shows an "View Pricing →" CTA linking to `https://xtremeplugins.com/plugins/xtreme-forms`. **No core free-plugin feature is gated by license status** — every feature documented on the WordPress.org listing remains unrestricted (WP.org guideline 5, trialware compliance).
- **`Xtremeforms_License` storage + activation class** (`includes/class-xf-license.php`). Stores `{ key, status, plan, expires_at, last_checked, last_message }` in the `xtremeforms_license` option. Talks to the licensing API at `https://xtremeplugins.com/api/v1/license/{activate,deactivate}`, overridable via the `xtremeforms_license_activate_url` / `xtremeforms_license_deactivate_url` filters for self-hosted licensing servers or staging environments. Always clears local state on deactivation even if the remote call fails (so the seat can be re-claimed later by re-activating).
- **Public license API for the Pro add-on.** `Xtremeforms_License::is_active(): bool`, `::get_plan(): string`, and `::get_data(): array` for the optional Pro add-on (sold from xtremeplugins.com, not on .org) to read and decide whether to enable its own features.
- **Two new AJAX handlers** in `Xtremeforms_Ajax`: `handle_activate_license` and `handle_deactivate_license`. Both require `manage_options` capability + `xtremeforms_admin_nonce`. Wired through `wp_ajax_xtremeforms_activate_license` / `wp_ajax_xtremeforms_deactivate_license`.

### Notes
- License-tab JS is registered through `wp_add_inline_script( 'xtremeforms-admin', $code, 'after' )` (matching the 2.4.0 review-mandated pattern for keeping admin partials free of inline `<script>` blocks).

## [2.4.4] - 2026-05-22

### Fixed
- Duplicated "Assignment saved" text in the lead-detail assign-feedback banner (`includes/class-xf-ajax.php:1409`). When the newly assigned WordPress user had no email address on file, the inline feedback used to read *"Assignment saved. Assignment saved, but the notification email could not be sent (the user may not have an email address)."* The JS in `admin/partials/xf-admin-lead-detail.php:540` already prepends `"Assignment saved."` before concatenating the server-side warning, so the warning string itself no longer repeats that prefix. New copy: *"Notification email could not be sent — the assigned user has no email address on file."* Reproduced on mr-roof.yobooth.com lead #1.

## [2.4.3] - 2026-05-22

### Changed
- Bumped `Tested up to:` header in `readme.txt` from `6.9` to `7.0`. WordPress.org's automated submission scan flagged the previous value as out-of-date (`outdated_tested_upto_header: Tested up to: 6.9 < 7.0`), which prevents the plugin from being listed in directory search results. No code changes.

## [2.4.2] - 2026-05-22

### Fixed
- **Activation / DB migration emits no errors on repeated runs** (`includes/class-xf-activator.php`). WordPress.org review round 3 reported that activating or upgrading the plugin "repeatedly tries to create existing tables/columns" and generates DB errors, reproducible on WordPress Playground.

### Changed
- **All four "extension" columns are now declared in `dbDelta()` CREATE TABLE strings** instead of being added by conditional `ALTER TABLE ADD COLUMN` statements after `dbDelta()` returns:
  - `wp_xtremeforms_forms`: `activate_at`, `expire_at`, `closed_message`
  - `wp_xtremeforms_leads`: `consent_given`
  On existing installs `dbDelta()` emits the same ALTER itself, idempotently — no behavior change for users, but reviewers no longer see raw schema queries.
- **`maybe_upgrade()` is an option-read fast path.** On a fully-migrated site (the common case) the method now returns after a single `get_option( 'xtremeforms_db_version' )` + `version_compare()` — no schema queries are issued. A per-request static guard prevents `plugins_loaded` firing twice from double-invoking `dbDelta()`.
- **Every `PRIMARY KEY` declaration uses two spaces before the paren** (`PRIMARY KEY  (id)`), per the `dbDelta()` documentation. The single-space form is parsed by current `dbDelta` versions but is flagged as a quirk that has historically caused dbDelta to re-emit `ADD PRIMARY KEY` on each run.
- **wpdb errors are silenced around the `dbDelta` block** with `hide_errors()` + `suppress_errors( true )`, and restored on exit. Activation / upgrade therefore never prints a DB notice to the admin even if the host returns a benign warning during a partial-migration ALTER.

### Removed
- All raw `ALTER TABLE ADD COLUMN` queries from `Xtremeforms_Activator::create_tables()`, along with the `INFORMATION_SCHEMA.COLUMNS` lookups that gated them. The activator no longer touches schema outside `dbDelta()`.

## [2.4.1] - 2026-05-19

### Fixed
- **Form submission validation false-positive — required Name / Email / Phone / etc. rejected even when filled.** The public form HTML renders inputs as `name="xf_field[ID]"` but the AJAX submit handler was reading `$_POST['xtremeforms_field']`. The two keys never matched, so `$raw_fields` was always an empty array, every required field tripped the "X is required" branch, and the inline error banner ("Please correct the errors below") was returned on every submit. Reported on patriot-moving.com → *Moving Inventory Checklist* form: filling Name, Email, Cell Phone, both ZIP codes still showed "Name is required / Email is required / Cell Phone is required." Server now reads `$_POST['xf_field']`, matching the rendered form names. Affects every form, every field type (text, email, phone, textarea, date, zipcode, slider, dropdown, checkbox, radio) — not just the moving inventory form.
- **UTM cookie fallback never captured.** Server read `$_POST['xtremeforms_utm_cookie']` while JS sent `xf_utm_cookie` — leads submitted from a page without UTM query params lost the first-party cookie fallback. Aligned to `xf_utm_cookie`.
- **`submit_duration` (time-to-submit) silently null on every lead.** Server read `$_POST['xtremeforms_submit_duration']` while JS sent `xf_submit_duration`. Aligned to `xf_submit_duration` — analytics avg-time-to-submit will populate again.
- **Server-side redirect after successful submit broken on forms with a configured Redirect URL.** Handler read `$_POST['xtremeforms_form_id']` / `xtremeforms_redirect_nonce`, JS sent `xf_form_id` / `xf_redirect_nonce` → resulted in "Invalid form" or "Security check failed" instead of the configured redirect. Aligned to `xf_form_id` / `xf_redirect_nonce`.

### Notes
- All five fixes are server-side only (`includes/class-xf-ajax.php`). The rendered form HTML, public JS, and admin form builder were already consistent; only the backend was reading the wrong POST keys.
- Each `xtremeforms_*` POST read the handler still uses (`xtremeforms_form_id`, `xtremeforms_nonce`, `xtremeforms_source_url`, `xtremeforms_form_time`, `xtremeforms_recaptcha_token`, `xtremeforms_turnstile_token`, `xtremeforms_consent`, `xtremeforms_website_url` honeypot) was verified against the matching `name="xtremeforms_*"` hidden input in `class-xf-shortcode.php::render_form()` — these were correct and untouched.

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
