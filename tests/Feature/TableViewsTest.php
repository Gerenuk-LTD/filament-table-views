<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;
use Gerenuk\FilamentTableViews\Models\TableView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\OrderResource;
use Workbench\App\Filament\Resources\OrderResource\Pages\ListOrders;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderStatus;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    actingAs($this->user = User::create(['name' => 'Admin']));

    $status = OrderStatus::create(['name' => 'Paid']);

    $this->small = Order::create(['reference' => 'A-1', 'order_status_id' => $status->id, 'total' => 10, 'quantity' => 1]);
    $this->large = Order::create(['reference' => 'B-2', 'order_status_id' => $status->id, 'total' => 90, 'quantity' => 5]);
    $this->other = Order::create(['reference' => 'B-3', 'order_status_id' => $status->id, 'total' => 50, 'quantity' => 2]);
});

function quantityRule(): array
{
    return ['columnQueryBuilder' => ['rules' => [
        'rule' => ['type' => 'quantity', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 2]]],
    ]]];
}

it('saves the current table state and applies it again', function () {
    $page = Livewire::test(ListOrders::class)
        ->set('tableFilters', quantityRule())
        ->set('tableSearch', 'B-')
        ->call('sortTable', 'quantity', 'desc');

    $view = $page->instance()->createTableView(['name' => 'Big B orders']);

    expect($view->resource)->toBe(OrderResource::class)
        ->and($view->state['sort'])->toBe('quantity:desc')
        ->and($view->state['search'])->toBe('B-');

    $page->call('resetTableView')
        ->assertCanSeeTableRecords([$this->small, $this->large, $this->other])
        ->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', $view->id)
        ->assertCanSeeTableRecords([$this->large, $this->other], inOrder: true)
        ->assertCanNotSeeTableRecords([$this->small]);
});

it('drops parts of a view that no longer exist and still loads it', function () {
    $view = TableView::create([
        'user_id' => $this->user->id,
        'resource' => OrderResource::class,
        'name' => 'Stale',
        'state' => [
            'filters' => [
                'removed_filter' => ['isActive' => true],
                'columnQueryBuilder' => ['rules' => [
                    ...quantityRule()['columnQueryBuilder']['rules'],
                    'gone' => ['type' => 'removed_column', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'x']]],
                    'or' => ['type' => 'or', 'data' => ['groups' => [['rules' => [
                        'gone' => ['type' => 'removed_column', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'x']]],
                    ]]]]],
                ]],
            ],
            'sort' => 'removed_column:asc',
            'columns' => [['type' => 'column', 'name' => 'removed_column', 'label' => 'Removed', 'isHidden' => false, 'isToggled' => true, 'isToggleable' => true, 'isToggledHiddenByDefault' => false]],
        ],
    ]);

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $view->id)
        ->assertSet('tableSort', null)
        ->assertSet('tableFilters.columnQueryBuilder.rules', fn (array $rules): bool => array_column($rules, 'type') === ['quantity'])
        ->assertCanSeeTableRecords([$this->large, $this->other])
        ->assertCanNotSeeTableRecords([$this->small]);
});

it('only applies views the user owns or that are public', function () {
    $someoneElse = User::create(['name' => 'Someone else']);

    $private = TableView::create(['user_id' => $someoneElse->id, 'resource' => OrderResource::class, 'name' => 'Private', 'state' => quantityRule()]);
    $public = TableView::create(['user_id' => $someoneElse->id, 'resource' => OrderResource::class, 'name' => 'Public', 'state' => [], 'is_public' => true]);

    $page = Livewire::test(ListOrders::class);

    expect($page->instance()->getTableViewsQuery()->pluck('id')->all())->toBe([$public->id])
        ->and($public->isOwnedBy($this->user))->toBeFalse();

    $page->call('applyTableView', $private->id)
        ->assertSet('activeTableViewId', null)
        ->call('applyTableView', $public->id)
        ->assertSet('activeTableViewId', $public->id);
});

it('only shows views from the current tenant and stamps new ones with it', function () {
    $someoneElse = User::create(['name' => 'Someone else']);
    $tenantA = OrderStatus::create(['name' => 'Tenant A']);
    $tenantB = OrderStatus::create(['name' => 'Tenant B']);

    $viewA = TableView::create(['user_id' => $someoneElse->id, 'resource' => OrderResource::class, 'name' => 'A', 'state' => [], 'is_public' => true, 'tenant_type' => $tenantA->getMorphClass(), 'tenant_id' => (string) $tenantA->id]);
    $viewB = TableView::create(['user_id' => $someoneElse->id, 'resource' => OrderResource::class, 'name' => 'B', 'state' => [], 'is_public' => true, 'is_global_favorite' => true, 'tenant_type' => $tenantB->getMorphClass(), 'tenant_id' => (string) $tenantB->id]);
    $viewNone = TableView::create(['user_id' => $someoneElse->id, 'resource' => OrderResource::class, 'name' => 'None', 'state' => [], 'is_public' => true]);

    try {
        Filament::setTenant($tenantA, isQuiet: true);

        $page = Livewire::test(ListOrders::class);

        expect($page->instance()->getTableViewsQuery()->pluck('name')->all())->toBe(['A'])
            ->and($page->instance()->getFavoriteTableViews()->pluck('id')->all())->not->toContain($viewB->id);

        $page->call('applyTableView', $viewB->id)
            ->assertSet('activeTableViewId', null)
            ->callAction('saveTableView', ['name' => 'Scoped']);

        $scoped = TableView::where('name', 'Scoped')->sole();

        expect($scoped->tenant_type)->toBe($tenantA->getMorphClass())
            ->and($scoped->tenant_id)->toBe((string) $tenantA->id);

        Filament::setTenant(null);

        expect(Livewire::test(ListOrders::class)->instance()->getTableViewsQuery()->pluck('name')->all())->toBe(['None']);
    } finally {
        Filament::setTenant(null);
    }
});

