# D2D Live Cutover + Ads R2

This package does two separate jobs without replacing any public page design:

1. **Ads-only integration** using publisher `ca-pub-5807824613154542`.
2. **Main-domain cutover** by making `public_html` point to the existing Laravel `public` directory.

## Ads scope

Live AdSense bootstrap is allowed only on:

- `/blog/{slug}`
- `/universities/{slug}`
- `/scholarships/{slug}`
- `/opportunities/{slug}`
- `/jobs/{slug}`

The middleware strips this publisher's AdSense bootstrap from every other public route and from preview/staging.

This package does not change any Blade page, controller, `routes/web.php`, design CSS, content mapper, CRM code, Portal code, migration or database record.

## ads.txt

If `public/ads.txt` is missing, the installer recreates it with:

`google.com, pub-5807824613154542, DIRECT, f08c47fec0942fa0`

If an ads.txt already exists, the line is appended only when that publisher is missing.

## Install ads layer first

Extract this ZIP directly inside:

`/home/daresdre/d2d-laravel/`

Then:

```bash
cd ~/d2d-laravel
php install-live-ads-only.php
php artisan optimize:clear
php artisan phase5:live-ads-doctor
```

## Make Laravel the real dares2dream.com website

The primary domain's fixed cPanel document root can stay as `public_html`.

Instead of copying Laravel and creating two versions of the public files, run:

```bash
cd ~/d2d-laravel
php go-live-public-html.php
php artisan optimize:clear
```

The script:

- backs up `.env`
- sets `APP_ENV=production`
- sets `APP_URL=https://dares2dream.com`
- sets `APP_DEBUG=false`
- renames the old `public_html` to a timestamped `wordpress-old-*` directory
- creates `public_html` as a symlink to `/home/daresdre/d2d-laravel/public`

Therefore `dares2dream.com` serves exactly the same Laravel public directory you are already developing.

Immediately test:

- `https://dares2dream.com/`
- `https://dares2dream.com/universities`
- `https://dares2dream.com/blog`
- `https://dares2dream.com/ads.txt`
- one real single Blog or University URL

## Remove the old WordPress files

Only after the live domain is confirmed:

```bash
cd ~/d2d-laravel
php remove-old-wordpress-files.php --yes
```

This deletes the timestamped old WordPress **files only**. It deliberately does not delete any MySQL database.

## Rollback live cutover

Before deleting the old WordPress files:

```bash
cd ~/d2d-laravel
php rollback-live-cutover.php
php artisan optimize:clear
```

## Rollback ads-only changes

```bash
cd ~/d2d-laravel
php rollback-live-ads-only.php
php artisan optimize:clear
```

## Real ads

The package embeds Google's AdSense bootstrap on the five approved live detail route families only.

Because only the publisher ID was supplied and no manual ad-unit `data-ad-slot` IDs were supplied, this build uses the **Auto Ads bootstrap method**. Auto Ads must be enabled for `dares2dream.com` in the AdSense account for Google to choose and render ad locations automatically.

If you later want exactly one or two fixed ad positions per detail page, provide the AdSense ad-unit slot IDs and replace this route-gated Auto Ads layer with manual responsive units.
