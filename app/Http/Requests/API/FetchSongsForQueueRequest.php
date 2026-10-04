<?php

namespace App\Http\Requests\API;

use App\Builders\SongBuilder;
use App\Http\Requests\API\Concerns\HasSongFilters;
use Illuminate\Validation\Rule;

/**
 * @property-read string|null $sort
 * @property-read string $order
 * @property-read int $limit
 */
class FetchSongsForQueueRequest extends Request
{
    use HasSongFilters;

    /** @inheritdoc */
    public function rules(): array
    {
        return [
            ...$this->songFilterRules(),
            'order' => ['required', Rule::in('asc', 'desc', 'rand')],
            'limit' => ['required', 'integer', 'min:1'],
            'sort' => [
                'required_unless:order,rand',
                Rule::in(array_keys(SongBuilder::SORT_COLUMNS_NORMALIZE_MAP)),
            ],
        ];
    }
}
