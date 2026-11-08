<?php

namespace Gerenuk\FilamentTableViews\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Gerenuk\FilamentTableViews\Models\TableView;
use Gerenuk\FilamentTableViews\Support\TableViewForm;
use Illuminate\Support\Arr;

trait HasTableViewActions
{
    /**
     * @var array<int, ?TableView>
     */
    protected array $cachedTableViews = [];

    public function saveTableViewAction(): Action
    {
        return Action::make('saveTableView')
            ->label(__('filament-table-views::table-views.actions.save.label'))
            ->icon(Heroicon::OutlinedBookmark)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-table-views::table-views.actions.save.heading'))
            ->modalDescription(__('filament-table-views::table-views.actions.save.description'))
            ->modalSubmitActionLabel(__('filament-table-views::table-views.actions.save.submit'))
            ->schema([
                ...$this->getTableViewFormComponents(),
                Toggle::make('is_favorite')
                    ->label(__('filament-table-views::table-views.form.is_favorite'))
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $view = $this->createTableView(Arr::except($data, 'is_favorite'));

                if ($data['is_favorite'] ?? false) {
                    $this->toggleTableViewFavorite($view);
                }

                Notification::make()
                    ->success()
                    ->title(__('filament-table-views::table-views.actions.save.notification'))
                    ->send();
            });
    }

    public function updateTableViewAction(): Action
    {
        return Action::make('updateTableView')
            ->label(__('filament-table-views::table-views.actions.update.label'))
            ->tooltip(__('filament-table-views::table-views.actions.update.tooltip'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(function (): bool {
                $view = $this->getActiveTableView();

                return $view !== null && $view->isOwnedBy(Filament::auth()->user()) && $this->isTableViewModified($view);
            })
            ->action(function (): void {
                $this->getActiveTableView()?->update(['state' => $this->getTableViewState()]);

                Notification::make()
                    ->success()
                    ->title(__('filament-table-views::table-views.actions.update.notification'))
                    ->send();
            });
    }

    public function manageTableViewsAction(): Action
    {
        /** @var view-string $view */
        $view = 'filament-table-views::manager';

        return Action::make('manageTableViews')
            ->label(__('filament-table-views::table-views.actions.manage.label'))
            ->icon(Heroicon::OutlinedQueueList)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-table-views::table-views.actions.manage.heading'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-table-views::table-views.actions.manage.close'))
            ->registerModalActions([
                $this->makeApplyTableViewAction(),
                $this->makeToggleTableViewFavoriteAction(),
                $this->makeEditTableViewAction(),
                $this->makeDeleteTableViewAction(),
            ])
            ->modalContent(fn (Action $action) => view($view, [
                'action' => $action,
                'livewire' => $this,
                'views' => $this->getManagedTableViews(),
            ]));
    }

    protected function makeApplyTableViewAction(): Action
    {
        return Action::make('applyTableView')
            ->action(fn (array $arguments) => $this->applyTableView((int) ($arguments['view'] ?? 0)))
            ->cancelParentActions();
    }

    protected function makeToggleTableViewFavoriteAction(): Action
    {
        return Action::make('toggleTableViewFavorite')
            ->iconButton()
            ->icon(fn (array $arguments): Heroicon => $this->isFavoriteTableViewArgument($arguments) ? Heroicon::Star : Heroicon::OutlinedStar)
            ->color(fn (array $arguments): string => $this->isFavoriteTableViewArgument($arguments) ? 'warning' : 'gray')
            ->tooltip(fn (array $arguments): string => match (true) {
                $this->isGlobalFavoriteTableViewArgument($arguments) => __('filament-table-views::table-views.actions.favorite.global'),
                $this->isFavoriteTableViewArgument($arguments) => __('filament-table-views::table-views.actions.favorite.remove'),
                default => __('filament-table-views::table-views.actions.favorite.add'),
            })
            ->disabled(fn (array $arguments): bool => $this->isGlobalFavoriteTableViewArgument($arguments))
            ->action(function (array $arguments): void {
                $view = $this->resolveTableView($arguments);

                if ($view && (! $view->is_global_favorite)) {
                    $this->toggleTableViewFavorite($view);
                }
            });
    }

    protected function makeEditTableViewAction(): Action
    {
        return Action::make('editTableView')
            ->label(__('filament-table-views::table-views.actions.edit.label'))
            ->iconButton()
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (array $arguments): bool => $this->ownsTableView($arguments))
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-table-views::table-views.actions.edit.heading'))
            ->modalSubmitActionLabel(__('filament-table-views::table-views.actions.edit.submit'))
            ->fillForm(fn (array $arguments): array => $this->resolveTableView($arguments)?->only(['name', 'icon', 'color', 'is_public', 'is_global_favorite']) ?? [])
            ->schema($this->getTableViewFormComponents())
            ->action(function (array $arguments, array $data): void {
                $this->resolveTableView($arguments)?->update($data);
                $this->cachedActiveTableView = null;

                Notification::make()
                    ->success()
                    ->title(__('filament-table-views::table-views.actions.edit.notification'))
                    ->send();
            });
    }

    protected function makeDeleteTableViewAction(): Action
    {
        return Action::make('deleteTableView')
            ->label(__('filament-table-views::table-views.actions.delete.label'))
            ->iconButton()
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (array $arguments): bool => $this->ownsTableView($arguments))
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalHeading(fn (array $arguments): string => __('filament-table-views::table-views.actions.delete.heading', ['name' => $this->resolveTableView($arguments)?->name]))
            ->modalDescription(fn (array $arguments): string => $this->resolveTableView($arguments)?->is_public
                ? __('filament-table-views::table-views.actions.delete.description_public')
                : __('filament-table-views::table-views.actions.delete.description'))
            ->modalSubmitActionLabel(__('filament-table-views::table-views.actions.delete.submit'))
            ->action(function (array $arguments): void {
                $view = $this->resolveTableView($arguments);

                if ($view?->getKey() === $this->activeTableViewId) {
                    $this->activeTableViewId = null;
                }

                $view?->delete();
                $this->cachedActiveTableView = null;

                Notification::make()
                    ->success()
                    ->title(__('filament-table-views::table-views.actions.delete.notification'))
                    ->send();
            });
    }

    /**
     * @return array<Component|Field>
     */
    protected function getTableViewFormComponents(): array
    {
        return TableViewForm::components($this->canManageGlobalTableViewFavorites());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function resolveTableView(array $arguments): ?TableView
    {
        $id = $arguments['view'] ?? null;

        if (! is_numeric($id)) {
            return null;
        }

        return $this->cachedTableViews[(int) $id] ??= $this->getTableViewsQuery()->find((int) $id);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function ownsTableView(array $arguments): bool
    {
        return $this->resolveTableView($arguments)?->isOwnedBy(Filament::auth()->user()) ?? false;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function isGlobalFavoriteTableViewArgument(array $arguments): bool
    {
        $view = $this->resolveTableView($arguments);

        return $view !== null && $view->is_global_favorite;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function isFavoriteTableViewArgument(array $arguments): bool
    {
        $view = $this->resolveTableView($arguments);

        return $view !== null && ($view->is_global_favorite || $this->isFavoriteTableView($view));
    }
}
