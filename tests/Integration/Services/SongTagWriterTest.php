<?php

namespace Tests\Integration\Services;

use App\Models\Song;
use App\Services\SongTagWriter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\test_path;

class SongTagWriterTest extends TestCase
{
    private string $path;

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    #[Test]
    public function writeFlacTagsInPlace(): void
    {
        $this->path = sys_get_temp_dir() . '/' . uniqid('koel-', true) . '.flac';
        copy(test_path('songs/full-vorbis-comments.flac'), $this->path);
        touch($this->path, 1_500_000_000);
        clearstatcache();
        $inode = fileinode($this->path);

        $song = Song::factory()->createOne(['path' => $this->path]);

        self::assertTrue(app(SongTagWriter::class)->write($song, [
            'genre' => 'Köln Rock',
            'title' => 'Neu äöü',
            'lyrics' => "Zeile 1\nZeile 2",
            'year' => null,
        ]));

        clearstatcache();
        self::assertSame($inode, fileinode($this->path));
        self::assertSame(1_500_000_000, filemtime($this->path));

        $tags = $this->readTags();
        self::assertSame('Köln Rock', $tags['GENRE']);
        self::assertSame('Neu äöü', $tags['TITLE']);
        self::assertSame("Zeile 1\nZeile 2", $tags['LYRICS']);
        self::assertArrayNotHasKey('UNSYNCEDLYRICS', $tags);
        self::assertArrayNotHasKey('DATE', $tags);

        // untouched
        self::assertSame('Koel', $tags['ARTIST']);
        self::assertSame('11111111-1111-1111-1111-111111111111', $tags['MUSICBRAINZ_TRACKID']);
        self::assertNotEmpty(shell_exec('metaflac --list --block-type=PICTURE ' . escapeshellarg($this->path)));
    }

    #[Test]
    public function ignoreUnsupportedFiles(): void
    {
        $this->path = sys_get_temp_dir() . '/' . uniqid('koel-', true) . '.ogg';
        file_put_contents($this->path, 'x');

        $song = Song::factory()->createOne(['path' => $this->path]);

        self::assertFalse(app(SongTagWriter::class)->write($song, ['genre' => 'Rock']));
    }

    /** @return array<string, string> */
    private function readTags(): array
    {
        $tags = [];

        foreach (explode(
            "\n",
            trim((string) shell_exec('metaflac --export-tags-to=- ' . escapeshellarg($this->path))),
        ) as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $tags[strtoupper($key)] = $value;
            } elseif (isset($key)) {
                $tags[strtoupper($key)] .= "\n$line";
            }
        }

        return $tags;
    }
}
