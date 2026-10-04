<?php

namespace App\Console\Commands;

use App\Enums\PlayableType;
use App\Models\Song;
use App\Services\AudioAnalysisService;
use Illuminate\Console\Command;

class AnalyzeAudioCommand extends Command
{
    protected $signature = 'koel:analyze-audio
        {--force : Analyze every song, not only those without a BPM or key}
        {--limit= : Maximum number of songs to process}';

    protected $description = 'Detect the BPM and the musical key of songs and store them in the database and the files.';

    public function __construct(
        private readonly AudioAnalysisService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!AudioAnalysisService::available()) {
            $this->components->error('The audio analysis is not set up. See "audio_analysis" in config/koel.php.');

            return self::FAILURE;
        }

        $query = Song::query(type: PlayableType::SONG)
            ->when(!$this->option('force'), static fn ($q) => $q->where(
                static fn ($q) => $q->whereNull('songs.bpm')->orWhereNull('songs.musical_key'),
            ))
            ->when($this->option('limit'), static fn ($q, $limit) => $q->limit((int) $limit));

        $songs = $query->get();

        if ($songs->isEmpty()) {
            $this->components->info('Every song already has a BPM and a key.');

            return self::SUCCESS;
        }

        $failed = 0;
        $reasons = [];

        $this->withProgressBar($songs, function (Song $song) use (&$failed, &$reasons): void {
            if (!$this->service->analyze($song)) {
                $failed++;
                $reasons[$this->service->lastError()] = ($reasons[$this->service->lastError()] ?? 0) + 1;
            }
        });

        $this->newLine(2);
        $this->components->info(sprintf('Analyzed %d song(s), %d failed.', $songs->count() - $failed, $failed));

        // File paths differ per song, so only show a few examples.
        foreach (array_slice($reasons, 0, 5, true) as $reason => $count) {
            $this->components->twoColumnDetail($reason, (string) $count);
        }

        return self::SUCCESS;
    }
}
