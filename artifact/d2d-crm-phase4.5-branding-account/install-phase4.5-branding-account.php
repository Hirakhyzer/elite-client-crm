<?php

declare(strict_types=1);

/**
 * D2D CRM Phase 4.5 — Branding + Admin Account
 *
 * Adds a safe CRM account/branding editor for the currently authenticated CRM admin:
 * - CRM logo upload
 * - favicon upload (falls back to logo if no separate favicon is uploaded)
 * - admin profile photo upload/remove
 * - admin name + email update
 * - password change with current-password verification
 * - updates top-right admin avatar dynamically
 * - updates the first small upper-left CRM logo image dynamically
 *
 * No migrations. No Portal files. No role changes.
 * IMPORTANT: name/email/password are stored on the shared users table, so changing
 * them changes this same user's credentials anywhere the shared account is used.
 */

$root = __DIR__;

if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Extract this ZIP into /home/daresdre/crm.dares2dream.com first.\n");
    exit(1);
}

function p45ViewPath(string $root, string $view): string
{
    return $root.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
}

function p45Backup(string $path): void
{
    if (! is_file($path)) {
        return;
    }

    $backup = $path.'.phase4.5-backup';
    if (! is_file($backup)) {
        copy($path, $backup);
    }
}

function p45Write(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    p45Backup($path);
    file_put_contents($path, $content);
}

function p45AppendOnce(string $path, string $marker, string $content): bool
{
    if (! is_file($path)) {
        return false;
    }

    $current = (string) file_get_contents($path);
    if (str_contains($current, $marker)) {
        return true;
    }

    p45Backup($path);
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
        if ($candidate !== '' && is_file(p45ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    foreach (['crm.layout','crm.layouts.master','crm.layouts.dashboard','layouts.crm','layouts.app'] as $candidate) {
        if (is_file(p45ViewPath($root, $candidate))) {
            $layout = $candidate;
            break;
        }
    }
}

if ($layout === null) {
    fwrite(STDERR, "ERROR: Could not detect the working CRM Blade layout.\n");
    exit(2);
}

$layoutPath = p45ViewPath($root, $layout);
p45Backup($layoutPath);

// Controller.
$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CrmAccountBrandingController extends Controller
{
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password_for_email' => ['nullable', 'string'],
        ]);

        $emailChanged = strcasecmp((string) $user->email, (string) $data['email']) !== 0;

        if ($emailChanged) {
            $currentPassword = (string) ($data['current_password_for_email'] ?? '');
            if ($currentPassword === '' || ! Hash::check($currentPassword, (string) $user->password)) {
                return back()->withErrors([
                    'current_password_for_email' => 'Enter your current password to change the login email.',
                ])->withInput();
            }
        }

        $user->name = trim((string) $data['name']);
        $user->email = strtolower(trim((string) $data['email']));
        $user->save();

        return back()->with('status', 'Admin profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->password = Hash::make((string) $data['password']);
        $user->save();

        return back()->with('status', 'Password changed successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $dir = public_path('uploads/admin-avatars');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach (glob($dir.'/admin-'.$user->id.'.*') ?: [] as $old) {
            @unlink($old);
        }

        $file = $request->file('avatar');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $filename = 'admin-'.$user->id.'.'.$ext;
        $file->move($dir, $filename);

        return back()->with('status', 'Admin profile photo updated.');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $dir = public_path('uploads/admin-avatars');

        foreach (glob($dir.'/admin-'.$user->id.'.*') ?: [] as $old) {
            @unlink($old);
        }

        return back()->with('status', 'Admin profile photo removed.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'crm_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $dir = public_path('branding');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach (glob($dir.'/crm-logo.*') ?: [] as $old) {
            @unlink($old);
        }

        $file = $request->file('crm_logo');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $file->move($dir, 'crm-logo.'.$ext);

        return back()->with('status', 'CRM logo updated. The favicon will also use this logo until you upload a separate favicon.');
    }

    public function updateFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => ['required', 'file', 'mimes:ico,png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $dir = public_path('branding');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach (glob($dir.'/favicon.*') ?: [] as $old) {
            @unlink($old);
        }

        $file = $request->file('favicon');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $file->move($dir, 'favicon.'.$ext);

        return back()->with('status', 'CRM favicon updated.');
    }
}
PHP;

