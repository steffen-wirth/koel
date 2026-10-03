<?php

namespace App\Http\Requests\API\Album;

use App\Http\Requests\API\Request;

/**
 * @property-read list<string> $genres
 */
class AlbumGenresRequest extends Request
{
    /** @inheritDoc */
    public function rules(): array
    {
        return [
            'genres' => ['required', 'array', 'min:1', 'max:10'],
            'genres.*' => ['required', 'string', 'max:255'],
        ];
    }
}
