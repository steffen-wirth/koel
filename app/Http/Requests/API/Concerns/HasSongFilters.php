<?php

namespace App\Http\Requests\API\Concerns;

use App\Values\Song\SongFilters;

trait HasSongFilters
{
    /** @return array<string, mixed> */
    protected function songFilterRules(): array
    {
        return [
            'genre' => ['sometimes', 'nullable', 'string'],
            'formats' => ['sometimes', 'array'],
            'formats.*' => ['in:flac,mp3'],
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credit_role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'bpm_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'bpm_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function songFilters(): SongFilters
    {
        return SongFilters::make(
            genre: $this->input('genre') ?: null,
            formats: $this->input('formats', []),
            creditName: $this->input('credit') ?: null,
            creditRole: $this->input('credit_role') ?: null,
            bpmMin: $this->filled('bpm_min') ? (int) $this->input('bpm_min') : null,
            bpmMax: $this->filled('bpm_max') ? (int) $this->input('bpm_max') : null,
        );
    }
}
