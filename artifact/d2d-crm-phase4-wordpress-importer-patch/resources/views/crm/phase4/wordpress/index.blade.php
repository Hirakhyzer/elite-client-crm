@extends('crm.layouts.app')

@section('content')
<div class="crm-page" style="max-width:1400px;margin:0 auto;">
    <div style="display:flex;gap:16px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;margin-bottom:22px;">
        <div>
            <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#f4af00;font-weight:800;">Phase 4</div>
            <h1 style="margin:4px 0 6px;">WordPress Post Migration</h1>
            <p style="margin:0;opacity:.72;max-width:760px;">Posts only. Pages, WordPress guidebooks, users, comments, revisions and plugin data are ignored. Dry Run never writes content.</p>
        </div>
        <a href="{{ route('crm.phase4.guidebooks.index') }}" class="btn btn-secondary">Guidebooks &amp; Resources</a>
    </div>

    @if(session('success'))
        <div style="padding:14px 16px;border:1px solid rgba(244,175,0,.35);border-radius:14px;margin-bottom:18px;background:rgba(244,175,0,.08);">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div style="padding:14px 16px;border:1px solid #b91c1c;border-radius:14px;margin-bottom:18px;">
            <strong>Please fix:</strong> {{ $errors->first() }}
        </div>
    @endif

    @php($importResults = session('phase4_import_results'))
    @if($importResults)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
            @foreach(['imported'=>'Imported','skipped'=>'Skipped','failed'=>'Failed','seo_warnings'=>'SEO warnings','media_warnings'=>'Media warnings'] as $key=>$label)
                <div style="padding:16px;border:1px solid rgba(128,128,128,.25);border-radius:14px;">
                    <div style="font-size:28px;font-weight:800;">{{ $importResults[$key] ?? 0 }}</div>
                    <div style="opacity:.65;">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:18px;align-items:start;" class="phase4-grid">
        <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:18px;">
            <h2 style="margin-top:0;">1. Migration source</h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div style="padding:14px;border-radius:12px;background:rgba(128,128,128,.07);">
                    <strong>SQL export</strong><br>
                    <span style="opacity:.7;">{{ $sqlReady ? '✓ Ready' : 'Not uploaded' }}</span>
                    @if($sqlReady)<div style="font-size:12px;opacity:.55;">{{ number_format($sqlSize/1048576,2) }} MB</div>@endif
                </div>
                <div style="padding:14px;border-radius:12px;background:rgba(128,128,128,.07);">
                    <strong>uploads.zip</strong><br>
                    <span style="opacity:.7;">{{ $zipReady ? '✓ Ready' : 'Not uploaded' }}</span>
                    @if($zipReady)<div style="font-size:12px;opacity:.55;">{{ number_format($zipSize/1048576,2) }} MB</div>@endif
                </div>
            </div>

            <form method="POST" action="{{ route('crm.phase4.wordpress.upload') }}" enctype="multipart/form-data">
                @csrf
                <label style="display:block;font-weight:700;margin-bottom:6px;">WordPress database (.sql)</label>
                <input type="file" name="sql_file" accept=".sql" style="width:100%;margin-bottom:14px;">
                <label style="display:block;font-weight:700;margin-bottom:6px;">wp-content/uploads ZIP</label>
                <input type="file" name="uploads_zip" accept=".zip" style="width:100%;margin-bottom:16px;">
                <button class="btn btn-primary" type="submit">Save source files</button>
            </form>

            <p style="font-size:12px;opacity:.62;margin:14px 0 0;">Large files can also be uploaded through cPanel to <code>storage/app/phase4-import/</code> and named <code>legacy.sql</code> and <code>uploads.zip</code>.</p>
        </section>

        <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:18px;">
            <h2 style="margin-top:0;">Locked import rules</h2>
            <div style="line-height:1.9;">
                <div>✓ <strong>post_type=post only</strong></div>
                <div>✓ Original <code>/%postname%/</code> slug</div>
                <div>✓ Author defaults to <strong>Team D2D</strong></div>
                <div>✓ Yoast title/meta/canonical/OG where available</div>
                <div>✓ Featured + in-post referenced media only</div>
                <div>✓ WordPress ID + slug + exact-title duplicate protection</div>
                <div>✕ No pages / guidebooks / users / comments</div>
                <div>✕ No revisions / forms / plugin tables</div>
            </div>
        </section>
    </div>

    <section style="padding:20px;border:1px solid rgba(128,128,128,.22);border-radius:18px;margin-top:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <h2 style="margin:0 0 4px;">2. Dry Run</h2>
                <div style="opacity:.65;">Reads only the approved WordPress post data. Nothing is inserted or changed.</div>
            </div>
            <form method="POST" action="{{ route('crm.phase4.wordpress.dry-run') }}">@csrf
                <button type="submit" class="btn btn-primary" {{ $sqlReady ? '' : 'disabled' }}>Run Dry Run</button>
            </form>
        </div>
    </section>

    @if($report)
        @php($c = $report['counts'] ?? [])
        <section style="margin-top:18px;">
            <h2>Dry Run report</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">
                @foreach([
                    'posts_found'=>'Posts found','ready'=>'Ready','already_imported'=>'Already imported','duplicate_slug'=>'Slug conflicts','duplicate_title'=>'Title conflicts','duplicate_source'=>'Source duplicates','media_missing'=>'Missing media'
                ] as $key=>$label)
                    <div style="padding:14px;border:1px solid rgba(128,128,128,.22);border-radius:12px;">
                        <div style="font-size:24px;font-weight:800;">{{ $c[$key] ?? 0 }}</div><div style="opacity:.64;font-size:13px;">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            @if(!empty($report['target']['warnings']))
                <div style="padding:14px;border:1px solid #d97706;border-radius:12px;margin-bottom:14px;">
                    <strong>Target checks</strong>
                    @foreach($report['target']['warnings'] as $warning)<div>• {{ $warning }}</div>@endforeach
                </div>
            @endif

            <div style="overflow:auto;border:1px solid rgba(128,128,128,.22);border-radius:14px;">
                <table style="width:100%;border-collapse:collapse;min-width:900px;">
                    <thead><tr style="text-align:left;background:rgba(128,128,128,.07);">
                        <th style="padding:12px;">ID</th><th style="padding:12px;">Post</th><th style="padding:12px;">Slug</th><th style="padding:12px;">WP status</th><th style="padding:12px;">Result</th><th style="padding:12px;">Media</th>
                    </tr></thead>
                    <tbody>
                    @foreach(array_slice($report['rows'] ?? [],0,500) as $row)
                        <tr style="border-top:1px solid rgba(128,128,128,.14);">
                            <td style="padding:12px;">{{ $row['source_id'] }}</td>
                            <td style="padding:12px;"><strong>{{ $row['title'] }}</strong><div style="opacity:.55;font-size:12px;">{{ $row['reason'] }}</div></td>
                            <td style="padding:12px;"><code>{{ $row['slug'] }}</code></td>
                            <td style="padding:12px;">{{ $row['wp_status'] }}</td>
                            <td style="padding:12px;"><strong>{{ str_replace('_',' ',strtoupper($row['result'])) }}</strong></td>
                            <td style="padding:12px;">{{ $row['media_found'] }}/{{ $row['media_total'] }} found @if($row['media_missing']) · {{ $row['media_missing'] }} missing @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($report['rows'] ?? []) > 500)<div style="opacity:.6;font-size:12px;margin-top:8px;">Showing the first 500 rows in the browser; all rows are included in the Dry Run counts.</div>@endif

            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-top:18px;">
                <form method="POST" action="{{ route('crm.phase4.wordpress.clear') }}" onsubmit="return confirm('Remove staged SQL/ZIP files? Imported CRM posts will not be touched.');">@csrf @method('DELETE')
                    <button type="submit" class="btn btn-secondary">Clear staged source</button>
                </form>

                <form method="POST" action="{{ route('crm.phase4.wordpress.import') }}" onsubmit="return confirm('Import only the rows marked READY? Existing/duplicate items will be skipped.');" style="text-align:right;">@csrf
                    <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="confirm_import" value="1" required> I reviewed the Dry Run and want to import READY posts only.</label>
                    <button type="submit" class="btn btn-primary" {{ ($report['target']['content_posts_ready'] ?? false) ? '' : 'disabled' }}>Import Safe Posts</button>
                </form>
            </div>
        </section>
    @endif
</div>

<style>
@media (max-width:900px){.phase4-grid{grid-template-columns:1fr!important}}
</style>
@endsection
