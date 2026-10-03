<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Song;
use App\Models\User;
use App\Services\Integrations\MusicBrainzService;
use Illuminate\Support\Collection;

class AlbumGenreService
{
    public function __construct(
        private readonly MusicBrainzService $musicBrainz,
        private readonly SongTagWriter $tagWriter,
    ) {}

    /** @return list<string> */
    public function suggest(Album $album): array
    {
        return $this->musicBrainz->getAlbumGenres($album);
    }

    /**
     * Add genres to all the songs of an album (optionally only those the user may edit).
     * The genres the songs already have are kept.
     *
     * @param list<string> $genres
     *
     * @return Collection<int, Song> The songs that actually got new genres
     */
    public function addToSongs(Album $album, array $genres, ?User $user = null): Collection
    {
        return Song::query()
            ->where('album_id', $album->id)
            ->with('genres')
            ->get()
            ->filter(static fn (Song $song) => !$user || $user->can('edit', $song))
            ->filter(function (Song $song) use ($genres): bool {
                $existing = $song->genres->pluck('name');
                $known = $existing->map(static fn (string $name) => mb_strtolower($name));

                $missing = collect($genres)
                    ->map(static fn (string $genre) => trim($genre))
                    ->filter()
                    ->unique(static fn (string $genre) => mb_strtolower($genre))
                    ->reject(static fn (string $genre) => $known->contains(mb_strtolower($genre)));

                if ($missing->isEmpty()) {
                    return false;
                }

                $song->syncGenres($existing->merge($missing)->all());

                return true;
            })
            ->each(function (Song $song): void {
                $song->refresh();
                $this->tagWriter->write($song, ['genre' => $song->genre]);
            })
            ->values();
    }
}