p45Write($root.'/app/Http/Controllers/Crm/CrmAccountBrandingController.php', $controller);

// Dedicated routes. Existing GET /account/profile from Phase 4.2 remains unchanged.
$routes = <<<'PHP'
<?php

use App\Http\Controllers\Crm\CrmAccountBrandingController;
use App\Http\Middleware\Phase4CrmAccess;
use Illuminate\Support\Facades\Route;

Route::middleware([Phase4CrmAccess::class])->group(function () {
    Route::post('/account/profile/update', [CrmAccountBrandingController::class, 'updateProfile'])->name('crm.account.profile.update');
    Route::post('/account/profile/avatar', [CrmAccountBrandingController::class, 'updateAvatar'])->name('crm.account.avatar.update');
    Route::delete('/account/profile/avatar', [CrmAccountBrandingController::class, 'removeAvatar'])->name('crm.account.avatar.remove');
    Route::post('/account/security/password', [CrmAccountBrandingController::class, 'updatePassword'])->name('crm.account.password.update');
    Route::post('/account/branding/logo', [CrmAccountBrandingController::class, 'updateLogo'])->name('crm.account.branding.logo');
    Route::post('/account/branding/favicon', [CrmAccountBrandingController::class, 'updateFavicon'])->name('crm.account.branding.favicon');
});
PHP;

p45Write($root.'/routes/phase4-branding-account.php', $routes);

p45AppendOnce(
    $root.'/routes/web.php',
    'D2D_PHASE4_5_BRANDING_ACCOUNT_ROUTES',
    "// D2D_PHASE4_5_BRANDING_ACCOUNT_ROUTES\nrequire __DIR__.'/phase4-branding-account.php';"
);

// Functional My Profile page.
$profileView = '@extends(\''.$layout.'\')'."\n\n".<<<'BLADE'
@section('content')
@php
    $admin = auth()->user();
    $avatarFiles = glob(public_path('uploads/admin-avatars/admin-'.$admin->id.'.*')) ?: [];
    $avatarFile = $avatarFiles[0] ?? null;
    $avatarUrl = $avatarFile ? asset(str_replace(public_path().DIRECTORY_SEPARATOR, '', $avatarFile)) : null;

    $logoFiles = glob(public_path('branding/crm-logo.*')) ?: [];
    $logoFile = $logoFiles[0] ?? null;
    $logoUrl = $logoFile ? asset(str_replace(public_path().DIRECTORY_SEPARATOR, '', $logoFile)) : null;

    $faviconFiles = glob(public_path('branding/favicon.*')) ?: [];
    $faviconFile = $faviconFiles[0] ?? null;
    $faviconUrl = $faviconFile ? asset(str_replace(public_path().DIRECTORY_SEPARATOR, '', $faviconFile)) : $logoUrl;
@endphp

