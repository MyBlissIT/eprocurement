# eProcurement — Architecture Reference

> Detailed technical reference. For quick-start info, see the root `CLAUDE.md`.

---

## Plugin Constants

```php
EPROC_VERSION       = '2.10.3'      // CSS/JS cache busting
EPROC_PLUGIN_DIR    = plugin_dir_path(__FILE__)
EPROC_PLUGIN_URL    = plugin_dir_url(__FILE__)
EPROC_TABLE_PREFIX  = 'eproc_'     // All 9 tables: wp_eproc_*
EPROC_GITHUB_REPO   = 'MyBlissIT/eprocurement'  // Self-updater
```

---

## File Structure

```
eprocurement-repo/
├── CLAUDE.md                                 # Project instructions (slim)
├── .gitignore
├── .github/workflows/release.yml            # CI: build ZIP on tag push
├── docker-compose.yml                        # Dev environment (4 services)
├── setup.sh                                  # Dev setup script
├── setup-demo.php                            # Demo data PHP installer
├── wordfence-config.php                      # Legacy security config
├── mu-plugins/                               # MU-plugins (mounted into container)
│   ├── sme-admin-customizations.php         # MyBliss branding v5.0
│   └── sme-assets/mybliss-logo.png
├── docs/                                     # Non-production reference documents
│   ├── architecture.md                      # THIS FILE
│   ├── troubleshooting.md                   # Common issues & fixes
│   ├── brochure.html                        # Marketing brochure
│   ├── demo-credentials.txt                 # Demo user logins
│   ├── demo-data.sql                        # SQL seed data
│   ├── HOW-TO-UPDATE.txt                    # Plugin update guide
│   ├── eProcurement Plugin.docx             # Original spec
│   ├── eProcurement Edits.docx              # Design/edit notes
│   ├── eProcurement Code by Ollama.txt      # Early AI code notes
│   ├── eProcurement-System-Report-*.pdf     # System report
│   └── claude-spec-files/                   # Spec generation scripts + HTML
└── eprocurement/                             # THE WORDPRESS PLUGIN
    ├── eprocurement.php                      # Main file, constants, autoloader
    ├── uninstall.php                         # Cleanup on uninstall
    ├── composer.json
    ├── bundled-mu/                           # MU-plugin (auto-installed on activation)
    │   ├── sme-admin-customizations.php
    │   └── sme-assets/mybliss-logo.png
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
    │   ├── class-downloads.php              # Secure download + audit logging + date/search filters
    │   ├── class-access-control.php         # wp-admin restriction, login redirect, security hardening
    │   ├── class-rest-api.php               # Public REST API (eprocurement/v1) — 10 endpoints
    │   ├── class-admin-rest-api.php         # Admin REST API (eprocurement/v1/admin) — 28 endpoints
    │   ├── class-branding.php               # Dynamic tenant branding (name, logo, colors, login)
    │   ├── class-demo-data.php              # Demo data seeder/remover
    │   ├── class-updater.php                # Self-update via GitHub Releases API
    │   ├── class-smtp.php                   # SMTP configuration from plugin settings
    │   ├── class-storage-interface.php      # Abstract cloud storage + AES-256-CBC encryption
    │   └── storage/                         # Cloud provider implementations
    │       ├── class-google-drive.php
    │       ├── class-onedrive.php
    │       ├── class-dropbox.php
    │       ├── class-s3.php
    │       └── class-local-storage.php      # Local filesystem fallback
    │
    ├── admin/                               # Admin backend (completely custom UI)
    │   ├── class-admin.php                  # Menu registration, AJAX handlers, layout wrapper
    │   ├── admin-shell.css                  # Shell/sidebar/layout styles + CSS variables
    │   ├── admin.css                        # Component styles (cards, tables, forms)
    │   ├── admin.js                         # JS (AJAX, modals, uploads, datepicker)
    │   ├── themes/                          # Legacy theme variants (not actively used)
    │   └── partials/                        # Admin page templates
    │       ├── layout-wrapper.php           # LEFT SIDEBAR nav shell
    │       ├── dashboard.php                # Stat cards + recent activity
    │       ├── bid-list.php                 # Bid listing with filters
    │       ├── bid-edit.php                 # Two-column bid form + doc upload
    │       ├── messages.php                 # Two-pane messaging + bid preview modal
    │       ├── contact-persons.php          # Contact directory table
    │       ├── bidders.php                  # Registered bidders + search + CSV export
    │       ├── compliance-docs.php          # SCM document library
    │       ├── download-log.php             # Download audit log + date/search
    │       └── settings.php                 # General, notifications, cloud storage
    │
    ├── public/                              # Frontend (completely custom UI)
    │   ├── class-public.php                 # Shortcode handler, routing, nav, helpers
    │   ├── frontend.css                     # All frontend styles (~2000 lines)
    │   ├── frontend.js                      # JS (modals, AJAX, tabs, threads)
    │   └── partials/                        # Frontend page templates
    │       ├── tender-listing.php           # Hero + filter bar + card grid
    │       ├── tender-detail.php            # Dates, contacts, documents, Q&A
    │       ├── bidder-login.php             # Login form + forgot password
    │       ├── bidder-register.php          # Registration form (REST API POST)
    │       ├── bidder-dashboard.php         # Tabbed: Queries / Downloads / Profile
    │       └── compliance-docs.php          # Public compliance document list
    │
    └── templates/email/                     # Email templates
        ├── verification.php                 # Registration verification
        ├── new-query.php                    # New query notification
        └── new-reply.php                    # Reply notification
```

