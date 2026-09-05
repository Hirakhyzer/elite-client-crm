<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_type', 80)->index();
                $table->string('resource_type', 50)->nullable()->index();
                $table->unsignedBigInteger('resource_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('session_id', 128)->nullable()->index();
                $table->string('url_path', 2048)->nullable();
                $table->text('referrer')->nullable();
                $table->string('utm_source', 255)->nullable()->index();
                $table->string('utm_medium', 255)->nullable();
                $table->string('utm_campaign', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamps();

                $table->index(['resource_type', 'resource_id', 'event_type'], 'analytics_resource_event_idx');
                $table->index(['occurred_at', 'event_type'], 'analytics_date_event_idx');
            });
        }

        if (! Schema::hasTable('analytics_daily_metrics')) {
            Schema::create('analytics_daily_metrics', function (Blueprint $table) {
                $table->id();
                $table->date('metric_date')->index();
                $table->string('resource_type', 50)->nullable()->index();
                $table->unsignedBigInteger('resource_id')->nullable()->index();
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedBigInteger('unique_sessions')->default(0);
                $table->unsignedBigInteger('saves')->default(0);
                $table->unsignedBigInteger('shares')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->unsignedBigInteger('downloads')->default(0);
                $table->unsignedBigInteger('tool_starts')->default(0);
                $table->unsignedBigInteger('tool_completions')->default(0);
                $table->timestamps();

                $table->unique(['metric_date', 'resource_type', 'resource_id'], 'analytics_daily_resource_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
        Schema::dropIfExists('analytics_events');
    }
};
