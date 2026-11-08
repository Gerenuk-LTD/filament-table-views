<?php

use Filament\Facades\Filament;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Gerenuk\FilamentTableViews\Filters\ColumnQueryBuilder;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\OrderResource\Pages\ListOrders;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderStatus;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    actingAs(User::create(['name' => 'Admin']));

    $this->paid = OrderStatus::create(['name' => 'Paid']);
    $this->pending = OrderStatus::create(['name' => 'Pending']);

    $this->first = Order::create(['reference' => 'A-1', 'order_status_id' => $this->paid->id, 'total' => 10, 'quantity' => 1, 'is_paid' => true]);
    $this->second = Order::create(['reference' => 'B-2', 'order_status_id' => $this->pending->id, 'total' => 90, 'quantity' => 5]);
});

function constraints(): array
{
    $filter = Livewire::test(ListOrders::class)->instance()->getTable()->getFilter('columnQueryBuilder');

    expect($filter)->toBeInstanceOf(ColumnQueryBuilder::class);

    return $filter->getConstraints();
}

function rule(string $type, string $operator, array $settings): array
{
    return ['columnQueryBuilder' => ['rules' => [
        'rule' => ['type' => $type, 'data' => ['operator' => $operator, 'settings' => $settings]],
    ]]];
}

it('generates a constraint for each kind of column, skipping excluded and virtual ones and keeping hand-written ones', function () {
    $constraints = constraints();

    expect($constraints)->not->toHaveKeys(['internal_note', 'summary'])
        ->and($constraints['total'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['notes'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['reference'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['status_name'])->toBeInstanceOf(RelationshipConstraint::class)
        ->and($constraints['status_name']->getRelationshipName())->toBe('status')
        ->and($constraints['user_created_at'])->toBeInstanceOf(DateConstraint::class)
        ->and($constraints['user_created_at']->getRelationshipName())->toBe('user')
        ->and($constraints['quantity'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['is_paid'])->toBeInstanceOf(BooleanConstraint::class)
        ->and($constraints['placed_at'])->toBeInstanceOf(DateConstraint::class);
});

it('filters records through generated constraints and keeps boolean icon columns working', function () {
    Livewire::test(ListOrders::class)
        ->set('tableFilters', rule('reference', 'contains', ['text' => 'B-']))
        ->assertCanSeeTableRecords([$this->second])
        ->assertCanNotSeeTableRecords([$this->first])
        ->set('tableFilters', rule('status_name', 'isRelatedTo', ['value' => [$this->paid->id]]))
        ->assertCanSeeTableRecords([$this->first])
        ->assertCanNotSeeTableRecords([$this->second])
        ->set('tableFilters', rule('quantity', 'isMin', ['number' => 3]))
        ->assertCanSeeTableRecords([$this->second])
        ->assertCanNotSeeTableRecords([$this->first])
        ->set('tableFilters', rule('is_paid', 'isTrue', []))
        ->assertCanSeeTableRecords([$this->first])
        ->assertCanNotSeeTableRecords([$this->second])
        ->assertSeeHtml('fi-color-success');
});

it('defaults to a wide slide-over unless the table chooses a layout', function () {
    $page = Livewire::test(ListOrders::class)->instance();

    expect($page->getTable()->getFiltersLayout())->toBe(FiltersLayout::Modal)
        ->and($page->getTable()->getFiltersFormWidth())->toBe(Width::TwoExtraLarge)
        ->and($page->getTable()->getFiltersTriggerAction()->isModalSlideOver())->toBeTrue();

    $customised = Table::make($page)
        ->filtersTriggerAction(fn ($action) => $action->label('Refine'))
        ->filters([ColumnQueryBuilder::make()])
        ->getFiltersTriggerAction();

    expect($customised->isModalSlideOver())->toBeTrue()
        ->and($customised->getLabel())->toBe('Refine');

    $table = Table::make($page)->filters([ColumnQueryBuilder::make()], FiltersLayout::AboveContent);

    expect($table->getFiltersLayout())->toBe(FiltersLayout::AboveContent);
});
