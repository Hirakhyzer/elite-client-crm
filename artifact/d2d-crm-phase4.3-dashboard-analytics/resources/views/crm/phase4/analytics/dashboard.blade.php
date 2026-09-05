@php
    $d2d43 = app(\App\Services\Analytics\AnalyticsDashboardService::class)->dashboard(30);
    $d2d43Max = max(1, collect($d2d43['trend'])->max('views') ?: 1);
    $d2d43Count = max(1, count($d2d43['trend']));
    $d2d43Points = [];
    foreach ($d2d43['trend'] as $i => $row) {
        $x = $d2d43Count > 1 ? ($i / ($d2d43Count - 1)) * 100 : 50;
        $y = 90 - (($row['views'] / $d2d43Max) * 72);
        $d2d43Points[] = round($x,2).','.round($y,2);
    }
@endphp

<section class="d2d43-dashboard-block">
    <div class="d2d43-section-head">
        <div><div class="d2d43-kicker">Insights</div><h2>Content performance</h2></div>
        <a href="{{ route('crm.analytics.index') }}">Open Analytics →</a>
    </div>

    <div class="d2d43-stats">
        <a href="{{ route('crm.phase4.guidebooks.index') }}" class="d2d43-stat d2d43-stat-link">
            <span class="d2d43-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5z"/></svg></span>
            <span><strong>{{ number_format($d2d43['guidebooks']['total']) }}</strong><small>Guidebooks & resources</small><em>{{ $d2d43['guidebooks']['published'] }} published · {{ $d2d43['guidebooks']['draft'] }} drafts</em></span>
        </a>
        <div class="d2d43-stat"><span><strong>{{ number_format($d2d43['analytics']['views']) }}</strong><small>Public views</small><em>Last 30 days</em></span></div>
        <div class="d2d43-stat"><span><strong>{{ number_format($d2d43['analytics']['saves']) }}</strong><small>Saves</small><em>Last 30 days</em></span></div>
        <div class="d2d43-stat"><span><strong>{{ number_format($d2d43['analytics']['conversions']) }}</strong><small>Conversions</small><em>Last 30 days</em></span></div>
    </div>

    <div class="d2d43-chart-card">
        <div class="d2d43-chart-head"><div><strong>30-day public views</strong><span>D2D internal analytics</span></div></div>
        @if($d2d43['has_data'] && collect($d2d43['trend'])->sum('views') > 0)
            <svg class="d2d43-chart" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="30-day public views">
                <line x1="0" y1="90" x2="100" y2="90" class="d2d43-grid"/>
                <line x1="0" y1="54" x2="100" y2="54" class="d2d43-grid"/>
                <line x1="0" y1="18" x2="100" y2="18" class="d2d43-grid"/>
                <polyline points="{{ implode(' ', $d2d43Points) }}" class="d2d43-line"/>
            </svg>
        @else
            <div class="d2d43-empty">No public tracking data yet. The analytics foundation is ready for the Laravel public-site connection.</div>
        @endif
    </div>
</section>

<style>
.d2d43-dashboard-block{margin-top:24px}.d2d43-section-head{display:flex;justify-content:space-between;gap:12px;align-items:end;margin-bottom:12px}.d2d43-section-head h2{margin:3px 0 0;font-size:22px}.d2d43-section-head>a{font-size:12px;color:#956300;text-decoration:none;font-weight:800}.d2d43-kicker{text-transform:uppercase;letter-spacing:.15em;font-size:10px;font-weight:850;color:#8a8780}.d2d43-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.d2d43-stat{min-height:108px;padding:16px;border:1px solid #e7e1d6;border-radius:16px;background:#fff;box-shadow:0 8px 30px rgba(31,25,15,.04);display:flex;gap:11px;align-items:center;color:#171717;text-decoration:none}.d2d43-stat-link:hover{border-color:#e3b22f}.d2d43-stat strong{display:block;font-size:28px;line-height:1;font-weight:900}.d2d43-stat small{display:block;font-size:12px;font-weight:800;margin-top:7px}.d2d43-stat em{display:block;font-size:10px;color:#96928a;font-style:normal;margin-top:4px}.d2d43-stat-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:#fff2c6;color:#a36d00;flex:0 0 38px}.d2d43-stat-icon svg{width:19px;height:19px}.d2d43-chart-card{margin-top:12px;padding:16px;border:1px solid #e7e1d6;border-radius:16px;background:#fff;box-shadow:0 8px 30px rgba(31,25,15,.04)}.d2d43-chart-head strong,.d2d43-chart-head span{display:block}.d2d43-chart-head strong{font-size:14px}.d2d43-chart-head span{font-size:10px;color:#99958d;margin-top:2px}.d2d43-chart{width:100%;height:170px;margin-top:12px}.d2d43-grid{stroke:#eee9df;stroke-width:.45;vector-effect:non-scaling-stroke}.d2d43-line{fill:none;stroke:#f4af00;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}.d2d43-empty{margin-top:12px;min-height:120px;border:1px dashed #ddd6ca;border-radius:12px;background:#faf9f6;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px;color:#817d75;font-size:12px}
@media(max-width:1000px){.d2d43-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.d2d43-stats{grid-template-columns:1fr}}
</style>
