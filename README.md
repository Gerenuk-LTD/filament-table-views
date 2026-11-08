<img src="https://banners.beyondco.de/Filament%20Table%20Views.png?theme=light&packageManager=composer+require&packageName=gerenuk%2Ffilament-table-views&pattern=brickWall&style=style_1&description=Saved+table+views+and+column+filters+for+your+Filament+panel&md=1&showWatermark=0&fontSize=100px&images=table" alt="Project banner">

# Filament Table Views

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gerenuk/filament-table-views.svg?style=flat-square)](https://packagist.org/packages/gerenuk/filament-table-views)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/gerenuk-ltd/filament-table-views/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gerenuk-ltd/filament-table-views/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/gerenuk/filament-table-views.svg?style=flat-square)](https://packagist.org/packages/gerenuk/filament-table-views)
[![Licence](https://img.shields.io/github/license/Gerenuk-LTD/filament-table-views?color=blue&label=license&style=flat-square)](https://github.com/Gerenuk-LTD/filament-table-views/blob/main/LICENSE.md)

This package lets you save table views in your Filament panel, with filters that build themselves from your table's columns. Users save the current filters, sort, search and columns, share them, pin favourites above the table and come back to them from a link.

## Table of Contents
1. [Introduction](#filament-table-views)
2. [Version Compatibility](#version-compatibility)
3. [Installation](#installation)
4. [Usage](#usage)
    - [Registering the Plugin](#registering-the-plugin)
    - [Column Filters](#column-filters)
    - [Saved Views](#saved-views)
    - [Favourites for Everyone](#favourites-for-everyone)
    - [User Views Page](#user-views-page)
    - [Placing the Views Bar](#placing-the-views-bar)
    - [How Views Behave](#how-views-behave)
5. [Testing](#testing)
6. [Changelog](#changelog)
7. [Contributing](#contributing)
8. [Security Vulnerabilities](#security-vulnerabilities)
9. [Credits](#credits)
10. [License](#license)

## Version Compatibility

| Plugin | Filament | Laravel | PHP      |
|--------|----------|---------|----------|
| 1.x    | 5.11+    | 13.x    | 8.4\|8.5 |

## Installation

You can install the package via composer:

```bash
composer require gerenuk/filament-table-views
```

Views belong to the model of your default auth provider. Their `user_id` columns match that model's key, whether it uses integer IDs, UUIDs or ULIDs. If your panel signs in a different model, publish the config and set `user_model` before publishing and running the migration:

```bash
php artisan vendor:publish --tag="filament-table-views-config"
```

```php
'user_model' => App\Models\Admin::class,
```

Publish the migration and run it. It creates the `table_views` and `table_view_favorites` tables. The published migration can be edited before you run it.

```bash
php artisan vendor:publish --tag="filament-table-views-migrations"
php artisan migrate
```

Publish the package's stylesheet. Filament's `filament:upgrade` script does this for you if it runs after `composer update`:

```bash
php artisan filament:assets
```

Optionally, you can publish the translations using

```bash
php artisan vendor:publish --tag="filament-table-views-translations"
```

## Usage

### Registering the Plugin

Add the plugin to your panel:

```php
use Gerenuk\FilamentTableViews\FilamentTableViewsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentTableViewsPlugin::make());
}
```

### Column Filters

Add `ColumnQueryBuilder` to a table's filters. It reads the table's columns and adds a rule for each column backed by the database:

```php
use Gerenuk\FilamentTableViews\Filters\ColumnQueryBuilder;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('reference')->searchable()->sortable(),
            TextColumn::make('status.name')->label('Status'),
            TextColumn::make('total')->money('GBP'),
            IconColumn::make('is_paid')->boolean(),
            TextColumn::make('placed_at')->dateTime(),
        ])
        ->filters([
            ColumnQueryBuilder::make(),
        ]);
}
```

The type of each rule comes from the column and the database:

| Column | Rule |
|---|---|
| Toggle, checkbox, boolean icon, or a boolean cast or column type | Boolean |
| `date()` or `dateTime()` text column, or a date column type | Date |
| `numeric()` or `money()` text column, or a numeric column type | Number |
| A column on a direct relationship, such as `status.name` | "Is related to", a searchable select |
| Anything else | Text |

Deeper relationships, such as `customer.company.name`, filter on the related value. Image columns, and columns that aren't backed by the database (such as accessors), are skipped.

Leave columns out with `excludeColumns()`. Rules you add yourself with `constraints()` replace a generated rule of the same name:

```php
ColumnQueryBuilder::make()
    ->excludeColumns(['internal_note'])
    ->constraints([
        TextConstraint::make('notes'),
    ]),
```

If the table hasn't chosen a filter layout, the filters open in a slide-over wide enough to keep each rule on one line. Any layout or width the table sets itself is kept.

### Saved Views

Add the `HasTableViews` trait to a resource's list page:

```php
use Filament\Resources\Pages\ListRecords;
use Gerenuk\FilamentTableViews\Concerns\HasTableViews;

class ListOrders extends ListRecords
{
    use HasTableViews;

    protected static string $resource = OrderResource::class;
}
```

This adds a bar above the table:

- **Save view** saves the current filters, sort, search and columns, with a name, icon and colour. Users can share a view with everyone and add it to their favourites.
- **Favourites** show as tabs next to **Default view**. Users drag the tabs to reorder them.
- **Update view** appears when the open view is yours and the table has changed since it was saved. A dot on the tab shows the same thing.
- **Views** opens a slide-over to search, apply, favourite, edit and delete views. Only the owner of a view can edit or delete it.

Views are stored per resource, so renaming the list page keeps them.

Colours show as a row of circles, and every outline and solid Heroicon is offered (searchable). Choose your own with `colors()` and `icons()`:

```php
FilamentTableViewsPlugin::make()
    ->colors(['success', 'info', 'warning', 'danger', 'gray'])
    ->icons([Heroicon::OutlinedStar, Heroicon::OutlinedFlag]),
```

`colors()` takes Filament colour names. The default is grey, primary, blue, green, amber and red. Leave `icons()` out to offer every Heroicon.

### Favourites for Everyone

A favourite for everyone is pinned to every user's bar, before their own favourites. Choose who can set one with `globalFavorites()`:

```php
FilamentTableViewsPlugin::make()
    ->globalFavorites(fn (User $user): bool => $user->isAdmin()),
```

Those users get a "Favourite for everyone" toggle when saving or editing their own views. A favourite for everyone is always shared.

### User Views Page

The User Views page is off by default. Turn it on and choose who can open it:

```php
FilamentTableViewsPlugin::make()
    ->userViews(fn (User $user): bool => $user->isAdmin())
    ->userViewsNavigationGroup('Settings'), // optional
```

The page:

- lists every saved view in the current tenant, whoever owns it;
- lets those users rename, recolour, share or unshare, and delete any view, and set favourites for everyone (when they also pass `globalFavorites()`);
- lets them drag favourites for everyone into order on the "Favourites for everyone" tab.

Only the users that `userViews()` allows can open the page or use its actions.

### Placing the Views Bar

The trait adds the bar through the page's `content()` method. If your page overrides `content()`, add the bar yourself:

```php
public function content(Schema $schema): Schema
{
    return $schema
        ->components([
            $this->getTabsContentComponent(),
            $this->getTableViewsComponent(),
            EmbeddedTable::make(),
        ]);
}
```

### How Views Behave

- **Links:** the open view is kept in the URL as `?view=`, so a reload keeps it open. A link with only `?view=` applies the whole view.
- **Changed tables:** if a column, filter or rule type has been removed since a view was saved, that part of the view is dropped and the rest still loads.
- **Visibility:** users see their own views and views shared with everyone. In a panel with tenancy, views, including shared views and favourites for everyone, belong to the tenant they were saved in.

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Kieran Proctor](https://github.com/KieranLProctor)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
