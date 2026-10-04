<?php

namespace Tests\Integration\Services;

use App\Models\Song;
use App\Services\AudioAnalysisService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\test_path;

class AudioAnalysisServiceTest extends TestCase
{
    private string $python;

    public function setUp(): void
    {
        parent::setUp();

        config(['koel.audio_analysis.script' => test_path('songs/full.mp3')]);
    }

    protected function tearDown(): void
    {
        @unlink($this->python ?? '');

        parent::tearDown();
    }

    /** A stand-in for the Python interpreter that prints the given output and exits with the given code. */
    private function fakePython(string $output, int $exitCode = 0): void
    {
        $this->python = sys_get_temp_dir() . '/' . uniqid('koel-python-', true);
        file_put_contents($this->python, "#!/bin/sh\ncat <<'EOF'\n$output\nEOF\nexit $exitCode\n");
        chmod($this->python, 0o755);

        config(['koel.audio_analysis.python' => $this->python]);
    }

    #[Test]
    public function analyzeStoresBpmAndKeyAndWritesThemToTheFile(): void
    {
        $path = sys_get_temp_dir() . '/' . uniqid('koel-', true) . '.flac';
        copy(test_path('songs/full-vorbis-comments.flac'), $path);
        $song = Song::factory()->createOne(['path' => $path]);

        $this->fakePython('[   INFO   ] noise
{"bpm": 128, "key": "F#m", "key_strength": 0.7}');

        self::assertTrue(app(AudioAnalysisService::class)->analyze($song));

        $tags = (string) shell_exec('metaflac --export-tags-to=- ' . escapeshellarg($path));
        @unlink($path);

        self::assertSame(128, $song->refresh()->bpm);
        self::assertSame('F#m', $song->musical_key);
        self::assertStringContainsString("BPM=128\n", $tags);
        self::assertStringContainsString("INITIALKEY=F#m\n", $tags);
    }

    #[Test]
    public function failedAnalysisLeavesTheSongAlone(): void
    {
        $song = Song::factory()->createOne(['path' => test_path('songs/full.mp3'), 'bpm' => 90]);

        $this->fakePython('boom', 1);

        self::assertFalse(app(AudioAnalysisService::class)->analyze($song));
        self::assertSame(90, $song->refresh()->bpm);
    }

    #[Test]
    public function unavailableWithoutThePythonEnvironment(): void
    {
        config(['koel.audio_analysis.python' => '/nonexistent/python']);

        self::assertFalse(AudioAnalysisService::available());
    }
}
