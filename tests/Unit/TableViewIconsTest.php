<?php

use Gerenuk\FilamentTableViews\Support\TableViewIcons;

it('lists every outline and solid heroicon', function () {
    expect(TableViewIcons::all())
        ->toHaveKeys(['heroicon-o-star', 'heroicon-s-star'])
        ->and(count(TableViewIcons::all()))->toBeGreaterThan(600)
        ->and(TableViewIcons::search('', limit: 5))->toHaveCount(5);
});

it('searches by label words and heroicon names', function () {
    $solidStar = TableViewIcons::search('star solid');
    $outline = TableViewIcons::search('o-user-group');

    expect(TableViewIcons::search('academic'))->toHaveKey('heroicon-o-academic-cap')
        ->and($solidStar)->toHaveKey('heroicon-s-star')
        ->and($solidStar)->not->toHaveKey('heroicon-o-star')
        ->and(TableViewIcons::search('user-group'))->toHaveKeys(['heroicon-o-user-group', 'heroicon-s-user-group'])
        ->and(array_keys(TableViewIcons::search('heroicon-s-user-group')))->toBe(['heroicon-s-user-group'])
        ->and($outline)->toHaveKey('heroicon-o-user-group')
        ->and($outline)->not->toHaveKey('heroicon-s-user-group')
        ->and(array_key_first(TableViewIcons::search('user')))->toBeIn(['heroicon-o-user', 'heroicon-s-user'])
        ->and(TableViewIcons::search('user', limit: 1))->not->toBeEmpty();
});
