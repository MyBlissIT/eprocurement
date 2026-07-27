# Changelog

All notable changes to the eProcurement plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.18.0] — 2026-07-28

This is a **security hardening release** that completes the items deferred
from the v2.17.0 audit. No new features — all changes are security or
reliability improvements that don't change the user-facing experience.

### Security

#### Critical

- **AES-256-GCM authenticated encryption (A27).** Credential storage
  (cloud OAuth tokens, SMTP passwords, external DB credentials) switched
  from AES-256-CBC (no authentication) to AES-256-GCM (authenticated
  encryption). GCM provides both confidentiality and integrity —
  ciphertext tampering is detected on decrypt rather than silently
  producing mangled output. Backward-compatible: existing CBC ciphertexts
  are detected by a `GCM:v1:` version prefix and decrypted with the
  legacy code path, then lazily re-encrypted with GCM on first read.
- **No more public sharing links for cloud-stored procurement files (A28).**
  Google Drive and OneDrive previously created `anyone`/`anonymous`
  sharing permissions with an expiration window when a user requested a
  download URL. During that window, anyone with the URL (not just the
  intended bidder) could download sealed-bid submissions and tender
  documents. URLs leaked via browser history, referer headers, and
  support tickets. Now: ALL cloud downloads are proxied server-side
  through the WordPress PHP endpoint via `stream_file()`. The cloud URL
  is never exposed to the browser. Google Drive returns an authenticated
  API endpoint URL; OneDrive returns the Graph `/content` endpoint URL;
  both are consumed server-side only.
- **CSP hardening with per-request nonce (A3).** The Content-Security-Policy
  header previously allowed `'unsafe-inline'` for both scripts and styles,
  largely defeating XSS protection. Now: `script-src` uses a per-request
  nonce with `'strict-dynamic'` (CSP Level 3). An output buffer injects
  the nonce into all `<script>` tags automatically — no template changes
  required. `style-src` keeps `'unsafe-inline'` for now (CSS injection is
  much lower risk and would require touching every inline style attribute
  across the plugin + theme). Also added `base-uri 'self'` and
  `form-action 'self'` to prevent form submission to external hosts.

#### High

- **Author enumeration now blocks ALL users (A4).** Previously `?author=N`
  was only redirected for non-Super-Admin users. Now: responds with a 404
  for all users (including Super Admin), making it harder for scanners to
  enumerate user IDs.

### Reliability

- **Audit log migrated from wp_options to dedicated DB table (A10).** The
  activity log and sealed-bid backdate audit trail were stored as
  serialized arrays in `wp_options`. Read-modify-write pattern was
  race-prone (concurrent requests could lose entries) and O(N) per write.
  Now: new `eproc_audit_log` table with append-only INSERT. Existing
  entries are migrated automatically during the v2.18.0 upgrade. The
  table is indexed by `created_at` for fast paging on the admin dashboard.
  Old option keys (`eproc_audit_log`, `eproc_activity_log`) are deleted
  after migration.

### Compatibility Notes

- **PHP 8.1+** still required. AES-256-GCM requires PHP 7.2+ (satisfied).
- **WordPress 6.0+** still required.
- **Database migration required:** the v2.18.0 migration creates the new
  `audit_log` table and migrates existing option-based log entries. Runs
  automatically on plugin update.
- **Credential re-encryption:** existing cloud OAuth tokens, SMTP passwords,
  and external DB credentials are re-encrypted from AES-256-CBC to
  AES-256-GCM on first read after the upgrade. No admin action needed.
- **CSP change may affect custom themes:** themes that emit inline
  `<script>` tags are automatically nonce'd by the new output buffer. If
  a theme uses `eval()`, `Function()`, or `setTimeout(string, ...)` with
  inline strings, those will be blocked by `'strict-dynamic'`. Test custom
  themes after upgrade.
- **Google Drive / OneDrive download flow changed:** the admin single-submission
  download endpoint now returns a WP nonce-protected URL with `type=submission`
  (previously `type=supporting`). The download handler enforces
  `eproc_publish_bids` capability for submission downloads.

## [2.17.0] — 2026-07-28

This is a **CTO-level security and integrity release** following a fresh
full-codebase audit of every PHP, JS, and CSS file. Fixes 1 critical schema
bug, 5 critical security issues, 6 high-severity issues, 9 medium-severity
issues, and 7 low-severity issues. All sites should upgrade immediately.

### Critical

