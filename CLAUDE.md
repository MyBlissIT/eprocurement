# eProcurement WordPress Plugin — Project Reference

> **Last updated:** 2026-03-04
> **Author:** MyBliss Technologies
> **Plugin version:** 2.10.3
> **PHP:** 8.0+ | **WordPress:** 6.0+

---

## Purpose

A mini-CRM WordPress plugin for government/corporate procurement processes. Manages bid/tender notices, structured communication between procurement officials and prospective bidders, cloud-based document storage, download auditing, and role-based access control. The admin UI is **fully custom** with no WordPress chrome visible on eProcurement pages, while native WP admin pages (Plugins, Themes, Pages, Settings) retain their standard sidebar with MyBliss-branded styling.

---

## Development Environment

| Component | Details |
|-----------|---------|
| Docker Compose | `docker-compose.yml` (root of eProcument Plugin folder) |
| WordPress container | `eproc-wp` — port **8190** → 80, image `wordpress:6.7-php8.2-apache` |
| MySQL container | `eproc-db` — port **3307** → 3306 |
| Mailpit container | `eproc-mailpit` — SMTP **1025**, Web UI **http://localhost:8191** |
| WP-CLI container | `eproc-cli` — run one-off WP commands |
| Database | `eproc_wp`, user: `wpuser`, password: `wppassword`, root: `rootpassword` |
| WordPress admin | `http://localhost:8190/wp-admin/` — **admin / admin123** |
| Frontend URL | `http://localhost:8190/tenders/` |
| Plugin mount | `./eprocurement` → `/var/www/html/wp-content/plugins/eprocurement` |
| MU-plugins mount | `./mu-plugins` → `/var/www/html/wp-content/mu-plugins` |
| Claude Preview | launch.json entry `"eprocurement"` at port 8190 |

### Starting the environment
```bash
# Start Docker Desktop first, then:
docker compose -f "C:/Users/sinet/OneDrive/Documents/MyBliss Technologies/Website Development/Plugins/Custom Plugins/eProcument Plugin/docker-compose.yml" up -d

# Or via Claude Preview:
# launch.json already configured as "eprocurement" on port 8190
```

### WP-CLI inside container
```bash
docker exec eproc-wp bash -c "curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"
docker exec eproc-wp wp --allow-root <command>

# Flush rewrite rules (needed after changing sub-page routing):
docker exec eproc-wp wp rewrite flush --allow-root
```

---

## File Structure

