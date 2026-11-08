<?php

namespace Gerenuk\FilamentTableViews\Concerns;

use Filament\Facades\Filament;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Filters\QueryBuilder;
use Filament\View\PanelsRenderHook;
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;
use Gerenuk\FilamentTableViews\Models\TableView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

trait HasTableViews
{
    use HasTableViewActions;

    #[Url(as: 'view')]
    public ?int $activeTableViewId = null;

    public string $tableViewSearch = '';

    /**
     * @var ?array<int>
     */
    protected ?array $cachedFavoriteTableViewIds = null;

    protected ?TableView $cachedActiveTableView = null;

    protected bool $isMountingTableViews = false;

    public function mountHasTableViews(): void
    {
        $this->isMountingTableViews = true;
    }

    /**
     * Filters, search and sort already survive a reload in the URL, so a view is only applied for a bare `?view=` link.
     */
    public function bootedHasTableViews(): void
    {
        if ((! $this->isMountingTableViews) || ($this->activeTableViewId === null)) {
            return;
        }

        $this->isMountingTableViews = false;

        $id = $this->activeTableViewId;
        $this->activeTableViewId = null;

        if (request()->hasAny(['filters', 'search', 'sort'])) {
            $this->activeTableViewId = $this->getTableViewsQuery()->whereKey($id)->exists() ? $id : null;

            return;
        }

        $this->applyTableView($id);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                $this->getTableViewsComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getTableViewsComponent(): View
    {
        /** @var view-string $view */
        $view = 'filament-table-views::table-views-bar';

        return View::make($view)->key('tableViews');
    }

    #[Unexposed]
    public function getTableViewResource(): string
    {
        return static::getResource();
    }

    /**
     * @return Builder<TableView>
     */
    #[Unexposed]
    public function getTableViewsQuery(): Builder
    {
        return TableView::query()
            ->forResource($this->getTableViewResource())
            ->forTenant(Filament::getTenant())
            ->visibleTo(Filament::auth()->user())
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    #[Unexposed]
    public function getActiveTableView(): ?TableView
    {
        if ($this->activeTableViewId === null) {
            return null;
        }

        if ($this->cachedActiveTableView?->getKey() !== $this->activeTableViewId) {
            $this->cachedActiveTableView = $this->getTableViewsQuery()->find($this->activeTableViewId);
        }

        return $this->cachedActiveTableView;
    }

    /**
     * Global favourites come first, then the user's own in the order they were added.
     *
     * @return Collection<int, TableView>
     */
    #[Unexposed]
    public function getFavoriteTableViews(): Collection
    {
        $userId = Filament::auth()->id();

        return $this->getTableViewsQuery()
            ->where(fn (Builder $query) => $query
                ->where('is_global_favorite', true)
                ->orWhereHas('favoritedBy', fn (Builder $query) => $query->whereKey($userId)))
            ->reorder()
            ->orderByDesc('is_global_favorite')
            ->orderBy(DB::table('table_view_favorites')
                ->select('sort_order')
                ->whereColumn('table_view_id', 'table_views.id')
                ->where('user_id', $userId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, TableView>
     */
    #[Unexposed]
    public function getManagedTableViews(): Collection
    {
        return $this->getTableViewsQuery()
            ->with('user')
            ->when(filled($this->tableViewSearch), fn (Builder $query) => $query->where('name', 'like', '%' . $this->tableViewSearch . '%'))
            ->get();
    }

    #[Unexposed]
    public function isFavoriteTableView(TableView $view): bool
    {
        $this->cachedFavoriteTableViewIds ??= DB::table('table_view_favorites')
            ->where('user_id', Filament::auth()->id())
            ->pluck('table_view_id')
            ->all();

        return in_array($view->getKey(), $this->cachedFavoriteTableViewIds);
    }

    #[Unexposed]
    public function toggleTableViewFavorite(TableView $view): void
    {
        $userId = Filament::auth()->id();

        if ($this->isFavoriteTableView($view)) {
            $view->favoritedBy()->detach($userId);
        } else {
            // A double click or a second tab can get here after the row exists; ignore the duplicate.
            DB::table('table_view_favorites')->insertOrIgnore([
                'table_view_id' => $view->getKey(),
                'user_id' => $userId,
                'sort_order' => ((int) DB::table('table_view_favorites')->where('user_id', $userId)->max('sort_order')) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->cachedFavoriteTableViewIds = null;
    }

    /**
     * @param  array<int|string>  $ids
     */
    public function reorderTableViewFavorites(array $ids): void
    {
        $userId = Filament::auth()->id();

        foreach (array_values($ids) as $index => $id) {
            DB::table('table_view_favorites')
                ->where('user_id', $userId)
                ->where('table_view_id', (int) $id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    #[Unexposed]
    public function canManageGlobalTableViewFavorites(): bool
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return ($panel?->hasPlugin('filament-table-views') ?? false)
            && FilamentTableViewsPlugin::get()->canManageGlobalFavorites(Filament::auth()->user());
    }

    #[Unexposed]
    public function isTableViewModified(TableView $view): bool
    {
        return $this->normalizeTableViewState($view->state) !== $this->normalizeTableViewState($this->getTableViewState());
    }

    /**
     * @return array{filters: ?array<string, mixed>, sort: ?string, search: ?string, columns: array<int, array<string, mixed>>}
     */
    #[Unexposed]
    public function getTableViewState(): array
    {
        return [
            'filters' => $this->tableFilters,
            'sort' => $this->tableSort,
            'search' => filled($this->tableSearch) ? (string) $this->tableSearch : null,
            'columns' => $this->tableColumns,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[Unexposed]
    public function createTableView(array $attributes): TableView
    {
        $tenant = Filament::getTenant();

        $view = TableView::create([
            'name' => $attributes['name'],
            'icon' => $attributes['icon'] ?? null,
            'color' => $attributes['color'] ?? null,
            'is_public' => (bool) ($attributes['is_public'] ?? false),
            'is_global_favorite' => ($attributes['is_global_favorite'] ?? false) && $this->canManageGlobalTableViewFavorites(),
            'user_id' => Filament::auth()->id(),
            'tenant_type' => $tenant?->getMorphClass(),
            'tenant_id' => $tenant === null ? null : (string) $tenant->getKey(),
            'resource' => $this->getTableViewResource(),
            'state' => $this->getTableViewState(),
        ]);

        $this->activeTableViewId = $view->getKey();

        return $view;
    }

    public function applyTableView(int $id): void
    {
        $view = $this->getTableViewsQuery()->find($id);

        if (! $view instanceof TableView) {
            return;
        }

        $this->applyTableViewState($view->state);

        $this->activeTableViewId = $view->getKey();
    }

    public function resetTableView(): void
    {
        $this->applyTableViewState([]);

        $this->activeTableViewId = null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function applyTableViewState(array $state): void
    {
        $this->getTableFiltersForm()->fill($this->sanitizeTableViewFilters($state['filters'] ?? []));

        if ($this->getTable()->hasDeferredFilters()) {
            $this->applyTableFilters();
        } else {
            $this->handleTableFilterUpdates();
        }

        $this->tableSort = $this->sanitizeTableViewSort($state['sort'] ?? null);
        $this->updatedTableSort();

        $this->tableSearch = $state['search'] ?? '';
        $this->updatedTableSearch();

        filled($state['columns'] ?? null)
            ? $this->applyTableColumnManager($state['columns'], wasReordered: true)
            : $this->resetTableColumnManager();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function sanitizeTableViewFilters(array $filters): array
    {
        $filters = array_intersect_key($filters, $this->getTable()->getFilters());

        foreach ($filters as $name => $state) {
            $filter = $this->getTable()->getFilter($name);

            if ($filter instanceof QueryBuilder && is_array($state['rules'] ?? null)) {
                $filters[$name]['rules'] = $this->pruneTableViewRules($state['rules'], $filter);
            }
        }

        return $filters;
    }

    /**
     * Unknown rule types make the query builder throw, so rules for removed constraints are dropped.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function pruneTableViewRules(array $rules, QueryBuilder $filter): array
    {
        foreach ($rules as $key => $rule) {
            $type = $rule['type'] ?? null;

            if ($type === RuleBuilder::OR_BLOCK_NAME) {
                $groups = [];

                foreach ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? [] as $groupKey => $group) {
                    $groupRules = $this->pruneTableViewRules($group['rules'] ?? [], $filter);

                    if (filled($groupRules)) {
                        $groups[$groupKey] = [...$group, 'rules' => $groupRules];
                    }
                }

                if (filled($groups)) {
                    $rules[$key]['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] = $groups;

                    continue;
                }
            } elseif (is_string($type) && $filter->getConstraint($type) !== null) {
                continue;
            }

            unset($rules[$key]);
        }

        return $rules;
    }

    /**
     * Rule keys are regenerated whenever the filter form is filled, so only values and order are compared.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function normalizeTableViewState(array $state): array
    {
        $columns = array_intersect_key(
            $this->flattenTableViewColumns($state['columns'] ?? []),
            $this->flattenTableViewColumns($this->getDefaultTableColumnState()),
        );

        if (! $this->hasReorderableTableColumns()) {
            ksort($columns);
        }

        return [
            'filters' => $this->normalizeTableViewValue($this->sanitizeTableViewFilters($state['filters'] ?? [])),
            'sort' => $this->sanitizeTableViewSort($state['sort'] ?? null),
            'search' => filled($state['search'] ?? null) ? (string) $state['search'] : null,
            'columns' => $columns,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, bool>
     */
    protected function flattenTableViewColumns(array $items): array
    {
        $columns = [];

        foreach ($items as $item) {
            $isGroup = ($item['type'] ?? null) === self::TABLE_COLUMN_MANAGER_GROUP_TYPE;

            foreach ($isGroup ? ($item['columns'] ?? []) : [$item] as $column) {
                $columns[(string) $column['name']] = (bool) ($column['isToggled'] ?? true);
            }
        }

        return $columns;
    }

    protected function normalizeTableViewValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $item = $this->normalizeTableViewValue($item);

            if (in_array($key, ['rules', RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME], true) && is_array($item)) {
                $item = array_values($item);
            }

            if (! in_array($item, [null, [], '', false], true)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    protected function sanitizeTableViewSort(?string $sort): ?string
    {
        if (blank($sort)) {
            return null;
        }

        $column = $this->getTable()->getColumn((string) str($sort)->before(':'));

        return $column?->isSortable() ? $sort : null;
    }
}
