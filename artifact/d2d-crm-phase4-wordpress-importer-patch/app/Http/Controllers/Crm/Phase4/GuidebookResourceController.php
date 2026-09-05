<?php

namespace App\Http\Controllers\Crm\Phase4;

use App\Http\Controllers\Controller;
use App\Models\GuidebookResource;
use App\Models\GuidebookResourceVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuidebookResourceController extends Controller
{
    private array $types = ['guidebook', 'ebook', 'checklist', 'template', 'worksheet', 'toolkit', 'other'];
    private array $accessLevels = ['public', 'pro', 'ambassador', 'pro_ambassador'];
    private array $statuses = ['draft', 'published', 'archived'];

    public function index(Request $request)
    {
        $query = GuidebookResource::query()->with('currentVersion');

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')->orWhere('slug', 'like', '%'.$q.'%');
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('resource_type', $request->type);
        }

        return view('crm.phase4.guidebooks.index', [
            'resources' => $query->latest()->paginate(20)->withQueryString(),
            'types' => $this->types,
            'statuses' => $this->statuses,
        ]);
    }

    public function create()
    {
        return view('crm.phase4.guidebooks.form', [
            'resource' => new GuidebookResource(['author_name' => 'Team D2D', 'access_level' => 'public', 'status' => 'draft']),
            'types' => $this->types,
            'accessLevels' => $this->accessLevels,
            'statuses' => $this->statuses,
            'editing' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedResource($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['featured'] = $request->boolean('featured');
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?: now()) : null;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = '/storage/'.$request->file('cover_image')->store('guidebooks/covers', 'public');
        }
        if ($request->hasFile('og_image')) {
            $data['og_image'] = '/storage/'.$request->file('og_image')->store('guidebooks/og', 'public');
        }

        $resource = GuidebookResource::create($data);

        if ($request->hasFile('initial_file')) {
            $this->saveVersion($request, $resource, true, 'initial_file');
        }

        return redirect()->route('crm.phase4.guidebooks.show', $resource)->with('success', 'Guidebook/resource created.');
    }

    public function show(GuidebookResource $resource)
    {
        $resource->load('versions');
        return view('crm.phase4.guidebooks.show', compact('resource'));
    }

    public function edit(GuidebookResource $resource)
    {
        return view('crm.phase4.guidebooks.form', [
            'resource' => $resource,
            'types' => $this->types,
            'accessLevels' => $this->accessLevels,
            'statuses' => $this->statuses,
            'editing' => true,
        ]);
    }

    public function update(Request $request, GuidebookResource $resource)
    {
        $data = $this->validatedResource($request, $resource->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['featured'] = $request->boolean('featured');
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?: $resource->published_at ?: now()) : null;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = '/storage/'.$request->file('cover_image')->store('guidebooks/covers', 'public');
        }
        if ($request->hasFile('og_image')) {
            $data['og_image'] = '/storage/'.$request->file('og_image')->store('guidebooks/og', 'public');
        }

        $resource->update($data);
        return redirect()->route('crm.phase4.guidebooks.show', $resource)->with('success', 'Resource updated.');
    }

    public function archive(GuidebookResource $resource)
    {
        $resource->update(['status' => 'archived']);
        return redirect()->route('crm.phase4.guidebooks.index')->with('success', 'Resource archived safely. Files and version history were retained.');
    }

    public function addVersion(Request $request, GuidebookResource $resource)
    {
        $this->saveVersion($request, $resource, $request->boolean('is_current'));
        return redirect()->route('crm.phase4.guidebooks.show', $resource)->with('success', 'New resource version uploaded. Previous versions were retained.');
    }

    public function makeCurrent(GuidebookResource $resource, GuidebookResourceVersion $version)
    {
        abort_unless($version->guidebook_resource_id === $resource->id, 404);

        DB::transaction(function () use ($resource, $version) {
            $resource->versions()->update(['is_current' => false]);
            $version->update(['is_current' => true]);
        });

        return redirect()->route('crm.phase4.guidebooks.show', $resource)->with('success', 'Current version changed.');
    }

    public function download(GuidebookResource $resource, GuidebookResourceVersion $version)
    {
        abort_unless($version->guidebook_resource_id === $resource->id, 404);
        abort_unless(Storage::disk('public')->exists($version->file_path), 404);
        return Storage::disk('public')->download($version->file_path, $version->original_filename);
    }

    private function validatedResource(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('guidebook_resources', 'slug')->ignore($ignoreId)],
            'resource_type' => ['required', Rule::in($this->types)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'author_name' => ['required', 'string', 'max:255'],
            'access_level' => ['required', Rule::in($this->accessLevels)],
            'status' => ['required', Rule::in($this->statuses)],
            'published_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_image' => ['nullable', 'image', 'max:10240'],
            'initial_file' => ['nullable', 'file', 'max:102400'],
            'version_label' => ['nullable', 'string', 'max:40'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'released_at' => ['nullable', 'date'],
        ]);
    }

    private function saveVersion(Request $request, GuidebookResource $resource, bool $makeCurrent, string $fileField = 'resource_file'): GuidebookResourceVersion
    {
        $request->validate([
            $fileField => ['required', 'file', 'max:102400'],
            'version_label' => [
                'required', 'string', 'max:40',
                Rule::unique('guidebook_resource_versions', 'version_label')->where(fn ($q) => $q->where('guidebook_resource_id', $resource->id)),
            ],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'released_at' => ['nullable', 'date'],
        ]);

        $file = $request->file($fileField);
        $versionLabel = trim((string) $request->version_label);
        $safeVersion = Str::slug($versionLabel) ?: 'version';
        $storedPath = $file->storeAs(
            'guidebooks/'.$resource->id.'/'.$safeVersion,
            time().'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(),
            'public'
        );

        return DB::transaction(function () use ($resource, $request, $file, $storedPath, $versionLabel, $makeCurrent) {
            if ($makeCurrent || ! $resource->versions()->where('is_current', true)->exists()) {
                $resource->versions()->update(['is_current' => false]);
                $makeCurrent = true;
            }

            return $resource->versions()->create([
                'version_label' => $versionLabel,
                'file_path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'release_notes' => $request->release_notes,
                'released_at' => $request->released_at ?: now()->toDateString(),
                'is_current' => $makeCurrent,
            ]);
        });
    }
}
