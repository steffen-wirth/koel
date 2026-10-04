<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $song_id
 * @property string $role The MusicBrainz relationship type, e.g. composer, producer, instrument
 * @property string $name
 * @property ?string $instrument
 * @property ?string $artist_mbid
 */
#[Fillable(['song_id', 'role', 'name', 'instrument', 'artist_mbid'])]
#[WithoutTimestamps]
class SongCredit extends Model
{
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
