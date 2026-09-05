<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidebookResource extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'resource_type',
        'short_description',
        'description',
        'cover_image',
        'author_name',
        'access_level',
        'status',
        'featured',
        'seo_title',
        'meta_description',
        'canonical_url',
        'og_image',
        'published_at',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(GuidebookResourceVersion::class)->orderByDesc('is_current')->orderByDesc('released_at')->orderByDesc('id');
    }

    public function currentVersion()
    {
        return $this->hasOne(GuidebookResourceVersion::class)->where('is_current', true)->latestOfMany();
    }
}
