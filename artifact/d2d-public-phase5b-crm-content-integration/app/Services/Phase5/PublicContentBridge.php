<?php

namespace App\Services\Phase5;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicContentBridge
{
    public function diagnostics(): array
    {
        $tables = ['content_posts', 'opportunities', 'guidebook_resources', 'seo_meta', 'universities'];
        $out = [];

        foreach ($tables as $table) {
            $out[$table] = [
                'exists' => Schema::hasTable($table),
                'columns' => Schema::hasTable($table) ? Schema::getColumnListing($table) : [],
                'count' => Schema::hasTable($table) ? DB::table($table)->count() : null,
            ];
        }

        $out['content_type_counts'] = $this->contentTypeCounts();
        $out['university_source'] = $this->universitySource();
        $out['opportunity_source'] = $this->opportunitySource();
        $out['seo_relation'] = $this->seoRelation();

        return $out;
    }

    public function homeFeed(int $limit = 6): array
    {
        return [
            'blog' => $this->blogs($limit)->values(),
            'universities' => $this->universities($limit)->values(),
            'opportunities' => $this->opportunities($limit)->values(),
            'guidebooks' => $this->guidebooks($limit)->values(),
        ];
    }

    public function blogs(int $limit = 24, ?string $search = null): Collection
    {
        if (!Schema::hasTable('content_posts')) return collect();

        $columns = Schema::getColumnListing('content_posts');
        $q = DB::table('content_posts');
        $this->applyPublished($q, $columns);
        $this->applyContentTypes($q, $columns, ['blog', 'post', 'article']);
        $this->applySearch($q, $columns, $search);
        $this->applyNewest($q, $columns);

        return $q->limit($limit)->get()->map(fn ($row) => $this->normalizeContentPost($row, 'blog'));
    }

    public function blogBySlug(string $slug): ?array
    {
        return $this->contentPostBySlug($slug, ['blog', 'post', 'article'], 'blog');
    }

    public function universities(int $limit = 24, ?string $search = null): Collection
    {
        if (Schema::hasTable('universities')) {
            return $this->genericTableList('universities', $limit, $search, 'university');
        }

        if (!Schema::hasTable('content_posts')) return collect();
        $columns = Schema::getColumnListing('content_posts');
        $q = DB::table('content_posts');
        $this->applyPublished($q, $columns);
        $this->applyContentTypes($q, $columns, ['university', 'universities']);
        $this->applySearch($q, $columns, $search);
        $this->applyNewest($q, $columns);

        return $q->limit($limit)->get()->map(fn ($row) => $this->normalizeContentPost($row, 'university'));
    }

    public function universityBySlug(string $slug): ?array
    {
        if (Schema::hasTable('universities')) {
            return $this->genericTableBySlug('universities', $slug, 'university');
        }
        return $this->contentPostBySlug($slug, ['university', 'universities'], 'university');
    }

    public function opportunities(int $limit = 24, ?string $search = null, ?string $kind = null): Collection
    {
        if (Schema::hasTable('opportunities')) {
            $columns = Schema::getColumnListing('opportunities');
            $q = DB::table('opportunities');
            $this->applyPublished($q, $columns);
            $this->applySearch($q, $columns, $search);

            if ($kind) {
                foreach (['type', 'opportunity_type', 'category'] as $col) {
                    if (in_array($col, $columns, true)) {
                        $q->where($col, $kind);
                        break;
                    }
                }
            }

            $this->applyNewest($q, $columns);
            return $q->limit($limit)->get()->map(fn ($row) => $this->normalizeGeneric($row, 'opportunity'));
        }

        if (!Schema::hasTable('content_posts')) return collect();
        $columns = Schema::getColumnListing('content_posts');
        $q = DB::table('content_posts');
        $this->applyPublished($q, $columns);
        $types = $kind ? [$kind] : ['opportunity', 'scholarship', 'job', 'internship', 'fellowship'];
        $this->applyContentTypes($q, $columns, $types);
        $this->applySearch($q, $columns, $search);
        $this->applyNewest($q, $columns);
        return $q->limit($limit)->get()->map(fn ($row) => $this->normalizeContentPost($row, 'opportunity'));
    }

    public function opportunityBySlug(string $slug): ?array
    {
        if (Schema::hasTable('opportunities')) {
            return $this->genericTableBySlug('opportunities', $slug, 'opportunity');
        }
        return $this->contentPostBySlug($slug, ['opportunity', 'scholarship', 'job', 'internship', 'fellowship'], 'opportunity');
    }

