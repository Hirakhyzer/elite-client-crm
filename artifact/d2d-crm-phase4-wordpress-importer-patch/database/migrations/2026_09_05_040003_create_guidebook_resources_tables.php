<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('guidebook_resources')) {
            Schema::create('guidebook_resources', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('resource_type', 40)->default('guidebook');
                $table->text('short_description')->nullable();
                $table->longText('description')->nullable();
                $table->string('cover_image')->nullable();
                $table->string('author_name')->default('Team D2D');
                $table->string('access_level', 40)->default('public');
                $table->string('status', 24)->default('draft');
                $table->boolean('featured')->default(false);
                $table->string('seo_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('canonical_url')->nullable();
                $table->string('og_image')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'featured']);
                $table->index('resource_type');
            });
        }

        if (! Schema::hasTable('guidebook_resource_versions')) {
            Schema::create('guidebook_resource_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guidebook_resource_id')->constrained('guidebook_resources')->cascadeOnDelete();
                $table->string('version_label', 40);
                $table->string('file_path');
                $table->string('original_filename');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->text('release_notes')->nullable();
                $table->date('released_at')->nullable();
                $table->boolean('is_current')->default(false);
                $table->timestamps();
                $table->unique(['guidebook_resource_id', 'version_label'], 'guidebook_resource_version_unique');
                $table->index(['guidebook_resource_id', 'is_current'], 'guidebook_resource_current_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guidebook_resource_versions');
        Schema::dropIfExists('guidebook_resources');
    }
};
