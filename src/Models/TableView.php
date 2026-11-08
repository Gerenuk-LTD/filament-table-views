<?php

namespace Gerenuk\FilamentTableViews\Models;

use BladeUI\Icons\Exceptions\SvgNotFound;
use Filament\Support\View\ComponentAttributeBag;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use function Filament\Support\generate_icon_html;

/**
 * @property int $id
 * @property int|string $user_id
 * @property ?string $tenant_type
 * @property ?string $tenant_id
 * @property string $resource
 * @property string $name
 * @property ?string $icon
 * @property ?string $color
 * @property array{filters?: ?array<string, mixed>, sort?: ?string, search?: ?string, columns?: array<int, array<string, mixed>>} $state
 * @property bool $is_public
 * @property bool $is_global_favorite
 * @property int $sort_order
 */
class TableView extends Model
{
    protected $guarded = [];

    public const string DEFAULT_ICON = 'heroicon-o-table-cells';

    protected static function booted(): void
    {
        static::saving(function (TableView $view): void {
            if ($view->is_global_favorite) {
                $view->is_public = true;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'is_public' => 'boolean',
            'is_global_favorite' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(static::getUserModel(), 'user_id');
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(static::getUserModel(), 'table_view_favorites', 'table_view_id', 'user_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForResource(Builder $query, string $resource): void
    {
        $query->where('resource', $resource);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, ?Authenticatable $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user?->getAuthIdentifier())
            ->orWhere('is_public', true));
    }

    /**
     * Views made outside a tenant have no tenant, so panels without tenancy keep seeing them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForTenant(Builder $query, ?Model $tenant): void
    {
        if ($tenant === null) {
            $query->whereNull('tenant_id');

            return;
        }

        $query
            ->where('tenant_type', $tenant->getMorphClass())
            ->where('tenant_id', (string) $tenant->getKey());
    }

    public function getIconHtml(): ?Htmlable
    {
        $color = preg_match('/^[a-z]+$/', (string) $this->color) ? $this->color : null;
        // Icons are stored strings; a bad one must not break every page that lists the view.
        $icon = preg_match('/^[a-z0-9-]+$/', (string) $this->icon) ? $this->icon : null;

        $attributes = new ComponentAttributeBag([
            'class' => 'fi-tv-icon',
            'style' => $color ? "--tv-icon-color: var(--{$color}-500); --tv-icon-dark-color: var(--{$color}-400)" : null,
        ]);

        try {
            return generate_icon_html($icon ?? static::DEFAULT_ICON, attributes: $attributes);
        } catch (SvgNotFound) {
            return generate_icon_html(static::DEFAULT_ICON, attributes: $attributes);
        }
    }

    public function isOwnedBy(?Authenticatable $user): bool
    {
        return $user !== null && ((string) $this->user_id === (string) $user->getAuthIdentifier());
    }

    /**
     * @return class-string<Model>
     */
    public static function getUserModel(): string
    {
        return config('filament-table-views.user_model') ?? config('auth.providers.users.model');
    }
}
