<?php

namespace App\Values\SmartPlaylist;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Playlist-wide narrowing of a smart playlist, applied on top of its rule groups: a genre, a BPM range, a maximum
 * number of songs and whether those are picked at random.
 */
final readonly class SmartPlaylistSelection implements Arrayable
{
    private function __construct(
        public ?string $genre,
        public ?int $bpmMin,
        public ?int $bpmMax,
        public ?int $maxSongs,
        public bool $randomize,
    ) {}

    public static function make(
        ?string $genre = null,
        ?int $bpmMin = null,
        ?int $bpmMax = null,
        ?int $maxSongs = null,
        bool $randomize = false,
    ): self {
        return new self($genre ?: null, $bpmMin, $bpmMax, $maxSongs, $randomize);
    }

    /** @param array<string, mixed>|null $data */
    public static function tryFrom(?array $data): ?self
    {
        if (!$data) {
            return null;
        }

        $number = static fn (string $key): ?int => ($data[$key] ?? '') === '' ? null : (int) $data[$key];

        $selection = self::make(
            genre: isset($data['genre']) ? trim((string) $data['genre']) : null,
            bpmMin: $number('bpm_min'),
            bpmMax: $number('bpm_max'),
            maxSongs: $number('max_songs'),
            randomize: (bool) ($data['randomize'] ?? false),
        );

        return $selection->isEmpty() ? null : $selection;
    }

    public function isEmpty(): bool
    {
        return (
            !$this->genre
            && $this->bpmMin === null
            && $this->bpmMax === null
            && $this->maxSongs === null
            && !$this->randomize
        );
    }

    /** @inheritdoc */
    public function toArray(): array
    {
        return [
            'genre' => $this->genre,
            'bpm_min' => $this->bpmMin,
            'bpm_max' => $this->bpmMax,
            'max_songs' => $this->maxSongs,
            'randomize' => $this->randomize,
        ];
    }
}
