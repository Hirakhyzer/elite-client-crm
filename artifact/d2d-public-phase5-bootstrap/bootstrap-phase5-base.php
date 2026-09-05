<?php

declare(strict_types=1);

$root = __DIR__;
$normalized = str_replace('\\', '/', $root);

foreach (['crm.dares2dream.com', 'portal.dares2dream.com', '/public_html'] as $forbidden) {
    if (str_contains($normalized, $forbidden)) {
        fwrite(STDERR, "REFUSED: Phase 5 public bootstrap must not run inside CRM, Portal, or public_html.\n");
        exit(1);
    }
}

if (!is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "ERROR: vendor/autoload.php is missing. Copy the existing Laravel vendor directory first.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';

if (!class_exists(\Illuminate\Foundation\Application::class)) {
    fwrite(STDERR, "ERROR: Laravel framework was not detected in vendor/.\n");
    exit(1);
}

$version = \Illuminate\Foundation\Application::VERSION;
$major = (int) explode('.', $version)[0];

if ($major < 11) {
    fwrite(STDERR, "ERROR: Detected Laravel {$version}. This bootstrap package supports Laravel 11/12+. Stop here and send this version to ChatGPT.\n");
    exit(1);
}

if (is_file($root.'/artisan')) {
    echo "Laravel application already appears to exist: artisan found. No base files were replaced.\n";
    echo "Detected Laravel framework: {$version}\n";
    exit(0);
}

$dirs = [
    'app/Providers',
    'bootstrap/cache',
    'config',
    'public',
    'resources/views',
    'routes',
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
];

foreach ($dirs as $dir) {
    $path = $root.'/'.$dir;
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException("Could not create directory: {$path}");
    }
}

$write = static function (string $relative, string $content) use ($root): void {
    $path = $root.'/'.$relative;
    if (is_file($path)) {
        echo "KEEP  {$relative}\n";
        return;
    }
    $parent = dirname($path);
    if (!is_dir($parent)) {
        mkdir($parent, 0775, true);
    }
    file_put_contents($path, $content);
    echo "WRITE {$relative}\n";
};

$write('artisan', <<<'PHPFILE'
#!/usr/bin/env php
<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$status = (require_once __DIR__.'/bootstrap/app.php')
    ->handleCommand(new Symfony\Component\Console\Input\ArgvInput);

exit($status);
PHPFILE
);
@chmod($root.'/artisan', 0755);

$write('bootstrap/app.php', <<<'PHPFILE'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Public application middleware is added during Phase 5 integration.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Public application exception customization is added during Phase 5 integration.
    })
    ->create();
PHPFILE
);

$write('bootstrap/providers.php', <<<'PHPFILE'
<?php

return [
    App\Providers\AppServiceProvider::class,
];
PHPFILE
);

$write('app/Providers/AppServiceProvider.php', <<<'PHPFILE'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
PHPFILE
);

$write('routes/web.php', <<<'PHPFILE'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
PHPFILE
);

$write('routes/console.php', <<<'PHPFILE'
<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('phase5:base-status', function () {
    $this->info('D2D Phase 5 public Laravel base is running.');
});
PHPFILE
);

$write('public/index.php', <<<'PHPFILE'
<?php

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Illuminate\Http\Request::capture());
PHPFILE
);

$write('public/.htaccess', <<<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS
);

$write('resources/views/welcome.blade.php', <<<'BLADE'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Dare To Dream — Phase 5 Preview</title>
    <style>
        body{margin:0;background:#0b0b0b;color:#fff;font-family:Arial,sans-serif;display:grid;place-items:center;min-height:100vh}.box{max-width:760px;padding:48px}.tag{color:#f4af00;font-weight:700;letter-spacing:.08em;text-transform:uppercase}h1{font-size:42px;margin:10px 0 12px}.muted{color:#bbb;line-height:1.6}
    </style>
</head>
<body>
<div class="box">
    <div class="tag">Dare To Dream</div>
    <h1>Phase 5 Laravel base is running.</h1>
    <p class="muted">This is only the protected preview foundation. The locked V11 frontend will replace this screen during Phase 5A.1.</p>
</div>
</body>
</html>
BLADE
);

$write('config/app.php', <<<'PHPFILE'
<?php

return [
    'name' => env('APP_NAME', 'Dare To Dream'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://preview.dares2dream.com'),
    'timezone' => 'UTC',
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file')],
];
PHPFILE
);

$write('config/database.php', <<<'PHPFILE'
<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
PHPFILE
);

$write('config/cache.php', <<<'PHPFILE'
<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'database' => ['driver' => 'database', 'connection' => env('DB_CACHE_CONNECTION'), 'table' => env('DB_CACHE_TABLE', 'cache'), 'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'), 'lock_table' => env('DB_CACHE_LOCK_TABLE')],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),
];
PHPFILE
);

$write('config/session.php', <<<'PHPFILE'
<?php

use Illuminate\Support\Str;

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug((string) env('APP_NAME', 'laravel')).'-session'),
    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
