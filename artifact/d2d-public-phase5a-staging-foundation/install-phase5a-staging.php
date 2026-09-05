<?php

declare(strict_types=1);

/**
 * D2D Phase 5A — Public Laravel Staging Foundation
 *
 * PURPOSE
 * - Prepare a SEPARATE public Laravel staging app for the locked V11 frontend.
 * - Read the existing shared D2D database without changing business data.
 * - Provide a Phase 5 doctor + read-only content contract for V11 integration.
 * - Refuse installation on crm.dares2dream.com or portal.dares2dream.com.
 *
 * NO migrations. NO destructive DB actions. NO live-domain cutover.
 */

$root = __DIR__;

if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Extract this patch into the ROOT of the SEPARATE public Laravel staging app.\n");
    exit(1);
}

function p5aWrite(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $content);
}

function p5aBackup(string $path): void
{
    if (is_file($path) && ! is_file($path.'.phase5a-backup')) {
        copy($path, $path.'.phase5a-backup');
    }
}

function p5aEnvValue(string $envPath, string $key): ?string
{
    if (! is_file($envPath)) return null;
    $raw = (string) file_get_contents($envPath);
    if (! preg_match('/^'.preg_quote($key, '/').'\s*=\s*(.*)$/m', $raw, $m)) return null;
    return trim(trim($m[1]), "\"'");
}

$appUrl = strtolower((string) (p5aEnvValue($root.'/.env', 'APP_URL') ?? ''));
if ($appUrl !== '' && (str_contains($appUrl, 'crm.dares2dream.com') || str_contains($appUrl, 'portal.dares2dream.com'))) {
    fwrite(STDERR, "REFUSED: Phase 5A must NOT be installed into CRM or Portal.\n");
    fwrite(STDERR, "Current APP_URL: {$appUrl}\n");
    fwrite(STDERR, "Use a separate public staging Laravel app/subdomain.\n");
    exit(2);
}

$service = <<<'PHP'
<?php

namespace App\Services\D2D;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicContentRepository
{
    public function diagnostics(): array
    {
        $tables = [
            'content_posts',
            'universities',
            'opportunities',
            'guidebook_resources',
            'guidebook_resource_versions',
            'seo_meta',
            'popup_campaigns',
            'banner_campaigns',
            'analytics_events',
            'analytics_daily_metrics',
        ];

        $result = [];
        foreach ($tables as $table) {
            $exists = Schema::hasTable($table);
            $result[$table] = [
                'exists' => $exists,
                'count' => $exists ? DB::table($table)->count() : null,
                'columns' => $exists ? Schema::getColumnListing($table) : [],
            ];
        }

        return $result;
    }

    public function blog(int $limit = 12): array
    {
        if (! Schema::hasTable('content_posts')) return [];
        $cols = Schema::getColumnListing('content_posts');
        $q = DB::table('content_posts');

        if (in_array('status', $cols, true)) $q->where('status', 'published');
        if (in_array('content_type', $cols, true)) $q->where('content_type', 'blog');
        elseif (in_array('type', $cols, true)) $q->where('type', 'blog');

        $this->orderPublished($q, $cols);
        return $q->limit(max(1, min($limit, 100)))->get()->map(fn ($r) => $this->normalize($r, 'blog'))->all();
    }

    public function blogBySlug(string $slug): ?array
    {
        if (! Schema::hasTable('content_posts')) return null;
        $cols = Schema::getColumnListing('content_posts');
        if (! in_array('slug', $cols, true)) return null;

        $q = DB::table('content_posts')->where('slug', $slug);
        if (in_array('status', $cols, true)) $q->where('status', 'published');
        if (in_array('content_type', $cols, true)) $q->where('content_type', 'blog');
        elseif (in_array('type', $cols, true)) $q->where('type', 'blog');

        $row = $q->first();
        return $row ? $this->normalize($row, 'blog') : null;
    }

