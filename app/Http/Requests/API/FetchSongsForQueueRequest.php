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
 * @property-read ?string $credit
 * @property-read ?string $credit_role
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
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credit_role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort' => [
                'required_unless:order,rand',
                Rule::in(array_keys(SongBuilder::SORT_COLUMNS_NORMALIZE_MAP)),
            ],
        ];
    }

    /** @return ?array{role: ?string, name: string} */
    public function credit(): ?array
    {
        return (
            $this->filled('credit')
                ? ['role' => $this->input('credit_role') ?: null, 'name' => $this->input('credit')]
                : null
        );
    }
}