    public function guidebooks(int $limit = 24, ?string $search = null): Collection
    {
        if (!Schema::hasTable('guidebook_resources')) return collect();
        $columns = Schema::getColumnListing('guidebook_resources');
        $q = DB::table('guidebook_resources');
        $this->applyPublished($q, $columns);

        if (in_array('access_level', $columns, true)) {
            $q->whereIn('access_level', ['public', 'free']);
        } elseif (in_array('access', $columns, true)) {
            $q->whereIn('access', ['public', 'free']);
        }

        $this->applySearch($q, $columns, $search);

        if (in_array('featured', $columns, true)) $q->orderByDesc('featured');
        elseif (in_array('is_featured', $columns, true)) $q->orderByDesc('is_featured');
        $this->applyNewest($q, $columns);

        return $q->limit($limit)->get()->map(fn ($row) => $this->normalizeGeneric($row, 'guidebook'));
    }

    public function seoForContentPost(int $id, string $slug, string $fallbackTitle = '', string $fallbackDescription = ''): array
    {
        $seo = [
            'title' => $fallbackTitle,
            'description' => $fallbackDescription,
            'canonical' => url('/' . ltrim($slug, '/')),
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
        ];

        if (!Schema::hasTable('seo_meta')) return $seo;
        $relation = $this->seoRelation();
        if (!$relation) return $seo;

        $q = DB::table('seo_meta');
        if ($relation['mode'] === 'direct') {
            $q->where($relation['id'], $id);
        } else {
            $q->where($relation['id'], $id);
            $typeValues = in_array($relation['type'], ['seoable_type', 'model_type'], true)
                ? ['App\\Models\\ContentPost', 'App\Models\ContentPost']
                : ['content_post', 'blog', 'post'];
            $q->whereIn($relation['type'], $typeValues);
        }

        $row = $q->first();
        if (!$row) return $seo;
        $arr = (array) $row;

        $seo['title'] = $this->first($arr, ['meta_title', 'seo_title', 'title']) ?: $seo['title'];
        $seo['description'] = $this->first($arr, ['meta_description', 'seo_description', 'description']) ?: $seo['description'];
        $seo['canonical'] = $this->first($arr, ['canonical_url', 'canonical']) ?: $seo['canonical'];
        $seo['og_title'] = $this->first($arr, ['og_title', 'opengraph_title']);
        $seo['og_description'] = $this->first($arr, ['og_description', 'opengraph_description']);
        $seo['og_image'] = $this->first($arr, ['og_image', 'opengraph_image']);

        return $seo;
    }