---

## Database Schema (9 custom tables)

All tables prefixed `wp_eproc_`:

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `documents` | Bid/tender records | bid_number, title, category (bid/briefing_register/closing_register/appointments), status (draft/open/closed/cancelled/archived), opening_date, briefing_date, closing_date, scm_contact_id, technical_contact_id |
| `contact_persons` | SCM and Technical contacts | type (scm/technical), name, email, phone, department, user_id (nullable WP link) |
| `supporting_docs` | Files per bid (cloud-stored) | document_id (FK), file_name, cloud_provider, cloud_key, cloud_url, label, sort_order |
| `compliance_docs` | Static document library | file_name, cloud_provider, cloud_key, cloud_url, label, description |
| `threads` | Query conversations | document_id, bidder_id, contact_id, subject, visibility (private/public), status (open/resolved/closed) |
| `messages` | Individual messages in threads | thread_id (FK), sender_id, message, is_read |
| `message_attachments` | Cloud files on messages | message_id (FK), file_name, cloud_provider, cloud_key |
| `downloads` | Audit log | document_id, supporting_doc_id, user_id, ip_address, user_agent, downloaded_at |
| `bidder_profiles` | Extended bidder info | user_id (unique), company_name, company_reg, phone, verified, verification_token, token_expires_at |

---

## Custom Roles (4)

| Role | Slug | Access Level |
|------|------|-------------|
| eProcurement SCM Manager | `eprocurement_scm_manager` | Full procurement management |
| eProcurement SCM Official | `eprocurement_scm_official` | Limited procurement operations |
| eProcurement Unit Manager | `eprocurement_unit_manager` | Read-only + basic actions |
| eProcurement Bidder | `eprocurement_bidder` | WP Subscriber + bidder_profiles |

---

## Frontend Routing

The `[eprocurement]` shortcode on the "tenders" page handles all views:

| URL | View | Auth Required |
|-----|------|---------------|
| `/tenders/` | Tender listing | No |
| `/tenders/bid/123/` | Bid detail (clean URL) | No |
| `/tenders/register/` | Bidder registration | No (redirects if logged in) |
| `/tenders/login/` | Bidder login + forgot password | No (redirects if logged in) |
| `/tenders/my-account/` | Bidder dashboard | Yes (bidder role) |
| `/tenders/compliance/` | Compliance documents | No |

