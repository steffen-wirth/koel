<?php

namespace App\Casts;

use App\Values\SmartPlaylist\SmartPlaylistSelection;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class SmartPlaylistSelectionCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?SmartPlaylistSelection
    {
        return SmartPlaylistSelection::tryFrom($value ? json_decode($value, true) : null);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if (is_array($value)) {
            $value = SmartPlaylistSelection::tryFrom($value);
        }

        return $value ? json_encode($value->toArray()) : null;
    }
}
