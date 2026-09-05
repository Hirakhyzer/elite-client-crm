# D2D Phase 5A.2 — V11 Asset Compatibility Fix

Fixes the unstyled/broken-image preview caused by the locked V11 HTML resolving CSS/JS/media at the domain root while Phase 5A.1 installed the files under `public/v11/`.

This patch:
- serves the locked V11 `index.html` unchanged
- mirrors V11 CSS, JS, images and video into Laravel `public/`
- leaves `public/index.php` and `.htaccess` untouched
- does not modify the database, CRM, Portal or WordPress
- runs no migrations

Install into `/home/daresdre/d2d-laravel/`, then run:

```bash
cd ~/d2d-laravel
php install-phase5a2-v11-assets.php
php artisan optimize:clear
php artisan optimize
```

Then hard-refresh `https://preview.dares2dream.com`.
