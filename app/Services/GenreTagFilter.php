<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Web tags mix genres with places and release kinds. On some sites genres are lowercase and places capitalized.
 */
class GenreTagFilter
{
    private const IGNORED = [
        'remix',
        'edits',
        're-edits',
        'digital',
        'los angeles',
        'france',
        'new york',
        // places
        'greece',
        'tokyo',
        'uk',
        'british',
        'stockholm',
        'south africa',
        'colombia',
        'tel aviv',
        // formats, decades, instruments and other non-genres
        'vinyl',
        '45s',
        'reissue',
        '60s',
        '70s',
        '80s',
        'digi',
        'tape',
        'analog',
        'diy',
        'rare',
        'party',
        'club',
        'dj',
        'guitar',
        'piano',
        'drums',
        'brass',
        'percussion',
        'vocal',
        'beat',
        'beatmaker',
        'beatmaking',
        'bootlegs',
        'mashup',
        'mashups',
        'remixes',
        'rework',
        'vintage remix',
        'revive',
        'samples',
        'sample-based',
        'edit',
        'disco edit',
        'disco edits',
        'dj edits',
        're-edit',
    ];

    /**
     * @param iterable<string> $tags
     * @param bool $dropCapitalized Treat capitalized tags as places
     *
     * @return list<string>
     */
    public static function filter(iterable $tags, bool $dropCapitalized = true): array
    {
        return collect($tags)
            ->map(static fn (string $tag) => trim(html_entity_decode($tag)))
            ->reject(
                static fn (string $tag) => (
                    !$tag
                    || $dropCapitalized
                    && Str::ucfirst($tag) === $tag
                    && !ctype_digit($tag)
                ),
            )
            ->reject(static fn (string $tag) => in_array(mb_strtolower($tag), self::IGNORED, true))
            ->unique(static fn (string $tag) => mb_strtolower($tag))
            ->values()
            ->all();
    }
}
