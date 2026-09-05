<?php

namespace App\Http\Controllers\Crm\Phase44;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PopupPreviewController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = collect();
        $columns = [];

        if (Schema::hasTable('popup_campaigns')) {
            $columns = Schema::getColumnListing('popup_campaigns');
            $query = DB::table('popup_campaigns');

            if ($request->filled('q')) {
                $q = trim((string) $request->q);
                $query->where(function ($sub) use ($q, $columns) {
                    foreach (['name','campaign_name','title','headline'] as $field) {
                        if (in_array($field, $columns, true)) {
                            $sub->orWhere($field, 'like', '%'.$q.'%');
                        }
                    }
                });
            }

            $campaigns = $query->orderByDesc('id')->limit(100)->get()->map(fn ($row) => $this->normalize((array) $row));
        }

        return view('crm.phase4_4.popup-preview.index', [
            'campaigns' => $campaigns,
            'tableReady' => Schema::hasTable('popup_campaigns'),
        ]);
    }

    public function show(int $id)
    {
        abort_unless(Schema::hasTable('popup_campaigns'), 404, 'Popup campaigns table is unavailable.');
        $row = DB::table('popup_campaigns')->where('id', $id)->first();
        abort_unless($row, 404, 'Popup campaign not found.');

        return view('crm.phase4_4.popup-preview.show', [
            'popup' => $this->normalize((array) $row),
            'raw' => (array) $row,
        ]);
    }

    private function normalize(array $row): array
    {
        $pick = function (array $keys, $default = null) use ($row) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    return $row[$key];
                }
            }
            return $default;
        };

        $image = (string) $pick(['image_url','image','image_path','media_url','cover_image','background_image'], '');
        if ($image !== '' && !Str::startsWith($image, ['http://','https://','/'])) {
            $image = Storage::disk('public')->url($image);
        }

        $status = $pick(['status'], null);
        if ($status === null && array_key_exists('is_active', $row)) {
            $status = $row['is_active'] ? 'active' : 'paused';
        }
        if ($status === null && array_key_exists('active', $row)) {
            $status = $row['active'] ? 'active' : 'paused';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) $pick(['name','campaign_name','title','headline'], 'Popup #'.($row['id'] ?? '')),
            'headline' => (string) $pick(['headline','title','name','campaign_name'], 'Your D2D opportunity starts here'),
            'subheadline' => (string) $pick(['subheadline','subtitle'], ''),
            'body' => (string) $pick(['body','message','content','description','text'], 'Add your popup message in Popup Manager to preview it here.'),
            'cta_label' => (string) $pick(['cta_label','cta_text','button_text','button_label','action_label'], 'Learn More'),
            'cta_url' => (string) $pick(['cta_url','button_url','target_url','url','link_url'], '#'),
            'image' => $image,
            'status' => (string) ($status ?: 'draft'),
            'trigger' => (string) $pick(['trigger_type','trigger','display_trigger'], 'manual preview'),
            'delay' => $pick(['delay_seconds','delay','time_delay'], null),
            'scroll' => $pick(['scroll_percent','scroll_percentage'], null),
            'frequency' => (string) $pick(['frequency','frequency_cap'], ''),
            'device' => (string) $pick(['device_target','devices','device'], 'desktop + mobile'),
            'style' => (string) $pick(['template','layout','design_style','popup_style'], 'center modal'),
        ];
    }
}
