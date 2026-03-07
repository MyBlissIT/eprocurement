# eProcurement — Troubleshooting

## CSS not updating

Bump `EPROC_VERSION` in `eprocurement/eprocurement.php` to bust browser cache:
```php
define( 'EPROC_VERSION', '2.10.4' ); // Increment this
```

## Sub-pages showing 404

Flush rewrite rules:
```bash
docker exec eproc-wp wp rewrite flush --allow-root
```

## Sub-pages showing 404 on Bluehost / shared hosting

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

## Docker won't start

1. Ensure Docker Desktop is running
2. Remove `version: '3.8'` from `docker-compose.yml` if seeing warnings
3. Check port conflicts: 8190 (WP) and 3307 (MySQL)

## WP-CLI not available

WP-CLI is not pre-installed in the `wordpress:latest` image. Install it:
```bash
docker exec eproc-wp bash -c "curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"
```

## Plugin activation fails

Check PHP error log inside container:
```bash
docker exec eproc-wp cat /var/log/apache2/error.log
```

## Tenders page missing

The activator auto-creates the "tenders" page on activation. If missing:
```bash
docker exec eproc-wp wp post create --post_type=page --post_title="Tenders" --post_name="tenders" --post_content="[eprocurement]" --post_status=publish --allow-root
docker exec eproc-wp wp rewrite flush --allow-root
```
