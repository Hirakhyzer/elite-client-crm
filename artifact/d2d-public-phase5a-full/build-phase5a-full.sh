#!/usr/bin/env bash
set -euo pipefail
ROOT="artifact/d2d-public-phase5a-full/package"
rm -rf "$ROOT"
mkdir -p "$ROOT"/{app/Http/Controllers,app/Http/Middleware,app/Providers,app/Services,bootstrap,config,public,resources/views,resources/views/errors,routes,storage/app/phase5,storage/framework/cache,storage/framework/sessions,storage/framework/views,storage/logs}

cat > "$ROOT/artisan" <<'PHP'
#!/usr/bin/env php
<?php
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';
$status = $app->handleCommand(new ArgvInput);
exit($status);
PHP
chmod +x "$ROOT/artisan"

cat > "$ROOT/bootstrap/app.php" <<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\Phase5NoIndex::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
PHP
cat > "$ROOT/bootstrap/providers.php" <<'PHP'
<?php
return [App\Providers\AppServiceProvider::class];
PHP

cat > "$ROOT/app/Providers/AppServiceProvider.php" <<'PHP'
<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
PHP

cat > "$ROOT/app/Http/Middleware/Phase5NoIndex.php" <<'PHP'
<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class Phase5NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (filter_var(env('PHASE5_NOINDEX', true), FILTER_VALIDATE_BOOL)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
        return $response;
    }
}
PHP

cat > "$ROOT/app/Services/PublicContentRepository.php" <<'PHP'
<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
class PublicContentRepository
{
    public function published(string $type, int $limit = 12): Collection
    {
        if (!Schema::hasTable('content_posts')) return collect();
        $q = DB::table('content_posts');
        $cols = Schema::getColumnListing('content_posts');
        if (in_array('status',$cols,true)) $q->where('status','published');
        if (in_array('type',$cols,true)) $q->where('type',$type);
        elseif (in_array('content_type',$cols,true)) $q->where('content_type',$type);
        if (in_array('published_at',$cols,true)) $q->orderByDesc('published_at'); else $q->orderByDesc('id');
        return $q->limit($limit)->get();
    }
    public function guidebooks(int $limit = 12): Collection
    {
        if (!Schema::hasTable('guidebook_resources')) return collect();
        $q = DB::table('guidebook_resources')->where('status','published');
        if (Schema::hasColumn('guidebook_resources','access_level')) $q->where('access_level','public');
        return $q->orderByDesc('featured')->orderByDesc('published_at')->limit($limit)->get();
    }
    public function tableCounts(): array
    {
        $tables=['content_posts','universities','opportunities','guidebook_resources','seo_meta','popup_campaigns','banner_campaigns','analytics_events'];
        $out=[];
        foreach($tables as $t){$out[$t]=Schema::hasTable($t)?DB::table($t)->count():null;}
        return $out;
    }
}
PHP

cat > "$ROOT/app/Services/V11ArchiveInspector.php" <<'PHP'
<?php
namespace App\Services;
use ZipArchive;
class V11ArchiveInspector
{
    public function inspect(string $path): array
    {
        $result=['exists'=>is_file($path),'path'=>$path,'zip_extension'=>class_exists(ZipArchive::class),'entries'=>0,'entrypoint'=>null,'package_json'=>null,'type'=>'unknown','candidates'=>[]];
        if(!$result['exists']||!$result['zip_extension']) return $result;
        $zip=new ZipArchive(); if($zip->open($path)!==true) return $result;
        $names=[]; for($i=0;$i<$zip->numFiles;$i++){ $n=$zip->getNameIndex($i); if($n!==false)$names[]=$n; }
        $result['entries']=count($names);
        foreach($names as $n){
            $l=strtolower($n);
            if(preg_match('#(^|/)(dist|build)/index\.html$#',$l)){ $result['candidates'][]=$n; }
            elseif(preg_match('#(^|/)index\.html$#',$l)){ $result['candidates'][]=$n; }
            if(str_ends_with($l,'package.json') && $result['package_json']===null) $result['package_json']=$n;
        }
        if($result['candidates']){$result['entrypoint']=$result['candidates'][0];$result['type']='static-or-built';}
        elseif($result['package_json']){$result['type']='source-project';}
        $zip->close(); return $result;
    }
    public function installStatic(string $path, string $publicDir): array
    {
        $info=$this->inspect($path); if(($info['type']??'')!=='static-or-built') return ['ok'=>false,'message'=>'No built/static index.html detected.','info'=>$info];
        $zip=new ZipArchive(); if($zip->open($path)!==true) return ['ok'=>false,'message'=>'Cannot open ZIP.'];
        $entry=$info['entrypoint']; $base=preg_replace('#index\.html$#','',$entry);
        $tmp=storage_path('app/phase5/v11-extracted');
        if(is_dir($tmp)) $this->rrmdir($tmp); @mkdir($tmp,0775,true);
        $zip->extractTo($tmp); $zip->close();
        $src=rtrim($tmp.'/'.trim($base,'/'),'/');
        $dest=rtrim($publicDir,'/').'/v11'; if(is_dir($dest)) $this->rrmdir($dest); @mkdir($dest,0775,true);
        $this->rcopy($src,$dest);
        return ['ok'=>true,'message'=>'V11 static build installed to public/v11','entrypoint'=>$entry,'public'=>$dest];
    }
    private function rrmdir(string $dir): void { if(!is_dir($dir))return; foreach(scandir($dir)?:[] as $x){if($x==='.'||$x==='..')continue;$p=$dir.'/'.$x;is_dir($p)?$this->rrmdir($p):@unlink($p);}@rmdir($dir); }
    private function rcopy(string $src,string $dst): void { @mkdir($dst,0775,true); foreach(scandir($src)?:[] as $x){if($x==='.'||$x==='..')continue;$a=$src.'/'.$x;$b=$dst.'/'.$x;is_dir($a)?$this->rcopy($a,$b):copy($a,$b);} }
}
PHP

