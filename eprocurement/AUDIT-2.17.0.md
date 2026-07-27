# eProcurement CTO-Level Audit — v2.16.8 (commit 9def10c)

**Auditor:** CTO + Senior Dev + SCM Manager lens
**Method:** Read every line of every file. No guessing.
**Started:** 2026-07-28

## Issue Registry

### A1 — Version mismatch (Low, cosmetic but confusing)
- **File:** `eprocurement/eprocurement.php:6,26`
- **Bug:** Header declares `Version: 2.16.7`, `EPROC_VERSION` constant = `2.16.7`, but commit is `v2.16.8`. The `readme.txt` and `CHANGELOG.md` likely also lag.
- **Impact:** wp.org updater, GitHub release detection, and `eprocurement_maybe_upgrade()` gate all key off `EPROC_VERSION`. Sites that already ran 2.16.7 will NOT re-run migrations because `version_compare($installed, EPROC_VERSION, '>=')` returns true.
- **Fix:** Bump `EPROC_VERSION` to `2.16.8` (or `2.16.9` for the audit release) and update header + readme + changelog together.

### A2 — Fresh install schema missing 8 award/evaluation columns (CRITICAL)
- **File:** `eprocurement/includes/class-activator.php:81-367` (`create_tables`)
- **Bug:** The `documents` table CREATE statement only includes columns up through `briefing_compulsory`. It's missing:
  - `accept_online_submissions` (v2.12.2) — wait, line 102 HAS this. OK.
  - `submission_mode` (v2.16.0) — MISSING
  - `qa_deadline` (v2.14.0) — MISSING
  - `awarded_to_user_id` (v2.14.0) — MISSING
  - `award_amount` (v2.14.0) — MISSING
  - `award_date` (v2.14.0) — MISSING
  - `award_notes` (v2.14.0) — MISSING
  - `reminder_48h_sent` (v2.14.0) — MISSING
  - `reminder_24h_sent` (v2.14.0) — MISSING
- **Impact:** Fresh installs (and the very next migration step on existing sites if version gate is wrong) will have a `documents` table without award/evaluation columns. The award workflow, evaluation matrix, closing reminders, and single-document submission features will all silently fail or throw DB errors.
- **Root cause:** The migration in `eprocurement.php:193-218` adds these columns conditionally on `version_compare($installed, '2.14.0', '<')`. On a fresh install, `EPROC_VERSION` is 2.16.7, so the gate is `version_compare('2.16.7', '2.14.0', '<')` = false → columns never added. The migration is only for upgrades, not fresh installs.
- **Fix:** Add all 8 missing columns to the `documents` CREATE TABLE in `create_tables()`. Also update stale comment "Create all 11 custom database tables" → 14 tables.


### A3 — CSP allows unsafe-inline (Medium, security hardening)
- **File:** `eprocurement/includes/class-access-control.php:73`
- **Bug:** CSP `script-src 'self' 'unsafe-inline'` and `style-src 'self' 'unsafe-inline'`. The `'unsafe-inline'` on script-src largely defeats CSP's XSS protection.
- **Impact:** XSS in user content can still execute. CSS injection can exfiltrate data.
- **Mitigation:** Major refactor needed (nonces or hashes for all inline scripts). Document as known limitation; consider nonce-based CSP in next major version.

### A4 — Author enumeration only blocked for non-Super-Admin (Low, security)
- **File:** `eprocurement/includes/class-access-control.php:56-62`
- **Bug:** `?author=N` redirect is gated on `! is_super_admin()`. Super Admin can still be enumerated. Also, the redirect happens at `template_redirect` — the query has already run.
- **Fix:** Block for all users. Or better: redirect `/author/` and `?author=` to 404 unconditionally.

