<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsDashboardService
{
    private array $viewEvents = ['page_view', 'blog_view', 'opportunity_view', 'university_view', 'guidebook_view'];
    private array $saveEvents = ['content_save', 'opportunity_save', 'university_save'];
    private array $shareEvents = ['share', 'content_share'];
    private array $conversionEvents = ['application_created', 'consultant_contacted', 'tool_completed'];
    private array $downloadEvents = ['guidebook_download'];

    public function dashboard(int $days = 30): array
    {
        return [
            'guidebooks' => $this->guidebookCounts(),
            'analytics' => $this->summary($days),
            'trend' => $this->trend($days),
            'has_data' => $this->hasData(),
        ];
    }

    public function summary(int $days = 30, ?string $resourceType = null): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return $this->emptySummary();
        }

        $from = now()->subDays(max(1, $days) - 1)->startOfDay();
        $base = DB::table('analytics_events')->where('occurred_at', '>=', $from);
        if ($resourceType) {
            $base->where('resource_type', $resourceType);
        }

        $countFor = function (array $types) use ($base): int {
            $q = clone $base;
            return (int) $q->whereIn('event_type', $types)->count();
        };

        $unique = clone $base;
        $uniqueSessions = (int) $unique->whereIn('event_type', $this->viewEvents)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');

        return [
            'views' => $countFor($this->viewEvents),
            'unique_sessions' => $uniqueSessions,
            'saves' => $countFor($this->saveEvents),
            'shares' => $countFor($this->shareEvents),
            'conversions' => $countFor($this->conversionEvents),
            'downloads' => $countFor($this->downloadEvents),
        ];
    }

    public function trend(int $days = 30, ?string $resourceType = null): array
    {
        $days = max(1, min(90, $days));
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = [];

        if (Schema::hasTable('analytics_events')) {
            $query = DB::table('analytics_events')
                ->selectRaw('DATE(occurred_at) as day, COUNT(*) as total')
                ->where('occurred_at', '>=', $start)
                ->whereIn('event_type', $this->viewEvents);
            if ($resourceType) {
                $query->where('resource_type', $resourceType);
            }
            $rows = $query->groupBy(DB::raw('DATE(occurred_at)'))->pluck('total', 'day')->all();
        }

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $trend[] = [
                'date' => $key,
                'label' => $date->format($days > 31 ? 'M j' : 'M j'),
                'views' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $trend;
    }

    public function topContent(int $days = 30, ?string $resourceType = null, int $limit = 12): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return [];
        }

        $query = DB::table('analytics_events')
            ->select('resource_type', 'resource_id', DB::raw('COUNT(*) as views'))
            ->where('occurred_at', '>=', now()->subDays(max(1, $days) - 1)->startOfDay())
            ->whereIn('event_type', $this->viewEvents)
            ->whereNotNull('resource_type')
            ->whereNotNull('resource_id');

        if ($resourceType) {
            $query->where('resource_type', $resourceType);
        }

        $rows = $query->groupBy('resource_type', 'resource_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'resource_type' => $row->resource_type,
                'resource_id' => (int) $row->resource_id,
                'title' => $this->resourceTitle($row->resource_type, (int) $row->resource_id),
                'views' => (int) $row->views,
            ];
        })->all();
    }

    public function guidebookCounts(): array
    {
        if (! Schema::hasTable('guidebook_resources')) {
            return ['total' => 0, 'published' => 0, 'draft' => 0, 'archived' => 0];
        }

        return [
            'total' => (int) DB::table('guidebook_resources')->count(),
            'published' => (int) DB::table('guidebook_resources')->where('status', 'published')->count(),
            'draft' => (int) DB::table('guidebook_resources')->where('status', 'draft')->count(),
            'archived' => (int) DB::table('guidebook_resources')->where('status', 'archived')->count(),
        ];
    }

    public function hasData(): bool
    {
        return Schema::hasTable('analytics_events') && DB::table('analytics_events')->exists();
    }

    private function emptySummary(): array
    {
        return [
            'views' => 0,
            'unique_sessions' => 0,
            'saves' => 0,
            'shares' => 0,
            'conversions' => 0,
            'downloads' => 0,
        ];
    }

    private function resourceTitle(?string $type, int $id): string
    {
        try {
            return match ($type) {
                'blog' => Schema::hasTable('content_posts') ? (string) (DB::table('content_posts')->where('id', $id)->value('title') ?: 'Blog #'.$id) : 'Blog #'.$id,
                'guidebook' => Schema::hasTable('guidebook_resources') ? (string) (DB::table('guidebook_resources')->where('id', $id)->value('title') ?: 'Guidebook #'.$id) : 'Guidebook #'.$id,
                'university' => Schema::hasTable('institutions') ? (string) (DB::table('institutions')->where('id', $id)->value('name') ?: 'University #'.$id) : 'University #'.$id,
                'scholarship', 'job', 'opportunity' => Schema::hasTable('opportunities') ? (string) (DB::table('opportunities')->where('id', $id)->value('title') ?: ucfirst((string) $type).' #'.$id) : ucfirst((string) $type).' #'.$id,
                default => ucfirst((string) ($type ?: 'Content')).' #'.$id,
            };
        } catch (\Throwable $e) {
            return ucfirst((string) ($type ?: 'Content')).' #'.$id;
        }
    }
}
