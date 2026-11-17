<?php

namespace App\Support;

/**
 * Reverse lookup for the translated names of built-in reference data.
 */
final class ReferenceTranslations
{
    /**
     * Find the key of a built-in entry by one of its labels in any supported
     * locale, ignoring case.
     *
     * @param  string  $group  Translation group, e.g. "reference.units".
     */
    public static function findKey(string $group, string $label): ?string
    {
        $needle = mb_strtolower($label);

        foreach (config('app.supported_locales') as $locale) {
            foreach (trans($group, [], $locale) as $key => $labels) {
                $candidates = array_map(fn (string $candidate) => mb_strtolower($candidate), (array) $labels);

                if (in_array($needle, $candidates, true)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
