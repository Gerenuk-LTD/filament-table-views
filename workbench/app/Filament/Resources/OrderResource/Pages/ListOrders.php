<?php

namespace Workbench\App\Filament\Resources\OrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Gerenuk\FilamentTableViews\Concerns\HasTableViews;
use Workbench\App\Filament\Resources\OrderResource;

class ListOrders extends ListRecords
{
    use HasTableViews;

    protected static string $resource = OrderResource::class;
}
