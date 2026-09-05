@extends('crm.layouts.app')

@section('content')
<div style="max-width:1100px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px;">
        <div><div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#f4af00;font-weight:800;">Guidebooks &amp; Resources</div><h1 style="margin:4px 0;">{{ $editing ? 'Edit Resource' : 'New Resource' }}</h1></div>
        <a class="btn btn-secondary" href="{{ $editing ? route('crm.phase4.guidebooks.show',$resource) : route('crm.phase4.guidebooks.index') }}">Back</a>
    </div>

    @if($errors->any())<div style="padding:14px;border:1px solid #b91c1c;border-radius:12px;margin-bottom:14px;">{{ $errors->first() }}</div>@endif

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('crm.phase4.guidebooks.update',$resource) : route('crm.phase4.guidebooks.store') }}">
        @csrf @if($editing) @method('PUT') @endif

        <div style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(280px,.7fr);gap:18px;" class="resource-form-grid">
            <div>
                <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:16px;margin-bottom:16px;">
                    <h2 style="margin-top:0;">Content</h2>
                    <label>Title</label><input style="width:100%;margin:6px 0 14px;" name="title" value="{{ old('title',$resource->title) }}" required>
                    <label>Slug <span style="opacity:.55;">(leave empty to create automatically)</span></label><input style="width:100%;margin:6px 0 14px;" name="slug" value="{{ old('slug',$resource->slug) }}">
                    <label>Short description</label><textarea style="width:100%;margin:6px 0 14px;min-height:90px;" name="short_description">{{ old('short_description',$resource->short_description) }}</textarea>
                    <label>Full description</label><textarea style="width:100%;margin:6px 0 14px;min-height:260px;" name="description">{{ old('description',$resource->description) }}</textarea>
                    <label>Cover image</label><input type="file" name="cover_image" accept="image/*" style="width:100%;margin:6px 0 0;">
                    @if($resource->cover_image)<img src="{{ $resource->cover_image }}" alt="" style="max-width:180px;margin-top:12px;border-radius:10px;">@endif
                </section>

                <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:16px;">
                    <h2 style="margin-top:0;">SEO</h2>
                    <label>SEO title</label><input style="width:100%;margin:6px 0 14px;" name="seo_title" value="{{ old('seo_title',$resource->seo_title) }}">
                    <label>Meta description</label><textarea style="width:100%;margin:6px 0 14px;min-height:110px;" name="meta_description">{{ old('meta_description',$resource->meta_description) }}</textarea>
                    <label>Canonical URL</label><input type="url" style="width:100%;margin:6px 0 14px;" name="canonical_url" value="{{ old('canonical_url',$resource->canonical_url) }}">
                    <label>OG image</label><input type="file" name="og_image" accept="image/*" style="width:100%;margin-top:6px;">
                </section>
            </div>

            <div>
                <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:16px;margin-bottom:16px;">
                    <h2 style="margin-top:0;">Publishing</h2>
                    <label>Resource type</label><select name="resource_type" style="width:100%;margin:6px 0 14px;">@foreach($types as $type)<option value="{{ $type }}" @selected(old('resource_type',$resource->resource_type)===$type)>{{ ucfirst($type) }}</option>@endforeach</select>
                    <label>Author</label><input name="author_name" style="width:100%;margin:6px 0 14px;" value="{{ old('author_name',$resource->author_name ?: 'Team D2D') }}" required>
                    <label>Access</label><select name="access_level" style="width:100%;margin:6px 0 14px;">@foreach($accessLevels as $level)<option value="{{ $level }}" @selected(old('access_level',$resource->access_level)===$level)>{{ $level==='pro_ambassador' ? 'Pro + Ambassador' : ucfirst($level) }}</option>@endforeach</select>
                    <label>Status</label><select name="status" style="width:100%;margin:6px 0 14px;">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status',$resource->status)===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
                    <label>Publish date</label><input type="datetime-local" name="published_at" style="width:100%;margin:6px 0 14px;" value="{{ old('published_at',$resource->published_at?->format('Y-m-d\TH:i')) }}">
                    <label><input type="checkbox" name="featured" value="1" @checked(old('featured',$resource->featured))> Featured resource</label>
                </section>

                @if(!$editing)
                <section style="padding:20px;border:1px solid rgba(244,175,0,.3);border-radius:16px;margin-bottom:16px;">
                    <h2 style="margin-top:0;">Optional first version</h2>
                    <p style="font-size:13px;opacity:.65;">You can skip this and upload v1.0 after creating the resource.</p>
                    <label>Version</label><input name="version_label" placeholder="v1.0" style="width:100%;margin:6px 0 12px;">
                    <label>File</label><input type="file" name="initial_file" style="width:100%;margin:6px 0 12px;">
                    <label>Release notes</label><textarea name="release_notes" style="width:100%;margin:6px 0 12px;min-height:90px;"></textarea>
                    <label>Release date</label><input type="date" name="released_at" value="{{ now()->toDateString() }}" style="width:100%;margin-top:6px;">
                </section>
                @endif

                <button class="btn btn-primary" style="width:100%;padding:13px;" type="submit">{{ $editing ? 'Save Changes' : 'Create Resource' }}</button>
            </div>
        </div>
    </form>
</div>
<style>@media(max-width:900px){.resource-form-grid{grid-template-columns:1fr!important}}</style>
@endsection