**Routing mechanism**: WordPress rewrite rules:
- `^tenders/bid/(\d+)/?$` → `eproc_bid_id` query var
- `^tenders/([^/]+)/?$` → `eproc_route` query var
- `Eprocurement_Public::bid_url( $id )` — helper for generating bid URLs
- Legacy `?bid=123` parameter still supported as fallback

---

## REST API Endpoints (eprocurement/v1)

### Public API (`class-rest-api.php`) — 10 endpoints

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

### Admin API (`class-admin-rest-api.php`) — 28 endpoints

All require staff capabilities. Handles: bids CRUD, contacts, messages, settings, bidders, compliance docs, downloads, dashboard stats.

---

## AJAX Handlers (admin/class-admin.php)

All use `wp_ajax_` hooks with nonce verification:

| Action | Purpose |
|--------|---------|
| `eproc_save_bid` | Create/update bid document |
| `eproc_delete_bid` | Delete bid + supporting docs |
| `eproc_change_status` | Transition bid status |
| `eproc_save_contact` | Create/update contact person |
| `eproc_delete_contact` | Delete contact person |
| `eproc_reply_message` | Staff reply to thread (+ optional attachment) |
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

> Bidder export and most admin CRUD operations use the Admin REST API, not AJAX.

---

## Admin UI Architecture

- **WP admin bar**: completely hidden (`show_admin_bar` → `__return_false` + CSS)
- **WP admin sidebar**: hidden on eProcurement pages (`.eproc-admin-page` body class), visible and restyled (navy/maroon) on native WP pages
- **WP Dashboard**: Super Admin → direct access; others → redirected to eProcurement Dashboard
- **Custom sidebar**: `layout-wrapper.php` — compact left nav with logo, User Management, WordPress Admin link, Back to Portal, Logout
- **Floating nav bar** on WP native pages: maroon background, brand links, `left: 160px`
- **Layout pattern**: `open_layout() → require partial → close_layout()`
- **CSS files**: `admin-shell.css` (layout + variables) + `admin.css` (components)
- **CSS prefix**: `.eproc-admin-*` for shell, `.eproc-*` for components
- **Body class**: `admin_body_class` filter adds `.eproc-admin-page` for CSS scoping

---

## Frontend UI Architecture

- Custom CSS in `frontend.css` (~2000 lines)
- CSS prefix: `.eproc-*` (no `.eproc-admin-` prefix)
- Nav: `.eproc-navbar` with hamburger toggle at 768px
- All pages wrapped in `.eproc-wrap`
- JS in `frontend.js` using jQuery (WP bundled)

---

## Cloud Storage

- Abstract `Eprocurement_Storage_Interface` with factory pattern
- 5 providers: Google Drive, OneDrive, Dropbox, S3, **Local Storage** (fallback)
- Local storage path: `wp-content/uploads/eprocurement/`
- Credentials encrypted with AES-256-CBC using WordPress `AUTH_KEY`
- File validation: 50MB max, MIME whitelist, extension spoofing check
- Downloads: nonce-protected endpoint, time-limited cloud URLs

---

## Email / Notifications

- Registration verification: **always enabled** (not toggleable)
- All other notifications: **default OFF**, toggleable in Settings
- **Per-bidder toggle**: `notify_replies` column in `bidder_profiles` (default: ON)
  - Toggleable in query modal checkbox or dashboard profile
  - `notify_reply()` checks preference before sending
- Hooks: `eprocurement_query_created`, `eprocurement_reply_posted`, `eprocurement_status_changed`, `eprocurement_bid_published`, `eprocurement_visibility_changed`

---

## Multi-Tenant / Branding

