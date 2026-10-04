<?php

namespace App\Http\Requests\API;

/**
 * @property-read string $title
 * @property-read ?string $artist
 * @property-read ?string $album
 */
class SongMusicBrainzLookupRequest extends Request
{
    /** @inheritDoc */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'string', 'max:255'],
        ];
    }
}
