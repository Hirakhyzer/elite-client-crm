# D2D CRM Phase 4 — WordPress Posts Importer + Guidebooks & Resources

This patch is designed to install on top of the already-installed D2D CRM Phase 3.5 application at `crm.dares2dream.com`.

## Locked migration scope

WordPress import reads only the legacy `d2d_posts` and `d2d_postmeta` records needed for `post_type = post`.

Imported for normal blog posts:
- title
- original slug (`/%postname%/`)
- excerpt
- body content
- original post date/status
- default author `Team D2D` (editable later)
- Yoast SEO title/meta/canonical/Open Graph where available
- featured image
- images actually referenced inside imported posts
- WordPress source ID/checksum for idempotent duplicate protection

Never imported:
- WordPress pages
- WordPress guidebook custom post type
- WordPress users/passwords/sessions
- comments
- revisions
- forms
- plugin configuration/data
- Action Scheduler/plugin caches
- unused media

Duplicate protection checks:
1. previously imported WordPress post ID
2. existing CRM slug
3. existing exact CRM title
4. duplicates inside the WordPress source itself

Only rows marked `READY` by Dry Run are imported.

## New CRM section: Guidebooks & Resources

Supports:
- Guidebook
- E-book
- Checklist
- Template
- Worksheet
- Toolkit
- Other Resource

Each resource has an editable author (default `Team D2D`), cover image, description, SEO fields, publish status and access level: Public, Pro, Ambassador, or Pro + Ambassador.

Resource files are versioned. Uploading `v2.0` does not overwrite or delete `v1.0`. One version can be selected as Current while previous versions remain in the CRM.

## Install

Upload this ZIP into:

`/home/daresdre/crm.dares2dream.com/`

Extract it so `install-phase4.php`, `app/`, `database/`, `resources/` and `routes/` sit in the CRM Laravel root.

Run:

```bash
cd ~/crm.dares2dream.com
php install-phase4.php
php artisan optimize:clear
php artisan migrate --force
php artisan phase4:doctor
php artisan optimize
```

Never run destructive migration commands on the shared database (`migrate:fresh`, `migrate:reset`, `migrate:refresh`, `rollback`, `db:wipe`).

If `/storage` is not already linked, run once:

```bash
php artisan storage:link
```

## Migration source files

Open:

`https://crm.dares2dream.com/phase4/wordpress`

Upload the WordPress SQL export and `uploads.zip`, then click **Run Dry Run**. Review conflicts/missing media. Only after review tick the confirmation checkbox and click **Import Safe Posts**.

For very large files, upload them through cPanel to:

`/home/daresdre/crm.dares2dream.com/storage/app/phase4-import/`

using exact names:
- `legacy.sql`
- `uploads.zip`

Then open the migration screen and run Dry Run.

CLI alternative:

```bash
php artisan wordpress:dry-run
# review storage/app/phase4-import/dry-run.json
php artisan wordpress:import --yes
```

## Guidebooks & Resources

Open:

`https://crm.dares2dream.com/phase4/guidebooks`

Create a resource, then upload v1.0, v2.0, v3.0 etc. Previous versions are retained.

## Safety

- CRM-only Phase 4 files.
- Does not modify `portal.dares2dream.com`.
- SQL source is staged under Laravel `storage/`, not the public web root.
- SQL parser ignores all unapproved WordPress tables.
- The media ZIP is not bulk-imported; only files referenced by imported blog posts are copied to Laravel storage.
- Installer creates `.phase4-backup` copies of route/layout files before the small registration/navigation changes.
