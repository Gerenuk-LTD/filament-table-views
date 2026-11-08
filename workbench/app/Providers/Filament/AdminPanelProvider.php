<?php

namespace Workbench\App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;
use Workbench\App\Filament\Resources\OrderResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->resources([
                OrderResource::class,
            ])
            ->plugin(
                FilamentTableViewsPlugin::make()
                    ->globalFavorites(fn ($user): bool => $user->name === 'Admin')
                    ->userViews(fn ($user): bool => $user->name === 'Admin'),
            );
    }
}
