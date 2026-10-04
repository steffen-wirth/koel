<?php

namespace App\Services\TagEditors;

use App\Models\SongCredit;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class FlacTagEditor
{
    /** Vorbis comment names per tag. The first one is written, all of them are removed to avoid duplicates. */
    private const FIELDS = [
        'title' => ['TITLE'],
        'artist' => ['ARTIST'],
        'album' => ['ALBUM'],
        'album_artist' => ['ALBUMARTIST', 'ALBUM_ARTIST'],
        'track' => ['TRACKNUMBER', 'TRACK'],
        'disc' => ['DISCNUMBER'],
        'year' => ['DATE', 'YEAR'],
        'genre' => ['GENRE'],
        'lyrics' => ['LYRICS', 'UNSYNCEDLYRICS'],
        'mbid' => ['MUSICBRAINZ_TRACKID'],
        'album_mbid' => ['MUSICBRAINZ_ALBUMID'],
        'artist_mbid' => ['MUSICBRAINZ_ARTISTID'],
        'albumartist_mbid' => ['MUSICBRAINZ_ALBUMARTISTID'],
    ];

    /** Credit roles written as their own (repeatable) comment. Instrumentalists and vocalists become PERFORMER. */
    private const CREDIT_FIELDS = [
        'composer' => 'COMPOSER',
        'writer' => 'COMPOSER',
        'lyricist' => 'LYRICIST',
        'producer' => 'PRODUCER',
        'arranger' => 'ARRANGER',
        'conductor' => 'CONDUCTOR',
        'mix' => 'MIXER',
        'engineer' => 'ENGINEER',
        'recording' => 'ENGINEER',
        'remixer' => 'REMIXER',
        'instrument' => 'PERFORMER',
        'vocal' => 'PERFORMER',
        'performer' => 'PERFORMER',
    ];

    /**
     * Edit the given tags of a FLAC file. Other comments and pictures stay untouched.
     * An empty (or zero) value removes the tag.
     *
     * @param array<string, string|int|null|list<SongCredit>> $tags
     */
    public function edit(string $path, array $tags): void
    {
        $args = ['metaflac', '--no-utf8-convert'];

        foreach (array_intersect_key(self::FIELDS, $tags) as $key => $names) {
            foreach ($names as $name) {
                $args[] = "--remove-tag=$name";
            }

            if ($tags[$key]) {
                $args[] = "--set-tag={$names[0]}={$tags[$key]}";
            }
        }

        if (array_key_exists('credits', $tags)) {
            foreach (array_unique(self::CREDIT_FIELDS) as $name) {
                $args[] = "--remove-tag=$name";
            }

            foreach ($this->creditValues($tags['credits']) as [$name, $value]) {
                $args[] = "--set-tag=$name=$value";
            }
        }

        $args[] = $path;

        $result = Process::run($args);

        throw_unless($result->successful(), new RuntimeException('metaflac failed: ' . $result->errorOutput()));
    }

    /**
     * @param iterable<SongCredit> $credits
     *
     * @return list<array{string, string}> Vorbis comment name and value
     */
    private function creditValues(iterable $credits): array
    {
        $values = [];

        foreach ($credits as $credit) {
            $name = self::CREDIT_FIELDS[$credit->role] ?? null;

            if (!$name) {
                continue;
            }

            $values[] = [
                $name,
                $name === 'PERFORMER' && $credit->instrument
                    ? "{$credit->name} ({$credit->instrument})"
                    : $credit->name,
            ];
        }

        return $values;
    }
}