    public function contentTypeCounts(): array
    {
        if (!Schema::hasTable('content_posts')) return [];
        $columns = Schema::getColumnListing('content_posts');
        $typeCol = in_array('type', $columns, true) ? 'type' : (in_array('content_type', $columns, true) ? 'content_type' : null);
        if (!$typeCol) return [];

        return DB::table('content_posts')
            ->select($typeCol, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($typeCol)
            ->orderByDesc('aggregate')
            ->pluck('aggregate', $typeCol)
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function contentPostBySlug(string $slug, array $types, string $kind): ?array
    {
        if (!Schema::hasTable('content_posts')) return null;
        $columns = Schema::getColumnListing('content_posts');
        if (!in_array('slug', $columns, true)) return null;

        $q = DB::table('content_posts')->where('slug', $slug);
        $this->applyPublished($q, $columns);
        $this->applyContentTypes($q, $columns, $types);
        $row = $q->first();
        if (!$row) return null;

        $item = $this->normalizeContentPost($row, $kind);
        $item['seo'] = $this->seoForContentPost((int) ($item['id'] ?? 0), $slug, $item['title'] ?? '', $item['summary'] ?? '');
        return $item;
    }

    private function genericTableList(string $table, int $limit, ?string $search, string $kind): Collection
    {
        $columns = Schema::getColumnListing($table);
        $q = DB::table($table);
        $this->applyPublished($q, $columns);
        $this->applySearch($q, $columns, $search);
        $this->applyNewest($q, $columns);
        return $q->limit($limit)->get()->map(fn ($row) => $this->normalizeGeneric($row, $kind));
    }

    private function genericTableBySlug(string $table, string $slug, string $kind): ?array
    {
        $columns = Schema::getColumnListing($table);
        if (!in_array('slug', $columns, true)) return null;
        $q = DB::table($table)->where('slug', $slug);
        $this->applyPublished($q, $columns);
        $row = $q->first();
        return $row ? $this->normalizeGeneric($row, $kind) : null;
    }

    private function applyPublished(Builder $q, array $columns): void
    {
        if (in_array('status', $columns, true)) {
            $q->whereIn('status', ['published', 'publish', 'active', 'open']);
        } elseif (in_array('is_published', $columns, true)) {
            $q->where('is_published', 1);
        } elseif (in_array('published', $columns, true)) {
            $q->where('published', 1);
        }
    }

    private function applyContentTypes(Builder $q, array $columns, array $types): void
    {
        if (in_array('type', $columns, true)) $q->whereIn('type', $types);
        elseif (in_array('content_type', $columns, true)) $q->whereIn('content_type', $types);
    }

    private function applySearch(Builder $q, array $columns, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') return;
        $searchable = array_values(array_intersect(['title', 'name', 'summary', 'excerpt', 'description', 'country', 'location'], $columns));
        if (!$searchable) return;

        $q->where(function (Builder $inner) use ($searchable, $search) {
            foreach ($searchable as $i => $column) {
                $i === 0
                    ? $inner->where($column, 'like', '%' . $search . '%')
                    : $inner->orWhere($column, 'like', '%' . $search . '%');
            }
        });
    }

    private function applyNewest(Builder $q, array $columns): void
    {
        foreach (['published_at', 'release_date', 'created_at', 'id'] as $col) {
            if (in_array($col, $columns, true)) {
                $q->orderByDesc($col);
                return;
            }
        }
    }

    private function normalizeContentPost(object $row, string $kind): array
    {
        $a = (array) $row;
        return [
            'id' => isset($a['id']) ? (int) $a['id'] : null,
            'kind' => $kind,
            'title' => $this->first($a, ['title', 'name']) ?: 'Untitled',
            'slug' => $this->first($a, ['slug']),
            'summary' => $this->first($a, ['excerpt', 'summary', 'description']),
            'content_html' => $this->first($a, ['content', 'body', 'content_html', 'description']),
            'image' => $this->mediaUrl($this->first($a, ['featured_image', 'image', 'cover_image', 'thumbnail'])),
            'author' => $this->first($a, ['author_name', 'author']) ?: 'Team D2D',
            'country' => $this->first($a, ['country', 'country_name']),
            'location' => $this->first($a, ['location', 'city']),
            'type' => $this->first($a, ['type', 'content_type', 'category']),
            'deadline' => $this->first($a, ['deadline', 'application_deadline', 'deadline_at']),
            'apply_url' => $this->first($a, ['apply_url', 'application_url', 'external_url', 'url']),
            'published_at' => $this->first($a, ['published_at', 'created_at', 'updated_at']),
            'raw' => $a,
        ];
    }

    private function normalizeGeneric(object $row, string $kind): array
    {
        $a = (array) $row;
        return [
            'id' => isset($a['id']) ? (int) $a['id'] : null,
            'kind' => $kind,
            'title' => $this->first($a, ['title', 'name']) ?: 'Untitled',
            'slug' => $this->first($a, ['slug']) ?: Str::slug((string) ($this->first($a, ['title', 'name']) ?: 'item')),
            'summary' => $this->first($a, ['short_description', 'summary', 'excerpt', 'description']),
            'content_html' => $this->first($a, ['content', 'body', 'content_html', 'full_description', 'description']),
            'image' => $this->mediaUrl($this->first($a, ['featured_image', 'cover_image', 'image', 'thumbnail'])),
            'author' => $this->first($a, ['author_name', 'author']) ?: 'Team D2D',
            'country' => $this->first($a, ['country', 'country_name']),
            'location' => $this->first($a, ['location', 'city']),
            'type' => $this->first($a, ['resource_type', 'type', 'opportunity_type', 'category']),
            'deadline' => $this->first($a, ['deadline', 'application_deadline', 'deadline_at']),
            'apply_url' => $this->first($a, ['apply_url', 'application_url', 'external_url', 'url']),
            'published_at' => $this->first($a, ['published_at', 'release_date', 'created_at']),
            'raw' => $a,
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (preg_match('#^https?://#i', $path)) return $path;
        if (str_starts_with($path, '/v11/')) return $path;
        if (str_starts_with($path, '/storage/')) return '/phase5-media/' . ltrim(substr($path, strlen('/storage/')), '/');
        return $path;
    }

    public function crmPublicMediaPath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '../')) return null;
        $base = realpath('/home/daresdre/crm.dares2dream.com/public/storage');
        if (!$base) return null;
        $candidate = realpath($base . '/' . $relative);
        if (!$candidate || !is_file($candidate) || !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) return null;
        return $candidate;
    }

    private function first(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
        }
        return null;
    }

    private function universitySource(): string
    {
        if (Schema::hasTable('universities')) return 'universities';
        if (Schema::hasTable('content_posts')) {
            $counts = $this->contentTypeCounts();
            foreach (['university', 'universities'] as $type) if (($counts[$type] ?? 0) > 0) return 'content_posts:' . $type;
        }
        return 'none-detected';
    }

    private function opportunitySource(): string
    {
        if (Schema::hasTable('opportunities')) return 'opportunities';
        if (Schema::hasTable('content_posts')) return 'content_posts-fallback';
        return 'none-detected';
    }

    private function seoRelation(): ?array
    {
        if (!Schema::hasTable('seo_meta')) return null;
        $columns = Schema::getColumnListing('seo_meta');
        if (in_array('content_post_id', $columns, true)) return ['mode' => 'direct', 'id' => 'content_post_id'];
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
}
