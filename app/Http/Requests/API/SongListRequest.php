<?php

namespace App\Http\Requests\API;

use App\Http\Requests\API\Concerns\HasSongFilters;

/**
 * @property-read string $order
 * @property-read string $sort
 * @property-read ?string $cursor
 */
class SongListRequest extends Request
{
    use HasSongFilters;

    /** @inheritDoc */
    public function rules(): array
    {
        return [
            ...$this->songFilterRules(),
            'sort' => ['sometimes', 'string'],
            'order' => ['sometimes', 'in:asc,desc'],
            'cursor' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