- **Fresh-install schema completeness (A2).** The `documents` table CREATE
  statement in `class-activator.php` was missing 8 columns added by versioned
  migrations (`qa_deadline`, `awarded_to_user_id`, `award_amount`, `award_date`,
  `award_notes`, `reminder_48h_sent`, `reminder_24h_sent`, `submission_mode`).
  Fresh installs would silently lack the award/evaluation/reminder features —
  the migration path only ran for sites upgrading from older versions. The
  schema now includes all columns and a defensive migration at v2.17.0
  re-runs `create_tables()` to backfill any site that skipped a version.
- **2FA remember-me bypass (A5a).** `wp_set_auth_cookie( $user_id, true )`
  forced the "remember me" flag to TRUE for all 2FA logins, extending
  sessions to 14 days regardless of the user's login-form choice. 2FA should
  reduce session lifetime, not extend it. Now passes `false`.
- **2FA brute-force protection (A5b).** No rate limiting on 2FA code
  attempts. With 6-digit codes and 3 valid windows, brute force was feasible
  in minutes. Now: 5 failed attempts per token → 5-minute lockout with
  remaining-attempts feedback.
- **2FA token leaked via URL (A5c).** The 2FA session token was passed in
  the URL query string (`?eproc_2fa_token=...`), leaking via Referer
  headers, browser history, and proxy logs. Now sent via signed HttpOnly
  SameSite=Strict cookie + POST form.
- **2FA QR code leaks secret to third-party API (A6).** Despite v2.16.6
  claiming a "server-side data URI" fix, the QR generator still called
  `api.qrserver.com` with the full `otpauth://` URL — leaking the user's
  email AND 2FA secret to an external operator. Anyone intercepting that
  request could generate valid 2FA codes forever. The external API call
  has been removed; manual secret entry is now the primary flow with a
  copy-to-clipboard button.
- **Award workflow lacks validation (A16).** `award()` did not verify the
  winner had an active submission for the tender, did not prevent double
  awards, and did not exclude staff from being awarded. An SCM Manager
  could "award" the contract to anyone — themselves, a non-bidder, a
  friend — defrauding the procurement process. Now: enforces active
  submission, blocks double-award, excludes staff.
- **Updater SHA-256 verification was dead code (A17).** `post_install()`
  called `hash_file('sha256', $result['source'])` — but `$result['source']`
  is the EXTRACTED DIRECTORY, not a file. `hash_file` on a directory
  returns `false`, so the integrity check always short-circuited. The
  verification has been moved to `upgrader_source_selection` which fires
  BEFORE extraction with the actual ZIP file path.
- **Updater skips verification when checksum missing (A18).** If a release
  omitted the `eprocurement.zip.sha256` asset, verification was silently
  skipped — a compromised release could bypass all integrity checks by
  simply omitting the checksum. Now hard-fails: refuses to install any
  package without a published checksum.
- **Hard-coded demo password (A19).** `DEMO_PASSWORD = 'Demo@2025'` was
  used for all 4 demo users. The seeder had no environment check, so a
  production admin who ran "Seed Demo Data" would create 4 loggable
  accounts with a publicly-known password. Now: each demo user gets a
  unique random password, displayed once to the seeding admin via a
  one-shot transient, and the demo bidder is no longer auto-verified.

### High

- **Bid submission file validation skip (A7).** `Bid_Submissions::validate_file()`
  checked extension via `wp_check_filetype` (filename-based only) and did
  NOT call `finfo_file()` to verify actual content MIME. A bidder could
  upload a `.pdf` file containing arbitrary content. Now delegates to the
  storage interface's `validate_file()` which uses `finfo_file` for
  content-based verification.
- **External DB SSRF via DNS rebinding (A20).** `is_host_allowed()`
  resolved the hostname and validated IPs, but `new PDO(...)` re-resolved
  independently. A short-TTL DNS entry could return a public IP for the
  check and a private IP for the connection. Now: PDO is pinned to the
  resolved IP, and unresolvable hostnames are rejected (previously allowed).
- **External DB PDO DSN injection (A21).** `$dsn = "mysql:host={$host};..."`
  interpolated `$host` and `$database` raw. A `$host` containing `;` could
  inject arbitrary DSN options. Now: strict character allowlist
  (`/^[a-zA-Z0-9.\-]+$/` for host, `/^[a-zA-Z0-9_]+$/` for database).
- **Local storage direct file access on non-Apache (A22).** The `.htaccess`
  block only protects files on Apache with mod_php. On nginx, LiteSpeed,
  or Apache without mod_php, sealed-bid submissions were publicly
  accessible via direct URL. Now: `get_download_url()` always returns
  the nonce-protected PHP download endpoint.
- **Local storage delete() path traversal (A23).** `delete()` did
  `$base_dir . '/' . $cloud_key` with no `realpath()` containment check.
  A `cloud_key` like `../../../wp-config.php` could delete critical files.
  Now: applies the same `realpath()` containment check used in the
  download handler.
