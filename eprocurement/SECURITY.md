# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in the eProcurement plugin, please
report it responsibly:

1. **Email**: security@myblisstech.com
2. **Subject**: `[SECURITY] eProcurement — <short description>`
3. **Include**:
   - Affected version (find in `eprocurement.php` header)
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

We respond within **48 hours** and credit responsible disclosure in our
changelog (unless you prefer to remain anonymous).

**Please do not** open public GitHub issues for security vulnerabilities.

## Supported Versions

Only the latest release receives security updates.

| Version | Supported          |
|---------|--------------------|
| 2.14.x  | :white_check_mark: |
| < 2.14  | :x:                |

## Security Hardening Checklist

For production deployments, we recommend the following hardening:

### wp-config.php

- Generate unique salts at https://api.wordpress.org/secret-key/1.1/salt/
  and add them to `wp-config.php`. The plugin refuses to encrypt credentials
  if `AUTH_KEY` is undefined or empty.
- Set `wp_get_environment_type()` to `'production'` (or define
  `WP_ENVIRONMENT_TYPE`).
- Disable `WP_DEBUG` and `WP_DEBUG_LOG` in production.
- Set `DISALLOW_FILE_EDIT` to true.
- Set `DISALLOW_FILE_MODS` to true if you don't need the plugin/theme editor.

### Server

- Run PHP 8.1+ (8.0 reached end-of-life November 2023).
- Run the latest stable WordPress (6.0+ required, 6.7+ recommended).
- Use HTTPS site-wide (Let's Encrypt is free).
- Set `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  and a Content-Security-Policy at the webserver level (the plugin also
  sets these on its own pages, but server-level headers cover everything).
- Restrict `wp-content/uploads/eprocurement/` access at the webserver
  level (the plugin's `.htaccess` handles Apache; for nginx, add:
  `location ~* /wp-content/uploads/eprocurement/ { deny all; return 403; }`).

### Plugin configuration

- Configure SMTP via **eProcurement → Settings → SMTP** (do not rely on
  the dev-mode Mailpit fallback).
- Configure a cloud storage provider with restricted permissions (e.g.,
  a dedicated Google Drive folder with edit-only scope, not full drive
  access).
- Set CORS origins explicitly in **Settings → Advanced** — do not use `*`.
- Enable "Delete all data on uninstall" only if you intend to fully
  uninstall.

### Operational

- Rotate cloud-storage OAuth tokens annually (re-link via Settings).
- Review the audit log (`eproc_audit_log` option) monthly for unexpected
  backdate actions.
- Review the download log (eProcurement → Download Log) weekly for
  suspicious patterns.
- Back up the database daily and the uploads directory weekly.
- Monitor `wp-content/debug.log` for plugin-specific errors.

## Acknowledgements

We thank the following researchers for responsible disclosure:

- *(none yet — be the first!)*
