<?php

use Filament\Facades\Filament;
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;
use Gerenuk\FilamentTableViews\FilamentTableViewsServiceProvider;
use Illuminate\Support\ServiceProvider;

it('registers on the panel and publishes its migration instead of running it from vendor', function () {
    $published = ServiceProvider::pathsToPublish(FilamentTableViewsServiceProvider::class, 'filament-table-views-migrations');

    expect(Filament::getPanel('admin')->getPlugin('filament-table-views'))->toBeInstanceOf(FilamentTableViewsPlugin::class)
        ->and($published)->toHaveCount(1)
        ->and(array_key_first($published))->toEndWith('create_table_views_table.php')
        ->and(app('migrator')->paths())->each->not->toContain('filament-table-views');
});
