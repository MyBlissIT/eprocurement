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

# WP-CLI (install first if needed — see docs/troubleshooting.md):
docker exec eproc-wp wp --allow-root <command>
docker exec eproc-wp wp rewrite flush --allow-root
```

## Key Architecture

- **9 custom tables** prefixed `wp_eproc_*` — see [docs/architecture.md](docs/architecture.md) for full schema
- **4 custom roles**: SCM Manager, SCM Official, Unit Manager, Bidder
- **38 REST endpoints** (10 public + 28 admin) — see [docs/architecture.md](docs/architecture.md)
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
├── includes/                 # Core logic (17 classes + storage/)
├── admin/                    # Custom admin UI (class-admin.php, CSS, JS, partials/)
├── public/                   # Frontend UI (class-public.php, CSS, JS, partials/)
├── templates/email/          # Email templates (verification, query, reply)
└── bundled-mu/               # MU-plugin (auto-installed on activation)
```

Full file tree with annotations: [docs/architecture.md](docs/architecture.md)

## GitHub & Releases

| Detail | Value |
|--------|-------|
| Repo | `MyBlissIT/eprocurement` (public) |
| Branch | `master` |
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

Deployment details (SSH, credentials, caching notes) are in `.claude/deploy.md` (gitignored — not committed to public repo).

## Current Status

**UI Redesign: 100% complete** (all 7 phases, 18 backend edits done).
**254 automated tests passing** across 3 test suites.

Full "What's Working" list: [docs/architecture.md](docs/architecture.md)

### Needs Verification
1. Cloud storage OAuth flows (need real API credentials)
2. S3 test connection (need real credentials)
3. Frontend pixel-perfect visual verification

### Known Technical Debt
1. `get_recent_activity()` UNION query may need optimization at scale
2. Bidder dashboard uses vanilla JS (not jQuery) — minor inconsistency

### What's Next
**Should Do:**
1. Deploy to shared hosting + configure WP Mail SMTP
2. MainWP dashboard for centralized client management
3. Visual verification via browser screenshots
4. Test cloud storage with a real provider (S3 recommended)
5. Cross-browser testing (Chrome, Firefox, mobile Safari)

**Nice to Have:**
- Unit tests for core business logic
- Database indexes review + query caching
- Weekly Digest notification (marked "Coming Soon" in Settings)
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
