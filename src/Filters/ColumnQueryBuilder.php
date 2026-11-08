<?php

namespace Gerenuk\FilamentTableViews\Filters;

use Closure;
use Filament\Actions\Action;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Table;
use Gerenuk\FilamentTableViews\Support\ColumnConstraintGenerator;
use Illuminate\Database\Eloquent\Model;

class ColumnQueryBuilder extends QueryBuilder
{
    /**
     * @var array<string>
     */
    protected array $excludedColumns = [];

    protected bool $hasGeneratedConstraints = false;

    public static function getDefaultName(): ?string
    {
        return 'columnQueryBuilder';
    }

    /**
     * Rules only fit on one line in a wide form, so a table that hasn't chosen a layout gets a wide slide-over.
     */
    public function table(Table $table): static
    {
        if ($table->getFiltersLayout() === FiltersLayout::Dropdown) {
            $table->filtersLayout(FiltersLayout::Modal);

            $modifyTriggerActionUsing = (fn (): ?Closure => $this->modifyFiltersTriggerActionUsing)->call($table);

            $table->filtersTriggerAction(function (Action $action) use ($table, $modifyTriggerActionUsing): Action {
                $action->slideOver();

                return $modifyTriggerActionUsing ? ($table->evaluate($modifyTriggerActionUsing, ['action' => $action]) ?? $action) : $action;
            });
        }

        if ($table->getFiltersFormWidth() === null) {
            $table->filtersFormWidth(Width::TwoExtraLarge);
        }

        return parent::table($table);
    }

    /**
     * @param  array<string>  $columns
     */
    public function excludeColumns(array $columns): static
    {
        $this->excludedColumns = $columns;

        return $this;
    }

    public function getConstraints(): array
    {
        $this->generateConstraints();

        return parent::getConstraints();
    }

    public function getConstraint(string $name): ?Constraint
    {
        $this->generateConstraints();

        return parent::getConstraint($name);
    }

    /**
     * Runs on first use because filters() can be called before columns(). Constraints set by hand win.
     */
    protected function generateConstraints(): void
    {
        if ($this->hasGeneratedConstraints) {
            return;
        }

        $this->hasGeneratedConstraints = true;

        /** @var class-string<Model> $model */
        $model = $this->getTable()->getModel();

        $columns = array_diff_key($this->getTable()->getColumns(), array_flip($this->excludedColumns));

        $this->constraints = [
            ...app(ColumnConstraintGenerator::class)->generate($columns, new $model),
            ...$this->constraints,
        ];
    }
}