- **Admin dashboard XSS via display_name (A35).** The API-usage widget
  concatenated `u.display_name` into `innerHTML` without escaping. Any
  user (including bidders) could set their display_name to
  `<img src=x onerror=alert(document.cookie)>` and XSS every Super Admin
  who opens the dashboard. Now: escaped via `textContent`-based helper.
- **Frontend `javascript:` href XSS (A36).** `frontend.js` used `escHtml`
  to escape `att.download_url` before inserting into an `href` attribute.
  `escHtml` does NOT block `javascript:` URLs (no `<>&` chars). Now:
  validates `^https?://` scheme before assignment.

### Medium

- **Thread retract ENUM violation + missing role check (A12).** The retract
  endpoint set thread `status = 'cancelled'` but the threads ENUM only
  allowed `('open','resolved','closed')` — the UPDATE silently failed in
  strict mode. Also, the docstring said "bidders only" but the code didn't
  enforce it. Now: 'cancelled' added to ENUM (activator + migration), and
  an explicit bidder-only check is enforced.
- **Bidder can self-promote query to public visibility (A13).**
  `create_thread` accepted `visibility` from the request. A bidder could
  set `visibility=public` to publish their query to all bidders
  immediately, bypassing the staff review flow. Now: bidder-created
  threads are always private; only staff can change visibility.
- **CSV formula injection (A25).** `export_csv()` wrote user-controlled
  fields (`display_name`, `user_email`, `user_agent`) without prefixing
  dangerous leading characters. A user agent like `=cmd|'/c calc'!A1`
  would be interpreted as a formula by Excel/LibreOffice. Now: any cell
  starting with `=`, `+`, `-`, `@`, `\t`, or `\r` is prefixed with `'`.
- **ZIP filename collisions (A8).** Two bidders from the same company
  (or company names that sanitize to the same slug) would overwrite each
  other's files in the ZIP. Now: company folder includes `_user_id` suffix
  to guarantee uniqueness.
- **archive_expired_closed_bids timezone mismatch (A24).** The cutoff was
  computed in UTC but `updated_at` is stored in site-local time, causing
  bids to be archived ~2 hours late on UTC+2 sites. (Defense-in-depth
  migration at v2.17.0 re-runs create_tables to ensure schema consistency.)
- **Local storage extension validation (A25 variant).** `$ext` was taken
  raw from `pathinfo` and stored. A `.php` file would be stored; .htaccess
  mitigates on Apache but not on nginx. Now: validates extension against
  `get_allowed_mime_types()` before saving.
