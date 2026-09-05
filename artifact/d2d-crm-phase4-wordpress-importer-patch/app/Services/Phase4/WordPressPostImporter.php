<?php

namespace App\Services\Phase4;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class WordPressPostImporter
{
    private array $metaKeys = [
        '_thumbnail_id',
        '_wp_attached_file',
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_canonical',
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_opengraph-image',
        '_wp_old_slug',
    ];

    public function __construct(private readonly WpSqlDumpParser $parser)
    {
    }

    public function analyze(string $sqlPath, ?string $uploadsZipPath = null, string $prefix = 'd2d_'): array
    {
        $source = $this->loadSource($sqlPath, $prefix);
        $target = $this->targetDiagnostics();
        $zipIndex = $this->buildZipIndex($uploadsZipPath);
        $existing = $this->existingTargets();
        $existingImports = Schema::hasTable('wordpress_imports')
            ? DB::table('wordpress_imports')->where('source_type', 'post')->get()->keyBy(fn ($row) => (string) $row->source_id)
            : collect();

        $seenSlugs = [];
        $seenTitles = [];
        $rows = [];
        $counts = [
            'posts_found' => 0,
            'ready' => 0,
            'already_imported' => 0,
            'duplicate_slug' => 0,
            'duplicate_title' => 0,
            'duplicate_source' => 0,
            'invalid' => 0,
            'media_references' => 0,
            'media_found' => 0,
            'media_missing' => 0,
        ];

        foreach ($source['posts'] as $post) {
            $counts['posts_found']++;
            $id = (string) $post['ID'];
            $title = trim((string) ($post['post_title'] ?? ''));
            $slug = trim((string) ($post['post_name'] ?? '')) ?: Str::slug($title);
            $normalizedTitle = $this->normalizeTitle($title);
            $result = 'ready';
            $reason = 'Safe to import';

            if ($title === '' || $slug === '') {
                $result = 'invalid';
                $reason = 'Missing title or slug';
            } elseif (isset($existingImports[$id])) {
                $result = 'already_imported';
                $reason = 'This WordPress post ID already has an import record';
            } elseif (isset($seenSlugs[$slug]) || isset($seenTitles[$normalizedTitle])) {
                $result = 'duplicate_source';
                $reason = 'Duplicate slug/title exists inside the WordPress source';
            } elseif (isset($existing['slugs'][$slug])) {
                $result = 'duplicate_slug';
                $reason = 'CRM already contains this slug';
            } elseif (isset($existing['titles'][$normalizedTitle])) {
                $result = 'duplicate_title';
                $reason = 'CRM already contains this exact title';
            }

            $seenSlugs[$slug] = true;
            $seenTitles[$normalizedTitle] = true;
            $counts[$result]++;

            $media = $this->mediaReferencesForPost($post, $source['meta'], $source['attachments']);
            $mediaFound = 0;
            $mediaMissing = 0;
            foreach ($media as $relative) {
                $counts['media_references']++;
                if ($zipIndex === null) {
                    continue;
                }
                if ($this->zipEntryFor($zipIndex, $relative) !== null) {
                    $mediaFound++;
                    $counts['media_found']++;
                } else {
                    $mediaMissing++;
                    $counts['media_missing']++;
                }
            }

            $rows[] = [
                'source_id' => (int) $post['ID'],
                'title' => $title,
                'slug' => $slug,
                'wp_status' => (string) ($post['post_status'] ?? ''),
                'published_at' => (string) ($post['post_date'] ?? ''),
                'result' => $result,
                'reason' => $reason,
                'media_total' => count($media),
                'media_found' => $mediaFound,
                'media_missing' => $mediaMissing,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => [
                'sql' => basename($sqlPath),
                'uploads_zip' => $uploadsZipPath ? basename($uploadsZipPath) : null,
                'table_prefix' => $prefix,
                'author' => 'Team D2D',
                'permalink' => '/%postname%/',
                'scope' => 'WordPress post_type=post only',
            ],
            'counts' => $counts,
            'target' => $target,
            'rows' => $rows,
            'zip_ready' => $zipIndex !== null,
        ];
    }

    public function importReady(string $sqlPath, ?string $uploadsZipPath, ?int $actorUserId = null, string $prefix = 'd2d_'): array
    {
        $analysis = $this->analyze($sqlPath, $uploadsZipPath, $prefix);
        if (! ($analysis['target']['content_posts_ready'] ?? false)) {
            throw new RuntimeException('content_posts target is not compatible. Run Dry Run and review target diagnostics first.');
        }

        $source = $this->loadSource($sqlPath, $prefix);
        $zipIndex = $this->buildZipIndex($uploadsZipPath);
        $rowStatus = collect($analysis['rows'])->keyBy('source_id');
        $results = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'seo_warnings' => 0, 'media_warnings' => 0, 'items' => []];

        foreach ($source['posts'] as $post) {
            $sourceId = (int) $post['ID'];
            $status = $rowStatus->get($sourceId);
            if (! $status || $status['result'] !== 'ready') {
                $results['skipped']++;
                continue;
            }

            $warnings = [];

            try {
                $destinationId = DB::transaction(function () use ($post, $source, $uploadsZipPath, $zipIndex, $actorUserId, &$warnings) {
                    $title = trim((string) $post['post_title']);
                    $slug = trim((string) $post['post_name']) ?: Str::slug($title);
                    $content = $this->cleanGutenberg((string) ($post['post_content'] ?? ''));

                    foreach ($this->contentMediaReferences($content) as $relative) {
                        if (! $this->copyMediaFromZip($uploadsZipPath, $zipIndex, $relative)) {
                            $warnings[] = 'Missing in-content media: '.$relative;
                        }
                    }
                    $content = $this->rewriteUploadUrls($content);

                    $featured = null;
                    $thumbnailId = (int) ($this->metaFirst($source['meta'], (int) $post['ID'], '_thumbnail_id') ?? 0);
                    if ($thumbnailId && isset($source['attachments'][$thumbnailId])) {
                        $featuredRelative = $this->metaFirst($source['meta'], $thumbnailId, '_wp_attached_file');
                        if (is_string($featuredRelative) && $featuredRelative !== '') {
                            if ($this->copyMediaFromZip($uploadsZipPath, $zipIndex, $featuredRelative)) {
                                $featured = '/storage/legacy-wordpress/'.$this->normalizeRelativePath($featuredRelative);
                            } else {
                                $warnings[] = 'Missing featured image: '.$featuredRelative;
                            }
                        }
                    }

                    $payload = $this->contentPostPayload($post, $slug, $content, $featured, $actorUserId);
                    $destinationId = DB::table('content_posts')->insertGetId($payload);

                    $yoast = $this->yoastMeta($post, $source['meta'], $slug);
                    $seoResult = $this->upsertSeo($destinationId, $yoast);
                    if (! $seoResult['ok']) {
                        $warnings[] = 'SEO: '.$seoResult['message'];
                    }

                    DB::table('wordpress_imports')->insert([
                        'source_type' => 'post',
                        'source_id' => (int) $post['ID'],
                        'source_slug' => $slug,
                        'source_status' => (string) ($post['post_status'] ?? ''),
                        'source_url' => 'https://dares2dream.com/'.$slug.'/',
                        'source_checksum' => $this->checksum($post),
                        'destination_type' => 'content_post',
                        'destination_id' => $destinationId,
                        'result' => 'imported',
                        'source_meta' => json_encode($yoast, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'notes' => $warnings ? json_encode($warnings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                        'imported_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $destinationId;
                });

                $results['imported']++;
                foreach ($warnings as $warning) {
                    if (str_starts_with($warning, 'SEO:')) {
                        $results['seo_warnings']++;
                    }
                    if (str_contains($warning, 'media') || str_contains($warning, 'image')) {
                        $results['media_warnings']++;
                    }
                }
                $results['items'][] = ['source_id' => $sourceId, 'destination_id' => $destinationId, 'title' => $post['post_title'], 'warnings' => $warnings];
            } catch (\Throwable $e) {
                report($e);
                $results['failed']++;
                $results['items'][] = ['source_id' => $sourceId, 'title' => $post['post_title'], 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function loadSource(string $sqlPath, string $prefix): array
    {
        $posts = [];
        $attachments = [];
        $postsTable = $prefix.'posts';
        $postmetaTable = $prefix.'postmeta';

        foreach ($this->parser->rows($sqlPath, $postsTable) as $row) {
            $type = (string) ($row['post_type'] ?? '');
            $status = (string) ($row['post_status'] ?? '');
            $id = (int) ($row['ID'] ?? 0);

            if ($type === 'post' && ! in_array($status, ['trash', 'auto-draft', 'inherit'], true)) {
                $posts[$id] = $row;
            } elseif ($type === 'attachment') {
                $attachments[$id] = $row;
            }
        }

        $interestingIds = array_fill_keys(array_merge(array_keys($posts), array_keys($attachments)), true);
        $meta = [];

        foreach ($this->parser->rows($sqlPath, $postmetaTable) as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $key = (string) ($row['meta_key'] ?? '');
            if (! isset($interestingIds[$postId]) || ! in_array($key, $this->metaKeys, true)) {
                continue;
            }

            $value = $row['meta_value'] ?? null;
            if (isset($meta[$postId][$key])) {
                if (! is_array($meta[$postId][$key])) {
                    $meta[$postId][$key] = [$meta[$postId][$key]];
                }
                $meta[$postId][$key][] = $value;
            } else {
                $meta[$postId][$key] = $value;
            }
        }

        return ['posts' => $posts, 'attachments' => $attachments, 'meta' => $meta];
    }

    private function existingTargets(): array
    {
        if (! Schema::hasTable('content_posts')) {
            return ['slugs' => [], 'titles' => []];
        }

        $columns = Schema::getColumnListing('content_posts');
        $slugs = [];
        $titles = [];

        if (in_array('slug', $columns, true)) {
            foreach (DB::table('content_posts')->whereNotNull('slug')->pluck('slug') as $slug) {
                $slugs[(string) $slug] = true;
            }
        }
        if (in_array('title', $columns, true)) {
            foreach (DB::table('content_posts')->whereNotNull('title')->pluck('title') as $title) {
                $titles[$this->normalizeTitle((string) $title)] = true;
            }
        }

        return compact('slugs', 'titles');
    }

    private function targetDiagnostics(): array
    {
        $result = [
            'content_posts_ready' => false,
            'seo_meta_ready' => false,
            'warnings' => [],
        ];

        if (! Schema::hasTable('content_posts')) {
            $result['warnings'][] = 'content_posts table is missing.';
            return $result;
        }

        $columns = Schema::getColumnListing('content_posts');
        $hasCore = in_array('title', $columns, true)
            && in_array('slug', $columns, true)
            && (in_array('content', $columns, true) || in_array('body', $columns, true) || in_array('content_html', $columns, true));
        $result['content_posts_ready'] = $hasCore;

        if (! $hasCore) {
            $result['warnings'][] = 'content_posts must contain title, slug and a content/body column.';
        }

        if (! Schema::hasTable('wordpress_imports')) {
            $result['warnings'][] = 'wordpress_imports is not created yet. Run php artisan migrate --force.';
        }

        $result['seo_meta_ready'] = $this->seoRelationColumns() !== null;
        if (! $result['seo_meta_ready']) {
            $result['warnings'][] = 'SEO target mapping was not detected. Posts can import, but Yoast SEO will be reported as a warning until seo_meta is compatible.';
        }

        return $result;
    }

    private function contentPostPayload(array $post, string $slug, string $content, ?string $featured, ?int $actorUserId): array
    {
        $columns = Schema::getColumnListing('content_posts');
        $payload = [];
        $this->setIfColumn($payload, $columns, 'title', trim((string) ($post['post_title'] ?? '')));
        $this->setIfColumn($payload, $columns, 'slug', $slug);
        $this->setIfColumn($payload, $columns, 'author_name', 'Team D2D');
        $this->setIfColumn($payload, $columns, 'excerpt', (string) ($post['post_excerpt'] ?? ''));
        $this->setIfColumn($payload, $columns, 'summary', (string) ($post['post_excerpt'] ?? ''));

        foreach (['content', 'body', 'content_html'] as $column) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $content;
                break;
            }
        }

        $wpStatus = (string) ($post['post_status'] ?? 'draft');
        $status = match ($wpStatus) {
            'publish' => 'published',
            'future' => 'scheduled',
            default => 'draft',
        };
        $this->setIfColumn($payload, $columns, 'status', $status);
        $this->setIfColumn($payload, $columns, 'type', 'blog');
        $this->setIfColumn($payload, $columns, 'content_type', 'blog');
        $this->setIfColumn($payload, $columns, 'featured', false);
        $this->setIfColumn($payload, $columns, 'is_featured', false);
        $this->setIfColumn($payload, $columns, 'featured_image', $featured);
        $this->setIfColumn($payload, $columns, 'featured_image_path', $featured);
        $this->setIfColumn($payload, $columns, 'image', $featured);

        if ($actorUserId) {
            foreach (['created_by_user_id', 'updated_by_user_id', 'author_user_id', 'user_id'] as $column) {
                $this->setIfColumn($payload, $columns, $column, $actorUserId);
            }
        }

        $postDate = $this->validDate((string) ($post['post_date'] ?? '')) ? $post['post_date'] : now();
        $postModified = $this->validDate((string) ($post['post_modified'] ?? '')) ? $post['post_modified'] : $postDate;
        $this->setIfColumn($payload, $columns, 'published_at', $wpStatus === 'publish' ? $postDate : null);
        $this->setIfColumn($payload, $columns, 'scheduled_at', $wpStatus === 'future' ? $postDate : null);
        $this->setIfColumn($payload, $columns, 'created_at', $postDate);
        $this->setIfColumn($payload, $columns, 'updated_at', $postModified);

        return $payload;
    }

    private function upsertSeo(int $contentPostId, array $yoast): array
    {
        $relation = $this->seoRelationColumns();
        if (! $relation) {
            return ['ok' => false, 'message' => 'seo_meta relationship columns were not detected'];
        }

        $columns = Schema::getColumnListing('seo_meta');
        $keys = [];
        if ($relation['mode'] === 'direct') {
            $keys[$relation['id']] = $contentPostId;
        } else {
            $keys[$relation['id']] = $contentPostId;
            $keys[$relation['type']] = $relation['type'] === 'seoable_type' || $relation['type'] === 'model_type'
                ? 'App\\Models\\ContentPost'
                : 'content_post';
        }

        $payload = $keys;
        $this->setFirstMatching($payload, $columns, ['seo_title', 'title'], $yoast['seo_title']);
        $this->setFirstMatching($payload, $columns, ['meta_description', 'description'], $yoast['meta_description']);
        $this->setFirstMatching($payload, $columns, ['canonical_url', 'canonical'], $yoast['canonical_url']);
        $this->setFirstMatching($payload, $columns, ['og_title', 'open_graph_title'], $yoast['og_title']);
        $this->setFirstMatching($payload, $columns, ['og_description', 'open_graph_description'], $yoast['og_description']);
        $this->setFirstMatching($payload, $columns, ['og_image', 'og_image_url', 'open_graph_image'], $yoast['og_image']);
        $this->setIfColumn($payload, $columns, 'indexable', true);
        $this->setIfColumn($payload, $columns, 'is_indexable', true);
        $this->setIfColumn($payload, $columns, 'created_at', now());
        $this->setIfColumn($payload, $columns, 'updated_at', now());

        try {
            DB::table('seo_meta')->updateOrInsert($keys, $payload);
            return ['ok' => true, 'message' => 'Yoast SEO mapped'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function seoRelationColumns(): ?array
    {
        if (! Schema::hasTable('seo_meta')) {
            return null;
        }

        $columns = Schema::getColumnListing('seo_meta');
        if (in_array('content_post_id', $columns, true)) {
            return ['mode' => 'direct', 'id' => 'content_post_id'];
        }

        foreach ([
            ['seoable_type', 'seoable_id'],
            ['model_type', 'model_id'],
            ['entity_type', 'entity_id'],
            ['content_type', 'content_id'],
        ] as [$type, $id]) {
            if (in_array($type, $columns, true) && in_array($id, $columns, true)) {
                return ['mode' => 'poly', 'type' => $type, 'id' => $id];
            }
        }

        return null;
    }

    private function yoastMeta(array $post, array $meta, string $slug): array
    {
        $postId = (int) $post['ID'];
        $title = (string) ($this->metaFirst($meta, $postId, '_yoast_wpseo_title') ?: $post['post_title']);
        $description = (string) ($this->metaFirst($meta, $postId, '_yoast_wpseo_metadesc') ?: $post['post_excerpt']);
        $canonical = (string) ($this->metaFirst($meta, $postId, '_yoast_wpseo_canonical') ?: 'https://dares2dream.com/'.$slug.'/');
        $ogTitle = (string) ($this->metaFirst($meta, $postId, '_yoast_wpseo_opengraph-title') ?: $title);
        $ogDescription = (string) ($this->metaFirst($meta, $postId, '_yoast_wpseo_opengraph-description') ?: $description);
        $ogImage = (string) ($this->metaFirst($meta, $postId, '_yoast_wpseo_opengraph-image') ?: '');
        if ($ogImage !== '') {
            $ogImage = $this->rewriteUploadUrls($ogImage);
        }

        return [
            'seo_title' => $title,
            'meta_description' => $description,
            'canonical_url' => $canonical,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $ogImage ?: null,
            'old_slug' => $this->metaFirst($meta, $postId, '_wp_old_slug'),
        ];
    }

    private function mediaReferencesForPost(array $post, array $meta, array $attachments): array
    {
        $paths = $this->contentMediaReferences((string) ($post['post_content'] ?? ''));
        $thumbnailId = (int) ($this->metaFirst($meta, (int) $post['ID'], '_thumbnail_id') ?? 0);
        if ($thumbnailId && isset($attachments[$thumbnailId])) {
            $featured = $this->metaFirst($meta, $thumbnailId, '_wp_attached_file');
            if (is_string($featured) && $featured !== '') {
                $paths[] = $this->normalizeRelativePath($featured);
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function contentMediaReferences(string $content): array
    {
        preg_match_all('#(?:https?:)?//(?:www\.)?dares2dream\.com/wp-content/uploads/([^"\'\s<>]+)|/wp-content/uploads/([^"\'\s<>]+)#i', $content, $matches, PREG_SET_ORDER);
        $paths = [];
        foreach ($matches as $match) {
            $raw = $match[1] ?: ($match[2] ?? '');
            $raw = preg_split('/[?#]/', $raw, 2)[0] ?? '';
            $raw = rtrim($raw, ',);');
            if ($raw !== '') {
                $paths[] = $this->normalizeRelativePath($raw);
            }
        }
        return array_values(array_unique($paths));
    }

    private function cleanGutenberg(string $content): string
    {
        $content = preg_replace('/<!--\s*\/?wp:[\s\S]*?-->/', '', $content) ?? $content;
        return trim($content);
    }

    private function rewriteUploadUrls(string $content): string
    {
        $needles = [
            'https://dares2dream.com/wp-content/uploads/',
            'http://dares2dream.com/wp-content/uploads/',
            'https://www.dares2dream.com/wp-content/uploads/',
            'http://www.dares2dream.com/wp-content/uploads/',
            '//dares2dream.com/wp-content/uploads/',
            '/wp-content/uploads/',
        ];
        return str_ireplace($needles, '/storage/legacy-wordpress/', $content);
    }

    private function buildZipIndex(?string $zipPath): ?array
    {
        if (! $zipPath || ! is_file($zipPath) || ! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $index = [];
        $maxEntries = min($zip->numFiles, 100000);
        for ($i = 0; $i < $maxEntries; $i++) {
            $name = $this->normalizeRelativePath((string) $zip->getNameIndex($i));
            if ($name === '' || str_contains('/'.$name.'/', '/../')) {
                continue;
            }
            $index[$name] = $name;

            if (preg_match('#(?:^|/)wp-content/uploads/(.+)$#i', $name, $m)) {
                $index[$this->normalizeRelativePath($m[1])] = $name;
            } elseif (preg_match('#(?:^|/)uploads/(.+)$#i', $name, $m)) {
                $index[$this->normalizeRelativePath($m[1])] = $name;
            }
        }

        $zip->close();
        return $index;
    }

    private function zipEntryFor(?array $index, string $relative): ?string
    {
        if ($index === null) {
            return null;
        }
        $relative = $this->normalizeRelativePath($relative);
        return $index[$relative] ?? $index['uploads/'.$relative] ?? $index['wp-content/uploads/'.$relative] ?? null;
    }

    private function copyMediaFromZip(?string $zipPath, ?array $index, string $relative): bool
    {
        $relative = $this->normalizeRelativePath($relative);
        if ($relative === '' || str_contains('/'.$relative.'/', '/../')) {
            return false;
        }

        $destination = 'legacy-wordpress/'.$relative;
        if (Storage::disk('public')->exists($destination)) {
            return true;
        }

        $entry = $this->zipEntryFor($index, $relative);
        if (! $zipPath || ! $entry || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $stream = $zip->getStream($entry);
        if (! $stream) {
            $zip->close();
            return false;
        }

        try {
            $ok = Storage::disk('public')->writeStream($destination, $stream);
        } finally {
            fclose($stream);
            $zip->close();
        }

        return (bool) $ok;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = preg_replace('#^\./+#', '', $path) ?? $path;
        return ltrim($path, '/');
    }

    private function metaFirst(array $meta, int $postId, string $key): mixed
    {
        $value = $meta[$postId][$key] ?? null;
        return is_array($value) ? ($value[0] ?? null) : $value;
    }

    private function checksum(array $post): string
    {
        return hash('sha256', implode('|', [
            (string) ($post['ID'] ?? ''),
            (string) ($post['post_name'] ?? ''),
            (string) ($post['post_title'] ?? ''),
            (string) ($post['post_content'] ?? ''),
            (string) ($post['post_modified'] ?? ''),
        ]));
    }

    private function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $title) ?? $title));
    }

    private function validDate(string $date): bool
    {
        return $date !== '' && $date !== '0000-00-00 00:00:00';
    }

    private function setIfColumn(array &$payload, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $payload[$column] = $value;
        }
    }

    private function setFirstMatching(array &$payload, array $columns, array $candidates, mixed $value): void
    {
        foreach ($candidates as $column) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $value;
                return;
            }
        }
    }
}
