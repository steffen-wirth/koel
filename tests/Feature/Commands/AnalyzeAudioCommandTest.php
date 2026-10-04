<?php

namespace Tests\Feature\Commands;

use App\Models\Song;
use App\Services\AudioAnalysisService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\test_path;

class AnalyzeAudioCommandTest extends TestCase
{
    #[Test]
    public function failWhenNotSetUp(): void
    {
        config(['koel.audio_analysis.python' => '/nonexistent/python']);

        $this->artisan('koel:analyze-audio')->assertFailed();
    }

    #[Test]
    public function analyzeOnlySongsWithoutValues(): void
    {
        config([
            'koel.audio_analysis.python' => '/bin/sh',
            'koel.audio_analysis.script' => test_path('songs/full.mp3'),
        ]);

        $done = Song::factory()->createOne(['bpm' => 100, 'musical_key' => 'C']);
        $missingKey = Song::factory()->createOne(['bpm' => 100, 'musical_key' => null]);
        $missingBoth = Song::factory()->createOne();
        Song::factory()->createOne(['length' => 601]);

        $service = $this->mock(AudioAnalysisService::class);
        $service->expects('analyze')->twice()->andReturn(true);

        $this->artisan('koel:analyze-audio')->assertSuccessful();

        self::assertNotNull($done);
        self::assertNotNull($missingKey);
        self::assertNotNull($missingBoth);
    }
}
