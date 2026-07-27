# HANDOFF DOCUMENT — eProcurement WordPress Plugin

**Version:** 2.16.7
**Date:** 2026-06-22
**Prepared by:** Super Z (AI assistant)
**Repository:** github.com/MyBlissIT/eprocurement

---

## 1. EXECUTIVE SUMMARY

This plugin is a self-hosted procurement management system for South African
municipalities and large organisations. It was originally developed at v2.13.1,
then underwent a comprehensive security audit, remediation, and feature expansion
to v2.16.6 across multiple sessions.

The plugin manages the full procurement lifecycle: tender creation → publication →
bidder registration → Q&A → bid submission (single-file or per-document) →
evaluation (weighted scoring matrix) → comparison (ranked + side-by-side) → award
(with winner/loser email notifications) → archival.

It is designed to be embedded within a municipal WordPress website and inherits
the host theme's branding (colours, fonts) via CSS custom properties and
theme.json detection.

---

## 2. VERSION HISTORY (this session)

| Version | Summary |
|---------|---------|
| 2.14.0  | Security release: 4 critical + 9 high + 11 medium + 7 low fixes. Premium CSS layer. DRY helpers. Readme.txt. |
| 2.14.1  | Premium UX: avatars, branded HTML emails, profile card, password toggle/strength meter, auth card. |
| 2.14.2  | Breadcrumbs, countdown timer, notification bell, profile completion meter, last login tracking. |
| 2.15.0  | Evaluation matrix (criteria + scoring + ranked comparison), award workflow, closing reminders, 3 new email templates. |
| 2.15.1  | Evaluation UI: criteria card, comparison modal, award form modal. |
| 2.15.2  | Scoring modal on submissions table. Activity feed on dashboard. Branding consistency pass. |
| 2.16.0  | Per-document submission requirements, backdated tender upload, recently viewed tenders, search autocomplete, keyboard shortcuts, side-by-side comparison CSS. |
| 2.16.1  | Side-by-side comparison toggle, per-document upload UI, multi-file submission endpoint. |
| 2.16.2  | Enhanced ZIP download (company folders + summary CSV), comparison CSV export. |
| 2.16.3  | Q&A deadline enforcement, submission confirmation email, award visibility, unsaved changes warning. |
| 2.16.4  | Branding adaptability (theme.json inheritance), PDF preview modal, query retraction, 2FA (TOTP), API usage dashboard. |
| 2.16.5  | CRITICAL FIX: submission_mode, qa_deadline, created_at_override not being saved in form submission. |
| 2.16.6  | 2FA QR code server-side data URI, action hook signature fixes. |

---

## 3. ARCHITECTURE OVERVIEW

### 3.1 Directory Structure

