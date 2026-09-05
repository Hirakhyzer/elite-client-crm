<?php

declare(strict_types=1);

/**
 * D2D CRM Phase 4.2 UI polish
 * - Keeps WordPress migration post-only behavior unchanged.
 * - Keeps Guidebooks & Resources as manual CRM CRUD/version uploads.
 * - Removes/hides the bulky sidebar account block.
 * - Replaces the old Phase 4 sidebar links with premium styled links.
 * - Replaces the top-right CRM pill with an admin dropdown.
 * - Adds simple My Profile and CRM Settings pages.
 * - No migrations. No Portal changes.
 */

$root = __DIR__;
if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Extract this patch into the CRM Laravel root first.\n");
    exit(1);
}

function p42ViewPath(string $root, string $view): string
{
    return $root.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
}

function p42Backup(string $path): void
{
    $backup = $path.'.phase4.2-backup';
    if (is_file($path) && ! is_file($backup)) {
        copy($path, $backup);
    }
}

function p42Write(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $content);
}

function p42AppendOnce(string $path, string $marker, string $content): bool
{
    if (! is_file($path)) {
        return false;
    }
    $current = (string) file_get_contents($path);
    if (str_contains($current, $marker)) {
        return true;
    }
    p42Backup($path);
    file_put_contents($path, rtrim($current)."\n\n".$content."\n");
    return true;
}

