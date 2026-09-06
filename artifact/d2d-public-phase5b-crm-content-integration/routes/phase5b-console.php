<?php

use App\Services\Phase5\PublicContentBridge;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('phase5b:doctor', function (PublicContentBridge $bridge) {
    $this->info('=== D2D PHASE 5B — CRM CONTENT INTEGRATION DOCTOR ===');
    $this->line('Laravel: ' . app()->version());
    $this->line('Base: ' . base_path());

    try {
        DB::connection()->getPdo();
        $this->info('DB: CONNECTED');
    } catch (Throwable $e) {
        $this->error('DB: FAILED - ' . $e->getMessage());
        return 1;
    }

    $d = $bridge->diagnostics();
    foreach (['content_posts', 'opportunities', 'guidebook_resources', 'seo_meta', 'universities'] as $table) {
        $info = $d[$table];
        $this->line(str_pad($table, 24) . ($info['exists'] ? 'OK count=' . $info['count'] : 'MISSING'));
        if ($info['exists']) {
            $this->line('  columns: ' . implode(', ', $info['columns']));
        }
    }

    $this->newLine();
    $this->info('Detected content types');
    if (!$d['content_type_counts']) {
        $this->line('  none / no type column');
    } else {
        foreach ($d['content_type_counts'] as $type => $count) {
            $this->line('  ' . ($type === '' ? '(blank)' : $type) . ': ' . $count);
        }
    }

    $this->newLine();
    $this->line('University source: ' . $d['university_source']);
    $this->line('Opportunity source: ' . $d['opportunity_source']);
    $this->line('SEO relation: ' . json_encode($d['seo_relation'], JSON_UNESCAPED_SLASHES));

    $this->newLine();
    $this->info('Public bridge counts');
    $this->line('  Blogs: ' . $bridge->blogs(500)->count());
    $this->line('  Universities: ' . $bridge->universities(500)->count());
    $this->line('  Opportunities: ' . $bridge->opportunities(500)->count());
    $this->line('  Public guidebooks: ' . $bridge->guidebooks(500)->count());

    $this->newLine();
    $this->info('No migrations and no database writes were performed by Phase 5B.');
    return 0;
})->purpose('Inspect Phase 5B CRM/public content mapping');
