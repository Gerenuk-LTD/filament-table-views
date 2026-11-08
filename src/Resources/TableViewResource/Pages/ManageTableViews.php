<?php

namespace Gerenuk\FilamentTableViews\Resources\TableViewResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Gerenuk\FilamentTableViews\Models\TableView;
use Gerenuk\FilamentTableViews\Resources\TableViewResource;
use Illuminate\Database\Eloquent\Builder;

class ManageTableViews extends ManageRecords
{
    protected static string $resource = TableViewResource::class;

    /**
     * @return array<string | int, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('filament-table-views::table-views.user_views.tabs.all')),
            'global' => Tab::make(__('filament-table-views::table-views.user_views.tabs.global'))
                ->modifyQueryUsing(fn (Builder $query): Builder => static::onlyGlobalFavorites($query)),
        ];
    }

    /**
     * @param  Builder<TableView>  $query
     * @return Builder<TableView>
     */
    protected static function onlyGlobalFavorites(Builder $query): Builder
    {
        return $query->where('is_global_favorite', true);
    }
}
