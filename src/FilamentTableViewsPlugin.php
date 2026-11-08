<?php

namespace Gerenuk\FilamentTableViews;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Gerenuk\FilamentTableViews\Resources\TableViewResource;
use Gerenuk\FilamentTableViews\Support\TableViewIcons;
use Illuminate\Contracts\Auth\Authenticatable;

class FilamentTableViewsPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool | Closure $canManageGlobalFavorites = false;

    protected bool | Closure $canManageUserViews = false;

    protected ?string $userViewsNavigationGroup = null;

    /** @var array<int, string> | Closure */
    protected array | Closure $colors = ['gray', 'primary', 'info', 'success', 'warning', 'danger'];

    /** @var array<int, string | Heroicon> | Closure | null */
    protected array | Closure | null $icons = null;

    public function getId(): string
    {
        return 'filament-table-views';
    }

    public function register(Panel $panel): void
    {
        if ($this->hasUserViews()) {
            $panel->resources([TableViewResource::class]);
        }
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = Filament::getPlugin(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Who may make a view a favourite for everyone. The closure receives `$user`.
     */
    public function globalFavorites(bool | Closure $condition = true): static
    {
        $this->canManageGlobalFavorites = $condition;

        return $this;
    }

    /**
     * Add the User Views page, where these users can manage every saved view. The closure receives `$user`.
     */
    public function userViews(bool | Closure $condition = true): static
    {
        $this->canManageUserViews = $condition;

        return $this;
    }

    public function canManageUserViews(?Authenticatable $user): bool
    {
        return $user !== null && (bool) $this->evaluate($this->canManageUserViews, [
            'user' => $user,
        ], [
            Authenticatable::class => $user,
        ]);
    }

    public function hasUserViews(): bool
    {
        return $this->canManageUserViews !== false;
    }

    public function userViewsNavigationGroup(?string $group): static
    {
        $this->userViewsNavigationGroup = $group;

        return $this;
    }

    public function getUserViewsNavigationGroup(): ?string
    {
        return $this->userViewsNavigationGroup;
    }

    /**
     * The colours offered when saving a view. Use Filament colour names.
     *
     * @param  array<int, string> | Closure  $colors
     */
    public function colors(array | Closure $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getColors(): array
    {
        /** @var array<int, mixed> $colors */
        $colors = $this->evaluate($this->colors);

        return array_values(array_filter(
            $colors,
            fn (mixed $color): bool => is_string($color) && preg_match('/^[a-z]+$/', $color) === 1,
        ));
    }

    /**
     * Limit the icons offered when saving a view. Leave unset for every Heroicon.
     *
     * @param  array<int, string | Heroicon> | Closure | null  $icons
     */
    public function icons(array | Closure | null $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /**
     * @return array<string, string> | null Stored name => label, or null for all.
     */
    public function getIcons(): ?array
    {
        /** @var array<int, string | Heroicon> | null $icons */
        $icons = $this->evaluate($this->icons);

        if ($icons === null) {
            return null;
        }

        $all = TableViewIcons::all();
        $allowed = [];

        foreach ($icons as $icon) {
            if ($icon instanceof Heroicon) {
                $icon = str_starts_with($icon->name, 'Outlined')
                    ? 'heroicon-' . $icon->value
                    : 'heroicon-s-' . $icon->value;
            }

            if (isset($all[$icon])) {
                $allowed[$icon] = $all[$icon];
            }
        }

        return $allowed;
    }

    public function canManageGlobalFavorites(?Authenticatable $user): bool
    {
        return $user !== null && (bool) $this->evaluate($this->canManageGlobalFavorites, [
            'user' => $user,
        ], [
            Authenticatable::class => $user,
        ]);
    }
}
