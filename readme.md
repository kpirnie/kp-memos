# KP Memos

[![Last Commit](https://img.shields.io/github/last-commit/kpirnie/REPO?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHJlY3QgeD0iMyIgeT0iNC41IiB3aWR0aD0iMTgiIGhlaWdodD0iMTYuNSIgcng9IjIiLz48bGluZSB4MT0iMyIgeTE9IjkuNSIgeDI9IjIxIiB5Mj0iOS41Ii8+PGxpbmUgeDE9IjgiIHkxPSIyLjUiIHgyPSI4IiB5Mj0iNi41Ii8+PGxpbmUgeDE9IjE2IiB5MT0iMi41IiB4Mj0iMTYiIHkyPSI2LjUiLz48L3N2Zz4=)](https://github.com/kpirnie/kptv-filter-app/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)
[![PHP](https://img.shields.io/badge/Min.%20-%20php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![MariaDB](https://img.shields.io/badge/Min.%20MariaDB-13-003545?logo=mariadb&logoColor=white&style=for-the-badge&labelColor=000)](https://mariadb.org/)
[![Kevin Pirnie](https://img.shields.io/badge/-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

Secure, self-hosted notes: a WYSIWYG editor with source view, multi-file
attachments, nested categories, tags, full-text search, pinned notes you can
drag into order, and optional public share links. Every account signs in
with a password **and** TOTP.

Built on [kpt-router](https://packagist.org/packages/kevinpirnie/kpt-router),
[kpt-database](https://packagist.org/packages/kevinpirnie/kpt-database), and
[kpt-utils](https://packagist.org/packages/kevinpirnie/kpt-utils).

## Requirements

- PHP 8.5 with `pdo_mysql`, `sodium`, `openssl`, `redis`, `fileinfo`, `intl`, `mbstring`, `curl`
- MariaDB 10.6+
- Redis (login, two-factor, and share-password lockouts; the app refuses sign-ins when Redis is down)
- nginx that includes `/var/www/html/.nginx.conf*` from the site's `server` block
- HTTPS (the session cookie is `__Host-` prefixed and `Secure`)

## Install

```bash
# from the site root (the repository)
composer install --no-dev --optimize-autoloader

# configuration: fill in app.url, the database, and redis
cp app/config.sample.php app/config.php
php bin/keygen.php            # paste the output into app.key

# schema, stored procedures, and the EXECUTE-only application account
php bin/install.php

# the first administrator
php bin/create-admin.php
```

`storage/` must be writable by the php-fpm user; attachments and the
HTMLPurifier cache live there. nginx denies it (and `app/`, `bin/`,
`database/`, `vendor/`, `tmp/`) outright, so the root `index.php` is the only
PHP that can execute.

Upload limits come straight from PHP: raise `upload_max_filesize` and
`post_max_size` (and nginx's `client_max_body_size`) to allow larger
attachments. Files upload one per request, so the per-file limit is what
matters.

## Updating

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/install.php           # re-applies tables (idempotent) and procedures
```

## Security model

- **Database**: the app account holds `EXECUTE` only. Every read and write is a `kpm_` stored procedure scoped to the signed-in user; no table name appears in the PHP. Admins manage accounts and can never read another user's notes.
- **Sign in**: Argon2id passwords, mandatory TOTP (RFC 6238, replay-protected, secret encrypted with `app.key`), 10 single-use recovery codes stored as keyed hashes, lockouts per account and per IP, idle and absolute session timeouts, sessions bound to the browser and ended by any password, role, or two-factor change.
- **Requests**: CSRF token plus `Origin` / `Sec-Fetch-Site` checks on every state change, nonce-based CSP, HSTS, `frame-ancestors 'none'`, no-store caching.
- **Content**: note HTML is sanitized by HTMLPurifier on save (allowlisted tags and CSS properties, safe URL schemes, no classes or ids). Attachments are stored under random names, never executed, and served as downloads with a sandbox CSP; only raster images display inline.
- **Sharing**: 192-bit link tokens, optional expiry and Argon2id password, instant revocation by regenerating, `noindex`, and throttling against token guessing.
- **PWA**: online only. The service worker caches the static shell alone; notes are never stored on the device.

## Configuration notes

- `app.url` must be the exact public origin; state-changing requests from any other host are refused.
- Behind a reverse proxy, list its address in `security.trusted_proxies`, or every visitor shares the proxy's IP for lockouts.
- Keep `app.debug` off in production.
- Back up the database, `storage/attachments/`, **and `app.key`**. Without the key, enrolled authenticators and recovery codes stop working (an admin can reset a user's two-factor so they enroll again).
