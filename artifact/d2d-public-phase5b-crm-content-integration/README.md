# D2D Phase 5B — CRM Content Integration

This package connects the permanent public Laravel app to the shared D2D database without changing CRM, Portal, WordPress, or database rows.

## Includes
- Blog list/detail from `content_posts`
- University source auto-detection (`universities` table or `content_posts` university type)
- Opportunities from `opportunities` with `content_posts` fallback
- Public guidebooks/resources from `guidebook_resources`
- SEO mapping from `seo_meta`, including the Phase 4 direct/polymorphic relation patterns
- Public JSON bridge under `/api/v1/*`
- Safe CRM public-media proxy for content images referenced as `/storage/...`
- Phase 5B doctor command with schema/type diagnostics
- Preview pages styled to match the black/gold D2D visual direction
- Staging noindex remains controlled by the existing Phase 5 middleware

## Safety
- No migrations
- No inserts/updates/deletes
- No Portal files touched
- No CRM files touched
- No WordPress files touched
- No change to `public/v11/`
- No root catch-all permalink route yet, so existing URL cutover remains controlled

## Install
Extract into `/home/daresdre/d2d-laravel/`, then run:

```bash
cd ~/d2d-laravel
php install-phase5b.php
php artisan optimize:clear
php artisan phase5b:doctor
php artisan route:list | grep -E "blog|universities|opportunities|resources|api/v1"
```

Do not run migrations for this package.

## Preview
- `/blog`
- `/universities`
- `/opportunities`
- `/resources`
- `/api/v1/home`

The doctor output is important because the current shared DB reports no standalone `universities` table. Phase 5B therefore detects whether university records are actually stored in `content_posts` and prints the exact content type counts before we lock final university routing.