```
eProcument Plugin/
├── docker-compose.yml                        # Dev environment (4 services)
├── CLAUDE.md                                 # THIS FILE
├── .gitignore                                # Git ignore rules
├── .github/
│   └── workflows/
│       └── release.yml                      # GitHub Actions: build ZIP on tag push
├── mu-plugins/                               # MU-plugins (mounted into container)
│   ├── sme-admin-customizations.php         # MyBliss branding v5.0: login, admin bar hidden, scoped sidebar hide, WP sidebar restyled, profile cleanup, security
│   └── sme-assets/
│       └── mybliss-logo.png                 # MyBliss Technologies logo
└── eprocurement/                             # The WordPress plugin
    ├── eprocurement.php                      # Main plugin file, constants, autoloader
    ├── uninstall.php                         # Cleanup on uninstall
    ├── composer.json                         # Dependencies
    ├── bundled-mu/                           # MU-plugin (auto-installed on activation)
    │   ├── sme-admin-customizations.php     # MyBliss branding, security, admin overhaul
    │   └── sme-assets/
    │       └── mybliss-logo.png             # MyBliss Technologies logo
    │
    ├── includes/                             # Core business logic
    │   ├── class-activator.php              # Activation: tables, roles, options, page creation
    │   ├── class-deactivator.php            # Deactivation: clear cron
    │   ├── class-database.php               # DB helper (table names, get_by_id, etc.)
    │   ├── class-roles.php                  # 4 custom roles + 14 capabilities
    │   ├── class-documents.php              # Bid/tender CRUD, status transitions
    │   ├── class-contact-persons.php        # Contact person CRUD
    │   ├── class-bidder.php                 # Registration, email verification (48hr token)
    │   ├── class-messaging.php              # Q&A threads, access control, read tracking
    │   ├── class-notifications.php          # Email hooks (all default OFF except verification)
    │   ├── class-compliance-docs.php        # Static document library
    │   ├── class-downloads.php              # Secure download endpoint + audit logging + date/search filters
    │   ├── class-access-control.php         # wp-admin restriction, login redirect, security hardening
    │   ├── class-rest-api.php               # Public REST API (eprocurement/v1) — 10 endpoints
    │   ├── class-admin-rest-api.php         # Admin REST API (eprocurement/v1/admin) — 28 endpoints
    │   ├── class-branding.php               # Dynamic tenant branding (name, logo, colors, login)
    │   ├── class-demo-data.php              # Demo data seeder/remover (users, bids, contacts, threads)
    │   ├── class-updater.php                # Self-update via GitHub Releases API
    │   ├── class-smtp.php                   # SMTP configuration from plugin settings
    │   ├── class-storage-interface.php      # Abstract cloud storage + AES-256-CBC encryption
    │   └── storage/                         # Cloud provider implementations
    │       ├── class-google-drive.php
    │       ├── class-onedrive.php
    │       ├── class-dropbox.php
    │       ├── class-s3.php
    │       └── class-local-storage.php      # Local filesystem fallback (wp-content/uploads/eprocurement/)
    │
    ├── admin/                               # Admin backend (completely custom UI)
    │   ├── class-admin.php                  # Menu registration, AJAX handlers, layout wrapper
    │   ├── admin-shell.css                  # Admin shell/sidebar/layout styles + CSS variables
    │   ├── admin.css                        # Admin component styles (cards, tables, forms)
    │   ├── admin.js                         # Admin JS (AJAX, modals, uploads, datepicker)
    │   ├── themes/                          # Legacy theme variants (not actively used)
    │   │   ├── theme-slate.css
    │   │   ├── theme-teal.css
    │   │   └── theme-indigo.css
    │   └── partials/                        # Admin page templates
    │       ├── layout-wrapper.php           # LEFT SIDEBAR nav shell (compact, User Mgmt + Back to Portal + Logout nav items)
    │       ├── dashboard.php                # Stat cards + recent activity
    │       ├── bid-list.php                 # Bid listing with filters (status hidden for non-bid categories)
    │       ├── bid-edit.php                 # Two-column bid form + doc upload + Save & Open
    │       ├── messages.php                 # Two-pane messaging + bid preview modal + Open filter
    │       ├── contact-persons.php          # Contact directory table
    │       ├── bidders.php                  # Registered bidders + search + CSV export
    │       ├── compliance-docs.php          # SCM document library
    │       ├── download-log.php             # Download audit log + date picker + search autofill
    │       └── settings.php                 # General, notifications, cloud storage
    │
    ├── public/                              # Frontend (completely custom UI)
    │   ├── class-public.php                 # Shortcode handler, routing, nav, helpers
    │   ├── frontend.css                     # All frontend styles (~2000 lines)
    │   ├── frontend.js                      # Frontend JS (modals, AJAX, tabs, threads)
    │   └── partials/                        # Frontend page templates
    │       ├── tender-listing.php           # Hero + filter bar + card grid
    │       ├── tender-detail.php            # Dates, contacts, documents, Q&A, query modal
    │       ├── bidder-login.php             # Login form + forgot password link
    │       ├── bidder-register.php          # Registration form (REST API POST)
    │       ├── bidder-dashboard.php         # Tabbed: My Queries / My Downloads / My Profile
    │       └── compliance-docs.php          # Public compliance document list
    │
    └── templates/                           # Email templates
        └── email/
            ├── verification.php             # Registration verification email
            ├── new-query.php                # New query notification
            └── new-reply.php                # Reply notification
```

---

## Architecture & Key Decisions

### Plugin Constants
```php
EPROC_VERSION       = '2.10.3'      // Used for CSS/JS cache busting
EPROC_PLUGIN_DIR    = plugin_dir_path(__FILE__)
EPROC_PLUGIN_URL    = plugin_dir_url(__FILE__)
EPROC_TABLE_PREFIX  = 'eproc_'     // All 9 tables: wp_eproc_*
EPROC_GITHUB_REPO   = 'MyBlissIT/eprocurement'  // For self-updater
```