```
eprocurement/
├── eprocurement.php                  # Main bootstrap: constants, autoloader, activation hooks, init
├── uninstall.php                     # Data-aware cleanup (preserves data by default)
├── composer.json                     # Google API + AWS SDK dependencies
├── readme.txt                        # WordPress.org format readme
├── CHANGELOG.md                      # Keep a Changelog format
├── SECURITY.md                       # Vulnerability reporting policy
├── HANDOFF.md                        # This document
│
├── includes/                         # Shared business logic (models, services, REST)
│   ├── helpers.php                   # DRY utility functions (loaded first)
│   ├── class-database.php            # Thin DBAL: insert/update/delete/get_rows with column whitelisting
│   ├── class-documents.php           # Tender CRUD, status transitions (state machine), award, reminders
│   ├── class-bidder.php              # Bidder registration, email verification, profile management
│   ├── class-bid-submissions.php     # Bid submission, cancellation, ZIP generation, backdating
│   ├── class-messaging.php           # Query threads, messages, attachments
│   ├── class-contact-persons.php     # Contact persons directory (SCM + Technical)
│   ├── class-compliance-docs.php     # SCM Documents library
│   ├── class-downloads.php           # Secure download endpoint, audit log, CSV export
│   ├── class-notifications.php       # All email notifications (7 branded HTML templates)
│   ├── class-roles.php               # 4 custom roles + capability management
│   ├── class-access-control.php      # Security headers, user enumeration prevention
│   ├── class-activator.php           # Schema creation (14 tables), cron scheduling, role creation
│   ├── class-deactivator.php         # Cron cleanup, rewrite flush
│   ├── class-branding.php            # Brand name/URL/logo/colour settings
│   ├── class-smtp.php                # SMTP configuration (encrypted credentials)
│   ├── class-external-db.php         # External DB user sync (SSRF-protected)
│   ├── class-updater.php             # GitHub Releases self-update with SHA-256 checksum
│   ├── class-storage-interface.php   # Abstract storage + AES-256-CBC encryption + factory
│   ├── class-evaluation.php          # Evaluation criteria CRUD, scoring, ranked comparison
│   ├── class-submission-requirements.php # Per-document upload field definitions
│   ├── class-activity-log.php        # Append-only activity feed for dashboard
│   ├── class-two-factor.php          # TOTP 2FA for staff users (RFC 6238, no deps)
│   ├── class-demo-data.php           # Demo data seeder (dev only)
│   └── storage/
│       ├── class-local-storage.php   # Local filesystem (protected by .htaccess Deny from all)
│       ├── class-google-drive.php    # Google Drive via Google SDK
│       ├── class-onedrive.php        # OneDrive via Microsoft Graph API
│       ├── class-dropbox.php         # Dropbox via Dropbox API v2
│       └── class-s3.php              # Amazon S3 via AWS SDK
│
├── admin/                            # wp-admin backend
│   ├── class-admin.php               # Menu registration, 23 AJAX handlers, OAuth, settings save
│   ├── admin.css                     # Base admin styles
│   ├── admin-shell.css               # Sidebar layout (CSS custom properties for branding)
│   ├── admin-premium.css             # Premium polish layer (shadows, animations, avatars, modals, etc.)
│   ├── admin.js                      # AJAX handlers (jQuery)
│   └── partials/
│       ├── layout-wrapper.php        # Sidebar shell (logo, nav, user footer with avatar)
│       ├── dashboard.php             # KPI stat cards + recent bids + recent queries + activity feed + API usage
│       ├── bid-list.php              # Bid listing with filters
│       ├── bid-edit.php              # Add/edit tender (2-column layout, dates, contacts, submission settings)
│       ├── bidders.php               # Bidder directory with avatars
│       ├── contact-persons.php       # Contact directory with modal add/edit
│       ├── compliance-docs.php       # SCM Documents upload + sortable list
│       ├── download-log.php          # Download audit log with CSV export
│       ├── messages.php              # Two-pane messaging inbox
│       ├── online-bids.php           # Bids with online submissions enabled
│       ├── settings.php              # Super Admin settings (branding, storage, SMTP, external DB, CORS)
│       └── users.php                 # Staff user management
│
├── public/                           # Frontend bidder portal + manage panel
│   ├── class-public.php              # Rewrite rules, template loading, asset enqueue, brand CSS injection
│   ├── class-frontend-admin.php      # Manage panel router
│   ├── frontend.css                  # Base frontend styles (CSS custom properties)
│   ├── frontend.js                   # Bidder-facing JS (tender listing, query modal, submission upload)
│   ├── frontend-admin.css            # Manage panel bridge styles
│   ├── frontend-admin.js             # Shared utilities (eprocAPI, eprocToast, eprocShowNotice, password toggle, keyboard shortcuts)
│   └── partials/
│       ├── tender-listing.php        # Public tender card grid + search autocomplete + countdown
│       ├── tender-detail.php         # Single tender: header, countdown, award banner, docs, contacts, Q&A, submission
│       ├── bidder-login.php          # Login with password toggle
│       ├── bidder-register.php       # Registration with password strength meter
│       ├── bidder-dashboard.php      # Bidder home: profile card, completion meter, recently viewed, 4 tabs
│       ├── compliance-docs.php       # Public SCM documents download
│       ├── access-denied.php         # 403 page
│       └── manage/                   # Frontend staff admin panel
│           ├── layout-wrapper.php    # Sidebar (same as admin but with frontend URLs)
│           ├── dashboard.php         # Same as admin dashboard
│           ├── bid-list.php          # Same as admin bid-list
│           ├── bid-edit.php          # Full bid edit + evaluation matrix + scoring + comparison + award
│           ├── bidders.php           # Same as admin bidders
│           ├── contacts.php          # Same as admin contacts
│           ├── messages.php          # Same as admin messages
│           ├── downloads.php         # Same as admin download-log
│           ├── online-bids.php       # Same as admin online-bids
│           ├── scm-docs.php          # Same as admin compliance-docs
│           ├── users.php             # Same as admin users
│           ├── settings.php          # Same as admin settings (frontend version)
│           └── access-denied.php     # 403 page
│
└── templates/                        # Page + email templates (theme-overridable)
    ├── page-eprocurement.php         # Bare HTML wrapper (bypasses theme)
    └── email/
        ├── _header.php               # Shared email header (gradient banner, brand logo)
        ├── _footer.php               # Shared email footer (brand tagline, support email)
        ├── verification.php          # Bidder email verification
        ├── briefing-invite.php       # Briefing attendance invitation
        ├── new-query.php             # Staff notification: new query received
        ├── new-reply.php             # Bidder notification: staff replied
        ├── closing-reminder.php      # 48h/24h closing reminder
        ├── award-winner.php          # Award notification (winner)
        ├── award-loser.php           # Award notification (non-winner)
        └── submission-confirmation.php # Bidder submission confirmation
```