### A5 — 2FA flow has multiple critical vulnerabilities (CRITICAL)
- **File:** `eprocurement/includes/class-two-factor.php`
- **Sub-issues:**
  - **A5a (High):** Line 102 — `wp_set_auth_cookie( $user_id, true )` forces "remember me" = TRUE for all 2FA logins, extending session to 14 days. Should be `false` (default 2-day session) or respect the original login form's remember-me checkbox.
  - **A5b (High):** No rate limiting on 2FA code attempts (lines 96-112). 6-digit code = 1M combinations; with 3 valid codes (±1 window), brute-force is feasible. Need: lockout after N failed attempts (e.g., 5 attempts → 5-minute lockout per token).
  - **A5c (Medium):** Line 64-72 — The 2FA token is passed in URL query string. URL parameters leak via Referer headers, browser history, proxy logs. Should use POST form + cookie instead.
  - **A5d (Low):** Line 82 — Superglobal access without `wp_unslash()`. WP core recommends unslashing before sanitizing.

### A6 — 2FA QR code leaks secret to third-party API (CRITICAL, privacy regression)
- **File:** `eprocurement/includes/class-two-factor.php:350-381`
- **Bug:** Despite v2.16.6 claiming a "server-side data URI" fix, the code STILL calls `https://api.qrserver.com/v1/create-qr-code/` with the full `otpauth://` URL containing the user's email AND the 2FA secret.
- **Impact:** Anyone intercepting that HTTP request (the API operator, a MITM on HTTP, leaked logs) gets permanent access to generate valid 2FA codes. This defeats the entire purpose of 2FA.
- **Fix:** Bundle a local QR code generator (e.g., `chillerlan/php-qrcode` via composer, or a minimal pure-PHP QR encoder). Remove the external API call entirely. The otpauth URL is already shown as text for manual entry — that's sufficient fallback.


### A7 — Bid submission file validation skips content MIME check (High, security)
- **File:** `eprocurement/includes/class-bid-submissions.php:924-986`
- **Bug:** `Bid_Submissions::validate_file()` checks extension via `wp_check_filetype()` (filename-based only) but does NOT call `finfo_file()` to verify the actual file content matches the extension. Compare with `Eprocurement_Storage_Interface::validate_file()` at line 253-326 which DOES do proper finfo content verification.
- **Impact:** A malicious bidder could upload a `.pdf` file containing arbitrary content (e.g., a malicious macro-enabled Office doc renamed to .pdf, or PHP code). While cloud storage prevents execution on the WP server, the file could be served to other users (e.g., SCM staff downloading the ZIP) and exploit client-side vulnerabilities.
- **Fix:** Replace the local `validate_file()` in `Bid_Submissions` with a call to `Eprocurement_Storage_Interface::validate_file()`, or at minimum add `finfo_file()` content MIME verification mirroring the storage interface logic.

### A8 — ZIP filename collisions for same-company bidders (Low, data integrity)
- **File:** `eprocurement/includes/class-bid-submissions.php:690`
- **Bug:** `$primary_local = $company_folder . '/' . sanitize_file_name( $sub->file_name )`. If two bidders from the same company submit (or company names sanitize to the same slug), files collide in the ZIP and the second overwrites the first.
- **Fix:** Append `_<user_id>` to the company folder or primary file name to guarantee uniqueness.

### A9 — Temp file leak on exception in ZIP generation (Low, resource leak)
- **File:** `eprocurement/includes/class-bid-submissions.php:660-773`
- **Bug:** Temp files are collected in `$temp_files` and unlinked at line 770-773 AFTER `$zip->close()`. If an exception is thrown mid-loop, temp files are never cleaned.
- **Fix:** Wrap the loop in try/finally; cleanup in finally block.

### A10 — Audit log stored as WP option (Medium, performance + race condition)
- **File:** `eprocurement/includes/class-bid-submissions.php:579-612`
- **Bug:** Backdate audit log is stored in `eproc_audit_log` option as a serialized array. Read-modify-write pattern. Two concurrent backdates can lose entries. Performance degrades with log size (1000-entry cap helps but still O(N) per write).
- **Fix:** Use a dedicated DB table (`wp_eproc_audit_log`) with append-only INSERT. No UPDATE/DELETE API.

