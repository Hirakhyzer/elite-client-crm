<?php

namespace App\Http\Controllers;

use App\Services\PublicContentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PublicSiteController extends Controller
{
    public function home(PublicContentRepository $repo)
    {
        $v11 = public_path('v11/index.html');

        if (File::isFile($v11)) {
            $html = File::get($v11);
            $html = $this->prepareV11Html($html);

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        return view('phase5-home', ['counts' => $repo->tableCounts()]);
    }

    public function v11Asset(Request $request, string $path = '')
    {
        $base = realpath(public_path('v11'));
        $file = realpath(public_path('v11/'.$path));

        abort_unless(
            $base && $file && str_starts_with($file, $base) && is_file($file),
            404
        );

        return response()->file($file);
    }

    private function prepareV11Html(string $html): string
    {
        // The locked V11 build uses relative asset paths. Because Laravel serves
        // its index.html at '/', add a base URL so styles.css, app.js, assets/*,
        // hero media and favicon resolve from /v11/ without changing V11 itself.
        if (! preg_match('/<base\s/i', $html)) {
            $html = preg_replace(
                '/<head(\s[^>]*)?>/i',
                '$0'."\n".'<base href="/v11/">',
                $html,
                1
            ) ?? $html;
        }

        // Also support any root-relative references that may exist in the
        // exported V11 HTML while leaving external URLs and normal site links alone.
        $rootFiles = [
            'styles.css',
            'app.js',
            'hero-background.mp4',
            'hero-video-base.jpg',
            'hero-video-base.webp',
        ];

        foreach ($rootFiles as $file) {
            $html = str_replace(
                ['="/'.$file.'"', "='/".$file."'"],
                ['="/v11/'.$file.'"', "='/v11/".$file."'"],
                $html
            );
        }

        $html = str_replace(
            ['="/assets/', "='/assets/"],
            ['="/v11/assets/', "='/v11/assets/"],
            $html
        );

        return $html;
    }
}
