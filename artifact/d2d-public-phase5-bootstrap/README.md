# D2D Phase 5 Public Laravel Bootstrap

Use this only in `/home/daresdre/d2d-laravel`.

This patch is for the exact situation where the permanent Phase 5 folder already has `vendor/` but does not yet have a Laravel application skeleton (`artisan`, `app/`, `bootstrap/`, `config/`, `routes/`, etc.).

It does not modify CRM, Portal, WordPress, DNS, or the database. It performs no migrations.

## Install

1. Extract this ZIP into `/home/daresdre/d2d-laravel`.
2. Run:

```bash
cd ~/d2d-laravel
php bootstrap-phase5-base.php
php artisan --version
```

The bootstrap detects the Laravel framework version from the existing `vendor/` directory. It supports the modern Laravel 11/12 application structure. It also creates a separate app key and a preview-only session cookie.

If `/home/daresdre/crm.dares2dream.com/.env` exists, only the DB_* connection values are copied into the new public app `.env`; CRM application/session/mail settings are not copied.

After the base works, continue with the previously extracted Phase 5A installer:

```bash
php install-phase5a-staging.php
php artisan optimize:clear
php artisan phase5:doctor
```

Move the locked V11 ZIP into the inspector location:

```bash
mkdir -p storage/app/phase5
mv d2d-public-frontend-v11-.zip storage/app/phase5/v11.zip
php artisan phase5:doctor
```

If your uploaded V11 filename differs, use its exact filename in the `mv` command.
