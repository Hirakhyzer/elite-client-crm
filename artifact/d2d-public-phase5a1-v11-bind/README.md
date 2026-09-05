# D2D Phase 5A.1 — Exact V11 binding

Target: `/home/daresdre/d2d-laravel`

This patch does not modify CRM, Portal, WordPress, or the database.

It updates only the public Laravel controller so the locked static V11 frontend can be served from `/` while its CSS, JS, images, favicon and hero media remain under `public/v11/`.

Install:

```bash
cd ~/d2d-laravel
php artisan phase5:doctor --install-v11
php artisan optimize:clear
php artisan route:list | grep -E '(^| )/$|v11'
```

Then open `https://preview.dares2dream.com` and hard refresh.

Staging remains noindex through Phase 5 middleware.
