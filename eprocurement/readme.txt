=== eProcurement ===
Contributors: myblisstech
Tags: procurement, tender, bids, crm, government, supply-chain, scm
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 2.17.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mini-CRM WordPress plugin for procurement processes — manage bid/tender notices, structured bidder communication, cloud-based document storage, and role-based access control.

== Description ==

eProcurement is a self-hosted procurement management system for government departments and large organizations. It enables you to publish tender notices, accept bidder registrations, exchange structured queries and replies with prospective bidders, optionally accept online bid submissions, and archive closed tenders.

**Key features**

* **Bid lifecycle management** — Draft → Open → Closed → Archived status flow with a strict state machine that prevents invalid transitions.
* **Multi-cloud document storage** — Store tender documents and bid submissions in Google Drive, OneDrive, Dropbox, Amazon S3, or local disk. All credentials are encrypted at rest with AES-256-CBC.
* **Sealed-bid submissions** — Bidders upload their bid documents through a secure, nonce-protected endpoint. Submissions are stored in a protected directory that is not directly web-accessible.
* **Structured messaging** — Bidders can submit queries (public or private) which are routed to the assigned SCM/Technical contact. Staff can reply, mark threads as resolved, and attach files.
* **Role-based access control** — Four custom roles (SCM Manager, SCM Official, Unit Manager, Bidder) with curated capabilities. WordPress Administrators and Editors automatically receive procurement capabilities.
* **Multisite-ready** — Full network-activation support with per-site provisioning. New sites created after network activation are auto-provisioned.
* **Self-updating via GitHub Releases** — The plugin checks for new tagged releases on GitHub and lets admins one-click update from wp-admin. Release ZIPs are verified against a published SHA-256 checksum.
* **Security hardening** — CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy headers on frontend pages. User enumeration disabled.
* **Daily and weekly crons** — Auto-closes expired bids and archives closed bids past a retention period. Weekly digest email for SCM Managers.
* **Premium UX** — Clean, modern admin panel and bidder portal with responsive layouts, toast notifications, accessible forms, and inline validation.

**Privacy & data**

Bidder profiles, submission records, and message attachments are stored in your WordPress database and your configured cloud storage. The plugin does not phone home and does not transmit any data to third parties (other than the configured cloud provider for file storage and the GitHub API for update checks).

== Installation ==

1. Upload the `eprocurement` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **eProcurement → Settings** to configure your cloud storage provider, SMTP, branding, and frontend page slug.
4. The plugin automatically creates a frontend page (default slug: `tenders`) at activation. You can change the slug in Settings.
5. Assign SCM roles to staff users via **Users** in wp-admin, or use the built-in user management screen at **eProcurement → Users**.

For multisite installations, network-activate the plugin. New sites will be auto-provisioned.

== Frequently Asked Questions ==

= How do I configure cloud storage? =

Visit **eProcurement → Settings → Storage**. Choose a provider (Local, Google Drive, OneDrive, Dropbox, or S3), enter your credentials, and click **Test Connection**. For OAuth providers (Google Drive, OneDrive, Dropbox), click the authorization link to grant access — the plugin uses a `state` parameter to prevent CSRF attacks.

= How do bidders register? =

Bidders visit the frontend portal (e.g., `/tenders/register/`) and complete the registration form. They receive a verification email with a token-valid for 48 hours. After verification, they can log in and submit bids for open tenders.

= How are sealed-bid submissions protected? =

When using local storage, files are saved to `wp-content/uploads/eprocurement/` with an `.htaccess` file that denies all direct HTTP access. Files are served only through the nonce-protected `/eproc-download/` endpoint, which verifies the user is the bidder who owns the submission or a staff member with `eproc_publish_bids` capability.

For cloud storage, files are stored in the provider's root folder with randomized filenames. Download URLs are time-limited signed URLs.

= Can I extend the keep-list for theme assets? =

Yes. The plugin strips most theme CSS/JS on eProcurement pages to provide a clean, isolated UI. Use the `eproc_keep_styles` and `eproc_keep_scripts` filters in your theme's `functions.php`:

`add_filter( 'eproc_keep_styles', fn( $list ) => array_merge( $list, [ 'my-gdpr-banner' ] ) );`

= How do I uninstall completely? =

By default, uninstalling the plugin preserves all data so you can reinstall without loss. To delete all data on uninstall, enable **Delete all data on uninstall** in **eProcurement → Settings → Advanced** before deactivating and deleting the plugin.

= How do I report a security issue? =

Email security@myblisstech.com with details. We respond within 48 hours and credit responsible disclosure in our changelog.

== Changelog ==

= 2.17.0 (2026-07-28) =

**Critical**

* Schema: Fresh installs now get all 14 documents-table columns (was missing 8 award/evaluation/reminder columns on fresh install — only upgrades got them).
* 2FA: Removed remember-me bypass that extended 2FA sessions to 14 days.
* 2FA: Added rate limiting (5 attempts / 5-min lockout) to prevent brute force.
* 2FA: Moved session token from URL query string to signed HttpOnly SameSite=Strict cookie (was leaking via Referer/history).
* 2FA: Removed external api.qrserver.com call that leaked the 2FA secret + email to a third-party operator. Manual secret entry is now the primary flow.
* Award: Added procurement-integrity validations — winner must have an active submission, no double-award, no staff-as-winner.
* Updater: Fixed SHA-256 verification that was hashing a directory instead of a file (dead code). Now verifies before extraction via `upgrader_source_selection`.
* Updater: Hard-fails if no checksum asset is published (previously silently skipped, allowing bypass).
* Demo data: Replaced hard-coded `Demo@2025` password with per-install random passwords, displayed once to the seeding admin.

