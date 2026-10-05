<?php

namespace App\Console\Commands;

use App\Enums\PlayableType;
use App\Models\Song;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class BackfillFileCreatedAtCommand extends Command
{
    protected $signature = 'koel:backfill-file-created-at
        {--force : Overwrite the file creation date of every locally stored song, not only those without one}';

    protected $description = 'Fill in the file creation date of locally stored songs that do not have one yet.';

    public function handle(): int
    {
        $updated = 0;
        $missing = 0;

        Song::query(type: PlayableType::SONG)
            ->storedLocally()
            ->when(!$this->option('force'), static fn ($q) => $q->whereNull('songs.file_created_at'))
            ->select('songs.id', 'songs.path')
            ->chunkById(
                500,
                function ($songs) use (&$updated, &$missing): void {
                    foreach ($songs as $song) {
                        if (!File::exists($song->path)) {
                            $missing++;

                            continue;
                        }

                        // Bypass the model so that updated_at and the search index stay untouched.
                        Song::query()
                            ->whereKey($song->id)
                            ->toBase()
                            ->update([
                                'file_created_at' => Carbon::createFromTimestamp(get_file_creation_time($song->path)),
                            ]);

                        $updated++;
                    }
                },
                'songs.id',
                'id',
            );

        $this->components->info(sprintf('Updated %d song(s), %d file(s) not found.', $updated, $missing));

        return self::SUCCESS;
    }
}