### 3.2 Database Schema (14 tables)

All tables prefixed `{wp_prefix}_eproc_`:

| # | Table | Purpose |
|---|-------|---------|
| 1 | `documents` | Tender/bid records (status, dates, contacts, submission_mode, award info, reminder flags) |
| 2 | `contact_persons` | SCM + Technical contacts (linked to WP users) |
| 3 | `supporting_docs` | Tender document files (also stores per-document bidder uploads) |
| 4 | `compliance_docs` | SCM Documents library |
| 5 | `threads` | Query threads (bidder ↔ staff) |
| 6 | `messages` | Individual messages in threads |
| 7 | `message_attachments` | Files attached to messages |
| 8 | `downloads` | Download audit log |
| 9 | `bidder_profiles` | Bidder company info + verification tokens |
| 10 | `bid_submissions` | Bid submissions (file, status, timestamps, backdate info) |
| 11 | `briefing_attendees` | Compulsory briefing attendance list |
| 12 | `evaluation_criteria` | Per-tender scoring criteria (name, weight, max_score) |
| 13 | `evaluation_scores` | Per-submission × criterion × evaluator scores |
| 14 | `submission_requirements` | Per-tender required document fields (for per-document mode) |

### 3.3 Custom Roles

| Role slug | Label | Capabilities |
|-----------|-------|-------------|
| `eprocurement_scm_manager` | SCM Manager | Full bid lifecycle, evaluation, award, no settings |
| `eprocurement_scm_official` | SCM Official | Bid management, no delete, no settings |
| `eprocurement_unit_manager` | Unit Manager | Query inbox + reply only |
| `eprocurement_subscriber` | eProcurement Bidder | Frontend portal access |

WordPress Administrator and Editor also receive procurement capabilities.

### 3.4 REST API

- **Public namespace:** `eprocurement/v1/` (14 endpoints)
- **Admin namespace:** `eprocurement/v1/admin/` (43 endpoints)
- **Authentication:** WordPress cookie + `X-WP-Nonce` header (default WP REST)
- **CORS:** Configurable via Settings → Advanced (wildcard `*` cannot combine with credentials)

### 3.5 Cron Events

| Hook | Schedule | Purpose |
|------|----------|---------|
| `eprocurement_daily_cleanup` | Daily | Auto-close expired bids + archive old closed bids |
| `eprocurement_hourly_reminder_check` | Hourly | Send 48h + 24h closing reminders to interested bidders |
| `eprocurement_weekly_digest` | Weekly (Mon 9AM) | Activity summary email to SCM Managers |

---

## 4. SECURITY MEASURES IMPLEMENTED

### 4.1 Critical Fixes (v2.14.0)

1. **OAuth CSRF prevention** — `state` parameter on all cloud-storage OAuth flows
2. **Sealed local storage** — `.htaccess` denies ALL direct HTTP access + PHP engine off
3. **IDOR fix** — attachment downloads verify thread participation
4. **Supply-chain integrity** — GitHub release body sanitised with `wp_kses_post()` + SHA-256 checksum verification

### 4.2 High-Severity Fixes

- DOM XSS prevention (all REST error messages escaped)
- Sealed-bid ZIP capability tightened to `eproc_publish_bids`
- Contact ID validation on thread creation
- Per-file download nonces
- CORS `Allow-Credentials` + wildcard fix
- `AUTH_KEY` enforcement (no fallback key)
- Mailpit dev block gated on `EPROC_DEV_MODE` / `wp_get_environment_type()`
- `wp_mail_content_type` filter leak fix
- HTTP_ORIGIN sanitisation

### 4.3 Ongoing Protections

- AES-256-CBC encryption for all credentials at rest
- SSRF prevention on external DB connector (RFC1918/link-local blocked)
- Rate limiting on bidder registration (5/hour per IP)
- Immutable audit log for bid backdating
- 2FA (TOTP) for staff users
- Security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy)
- User enumeration prevention

