<?php

namespace Tests\Integration\Services;

use App\Models\Song;
use App\Services\SongTagWriter;
use getID3;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Id3v2TagEditorTest extends TestCase
{
    private const AUDIO = "\xFF\xFB\x90\x00AUDIO-DATA-AUDIO-DATA";

    private string $path;

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /** @return array<string, array{int}> */
    public static function provideVersions(): array
    {
        return ['v2.3' => [3], 'v2.4' => [4]];
    }

    #[Test]
    #[DataProvider('provideVersions')]
    public function editFramesAndKeepTheRest(int $version): void
    {
        $untouched = [
            $this->frame($version, 'UFID', "http://musicbrainz.org\x00" . 'abc-123'),
            $this->frame($version, 'APIC', "\x00image/png\x00\x03\x00" . 'PNGDATA'),
            $this->frame($version, 'TXXX', "\x00MusicBrainz Album Id\x00album-id"),
            $this->frame($version, 'TPE1', "\x00Koel"),
        ];

        $this->path = $this->makeFile(
            $version,
            [
                $this->frame($version, 'TIT2', "\x00Old title"),
                $this->frame($version, 'TCON', "\x00Blues"),
                $this->frame($version, 'USLT', "\x00eng\x00Old lyrics"),
                $this->frame($version, 'SYLT', "\x00eng\x02\x01\x00Hello\x00\x00\x00\x00\x00"),
                ...$untouched,
            ],
            64,
        );

        touch($this->path, 1_500_000_000);
        clearstatcache();
        $inode = fileinode($this->path);

        $song = Song::factory()->createOne(['path' => $this->path]);

        self::assertTrue(app(SongTagWriter::class)->write($song, [
            'title' => 'Neu äöü €',
            'genre' => 'Köln Rock',
            'lyrics' => "Zeile 1\nZeile 2",
            'track' => 5,
            'year' => 2015,
            'album_artist' => '',
        ]));

        clearstatcache();
        self::assertSame($inode, fileinode($this->path));
        self::assertSame(1_500_000_000, filemtime($this->path));

        $contents = file_get_contents($this->path);
        self::assertStringEndsWith(self::AUDIO, $contents);

        foreach ($untouched as $frame) {
            self::assertStringContainsString($frame, $contents);
        }

        $info = (new getID3())->analyze($this->path);
        $tags = $info['tags']['id3v2'];

        self::assertSame(['Neu äöü €'], $tags['title']);
        self::assertSame(['Köln Rock'], $tags['genre']);
        self::assertSame(['5'], $tags['track_number']);
        self::assertSame(['2015'], $tags['year']);
        self::assertSame(['Koel'], $tags['artist']);
        self::assertSame(["Zeile 1\nZeile 2"], $tags['unsynchronised_lyric']);
        self::assertArrayNotHasKey('synchronised_lyric', $info['id3v2']);
        self::assertArrayNotHasKey('SYLT', $info['id3v2']);
    }

    #[Test]
    public function keepTagSizeWhenFramesStillFit(): void
    {
        $this->path = $this->makeFile(3, [$this->frame(3, 'TCON', "\x00Blues")], 512);
        $size = filesize($this->path);

        $song = Song::factory()->createOne(['path' => $this->path]);
        app(SongTagWriter::class)->write($song, ['genre' => 'Rock']);

        clearstatcache();
        self::assertSame($size, filesize($this->path));
    }

    #[Test]
    public function addTagToFileWithoutOne(): void
    {
        $this->path = sys_get_temp_dir() . '/' . uniqid('koel-', true) . '.mp3';
        file_put_contents($this->path, self::AUDIO);

        $song = Song::factory()->createOne(['path' => $this->path]);

        self::assertTrue(app(SongTagWriter::class)->write($song, ['genre' => 'Rock']));
        self::assertSame(['Rock'], (new getID3())->analyze($this->path)['tags']['id3v2']['genre']);
        self::assertStringEndsWith(self::AUDIO, file_get_contents($this->path));
    }

    #[Test]
    public function skipUnsupportedId3v22(): void
    {
        $this->path = sys_get_temp_dir() . '/' . uniqid('koel-', true) . '.mp3';
        copy(base_path('tests/songs/full.mp3'), $this->path);
        $original = file_get_contents($this->path);

        $song = Song::factory()->createOne(['path' => $this->path]);

        self::assertFalse(app(SongTagWriter::class)->write($song, ['genre' => 'Rock']));
        self::assertSame($original, file_get_contents($this->path));
    }

    private function frame(int $version, string $id, string $body): string
    {
        $size = $version === 4 ? $this->syncsafe(strlen($body)) : pack('N', strlen($body));

        return $id . $size . "\x00\x00" . $body;
    }

    private function syncsafe(int $n): string
    {
        return chr(($n >> 21) & 0x7F) . chr(($n >> 14) & 0x7F) . chr(($n >> 7) & 0x7F) . chr($n & 0x7F);
    }

    /** @param list<string> $frames */
    private function makeFile(int $version, array $frames, int $padding): string
    {
        $body = implode('', $frames) . str_repeat("\x00", $padding);
        $path = sys_get_temp_dir() . '/' . uniqid('koel-', true) . '.mp3';

        file_put_contents(
            $path,
            'ID3' . chr($version) . "\x00\x00" . $this->syncsafe(strlen($body)) . $body . self::AUDIO,
        );

        return $path;
    }
}
