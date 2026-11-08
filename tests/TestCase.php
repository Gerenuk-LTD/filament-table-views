<?php

namespace Gerenuk\FilamentTableViews\Tests;

use Gerenuk\FilamentTableViews\FilamentTableViewsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\User;
use Workbench\App\Providers\Filament\AdminPanelProvider;

class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->foreignId('order_status_id')->constrained();
            $table->foreignUuid('user_id')->nullable()->constrained();
            $table->decimal('total', 10, 2);
            $table->integer('quantity');
            $table->boolean('is_paid')->default(false);
            $table->dateTime('placed_at')->nullable();
            $table->string('internal_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        (include __DIR__ . '/../database/migrations/create_table_views_table.php')->up();
    }

    protected function getPackageProviders($app)
    {
        return [
            FilamentTableViewsServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        config()->set('auth.providers.users.model', User::class);
    }
}
