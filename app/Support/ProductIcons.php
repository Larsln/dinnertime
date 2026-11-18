<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The line icons a product can be pictured with on the shopping list, as
 * listed in the manifest next to the drawings. Both clients bundle them.
 */
final class ProductIcons
{
    private const string MANIFEST = 'icons/products/manifest.json';

    private const string SECTION_PREFIX = 'section-';

    /**
     * @var list<array{key: string, de: string, en: string, section: string, source: string}>|null
     */
    private static ?array $entries = null;

    /**
     * Keys of the icons that picture a product, without the generic
     * icons of the store sections.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::entries(), 'key');
    }

    public static function has(?string $key): bool
    {
        return $key !== null && in_array($key, self::keys(), true);
    }

    /**
     * Key of the icon that carries exactly this name in German or
     * English, ignoring case.
     */
    public static function named(string $name): ?string
    {
        $wanted = mb_strtolower(trim($name));

        foreach (self::entries() as $entry) {
            if (mb_strtolower($entry['de']) === $wanted || mb_strtolower($entry['en']) === $wanted) {
                return $entry['key'];
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, de: string, en: string, section: string, source: string}>
     */
    private static function entries(): array
    {
        return self::$entries ??= array_values(array_filter(
            File::json(resource_path(self::MANIFEST)),
            fn (array $entry) => ! str_starts_with($entry['key'], self::SECTION_PREFIX),
        ));
    }
}
