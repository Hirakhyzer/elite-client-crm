@extends('__D2D_LAYOUT__')

@section('content')
<div style="max-width:1500px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#f4af00;font-weight:800;">Popup Preview</div>
            <h1 style="margin:4px 0 6px;">{{ $popup['name'] }}</h1>
            <p style="margin:0;opacity:.64;">CRM sandbox only — this does not publish or trigger the popup publicly.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('crm.phase4_4.popup-preview.index') }}" class="btn btn-secondary">All Popups</a>
            <button type="button" class="btn btn-secondary" id="previewDesktop">Desktop</button>
            <button type="button" class="btn btn-secondary" id="previewMobile">Mobile</button>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start;" class="p44-layout">
        <section style="padding:18px;border:1px solid #e7e1d6;border-radius:18px;background:#fff;box-shadow:0 8px 30px rgba(31,25,15,.045);overflow:auto;">
            <div id="previewFrame" class="p44-frame is-desktop">
                <div class="p44-site-shell">
                    <div class="p44-site-top"><span class="p44-logo-dot"></span><span>Dare To Dream</span><span class="p44-site-nav">Scholarships &nbsp; Universities &nbsp; Resources</span></div>
                    <div class="p44-site-page"><div class="p44-site-line wide"></div><div class="p44-site-line"></div><div class="p44-site-line short"></div></div>
                    <div class="p44-overlay"></div>
                    <div class="p44-modal" role="dialog" aria-modal="true" aria-label="Popup preview">
                        <button class="p44-close" type="button" aria-label="Close preview">×</button>
                        @if($popup['image'])
                            <div class="p44-image-wrap"><img src="{{ $popup['image'] }}" alt="" class="p44-image"></div>
                        @endif
                        <div class="p44-copy">
                            <div class="p44-eyebrow">DARE TO DREAM</div>
                            <h2>{{ $popup['headline'] }}</h2>
                            @if($popup['subheadline'])<div class="p44-sub">{{ $popup['subheadline'] }}</div>@endif
                            <div class="p44-body">{!! nl2br(e(strip_tags($popup['body']))) !!}</div>
                            @if($popup['cta_label'])<a href="javascript:void(0)" class="p44-cta">{{ $popup['cta_label'] }}</a>@endif
                            <div class="p44-fine">You can close this message anytime.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <aside style="padding:18px;border:1px solid #e7e1d6;border-radius:18px;background:#fff;box-shadow:0 8px 30px rgba(31,25,15,.045);">
            <h3 style="margin-top:0;">Preview settings</h3>
            <div class="p44-detail"><span>Status</span><strong>{{ $popup['status'] }}</strong></div>
            <div class="p44-detail"><span>Trigger</span><strong>{{ $popup['trigger'] }}</strong></div>
            @if($popup['delay'] !== null)<div class="p44-detail"><span>Delay</span><strong>{{ $popup['delay'] }} sec</strong></div>@endif
            @if($popup['scroll'] !== null)<div class="p44-detail"><span>Scroll</span><strong>{{ $popup['scroll'] }}%</strong></div>@endif
            @if($popup['frequency'])<div class="p44-detail"><span>Frequency</span><strong>{{ $popup['frequency'] }}</strong></div>@endif
            <div class="p44-detail"><span>Device</span><strong>{{ $popup['device'] }}</strong></div>
            <div class="p44-detail"><span>Style</span><strong>{{ $popup['style'] }}</strong></div>
            <div style="margin-top:16px;padding:12px;border-radius:12px;background:#f8f5ed;font-size:12px;line-height:1.6;color:#555;">This is the CRM visual sandbox. In Phase 5, the live public popup component should use the same styling contract so Preview and Live remain matched.</div>
        </aside>
    </div>
</div>