---

## 5. KEY DESIGN DECISIONS

### 5.1 Why Two Interfaces (wp-admin + Frontend Manage Panel)?

The plugin provides two parallel interfaces:
- **wp-admin** (admin/ directory) — for Super Admins who prefer the WordPress backend
- **Frontend manage panel** (public/partials/manage/) — for SCM staff who access via `/tenders/manage/`

The frontend panel is the **primary** interface. The admin version exists for backward
compatibility and for Super Admin tasks (settings, user management). Both share the same
REST API backend, so data is always consistent.

**For Claude Code:** When testing, use the frontend manage panel at `/tenders/manage/` as
the primary SCM interface. The admin panel at `/wp-admin/admin.php?page=eprocurement` is
secondary.

### 5.2 Why the Evaluation Card Only Shows on Closed Bids

Evaluation happens post-closing. The `Eprocurement_Evaluation` model and the evaluation
UI card on `bid-edit.php` are gated behind `$current_status === 'closed'`. This prevents
premature scoring and ensures the sealed-bid integrity (no one can score submissions
before the closing date).

### 5.3 Why Per-Document Files Are Stored as `supporting_docs`

When a bidder submits multiple files in per-document mode, the first file becomes the
primary `bid_submissions` record, and additional files are stored as `supporting_docs`
with `uploaded_by` set to the bidder's user ID. This reuses the existing storage
infrastructure without needing a separate table. The ZIP download matches these back
to submissions by `user_id` + timestamp proximity.

### 5.4 Why Branding Uses CSS Custom Properties

The plugin defines all colours as CSS custom properties (`--eproc-primary`, etc.) on
`.eproc-wrap` and `.eproc-admin-shell`. The `inject_brand_css()` method in
`class-public.php` generates a `<style>` tag that overrides these properties based on:
1. Theme.json palette detection (if "Inherit Theme Colors" is enabled)
2. Plugin settings (manual colour pickers)
3. `eproc_brand_colors` filter (for programmatic override)

This allows the plugin to match any municipal website's branding without touching the
plugin's CSS files.

### 5.5 Why 2FA Only Affects Staff

The `Eprocurement_Two_Factor` class checks `Eprocurement_Roles::is_staff()` before
enabling 2FA on a user's profile and before intercepting the login flow. Bidders
(`eprocurement_subscriber` role) are never prompted for 2FA — this keeps the bidder
registration/login flow frictionless.

---

## 6. TESTING GUIDE FOR CLAUDE CODE

### 6.1 Environment Setup

```bash
# The plugin requires:
PHP 8.1+
WordPress 6.0+
MySQL 5.7+ / MariaDB 10.3+
Composer (for Google API + AWS SDK)

# Install dependencies:
cd eprocurement/eprocurement
composer install

# The docker-compose.yml in the repo root provides a dev environment:
cd /path/to/repo
docker-compose up -d
```

### 6.2 Critical Test Paths

**Test 1: SCM creates a tender with per-document submission**
1. Login as SCM Manager at `/tenders/manage/`
2. Click "Add New" → fill in bid number, title, description
3. Set opening/closing/Q&A deadline dates
4. Check "Accept Online Submissions"
5. Select "Per Document" submission mode
6. Verify "Required Documents" card appears
7. Add document requirements using presets (Tax, BBBEE, etc.)
8. Save draft → verify all fields persisted (check DB)
9. Publish → verify status changes to 'open'
10. Verify activity log records the publication

**Test 2: Bidder registers and submits a per-document bid**
1. Visit `/tenders/register/`
2. Fill in registration form → verify password strength meter works
3. Check email for verification link → click it
4. Login at `/tenders/login/`
5. Browse tenders → verify search autocomplete works
6. Click a tender → verify countdown timer shows
7. Submit a query → verify it works
8. Try to retract the query → verify "Retract" button works
9. Submit a bid (per-document) → upload each required file
10. Verify submission confirmation email is received
11. Check dashboard → verify "Awarded" or "Not Awarded" badge shows after award