cat > "$ROOT/app/Http/Controllers/PublicSiteController.php" <<'PHP'
<?php
namespace App\Http\Controllers;
use App\Services\PublicContentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
class PublicSiteController extends Controller
{
    public function home(PublicContentRepository $repo)
    {
        $v11=public_path('v11/index.html');
        if(File::isFile($v11)) return response(File::get($v11),200,['Content-Type'=>'text/html; charset=UTF-8']);
        return view('phase5-home',['counts'=>$repo->tableCounts()]);
    }
    public function v11Asset(Request $request,string $path='')
    {
        $base=realpath(public_path('v11')); $file=realpath(public_path('v11/'.$path));
        abort_unless($base&&$file&&str_starts_with($file,$base)&&is_file($file),404);
        return response()->file($file);
    }
}
PHP
cat > "$ROOT/app/Http/Controllers/Controller.php" <<'PHP'
<?php
namespace App\Http\Controllers;
abstract class Controller {}
PHP

cat > "$ROOT/routes/web.php" <<'PHP'
<?php
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;
Route::get('/',[PublicSiteController::class,'home'])->name('home');
Route::get('/_phase5/health',fn()=>response()->json(['ok'=>true,'phase'=>'5A','app'=>config('app.name'),'time'=>now()->toIso8601String()]));
Route::get('/_phase5/db',function(\App\Services\PublicContentRepository $repo){return response()->json($repo->tableCounts());});
Route::get('/v11/{path}',[PublicSiteController::class,'v11Asset'])->where('path','.*');
PHP

