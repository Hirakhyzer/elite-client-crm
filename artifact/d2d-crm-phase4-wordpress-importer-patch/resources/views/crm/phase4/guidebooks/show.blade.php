@extends('crm.layouts.app')

@section('content')
<div style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#f4af00;font-weight:800;">{{ ucfirst($resource->resource_type) }}</div>
            <h1 style="margin:4px 0;">{{ $resource->title }}</h1>
            <div style="opacity:.62;">Author: {{ $resource->author_name }} · Access: {{ $resource->access_level === 'pro_ambassador' ? 'Pro + Ambassador' : ucfirst($resource->access_level) }} · {{ ucfirst($resource->status) }}</div>
        </div>
        <div style="display:flex;gap:10px;"><a class="btn btn-secondary" href="{{ route('crm.phase4.guidebooks.index') }}">Back</a><a class="btn btn-primary" href="{{ route('crm.phase4.guidebooks.edit',$resource) }}">Edit</a></div>
    </div>

    @if(session('success'))<div style="padding:14px;border:1px solid rgba(244,175,0,.35);border-radius:14px;margin-bottom:16px;background:rgba(244,175,0,.08);">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="padding:14px;border:1px solid #b91c1c;border-radius:14px;margin-bottom:16px;">{{ $errors->first() }}</div>@endif

    <div style="display:grid;grid-template-columns:minmax(0,.75fr) minmax(0,1.25fr);gap:18px;" class="resource-show-grid">
        <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:16px;">
            @if($resource->cover_image)<img src="{{ $resource->cover_image }}" alt="{{ $resource->title }}" style="width:100%;max-height:390px;object-fit:cover;border-radius:12px;margin-bottom:16px;">@endif
            <h2>Resource information</h2>
            <p>{{ $resource->short_description }}</p>
            @if($resource->description)<div style="line-height:1.7;">{!! nl2br(e($resource->description)) !!}</div>@endif
            <hr style="border:0;border-top:1px solid rgba(128,128,128,.18);margin:20px 0;">
            <div><strong>Slug:</strong> /{{ $resource->slug }}</div>
            <div><strong>SEO title:</strong> {{ $resource->seo_title ?: '—' }}</div>
            <div><strong>Canonical:</strong> {{ $resource->canonical_url ?: '—' }}</div>
        </section>

        <div>
            <section style="padding:20px;border:1px solid rgba(244,175,0,.28);border-radius:16px;margin-bottom:18px;">
                <h2 style="margin-top:0;">Upload new version</h2>
                <p style="opacity:.65;">The existing versions stay available in CRM. Setting this as Current only changes which version is considered the latest.</p>
                <form method="POST" enctype="multipart/form-data" action="{{ route('crm.phase4.guidebooks.versions.store',$resource) }}">@csrf
                    <div style="display:grid;grid-template-columns:160px 1fr;gap:12px;" class="version-form-grid">
                        <div><label>Version</label><input name="version_label" placeholder="v2.0" required style="width:100%;margin-top:6px;"></div>
                        <div><label>File</label><input type="file" name="resource_file" required style="width:100%;margin-top:6px;"></div>
                        <div><label>Release date</label><input type="date" name="released_at" value="{{ now()->toDateString() }}" style="width:100%;margin-top:6px;"></div>
                        <div><label>Release notes</label><textarea name="release_notes" style="width:100%;min-height:80px;margin-top:6px;"></textarea></div>
                    </div>
                    <label style="display:block;margin:12px 0;"><input type="checkbox" name="is_current" value="1" checked> Set this upload as the current version</label>
                    <button class="btn btn-primary">Upload Version</button>
                </form>
            </section>

            <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:16px;">
                <h2 style="margin-top:0;">Version history</h2>
                <div style="display:grid;gap:10px;">
                    @forelse($resource->versions as $version)
                        <div style="padding:14px;border:1px solid rgba(128,128,128,.18);border-radius:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                            <div>
                                <div><strong>{{ $version->version_label }}</strong> @if($version->is_current)<span style="color:#f4af00;font-weight:800;">CURRENT</span>@endif</div>
                                <div style="font-size:12px;opacity:.6;">{{ $version->original_filename }} · {{ $version->released_at?->format('M j, Y') ?: 'No date' }} @if($version->file_size) · {{ number_format($version->file_size/1048576,2) }} MB @endif</div>
                                @if($version->release_notes)<div style="margin-top:6px;opacity:.75;">{{ $version->release_notes }}</div>@endif
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a class="btn btn-secondary" href="{{ route('crm.phase4.guidebooks.versions.download',[$resource,$version]) }}">Download</a>
                                @unless($version->is_current)<form method="POST" action="{{ route('crm.phase4.guidebooks.versions.current',[$resource,$version]) }}">@csrf<button class="btn btn-secondary">Make Current</button></form>@endunless
                            </div>
                        </div>
                    @empty
                        <div style="opacity:.65;">No versions uploaded yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
<style>@media(max-width:900px){.resource-show-grid{grid-template-columns:1fr!important}}@media(max-width:650px){.version-form-grid{grid-template-columns:1fr!important}}</style>
@endsection
