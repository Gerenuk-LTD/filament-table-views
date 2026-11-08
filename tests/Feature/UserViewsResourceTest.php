<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Gerenuk\FilamentTableViews\Models\TableView;
use Gerenuk\FilamentTableViews\Resources\TableViewResource;
use Gerenuk\FilamentTableViews\Resources\TableViewResource\Pages\ManageTableViews;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\OrderResource;
use Workbench\App\Filament\Resources\OrderResource\Pages\ListOrders;
use Workbench\App\Models\OrderStatus;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    actingAs($this->admin = User::create(['name' => 'Admin']));

    $this->someoneElse = User::create(['name' => 'Someone else']);
});

function makeUserView(array $attributes = []): TableView
{
    return TableView::create($attributes + [
        'user_id' => test()->someoneElse->id,
        'resource' => OrderResource::class,
        'name' => 'A view',
        'state' => [],
    ]);
}

it('lists every user\'s views with their icons for an admin', function () {
    $private = makeUserView(['name' => 'Private', 'icon' => 'heroicon-o-star']);
    $public = makeUserView(['name' => 'Public', 'is_public' => true]);

    Livewire::test(ManageTableViews::class)
        ->assertCanSeeTableRecords([$private, $public])
        ->assertSeeHtml('fi-tv-icon')
        ->assertSeeHtml('<svg');
});

it('is closed to users the plugin does not allow', function () {
    actingAs(User::create(['name' => 'Not an admin']));

    $this->get(TableViewResource::getUrl('index'))->assertForbidden();

    Livewire::test(ManageTableViews::class)->assertForbidden();
});

it('lets an admin rename someone else\'s view in a slide-over', function () {
    $view = makeUserView(['name' => 'Old name']);

    $component = Livewire::test(ManageTableViews::class)
        ->mountAction(TestAction::make('edit')->table($view));

    expect($component->instance()->getMountedAction()->isModalSlideOver())->toBeTrue();

    $component->unmountAction()
        ->callAction(TestAction::make('edit')->table($view), ['name' => 'Renamed'])
        ->assertHasNoActionErrors();

    expect($view->fresh()->name)->toBe('Renamed');
});

it('toggles a favourite for everyone', function () {
    $view = makeUserView();

    Livewire::test(ManageTableViews::class)
        ->callAction(TestAction::make('toggleGlobalFavorite')->table($view));

    expect($view->fresh()->is_global_favorite)->toBeTrue()
        ->and($view->fresh()->is_public)->toBeTrue();

    Livewire::test(ManageTableViews::class)
        ->callAction(TestAction::make('toggleGlobalFavorite')->table($view));

    expect($view->fresh()->is_global_favorite)->toBeFalse();
});

it('deletes views, one or in bulk', function () {
    $single = makeUserView(['name' => 'Single']);
    $bulkA = makeUserView(['name' => 'Bulk A']);
    $bulkB = makeUserView(['name' => 'Bulk B']);

    Livewire::test(ManageTableViews::class)
        ->callAction(TestAction::make('delete')->table($single))
        ->assertHasNoActionErrors();

    expect(TableView::find($single->id))->toBeNull();

    Livewire::test(ManageTableViews::class)
        ->selectTableRecords([$bulkA->id, $bulkB->id])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertHasNoActionErrors();

    expect(TableView::count())->toBe(0);
});

it('orders favourites for everyone from the global tab only', function () {
    $a = makeUserView(['name' => 'A', 'is_public' => true, 'is_global_favorite' => true]);
    $b = makeUserView(['name' => 'B', 'is_public' => true, 'is_global_favorite' => true]);
    $c = makeUserView(['name' => 'C', 'is_public' => true, 'is_global_favorite' => true]);

    Livewire::test(ManageTableViews::class)
        ->call('reorderTable', [$b->id, $a->id]);

    expect($a->fresh()->sort_order)->toBe($b->fresh()->sort_order);

    Livewire::test(ManageTableViews::class)
        ->set('activeTab', 'global')
        ->call('reorderTable', [$c->id, $a->id, $b->id]);

    expect($c->fresh()->sort_order)->toBeLessThan($a->fresh()->sort_order)
        ->and($a->fresh()->sort_order)->toBeLessThan($b->fresh()->sort_order);

    expect(Livewire::test(ListOrders::class)->instance()->getFavoriteTableViews()->pluck('id')->take(3)->all())
        ->toBe([$c->id, $a->id, $b->id]);
});

it('lists view owners in the owner filter', function () {
    $theirs = makeUserView(['name' => 'Theirs']);
    $mine = makeUserView(['name' => 'Mine', 'user_id' => $this->admin->id]);

    Livewire::test(ManageTableViews::class)
        ->assertTableFilterExists('user_id')
        ->filterTable('user_id', $this->someoneElse->id)
        ->assertCanSeeTableRecords([$theirs])
        ->assertCanNotSeeTableRecords([$mine]);
});

it('only shows the current tenant\'s views', function () {
    $tenantA = OrderStatus::create(['name' => 'Tenant A']);
    $tenantB = OrderStatus::create(['name' => 'Tenant B']);

    $viewA = makeUserView(['name' => 'A', 'tenant_type' => $tenantA->getMorphClass(), 'tenant_id' => (string) $tenantA->id]);
    $viewB = makeUserView(['name' => 'B', 'tenant_type' => $tenantB->getMorphClass(), 'tenant_id' => (string) $tenantB->id]);

    try {
        Filament::setTenant($tenantA, isQuiet: true);

        Livewire::test(ManageTableViews::class)
            ->assertCanSeeTableRecords([$viewA])
            ->assertCanNotSeeTableRecords([$viewB]);
    } finally {
        Filament::setTenant(null);
    }
});
