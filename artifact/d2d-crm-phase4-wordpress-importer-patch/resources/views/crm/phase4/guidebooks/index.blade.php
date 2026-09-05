@extends('crm.layouts.app')

@section('content')
<div style="max-width:1400px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#f4af00;font-weight:800;">Resources</div>
            <h1 style="margin:4px 0 6px;">Guidebooks &amp; Resources</h1>
            <p style="margin:0;opacity:.68;">Create new guidebooks, e-books, checklists, templates and toolkits. New file versions never overwrite the previous version.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a class="btn btn-secondary" href="{{ route('crm.phase4.wordpress.index') }}">WordPress Migration</a>
            <a class="btn btn-primary" href="{{ route('crm.phase4.guidebooks.create') }}">+ New Resource</a>
        </div>
    </div>

    @if(session('success'))<div style="padding:14px 16px;border:1px solid rgba(244,175,0,.35);border-radius:14px;margin-bottom:16px;background:rgba(244,175,0,.08);">{{ session('success') }}</div>@endif

    <form method="GET" style="display:grid;grid-template-columns:minmax(200px,1fr) 180px 180px auto;gap:10px;margin-bottom:16px;">
        <input name="q" value="{{ request('q') }}" placeholder="Search title or slug">
        <select name="type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ ucfirst($type) }}</option>@endforeach</select>
        <select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <button class="btn btn-secondary">Filter</button>
    </form>

    <div style="overflow:auto;border:1px solid rgba(128,128,128,.22);border-radius:16px;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
            <thead><tr style="text-align:left;background:rgba(128,128,128,.07);">
                <th style="padding:13px;">Resource</th><th style="padding:13px;">Type</th><th style="padding:13px;">Access</th><th style="padding:13px;">Current version</th><th style="padding:13px;">Status</th><th style="padding:13px;text-align:right;">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($resources as $resource)
                <tr style="border-top:1px solid rgba(128,128,128,.14);">
                    <td style="padding:13px;"><strong>{{ $resource->title }}</strong><div style="font-size:12px;opacity:.55;">/{{ $resource->slug }}</div></td>
                    <td style="padding:13px;">{{ ucfirst($resource->resource_type) }}</td>
                    <td style="padding:13px;">{{ str_replace('_',' + ',ucfirst($resource->access_level)) }}</td>
                    <td style="padding:13px;">{{ optional($resource->currentVersion)->version_label ?? '—' }}</td>
                    <td style="padding:13px;">{{ ucfirst($resource->status) }}</td>
                    <td style="padding:13px;text-align:right;white-space:nowrap;">
                        <a class="btn btn-secondary" href="{{ route('crm.phase4.guidebooks.show',$resource) }}" title="View">👁</a>
                        <a class="btn btn-secondary" href="{{ route('crm.phase4.guidebooks.edit',$resource) }}" title="Edit">✎</a>
                        <form method="POST" action="{{ route('crm.phase4.guidebooks.archive',$resource) }}" style="display:inline" onsubmit="return confirm('Archive this resource? Version files will be retained.');">@csrf @method('DELETE')<button class="btn btn-secondary" title="Archive">🗑</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:28px;text-align:center;opacity:.65;">No resources yet. Create your first guidebook or e-book.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $resources->links() }}</div>
</div>
@endsection