it('saves a favourite view from the bar and only lets the owner change it', function () {
    Livewire::test(ListOrders::class)
        ->set('tableFilters', quantityRule())
        ->callAction('saveTableView', ['name' => 'Big orders', 'icon' => 'heroicon-o-star', 'color' => 'success', 'is_favorite' => true])
        ->assertHasNoActionErrors()
        ->assertSeeText(['Default view', 'Big orders'])
        ->assertActionHidden('updateTableView');

    $view = TableView::sole();

    expect($view->favoritedBy()->pluck('users.id')->all())->toBe([$this->user->id]);

    actingAs(User::create(['name' => 'Someone else']));
    $view->update(['is_public' => true]);

    $nested = fn (string $name): array => [TestAction::make('manageTableViews'), TestAction::make($name)->arguments(['view' => $view->id])];

    Livewire::test(ListOrders::class)
        ->assertDontSeeText('Big orders')
        ->assertActionHidden($nested('deleteTableView'))
        ->assertActionHidden($nested('editTableView'))
        ->callAction($nested('toggleTableViewFavorite'))
        ->unmountAction()
        ->assertSeeText('Big orders');
});

it('opens a view from a link and keeps the open view across reloads', function () {
    $view = TableView::create(['user_id' => $this->user->id, 'resource' => OrderResource::class, 'name' => 'Shared link', 'state' => ['search' => 'B-']]);

    Livewire::withQueryParams(['view' => $view->id])
        ->test(ListOrders::class)
        ->assertSet('activeTableViewId', $view->id)
        ->assertSet('tableSearch', 'B-');

    Livewire::withQueryParams(['view' => $view->id, 'search' => 'A-'])
        ->test(ListOrders::class)
        ->assertSet('activeTableViewId', $view->id)
        ->assertSet('tableSearch', 'A-');
});

it('tracks unsaved changes, favourite order and favourites for everyone', function () {
    $page = Livewire::test(ListOrders::class)
        ->set('tableFilters', quantityRule())
        ->callAction('saveTableView', ['name' => 'First', 'is_favorite' => true, 'is_global_favorite' => true])
        ->assertActionHidden('updateTableView')
        ->set('tableSearch', 'B-')
        ->assertActionVisible('updateTableView')
        ->callAction('updateTableView')
        ->assertActionHidden('updateTableView')
        ->callAction('saveTableView', ['name' => 'Second', 'is_favorite' => true])
        ->callAction('saveTableView', ['name' => 'Third', 'is_favorite' => true]);

    [$first, $second, $third] = TableView::orderBy('id')->get()->all();

    expect($first->is_global_favorite)->toBeTrue()
        ->and($first->is_public)->toBeTrue()
        ->and($first->state['search'])->toBe('B-');

    $page->call('reorderTableViewFavorites', [$third->id, $second->id]);

    expect($page->instance()->getFavoriteTableViews()->pluck('name')->all())->toBe(['First', 'Third', 'Second']);

    actingAs(User::create(['name' => 'Not an admin']));

    Livewire::test(ListOrders::class)
        ->callAction('saveTableView', ['name' => 'Mine', 'is_global_favorite' => true]);

    expect(TableView::where('name', 'Mine')->sole()->is_global_favorite)->toBeFalse();
});

it('matches the user key type when migrating', function () {
    $migration = include __DIR__ . '/../../database/migrations/create_table_views_table.php';
    $migration->down();

    config(['filament-table-views.user_model' => Customer::class]);
    $migration->up();

    expect(Schema::getColumnType('table_views', 'user_id'))->toBe('integer')
        ->and(Schema::getColumnType('table_view_favorites', 'user_id'))->toBe('integer');
});

