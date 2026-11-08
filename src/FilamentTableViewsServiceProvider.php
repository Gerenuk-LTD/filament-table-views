<?php

namespace Gerenuk\FilamentTableViews;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTableViewsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-table-views';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_table_views_table');
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-table-views', __DIR__ . '/../resources/dist/filament-table-views.css'),
        ], 'gerenuk/filament-table-views');
    }
}
