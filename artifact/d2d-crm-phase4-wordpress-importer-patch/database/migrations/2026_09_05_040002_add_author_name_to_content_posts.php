<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('content_posts') && ! Schema::hasColumn('content_posts', 'author_name')) {
            Schema::table('content_posts', function (Blueprint $table) {
                $table->string('author_name')->nullable()->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('content_posts') && Schema::hasColumn('content_posts', 'author_name')) {
            Schema::table('content_posts', function (Blueprint $table) {
                $table->dropColumn('author_name');
            });
        }
    }
};
