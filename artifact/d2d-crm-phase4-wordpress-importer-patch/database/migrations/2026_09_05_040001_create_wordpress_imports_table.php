<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('wordpress_imports')) {
            return;
        }

        Schema::create('wordpress_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 32)->default('post');
            $table->unsignedBigInteger('source_id');
            $table->string('source_slug')->nullable();
            $table->string('source_status', 32)->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->string('destination_type', 64)->default('content_post');
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->string('result', 32)->default('imported');
            $table->longText('source_meta')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'wordpress_imports_source_unique');
            $table->index(['destination_type', 'destination_id'], 'wordpress_imports_destination_index');
            $table->index('source_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_imports');
    }
};
