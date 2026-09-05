# D2D CRM Phase 4.5 — Branding + Admin Account

CRM-only patch for managing the CRM brand and the currently logged-in CRM administrator.

## Adds

- CRM logo upload
- favicon upload (logo is used as fallback if no separate favicon exists)
- admin profile photo upload/remove
- admin name update
- admin email update with current-password verification
- password change with current-password verification
- top-right admin profile photo support
- upper-left CRM logo replacement

## Safety

- No migrations.
- No Portal files are changed.
- No roles or permissions are changed.
- The patch only edits the currently authenticated CRM user's account when the forms are submitted.
- CRM and Portal share the same `users` table. Therefore, if the same admin account is used somewhere else, changing its email/password changes that shared account there as well.

## Install

Extract this ZIP into:

`/home/daresdre/crm.dares2dream.com/`

Then run:

```bash
cd ~/crm.dares2dream.com
php install-phase4.5-branding-account.php
php artisan optimize:clear
php artisan route:list | grep -E "account/profile/update|account/security/password|account/branding"
php artisan phase4.5:doctor
php artisan optimize
```

No migration command is required.

## Use

Open:

`https://crm.dares2dream.com/account/profile`

You can upload the eagle image there as the CRM logo. Upload the same image as the admin profile photo if you want it as the admin DP, or upload a personal admin photo separately.

For favicon, you can either upload a separate `.ico`/PNG image or leave it unset; the CRM logo will be used as the browser icon automatically.