### A11 — `get_users(['role' => 'eprocurement_scm_manager'])` misses admins with multiple roles (Medium, functional bug)
- **File:** `eprocurement/includes/class-notifications.php:232, 412, 479`
- **Bug:** `get_users(['role' => 'X'])` only returns users whose `wp_capabilities` meta has `X` as one of their roles. But this query uses an INNER JOIN on the role meta key — if a user has multiple roles, they may or may not be returned depending on which role meta is queried first. Worse: line 743-746 uses `role__in` which is correct, but lines 232/412/479 use single-role which misses admins who are also scm_managers.
- **Impact:** Bid submission notifications may not reach all intended recipients. Inconsistent.
- **Fix:** Use `role__in => ['administrator', 'eprocurement_scm_manager']` consistently, OR use `get_users(['capability' => 'eproc_view_dashboard'])` which respects inherited caps.


### A12 — Thread retract has ENUM violation + missing role check (Medium, functional bug)
- **File:** `eprocurement/includes/class-rest-api.php:1060-1096`
- **Bug 1:** Line 1084 sets thread `status` to `'cancelled'`, but the `threads` table schema (activator.php:183) defines `status ENUM('open','resolved','closed')`. 'cancelled' is NOT in the ENUM.
- **Impact:** MySQL strict mode rejects the UPDATE (silent failure). Non-strict mode stores empty string. Either way, the retract appears to succeed (the response says "Your query has been retracted") but the thread remains in its original status.
- **Bug 2:** The ownership check is implicit via `get_thread()` ACL, which returns the thread for staff users (staff can view all threads). Then the sender_id loop check at line 1073-1080 only blocks if someone else has replied. A staff user whose own messages are the only ones in a thread could retract it. The docstring says "Only allowed if the current user owns the thread (is the bidder)" but the code doesn't enforce bidder-only.
- **Fix:** Add 'cancelled' to the threads.status ENUM in activator schema + migration. Add an explicit check `if (!Eprocurement_Roles::is_bidder($user_id)) return 403`.

### A13 — Bidder can self-promote query to 'public' visibility (Medium, security)
- **File:** `eprocurement/includes/class-messaging.php:61-63`
- **Bug:** `create_thread` accepts `visibility` from the request data. A bidder can submit `visibility=public` to make their query visible to ALL bidders immediately, bypassing the staff review flow that's documented elsewhere (`update_visibility` + `notify_visibility_change`).
- **Impact:** Information disclosure. A bidder could post sensitive content as "public" to all competitors. The intended flow (per `notify_visibility_change` hook) is staff-only visibility changes.
- **Fix:** Force `visibility = 'private'` for bidder-created threads. Only staff should be able to flip to public via the dedicated visibility change endpoint.

### A14 — `submit_query` rate limiting absent (Low, abuse vector)
- **File:** `eprocurement/includes/class-rest-api.php:387-496`
- **Bug:** Unlike `register_bidder` (which has IP rate limiting at line 343-353), `submit_query` has no rate limit. A verified bidder could spam thousands of queries, flooding the SCM inbox and consuming cloud storage via attachments.
- **Fix:** Add per-user rate limit (e.g., 20 queries per hour per user) similar to registration.

### A15 — `current_time('timestamp')` deprecated (Low, forward-compat)
- **File:** `eprocurement/includes/class-rest-api.php:411`, `eprocurement/includes/helpers.php:478`
- **Bug:** `current_time('timestamp')` is deprecated since WP 5.3. Should use `current_datetime()->getTimestamp()` or `time()`.
- **Impact:** Works today but throws deprecation notices in WP 6.x; will break in a future WP version.
- **Fix:** Replace with `time()` for UTC timestamp, or `current_datetime()->getTimestamp()` for site-tz-aware.


