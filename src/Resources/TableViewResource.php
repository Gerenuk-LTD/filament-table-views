<?php

namespace Gerenuk\FilamentTableViews\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;
use Gerenuk\FilamentTableViews\Models\TableView;
use Gerenuk\FilamentTableViews\Resources\TableViewResource\Pages\ManageTableViews;
use Gerenuk\FilamentTableViews\Support\TableViewForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class TableViewResource extends Resource
{
    protected static ?string $model = TableView::class;

    protected static bool $isScopedToTenant = false;

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedFunnel;
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return FilamentTableViewsPlugin::get()->getUserViewsNavigationGroup();
    }

    public static function getModelLabel(): string
    {
        return __('filament-table-views::table-views.user_views.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-table-views::table-views.user_views.plural_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    /**
     * @return Builder<TableView>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<TableView> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->forTenant(Filament::getTenant())
            ->with('user')
            ->withCount('favoritedBy');
    }

    public static function canAccess(): bool
    {
        return static::canManage();
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDeleteAny(): bool
    {
        return static::canManage();
    }

    public static function canReorder(): bool
    {
        return static::canManage();
    }

    public static function table(Table $table): Table
    {
        $canManageGlobalFavorites = FilamentTableViewsPlugin::get()->canManageGlobalFavorites(Filament::auth()->user());

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-table-views::table-views.user_views.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state, TableView $record): HtmlString => new HtmlString(
                        '<span class="fi-tv-option">' . $record->getIconHtml()?->toHtml() . e($state) . '</span>'
                    )),
                TextColumn::make('user_id')
                    ->label(__('filament-table-views::table-views.user_views.columns.owner'))
                    ->formatStateUsing(fn ($state, TableView $record): string => $record->user ? Filament::getUserName($record->user) : '—'),
                TextColumn::make('resource')
                    ->label(__('filament-table-views::table-views.user_views.columns.resource'))
                    ->formatStateUsing(fn (string $state): string => static::resourceLabel($state)),
                IconColumn::make('is_public')
                    ->label(__('filament-table-views::table-views.user_views.columns.is_public'))
                    ->boolean(),
                IconColumn::make('is_global_favorite')
                    ->label(__('filament-table-views::table-views.user_views.columns.is_global_favorite'))
                    ->boolean(),
                TextColumn::make('favorited_by_count')
                    ->label(__('filament-table-views::table-views.user_views.columns.favorited_by_count'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('filament-table-views::table-views.user_views.columns.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('resource')
                    ->label(__('filament-table-views::table-views.user_views.columns.resource'))
                    ->options(fn (): array => static::getEloquentQuery()
                        ->reorder()
                        ->distinct()
                        ->pluck('resource')
                        ->mapWithKeys(fn (string $resource): array => [$resource => static::resourceLabel($resource)])
                        ->all()),
                SelectFilter::make('user_id')
                    ->label(__('filament-table-views::table-views.user_views.columns.owner'))
                    ->options(function (): array {
                        $userModel = TableView::getUserModel();

                        $ids = static::getEloquentQuery()->reorder()->distinct()->pluck('user_id');

                        return $userModel::query()
                            ->whereKey($ids)
                            ->get()
                            ->mapWithKeys(fn (Model $user): array => [$user->getKey() => Filament::getUserName($user)])
                            ->all();
                    }),
                TernaryFilter::make('is_public')
                    ->label(__('filament-table-views::table-views.user_views.columns.is_public')),
                TernaryFilter::make('is_global_favorite')
                    ->label(__('filament-table-views::table-views.user_views.columns.is_global_favorite')),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth(Width::Medium)
                    ->schema(TableViewForm::components($canManageGlobalFavorites)),
                Action::make('toggleGlobalFavorite')
                    ->iconButton()
                    ->icon(fn (TableView $record): Heroicon => $record->is_global_favorite ? Heroicon::Star : Heroicon::OutlinedStar)
                    ->color(fn (TableView $record): string => $record->is_global_favorite ? 'warning' : 'gray')
                    ->tooltip(fn (TableView $record): string => $record->is_global_favorite
                        ? __('filament-table-views::table-views.user_views.toggle_global_favorite.remove')
                        : __('filament-table-views::table-views.user_views.toggle_global_favorite.add'))
                    ->visible($canManageGlobalFavorites)
                    ->authorize(fn (): bool => static::canManage() && FilamentTableViewsPlugin::get()->canManageGlobalFavorites(Filament::auth()->user()))
                    ->action(function (TableView $record): void {
                        $record->update(['is_global_favorite' => ! $record->is_global_favorite]);

                        Notification::make()
                            ->success()
                            ->title(__('filament-table-views::table-views.user_views.toggle_global_favorite.notification'))
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order', fn (ManageTableViews $livewire): bool => $livewire->activeTab === 'global')
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('name'));
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageTableViews::route('/'),
        ];
    }

    protected static function canManage(): bool
    {
        return FilamentTableViewsPlugin::get()->canManageUserViews(Filament::auth()->user());
    }

    protected static function resourceLabel(string $resource): string
    {
        if (class_exists($resource) && is_subclass_of($resource, Resource::class)) {
            return (string) str($resource::getPluralModelLabel())->title();
        }

        return class_basename($resource);
    }
}