it('only lets the browser apply, reset and reorder views', function () {
    $view = TableView::create([
        'user_id' => $this->user->id,
        'resource' => OrderResource::class,
        'name' => 'Mine',
        'state' => [],
    ]);

    $calls = [
        'createTableView' => [['name' => 'x']],
        'getManagedTableViews' => [],
        'getFavoriteTableViews' => [],
        'getActiveTableView' => [],
        'getTableViewState' => [],
        'toggleTableViewFavorite' => [$view->id],
    ];

    foreach ($calls as $method => $params) {
        Livewire::test(ListOrders::class)->call($method, ...$params)->assertForbidden();
    }

    expect(TableView::where('name', 'x')->exists())->toBeFalse();

    Livewire::test(ListOrders::class)
        ->call('applyTableView', $view->id)
        ->assertSet('activeTableViewId', $view->id)
        ->call('resetTableView')
        ->assertSet('activeTableViewId', null)
        ->call('reorderTableViewFavorites', [$view->id])
        ->assertOk();
});

it('renders a view with a bad stored icon using the default icon', function () {
    foreach (['https://example.test/x.png', 'heroicon-o-does-not-exist'] as $i => $icon) {
        $view = TableView::create([
            'user_id' => $this->user->id,
            'resource' => OrderResource::class,
            'name' => "Bad icon {$i}",
            'icon' => $icon,
            'is_public' => true,
            'state' => [],
        ]);

        $html = $view->getIconHtml()->toHtml();

        expect($html)->not->toContain('<img')->not->toContain('example.test');
    }

    Livewire::test(ListOrders::class)
        ->mountAction('manageTableViews')
        ->assertSuccessful();
});

it('ignores a favourite that already exists', function () {
    $view = TableView::create(['user_id' => $this->user->id, 'resource' => OrderResource::class, 'name' => 'Mine', 'state' => []]);

    $component = Livewire::test(ListOrders::class)->instance();

    expect($component->isFavoriteTableView($view))->toBeFalse();

    $view->favoritedBy()->attach($this->user->id);

    $component->toggleTableViewFavorite($view);

    expect(DB::table('table_view_favorites')->count())->toBe(1);
});

it('loads the active view once per request and shows its new name after an edit', function () {
    $view = TableView::create(['user_id' => $this->user->id, 'resource' => OrderResource::class, 'name' => 'Mine', 'state' => []]);

    $page = Livewire::test(ListOrders::class);

    DB::enableQueryLog();

    $page->call('applyTableView', $view->id);

    // One lookup in applyTableView, one for the active view (shared by the bar and the Update action).
    $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"table_views"."id" = ?'));

    expect($queries)->toHaveCount(2);

    DB::disableQueryLog();

    $page->assertSeeText('Mine')
        ->mountAction('manageTableViews')
        ->call('mountAction', 'editTableView', ['view' => $view->id], [])
        ->set('mountedActions.1.data.name', 'Renamed')
        ->call('callMountedAction');

    expect($page->instance()->getActiveTableView()->name)->toBe('Renamed');
});

it('saves any heroicon, rejects unknown icons and limits icons when the app chooses them', function () {
    Livewire::test(ListOrders::class)
        ->callAction('saveTableView', ['name' => 'Solid', 'icon' => 'heroicon-s-academic-cap'])
        ->assertHasNoActionErrors();

    expect(TableView::sole()->icon)->toBe('heroicon-s-academic-cap');

    Livewire::test(ListOrders::class)
        ->callAction('saveTableView', ['name' => 'Bad', 'icon' => 'heroicon-o-not-a-real-icon'])
        ->assertHasActionErrors(['icon']);

    FilamentTableViewsPlugin::get()->icons([Heroicon::OutlinedStar, 'heroicon-s-bolt']);

    try {
        Livewire::test(ListOrders::class)
            ->callAction('saveTableView', ['name' => 'Bolt', 'icon' => 'heroicon-s-bolt'])
            ->assertHasNoActionErrors();

        Livewire::test(ListOrders::class)
            ->callAction('saveTableView', ['name' => 'Cap', 'icon' => 'heroicon-o-academic-cap'])
            ->assertHasActionErrors(['icon']);
    } finally {
        FilamentTableViewsPlugin::get()->icons(null);
    }
});

it('shows colours as circles and only offers the configured ones', function () {
    // The slide-over's markup isn't part of the page HTML in tests, so render the field itself.
    $page = Livewire::test(ListOrders::class)->mountAction('saveTableView');

    $html = invade($page->instance())->getMountedActionSchema()->getComponent('color')->toHtml();

    expect($html)
        ->toContain('fi-tv-color-picker')
        ->toContain('aria-label="Green"')
        ->not->toContain('<select');

    FilamentTableViewsPlugin::get()->colors(['success', 'danger']);

    try {
        Livewire::test(ListOrders::class)
            ->callAction('saveTableView', ['name' => 'Ok', 'color' => 'danger'])
            ->assertHasNoActionErrors();

        Livewire::test(ListOrders::class)
            ->callAction('saveTableView', ['name' => 'No', 'color' => 'gray'])
            ->assertHasActionErrors(['color']);
    } finally {
        FilamentTableViewsPlugin::get()->colors(['gray', 'primary', 'info', 'success', 'warning', 'danger']);
    }
});
