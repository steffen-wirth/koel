<?php

namespace App\Services;

use App\Enums\SongStorageType;
use App\Models\Song;
use App\Services\TagEditors\FlacTagEditor;
use App\Services\TagEditors\Id3v2TagEditor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SongTagWriter
{
    public function __construct(
        private readonly FlacTagEditor $flacEditor,
        private readonly Id3v2TagEditor $id3Editor,
    ) {}

    /**
     * Write the given tags into the song's file, if it is a local file in a supported format.
     * The file keeps its inode and modification time. Failures are logged and never thrown.
     *
     * @param array<string, string|int|null> $tags
     */
    public function write(Song $song, array $tags): bool
    {
        if (!$tags || $song->isEpisode() || $song->storage !== SongStorageType::LOCAL) {
            return false;
        }

        $path = $song->path;
        $editor = match (Str::lower(pathinfo($path, PATHINFO_EXTENSION))) {
            'flac' => $this->flacEditor,
            'mp3' => $this->id3Editor,
            default => null,
        };

        if (!$editor || !is_file($path) || !is_writable($path)) {
            return false;
        }

        $temp = tempnam(sys_get_temp_dir(), 'koel-tags-');

        try {
            $mtime = filemtime($path);
            $atime = fileatime($path);

            throw_unless(copy($path, $temp), new RuntimeException('Could not copy the file to a temporary location.'));

            $editor->edit($temp, $tags);
            $this->copyBack($temp, $path);

            // Writing the tags is not a content change from the library's point of view.
            touch($path, $mtime, $atime);

            return true;
        } catch (Throwable $e) {
            Log::warning("Could not write tags to $path: {$e->getMessage()}");

            return false;
        } finally {
            @unlink($temp);
        }
    }

    /**
     * Overwrite the original's content in place, so it keeps its inode (and birth time).
     */
    private function copyBack(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($target, 'r+b');

        throw_unless($in && $out, new RuntimeException('Could not open the files for writing.'));

        try {
            stream_copy_to_stream($in, $out);
            ftruncate($out, ftell($out));
        } finally {
            fclose($in);
            fclose($out);
        }
    }
}
