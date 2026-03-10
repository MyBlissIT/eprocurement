# eProcurement WordPress Plugin

> **Author:** MyBliss Technologies | **PHP:** 8.0+ | **WordPress:** 6.0+
> **Version:** See `EPROC_VERSION` in `eprocurement/eprocurement.php`

## Purpose

Mini-CRM WordPress plugin for government/corporate procurement. Manages bid/tender notices, bidder communication, cloud document storage, download auditing, and role-based access. Admin UI is fully custom (no WP chrome on plugin pages).

## Development Environment

| Component | Details |
|-----------|---------|
| WordPress | `eproc-wp` — port **8190**, image `wordpress:6.7-php8.2-apache` |
| MySQL | `eproc-db` — port **3307** |
| Mailpit | SMTP **1025**, Web UI **http://localhost:8191** |
| WP Admin | `http://localhost:8190/wp-admin/` — **admin / admin123** |
| Frontend | `http://localhost:8190/tenders/` |
| Plugin mount | `./eprocurement` → `/var/www/html/wp-content/plugins/eprocurement` |

```bash
# Start environment (Docker Desktop must be running):
docker compose -f "C:/Users/sinet/OneDrive/Documents/MyBliss Technologies/Website Development/Plugins/Custom Plugins/eProcument Plugin/docker-compose.yml" up -d

# Stop and remove containers when done (volumes preserved):
docker compose -f "C:/Users/sinet/OneDrive/Documents/MyBliss Technologies/Website Development/Plugins/Custom Plugins/eProcument Plugin/docker-compose.yml" down

# WP-CLI (install first if needed — see docs/troubleshooting.md):
docker exec eproc-wp wp --allow-root <command>
docker exec eproc-wp wp rewrite flush --allow-root
```

> **Container cleanup rule:** Always `docker compose down` when done working. Never leave containers stopped. Volumes hold all data and `docker compose up -d` recreates containers instantly.

## Key Architecture

- **11 custom tables** prefixed `wp_eproc_*` — see [docs/architecture.md](docs/architecture.md) for full schema
- **4 custom roles**: SCM Manager, SCM Official, Unit Manager, Bidder
- **50 REST endpoints** (13 public + 37 admin) — see [docs/architecture.md](docs/architecture.md)
- **17 AJAX handlers** — see [docs/architecture.md](docs/architecture.md)
- **5 cloud storage providers**: Google Drive, OneDrive, Dropbox, S3, Local (fallback)
- **Frontend routing** via `[eprocurement]` shortcode on `/tenders/` page with WP rewrite rules

### Bid Categories & Status
- **Regular Bids** (`category = 'bid'`): Draft → Open → Closed → Archived (+ Cancelled)
- **Briefing Register, Closing Register, Appointments**: NO status workflow — simple entries only

### UI Conventions
- CSS prefix: `.eproc-admin-*` (admin shell), `.eproc-*` (components + frontend)
- Colors: maroon `#8b1a2b` primary, navy `#1a1a5e` sidebar — all via `--eproc-*` CSS variables
- **All buttons must use standard maroon** — no green or other accent colors
- Cache bust CSS/JS by bumping `EPROC_VERSION`
- Frontend titles/headings are center-aligned

### Plugin Structure (key paths)
```
eprocurement/
├── eprocurement.php          # Main file, constants, autoloader
├── includes/                 # Core logic (18 classes + storage/)
├── admin/                    # Custom admin UI (class-admin.php, CSS, JS, partials/)
├── public/                   # Frontend UI (class-public.php, CSS, JS, partials/)
├── templates/email/          # Email templates (verification, query, reply, briefing-invite)
└── bundled-mu/               # MU-plugin (auto-installed on activation)
```

Full file tree with annotations: [docs/architecture.md](docs/architecture.md)

## GitHub & Releases

| Detail | Value |
|--------|-------|
| Repo | `MyBlissIT/eprocurement` (public) |
| Branch | `master` |
| Current tag | `v2.12.0` |
| CI/CD | `.github/workflows/release.yml` — auto-builds ZIP on tag push |

### Release Flow
```bash
git add <files> && git commit -m "Description"
git push                    # Saves code only
git tag v2.x.x              # Creates release
git push origin v2.x.x      # Triggers CI → clients see update within 12h
```

### Rules
- **ALWAYS ask user permission before pushing, tagging, or releasing**
- `git push` does NOT trigger client updates — only tags do
- Self-updater (`class-updater.php`) checks GitHub Releases API every 12h

## Live Site

Production receives updates via the self-updater when a tag is pushed to GitHub. Flow: tag → push tag → GitHub Actions builds ZIP → live site sees "Update Available" in wp-admin → Plugins within 12h (or update immediately from wp-admin).

VPS SSH config is in `~/.ssh/config` under `my-vps` (72.62.124.131, root, port 22).

## Current Status

**UI Redesign: 100% complete** (all 7 phases, 18 backend edits done).
**254 automated tests passing** across 3 test suites + **96/100 Playwright cross-browser tests**.

Full "What's Working" list: [docs/architecture.md](docs/architecture.md)