**Test 3: SCM evaluates and awards**
1. Close the tender (status: open → closed)
2. Verify "Evaluation Matrix" card appears on bid-edit
3. Add evaluation criteria (e.g. Technical: weight 3, Price: weight 2)
4. Click "Score" on each submission → enter scores → save
5. Click "Compare & Award" → verify ranked view + side-by-side toggle
6. Click "Export CSV" → verify CSV download
7. Click "Award" on the winner → fill in contract value + notes → confirm
8. Verify award-winner email sent to winner
9. Verify award-loser email sent to non-winners
10. Verify "Awarded" badge appears on tender listing
11. Verify award banner appears on tender detail page
12. Download submissions ZIP → verify company folders + per-document files + summary CSV

**Test 4: Branding adaptability**
1. Go to Settings → Branding
2. Check "Inherit colours from the active WordPress theme"
3. Save settings
4. Visit `/tenders/` → verify colours match the theme
5. Uncheck → set manual colours → verify colours change
6. Verify `eproc_brand_colors` filter works (add to theme's functions.php)

**Test 5: 2FA**
1. As a staff user, go to wp-admin → Profile
2. Enable 2FA → scan QR code → enter 6-digit code
3. Logout → login again → verify 2FA prompt appears
4. Enter code → verify login completes
5. Login as a bidder → verify NO 2FA prompt (bidders are exempt)

### 6.3 Known Limitations

1. **No unit tests** — the codebase has visual Playwright tests in `tests/visual/`
   but no PHP unit tests. The code is structured for testability (dependency injection
   would be needed — currently classes `new` their dependencies inline).

2. **QR code for 2FA** — uses `api.qrserver.com` fetched server-side. In air-gapped
   environments, the QR won't render but the manual secret entry is always shown.

3. **No `declare(strict_types=1)`** — the plugin uses PHP 8 typed signatures but
   doesn't declare strict_types. Adding it would be a P2 refactoring task.

4. **Admin bid-edit lacks full requirements card** — the interactive requirements
   CRUD card (with AJAX add/delete) only exists in the frontend manage panel. The
   wp-admin version shows the submission mode radio with a link to the manage panel.
   This is by design — the manage panel is the primary SCM interface.

5. **`eproc-info-box` CSS** — the class definitions still exist in `frontend.css`
   (10 rules) but are no longer referenced by any JS or PHP. They're dead weight
   but harmless. Can be removed in a future cleanup.

### 6.4 Database Migration

The plugin auto-migrates on version change via `eprocurement_maybe_upgrade()` in
`eprocurement.php`. The migration checks `version_compare($installed_version, X, '<')`
and runs `ALTER TABLE` + `dbDelta()` as needed. No manual migration steps required.

**For existing v2.13.1 installations:** simply update the plugin — all migrations
from v2.14.0 through v2.16.6 run automatically on the first page load after update.

---

## 7. OPTIONS REFERENCE

All plugin options are prefixed `eprocurement_`:

| Option | Type | Purpose |
|--------|------|---------|
| `eprocurement_version` | string | Current installed version (for migration gating) |
| `eprocurement_cloud_provider` | string | Active storage provider (local/google_drive/onedrive/dropbox/s3) |
| `eprocurement_cloud_credentials` | string (encrypted) | Cloud storage credentials (AES-256-CBC) |
| `eprocurement_brand_name` | string | Organisation name (shown in sidebar, emails) |
| `eprocurement_brand_url` | string | Organisation website URL |
| `eprocurement_support_email` | string | Support contact email |
| `eprocurement_brand_logo` | string | Logo URL (shown in sidebar, emails) |
| `eprocurement_brand_colors` | string (JSON) | Primary + secondary colours |
| `eprocurement_inherit_theme_colors` | string ('0'/'1') | Inherit colours from theme.json |
| `eprocurement_frontend_page_slug` | string | Frontend page slug (default: 'tenders') |
| `eprocurement_smtp_settings` | string (encrypted) | SMTP configuration |
| `eprocurement_notification_settings` | string (JSON) | Email notification toggles |
| `eprocurement_cors_origins` | string | Comma-separated allowed CORS origins |
| `eprocurement_external_db_settings` | string (encrypted) | External DB connection for user sync |
| `eprocurement_delete_data_on_uninstall` | string ('0'/'1') | Data deletion gate |
| `eproc_audit_log` | array (autoload=false) | Immutable bid backdating audit trail |
| `eproc_activity_log` | array (autoload=false) | Dashboard activity feed (max 200 entries) |
| `eproc_github_latest_release` | object (transient) | Cached GitHub release info (12h TTL) |
| `eproc_storage_ok_{provider}` | int (transient) | Cached storage connection test (5min TTL) |
| `eproc_last_auto_close` | int (transient) | Throttle for auto_close_expired_bids (5min) |

---

## 8. ACTION HOOKS REFERENCE

### Custom Actions (fired by the plugin)

| Hook | Args | Purpose |
|------|------|---------|
| `eprocurement_bid_published` | `$document_id` | Tender published (draft → open) |
| `eprocurement_status_changed` | `$doc_id`, `$new_status`, `$old_status` | Any status transition |
| `eprocurement_query_created` | `$thread_id`, `$message_id` | New query submitted by bidder |
| `eprocurement_reply_posted` | `$thread_id`, `$message_id` | Staff or bidder replied |
| `eprocurement_visibility_changed` | `$thread_id`, `$old`, `$new`, `$reason` | Query visibility changed |
| `eprocurement_bid_submitted` | `$submission_id`, `$document_id`, `$user_id`, `$is_late` | Bid submitted |
| `eprocurement_bid_cancelled` | `$submission_id`, `$document_id`, `$user_id` | Bid cancelled |
| `eprocurement_bid_awarded` | `$document_id`, `$winner_user_id` | Tender awarded |

### Custom Filters

| Filter | Args | Purpose |
|--------|------|---------|
| `eproc_brand_colors` | `$overrides` | Override brand colours programmatically |
| `eproc_keep_styles` | `$styles` | Preserve additional CSS handles on eProcurement pages |
| `eproc_keep_scripts` | `$scripts` | Preserve additional JS handles on eProcurement pages |
| `eprocurement_registration_rate_limit` | `5` | Override registration rate limit per hour |
| `eprocurement_use_gravatar` | `false` | Enable Gravatar for avatars (disabled by default) |
| `eprocurement_allow_internal_db_host` | `false` | Allow RFC1918 hosts for external DB (SSRF override) |

---

## 9. FILE PERMISSIONS

| Path | Permissions | Notes |
|------|-------------|-------|
| `wp-content/uploads/eprocurement/` | 0755 | Local storage directory |
| `wp-content/uploads/eprocurement/.htaccess` | 0644 | `Deny from all` + `Require all denied` + PHP engine off |
| Plugin PHP files | 0644 | Standard |
| `composer.json` | 0644 | Defines SDK dependencies |

---

## 10. THINGS TO WATCH OUT FOR

### 10.1 AUTH_KEY Requirement

The plugin **refuses to encrypt credentials** if `AUTH_KEY` is not defined in
`wp-config.php`. This is by design (security fix H-06). If a site has the default
WordPress placeholder `put your unique phrase here`, the plugin throws a
`RuntimeException`. Generate proper salts at
https://api.wordpress.org/secret-key/1.1/salt/

### 10.2 Bluehost Endurance Page Cache

The plugin excludes eProcurement pages from Bluehost's EPC via the
`epc_exempt_uri_contains` filter. If the site uses a different caching plugin
(WP Rocket, W3 Total Cache, etc.), eProcurement pages must be excluded from
page caching — the countdown timer, submission status, and query inbox are all
dynamic.

### 10.3 Cloud Storage OAuth

OAuth callbacks for Google Drive, OneDrive, and Dropbox use a `state` parameter
stored in a per-user transient (10-minute TTL). If two Super Admins initiate
OAuth flows simultaneously, their states are isolated by user ID. The callback
URL is `/wp-admin/admin.php?page=eprocurement-settings&eproc_oauth_callback={provider}&code={auth_code}&state={state}`

### 10.4 Per-Document Submission File Matching

When a bidder submits multiple files in per-document mode, the additional files
are stored as `supporting_docs` with `uploaded_by = bidder's user_id`. The ZIP
download matches these back to submissions by checking if `created_at` is within
1 hour of the submission's `submitted_at`. This is a heuristic — if a bidder
uploads tender documents (admin-uploaded) and then submits a bid within 1 hour,
those admin docs might be included in the ZIP. The 1-hour window is a trade-off
between accuracy and clock drift.

### 10.5 Evaluation Scoring Formula

The weighted total is computed as:
```
SUM(avg_score / max_score * weight) / SUM(weight) * 100
```
This normalises to 0-100 regardless of how many criteria exist or what their
max_scores are. Multiple evaluators' scores are averaged per criterion before
the weighted total is computed.

---

## 11. CONTACT

- **Author:** MyBliss Tech (https://www.myblisstech.com)
- **Repository:** github.com/MyBlissIT/eprocurement
- **Security reports:** security@myblisstech.com
- **Self-update:** GitHub Releases (tagged versions, SHA-256 verified)