<div class="p45-page">
    <div class="p45-head">
        <div>
            <div class="p45-eyebrow">ACCOUNT &amp; BRANDING</div>
            <h1>My Profile</h1>
            <p>Manage the CRM admin identity, login security and CRM-only branding.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="p45-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="p45-errors">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="p45-grid">
        <section class="p45-card">
            <div class="p45-card-head">
                <div>
                    <div class="p45-kicker">ADMIN PHOTO</div>
                    <h2>Profile picture</h2>
                    <p>This appears in the CRM admin menu.</p>
                </div>
                <div class="p45-avatar-preview">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}?v={{ time() }}" alt="Admin profile photo">
                    @else
                        <span>{{ strtoupper(substr((string) ($admin->name ?? 'A'), 0, 1)) }}</span>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('crm.account.avatar.update') }}" enctype="multipart/form-data" class="p45-form">
                @csrf
                <label>Upload profile photo
                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" required>
                </label>
                <small>JPG, PNG or WebP. Maximum 4 MB.</small>
                <button class="p45-btn" type="submit">Update photo</button>
            </form>

            @if($avatarUrl)
                <form method="POST" action="{{ route('crm.account.avatar.remove') }}" class="p45-inline-form">
                    @csrf
                    @method('DELETE')
                    <button class="p45-link-danger" type="submit">Remove photo</button>
                </form>
            @endif
        </section>

        <section class="p45-card">
            <div class="p45-kicker">ADMIN IDENTITY</div>
            <h2>Name &amp; email</h2>
            <p>The name is shown in the CRM header. Email is also the login email for this shared user account.</p>

            <form method="POST" action="{{ route('crm.account.profile.update') }}" class="p45-form">
                @csrf
                <label>Admin name
                    <input type="text" name="name" value="{{ old('name', $admin->name) }}" maxlength="120" required>
                </label>
                <label>Email address
                    <input type="email" name="email" value="{{ old('email', $admin->email) }}" maxlength="190" required>
                </label>
                <label>Current password <span class="p45-muted">required only when changing email</span>
                    <input type="password" name="current_password_for_email" autocomplete="current-password">
                </label>
                <button class="p45-btn" type="submit">Save profile</button>
            </form>
        </section>

        <section class="p45-card">
            <div class="p45-kicker">SECURITY</div>
            <h2>Change password</h2>
            <p>For safety, your current password is required.</p>

            <form method="POST" action="{{ route('crm.account.password.update') }}" class="p45-form">
                @csrf
                <label>Current password
                    <input type="password" name="current_password" autocomplete="current-password" required>
                </label>
                <label>New password
                    <input type="password" name="password" autocomplete="new-password" minlength="10" required>
                </label>
                <label>Confirm new password
                    <input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required>
                </label>
                <button class="p45-btn" type="submit">Change password</button>
            </form>
        </section>

        <section class="p45-card p45-brand-card">
            <div class="p45-kicker">CRM BRANDING</div>
            <h2>Logo &amp; favicon</h2>
            <p>Upload the eagle artwork here. This affects CRM only — not the Portal or public website.</p>

            <div class="p45-brand-previews">
                <div>
                    <span>CRM logo</span>
                    <div class="p45-logo-box">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}?v={{ time() }}" alt="CRM logo">
                        @else
                            <em>No custom logo yet</em>
                        @endif
                    </div>
                </div>
                <div>
                    <span>Favicon</span>
                    <div class="p45-favicon-box">
                        @if($faviconUrl)
                            <img src="{{ $faviconUrl }}?v={{ time() }}" alt="CRM favicon">
                        @else
                            <em>—</em>
                        @endif
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('crm.account.branding.logo') }}" enctype="multipart/form-data" class="p45-form p45-divider-top">
                @csrf
                <label>CRM logo
                    <input type="file" name="crm_logo" accept="image/png,image/jpeg,image/webp" required>
                </label>
                <small>If no separate favicon is uploaded, the browser icon will use this logo.</small>
                <button class="p45-btn" type="submit">Update CRM logo</button>
            </form>

            <form method="POST" action="{{ route('crm.account.branding.favicon') }}" enctype="multipart/form-data" class="p45-form p45-divider-top">
                @csrf
                <label>Favicon <span class="p45-muted">optional separate icon</span>
                    <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp,image/x-icon,.ico" required>
                </label>
                <button class="p45-btn-secondary" type="submit">Update favicon</button>
            </form>
        </section>
    </div>

    <div class="p45-shared-warning">
        <strong>Shared-account notice:</strong> CRM and Portal use the same users table. This page changes only the currently logged-in CRM admin. If this same user account is used elsewhere, changing its email/password changes that account there too. No role or permission is changed by this patch.
    </div>
</div>