### A16 — Award workflow lacks validation (CRITICAL, business logic)
- **File:** `eprocurement/includes/class-documents.php:508-540` (`award()`)
- **Bug:** The `award()` method has three missing validations:
  1. **No submission check:** Does NOT verify `$winner_user_id` actually submitted a bid for this tender. An SCM Manager could "award" the contract to ANY registered user — themselves, a friend, a non-bidder. This is procurement fraud territory.
  2. **No double-award check:** Does NOT check if `$document->awarded_to_user_id` is already set. Re-awarding silently overwrites the previous winner, with no audit trail beyond the `eprocurement_bid_awarded` action firing twice (and the winner notification going to the new winner, but the old winner is NOT notified they've been un-awarded).
  3. **No staff exclusion:** Does NOT prevent awarding to a staff user (SCM Manager, Super Admin). A corrupt official could "award" to their colleague.
- **Impact:** Procurement integrity is at risk. South African municipal procurement has strict B-BBEE and tender integrity requirements (PFMA, MFMA). A staff member with `eproc_publish_bids` capability could award a contract to a non-bidder, defrauding the public.
- **Fix:**
  ```php
  // 1. Verify winner has an active submission
  $submissions = new Eprocurement_Bid_Submissions();
  $subs = $submissions->get_submissions_for_document($document_id);
  $valid_winner = false;
  foreach ($subs as $sub) {
      if ((int)$sub->user_id === $winner_user_id && $sub->status === 'submitted') {
          $valid_winner = true;
          break;
      }
  }
  if (!$valid_winner) {
      return new \WP_Error('not_a_bidder', __('Winner must have an active submission for this tender.', 'eprocurement'), ['status' => 400]);
  }
  // 2. Prevent double-award (require explicit withdraw first)
  if (!empty($document->awarded_to_user_id)) {
      return new \WP_Error('already_awarded', __('Tender already awarded. Withdraw the existing award first.', 'eprocurement'), ['status' => 400]);
  }
  // 3. Exclude staff
  if (Eprocurement_Roles::is_staff($winner_user_id)) {
      return new \WP_Error('staff_not_allowed', __('Cannot award to a staff member.', 'eprocurement'), ['status' => 400]);
  }
  ```


## Subagent Audit — Additional Findings (Files 1-15)

### A17 (CRITICAL) — Updater SHA-256 checksum verification is dead code
- **File:** `eprocurement/includes/class-updater.php:264-293`
- **Bug:** `post_install()` calls `hash_file('sha256', $result['source'])` but `$result['source']` is the **extracted directory**, not a file. `hash_file` on a directory returns `false`. The `if ($actual_sha && !hash_equals(...))` guard short-circuits on the falsy `$actual_sha`, so the checksum mismatch branch NEVER executes. Supply-chain protection is non-functional.
- **Fix:** Hash the ZIP **before** extraction via the `upgrader_package_options` filter, or implement a dedicated download-then-verify step in `check_update()`.

### A18 (CRITICAL) — Updater skips verification when checksum asset is missing
- **File:** `eprocurement/includes/class-updater.php:145, 169, 276`
- **Bug:** If the GitHub release omits `eprocurement.zip.sha256`, `fetch_release_checksum()` returns null and `post_install` skips verification entirely. An attacker who can push a release without the checksum asset bypasses all integrity checks.
- **Fix:** Hard-fail (refuse to install) when no checksum is published. Better: use minisign/cosign cryptographic signatures with a public key baked into the plugin.

### A19 (CRITICAL) — Hard-coded demo password
- **File:** `eprocurement/includes/class-demo-data.php:23, 295`
- **Bug:** `DEMO_PASSWORD = 'Demo@2025'` is used for all 4 demo users. The seeder is gated by `is_super_admin()` but has NO environment check. A production admin who runs "Seed Demo Data" creates 4 loggable accounts with a publicly-known password.
- **Fix:** Either (a) gate the seeder on `wp_get_environment_type() === 'local' || 'development'`, OR (b) generate a random per-install password via `wp_generate_password(24)` and display it once.

### A20 (HIGH) — External DB SSRF via DNS rebinding
- **File:** `eprocurement/includes/class-external-db.php:155-179, 188-223`
- **Bug:** `is_host_allowed()` resolves hostname via `gethostbynamel()` and validates IPs, but `new PDO(...)` re-resolves independently. DNS rebinding: short-TTL hostname returns public IP for the check, private IP (e.g., 169.254.169.254) for the connection. Also, if `gethostbynamel()` fails, host is allowed (line 211-213).
- **Fix:** Resolve once, connect to the resolved IP directly. Reject unresolved hostnames.

### A21 (HIGH) — External DB PDO DSN injection
- **File:** `eprocurement/includes/class-external-db.php:172`
- **Bug:** `$dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4"` interpolates `$host` and `$database` raw. A `$host` containing `;` injects arbitrary DSN options. `is_host_allowed()` doesn't reject non-IP non-resolvable hosts containing `;`.
- **Fix:** Whitelist hostnames against `/^[a-zA-Z0-9.\-]+$/`; reject values containing `;`, `=`, `\0`.

### A22 (HIGH) — Local storage direct file access on non-Apache servers
- **File:** `eprocurement/includes/storage/class-local-storage.php:62-87, 116-119`
- **Bug:** `.htaccess` (`Deny from all` + `php_flag engine off`) only protects files on Apache with mod_php. On nginx, LiteSpeed, or Apache without mod_php, sealed-bid submissions, supporting docs, and message attachments in `wp-content/uploads/eprocurement/` are publicly accessible via direct URL. `get_download_url()` returns the direct URL.
- **Fix:** Always serve local files through the PHP download endpoint. Document an nginx config snippet.

### A23 (HIGH) — Local storage delete() path traversal
- **File:** `eprocurement/includes/storage/class-local-storage.php:124-133`
- **Bug:** `delete()` does `$file_path = $this->get_base_dir() . '/' . $cloud_key` with no `realpath()` containment check (unlike `class-downloads.php:114-118`). If `cloud_key` is ever attacker-controllable (IDOR, corrupted DB row), `../../../wp-config.php` would delete critical files.
- **Fix:** Apply the same `realpath()` containment check used in `class-downloads.php::handle_download_request()`.

### A24 (MEDIUM) — archive_expired_closed_bids() timezone mismatch
- **File:** `eprocurement/includes/class-documents.php:300-325`
- **Bug:** `$cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"))` produces UTC, but `updated_at` is stored via `current_time('mysql')` (local). Comparison is timezone-inconsistent. On UTC+2, bids archived ~2h late.
- **Fix:** Use `current_time('mysql')` for cutoff.

### A25 (MEDIUM) — CSV formula injection in export_csv
- **File:** `eprocurement/includes/class-downloads.php:352-408`
- **Bug:** `fputcsv()` writes user-controlled fields (`user_agent`, `display_name`, `user_email`) without prefixing dangerous leading chars. A user agent like `=cmd|'/c calc'!A1` would be interpreted as a formula by Excel/LibreOffice.
- **Fix:** Prefix any cell value starting with `=`, `+`, `-`, `@`, `\t`, or `\r` with a single quote `'` before `fputcsv`.

### A26 (MEDIUM) — get_client_ip trusts spoofable headers
- **File:** `eprocurement/includes/class-downloads.php:413-435`
- **Bug:** Checks `HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR`, `HTTP_X_REAL_IP` then `REMOTE_ADDR`. Without verifying the request came through a trusted proxy, any client can spoof these headers, polluting the download audit log.
- **Fix:** Only honor forwarded headers when `REMOTE_ADDR` is in a configured trusted-proxy allowlist; otherwise return `REMOTE_ADDR`.

### A27 (MEDIUM) — AES-256-CBC without authentication (no HMAC/AEAD)
- **File:** `eprocurement/includes/class-storage-interface.php:178-205`
- **Bug:** `encrypt()` uses AES-256-CBC with random IV but no HMAC. Ciphertext is malleable; padding-oracle attacks possible with DB write access. `decrypt()` silently returns `''` on integrity failure.
- **Fix:** Switch to `AES-256-GCM` (PHP 7.2+) or wrap CBC with HMAC-SHA256 (encrypt-then-MAC).

### A28 (MEDIUM) — Google Drive / OneDrive anonymous public sharing links
- **Files:** `eprocurement/includes/storage/class-google-drive.php:149-169`; `class-onedrive.php:161-184`
- **Bug:** `get_download_url()` creates `anyone`/`anonymous` sharing permissions during the expiration window. Anyone with the URL (not just the intended bidder) can download sealed-bid submissions and tender documents. URLs leak via browser history, referer headers, support tickets.
- **Fix:** Proxy downloads through the WordPress endpoint (download server-side from cloud, stream to user). Don't issue public sharing links for sensitive procurement files.

### A29 (MEDIUM) — Branding CSS injection via unvalidated color values
- **File:** `eprocurement/includes/class-branding.php:105-120, 156-168, 198-215`
- **Bug:** `resolved_colors()` merges user-saved color values over defaults without validating they're hex codes. `inline_css()` escapes via `esc_attr()` (which doesn't strip `;{}`) and emits `--eproc-primary:{$value};`. An admin can inject `red;} body{background:url(//evil.com/track.png)} .eproc-wrap{--x:`, enabling CSS-based exfiltration.
- **Fix:** Validate each color against `/^#([a-fA-F0-9]{3}){1,2}$/` on save.