<style>
.p44-frame{margin:0 auto;transition:.25s ease}.p44-frame.is-desktop{width:100%;min-width:760px}.p44-frame.is-mobile{width:390px;min-width:390px}.p44-site-shell{height:680px;position:relative;overflow:hidden;border-radius:15px;border:1px solid #dfdbd2;background:#f5f2ea}.p44-site-top{height:64px;padding:0 22px;display:flex;align-items:center;gap:10px;background:#111;color:#fff;font-size:13px;font-weight:800}.p44-logo-dot{width:25px;height:25px;border-radius:8px;background:#f4af00}.p44-site-nav{margin-left:auto;color:#c6c6c8;font-weight:600;font-size:12px}.p44-site-page{padding:50px 8%;}.p44-site-line{height:16px;width:70%;border-radius:999px;background:#ded9ce;margin-bottom:16px}.p44-site-line.wide{height:34px;width:54%;background:#cbc4b5}.p44-site-line.short{width:42%}.p44-overlay{position:absolute;inset:0;background:rgba(0,0,0,.62);backdrop-filter:blur(3px)}.p44-modal{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(720px,84%);max-height:84%;display:grid;grid-template-columns:42% 58%;background:#fff;border-radius:22px;box-shadow:0 28px 90px rgba(0,0,0,.34);overflow:hidden}.p44-modal:not(:has(.p44-image-wrap)){display:block;width:min(560px,84%)}.p44-image-wrap{min-height:430px;background:#171717}.p44-image{width:100%;height:100%;object-fit:cover}.p44-copy{padding:48px 42px 38px;position:relative}.p44-eyebrow{font-size:10px;letter-spacing:.19em;font-weight:900;color:#b37b00;margin-bottom:13px}.p44-copy h2{font-size:34px;line-height:1.05;margin:0 0 12px;color:#151515}.p44-sub{font-size:16px;font-weight:700;margin-bottom:10px;color:#3b3b3d}.p44-body{font-size:14px;line-height:1.65;color:#5d5d61;margin-bottom:24px}.p44-cta{display:inline-flex;min-height:42px;padding:0 18px;align-items:center;justify-content:center;border-radius:10px;background:#f4af00;color:#111;text-decoration:none;font-size:13px;font-weight:900}.p44-fine{margin-top:14px;font-size:10px;color:#929296}.p44-close{position:absolute;right:14px;top:12px;z-index:4;width:33px;height:33px;border:0;border-radius:50%;background:rgba(20,20,20,.08);font-size:24px;line-height:1;cursor:pointer}.p44-detail{display:flex;justify-content:space-between;gap:16px;padding:11px 0;border-bottom:1px solid #eee9df;font-size:13px}.p44-detail span{color:#777}.p44-detail strong{text-align:right}.p44-frame.is-mobile .p44-site-shell{height:720px}.p44-frame.is-mobile .p44-site-nav{display:none}.p44-frame.is-mobile .p44-site-top{padding:0 15px}.p44-frame.is-mobile .p44-modal{width:322px;max-height:610px;display:block;border-radius:20px;overflow:auto}.p44-frame.is-mobile .p44-image-wrap{height:185px;min-height:185px}.p44-frame.is-mobile .p44-copy{padding:30px 26px 26px}.p44-frame.is-mobile .p44-copy h2{font-size:26px}.p44-frame.is-mobile .p44-body{font-size:13px}.p44-frame.is-mobile .p44-cta{width:100%;box-sizing:border-box}.p44-frame.is-mobile .p44-close{background:rgba(255,255,255,.92)}
@media(max-width:1020px){.p44-layout{grid-template-columns:1fr!important}.p44-frame.is-desktop{min-width:700px}}
</style>
<script>
(function(){
 const frame=document.getElementById('previewFrame');
 const desktop=document.getElementById('previewDesktop');
 const mobile=document.getElementById('previewMobile');
 if(!frame)return;
 desktop?.addEventListener('click',()=>{frame.classList.add('is-desktop');frame.classList.remove('is-mobile');});
 mobile?.addEventListener('click',()=>{frame.classList.add('is-mobile');frame.classList.remove('is-desktop');});
 document.querySelector('.p44-close')?.addEventListener('click',()=>{document.querySelector('.p44-modal').style.opacity='.25';setTimeout(()=>document.querySelector('.p44-modal').style.opacity='1',450);});
})();
</script>
@endsection