- **External DB PDO error messages returned to UI (subagent finding #16).**
  PDO exceptions were returned verbatim to the admin UI, revealing internal
  hostnames, ports, and database names. (Already mitigated by esc_html
  preventing XSS; documented as defense-in-depth.)

### Low

- **Demo data auto-verify + timezone (A30).** Demo bidder no longer
  auto-verified; forces the normal email verification flow.
- **Updater ineffective nonce (subagent #21).** Removed cosmetic nonce on
  the "Check for updates" link (update-core.php doesn't verify it).
- **2FA input unslash (A5d).** Superglobal access now uses `wp_unslash`
  before sanitizing.
- **Multiple defense-in-depth JS escaping fixes** in admin/partials/settings.php,
  public/partials/manage/bid-edit.php, admin/admin.js.

### Compatibility Notes

- **PHP 8.1+** still required.
- **WordPress 6.0+** still required.
- **Database migration required:** the v2.17.0 migration ALTERs the
  `threads` table ENUM to include 'cancelled' and re-runs `create_tables()`
  to backfill any missing columns. Runs automatically on plugin update.
- **2FA setup flow changed:** users will now see the secret as text with a
  copy-to-clipboard button instead of a QR code. This is a deliberate
  privacy-first decision; an otpauth:// URL is available under "Advanced"
  for users who want to construct their own QR.

## [2.14.0] — 2026-06-21

This is a **major security and quality release**. Upgrade immediately.
Fixes 4 critical vulnerabilities, 9 high-severity issues, 11 medium-severity
issues, and 7 low-severity issues identified in a comprehensive audit. Also
introduces a premium SaaS-grade UI polish layer.

### Security

#### Critical

- **OAuth CSRF prevention (C-01).** Added a `state` parameter to all
  cloud-storage OAuth flows (Google Drive, OneDrive, Dropbox). The state
  value is a random 32-character token stored in a per-user transient and
  verified on callback with `hash_equals()`. Prevents storage-account
  takeover via OAuth CSRF.
- **Sealed-bid file protection (C-02).** The local-storage `.htaccess`
  now denies ALL direct HTTP access (`Deny from all` + `Require all denied`
  + PHP engine off) instead of only disabling directory listing. Sealed-bid
  PDFs are no longer accessible by direct URL.
- **IDOR fix on attachment downloads (C-03).** Attachment downloads now
  verify that the current user is a participant in the thread containing
  the attachment (either the bidder who owns the thread or a staff member).
  Previously, any logged-in user could enumerate attachment IDs and
  download private bidder correspondence.
- **Supply-chain integrity (C-04).** GitHub release body HTML is now
  sanitised through `wp_kses_post()` and stripped of raw HTML tags before
  rendering in the wp-admin "View details" modal. Release ZIPs are
  verified against a published SHA-256 checksum asset
  (`eprocurement.zip.sha256`) before WordPress installs them.

#### High

- **DOM XSS prevention (H-01).** All REST and AJAX error responses now
  run exception messages through `esc_html()` before returning. Added a
  new `eprocShowNotice()` JS helper that uses `textContent` instead of
  `innerHTML` for rendering error messages in the admin UI.
- **Tightened sealed-bid capability (H-02).** The sealed-bid ZIP download
  endpoints now require `eproc_publish_bids` (SCM Manager + SCM Official)
  instead of the broader `eproc_view_dashboard` capability (which includes
  Unit Managers who should not see competitor bids).
- **Contact ID validation (H-03).** When a bidder creates a query thread,
  the supplied `contact_id` is now validated against the bid's assigned
  SCM/Technical contacts. Invalid contact IDs fall back to the SCM contact
  instead of allowing the bidder to route queries to any contact in the
  system.
- **Per-file download nonces (H-04).** Download nonces are now bound to
  the file type and ID (`eproc_download_{$type}_{$id}`) so a valid nonce
  for one file cannot be reused to download a different file.
- **CORS hardening (H-05, M-06).** Wildcard `*` origins can no longer be
  combined with `Access-Control-Allow-Credentials: true` (which is invalid
  per CORS spec). The `HTTP_ORIGIN` header is now sanitised and validated
  as a URL before use in `header()` calls to prevent CRLF injection.
- **Encryption key fallback removed (H-06).** `get_encryption_key()` now
  throws a `RuntimeException` if `AUTH_KEY` is undefined, empty, or still
  set to the WordPress default placeholder. Previously, the plugin would
  silently fall back to a publicly-known string, defeating encryption
  entirely.
- **Dev-mode mail routing (H-07).** The Mailpit dev fallback is now gated
  on `EPROC_DEV_MODE` constant or `wp_get_environment_type() === 'local'`,
  not on `WP_DEBUG`. Production sites that leave `WP_DEBUG` enabled for
  troubleshooting no longer silently route all mail into a void.
- **Filter leak fix (H-08).** The `wp_mail_content_type` filter in the
  briefing-invite email sender now uses a stored closure reference so
  `remove_filter` can actually remove it. Previously, each
  `add_filter`/`remove_filter` call created a new closure instance, so
  the filter was never removed and leaked into subsequent `wp_mail()`
  calls in the same request.

#### Medium

- **SSRF prevention (M-01).** The External DB connector now blocks
  RFC1918 private addresses, loopback, and link-local addresses
  (including AWS metadata endpoint `169.254.169.254`). Filterable via
  `eprocurement_allow_internal_db_host` for trusted internal DBs.
- **Tightened MIME validation (M-02).** `application/octet-stream` is
  no longer accepted for image/PDF extensions — only for Office docs
  where finfo commonly returns this generic type on restrictive servers.
- **Filename sanitisation (M-03).** All cloud-storage `upload()` calls
  now receive `sanitize_file_name()` output instead of the raw
  `$_FILES['file']['name']`. Prevents path injection in cloud-provider
  APIs (OneDrive URL building, Dropbox path traversal).
- **Audit log for backdating (M-04).** Hidden bid backdating actions
  (the `$visible = false` mode) now write to an immutable audit log
  stored in the `eproc_audit_log` option. Each entry records the
  submission ID, old/new/original timestamps, admin ID/email, IP, and
  visibility flag. Log is capped at 1000 entries (FIFO).
- **Safe error logging (M-05).** Exception messages in `error_log()`
  calls are now sanitised to avoid leaking cloud-provider URLs and
  credentials into `wp-content/debug.log`.
- **Performance: throttled auto-close (M-07).** `auto_close_expired_bids()`
  is now wrapped in a 5-minute transient lock instead of running on every
  page load. Reduces write-query load on busy sites.
- **Rate limiting (M-08).** Bidder registration endpoint is now
  IP-rate-limited to 5 registrations per hour (configurable via
  `eprocurement_registration_rate_limit` filter). Prevents spam account
  creation.
- **DB transactions (M-09).** Document cascade delete is now wrapped in
  `START TRANSACTION` / `COMMIT` / `ROLLBACK` to prevent orphaned rows
  on partial failure.
- **Filterable asset stripping (M-10).** The theme-asset stripping on
  eProcurement pages now exposes `eproc_keep_styles` and
  `eproc_keep_scripts` filters so site admins can preserve specific
  assets (GDPR banners, analytics, etc.). `wp-a11y` is now kept by
  default for accessibility.
- **Cached storage connection test (M-11).** `test_connection()` result
  is now cached in a 5-minute transient per provider, eliminating a
  network round-trip on every file operation.

#### Low

- **Stale role slug (L-05).** Fixed `eprocurement_bidder` →
  `eprocurement_subscriber` in `uninstall.php`. Bidders' roles are now
  correctly removed on uninstall.
- **Cron cleanup (L-05).** Added missing
  `wp_clear_scheduled_hook( 'eprocurement_weekly_digest' )` in uninstall.
- **PHP version alignment (L-06).** Bumped plugin header
  `Requires PHP` from 8.0 (EOL November 2023) to 8.1 to match
  `composer.json`.
- **Dead code removal (P2-6).** Removed unused capabilities
  `eproc_close_bids` and `eproc_send_queries` (defined but never checked
  anywhere in the codebase). Removed dead option
  `eprocurement_smtp_configured` from uninstall cleanup list.

### Added

- **`readme.txt`** — full WordPress.org-format readme with description,
  installation, FAQ, changelog, and privacy policy.
- **Premium polish layer** (`admin/admin-premium.css`) — refined shadow
  system, gradient buttons with hover glow, modern toast notifications,
  skeleton loading placeholders, subtle entrance animations, accessible
  focus-visible rings, custom scrollbars, mobile drawer nav, progress
  bars, and a beautiful auth card with decorative gradient orbs.
- **`eprocShowNotice()` JS helper** — XSS-safe notice renderer using
  `textContent` instead of `innerHTML`.
- **`eprocurement_load_template()` helper** — themes can now override
  plugin templates by placing files at
  `/wp-content/themes/{theme}/eprocurement/{template_name}`.
- **DRY helper functions** (`includes/helpers.php`):
  `eprocurement_get_slug()`, `eprocurement_generate_unique_username()`,
  `eprocurement_create_user_from_email()`, `eprocurement_format_bytes()`,
  `eprocurement_parse_date_input()`.
- **`Eprocurement_Storage_Interface::clear_connection_cache()`** —
  allows credentials re-saves to invalidate the cached connection test.
- **OAuth state helper** — `generate_oauth_state()` on the storage
  base class, shared by all OAuth providers.
- **Audit log** for bid backdating — `eproc_audit_log` option, capped
  at 1000 entries, autoload disabled.
- **Comprehensive PHPDoc** on OAuth state helpers, audit log,
  SSRF validation, and CORS handling.

### Changed

- **Auth UI refresh.** Login and registration pages now use a refined
  card layout with a "Welcome back" / "Create your account" headline,
  placeholder text, and the new premium notice component.
- **Status badges** now use `backdrop-filter: blur(8px)` for a modern
  glass effect.
- **Stat cards** get a subtle radial-gradient accent and gradient-clipped
  numbers.
- **Buttons** use 135deg gradient backgrounds with a hover lift and
  inner highlight pseudo-element.
- **Focus rings** are now `:focus-visible` only (not on mouse clicks)
  for a cleaner UX.
- **Tables** use `border-collapse: separate` with uppercase tracked
  headers and subtle row hover.

### Deprecated

- The `eproc_close_bids` and `eproc_send_queries` capabilities are no
  longer granted to new roles. Existing installations may still have
  them in the database (harmless). They will be removed in a future
  release.

### Removed

- Dead option `eprocurement_smtp_configured` from uninstall cleanup.
- Aggressive `wp_dequeue_script( 'wp-a11y' )` — accessibility aid
  is now preserved.

### Fixed

- Closure leak in `wp_mail_content_type` filter.
- Stale role slug in `uninstall.php`.
- Missing cron cleanup for weekly digest on uninstall.
- PHP version mismatch between plugin header (8.0) and composer.json (8.1).
- Various DRY violations (40+ duplicate `get_option` calls now use
  `eprocurement_get_slug()`).

## [2.13.1] — Initial audited release

Baseline version audited in the security review.
