<?php

namespace App\Http\Requests\API;

use App\Builders\SongBuilder;
use Illuminate\Validation\Rule;

/**
 * @property-read string|null $sort
 * @property-read string $order
 * @property-read int $limit
 * @property-read ?string $genre
 * @property-read list<string> $formats
 */
class FetchSongsForQueueRequest extends Request
{
    /** @inheritdoc */
    public function rules(): array
    {
        return [
            'order' => ['required', Rule::in('asc', 'desc', 'rand')],
            'limit' => ['required', 'integer', 'min:1'],
            'genre' => ['sometimes', 'nullable', 'string'],
            'formats' => ['sometimes', 'array'],
            'formats.*' => ['in:flac,mp3'],
            'sort' => [
                'required_unless:order,rand',
                Rule::in(array_keys(SongBuilder::SORT_COLUMNS_NORMALIZE_MAP)),
            ],
        ];
    }
}
