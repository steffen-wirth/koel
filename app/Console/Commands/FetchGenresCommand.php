<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Artist;
use App\Services\AlbumGenreService;
use App\Services\Integrations\MusicBrainzService;
use Illuminate\Console\Command;

class FetchGenresCommand extends Command
{
    protected $signature = 'koel:fetch-genres
        {--dry-run : Only show the suggested genres without changing anything}
        {--limit= : Maximum number of albums to process}';

    protected $description = 'Add genres from MusicBrainz to the songs of albums that have songs without any genre.';

    public function __construct(
        private readonly AlbumGenreService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!MusicBrainzService::enabled()) {
            $this->components->error('Please enable the MusicBrainz integration first.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Load the albums upfront: adding genres changes which albums match the condition while iterating.
        $albums = Album::query()
            ->whereNot('name', Album::UNKNOWN_NAME)
            ->whereNotIn('artist_name', [Artist::UNKNOWN_NAME, Artist::VARIOUS_NAME])
            ->whereHas('songs', static fn ($songs) => $songs->whereDoesntHave('genres'))
            ->orderBy('artist_name')
            ->orderBy('name')
            ->when($limit, static fn ($query) => $query->limit($limit))
            ->get();

        $withGenres = 0;
        $genreCount = 0;

        $rows = $albums->map(function (Album $album) use ($dryRun, &$withGenres, &$genreCount): array {
            $genres = $this->service->suggest($album);

            if (!$dryRun && $genres) {
                $this->service->addToSongs($album, $genres);
            }

            if ($genres) {
                $withGenres++;
                $genreCount += count($genres);
            }

            return [$album->artist_name, $album->name, implode(', ', $genres) ?: '—'];
        });

        $this->table(['Artist', 'Album', $dryRun ? 'Suggested genres' : 'Added genres'], $rows->all());
        $this->components->info('Statistics');
        $this->components->twoColumnDetail('Albums processed', (string) $albums->count());
        $this->components->twoColumnDetail(
            $dryRun ? 'Albums with suggestions' : 'Albums with genres added',
            (string) $withGenres,
        );
        $this->components->twoColumnDetail('Albums without any genre found', (string) ($albums->count() - $withGenres));
        $this->components->twoColumnDetail($dryRun ? 'Genres suggested' : 'Genres added', (string) $genreCount);

        $this->components->success($dryRun ? 'Nothing was changed (dry run).' : 'All done!');

        return self::SUCCESS;
    }
}