### Database Schema (9 custom tables)
All tables prefixed `wp_eproc_`:

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `documents` | Bid/tender records | bid_number, title, category (bid/briefing_register/closing_register/appointments), status (enum: draft/open/closed/cancelled/archived), opening_date, briefing_date, closing_date, scm_contact_id, technical_contact_id |
| `contact_persons` | SCM and Technical contacts | type (enum: scm/technical), name, email, phone, department, user_id (nullable WP link) |
| `supporting_docs` | Files per bid (cloud-stored) | document_id (FK), file_name, cloud_provider, cloud_key, cloud_url, label, sort_order |
| `compliance_docs` | Static document library | file_name, cloud_provider, cloud_key, cloud_url, label, description |
| `threads` | Query conversations | document_id, bidder_id, contact_id, subject, visibility (private/public), status (open/resolved/closed) |
| `messages` | Individual messages in threads | thread_id (FK), sender_id, message, is_read |
| `message_attachments` | Cloud files on messages | message_id (FK), file_name, cloud_provider, cloud_key |
| `downloads` | Audit log | document_id, supporting_doc_id, user_id, ip_address, user_agent, downloaded_at |
| `bidder_profiles` | Extended bidder info | user_id (unique), company_name, company_reg, phone, verified, verification_token, token_expires_at |

### Bid Categories & Status Workflow
- **Regular Bids** (`category = 'bid'`): Full workflow — Draft → Open → Closed → Archived (+ Cancelled). Has Key Dates, Contact Persons, status filters.
- **Briefing Register, Closing Register, Appointments**: **NO status workflow**. No status filters, no Change Status dropdown, no status badge. Simple entries with Bid Number + Description + Documents only.

### Custom Roles (4)
- **eProcurement SCM Manager** (`eprocurement_scm_manager`): Full procurement management
- **eProcurement SCM Official** (`eprocurement_scm_official`): Limited procurement operations
- **eProcurement Unit Manager** (`eprocurement_unit_manager`): Read-only + basic actions
- **eProcurement Bidder** (`eprocurement_bidder`): Maps to WP Subscriber role, extended with bidder_profiles table

### Frontend Routing
The `[eprocurement]` shortcode on the "tenders" page handles all frontend views:

| URL | View | Auth Required |
|-----|------|---------------|
| `/tenders/` | Tender listing | No |
| `/tenders/bid/123/` | Bid detail page (clean URL) | No |
| `/tenders/register/` | Bidder registration | No (redirects if logged in) |
| `/tenders/login/` | Bidder login + forgot password | No (redirects if logged in) |
| `/tenders/my-account/` | Bidder dashboard | Yes (bidder role) |
| `/tenders/compliance/` | Compliance documents | No |

**Routing mechanism**: WordPress rewrite rules:
- `^tenders/bid/(\d+)/?$` → clean bid URLs with `eproc_bid_id` query var
- `^tenders/([^/]+)/?$` → sub-page routing with `eproc_route` query var
- `Eprocurement_Public::bid_url( $id )` — static helper for generating bid URLs
- Legacy `?bid=123` parameter still supported as fallback

### REST API Endpoints (eprocurement/v1)