PHPFILE
);

$write('config/filesystems.php', <<<'PHPFILE'
<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => true, 'throw' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
PHPFILE
);

$write('config/logging.php', <<<'PHPFILE'
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => ['channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'), 'trace' => env('LOG_DEPRECATIONS_TRACE', false)],
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => explode(',', (string) env('LOG_STACK', 'single')), 'ignore_exceptions' => false],
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'replace_placeholders' => true],
        'stderr' => ['driver' => 'monolog', 'level' => env('LOG_LEVEL', 'debug'), 'handler' => StreamHandler::class, 'handler_with' => ['stream' => 'php://stderr'], 'processors' => [PsrLogMessageProcessor::class]],
        'syslog' => ['driver' => 'syslog', 'level' => env('LOG_LEVEL', 'debug'), 'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER), 'replace_placeholders' => true],
        'errorlog' => ['driver' => 'errorlog', 'level' => env('LOG_LEVEL', 'debug'), 'replace_placeholders' => true],
        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
        'emergency' => ['path' => storage_path('logs/laravel.log')],
    ],
];
PHPFILE
);

$write('config/view.php', <<<'PHPFILE'
<?php

return [
    'paths' => [resource_path('views')],
    'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views')) ?: storage_path('framework/views')),
];
PHPFILE
);

$write('config/queue.php', <<<'PHPFILE'
<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'connection' => env('DB_QUEUE_CONNECTION'), 'table' => env('DB_QUEUE_TABLE', 'jobs'), 'queue' => env('DB_QUEUE', 'default'), 'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90), 'after_commit' => false],
    ],
    'failed' => ['driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'), 'database' => env('DB_CONNECTION', 'mysql'), 'table' => 'failed_jobs'],
];
PHPFILE
);

$write('config/mail.php', <<<'PHPFILE'
<?php

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'hello@dares2dream.com'), 'name' => env('MAIL_FROM_NAME', 'Dare To Dream')],
];
PHPFILE
);

if (!is_file($root.'/.env')) {
    $crmEnv = dirname($root).'/crm.dares2dream.com/.env';
    $dbLines = [];
    if (is_file($crmEnv)) {
        $allowed = ['DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_SOCKET'];
        foreach (file($crmEnv, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key] = explode('=', $line, 2);
            $key = trim($key);
            if (in_array($key, $allowed, true)) {
                $dbLines[$key] = $line;
            }
        }
    }

    $key = 'base64:'.base64_encode(random_bytes(32));
    $env = [
        'APP_NAME="Dare To Dream"',
        'APP_ENV=production',
        'APP_KEY='.$key,
        'APP_DEBUG=true',
        'APP_URL=https://preview.dares2dream.com',
        '',
        'APP_LOCALE=en',
        'APP_FALLBACK_LOCALE=en',
        '',
        'LOG_CHANNEL=stack',
        'LOG_LEVEL=debug',
        '',
        $dbLines['DB_CONNECTION'] ?? 'DB_CONNECTION=mysql',
        $dbLines['DB_HOST'] ?? 'DB_HOST=127.0.0.1',
        $dbLines['DB_PORT'] ?? 'DB_PORT=3306',
        $dbLines['DB_DATABASE'] ?? 'DB_DATABASE=',
        $dbLines['DB_USERNAME'] ?? 'DB_USERNAME=',
        $dbLines['DB_PASSWORD'] ?? 'DB_PASSWORD=',
    ];
    if (isset($dbLines['DB_SOCKET'])) {
        $env[] = $dbLines['DB_SOCKET'];
    }
    $env = array_merge($env, [
        '',
        'SESSION_DRIVER=file',
        'SESSION_LIFETIME=120',
        'SESSION_ENCRYPT=false',
        'SESSION_PATH=/',
        'SESSION_DOMAIN=null',
        'SESSION_SECURE_COOKIE=true',
        'SESSION_COOKIE=d2d_preview_session',
        '',
        'CACHE_STORE=file',
        'FILESYSTEM_DISK=local',
        'QUEUE_CONNECTION=sync',
        'MAIL_MAILER=log',
        '',
    ]);
    file_put_contents($root.'/.env', implode(PHP_EOL, $env));
    @chmod($root.'/.env', 0600);
    echo "WRITE .env (new public app key; DB values copied from CRM only when available)\n";
} else {
    echo "KEEP  .env\n";
}

@chmod($root.'/bootstrap/cache', 0775);
@chmod($root.'/storage', 0775);

echo "\nD2D Phase 5 Laravel base created successfully.\n";
echo "Detected Laravel framework: {$version}\n";
echo "Root: {$root}\n";
echo "No database migrations were run. CRM, Portal and WordPress were not modified.\n\n";
echo "NEXT:\n";
echo "  php artisan --version\n";
echo "  php artisan phase5:base-status\n";
echo "  php install-phase5a-staging.php\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan phase5:doctor\n";