### A30 (LOW) — Demo data auto-verifies bidder + timezone bug
- **File:** `eprocurement/includes/class-demo-data.php:327, 396-400`
- **Bug:** Demo bidder profile created with `verified=1` — can submit bids immediately if demo persists in production. Demo bid `closing_date` is in UTC (`gmdate`) while `auto_close_expired_bids()` compares against `current_time('mysql')` (local).
- **Fix:** Don't auto-verify demo bidders; use `current_time('mysql')` for demo dates.

### A31 (LOW) — SMTP host not validated against SSRF
- **File:** `eprocurement/includes/class-smtp.php:29-53`
- **Bug:** Unlike external DB, the SMTP host is not validated against internal/private IPs. A Super Admin could configure `127.0.0.1:25` or `169.254.169.254:80` and use PHPMailer as an internal port scanner.
- **Fix:** Apply the same `is_host_allowed()` blocklist used in class-external-db.php.

### A32 (LOW) — Google Drive/OneDrive/Dropbox memory blow-up on large files
- **Files:** `class-google-drive.php:133`; `class-onedrive.php:133`; `class-dropbox.php:125`
- **Bug:** `file_get_contents($local_path)` loads the entire file into memory before upload. 50 MB bid docs on 128 MB PHP limit will exhaust memory. S3 correctly uses `SourceFile` for streaming.
- **Fix:** Use the SDK's stream/chunked-upload APIs.

