<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidebookResourceVersion extends Model
{
    protected $fillable = [
        'guidebook_resource_id',
        'version_label',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'release_notes',
        'released_at',
        'is_current',
    ];

    protected $casts = [
        'released_at' => 'date',
        'is_current' => 'boolean',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(GuidebookResource::class, 'guidebook_resource_id');
    }
}
