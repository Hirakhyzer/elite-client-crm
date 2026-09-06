<?php

namespace App\Http\Controllers\Phase5;

use App\Http\Controllers\Controller;
use App\Services\Phase5\PublicContentBridge;
use Illuminate\Http\Request;

class PublicContentController extends Controller
{
    public function blog(Request $request, PublicContentBridge $bridge)
    {
        return view('phase5b.blog-index', [
            'items' => $bridge->blogs(30, $request->string('q')->toString()),
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function blogShow(string $slug, PublicContentBridge $bridge)
    {
        $item = $bridge->blogBySlug($slug);
        abort_unless($item, 404);
        return view('phase5b.detail', compact('item'));
    }

    public function universities(Request $request, PublicContentBridge $bridge)
    {
        return view('phase5b.universities-index', [
            'items' => $bridge->universities(30, $request->string('q')->toString()),
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function universityShow(string $slug, PublicContentBridge $bridge)
    {
        $item = $bridge->universityBySlug($slug);
        abort_unless($item, 404);
        return view('phase5b.detail', compact('item'));
    }

    public function opportunities(Request $request, PublicContentBridge $bridge)
    {
        return view('phase5b.opportunities-index', [
            'items' => $bridge->opportunities(40, $request->string('q')->toString(), $request->string('type')->toString() ?: null),
            'q' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
        ]);
    }

    public function opportunityShow(string $slug, PublicContentBridge $bridge)
    {
        $item = $bridge->opportunityBySlug($slug);
        abort_unless($item, 404);
        return view('phase5b.detail', compact('item'));
    }

    public function resources(Request $request, PublicContentBridge $bridge)
    {
        return view('phase5b.resources-index', [
            'items' => $bridge->guidebooks(30, $request->string('q')->toString()),
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function apiHome(PublicContentBridge $bridge)
    {
        return response()->json(['ok' => true, 'data' => $bridge->homeFeed()]);
    }

    public function apiBlog(Request $request, PublicContentBridge $bridge)
    {
        return response()->json(['ok' => true, 'data' => $bridge->blogs(50, $request->string('q')->toString())->values()]);
    }

    public function apiBlogShow(string $slug, PublicContentBridge $bridge)
    {
        $item = $bridge->blogBySlug($slug);
        abort_unless($item, 404);
        return response()->json(['ok' => true, 'data' => $item]);
    }

    public function apiUniversities(Request $request, PublicContentBridge $bridge)
    {
        return response()->json(['ok' => true, 'data' => $bridge->universities(50, $request->string('q')->toString())->values()]);
    }

    public function apiOpportunities(Request $request, PublicContentBridge $bridge)
    {
        return response()->json(['ok' => true, 'data' => $bridge->opportunities(50, $request->string('q')->toString(), $request->string('type')->toString() ?: null)->values()]);
    }

    public function apiGuidebooks(Request $request, PublicContentBridge $bridge)
    {
        return response()->json(['ok' => true, 'data' => $bridge->guidebooks(50, $request->string('q')->toString())->values()]);
    }

    public function media(string $path, PublicContentBridge $bridge)
    {
        $file = $bridge->crmPublicMediaPath($path);
        abort_unless($file, 404);
        return response()->file($file, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
