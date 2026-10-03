<?php

namespace App\Http\Requests\API;

use App\Models\Song;
use App\Values\Song\SongUpdateData;
use Illuminate\Validation\Rule;

/**
 * @property-read array<string> $songs
 * @property-read array<mixed> $data
 */
class SongUpdateRequest extends Request
{
    /** @inheritdoc */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'songs' => ['required', 'array', Rule::exists(Song::class, 'id')->whereNull('podcast_id')],
        ];
    }

    public function toDto(): SongUpdateData
    {
        return SongUpdateData::make(
            title: $this->input('data.title'),
            artistName: $this->input('data.artist_name'),
            albumName: $this->input('data.album_name'),
            albumArtistName: $this->input('data.album_artist_name'),
            track: $this->nullableInt('data.track'),
            disc: $this->nullableInt('data.disc'),
            genre: $this->input('data.genre'),
            year: $this->nullableInt('data.year'),
            lyrics: $this->input('data.lyrics'),
        );
    }

    /** Keep a missing value as null, so it's not mistaken for an explicit 0 (which would overwrite existing data). */
    private function nullableInt(string $key): ?int
    {
        return $this->filled($key) ? (int) $this->input($key) : null;
    }
}