cat > "$ROOT/routes/console.php" <<'PHP'
<?php
use App\Services\PublicContentRepository;
use App\Services\V11ArchiveInspector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
Artisan::command('phase5:base-status',function(){
 $this->info('D2D Phase 5A public Laravel base is running.');
 $this->line('Base: '.base_path()); $this->line('URL: '.config('app.url')); $this->line('Noindex: '.(env('PHASE5_NOINDEX',true)?'ON':'OFF'));
})->purpose('Check Phase 5A base');
Artisan::command('phase5:doctor {--install-v11}',function(PublicContentRepository $repo,V11ArchiveInspector $v11){
 $this->info('=== D2D PHASE 5A DOCTOR ===');
 $this->line('Laravel: '.app()->version()); $this->line('PHP: '.PHP_VERSION); $this->line('Base: '.base_path());
 try{DB::connection()->getPdo();$this->info('DB: CONNECTED');}catch(Throwable $e){$this->error('DB: FAILED - '.$e->getMessage());}
 foreach($repo->tableCounts() as $t=>$c){$this->line(sprintf('%-24s %s',$t,$c===null?'MISSING':'OK count='.$c));}
 $path=storage_path('app/phase5/v11.zip'); $info=$v11->inspect($path); $this->newLine(); $this->info('V11 ZIP');
 foreach($info as $k=>$val){$this->line($k.': '.(is_array($val)?json_encode($val,JSON_UNESCAPED_SLASHES):(is_bool($val)?($val?'yes':'no'):(string)$val));}
 if($this->option('install-v11')){ $r=$v11->installStatic($path,public_path()); ($r['ok']??false)?$this->info($r['message']):$this->error($r['message']); }
 $this->newLine(); $this->info('No migrations were run by Phase 5A.');
})->purpose('Inspect public DB readiness and exact V11 ZIP');
PHP

cat > "$ROOT/resources/views/phase5-home.blade.php" <<'BLADE'
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Dare To Dream — Phase 5A</title><style>body{margin:0;background:#090909;color:#fff;font-family:Arial,sans-serif}.wrap{max-width:980px;margin:10vh auto;padding:28px}.gold{color:#f4af00}.card{border:1px solid #292929;border-radius:20px;padding:28px;background:#111}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.item{background:#181818;border-radius:14px;padding:16px}code{color:#f4af00}</style></head><body><div class="wrap"><div class="card"><h1>Dare To Dream <span class="gold">Phase 5A</span></h1><p>The permanent public Laravel application is running. Upload the exact V11 ZIP to <code>storage/app/phase5/v11.zip</code>, then run <code>php artisan phase5:doctor --install-v11</code>.</p><div class="grid">@foreach($counts as $table=>$count)<div class="item"><strong>{{ $table }}</strong><br>{{ $count===null?'missing':$count }}</div>@endforeach</div></div></div></body></html>
BLADE

cat > "$ROOT/config/app.php" <<'PHP'
<?php
return ['name'=>env('APP_NAME','Dare To Dream'),'env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','http://localhost'),'timezone'=>'UTC','locale'=>'en','fallback_locale'=>'en','faker_locale'=>'en_US','key'=>env('APP_KEY'),'cipher'=>'AES-256-CBC','maintenance'=>['driver'=>'file']];
PHP
cat > "$ROOT/config/database.php" <<'PHP'
<?php
return ['default'=>env('DB_CONNECTION','mysql'),'connections'=>['mysql'=>['driver'=>'mysql','url'=>env('DB_URL'),'host'=>env('DB_HOST','127.0.0.1'),'port'=>env('DB_PORT','3306'),'database'=>env('DB_DATABASE','forge'),'username'=>env('DB_USERNAME','forge'),'password'=>env('DB_PASSWORD',''),'unix_socket'=>env('DB_SOCKET',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','prefix_indexes'=>true,'strict'=>true,'engine'=>null,'options'=>extension_loaded('pdo_mysql')?array_filter([PDO::ATTR_EMULATE_PREPARES=>true]):[]]],'migrations'=>['table'=>'migrations','update_date_on_publish'=>true],'redis'=>['client'=>env('REDIS_CLIENT','phpredis')]];
PHP
cat > "$ROOT/config/cache.php" <<'PHP'
<?php
return ['default'=>env('CACHE_STORE','file'),'stores'=>['file'=>['driver'=>'file','path'=>storage_path('framework/cache/data')]],'prefix'=>env('CACHE_PREFIX','d2d_phase5_cache')];
PHP
cat > "$ROOT/config/session.php" <<'PHP'
<?php
return ['driver'=>env('SESSION_DRIVER','file'),'lifetime'=>(int)env('SESSION_LIFETIME',120),'expire_on_close'=>false,'encrypt'=>false,'files'=>storage_path('framework/sessions'),'connection'=>env('SESSION_CONNECTION'),'table'=>'sessions','store'=>env('SESSION_STORE'),'lottery'=>[2,100],'cookie'=>env('SESSION_COOKIE','d2d_phase5_session'),'path'=>'/','domain'=>env('SESSION_DOMAIN'),'secure'=>env('SESSION_SECURE_COOKIE',true),'http_only'=>true,'same_site'=>'lax','partitioned'=>false];
PHP
cat > "$ROOT/config/filesystems.php" <<'PHP'
<?php
return ['default'=>env('FILESYSTEM_DISK','local'),'disks'=>['local'=>['driver'=>'local','root'=>storage_path('app/private'),'serve'=>true,'throw'=>false],'public'=>['driver'=>'local','root'=>storage_path('app/public'),'url'=>env('APP_URL').'/storage','visibility'=>'public','throw'=>false]],'links'=>[public_path('storage')=>storage_path('app/public')]];
PHP
cat > "$ROOT/config/logging.php" <<'PHP'
<?php
use Monolog\Handler\NullHandler; use Monolog\Handler\StreamHandler;
return ['default'=>env('LOG_CHANNEL','stack'),'deprecations'=>['channel'=>env('LOG_DEPRECATIONS_CHANNEL','null'),'trace'=>false],'channels'=>['stack'=>['driver'=>'stack','channels'=>explode(',',env('LOG_STACK','single')),'ignore_exceptions'=>false],'single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>env('LOG_LEVEL','debug'),'replace_placeholders'=>true],'stderr'=>['driver'=>'monolog','level'=>env('LOG_LEVEL','debug'),'handler'=>StreamHandler::class,'with'=>['stream'=>'php://stderr']],'null'=>['driver'=>'monolog','handler'=>NullHandler::class]]];
PHP
cat > "$ROOT/config/view.php" <<'PHP'
<?php
return ['paths'=>[resource_path('views')],'compiled'=>env('VIEW_COMPILED_PATH',realpath(storage_path('framework/views'))?:storage_path('framework/views'))];
PHP

cat > "$ROOT/public/index.php" <<'PHP'
<?php
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
if(file_exists($maintenance=__DIR__.'/../storage/framework/maintenance.php')) require $maintenance;
require __DIR__.'/../vendor/autoload.php';
(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
PHP
cat > "$ROOT/public/.htaccess" <<'HT'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>Options -MultiViews -Indexes</IfModule>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HT

cat > "$ROOT/.env.phase5.example" <<'ENV'
APP_NAME="Dare To Dream"
APP_ENV=production
APP_KEY=
APP_DEBUG=true
APP_URL=https://preview.dares2dream.com
PHASE5_NOINDEX=true
LOG_CHANNEL=stack
LOG_LEVEL=debug
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
SESSION_DRIVER=file
CACHE_STORE=file
FILESYSTEM_DISK=local
ENV

cat > "$ROOT/setup-phase5a.php" <<'PHP'
<?php
$root=__DIR__;
if(!is_file($root.'/vendor/autoload.php')){fwrite(STDERR,"ERROR: vendor/autoload.php missing. Copy vendor from the existing Portal first.\n");exit(1);} 
foreach(['bootstrap/cache','storage/app/phase5','storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs'] as $d){if(!is_dir($root.'/'.$d))mkdir($root.'/'.$d,0775,true);@chmod($root.'/'.$d,0775);} 
if(!is_file($root.'/.env')){
 $env=file_get_contents($root.'/.env.phase5.example');
 $crm=dirname($root).'/crm.dares2dream.com/.env';
 if(is_file($crm)){
  $src=file($crm,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
  foreach(['DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key){foreach($src as $line){if(str_starts_with(trim($line),$key.'=')){ $value=substr(trim($line),strlen($key)+1); $env=preg_replace('/^'.preg_quote($key,'/').'=.*$/m',$key.'='.$value,$env); break; }}}
 }
 file_put_contents($root.'/.env',$env); echo "Created .env (DB settings copied from CRM when available).\n";
} else echo ".env already exists; left unchanged.\n";
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
try{ $key=trim((string)($_ENV['APP_KEY']??getenv('APP_KEY'))); }catch(Throwable $e){$key='';}
echo "Phase 5A files ready. Next run: php artisan key:generate --force\nThen: php artisan optimize:clear\nThen: php artisan phase5:doctor\n";
PHP

cat > "$ROOT/README.md" <<'MD'
# D2D Public Phase 5A — Full Laravel Base

This is a complete public Laravel application skeleton for `/home/daresdre/d2d-laravel` **except `vendor/`**. It does not contain CRM or Portal code and does not run database migrations.

## Install
1. Back up/delete the earlier incomplete Phase 5 files if desired; keep `vendor/` and your V11 ZIP.
2. Extract this ZIP directly into `/home/daresdre/d2d-laravel/`.
3. If `vendor/` is missing: `mkdir -p vendor && cp -a ~/portal.dares2dream.com/vendor/. ~/d2d-laravel/vendor/`
4. Run `php setup-phase5a.php`
5. Run `php artisan key:generate --force`
6. Run `php artisan optimize:clear`
7. Put exact V11 ZIP at `storage/app/phase5/v11.zip`
8. Run `php artisan phase5:doctor`
9. If doctor says `type: static-or-built`, run `php artisan phase5:doctor --install-v11`.

Keep `PHASE5_NOINDEX=true` while previewing. No migrations are needed.
MD

cat > "$ROOT/FULL_PACKAGE_MARKER.txt" <<'TXT'
D2D PUBLIC PHASE 5A FULL PACKAGE
Contains artisan, bootstrap, config, routes, app services/controllers/middleware, public front controller, views, setup script and storage directories.
Vendor is intentionally excluded and should be copied from the existing compatible Laravel install.
TXT
