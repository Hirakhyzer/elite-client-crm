# D2D CRM Phase 4.2 UI Polish

Install on top of the working Phase 4.1 CRM.

This patch is UI-only. It does not run migrations and does not touch portal.dares2dream.com.

Changes:
- Removes/hides the bulky sidebar admin name/email/sign-out block.
- Replaces the small top-right CRM pill with a D2D admin dropdown.
- Dropdown items: My Profile, CRM Settings, Sign out.
- Restyles Guidebooks & Resources and WordPress Migration navigation to match the premium black/gold CRM.
- Adds simple My Profile and CRM Settings pages.
- Adds light Phase 4 form/card polish.
- Keeps WordPress migration post_type=post only.
- Keeps Guidebooks & Resources as manual CRM CRUD/version uploads only.

Install:

```bash
cd ~/crm.dares2dream.com
php install-phase4.2-ui.php
php artisan optimize:clear
php artisan route:list | grep -E "account/profile|account/settings|phase4"
php artisan optimize
```

No migration is required.
