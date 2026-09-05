<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class D2dAnalytics
{
    public function record(string $eventType, array $context = []): bool
    {
        if (! Schema::hasTable('analytics_events')) {
            return false;
        }

        $session = $context['session_id'] ?? null;
        if (is_string($session) && $session !== '') {
            $session = hash('sha256', $session);
        }

        DB::table('analytics_events')->insert([
            'event_type' => substr($eventType, 0, 80),
            'resource_type' => isset($context['resource_type']) ? substr((string) $context['resource_type'], 0, 50) : null,
            'resource_id' => isset($context['resource_id']) ? (int) $context['resource_id'] : null,
            'user_id' => isset($context['user_id']) ? (int) $context['user_id'] : null,
            'session_id' => $session,
            'url_path' => isset($context['url_path']) ? substr((string) $context['url_path'], 0, 2048) : null,
            'referrer' => $context['referrer'] ?? null,
            'utm_source' => isset($context['utm_source']) ? substr((string) $context['utm_source'], 0, 255) : null,
            'utm_medium' => isset($context['utm_medium']) ? substr((string) $context['utm_medium'], 0, 255) : null,
            'utm_campaign' => isset($context['utm_campaign']) ? substr((string) $context['utm_campaign'], 0, 255) : null,
            'metadata' => isset($context['metadata']) ? json_encode($context['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'occurred_at' => $context['occurred_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
