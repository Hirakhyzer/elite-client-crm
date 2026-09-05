@extends('__D2D_LAYOUT__')

@section('content')
<div style="max-width:1380px;margin:0 auto;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#f4af00;font-weight:800;">Marketing</div>
            <h1 style="margin:4px 0 6px;">Popup Preview Lab</h1>
            <p style="margin:0;opacity:.68;max-width:760px;">Preview the popup content already saved in Popup Manager without publishing anything to dares2dream.com.</p>
        </div>
        <a href="{{ url('/marketing/popups') }}" class="btn btn-secondary">Back to Popup Manager</a>
    </div>

    @if(!$tableReady)
        <div style="padding:18px;border:1px solid #d9a300;border-radius:14px;background:rgba(244,175,0,.08);">The <code>popup_campaigns</code> table is not available. Phase 3 marketing tables must be installed first.</div>
    @else
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <input name="q" value="{{ request('q') }}" placeholder="Search popup" style="min-width:280px;flex:1;max-width:520px;">
            <button class="btn btn-secondary">Search</button>
            @if(request('q'))<a href="{{ route('crm.phase4_4.popup-preview.index') }}" class="btn btn-secondary">Clear</a>@endif
        </form>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
            @forelse($campaigns as $popup)
                <article style="padding:18px;border:1px solid #e7e1d6;border-radius:16px;background:#fff;box-shadow:0 8px 28px rgba(31,25,15,.045);">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px;">
                        <span style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#7b7b80;">Popup #{{ $popup['id'] }}</span>
                        <span style="padding:5px 8px;border-radius:999px;background:{{ strtolower($popup['status'])==='active' ? 'rgba(22,163,74,.10)' : 'rgba(128,128,128,.10)' }};font-size:11px;font-weight:800;text-transform:uppercase;">{{ $popup['status'] }}</span>
                    </div>
                    <h3 style="margin:0 0 8px;font-size:18px;">{{ $popup['name'] }}</h3>
                    <div style="font-size:13px;opacity:.64;line-height:1.6;margin-bottom:16px;">
                        {{ $popup['trigger'] }}
                        @if($popup['frequency']) · {{ $popup['frequency'] }} @endif
                    </div>
                    <a href="{{ route('crm.phase4_4.popup-preview.show',$popup['id']) }}" target="_blank" class="btn btn-primary" style="width:100%;text-align:center;">Preview Desktop / Mobile</a>
                </article>
            @empty
                <div style="grid-column:1/-1;padding:34px;text-align:center;border:1px dashed #d8d2c7;border-radius:16px;opacity:.68;">No popup campaigns found yet. Create one in Popup Manager, then return here to preview it.</div>
            @endforelse
        </div>
    @endif
</div>
@endsection
