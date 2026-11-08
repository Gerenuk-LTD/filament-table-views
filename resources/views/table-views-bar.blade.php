@php
    $livewire = $getLivewire();
    $activeView = $livewire->getActiveTableView();
    $isActiveViewModified = $activeView && $livewire->isTableViewModified($activeView);
    $views = $livewire->getFavoriteTableViews();

    if ($activeView && (! $views->contains($activeView))) {
        $views->push($activeView);
    }
@endphp

<div class="fi-tv-bar">
    @if ($views->isNotEmpty())
        <x-filament::tabs
            :label="__('filament-table-views::table-views.bar.label')"
            class="fi-tv-bar-tabs"
            x-sortable
            x-on:end.stop="$wire.reorderTableViewFavorites($event.target.sortable.toArray())"
        >
            <x-filament::tabs.item
                :active="$activeView === null"
                icon="heroicon-o-table-cells"
                wire:click="resetTableView"
            >
                {{ __('filament-table-views::table-views.bar.default') }}
            </x-filament::tabs.item>

            @foreach ($views as $view)
                @php
                    $isSortable = (! $view->is_global_favorite) && $livewire->isFavoriteTableView($view);
                    $isActive = $activeView?->is($view) ?? false;
                @endphp

                <x-filament::tabs.item
                    :active="$isActive"
                    :wire:key="'table-view-tab-' . $view->getKey()"
                    :wire:click="'applyTableView(' . $view->getKey() . ')'"
                    :x-sortable-item="$isSortable ? $view->getKey() : null"
                    :x-sortable-handle="$isSortable"
                >
                    <span class="fi-tv-bar-tab-label">
                        {{ $view->getIconHtml() }}
                        {{ $view->name }}

                        @if ($isActive && $isActiveViewModified)
                            <span
                                class="fi-tv-modified-dot"
                                title="{{ __('filament-table-views::table-views.bar.modified') }}"
                            >
                                <span class="fi-sr-only">{{ __('filament-table-views::table-views.bar.modified') }}</span>
                            </span>
                        @endif
                    </span>
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @endif

    <x-filament::actions
        :actions="[
            $livewire->updateTableViewAction,
            $livewire->saveTableViewAction,
            $livewire->manageTableViewsAction,
        ]"
        class="fi-tv-bar-actions"
    />
</div>
