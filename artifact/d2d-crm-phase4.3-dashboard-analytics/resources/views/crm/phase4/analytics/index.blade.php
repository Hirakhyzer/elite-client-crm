@extends('__D2D_LAYOUT__')

@section('content')
@php
    $maxViews = max(1, collect($trend)->max('views') ?: 1);
    $count = max(1, count($trend));
    $points = [];
    foreach ($trend as $i => $row) {
        $x = $count > 1 ? ($i / ($count - 1)) * 100 : 50;
        $y = 92 - (($row['views'] / $maxViews) * 76);
        $points[] = round($x,2).','.round($y,2);
    }
@endphp
<div class="d2d-analytics-page">
    <div class="d2d-a-head">
        <div>
            <div class="d2d-a-kicker">Insights</div>
            <h1>Analytics</h1>
            <p>D2D-owned content performance. Google Analytics will be connected later as a separate traffic source.</p>
        </div>
        <div class="d2d-a-actions">
            @foreach([7,30,90] as $period)
                <a href="{{ route('crm.analytics.index', array_filter(['days'=>$period,'type'=>$type])) }}" class="d2d-a-pill {{ $days===$period ? 'is-active' : '' }}">{{ $period }} days</a>
            @endforeach
        </div>
    </div>

    <form method="GET" class="d2d-a-filter">
        <input type="hidden" name="days" value="{{ $days }}">
        <label>Content type
            <select name="type" onchange="this.form.submit()">
                <option value="">All content</option>
                @foreach($types as $option)
                    <option value="{{ $option }}" @selected($type===$option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <div class="d2d-a-cards">
        @foreach([
            ['Views',$summary['views'],'eye'],
            ['Unique sessions',$summary['unique_sessions'],'users'],
            ['Saves',$summary['saves'],'bookmark'],
            ['Shares',$summary['shares'],'share'],
            ['Conversions',$summary['conversions'],'spark'],
            ['Downloads',$summary['downloads'],'download'],
        ] as [$label,$value,$icon])
            <div class="d2d-a-card"><div class="d2d-a-card-label">{{ $label }}</div><div class="d2d-a-card-value">{{ number_format($value) }}</div><div class="d2d-a-card-foot">Last {{ $days }} days</div></div>
        @endforeach
    </div>

    <div class="d2d-a-grid">
        <section class="d2d-a-panel d2d-a-chart-panel">
            <div class="d2d-a-panel-head"><div><div class="d2d-a-kicker">Trend</div><h2>Public views</h2></div><span>{{ $days }} days</span></div>
            @if($hasData && collect($trend)->sum('views') > 0)
                <div class="d2d-a-chart-wrap">
                    <svg class="d2d-a-chart" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Public views trend">
                        <line x1="0" y1="92" x2="100" y2="92" class="d2d-gridline"/>
                        <line x1="0" y1="66" x2="100" y2="66" class="d2d-gridline"/>
                        <line x1="0" y1="40" x2="100" y2="40" class="d2d-gridline"/>
                        <line x1="0" y1="14" x2="100" y2="14" class="d2d-gridline"/>
                        <polyline points="{{ implode(' ', $points) }}" class="d2d-line"/>
                    </svg>
                    <div class="d2d-a-axis"><span>{{ $trend[0]['label'] ?? '' }}</span><span>{{ $trend[(int) floor((count($trend)-1)/2)]['label'] ?? '' }}</span><span>{{ $trend[count($trend)-1]['label'] ?? '' }}</span></div>
                </div>
            @else
                <div class="d2d-a-empty"><strong>No public analytics yet.</strong><span>The dashboard is ready. Real views will start appearing when the Laravel public site begins sending D2D analytics events.</span></div>
            @endif
        </section>

        <section class="d2d-a-panel">
            <div class="d2d-a-panel-head"><div><div class="d2d-a-kicker">Resources</div><h2>Guidebooks</h2></div><a href="{{ route('crm.phase4.guidebooks.index') }}">Open →</a></div>
            <div class="d2d-guidebook-count">{{ number_format($guidebooks['total']) }}</div>
            <div class="d2d-guidebook-row"><span>Published</span><strong>{{ number_format($guidebooks['published']) }}</strong></div>
            <div class="d2d-guidebook-row"><span>Drafts</span><strong>{{ number_format($guidebooks['draft']) }}</strong></div>
            <div class="d2d-guidebook-row"><span>Archived</span><strong>{{ number_format($guidebooks['archived']) }}</strong></div>
        </section>
    </div>

    <section class="d2d-a-panel d2d-a-top">
        <div class="d2d-a-panel-head"><div><div class="d2d-a-kicker">Performance</div><h2>Top content</h2></div></div>
        @if(count($topContent))
            <div class="d2d-a-table-wrap"><table><thead><tr><th>Content</th><th>Type</th><th class="num">Views</th></tr></thead><tbody>
            @foreach($topContent as $row)
                <tr><td><strong>{{ $row['title'] }}</strong></td><td>{{ ucfirst($row['resource_type']) }}</td><td class="num">{{ number_format($row['views']) }}</td></tr>
            @endforeach
            </tbody></table></div>
        @else
            <div class="d2d-a-empty compact"><strong>No ranked content yet.</strong><span>Once public tracking is active, the most-viewed Blog, University, Scholarship, Job and Guidebook items will appear here.</span></div>
        @endif
    </section>
</div>

<style>
.d2d-analytics-page{max-width:1440px;margin:0 auto;padding:4px 0 32px}.d2d-a-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap;margin-bottom:20px}.d2d-a-head h1{margin:3px 0 6px;font-size:34px}.d2d-a-head p{margin:0;color:#6e6d69;max-width:720px}.d2d-a-kicker{text-transform:uppercase;letter-spacing:.15em;font-weight:850;font-size:10px;color:#a56f00}.d2d-a-actions{display:flex;gap:8px;flex-wrap:wrap}.d2d-a-pill{padding:9px 12px;border:1px solid #ddd7cc;border-radius:10px;text-decoration:none;color:#292929;background:#fff;font-weight:750;font-size:12px}.d2d-a-pill.is-active{background:#171717;border-color:#171717;color:#fff}.d2d-a-filter{margin-bottom:15px;display:flex;justify-content:flex-end}.d2d-a-filter label{font-size:11px;font-weight:800;color:#777;display:flex;align-items:center;gap:8px}.d2d-a-filter select{border:1px solid #ddd7cc;border-radius:9px;padding:8px 30px 8px 10px;background:#fff;color:#222}.d2d-a-cards{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:14px}.d2d-a-card,.d2d-a-panel{background:#fff;border:1px solid #e7e1d6;border-radius:17px;box-shadow:0 8px 30px rgba(31,25,15,.045)}.d2d-a-card{padding:16px}.d2d-a-card-label{font-size:11px;color:#777;font-weight:750}.d2d-a-card-value{font-size:28px;font-weight:900;color:#141414;margin:5px 0}.d2d-a-card-foot{font-size:10px;color:#96938c}.d2d-a-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,.8fr);gap:14px}.d2d-a-panel{padding:18px}.d2d-a-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}.d2d-a-panel-head h2{margin:3px 0 0;font-size:20px}.d2d-a-panel-head>a,.d2d-a-panel-head>span{font-size:11px;color:#956300;text-decoration:none;font-weight:800}.d2d-a-chart-wrap{height:250px;position:relative;padding:6px 0 22px}.d2d-a-chart{width:100%;height:215px;overflow:visible}.d2d-gridline{stroke:#ece8df;stroke-width:.45;vector-effect:non-scaling-stroke}.d2d-line{fill:none;stroke:#f4af00;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}.d2d-a-axis{display:flex;justify-content:space-between;color:#99958b;font-size:10px}.d2d-a-empty{min-height:220px;border:1px dashed #ddd6c9;border-radius:13px;background:#faf9f6;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:24px;color:#777;gap:7px}.d2d-a-empty strong{color:#333}.d2d-a-empty.compact{min-height:100px}.d2d-guidebook-count{font-size:54px;font-weight:950;line-height:1;margin:14px 0 18px;color:#151515}.d2d-guidebook-row{display:flex;justify-content:space-between;border-top:1px solid #eee9df;padding:11px 0;font-size:12px;color:#696762}.d2d-guidebook-row strong{color:#171717}.d2d-a-top{margin-top:14px}.d2d-a-table-wrap{overflow:auto}.d2d-a-table-wrap table{width:100%;border-collapse:collapse;min-width:620px}.d2d-a-table-wrap th,.d2d-a-table-wrap td{padding:12px 8px;border-top:1px solid #eee9df;text-align:left;font-size:12px}.d2d-a-table-wrap thead th{color:#8b887f;font-size:10px;text-transform:uppercase;letter-spacing:.08em}.d2d-a-table-wrap .num{text-align:right}
@media(max-width:1150px){.d2d-a-cards{grid-template-columns:repeat(3,1fr)}}@media(max-width:850px){.d2d-a-grid{grid-template-columns:1fr}.d2d-a-cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.d2d-a-cards{grid-template-columns:1fr 1fr}.d2d-a-head h1{font-size:28px}.d2d-a-card-value{font-size:23px}}
</style>
@endsection