**Public API** (`class-rest-api.php`) — 10 endpoints:

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/documents` | GET | Public | List published/open/closed bids |
| `/documents/{id}` | GET | Public | Single bid with contacts, files, public Q&A |
| `/register` | POST | Public | Bidder registration |
| `/verify` | GET | Public | Email verification token check |
| `/query` | POST | Verified bidder | Submit new query (+ optional attachment) |
| `/reply` | POST | Staff or bidder | Reply to thread |
| `/threads` | GET | Logged in | List threads (bidder or staff inbox) |
| `/threads/{id}` | GET | Logged in | Thread detail with messages |
| `/compliance-docs` | GET | Public | List compliance documents |
| `/upload` | POST | Staff (upload cap) | Upload file to cloud storage |

**Admin API** (`class-admin-rest-api.php`) — 28 endpoints for frontend-admin panel (bids CRUD, contacts, messages, settings, bidders, compliance docs, downloads, dashboard stats). All require staff capabilities.

### Admin UI Architecture
- **WP admin bar completely hidden** via `show_admin_bar` → `__return_false` + CSS
- **WP admin sidebar**: hidden on eProcurement pages (`.eproc-admin-page` body class), visible and restyled (navy/maroon branding) on native WP pages (Plugins, Themes, Pages, Settings)
- **WP Dashboard**: accessible to Super Admin directly; non-Super-Admin redirected to eProcurement Dashboard
- **Custom sidebar navigation** in `layout-wrapper.php` — compact left sidebar with logo, nav items (including User Management, WordPress Admin link, Back to Portal, Logout), simplified footer showing user name + role only
- **Floating nav bar** on WP native pages: maroon background, brand name + "Back to eProcurement" + "View Portal" links, positioned at `left: 160px` to clear WP sidebar
- Every eProcurement admin page: `open_layout() → require partial → close_layout()`
- All admin CSS in `admin-shell.css` (layout + CSS variables) + `admin.css` (components)
- CSS class prefix: `.eproc-admin-*` for shell, `.eproc-*` for components
- **CSS custom properties** on `.eproc-admin-shell` for multi-tenant color adaptability
- **All buttons use standard maroon** — no green, no other accent colors on buttons
- `admin_body_class` filter adds `.eproc-admin-page` on eProcurement screens for CSS scoping

### Frontend UI Architecture
- Completely custom CSS in `frontend.css` (~2000 lines)
- CSS class prefix: `.eproc-*` (no `.eproc-admin-` prefix)
- Navigation bar: `.eproc-navbar` with hamburger toggle at 768px
- All pages wrapped in `.eproc-wrap`
- JavaScript in `frontend.js` using jQuery (WP bundled)

### Cloud Storage
- Abstract `Eprocurement_Storage_Interface` with factory pattern
- 5 providers: Google Drive, OneDrive, Dropbox, S3, **Local Storage** (fallback)
- Local storage: `wp-content/uploads/eprocurement/` — automatic fallback if configured provider fails
- Credentials encrypted with AES-256-CBC using WordPress `AUTH_KEY`
- File validation: 50MB max, MIME whitelist, extension spoofing check
- Downloads: nonce-protected endpoint, generates time-limited cloud URLs

### Email/Notifications
- Registration verification: **always enabled** (not toggleable)
- All other notifications: **default OFF**, toggleable in settings
- **Per-bidder chat notification toggle**: `notify_replies` column in `bidder_profiles` (default: ON)
  - Bidders can toggle via query modal checkbox or dashboard profile
  - Staff sees notification indicator in messages panel
  - `notify_reply()` checks preference before sending email
- Hooks: `eprocurement_query_created`, `eprocurement_reply_posted`, `eprocurement_status_changed`, `eprocurement_bid_published`, `eprocurement_visibility_changed`

### Multi-Tenant / Branding System
- **`Eprocurement_Branding` class** (`class-branding.php`): Static methods for brand name, URL, logo, colors, login title
- All values read from per-site `get_option()` with MyBliss Technologies defaults
- Settings page has "Branding" section: name, URL, email, logo, login title, color pickers
- `inline_css()` generates `<style>` block overriding `--eproc-*` CSS variables on both `.eproc-wrap` and `.eproc-admin-shell`
- MU-plugin uses `sme_get_brand()` helper to bridge to branding class (with constant fallbacks)
- **Multisite support**: Network-wide activation iterates all sites; `wp_initialize_site` hook provisions new sites
- Frontend CSS: 35 custom properties on `.eproc-wrap`, 317 `var()` references (fully refactored from hardcoded colors)
- Admin CSS: Custom properties on `.eproc-admin-shell` (already existed)

### Color Scheme (MyBliss Technologies Branding)
```
Primary Maroon:  #8b1a2b   (all buttons, links, accents)
Maroon Hover:    #6d1522
Maroon Light:    #a52040
Dark Navy:       #1a1a5e   (sidebar background)
Content BG:      #f1f5f9
Card border:     #e2e8f0
Red:             #e74c3c   (danger/delete)
Orange:          #f39c12   (warnings)
```
All colors defined as CSS custom properties on `.eproc-admin-shell` for multi-tenant override.
**All buttons must use standard maroon** — user explicitly rejected green and other accent colors.

---

## UI Redesign Status (7-Phase Plan)

**Overall completion: 100%** — All 7 phases complete. All 18 backend edits (BE-01 through BE-18) implemented.

---

## What's Working

- All 9 database tables create correctly on activation (21 total tables in DB after cleanup)
- Automatic database migration via `eprocurement_maybe_upgrade()` on version change
- Frontend page auto-created on activation (`class-activator.php`)
- Bid CRUD (create, edit, status transitions: Draft→Open→Closed→Archived, delete)
- Bid categories (Briefing Register, Closing Register, Appointments) — toggleable in Settings, **NO status workflow**
- Bid edit: "Save Draft" + "Save & Open Bid" buttons on new bids, "Update Bid" + "Open Bid" on existing drafts
- Bid edit: date validation on both Save and Open Bid buttons (`validateBidDates()`)
- Bid document upload/download with cloud storage (upload before save supported)
- **Clean bid URLs**: `/tenders/bid/123/` (with `?bid=123` fallback for backward compatibility)
- Contact person management (CRUD, auto WP user creation, department dropdown)
- Bidder registration with email verification (48hr token) — **E2E tested and passing**
- Email delivery via Mailpit in dev environment (verified working)
- Login/logout flow (WordPress POST with nonce) + **Forgot Password** link
- Messaging system (create thread, reply, staff attachments, mark resolved)
- Messages: bid title opens in **modal overlay** (iframe) preserving reply state; **"Open" thread filter** tab
- Visibility control (private/public threads) with bidder notification on change
- SCM document library (formerly "Compliance Documents")
- Download audit logging with CSV export, **date range picker**, **search with autofill**
- REST API — 38 endpoints functional (10 public + 28 admin)
- `[eprocurement]`, `[eprocurement_open]`, `[eprocurement_closed]` shortcodes (all with hero sections)
- Custom admin sidebar navigation with dynamic category items, User Management, WordPress Admin, Back to Portal, Logout links
- Custom admin UI on eProcurement pages (no WP UI chrome visible)
- **WP native admin pages** (Plugins, Themes, Pages, Settings) retain sidebar with navy/maroon branding
- Frontend: tender listing, bid detail, category pages, login, register, dashboard, compliance pages
- **All frontend titles/headings center-aligned**
- Responsive design (mobile hamburger nav at 768px)
- CSS cache busting via `EPROC_VERSION` constant
- Daily cron for expired bid archival + auto-close on page load
- MyBliss Technologies login page branding (Sunset Cityscape theme via MU-plugin)
- WP admin overhaul: admin bar hidden, eProcurement sidebar on plugin pages, WP sidebar on native pages
- Profile page cleaned up: only email, username, password, role visible
- CSS custom properties for multi-tenant color scheme (`--eproc-*` variables)
- Admin + Frontend fully rebranded: maroon (#8b1a2b) primary, navy (#1a1a5e) sidebar
- Access control: non-Super-Admin restricted from wp-admin, role-based login redirect
- Security hardening: XMLRPC disabled, user enumeration blocked, security headers, file editing disabled
- **Multi-tenant branding**: Eprocurement_Branding class, Settings > Branding section, MU-plugin dynamic
- **Frontend CSS fully refactored**: 35 CSS custom properties on `.eproc-wrap`, 317 var() references
- **Multisite activation**: Network-wide + wp_initialize_site for new sites
- **Per-bidder chat notification toggle**: notify_replies preference, default ON, toggleable in modal & profile
- **DEMO tenant**: 4 role users, 6 sample bids, 3 contacts, 2 Q&A threads, all settings enabled
- **Demo Data Seeder**: Settings > Demo Data card — seed/remove demo data with one click (Super Admin only)
- **MU-plugin auto-installation**: Bundled in `bundled-mu/`, auto-copied to `wp-content/mu-plugins/` on activation
- **Home→Tenders redirect**: 302 redirect from `/` to `/tenders/` (avoids static front page 301 issues)
- **254 automated tests passing** across 3 test suites
- Local filesystem storage fallback when no cloud provider configured
- Toast notification system (fixed-position slide-in) on admin pages

## What Needs Verification / May Have Issues

1. **Cloud storage OAuth flows**: Google Drive, OneDrive, Dropbox connect buttons require real API credentials to test
2. **S3 test connection**: Needs real S3 credentials
3. **Frontend visual verification**: All 10 pages confirmed to load (HTTP 200, zero PHP errors) but not fully screenshot-verified for pixel-perfect CSS

## Known Issues / Technical Debt
1. **Dashboard recent activity**: The `get_recent_activity()` UNION query in `class-admin.php` should be verified for performance on larger datasets
2. **Bidder dashboard JS**: Uses vanilla JS (not jQuery) for thread loading and profile update — minor inconsistency but works
3. **Front page setting**: Resolved — activator no longer sets tenders as static front page. A 302 redirect from `/` to `/tenders/` is used instead (controlled by `eprocurement_redirect_home` option)

## Installed Plugins

| Plugin | Version | Purpose |
|--------|---------|---------|
| eProcurement | 2.10.3 | The main procurement plugin (this project) |
| sme-admin-customizations (MU) | v5.0 | Dynamic tenant branding: login page, admin bar hidden, scoped sidebar hide, WP sidebar restyled, profile cleanup, security. **Auto-installed** from `bundled-mu/` on plugin activation. |

> **Removed plugins**: Wordfence and Really Simple SSL were removed (2026-03-04) — 25 Wordfence tables dropped, DB reduced from 46 → 21 tables. Security hardening now handled by MU-plugin + `class-access-control.php`.

---

## Key AJAX Handlers (admin/class-admin.php)

All use `wp_ajax_` hooks with nonce verification:

| Action | Purpose |
|--------|---------|
| `eproc_save_bid` | Create/update bid document |
| `eproc_delete_bid` | Delete bid + supporting docs |
| `eproc_change_status` | Transition bid status |
| `eproc_save_contact` | Create/update contact person |
| `eproc_delete_contact` | Delete contact person |
| `eproc_reply_message` | Staff reply to thread (with optional attachment) |
| `eproc_resolve_thread` | Mark thread as resolved |
| `eproc_upload_supporting_doc` | Upload file to cloud + attach to bid |
| `eproc_remove_supporting_doc` | Delete supporting doc from cloud + DB |
| `eproc_upload_compliance_doc` | Upload compliance document |
| `eproc_delete_compliance_doc` | Delete compliance document |
| `eproc_save_settings` | Save all plugin settings |
| `eproc_test_storage` | Test cloud storage connection |
| `eproc_resend_verification` | Resend bidder verification email |
| `eproc_export_downloads` | Export download log to CSV |
| `eproc_seed_demo_data` | Seed demo users, bids, contacts, threads |
| `eproc_remove_demo_data` | Remove all demo data cleanly |

> **Note**: Bidder export and most admin CRUD operations are handled via the Admin REST API (`class-admin-rest-api.php`), not AJAX.

---

## GitHub Repository & Version Control

| Detail | Value |
|--------|-------|
| **Repo** | `MyBlissIT/eprocurement` (public) |
| **URL** | https://github.com/MyBlissIT/eprocurement |
| **Branch** | `master` |
| **Current tag** | `v2.10.3` |
| **CI/CD** | GitHub Actions (`.github/workflows/release.yml`) |

### Self-Update Mechanism
- **`includes/class-updater.php`** hooks into WordPress plugin update system
- Checks GitHub Releases API every 12 hours (cached via transient)
- Client sites see "Update Available" in wp-admin when a new release exists
- Downloads `eprocurement.zip` from the release assets
- **No token needed** — repo is public, updates work like any standard plugin

### Release Flow
```bash
# 1. Make code changes and commit
git add <files>
git commit -m "Description of changes"
git push

