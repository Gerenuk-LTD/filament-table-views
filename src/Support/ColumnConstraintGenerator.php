<?php

namespace Gerenuk\FilamentTableViews\Support;

use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;

class ColumnConstraintGenerator
{
    protected const array BOOLEAN_TYPES = ['bool', 'boolean'];

    protected const array DATE_TYPES = ['date', 'datetime', 'datetime2', 'timestamp', 'timestamptz'];

    protected const array NUMBER_TYPES = [
        'bigint', 'decimal', 'double', 'float', 'float4', 'float8', 'int', 'int2', 'int4', 'int8',
        'integer', 'mediumint', 'money', 'numeric', 'real', 'smallint', 'tinyint',
    ];

    /**
     * @var array<string, array<string, array{type_name: string, nullable: bool}>>
     */
    protected array $schemas = [];

    /**
     * @param  array<Column>  $columns
     * @return array<string, Constraint>
     */
    public function generate(array $columns, Model $model): array
    {
        $constraints = [];

        foreach ($columns as $column) {
            $constraint = $this->makeConstraint($column, $model);

            if ($constraint !== null) {
                $constraints[$constraint->getName()] = $constraint;
            }
        }

        return $constraints;
    }

    protected function makeConstraint(Column $column, Model $model): ?Constraint
    {
        if ($column instanceof ImageColumn) {
            return null;
        }

        $segments = explode('.', $column->getName());
        $attribute = (string) array_pop($segments);
        $relationship = implode('.', $segments);

        [$related, $isToMany] = $this->resolveRelated($model, $segments);

        $schema = $related === null ? null : $this->getSchema($related)[$attribute] ?? null;

        if ($related === null || $schema === null) {
            return null;
        }

        $name = str_replace('.', '_', $column->getName());

        $constraint = match (true) {
            $this->isBoolean($column, $related, $attribute, $schema['type_name']) => BooleanConstraint::make($name)->nullable($schema['nullable']),
            $this->isDate($column, $schema['type_name']) => DateConstraint::make($name)->nullable($schema['nullable']),
            $this->isNumber($column, $schema['type_name']) => NumberConstraint::make($name)->nullable($schema['nullable']),
            count($segments) === 1 => RelationshipConstraint::make($name)
                ->attribute($relationship)
                ->multiple($isToMany)
                ->emptyable()
                ->selectable(IsRelatedToOperator::make()->titleAttribute($attribute)->searchable()->preload()->multiple()),
            default => TextConstraint::make($name)->nullable($schema['nullable']),
        };

        if ($relationship !== '' && ! $constraint instanceof RelationshipConstraint) {
            $constraint->relationship($relationship, $attribute);
        }

        $label = $column->getLabel();

        return $constraint->label($label instanceof Htmlable ? strip_tags($label->toHtml()) : $label);
    }

    /**
     * @param  array<string>  $relationships
     * @return array{0: ?Model, 1: bool}
     */
    protected function resolveRelated(Model $model, array $relationships): array
    {
        $isToMany = false;

        foreach ($relationships as $name) {
            $relation = $model->isRelation($name) ? $model->{$name}() : null;

            if (! $relation instanceof Relation) {
                return [null, false];
            }

            $isToMany = $isToMany || ! ($relation instanceof BelongsTo || $relation instanceof HasOne || $relation instanceof MorphOne);
            $model = $relation->getRelated();
        }

        return [$model, $isToMany];
    }

    /**
     * @return array<string, array{type_name: string, nullable: bool}>
     */
    protected function getSchema(Model $model): array
    {
        return $this->schemas[$model->getConnectionName() . '.' . $model->getTable()] ??= collect($model->getConnection()->getSchemaBuilder()->getColumns($model->getTable()))
            ->keyBy('name')
            ->all();
    }

    protected function isBoolean(Column $column, Model $model, string $attribute, string $type): bool
    {
        return $column instanceof ToggleColumn
            || $column instanceof CheckboxColumn
            // isBoolean() caches false when called without a record, so ask a clone.
            || ($column instanceof IconColumn && (clone $column)->isBoolean())
            || $model->hasCast($attribute, ['bool', 'boolean'])
            || in_array($type, self::BOOLEAN_TYPES, true);
    }

    protected function isDate(Column $column, string $type): bool
    {
        return ($column instanceof TextColumn && ($column->isDate() || $column->isDateTime()))
            || in_array($type, self::DATE_TYPES, true);
    }

    protected function isNumber(Column $column, string $type): bool
    {
        return ($column instanceof TextColumn && ($column->isNumeric() || $column->isMoney()))
            || in_array($type, self::NUMBER_TYPES, true);
    }
}
