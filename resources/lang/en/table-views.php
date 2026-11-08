<?php

return [

    'bar' => [
        'label' => 'Table views',
        'default' => 'Default view',
        'modified' => 'Unsaved changes',
    ],

    'form' => [
        'name' => 'Name',
        'icon' => 'Icon',
        'color' => 'Colour',
        'is_public' => 'Share with everyone',
        'is_public_helper' => 'Other users can see and apply this view, but only you can change it.',
        'is_favorite' => 'Add to favourites',
        'is_global_favorite' => 'Favourite for everyone',
        'is_global_favorite_helper' => 'Shares the view and pins it to everyone\'s favourites.',
    ],

    'colors' => [
        'gray' => 'Grey',
        'primary' => 'Primary',
        'info' => 'Blue',
        'success' => 'Green',
        'warning' => 'Amber',
        'danger' => 'Red',
    ],

    'actions' => [
        'save' => [
            'label' => 'Save view',
            'heading' => 'Save view',
            'description' => 'Saves the current filters, sort, search and columns.',
            'submit' => 'Save',
            'notification' => 'View saved',
        ],
        'update' => [
            'label' => 'Update view',
            'tooltip' => 'Save the current table state to this view',
            'notification' => 'View updated',
        ],
        'manage' => [
            'label' => 'Views',
            'heading' => 'Table views',
            'close' => 'Close',
        ],
        'favorite' => [
            'add' => 'Add to favourites',
            'remove' => 'Remove from favourites',
            'global' => 'Favourite for everyone',
        ],
        'edit' => [
            'label' => 'Edit',
            'heading' => 'Edit view',
            'submit' => 'Save',
            'notification' => 'View updated',
        ],
        'delete' => [
            'label' => 'Delete',
            'heading' => 'Delete ":name"?',
            'description' => 'The view will be removed. Your table won\'t change.',
            'description_public' => 'This view is shared, so it will be removed for everyone who uses it.',
            'submit' => 'Delete view',
            'notification' => 'View deleted',
        ],
    ],

    'user_views' => [
        'label' => 'User view',
        'plural_label' => 'User views',
        'columns' => [
            'name' => 'Name',
            'owner' => 'Owner',
            'resource' => 'Table',
            'is_public' => 'Shared',
            'is_global_favorite' => 'Favourite for everyone',
            'favorited_by_count' => 'Favourited by',
            'updated_at' => 'Updated',
        ],
        'tabs' => [
            'all' => 'All views',
            'global' => 'Favourites for everyone',
        ],
        'toggle_global_favorite' => [
            'add' => 'Make favourite for everyone',
            'remove' => 'Remove favourite for everyone',
            'notification' => 'View updated',
        ],
    ],

    'manager' => [
        'search' => 'Search views',
        'empty' => 'No saved views found.',
        'yours_private' => 'Yours · private',
        'yours_public' => 'Yours · shared',
        'shared_by' => 'Shared by :name',
    ],

];
