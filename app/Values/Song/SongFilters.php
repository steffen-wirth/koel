<?php

namespace App\Values\Song;

final class SongFilters
{
    /**
     * @param list<string> $formats File extensions, without the leading dot
     * @param ?string $creditName Songs credited to this person, in $creditRole if given
     */
    private function __construct(
        public readonly ?string $genre,
        public readonly array $formats,
        public readonly ?string $creditName,
        public readonly ?string $creditRole,
        public readonly ?int $bpmMin,
        public readonly ?int $bpmMax,
    ) {}

    /** @param list<string> $formats */
    public static function make(
        ?string $genre = null,
        array $formats = [],
        ?string $creditName = null,
        ?string $creditRole = null,
        ?int $bpmMin = null,
        ?int $bpmMax = null,
    ): self {
        return new self($genre, $formats, $creditName, $creditRole, $bpmMin, $bpmMax);
    }
}
