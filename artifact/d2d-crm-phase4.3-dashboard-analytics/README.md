# D2D CRM Phase 4.3 — Dashboard + Analytics Foundation

Install this patch on top of the working Phase 4.2 CRM.

## Adds

- Guidebooks & Resources count on the CRM dashboard.
- Published/draft guidebook counts.
- Separate **Analytics** navigation tab.
- 7 / 30 / 90 day analytics filters.
- Content-type filters for Blog, University, Scholarship, Job and Guidebook.
- Public views trend graph.
- Top-content table.
- `analytics_events` raw event foundation.
- `analytics_daily_metrics` aggregation-ready table.
- `App\Services\Analytics\D2dAnalytics` recorder service for the future public Laravel site.
- `analytics:doctor` installation check.

## Important

This patch does **not** invent analytics data. Until `dares2dream.com` is connected to Laravel and starts recording D2D events, the dashboard intentionally shows zero/empty analytics.

Google Analytics/GA4 is a later separate integration. The D2D internal analytics database remains independent so D2D can track product events such as saves, applications and guidebook downloads.

## Install

Extract into:

`/home/daresdre/crm.dares2dream.com/`

Then run:

```bash
cd ~/crm.dares2dream.com
php install-phase4.3.php
php artisan optimize:clear
php artisan migrate --force
php artisan analytics:doctor
php artisan optimize
```

Open:

`https://crm.dares2dream.com/analytics`

No Portal code is modified.

Never run destructive commands on the shared database: `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `rollback`, or `db:wipe`.