    public function guidebooks(int $limit = 24): array
    {
        if (! Schema::hasTable('guidebook_resources')) return [];
        $cols = Schema::getColumnListing('guidebook_resources');
        $q = DB::table('guidebook_resources');
        if (in_array('status', $cols, true)) $q->where('status', 'published');
        if (in_array('access_level', $cols, true)) $q->where('access_level', 'public');
        $this->orderPublished($q, $cols);
        return $q->limit(max(1, min($limit, 100)))->get()->map(fn ($r) => $this->normalize($r, 'guidebook'))->all();
    }

    public function genericPublished(string $table, int $limit = 24): array
    {
        if (! Schema::hasTable($table)) return [];
        $cols = Schema::getColumnListing($table);
        $q = DB::table($table);
        if (in_array('status', $cols, true)) $q->where('status', 'published');
        elseif (in_array('is_published', $cols, true)) $q->where('is_published', true);
        $this->orderPublished($q, $cols);
        return $q->limit(max(1, min($limit, 100)))->get()->map(fn ($r) => $this->normalize($r, Str::singular($table)))->all();
    }

    private function orderPublished($q, array $cols): void
    {
        foreach (['published_at', 'created_at', 'updated_at', 'id'] as $col) {
            if (in_array($col, $cols, true)) {
                $q->orderByDesc($col);
                return;
            }
        }
    }

    private function normalize(object $row, string $kind): array
    {
        $r = (array) $row;
        $first = fn (array $keys, $default = null) => collect($keys)->first(fn ($k) => array_key_exists($k, $r) && $r[$k] !== null) ? $r[collect($keys)->first(fn ($k) => array_key_exists($k, $r) && $r[$k] !== null)] : $default;

        return [
            'kind' => $kind,
            'id' => $r['id'] ?? null,
            'title' => $first(['title', 'name']),
            'slug' => $r['slug'] ?? null,
            'excerpt' => $first(['excerpt', 'summary', 'short_description', 'description']),
            'content_html' => $first(['content_html', 'content', 'body', 'description']),
            'image' => $first(['featured_image', 'featured_image_path', 'cover_image', 'image', 'logo']),
            'author' => $first(['author_name', 'author']),
            'published_at' => $first(['published_at', 'created_at']),
            'status' => $r['status'] ?? null,
            'raw' => $r,
        ];
    }
}
PHP;

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\D2D;