**High**

* Bid submission file validation now uses `finfo_file` content MIME verification (was filename-based only).
* External DB: SSRF protection now pins PDO to the resolved IP to prevent DNS rebinding; unresolvable hostnames rejected.
* External DB: Added strict character allowlist on host/database names to prevent PDO DSN injection.
* Local storage: `get_download_url()` always returns the nonce-protected PHP endpoint (was returning direct file URLs that bypassed .htaccess on nginx).
* Local storage: Added `realpath()` containment check to `delete()` to prevent path traversal.
* Admin dashboard: Fixed XSS in API-usage widget where `display_name` was concatenated into `innerHTML` without escaping.
* Frontend: Fixed `javascript:` href XSS where `escHtml` was used instead of URL-scheme validation.

**Medium**

* Threads: Added 'cancelled' to status ENUM (was silently failing in strict mode). Migration included.
* Threads: Added explicit bidder-only check on retract endpoint.
* Messaging: Bidder-created threads are now always private (was accepting `visibility` from the request, allowing self-promotion to public).
* Downloads: CSV export now prefixes dangerous leading characters (`=`, `+`, `-`, `@`, `\t`, `\r`) with `'` to prevent formula injection in Excel.
* ZIP: Company folder now includes `_user_id` suffix to prevent filename collisions between bidders from the same company.
* Local storage: Validates file extension against allowed MIME types before saving.

**Low**

* Demo bidder no longer auto-verified (forces normal email verification flow).
* 2FA input now uses `wp_unslash` before sanitizing.
* Multiple defense-in-depth JS escaping fixes in admin and frontend.

= 2.14.0 (2026-06-21) =

**Security**

* Critical: Added OAuth `state` parameter to all cloud-storage OAuth flows to prevent CSRF / storage-account takeover.
* Critical: Strengthened local-storage `.htaccess` to deny ALL direct HTTP access to sealed-bid submissions.
* Critical: Fixed IDOR on attachment downloads — users can now only download attachments from threads they participate in.
* Critical: Sanitised GitHub release body HTML with `wp_kses_post()` and added SHA-256 checksum verification for release ZIPs.
* High: Sanitised exception messages in REST/AJAX responses to prevent DOM XSS via `innerHTML`.
* High: Tightened sealed-bid ZIP download capability to `eproc_publish_bids` (was `eproc_view_dashboard`).
* High: Validated `contact_id` against the bid's assigned SCM/Technical contacts before creating a thread.
* High: Bound download nonces to the file ID and type to prevent nonce reuse across files.
* High: Fixed CORS `Allow-Credentials: true` + `Access-Control-Allow-Origin: *` invalid combination.
* High: Fail loudly when `AUTH_KEY` is undefined instead of silently falling back to a publicly-known key.
* High: Gated Mailpit dev block on `EPROC_DEV_MODE` constant or `wp_get_environment_type() === 'local'`.
* High: Fixed `wp_mail_content_type` filter leak (closure reference instead of new instances).
* Medium: Blocked RFC1918 / link-local addresses in External DB connector (SSRF prevention).
* Medium: Tightened generic MIME-type allowlist — `application/octet-stream` only allowed for Office docs.
* Medium: Always `sanitize_file_name()` before passing to cloud-storage `upload()`.
* Medium: Added immutable audit log for hidden bid backdating actions.
* Medium: Wrapped document cascade delete in a DB transaction.
* Medium: Cached `test_connection()` result in a 5-minute transient (perf).
* Medium: Throttled `auto_close_expired_bids()` to once per 5 minutes (perf).
* Medium: Added IP-based rate limiting to bidder registration endpoint (5/hour).
* Medium: Sanitised `HTTP_ORIGIN` header to prevent CRLF injection.
* Medium: Made theme-asset stripping filterable (`eproc_keep_styles` / `eproc_keep_scripts`); restored `wp-a11y`.

**Code quality**

* Bumped `Requires PHP` to 8.1 (PHP 8.0 reached EOL November 2023).
* Added `eprocShowNotice()` JS helper using `textContent` instead of `innerHTML` for XSS-safe error rendering.
* Fixed stale role slug `eprocurement_bidder` → `eprocurement_subscriber` in `uninstall.php`.
* Added missing `wp_clear_scheduled_hook( 'eprocurement_weekly_digest' )` in uninstall.
* Added comprehensive PHPDoc to OAuth state helpers and audit log.

= 2.13.1 =

* Initial public release with full security audit findings documented.

== Upgrade Notice ==

= 2.14.0 =

This is a **security release**. Upgrade immediately. Fixes 4 critical vulnerabilities (OAuth CSRF, sealed-bid file access, attachment IDOR, supply-chain integrity) and 9 high-severity issues.

== Privacy Policy ==

eProcurement stores the following data:

* **Bidder profiles** — Company name, registration number, phone, verification token. Stored in the WordPress database.
* **Bid submissions** — Uploaded files (PDF, DOC, XLS, ZIP). Stored in your configured cloud provider or local disk.
* **Messages and attachments** — Bidder ↔ staff correspondence. Stored in the WordPress database and cloud storage.
* **Download audit log** — IP address, user agent, timestamp for every file download. Stored for 30 days.

The plugin does not transmit data to third parties beyond your configured cloud provider and the GitHub API (for update checks). It does not use cookies beyond WordPress's standard auth and session cookies.
