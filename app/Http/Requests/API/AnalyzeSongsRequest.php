<?php

namespace App\Http\Requests\API;

use App\Models\Song;
use Illuminate\Validation\Rule;

/**
 * @property-read list<string> $songs
 */
class AnalyzeSongsRequest extends Request
{
    /** @inheritDoc */
    public function rules(): array
    {
        return [
            'songs' => ['required', 'array', 'max:1000'],
            'songs.*' => ['string', Rule::exists(Song::class, 'id')->whereNull('podcast_id')],
        ];
    }
}
