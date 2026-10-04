<?php

namespace App\Jobs;

use App\Models\Song;
use App\Services\AudioAnalysisService;

class AnalyzeSongsJob extends QueuedJob
{
    public int $timeout = 3600;

    /** @param list<string> $songIds */
    public function __construct(
        private readonly array $songIds,
    ) {}

    public function handle(AudioAnalysisService $service): void
    {
        Song::query()
            ->findMany($this->songIds)
            ->each(static fn (Song $song) => $service->analyze($song));
    }
}