### Recent Changes (v2.12.0 — Bid Submission)
- **Sealed-bid document submission** — bidders upload PDF, Excel (.xls/.xlsx), or CSV (max 10 MB) via drag-and-drop on tender detail page
- **2 new DB tables**: `wp_eproc_bid_submissions` (submissions + lifecycle), `wp_eproc_briefing_attendees` (compulsory briefing gate)
- **2 new columns on `documents`**: `allow_late_submissions`, `briefing_compulsory`
- **12 new REST endpoints** (3 public + 9 admin) for submit/cancel/list/download/backdate/attendees/invites
- **Compulsory briefing gate** — SCM adds attendees by email, sends invite emails with UUID token links; only listed bidders can submit
- **Late submission toggle** — per-bid flag allows submissions after closing date (marked with orange "Late" badge)
- **Super Admin backdate** — "Replace & Show" (visible indicator) or "Replace & Hide" (invisible, `original_submitted_at` always stored)
- **Admin submissions card** — bid edit page shows submissions table, Download All (ZIP) button
- **"My Submissions" dashboard tab** — bidder sees all submissions across bids with status badges
- **Dual-channel notifications** — bid submitted/cancelled sends email to SCM Managers AND creates in-system private thread/message
- **Briefing invite email template** (`templates/email/briefing-invite.php`) — includes token-based submission link
- **Weekly digest updated** — includes new submissions count section

### Previous Release (v2.11.0)
- **14 composite DB indexes** across 6 tables (documents, threads, messages, downloads, bidder_profiles, supporting_docs)
- **Weekly Digest notification** — cron fires every Monday, emails admins + SCM Managers a 7-day activity summary. Toggle in Settings > Notifications.
- **Download count badge** — maroon pill next to Bid No. in Download Log showing total downloads per bid
- **Bid detail hero header** — OPEN bids get green gradient, CLOSED bids get maroon gradient (matching listing page hero colors)
- **Playwright cross-browser test suite** — 20 tests (12 admin + 8 frontend) across 5 browsers (Chromium, Firefox, WebKit, Mobile Chrome, Mobile Safari)
- **QA verification report** — `tests/visual/qa-report.html` (self-contained HTML with embedded screenshots)

### Needs Verification
1. Cloud storage OAuth flows (need real API credentials)
2. S3 test connection (need real credentials)

### Known Technical Debt
1. `get_recent_activity()` UNION query may need optimization at scale
2. Bidder dashboard uses vanilla JS (not jQuery) — minor inconsistency

### Bid Submission Architecture Notes
- **One submission per bidder per bid** — cancel to resubmit (before closing date)
- **Enforcement order in `can_submit()`**: bid open → verified → closing date → briefing gate → file validation → duplicate check
- **Cloud storage**: uses existing `Eprocurement_Storage_Interface` (upload/download/delete)
- **ZIP download naming**: `{Bid No} - {Title} - {Date}.zip`, files inside: `{company_name}_{timestamp}.{ext}`
- **Token link flow**: `/tenders/bid/{id}/submit/?token={uuid}` → login redirect if needed → marks `used_at` → strips token from URL
- **Backdate DB pattern**: `$wpdb->update()` can't set NULL — raw query needed for `backdated_by = NULL` (hidden mode)
- **Notifications**: `send_system_message()` helper creates private threads via existing messaging system (sender_id=0 for system)

### What's Next
**Should Do:**
1. Fix ZIP packaging so uploads replace the existing plugin folder (WordPress expects the ZIP to overwrite `wp-content/plugins/eprocurement/` in-place — not create a second folder). The GitHub Actions release ZIP and any manually created ZIPs must produce a clean "Upload Plugin" upgrade without needing to delete the old version first.
2. **Online Bids dashboard page** — new sidebar item (icon similar to Download Log). Table columns: Bid Number, Title, Closing Date, Submissions (total count to date), Bidders (clickable → opens page listing all bidders who submitted; clicking a bidder navigates to their submission on their profile). Design/adapt as needed.
3. Test cloud storage with a real provider (S3 recommended)
4. MainWP dashboard for centralized client management

**Nice to Have:**
- Unit tests for bid submission business logic
- Playwright tests for bid submission UI flows
- Document multi-tenant CSS variable override process

## Reference Documents (`docs/`)

| Document | Path |
|----------|------|
| Full architecture, DB schema, API endpoints, file tree | [docs/architecture.md](docs/architecture.md) |
| Troubleshooting common issues | [docs/troubleshooting.md](docs/troubleshooting.md) |
| Demo data SQL seed | `docs/demo-data.sql` |
| Demo user credentials | `docs/demo-credentials.txt` |
| Plugin update guide | `docs/HOW-TO-UPDATE.txt` |
| Original plugin spec | `docs/eProcurement Plugin.docx` |
| Design/edit history | `docs/eProcurement Edits.docx` |
| HTML spec + generators | `docs/claude-spec-files/` |
| Marketing brochure | `docs/brochure.html` |
| System report | `docs/eProcurement-System-Report-2026-03-04.pdf` |
