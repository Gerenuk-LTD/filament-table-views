<?php

use Gerenuk\FilamentTableViews\Models\TableView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_views', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TableView::getUserModel(), 'user_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_type')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('resource')->index();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->json('state');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_global_favorite')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['tenant_type', 'tenant_id']);
            $table->timestamps();
        });

        Schema::create('table_view_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_view_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TableView::getUserModel(), 'user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['table_view_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_view_favorites');
        Schema::dropIfExists('table_views');
    }
};
