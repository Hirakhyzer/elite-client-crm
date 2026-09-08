# D2D Ads All-in-One R5

This package replaces the previous experimental ads behavior with one isolated ads layer.

## Hard-approved routes only

- `/blog/{slug}`
- `/universities/{slug}`
- `/scholarships/{slug}`
- `/opportunities/{slug}`
- `/jobs/{slug}`

Everything else remains ad-free: homepage, listings, tools, resources/guidebooks, consultant pages, CRM and Portal.

## Real AdSense values included

Publisher:
`ca-pub-5807824613154542`

Manual Content Mid:
`9432468543`

Manual Content End / right rail:
`8039495827`

The second unit is used once per page: on wide layouts with a real right sidebar it is placed below the sidebar content as a responsive vertical/right-rail unit; otherwise it is placed near the end of the main content.

## Screenshot-style bottom ad intent anchor

The long rounded bar shown in the user's screenshot is an AdSense **Ad intent anchor**, not a normal manual display unit.

R5 permits that format only on the five approved detail routes because the AdSense site code is only emitted on those routes.

Recommended AdSense site settings:

- Auto ads: ON
- Intent-driven formats > Ad intent anchors: ON
- Ad intent links: OFF
- Ad intent chips: OFF
- Overlay formats > Side rail ads: ON (Right only recommended)
- Anchor ads: OFF
- Vignette ads: OFF
- In-page Banner ads: OFF
- Multiplex ads: OFF
- Existing ads optimization: OFF

This gives:

1. manual responsive content-mid ad
2. manual responsive content-end OR right-sidebar ad
3. Google Ad intent anchor like the screenshot
4. optional Google right-side rail on wide desktop

without letting Google place random in-page ads in the navigation/footer.

## Legacy cleanup

R5 is registered as the first provider/middleware and post-processes the final HTML. It removes earlier D2D AdSense bootstrap scripts, old manual units for this publisher and older D2D ads JS before inserting the R5 configuration.

It does not replace Blade views, controllers or web routes.

## ads.txt

Installer recreates:

`google.com, pub-5807824613154542, DIRECT, f08c47fec0942fa0`

at `public/ads.txt`.

## Install

Extract into `/home/daresdre/d2d-laravel/` then:

```bash
cd ~/d2d-laravel
php install-d2d-ads-r5.php
php artisan optimize:clear
php artisan phase5:ads-r5-doctor
```

Do not run migrations.

## Rollback

```bash
cd ~/d2d-laravel
php rollback-d2d-ads-r5.php
php artisan optimize:clear
```
