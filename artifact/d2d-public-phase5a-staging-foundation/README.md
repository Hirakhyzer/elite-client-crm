# D2D Phase 5A — Public Laravel Staging Foundation

This is the first safe Phase 5 package. It prepares a **separate public Laravel staging app** to read the existing shared D2D database and inspect the locked V11 frontend ZIP before any live-domain cutover.

## Safety
- Refuses installation when `.env` `APP_URL` contains `crm.dares2dream.com` or `portal.dares2dream.com`.
- No migrations.
- No destructive database commands.
- Read-only content queries only.
- No live `dares2dream.com` cutover.
- Adds `X-Robots-Tag: noindex, nofollow` to Phase 5 diagnostic/data endpoints.

## What it adds
- `app/Services/D2D/PublicContentRepository.php`
- `app/Http/Controllers/D2D/Phase5PreviewController.php`
- `app/Console/Commands/Phase5Doctor.php`
- `routes/phase5a.php`
- `/_phase5/health`
- read-only preview data endpoints for Blog, Universities, Opportunities and public Guidebooks
- server-side V11 ZIP inspection via `php artisan phase5:doctor`

## Install target
Extract this ZIP into the root of a **separate Laravel staging app**, not CRM and not Portal.

Then run:

```bash
php install-phase5a-staging.php
php artisan optimize:clear
php artisan phase5:doctor
php artisan route:list | grep _phase5
php artisan optimize
```

To let the server inspect the exact locked V11 frontend, place it at:

`storage/app/phase5/v11.zip`

Then run:

```bash
php artisan phase5:doctor
```

The command reports the shared-table readiness and identifies likely Vite/build/index entry points inside the V11 ZIP without extracting or modifying it.

## This is not the final V11 integration
This package intentionally does **not** approximate or redesign the locked V11 frontend. Once the exact ZIP structure is confirmed, the next Phase 5A package will bind that exact frontend to this read-only content bridge.
