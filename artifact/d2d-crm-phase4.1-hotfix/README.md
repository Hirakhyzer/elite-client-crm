# D2D CRM Phase 4.1 Hotfix

Purpose: fix the confirmed Laravel error `View [crm.layouts.app] not found` on both Phase 4 WordPress Migration and Guidebooks & Resources screens.

This hotfix does not change the database and does not touch the Portal.

It auto-detects the working Blade layout already used by the CRM dashboard, then replaces only the incorrect Phase 4 `@extends('crm.layouts.app')` references.

Locked behavior remains:
- WordPress importer: `post_type=post` only.
- No WordPress guidebooks/pages/users/comments/revisions/plugin data.
- Guidebooks & Resources: manual CRM CRUD and manual version uploads only.

Install from the CRM Laravel root:

```bash
php install-phase4.1-hotfix.php
php artisan optimize:clear
php artisan phase4:doctor
```

No migration is required.
