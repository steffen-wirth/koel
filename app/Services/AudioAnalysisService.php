<?php

namespace App\Services;

use App\Enums\SongStorageType;
use App\Models\Song;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class AudioAnalysisService
{
    /** Why the last analysis did not succeed, if it did not. */
    private ?string $lastError = null;

    public function __construct(
        private readonly SongTagWriter $tagWriter,
    ) {}

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public static function available(): bool
    {
        return is_executable(config('koel.audio_analysis.python')) && is_file(config('koel.audio_analysis.script'));
    }

    /**
     * Detect the BPM and the key of a song, store them and write them into the file's tags.
     * Failures are logged and never thrown.
     */
    public function analyze(Song $song): bool
    {
        $this->lastError = null;

        if (!static::available()) {
            return $this->fail('the audio analysis is not set up');
        }

        if ($song->isEpisode() || $song->storage !== SongStorageType::LOCAL) {
            return $this->fail('only songs stored locally can be analyzed');
        }

        if ($song->length > config('koel.audio_analysis.max_length')) {
            return $this->fail(
                'the track is longer than ' . (config('koel.audio_analysis.max_length') / 60) . ' minutes',
            );
        }

        if (!is_file($song->path)) {
            return $this->fail('the file was not found at the stored path (is the music directory mounted here?)');
        }

        try {
            $result = Process::timeout(config('koel.audio_analysis.timeout'))->run([
                config('koel.audio_analysis.python'),
                config('koel.audio_analysis.script'),
                $song->path,
            ]);

            throw_unless($result->successful(), new \RuntimeException(trim($result->errorOutput())));

            // Essentia logs to the output as well, the result is the last line.
            $lines = array_filter(array_map('trim', explode("\n", $result->output())));
            $analysis = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);

            $song->update(['bpm' => $analysis['bpm'] ?: null, 'musical_key' => $analysis['key'] ?: null]);
            $this->tagWriter->write($song, ['bpm' => $song->bpm, 'musical_key' => $song->musical_key]);

            return true;
        } catch (Throwable $e) {
            Log::warning("Could not analyze {$song->path}: {$e->getMessage()}");

            return $this->fail($e->getMessage() ?: $e::class);
        }
    }

    private function fail(string $reason): false
    {
        $this->lastError = $reason;

        return false;
    }
}