- **`Eprocurement_Branding`** class: static methods for brand name, URL, logo, colors, login title
- Values from per-site `get_option()` with MyBliss Technologies defaults
- Settings page "Branding" section: name, URL, email, logo, login title, color pickers
- `inline_css()` generates `<style>` overriding `--eproc-*` CSS variables on `.eproc-wrap` and `.eproc-admin-shell`
- MU-plugin uses `sme_get_brand()` helper to bridge to branding class
- **Multisite**: Network-wide activation + `wp_initialize_site` for new sites
- Frontend CSS: 35 custom properties on `.eproc-wrap`, 317 `var()` references

---

## Color Scheme

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

All defined as CSS custom properties on `.eproc-admin-shell` for multi-tenant override.

---

## Installed Plugins

| Plugin | Version | Purpose |
|--------|---------|---------|
| eProcurement | (see `EPROC_VERSION`) | The main procurement plugin |
| sme-admin-customizations (MU) | v5.0 | Dynamic tenant branding, login page, admin overhaul, security. Auto-installed from `bundled-mu/` on activation. |

> Wordfence and Really Simple SSL were removed (2026-03-04) — 25 Wordfence tables dropped, DB reduced from 46 to 21 tables. Security now handled by MU-plugin + `class-access-control.php`.

---

## What's Working (Full List)

- All 9 database tables create correctly on activation (21 total in DB)
- Automatic database migration via `eprocurement_maybe_upgrade()` on version change
- Frontend page auto-created on activation
- Bid CRUD (create, edit, status transitions, delete)
- Bid categories (Briefing Register, Closing Register, Appointments) — toggleable, NO status workflow
- Bid edit: "Save Draft" + "Save & Open Bid" / "Update Bid" + "Open Bid" buttons with date validation
- Bid document upload/download with cloud storage (upload before save supported)
- Clean bid URLs: `/tenders/bid/123/` with `?bid=123` fallback
- Contact person management (CRUD, auto WP user creation, department dropdown)
- Bidder registration with email verification (48hr token) — E2E tested
- Email delivery via Mailpit in dev
- Login/logout flow (WordPress POST with nonce) + Forgot Password
- Messaging system (threads, replies, staff attachments, mark resolved)
- Messages: bid title opens in modal overlay; "Open" thread filter tab
- Visibility control (private/public threads) with bidder notification
- SCM document library
- Download audit logging with CSV export, date range picker, search autofill
- REST API — 38 endpoints functional
- `[eprocurement]`, `[eprocurement_open]`, `[eprocurement_closed]` shortcodes
- Custom admin sidebar with dynamic category items
- Custom admin UI (no WP chrome) + WP native pages retain branded sidebar
- Frontend: all pages (listing, detail, categories, login, register, dashboard, compliance)
- All frontend titles/headings center-aligned
- Responsive design (mobile hamburger nav at 768px)
- CSS cache busting via `EPROC_VERSION`
- Daily cron for expired bid archival + auto-close on page load
- MyBliss login page branding (Sunset Cityscape theme via MU-plugin)
- Profile page: only email, username, password, role visible
- CSS custom properties for multi-tenant color scheme
- Access control: non-Super-Admin restricted from wp-admin, role-based redirect
- Security hardening: XMLRPC disabled, user enumeration blocked, security headers, file editing disabled
- Multi-tenant branding: Branding class, Settings section, MU-plugin dynamic
- Frontend CSS fully refactored: 35 custom properties, 317 var() references
- Multisite activation: network-wide + wp_initialize_site
- Per-bidder chat notification toggle (notify_replies, default ON)
- DEMO tenant: 4 role users, 6 sample bids, 3 contacts, 2 Q&A threads
- Demo Data Seeder: Settings > Demo Data card (Super Admin only)
- MU-plugin auto-installation from `bundled-mu/`
- Home → Tenders 302 redirect
- 254 automated tests passing across 3 test suites
- Local filesystem storage fallback
- Toast notification system on admin pages
