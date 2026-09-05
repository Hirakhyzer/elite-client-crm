# D2D CRM Phase 4.4 — Popup Preview UX

This patch installs on top of the working Phase 4.3 CRM.

## What it adds

- CRM-only **Popup Preview Lab** at `/phase4/popup-preview`
- lists existing records from `popup_campaigns`
- opens a popup preview in a new tab
- Desktop / Mobile preview switcher
- simulated public-site backdrop/overlay
- image, headline, subheadline, body, CTA and close button preview
- trigger/frequency/device/status details when those fields exist
- attempts to add **Preview** buttons to the current Popup Manager edit rows without replacing its existing CRUD
- no public publishing and no live popup triggering

## Safety

- no migration
- no database writes
- no Portal files
- no change to WordPress migration
- no change to Guidebooks CRUD
- existing Popup Manager remains the place where popup data is created/edited

## Install

Upload/extract into:

`/home/daresdre/crm.dares2dream.com/`

Then run:

```bash
cd ~/crm.dares2dream.com
php install-phase4.4.php
php artisan optimize:clear
php artisan route:list | grep popup-preview
php artisan optimize
```

Open:

`https://crm.dares2dream.com/phase4/popup-preview`

No migration is required.

## Important

This is a CRM visual sandbox. In Phase 5, the public Laravel site should reuse the same popup styling contract/component so CRM Preview and Live remain visually aligned.
