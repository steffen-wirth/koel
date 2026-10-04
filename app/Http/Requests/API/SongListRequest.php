<?php

namespace App\Http\Requests\API;

/**
 * @property-read string $order
 * @property-read string $sort
 * @property-read ?string $cursor
 * @property-read ?string $genre
 * @property-read list<string> $formats
 * @property-read ?string $credit
 * @property-read ?string $credit_role
 */
class SongListRequest extends Request
{
    /** @inheritDoc */
    public function rules(): array
    {
        return [
            'sort' => ['sometimes', 'string'],
            'order' => ['sometimes', 'in:asc,desc'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'genre' => ['sometimes', 'nullable', 'string'],
            'formats' => ['sometimes', 'array'],
            'formats.*' => ['in:flac,mp3'],
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credit_role' => ['sometimes', 'nullable', 'string', 'max:64'],
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
