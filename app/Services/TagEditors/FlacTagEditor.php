<?php

namespace App\Services\TagEditors;

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
    ];

    /**
     * Edit the given tags of a FLAC file. Other comments and pictures stay untouched.
     * An empty (or zero) value removes the tag.
     *
     * @param array<string, string|int|null> $tags
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

        $args[] = $path;

        $result = Process::run($args);

        throw_unless($result->successful(), new RuntimeException('metaflac failed: ' . $result->errorOutput()));
    }
}
