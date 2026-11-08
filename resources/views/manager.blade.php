@php
    use Filament\Facades\Filament;
    $user = Filament::auth()->user();
@endphp

<div class="fi-tv-manager">
    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
        <x-filament::input
            type="search"
            wire:model.live.debounce.300ms="tableViewSearch"
            :placeholder="__('filament-table-views::table-views.manager.search')"
        />
    </x-filament::input.wrapper>

    @if ($views->isEmpty())
        <p class="fi-tv-manager-empty">
            {{ __('filament-table-views::table-views.manager.empty') }}
        </p>
    @else
        <ul class="fi-tv-manager-list">
            @foreach ($views as $view)
                <li
                    wire:key="table-view-manager-{{ $view->getKey() }}"
                    @class([
                        'fi-tv-manager-item',
                        'fi-active' => $livewire->activeTableViewId === $view->getKey(),
                    ])
                >
                    <button
                        type="button"
                        class="fi-tv-manager-item-apply"
                        wire:click="{{ ($action->getModalAction('applyTableView'))(['view' => $view->getKey()])->getLivewireClickHandler() }}"
                    >
                        <span class="fi-tv-manager-item-name">
                            {{ $view->getIconHtml() }}
                            {{ $view->name }}
                        </span>

                        <span class="fi-tv-manager-item-meta">
                            @if ($view->isOwnedBy($user))
                                {{ $view->is_public ? __('filament-table-views::table-views.manager.yours_public') : __('filament-table-views::table-views.manager.yours_private') }}
                            @else
                                {{ __('filament-table-views::table-views.manager.shared_by', ['name' => $view->user ? Filament::getUserName($view->user) : '?']) }}
                            @endif
                        </span>
                    </button>

                    <x-filament::actions
                        :actions="[
                            ($action->getModalAction('toggleTableViewFavorite'))(['view' => $view->getKey()]),
                            ($action->getModalAction('editTableView'))(['view' => $view->getKey()]),
                            ($action->getModalAction('deleteTableView'))(['view' => $view->getKey()]),
                        ]"
                        class="fi-tv-manager-item-actions"
                    />
                </li>
            @endforeach
        </ul>
    @endif
</div>
