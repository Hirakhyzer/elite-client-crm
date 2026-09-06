# D2D Phase 5A.3 — V11 Root Asset Fix

This replaces the failed/expired Phase 5A.2 delivery with a fresh package.

It fixes the plain-HTML preview by mirroring the exact V11 static assets from `public/v11/` into Laravel's `public/` root, which is where the V11 HTML expects to find `styles.css`, `app.js`, `assets/`, and hero media.

Install:

```bash
cd ~/d2d-laravel
php install-phase5a3-v11-assets.php
php artisan optimize:clear
php artisan optimize
```

Then hard-refresh `https://preview.dares2dream.com`.

Safety:
- no migrations
- no DB changes
- no CRM changes
- no Portal changes
- no WordPress changes
- does not overwrite `public/index.php` or `public/.htaccess`
