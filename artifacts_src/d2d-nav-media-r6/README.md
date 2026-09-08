# D2D R6 — Nav + Hero Media Helper

This package is deliberately narrow.

## Adds to the public top navigation
- Opportunities -> `/opportunities`
- Jobs Abroad -> `/jobs`
- Internships -> `/internships`

The JS clones an existing nav link so the new items inherit the same classes and styling as Scholarships / Universities / Tools.
It also normalizes an older `All Opportunities` label to `Opportunities` instead of duplicating it.

## Does not replace
- any Blade page
- any public controller
- any route
- ads configuration
- university work
- blog work
- CRM
- Portal
- database data

## V11 hero media locations
- `/home/daresdre/d2d-laravel/public/v11/hero-background.mp4`
- `/home/daresdre/d2d-laravel/public/v11/hero-video-base.jpg`
- `/home/daresdre/d2d-laravel/public/v11/hero-video-base.webp`

Public URLs:
- `https://dares2dream.com/v11/hero-background.mp4`
- `https://dares2dream.com/v11/hero-video-base.jpg`
- `https://dares2dream.com/v11/hero-video-base.webp`

## Install
Extract into `/home/daresdre/d2d-laravel/`, then:

```bash
cd ~/d2d-laravel
php install-d2d-nav-media-r6.php
php artisan optimize:clear
php locate-v11-hero.php
```

Hard refresh with `Ctrl + Shift + R`.

## Rollback
```bash
cd ~/d2d-laravel
php rollback-d2d-nav-media-r6.php
php artisan optimize:clear
```

No migrations.
