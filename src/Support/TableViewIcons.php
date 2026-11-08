<?php

namespace Gerenuk\FilamentTableViews\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\generate_icon_html;

final class TableViewIcons
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $all = null;

    /**
     * Every Heroicon, as stored name => plain-text label, sorted by label.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $icons = [];

        foreach (Heroicon::cases() as $case) {
            if (str_starts_with($case->name, 'Outlined')) {
                $name = substr($case->value, 2);
                $icons["heroicon-o-{$name}"] = (string) str($name)->replace('-', ' ')->ucfirst();
            } else {
                $name = $case->value;
                $icons["heroicon-s-{$name}"] = str($name)->replace('-', ' ')->ucfirst() . ' (solid)';
            }
        }

        asort($icons, SORT_NATURAL | SORT_FLAG_CASE);

        return self::$all = $icons;
    }

    /**
     * @param  array<string, string>|null  $allowed
     * @return array<string, string>
     */
    public static function search(string $search, ?array $allowed = null, int $limit = 50): array
    {
        $words = array_filter(preg_split('/\s+/', mb_strtolower(trim($search))) ?: []);

        $query = mb_strtolower(trim($search));

        $exact = [];
        $others = [];

        foreach ($allowed ?? self::all() as $name => $label) {
            $haystack = mb_strtolower($label) . ' ' . $name;

            foreach ($words as $word) {
                if (! str_contains($haystack, $word)) {
                    continue 2;
                }
            }

            $short = str_replace('heroicon-', '', $name);
            $bare = (string) preg_replace('/^heroicon-[os]-/', '', $name);

            if ($query !== '' && in_array($query, [$name, $short, $bare], true)) {
                $exact[$name] = $label;
            } else {
                $others[$name] = $label;
            }
        }

        return array_slice($exact + $others, 0, $limit, preserve_keys: true);
    }

    public static function optionHtml(string $name, string $label): string
    {
        $icon = '';

        try {
            $icon = generate_icon_html($name, size: IconSize::Small)?->toHtml() ?? '';
        } catch (SvgNotFound) {
        }

        return '<span class="fi-tv-option">' . $icon . e($label) . '</span>';
    }
}
