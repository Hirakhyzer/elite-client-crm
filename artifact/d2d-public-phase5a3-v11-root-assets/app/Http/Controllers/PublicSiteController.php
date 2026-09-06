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
            return response(File::get($v11), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        return view('phase5-home', ['counts' => $repo->tableCounts()]);
    }

    public function v11Asset(Request $request, string $path = '')
    {
        $base = realpath(public_path('v11'));
        $file = realpath(public_path('v11/' . $path));

        abort_unless(
            $base &&
            $file &&
            str_starts_with($file, $base) &&
            is_file($file),
            404
        );

        return response()->file($file);
    }
}