use App\Http\Controllers\Controller;
use App\Services\D2D\PublicContentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Phase5PreviewController extends Controller
{
    public function __construct(private readonly PublicContentRepository $content) {}

    public function health(): JsonResponse
    {
        return response()->json([
            'phase' => '5A',
            'mode' => 'staging-read-only',
            'app_url' => config('app.url'),
            'database' => config('database.default'),
            'tables' => $this->content->diagnostics(),
            'live_cutover' => false,
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function blog(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->content->blog((int) $request->integer('limit', 12))])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function blogShow(string $slug): JsonResponse
    {
        $row = $this->content->blogBySlug($slug);
        abort_if(! $row, 404);
        return response()->json(['data' => $row])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function universities(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->content->genericPublished('universities', (int) $request->integer('limit', 24))])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function opportunities(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->content->genericPublished('opportunities', (int) $request->integer('limit', 24))])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function guidebooks(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->content->guidebooks((int) $request->integer('limit', 24))])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
PHP;

$routes = <<<'PHP'
<?php

use App\Http\Controllers\D2D\Phase5PreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('_phase5')->name('phase5.')->group(function () {
    Route::get('/health', [Phase5PreviewController::class, 'health'])->name('health');
    Route::get('/data/blog', [Phase5PreviewController::class, 'blog'])->name('blog');
    Route::get('/data/blog/{slug}', [Phase5PreviewController::class, 'blogShow'])->name('blog.show');
    Route::get('/data/universities', [Phase5PreviewController::class, 'universities'])->name('universities');
    Route::get('/data/opportunities', [Phase5PreviewController::class, 'opportunities'])->name('opportunities');
    Route::get('/data/guidebooks', [Phase5PreviewController::class, 'guidebooks'])->name('guidebooks');
});
PHP;

$command = <<<'PHP'
<?php

namespace App\Console\Commands;

use App\Services\D2D\PublicContentRepository;
use Illuminate\Console\Command;
use ZipArchive;

class Phase5Doctor extends Command
{
    protected $signature = 'phase5:doctor {--v11=storage/app/phase5/v11.zip : Path to the uploaded locked V11 ZIP}';
    protected $description = 'Verify the Phase 5 public staging data bridge and inspect the locked V11 ZIP without changing data.';

    public function handle(PublicContentRepository $repo): int
    {
        $this->info('D2D Phase 5A Doctor');
        $this->line('APP_URL: '.config('app.url'));
        $this->line('DB: '.config('database.default'));
        $this->newLine();

        foreach ($repo->diagnostics() as $table => $info) {
            $this->line(sprintf('%-30s %s%s', $table, $info['exists'] ? 'OK' : 'missing', $info['exists'] ? ' ('.$info['count'].')' : ''));
        }

        $this->newLine();
        $path = base_path((string) $this->option('v11'));
        if (! is_file($path)) {
            $this->warn('V11 ZIP not found at: '.$path);
            $this->line('Place the exact locked frontend ZIP there and run this command again.');
            return self::SUCCESS;
        }

        if (! class_exists(ZipArchive::class)) {
            $this->error('PHP ZipArchive is unavailable on this server.');
            return self::FAILURE;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $this->error('Could not open V11 ZIP.');
            return self::FAILURE;
        }

        $names = [];
        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $names[] = $name;
            $lower = strtolower($name);
            foreach (['package.json','vite.config.js','vite.config.ts','index.html','dist/index.html','build/index.html','resources/views','src/main','src/app'] as $needle) {
                if (str_contains($lower, strtolower($needle))) $candidates[] = $name;
            }
        }
        $zip->close();

        $this->info('V11 ZIP readable: '.count($names).' entries');
        $this->line('Integration candidates:');
        foreach (array_slice(array_values(array_unique($candidates)), 0, 80) as $candidate) {
            $this->line('  - '.$candidate);
        }

        $this->newLine();
        $this->info('Phase 5A staging foundation is ready. No live cutover has occurred.');
        return self::SUCCESS;
    }
}
PHP;

p5aWrite($root.'/app/Services/D2D/PublicContentRepository.php', $service);
p5aWrite($root.'/app/Http/Controllers/D2D/Phase5PreviewController.php', $controller);
p5aWrite($root.'/app/Console/Commands/Phase5Doctor.php', $command);
p5aWrite($root.'/routes/phase5a.php', $routes);

$web = $root.'/routes/web.php';
if (! is_file($web)) {
    fwrite(STDERR, "ERROR: routes/web.php not found.\n");
    exit(3);
}

$marker = 'D2D_PHASE5A_STAGING_ROUTES';
$current = (string) file_get_contents($web);
if (! str_contains($current, $marker)) {
    p5aBackup($web);
    file_put_contents($web, rtrim($current)."\n\n// {$marker}\nrequire __DIR__.'/phase5a.php';\n");
}

$storage = $root.'/storage/app/phase5';
if (! is_dir($storage)) mkdir($storage, 0775, true);

p5aWrite($storage.'/README.txt', "Place the exact locked V11 frontend ZIP here as v11.zip.\nThen run: php artisan phase5:doctor\n");

echo "\nD2D Phase 5A staging foundation installed.\n";
echo "NO migrations were run. NO data was written. NO live domain was changed.\n\n";
echo "Next:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan phase5:doctor\n";
echo "  php artisan route:list | grep _phase5\n\n";
echo "Health endpoint: /_phase5/health\n";
