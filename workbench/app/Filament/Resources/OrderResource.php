<?php

namespace Workbench\App\Filament\Resources;

use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Gerenuk\FilamentTableViews\Filters\ColumnQueryBuilder;
use Workbench\App\Filament\Resources\OrderResource\Pages\ListOrders;
use Workbench\App\Models\Order;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function table(Table $table): Table
    {
        return $table
            ->filters([
                ColumnQueryBuilder::make()
                    ->excludeColumns(['internal_note'])
                    ->constraints([
                        TextConstraint::make('total')->label('Total as text'),
                        TextConstraint::make('notes')->label('Notes'),
                    ]),
            ])
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('status.name'),
                TextColumn::make('user.name'),
                TextColumn::make('user.created_at')->dateTime(),
                TextColumn::make('total')->money('GBP'),
                TextColumn::make('quantity')->sortable()->toggleable(),
                IconColumn::make('is_paid')->boolean(),
                TextColumn::make('placed_at')->dateTime(),
                TextColumn::make('internal_note'),
                TextColumn::make('summary'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