// Detect the exact working CRM layout from the dashboard.
$layout = null;
foreach ([
    $root.'/resources/views/crm/dashboard.blade.php',
    $root.'/resources/views/crm/index.blade.php',
    $root.'/resources/views/dashboard.blade.php',
] as $dashboard) {
    if (! is_file($dashboard)) {
        continue;
    }
    $contents = (string) file_get_contents($dashboard);
    if (preg_match('/@extends\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $contents, $m)) {
        $candidate = trim($m[1]);
        if ($candidate !== '' && is_file(p42ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    foreach (['crm.layout','crm.layouts.master','crm.layouts.dashboard','layouts.crm','layouts.app'] as $candidate) {
        if (is_file(p42ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    fwrite(STDERR, "ERROR: Could not detect the working CRM Blade layout.\n");
    exit(2);
}

$layoutPath = p42ViewPath($root, $layout);
p42Backup($layoutPath);

// Premium Phase 4 sidebar navigation.
$phase4Nav = <<<'BLADE'
<div class="d2d-p42-nav">
    <div class="d2d-p42-nav-label">RESOURCES</div>
    <a href="{{ route('crm.phase4.guidebooks.index') }}" class="d2d-p42-nav-link {{ request()->routeIs('crm.phase4.guidebooks.*') ? 'is-active' : '' }}">
        <span class="d2d-p42-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5z"/></svg>
        </span>
        <span>Guidebooks &amp; Resources</span>
    </a>

    <div class="d2d-p42-nav-label d2d-p42-nav-label-space">MIGRATION</div>
    <a href="{{ route('crm.phase4.wordpress.index') }}" class="d2d-p42-nav-link {{ request()->routeIs('crm.phase4.wordpress.*') ? 'is-active' : '' }}">
        <span class="d2d-p42-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h10"/><path d="m11 4 3 3-3 3"/><path d="M20 17H10"/><path d="m13 14-3 3 3 3"/></svg>
        </span>
        <span>WordPress Migration</span>
    </a>
</div>
BLADE;
p42Write($root.'/resources/views/crm/partials/phase4-nav.blade.php', $phase4Nav);

// Top-right admin menu + scoped UI polish.
$adminMenu = <<<'BLADE'
@php($d2dAdmin = auth()->user())
@if($d2dAdmin)
<div id="d2dAdminMenu" class="d2d-admin-menu d2d-admin-menu--fallback" data-email="{{ strtolower((string) ($d2dAdmin->email ?? '')) }}">
    <button type="button" class="d2d-admin-trigger" id="d2dAdminTrigger" aria-haspopup="true" aria-expanded="false">
        <span class="d2d-admin-avatar">{{ strtoupper(substr((string) ($d2dAdmin->name ?? 'D'), 0, 1)) }}</span>
        <span class="d2d-admin-name">{{ $d2dAdmin->name ?? 'D2D Admin' }}</span>
        <svg class="d2d-admin-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7.5 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div class="d2d-admin-panel" id="d2dAdminPanel" role="menu">
        <a href="{{ route('crm.account.profile') }}" class="d2d-admin-item" role="menuitem">
            <span class="d2d-admin-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg></span>
            <span>My Profile</span>
        </a>
        <a href="{{ route('crm.account.settings') }}" class="d2d-admin-item" role="menuitem">
            <span class="d2d-admin-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.1A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.1A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.1A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.19.35.5.68 1 .9.25.11.53.16.8.16h.1v4h-.1c-.27 0-.55.05-.8.16-.5.22-.81.55-1 .9Z"/></svg></span>
            <span>CRM Settings</span>
        </a>
        <div class="d2d-admin-divider"></div>
        <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : url('/logout') }}">
            @csrf
            <button type="submit" class="d2d-admin-item d2d-admin-signout" role="menuitem">
                <span class="d2d-admin-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 5H5v14h5"/><path d="M14 8l4 4-4 4"/><path d="M8 12h10"/></svg></span>
                <span>Sign out</span>
            </button>
        </form>
    </div>
</div>

<style>
.d2d-p42-nav{padding:10px 10px 18px}.d2d-p42-nav-label{padding:0 10px 8px;font-size:10px;letter-spacing:.16em;color:#777b84;font-weight:800}.d2d-p42-nav-label-space{margin-top:15px}.d2d-p42-nav-link{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:11px;color:#e9e9ec;text-decoration:none;font-size:13px;font-weight:650;transition:.18s ease}.d2d-p42-nav-link:hover{background:rgba(255,255,255,.06);color:#fff}.d2d-p42-nav-link.is-active{background:linear-gradient(90deg,rgba(244,175,0,.18),rgba(244,175,0,.06));box-shadow:inset 3px 0 #f4af00;color:#fff}.d2d-p42-nav-icon{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#1e1f23;color:#aeb0b7;flex:0 0 28px}.d2d-p42-nav-link.is-active .d2d-p42-nav-icon{background:#f4af00;color:#111}.d2d-p42-nav-icon svg{width:16px;height:16px}
.d2d-admin-menu{z-index:10050}.d2d-admin-menu--fallback{position:fixed;top:13px;right:18px}.d2d-admin-menu--inline{position:relative;display:inline-flex;align-items:center}.d2d-admin-trigger{height:38px;display:flex;align-items:center;gap:9px;border:1px solid #e6e1d7;background:#fff;color:#191919;border-radius:12px;padding:4px 10px 4px 5px;box-shadow:0 5px 18px rgba(0,0,0,.06);cursor:pointer;font:inherit}.d2d-admin-trigger:hover{border-color:#d7a500;box-shadow:0 7px 24px rgba(0,0,0,.09)}.d2d-admin-avatar{width:29px;height:29px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:#f4af00;color:#111;font-weight:900;font-size:13px}.d2d-admin-name{max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:800}.d2d-admin-chevron{width:15px;height:15px;color:#777;transition:transform .18s}.d2d-admin-menu.is-open .d2d-admin-chevron{transform:rotate(180deg)}.d2d-admin-panel{position:absolute;right:0;top:calc(100% + 9px);width:210px;padding:8px;background:#fff;border:1px solid #e8e2d7;border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.16);display:none}.d2d-admin-menu.is-open .d2d-admin-panel{display:block}.d2d-admin-item{width:100%;display:flex;align-items:center;gap:10px;padding:10px 11px;border:0;border-radius:10px;background:transparent;color:#222;text-decoration:none;font:inherit;font-size:13px;font-weight:650;cursor:pointer;text-align:left}.d2d-admin-item:hover{background:#f7f4ed;color:#111}.d2d-admin-item-icon{width:25px;height:25px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;background:#f2f0eb;color:#5c5c60}.d2d-admin-item-icon svg{width:15px;height:15px}.d2d-admin-divider{height:1px;background:#ece7dd;margin:6px 4px}.d2d-admin-signout{color:#b42318}.d2d-admin-signout .d2d-admin-item-icon{background:#fff0ef;color:#b42318}
.phase4-grid>section,.resource-form-grid section,.resource-show-grid section{background:#fff!important;border-color:#e7e1d6!important;box-shadow:0 8px 30px rgba(31,25,15,.045)}.phase4-grid input,.phase4-grid select,.resource-form-grid input,.resource-form-grid select,.resource-form-grid textarea,.resource-show-grid input,.resource-show-grid textarea{border-radius:10px!important;border:1px solid #ddd7cc!important;background:#fff!important}.phase4-grid input:focus,.phase4-grid select:focus,.resource-form-grid input:focus,.resource-form-grid select:focus,.resource-form-grid textarea:focus,.resource-show-grid input:focus,.resource-show-grid textarea:focus{outline:2px solid rgba(244,175,0,.22)!important;border-color:#e0a000!important}
@media(max-width:720px){.d2d-admin-name{display:none}.d2d-admin-trigger{padding-right:6px}.d2d-admin-menu--fallback{right:10px}.d2d-admin-panel{width:205px}.d2d-p42-nav{padding-left:7px;padding-right:7px}}
</style>

<script>
(function(){
    const menu=document.getElementById('d2dAdminMenu');
    if(!menu)return;
    const trigger=document.getElementById('d2dAdminTrigger');
    const panel=document.getElementById('d2dAdminPanel');

    // Replace the existing small top-right CRM pill when present.
    const crmCandidates=[...document.querySelectorAll('button,a,span,div')].filter(function(el){
        if(el.children.length!==0 || el.textContent.trim()!=='CRM') return false;
        const r=el.getBoundingClientRect();
        return r.top<110 && r.right>(window.innerWidth*.65) && r.width<120;
    });
    if(crmCandidates.length){
        const leaf=crmCandidates[0];
        const host=leaf.closest('button,a') || leaf;
        if(host.parentElement){
            host.replaceWith(menu);
            menu.classList.remove('d2d-admin-menu--fallback');
            menu.classList.add('d2d-admin-menu--inline');
        }
    }

    // Remove the old sidebar name/email/sign-out block from view.
    const email=(menu.dataset.email||'').trim().toLowerCase();
    const sidebars=[...document.querySelectorAll('aside,[class*="sidebar"],[id*="sidebar"]')];
    sidebars.forEach(function(sidebar){
        if(email){
            const node=[...sidebar.querySelectorAll('*')].find(function(el){
                return el.children.length===0 && el.textContent.trim().toLowerCase().includes(email);
            });
            if(node){
                let block=node.parentElement;
                while(block && block.parentElement && block.parentElement!==sidebar){
                    const parent=block.parentElement;
                    const h=parent.getBoundingClientRect().height;
                    if(h>210) break;
                    block=parent;
                }
                if(block) block.style.display='none';
            }
        }
        [...sidebar.querySelectorAll('a,button')].forEach(function(el){
            const t=el.textContent.trim().toLowerCase();
            if(t==='sign out' || t==='logout' || t==='log out'){
                const holder=el.closest('form') || el;
                holder.style.display='none';
            }
        });
    });

    trigger.addEventListener('click',function(e){
        e.stopPropagation();
        const open=menu.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded',open?'true':'false');
    });
    panel.addEventListener('click',function(e){e.stopPropagation();});
    document.addEventListener('click',function(){menu.classList.remove('is-open');trigger.setAttribute('aria-expanded','false');});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){menu.classList.remove('is-open');trigger.setAttribute('aria-expanded','false');}});
})();
</script>
@endif
BLADE;
p42Write($root.'/resources/views/crm/partials/admin-menu.blade.php', $adminMenu);

// Account routes.
$uiRoutes = <<<'PHP'
<?php

use App\Http\Middleware\Phase4CrmAccess;
use Illuminate\Support\Facades\Route;

Route::middleware([Phase4CrmAccess::class])->group(function () {
    Route::get('/account/profile', function () {
        return view('crm.account.profile', ['user' => request()->user()]);
    })->name('crm.account.profile');

    Route::get('/account/settings', function () {
        return view('crm.account.settings', ['user' => request()->user()]);
    })->name('crm.account.settings');
});
PHP;
p42Write($root.'/routes/phase4-ui.php', $uiRoutes);

p42AppendOnce(
    $root.'/routes/web.php',
    'D2D_PHASE4_2_UI_ROUTES',
    "// D2D_PHASE4_2_UI_ROUTES\nif (file_exists(__DIR__.'/phase4-ui.php')) { require __DIR__.'/phase4-ui.php'; }\n// /D2D_PHASE4_2_UI_ROUTES"
);

$profile = <<<'BLADE'
@extends('__CRM_LAYOUT__')

@section('content')
<div style="max-width:900px;margin:0 auto;">
    <div style="margin-bottom:22px;">
        <div style="font-size:11px;letter-spacing:.16em;color:#a87900;font-weight:800;text-transform:uppercase;">Account</div>
        <h1 style="margin:5px 0 7px;">My Profile</h1>
        <p style="margin:0;color:#737373;">Your CRM identity comes from the shared D2D user account.</p>
    </div>
    <section style="background:#fff;border:1px solid #e7e1d6;border-radius:18px;padding:24px;box-shadow:0 8px 30px rgba(31,25,15,.05);">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;">
            <div style="width:58px;height:58px;border-radius:16px;background:#f4af00;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#111;">{{ strtoupper(substr((string)($user->name ?? 'D'),0,1)) }}</div>
            <div><div style="font-size:20px;font-weight:850;">{{ $user->name ?? 'D2D Admin' }}</div><div style="color:#777;margin-top:3px;">CRM administrator</div></div>
        </div>
        <div style="display:grid;grid-template-columns:160px 1fr;gap:12px 20px;align-items:center;">
            <div style="color:#777;">Name</div><div style="font-weight:700;">{{ $user->name ?? '—' }}</div>
            <div style="color:#777;">Email</div><div style="font-weight:700;">{{ $user->email ?? '—' }}</div>
            <div style="color:#777;">Account ID</div><div style="font-weight:700;">#{{ $user->id ?? '—' }}</div>
        </div>
    </section>
</div>
@endsection
BLADE;
$profile = str_replace('__CRM_LAYOUT__', $layout, $profile);
p42Write($root.'/resources/views/crm/account/profile.blade.php', $profile);

$settings = <<<'BLADE'
@extends('__CRM_LAYOUT__')

@section('content')
<div style="max-width:1050px;margin:0 auto;">
    <div style="margin-bottom:22px;">
        <div style="font-size:11px;letter-spacing:.16em;color:#a87900;font-weight:800;text-transform:uppercase;">System</div>
        <h1 style="margin:5px 0 7px;">CRM Settings</h1>
        <p style="margin:0;color:#737373;">A simple settings hub for the standalone D2D CRM.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
        <a href="{{ url('/marketing/google') }}" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #e7e1d6;border-radius:16px;padding:20px;box-shadow:0 7px 24px rgba(31,25,15,.04);"><div style="font-weight:850;font-size:16px;">Google Setup</div><div style="color:#777;margin-top:6px;font-size:13px;">AdSense, GA4 and Search Console configuration.</div></a>
        <a href="{{ url('/marketing/ads') }}" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #e7e1d6;border-radius:16px;padding:20px;box-shadow:0 7px 24px rgba(31,25,15,.04);"><div style="font-weight:850;font-size:16px;">Ads Manager</div><div style="color:#777;margin-top:6px;font-size:13px;">Manage ad placements and campaign rules.</div></a>
        <a href="{{ route('crm.phase4.guidebooks.index') }}" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #e7e1d6;border-radius:16px;padding:20px;box-shadow:0 7px 24px rgba(31,25,15,.04);"><div style="font-weight:850;font-size:16px;">Guidebooks &amp; Resources</div><div style="color:#777;margin-top:6px;font-size:13px;">Create resources and manage file versions.</div></a>
        <a href="{{ route('crm.phase4.wordpress.index') }}" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #e7e1d6;border-radius:16px;padding:20px;box-shadow:0 7px 24px rgba(31,25,15,.04);"><div style="font-weight:850;font-size:16px;">WordPress Migration</div><div style="color:#777;margin-top:6px;font-size:13px;">Posts-only migration and Dry Run tools.</div></a>
    </div>
</div>
@endsection
BLADE;
$settings = str_replace('__CRM_LAYOUT__', $layout, $settings);
p42Write($root.'/resources/views/crm/account/settings.blade.php', $settings);

// Replace the old injected Phase 4 navigation block with the polished partial.
$layoutContents = (string) file_get_contents($layoutPath);
$navInclude = "{{-- D2D_PHASE4_NAV --}}\n@include('crm.partials.phase4-nav')\n{{-- /D2D_PHASE4_NAV --}}";
$updated = preg_replace('/\{\{-- D2D_PHASE4_NAV --\}\}.*?\{\{-- \/D2D_PHASE4_NAV --\}\}/s', $navInclude, $layoutContents, 1, $navReplaced);
if (! is_string($updated)) {
    $updated = $layoutContents;
}

if (($navReplaced ?? 0) === 0 && ! str_contains($updated, "@include('crm.partials.phase4-nav')")) {
    $pos = strripos($updated, '</aside>');
    if ($pos === false) {
        $pos = strripos($updated, '</nav>');
    }
    if ($pos !== false) {
        $updated = substr($updated, 0, $pos)."\n".$navInclude."\n".substr($updated, $pos);
    }
}

// Inject the top-right account dropdown globally.
if (! str_contains($updated, "@include('crm.partials.admin-menu')")) {
    $menuInclude = "\n{{-- D2D_PHASE4_2_ADMIN_MENU --}}\n@include('crm.partials.admin-menu')\n{{-- /D2D_PHASE4_2_ADMIN_MENU --}}\n";
    $bodyPos = strripos($updated, '</body>');
    if ($bodyPos !== false) {
        $updated = substr($updated, 0, $bodyPos).$menuInclude.substr($updated, $bodyPos);
    } else {
        $updated .= $menuInclude;
    }
}

file_put_contents($layoutPath, $updated);

echo "D2D CRM Phase 4.2 UI polish installed.\n";
echo "Detected CRM layout: {$layout}\n";
echo "- Top-right admin dropdown added\n";
echo "- Sidebar account/email block will be hidden\n";
echo "- Guidebooks & Migration navigation polished\n";
echo "- My Profile and CRM Settings added\n";
echo "- WordPress importer logic unchanged\n";
echo "- Guidebooks remain manual CRM CRUD only\n\n";
echo "Now run:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan route:list | grep -E \"account/profile|account/settings|phase4\"\n";
echo "  php artisan optimize\n\n";
echo "No migration is required.\n";
