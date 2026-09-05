<?php

namespace App\Http\Controllers\Crm\Phase4;

use App\Http\Controllers\Controller;
use App\Services\Phase4\WordPressPostImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class WordPressImporterController extends Controller
{
    private function sourceDir(): string
    {
        return storage_path('app/phase4-import');
    }

    private function sqlPath(): string
    {
        return $this->sourceDir().DIRECTORY_SEPARATOR.'legacy.sql';
    }

    private function zipPath(): string
    {
        return $this->sourceDir().DIRECTORY_SEPARATOR.'uploads.zip';
    }

    private function reportPath(): string
    {
        return $this->sourceDir().DIRECTORY_SEPARATOR.'dry-run.json';
    }

    public function index()
    {
        $report = null;
        if (is_file($this->reportPath())) {
            $decoded = json_decode((string) file_get_contents($this->reportPath()), true);
            if (is_array($decoded)) {
                $report = $decoded;
            }
        }

        return view('crm.phase4.wordpress.index', [
            'sqlReady' => is_file($this->sqlPath()),
            'zipReady' => is_file($this->zipPath()),
            'sqlSize' => is_file($this->sqlPath()) ? filesize($this->sqlPath()) : null,
            'zipSize' => is_file($this->zipPath()) ? filesize($this->zipPath()) : null,
            'report' => $report,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'sql_file' => ['nullable', 'file'],
            'uploads_zip' => ['nullable', 'file'],
        ]);

        File::ensureDirectoryExists($this->sourceDir(), 0775, true);

        if ($request->hasFile('sql_file')) {
            $file = $request->file('sql_file');
            abort_unless(strtolower($file->getClientOriginalExtension()) === 'sql', 422, 'The database source must be a .sql file.');
            $file->move($this->sourceDir(), 'legacy.sql');
            @unlink($this->reportPath());
        }

        if ($request->hasFile('uploads_zip')) {
            $file = $request->file('uploads_zip');
            abort_unless(strtolower($file->getClientOriginalExtension()) === 'zip', 422, 'The media source must be uploads.zip.');
            $file->move($this->sourceDir(), 'uploads.zip');
            @unlink($this->reportPath());
        }

        return redirect()->route('crm.phase4.wordpress.index')->with('success', 'Migration source files staged safely outside the public web root.');
    }

    public function dryRun(WordPressPostImporter $importer)
    {
        abort_unless(is_file($this->sqlPath()), 422, 'Upload the WordPress SQL export first.');
        @set_time_limit(0);

        $report = $importer->analyze(
            $this->sqlPath(),
            is_file($this->zipPath()) ? $this->zipPath() : null,
            'd2d_'
        );

        File::ensureDirectoryExists($this->sourceDir(), 0775, true);
        file_put_contents($this->reportPath(), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return redirect()->route('crm.phase4.wordpress.index')->with('success', 'Dry Run completed. Nothing was imported. Review the report below.');
    }

    public function import(Request $request, WordPressPostImporter $importer)
    {
        $request->validate(['confirm_import' => ['accepted']]);
        abort_unless(is_file($this->sqlPath()), 422, 'SQL source is missing.');
        abort_unless(is_file($this->reportPath()), 422, 'Run Dry Run first.');
        @set_time_limit(0);

        $results = $importer->importReady(
            $this->sqlPath(),
            is_file($this->zipPath()) ? $this->zipPath() : null,
            optional($request->user())->id,
            'd2d_'
        );

        @unlink($this->reportPath());

        return redirect()->route('crm.phase4.wordpress.index')
            ->with('success', "Import finished: {$results['imported']} imported, {$results['skipped']} skipped, {$results['failed']} failed.")
            ->with('phase4_import_results', $results);
    }

    public function clear()
    {
        foreach ([$this->sqlPath(), $this->zipPath(), $this->reportPath()] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return redirect()->route('crm.phase4.wordpress.index')->with('success', 'Staged migration source files were removed. Imported CRM posts were not changed.');
    }
}