# 2. When ready to release to clients — tag and push:
git tag v2.11.0
git push origin v2.11.0
# GitHub Actions auto-builds eprocurement.zip and creates a Release
# Client sites will see "Update Available" within 12 hours
```

> **Important**: `git push` saves code but does NOT trigger client updates. Only pushing a **tag** (`v2.x.x`) creates a release that clients receive.

### Claude Workflow Rule — GitHub Pushes
**ALWAYS ask the user for permission before pushing to GitHub.** After making code edits:
1. Commit changes locally (if asked to commit)
2. Ask: "Should I push these changes to GitHub?" — wait for confirmation
3. If releasing: Ask: "Should I tag this as vX.Y.Z and push the tag to create a release?" — wait for confirmation
4. **Never auto-push or auto-tag without explicit user approval**

### Environment Strategy
- **Local (Docker)** = Development + Testing environment
- **GitHub** = Version control + distribution to clients
- **Client sites (shared hosting / VPS)** = Production — receive updates via self-updater

---

## What's Next (Remaining Work)

### Should Do
1. **Deploy to shared hosting** — upload `eprocurement/` folder to `wp-content/plugins/`, activate, configure WP Mail SMTP
2. **MainWP dashboard** — set up centralized management for all client sites
3. **Visual verification** of admin and frontend pages via browser screenshots (pixel-perfect CSS check)
5. **Test cloud storage** with at least one real provider (S3 recommended for simplicity)
6. **Cross-browser check** the frontend (Chrome, Firefox, mobile Safari)

### Nice to Have
7. **Add unit tests** for core business logic classes
8. **Database indexes review** and query caching for dashboard stats on larger datasets
9. **Weekly Digest** notification (currently marked "Coming Soon" in Settings)
10. **Multi-tenant color override** — document/implement how host sites override `--eproc-*` CSS variables

### Completed (previously on this list)
- ~~E2E registration flow~~ — tested and passing (register → email → verify → login → query)
- ~~Frontend CSS rebrand~~ — fully rebranded to MyBliss maroon/navy
- ~~Wordfence configuration~~ — Wordfence removed entirely
- ~~Bid categories checkbox save~~ — root cause: duplicate handler in admin.js missing category fields + partial callers wiping settings. Fixed with defensive `isset()` guards + removed duplicate handler
- ~~REST API validation~~ — POST `/query` now returns 400 for missing `document_id` or empty `message`, 404 for non-existent document
- ~~MU-plugin auto-install~~ — bundled in `bundled-mu/`, auto-copied to `mu-plugins/` on activation
- ~~Demo data seeder~~ — Settings > Demo Data card (seed/remove), 4 users, 6 bids, 3 contacts, 2 threads
- ~~Front page redirect~~ — 302 redirect from `/` to `/tenders/` instead of static front page assignment
- ~~Self-update system~~ — `class-updater.php` checks GitHub Releases, auto-update via wp-admin
- ~~GitHub Actions CI/CD~~ — `.github/workflows/release.yml` auto-builds ZIP on tag push
- ~~Bluehost cache fix~~ — EPC exempt filter for tenders slug, `.htaccess` no-cache headers, rewrite rule exclusion
- ~~Demo data~~ — `demo-data.sql` with 5 tenders, 3 contacts, 3 bidders, 6 compliance docs, 12 supporting docs, 4 query threads with 7 messages, 9 download audit entries

---

## Troubleshooting

### CSS not updating
Bump `EPROC_VERSION` in `eprocurement.php` to bust browser cache:
```php
define( 'EPROC_VERSION', '2.10.3' ); // Increment this
```

### Sub-pages showing 404
Flush rewrite rules:
```bash
docker exec eproc-wp wp rewrite flush --allow-root
```

### Sub-pages showing 404 on Bluehost / shared hosting
Bluehost's Endurance Page Cache caches full HTML pages — including 404 responses. The plugin hooks the `epc_exempt_uri_contains` filter to exclude the tenders slug, but you also need to:
1. Add a `RewriteCond %{REQUEST_URI} !^/tenders` line before the EPC cache rewrite rule in `.htaccess`
2. Delete stale cached files: `rm -rf wp-content/endurance-page-cache/tenders/`
3. Add no-cache headers for `/tenders/` URLs at the top of `.htaccess`:
```apache
<IfModule mod_headers.c>
    <If "%{REQUEST_URI} =~ m#^/tenders(/.*)?$#">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires "0"
    </If>
</IfModule>
```

### Docker won't start
1. Ensure Docker Desktop is running
2. Remove `version: '3.8'` from `docker-compose.yml` if seeing warnings
3. Check port conflicts: 8190 (WP) and 3307 (MySQL)

### WP-CLI not available
WP-CLI is not pre-installed in the `wordpress:latest` image. Install it:
```bash
docker exec eproc-wp bash -c "curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"
```

### Plugin activation fails
Check PHP error log inside container:
```bash
docker exec eproc-wp cat /var/log/apache2/error.log
```

### Tenders page missing
The activator auto-creates the "tenders" page on activation. If missing:
```bash
docker exec eproc-wp wp post create --post_type=page --post_title="Tenders" --post_name="tenders" --post_content="[eprocurement]" --post_status=publish --allow-root
docker exec eproc-wp wp rewrite flush --allow-root
```