### A33 (LOW) — OneDrive unencoded path in Graph API URL
- **File:** `eprocurement/includes/storage/class-onedrive.php:131, 135`
- **Bug:** `$path` is concatenated into `/me/drive/root:' . $path . ':/content` without `rawurlencode()` on segments. Filenames with `#`, `?`, `%` malform the URL.
- **Fix:** `implode('/', array_map('rawurlencode', explode('/', $path)))`.

### A34 (LOW) — Dropbox $expires_in parameter ignored
- **File:** `eprocurement/includes/storage/class-dropbox.php:158-178`
- **Bug:** `get_download_url($cloud_key, $expires_in = 3600)` accepts the param but never passes it to Dropbox. Dropbox temporary links last ~4 hours regardless. Callers expecting 1-hour URL get 4-hour.
- **Fix:** Document the actual expiration in the docblock.


## UI Layer Findings (Subagent audit of admin/public partials + JS)

### A35 (HIGH/XSS) — Admin dashboard API-usage widget XSS via display_name
- **File:** `eprocurement/admin/partials/dashboard.php:313`
- **Bug:** `data.by_user.slice(0,5).forEach(function(u){ html += '<tr><td>' + u.display_name + '</td>...'; })` — WP `display_name` is user-controllable. Inserted into `innerHTML` without escaping. A bidder setting `display_name = <img src=x onerror=alert(document.cookie)>` would execute JS in every Super Admin's wp-admin context (nonce theft, plugin install, etc.).
- **Fix:** Wrap with `escHtml()` helper before concatenation, or build rows with `textContent`/`createElement`.

### A36 (HIGH/XSS) — frontend.js `javascript:` href XSS via download_url
- **File:** `eprocurement/public/frontend.js:333`
- **Bug:** `'<a href="' + escHtml(att.download_url) + '" class="eproc-btn blue" ...'` — `escHtml` is the WRONG escape function for an `href` attribute. `javascript:alert(1)` survives `escHtml` (no `<>&` chars). If `download_url` is ever attacker-controlled (via cloud_key manipulation), clicking the link executes JS in the bidder's browser.
- **Fix:** Validate `^https?:` before assigning to href, or use `escAttr` + URL scheme validation.