<style>
.p45-page{max-width:1180px;margin:0 auto;padding:6px 4px 36px;color:#191919}.p45-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px}.p45-head h1{font-size:31px;line-height:1.05;margin:5px 0 7px;font-weight:900;letter-spacing:-.03em}.p45-head p,.p45-card p{color:#6f6c66;font-size:13px;margin:0;line-height:1.55}.p45-eyebrow,.p45-kicker{font-size:10px;letter-spacing:.16em;font-weight:900;color:#b77d00}.p45-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.p45-card{background:#fff;border:1px solid #e6e0d5;border-radius:18px;padding:22px;box-shadow:0 10px 35px rgba(35,29,18,.05)}.p45-card h2{font-size:19px;margin:5px 0 5px}.p45-card-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.p45-avatar-preview{width:74px;height:74px;border-radius:18px;background:#f4af00;color:#111;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:950;overflow:hidden;flex:0 0 74px;border:1px solid #e0a000}.p45-avatar-preview img{width:100%;height:100%;object-fit:cover}.p45-form{display:grid;gap:12px;margin-top:18px}.p45-form label{display:grid;gap:7px;font-size:12px;font-weight:800;color:#3b3935}.p45-form input{width:100%;box-sizing:border-box;border:1px solid #dcd5c9;border-radius:11px;padding:11px 12px;background:#fff;font:inherit;font-size:13px}.p45-form input:focus{outline:3px solid rgba(244,175,0,.16);border-color:#d69b00}.p45-form small{font-size:11px;color:#868078}.p45-btn,.p45-btn-secondary{display:inline-flex;justify-content:center;align-items:center;min-height:41px;border-radius:11px;padding:0 15px;font:inherit;font-size:12px;font-weight:900;cursor:pointer}.p45-btn{border:1px solid #e1a000;background:#f4af00;color:#111}.p45-btn:hover{filter:brightness(.97)}.p45-btn-secondary{border:1px solid #ddd6c9;background:#fff;color:#26231f}.p45-inline-form{margin-top:10px}.p45-link-danger{border:0;background:transparent;padding:2px 0;color:#b42318;font-size:12px;font-weight:800;cursor:pointer}.p45-muted{font-size:10px;font-weight:600;color:#8e8980}.p45-success,.p45-errors{padding:13px 15px;border-radius:12px;margin-bottom:18px;font-size:12px}.p45-success{background:#edf9f0;border:1px solid #b9e5c3;color:#176b2d}.p45-errors{background:#fff2f0;border:1px solid #f0c5bf;color:#9b251b}.p45-errors ul{margin:7px 0 0;padding-left:18px}.p45-brand-card{grid-column:1/-1}.p45-brand-previews{display:grid;grid-template-columns:minmax(0,1fr) 140px;gap:15px;margin-top:18px}.p45-brand-previews>div>span{display:block;font-size:11px;font-weight:850;color:#55514b;margin-bottom:7px}.p45-logo-box,.p45-favicon-box{border:1px solid #e1dbcf;background:#f8f6f1;border-radius:14px;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#8a847c}.p45-logo-box{height:150px}.p45-logo-box img{max-width:92%;max-height:135px;object-fit:contain}.p45-favicon-box{height:150px}.p45-favicon-box img{width:70px;height:70px;object-fit:contain;border-radius:12px}.p45-logo-box em,.p45-favicon-box em{font-size:11px}.p45-divider-top{padding-top:17px;border-top:1px solid #eee8dd}.p45-shared-warning{margin-top:18px;border:1px solid #ead49e;background:#fffbef;border-radius:14px;padding:14px 16px;color:#6d5416;font-size:12px;line-height:1.55}
@media(max-width:800px){.p45-grid{grid-template-columns:1fr}.p45-brand-card{grid-column:auto}.p45-brand-previews{grid-template-columns:1fr}.p45-favicon-box{height:110px}}
</style>
@endsection
BLADE;

p45Write($root.'/resources/views/crm/account/profile.blade.php', $profileView);

// Branding injection into working CRM layout.
$layoutContents = (string) file_get_contents($layoutPath);
$marker = 'D2D_PHASE45_BRANDING_RUNTIME';

if (! str_contains($layoutContents, $marker)) {
    $headBlock = <<<'BLADE'
    {{-- D2D_PHASE45_BRANDING_RUNTIME --}}
    @php
        $d2dBrandLogoFiles = glob(public_path('branding/crm-logo.*')) ?: [];
        $d2dBrandLogoFile = $d2dBrandLogoFiles[0] ?? null;
        $d2dBrandLogoUrl = $d2dBrandLogoFile ? asset(str_replace(public_path().DIRECTORY_SEPARATOR, '', $d2dBrandLogoFile)) : null;

        $d2dFavFiles = glob(public_path('branding/favicon.*')) ?: [];
        $d2dFavFile = $d2dFavFiles[0] ?? null;
        $d2dFavUrl = $d2dFavFile ? asset(str_replace(public_path().DIRECTORY_SEPARATOR, '', $d2dFavFile)) : $d2dBrandLogoUrl;
    @endphp
    @if($d2dFavUrl)
        <link rel="icon" href="{{ $d2dFavUrl }}?v={{ @filemtime($d2dFavFile ?: $d2dBrandLogoFile) ?: time() }}">
        <link rel="apple-touch-icon" href="{{ $d2dFavUrl }}?v={{ @filemtime($d2dFavFile ?: $d2dBrandLogoFile) ?: time() }}">
    @endif
BLADE;

    $headPos = stripos($layoutContents, '</head>');
    if ($headPos !== false) {
        $layoutContents = substr($layoutContents, 0, $headPos)."\n".$headBlock."\n".substr($layoutContents, $headPos);
    } else {
        $layoutContents = $headBlock."\n".$layoutContents;
    }

    $runtimeBlock = <<<'BLADE'
{{-- D2D_PHASE45_BRANDING_RUNTIME_SCRIPT --}}
@auth
@php
    $d2dAvatarFiles = glob(public_path('uploads/admin-avatars/admin-'.auth()->id().'.*')) ?: [];
    $d2dAvatarFile = $d2dAvatarFiles[0] ?? null;
    $d2dAvatarUrl = $d2dAvatarFile ? asset(str_replace(public_path().DIRECTORY_SEPARATOR, '', $d2dAvatarFile)) : null;
@endphp
<style>
.d2d-admin-avatar img{width:100%;height:100%;display:block;object-fit:cover;border-radius:inherit}.d2d-phase45-logo{object-fit:contain!important;background:#fff!important;border-radius:10px!important;padding:2px!important}
</style>
<script>
(function(){
    const brandLogo=@json($d2dBrandLogoUrl);
    const adminAvatar=@json($d2dAvatarUrl);

    function applyBranding(){
        if(adminAvatar){
            const slot=document.querySelector('.d2d-admin-avatar');
            if(slot){
                slot.innerHTML='';
                const img=document.createElement('img');
                img.src=adminAvatar+'?v='+(Date.now());
                img.alt='Admin';
                slot.appendChild(img);
            }
        }

        if(brandLogo){
            const candidates=[...document.querySelectorAll('aside img, nav img, header img')].filter(function(img){
                const r=img.getBoundingClientRect();
                return r.width>0 && r.height>0 && r.top<150 && r.left<220 && r.width<130 && r.height<130;
            });
            if(candidates.length){
                candidates[0].src=brandLogo+'?v='+(Date.now());
                candidates[0].alt='D2D CRM';
                candidates[0].classList.add('d2d-phase45-logo');
            }
        }
    }

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',applyBranding);
    else applyBranding();
})();
</script>
@endauth
BLADE;

    $bodyPos = stripos($layoutContents, '</body>');
    if ($bodyPos !== false) {
        $layoutContents = substr($layoutContents, 0, $bodyPos)."\n".$runtimeBlock."\n".substr($layoutContents, $bodyPos);
    } else {
        $layoutContents .= "\n".$runtimeBlock."\n";
    }

    file_put_contents($layoutPath, $layoutContents);
}

// Ensure upload folders exist.
foreach ([$root.'/public/branding', $root.'/public/uploads/admin-avatars'] as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// Optional doctor command.
$consoleMarker = 'D2D_PHASE4_5_DOCTOR';
$consoleFile = $root.'/routes/console.php';
if (is_file($consoleFile)) {
    p45AppendOnce($consoleFile, $consoleMarker, <<<'PHP'
// D2D_PHASE4_5_DOCTOR
\Illuminate\Support\Facades\Artisan::command('phase4.5:doctor', function () {
    $checks = [
        'controller' => app_path('Http/Controllers/Crm/CrmAccountBrandingController.php'),
        'profile view' => resource_path('views/crm/account/profile.blade.php'),
        'routes' => base_path('routes/phase4-branding-account.php'),
        'branding dir' => public_path('branding'),
        'avatar dir' => public_path('uploads/admin-avatars'),
    ];

    foreach ($checks as $label => $path) {
        $ok = is_dir($path) || is_file($path);
        $this->line(($ok ? '[OK] ' : '[MISSING] ').$label.' — '.$path);
    }

    $this->info('Phase 4.5 doctor complete.');
});
PHP);
}

echo "\nD2D CRM Phase 4.5 installed successfully.\n";
echo "Detected CRM layout: {$layout}\n";
echo "No migration was added or run.\n";
echo "Portal files were not touched.\n\n";
echo "Next run:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan route:list | grep -E \"account/profile/update|account/security/password|account/branding\"\n";
echo "  php artisan phase4.5:doctor\n";
echo "  php artisan optimize\n\n";
echo "Then open /account/profile in CRM.\n";
