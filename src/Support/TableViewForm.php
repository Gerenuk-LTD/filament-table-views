<?php

namespace Gerenuk\FilamentTableViews\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;

final class TableViewForm
{
    /**
     * @return array<Component|Field>
     */
    public static function components(bool $canManageGlobalFavorites): array
    {
        return [
            TextInput::make('name')
                ->label(__('filament-table-views::table-views.form.name'))
                ->required()
                ->maxLength(255)
                ->autofocus(),
            Select::make('icon')
                ->label(__('filament-table-views::table-views.form.icon'))
                ->searchable()
                ->allowHtml()
                ->options(fn (): array => self::iconOptions())
                ->getSearchResultsUsing(fn (string $search): array => self::iconOptions($search))
                ->getOptionLabelUsing(fn (?string $value): ?string => $value === null ? null : self::iconLabel($value))
                ->in(array_keys(FilamentTableViewsPlugin::get()->getIcons() ?? TableViewIcons::all())),
            ToggleButtons::make('color')
                ->label(__('filament-table-views::table-views.form.color'))
                ->options($colors = self::colorOptions())
                ->colors(array_combine(array_keys($colors), array_keys($colors)))
                ->tooltips($colors)
                ->hiddenButtonLabels()
                ->inline()
                ->in(array_keys($colors))
                ->extraAttributes(['class' => 'fi-tv-color-picker']),
            Toggle::make('is_public')
                ->label(__('filament-table-views::table-views.form.is_public'))
                ->helperText(__('filament-table-views::table-views.form.is_public_helper'))
                ->disabled(fn (Get $get): bool => (bool) $get('is_global_favorite')),
            Toggle::make('is_global_favorite')
                ->label(__('filament-table-views::table-views.form.is_global_favorite'))
                ->helperText(__('filament-table-views::table-views.form.is_global_favorite_helper'))
                ->visible($canManageGlobalFavorites)
                ->live()
                ->afterStateUpdated(fn (bool $state, Set $set) => $state ? $set('is_public', true) : null),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function iconOptions(?string $search = null): array
    {
        $allowed = FilamentTableViewsPlugin::get()->getIcons();

        if (filled($search)) {
            $icons = TableViewIcons::search($search, $allowed);
        } elseif ($allowed !== null) {
            $icons = array_slice($allowed, 0, 50, preserve_keys: true);
        } else {
            $all = TableViewIcons::all();
            $common = [];

            foreach ([
                'table-cells', 'star', 'bookmark', 'funnel', 'flag', 'fire', 'bolt', 'sparkles', 'heart', 'eye',
                'clock', 'calendar', 'check-circle', 'x-circle', 'exclamation-triangle', 'archive-box', 'inbox',
                'tag', 'user', 'users', 'shopping-cart', 'banknotes', 'currency-pound', 'chart-bar', 'globe-alt',
            ] as $icon) {
                $common["heroicon-o-{$icon}"] = $all["heroicon-o-{$icon}"];
            }

            $icons = array_slice($common + $all, 0, 50, preserve_keys: true);
        }

        return collect($icons)
            ->map(fn (string $label, string $name): string => TableViewIcons::optionHtml($name, $label))
            ->all();
    }

    private static function iconLabel(string $name): ?string
    {
        $label = TableViewIcons::all()[$name] ?? null;

        return $label === null ? null : TableViewIcons::optionHtml($name, $label);
    }

    /**
     * @return array<string, string>
     */
    private static function colorOptions(): array
    {
        return collect(FilamentTableViewsPlugin::get()->getColors())
            ->mapWithKeys(fn (string $color): array => [$color => trans()->has("filament-table-views::table-views.colors.{$color}")
                ? __("filament-table-views::table-views.colors.{$color}")
                : (string) str($color)->headline()])
            ->all();
    }
}
