<?php

namespace App\Services;

use App\Models\Genre;

/**
 * Unifies genres suggested by external sources: configured aliases win, then the spelling of a genre
 * the library already has is reused, and anything else is title-cased.
 */
class GenreNormalizer
{
    /**
     * @param list<string> $genres
     *
     * @return list<string>
     */
    public function normalize(array $genres): array
    {
        if (!$genres) {
            return [];
        }

        $canonical = [];

        foreach (config('koel.genre_aliases', []) as $name => $aliases) {
            foreach ([$name, ...$aliases] as $alias) {
                $canonical[self::key($alias)] = $name;
            }
        }

        $known = Genre::query()
            ->pluck('name')
            ->mapWithKeys(static fn (string $name) => [self::key($name) => $name]);

        return collect($genres)
            ->map(
                static fn (string $genre) => (
                    $canonical[self::key($genre)] ?? $known[self::key($genre)] ?? mb_convert_case($genre, MB_CASE_TITLE)
                ),
            )
            ->unique(static fn (string $genre) => mb_strtolower($genre))
            ->values()
            ->all();
    }

    private static function key(string $genre): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', str_replace('&', 'and', mb_strtolower($genre))) ?? '';
    }
}