### A37 (MEDIUM/XSS) — settings.php admin-self-XSS via response.data.message
- **File:** `eprocurement/admin/partials/settings.php:648, 717, 748, 753`
- **Bug:** `'<div class="eproc-notice success"><p>' + response.data.message + '</p></div>'` — server response concatenated via `.html()`. If the server returns a translated string containing HTML it renders. Mitigated by the fact that messages are typically fixed translation strings, but defense-in-depth.
- **Fix:** Use `.text()` for the inner `<p>`.

### A38 (MEDIUM/XSS) — manage/bid-edit.php unescaped sub.id and userId
- **File:** `eprocurement/public/partials/manage/bid-edit.php:1405, 1406, 1890`
- **Bug:** `data-sub-id="' + sub.id + '"` and `data-user-id` interpolated without `parseInt` or escaping. If REST API returns non-numeric values (JSON injection), attribute or URL breaks.
- **Fix:** Wrap with `parseInt(sub.id, 10) || 0` and `escAttr(userId)`.

### A39 (MEDIUM/Open Redirect) — redirect_to not constrained to same-site paths
- **File:** `eprocurement/public/class-public.php:834-838`
- **Bug:** `$redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ); wp_safe_redirect( $redirect );` — `wp_safe_redirect` blocks external hosts, but same-site paths to `/wp-admin/` could bounce users to admin pages post-login. Low impact (capability checks still gate admin pages).
- **Fix:** Validate `redirect_to` is a relative path starting with `/` and not pointing to `/wp-admin/` or `/wp-login.php`.

### A40 (MEDIUM/XSS) — admin.js showNotice concatenates message into HTML
- **File:** `eprocurement/admin/admin.js:62`
- **Bug:** `'<span class="eproc-toast-message">' + message + '</span>'` — message concatenated into HTML. Most callers pass server responses or fixed strings; if any server response contains HTML it'll be rendered.
- **Fix:** `$('<span class="eproc-toast-message">').text(message)` instead of string concat.

### A41 (MEDIUM/XSS) — frontend.js status badge not escaped
- **File:** `eprocurement/public/frontend.js:111`
- **Bug:** `'<span class="eproc-status-badge" style="background:' + color + '">' + doc.status.toUpperCase() + '</span>'` — `doc.status` not escaped. REST API returns constrained values (open/closed/etc.) so practically safe, but defense-in-depth.
- **Fix:** `escHtml(doc.status.toUpperCase())`.

### A42 (LOW/Capabilities) — `is_super_admin()` may behave unexpectedly on single-site
- **File:** `eprocurement/admin/partials/dashboard.php:286`; `eprocurement/public/partials/manage/layout-wrapper.php`
- **Bug:** `is_super_admin()` on multisite checks the network admin cap; on single-site it checks for `administrator` role with `manage_options`-equivalent. Mostly works but consider `current_user_can('manage_options')` for consistency.
- **Fix:** Replace `is_super_admin()` with `current_user_can('manage_options')` for non-multisite-aware checks.

### A43 (LOW/CSRF defense-in-depth) — manage/settings.php form has no wp_nonce_field
- **File:** `eprocurement/public/partials/manage/settings.php`
- **Bug:** Form submission is via JS (`eprocAPI.post`) with X-WP-Nonce header. Acceptable, but if JS fails and form is submitted as regular POST, there's no nonce field.
- **Fix:** Add `wp_nonce_field('wp_rest', '_wpnonce')` for defense-in-depth.

### A44 (LOW/Logic) — Reason for visibility change uses wp_kses_post instead of plain text
- **File:** `eprocurement/admin/class-admin.php:1067`
- **Bug:** `$reason = wp_kses_post( wp_unslash( $_POST['reason'] ) )` — shown to bidders via email + dashboard. `wp_kses_post` allows many tags. Reason should be plain text.
- **Fix:** Use `sanitize_textarea_field` instead.

