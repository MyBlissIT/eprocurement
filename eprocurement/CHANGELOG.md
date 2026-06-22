# Changelog

All notable changes to the eProcurement plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
