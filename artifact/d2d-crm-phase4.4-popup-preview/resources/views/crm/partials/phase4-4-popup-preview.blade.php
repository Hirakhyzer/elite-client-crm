{{-- Phase 4.4: add Preview access to existing Popup Manager without changing its CRUD/controller. --}}
@if(auth()->check())
<style>
.d2d-p44-preview-link{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:34px;padding:0 11px;border-radius:9px;border:1px solid rgba(244,175,0,.35);background:rgba(244,175,0,.08);color:#7c5900;text-decoration:none;font-size:12px;font-weight:800;white-space:nowrap}.d2d-p44-preview-link:hover{background:#f4af00;color:#111;border-color:#f4af00}.d2d-p44-preview-lab{margin-left:8px}
</style>
<script>
(function(){
    const path=window.location.pathname.toLowerCase();
    if(!path.includes('popup')) return;

    const base='{{ rtrim(url('/phase4/popup-preview'), '/') }}';
    const makeLink=(id)=>{
        const a=document.createElement('a');
        a.href=base+'/'+id;
        a.target='_blank';
        a.rel='noopener';
        a.className='d2d-p44-preview-link';
        a.innerHTML='<span aria-hidden="true">◉</span><span>Preview</span>';
        return a;
    };

    // Add Preview beside identifiable Edit links.
    document.querySelectorAll('a[href]').forEach(function(edit){
        if(edit.dataset.p44Done==='1') return;
        const href=edit.getAttribute('href')||'';
        if(!/popup/i.test(href) || !/edit/i.test(href)) return;
        const match=href.match(/\/([0-9]+)(?:\/edit|\?)/) || href.match(/\/([0-9]+)\/edit$/);
        if(!match) return;
        const id=match[1];
        const link=makeLink(id);
        edit.insertAdjacentElement('beforebegin',link);
        edit.dataset.p44Done='1';
    });

    // Add a Preview Lab button near the page heading once.
    if(!document.querySelector('[data-p44-lab]')){
        const heading=[...document.querySelectorAll('h1,h2')].find(el=>/popup/i.test(el.textContent||''));
        if(heading){
            const lab=document.createElement('a');
            lab.href=base;
            lab.target='_blank';
            lab.rel='noopener';
            lab.dataset.p44Lab='1';
            lab.className='d2d-p44-preview-link d2d-p44-preview-lab';
            lab.textContent='Preview Lab';
            heading.insertAdjacentElement('afterend',lab);
        }
    }
})();
</script>
@endif
