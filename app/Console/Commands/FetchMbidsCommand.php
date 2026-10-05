<?php

namespace App\Console\Commands;

use App\Http\Integrations\MusicBrainz\MusicBrainzConnector;
use App\Http\Integrations\MusicBrainz\ThrottledMusicBrainzConnector;
use App\Models\Album;
use App\Models\Artist;
use App\Repositories\AlbumRepository;
use App\Repositories\ArtistRepository;
use App\Services\Integrations\MbidService;
use App\Services\Integrations\MusicBrainzService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

class FetchMbidsCommand extends Command
{
    protected $signature = 'koel:fetch-mbids';

    protected $description = 'Attempt to fetch missing MusicBrainz identifiers for albums, artists and songs.';

    public function __construct(
        private readonly MbidService $mbidService,
        private readonly AlbumRepository $albumRepository,
        private readonly ArtistRepository $artistRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!MusicBrainzService::enabled()) {
            $this->error('MusicBrainz is disabled. Enable it before running this command.');

            return self::FAILURE;
        }

        $albumCount = $this->albumRepository->countWithIncompleteMbids();
        $artistCount = $this->artistRepository->countWithoutMbid();

        if ($albumCount === 0 && $artistCount === 0) {
            $this->info('Every album, artist and song already has an identifier.');

            return self::SUCCESS;
        }

        $this->throttleMusicBrainzRequests();

        $stats = [
            'album' => $this->lookUp($this->albumRepository->lazyGetWithIncompleteMbids(), $albumCount, 'album'),
            'artist' => $this->lookUp($this->artistRepository->lazyGetWithoutMbid(), $artistCount, 'artist'),
        ];

        $this->newLine();
        $this->components->info('Statistics');

        foreach ($stats as $noun => $counts) {
            $label = Str::ucfirst(Str::plural($noun));
            $this->components->twoColumnDetail($label . ' processed', (string) array_sum($counts));
            $this->components->twoColumnDetail($label . ' with identifier', (string) $counts['found']);
            $this->components->twoColumnDetail($label . ' without identifier', (string) $counts['missing']);
            $this->components->twoColumnDetail($label . ' failed with error', (string) $counts['errors']);
        }

        $this->newLine();
        $this->info('Done. Run the command again to continue where an interrupted run left off.');

        return self::SUCCESS;
    }

    private function throttleMusicBrainzRequests(): void
    {
        $this->laravel->instance(MusicBrainzConnector::class, new ThrottledMusicBrainzConnector());
    }

    /**
     * @template TEntity of Album|Artist
     *
     * @param LazyCollection<array-key, TEntity> $entities
     *
     * @return array{found: int, missing: int, errors: int}
     */
    private function lookUp(LazyCollection $entities, int $total, string $noun): array
    {
        $this->info(sprintf('Looking up %s.', Str::plural($noun, $total, prependCount: true)));

        $counts = ['found' => 0, 'missing' => 0, 'errors' => 0];
        $progress = $this->output->createProgressBar($total);
        $progress->setFormat(' %current%/%max% [%bar%] %message%');

        foreach ($entities as $entity) {
            $progress->setMessage(self::getLabelForEntity($entity));
            $progress->display();

            $counts[$this->fetchIdentifiersFor($entity)]++;
            $progress->advance();
        }

        $progress->setMessage('');
        $progress->display();
        $progress->finish();
        $this->newLine();

        return $counts;
    }

    /**
     * @return 'found'|'missing'|'errors'
     */
    private function fetchIdentifiersFor(Album|Artist $entity): string
    {
        try {
            if ($entity instanceof Album) {
                $this->mbidService->fetchAndStoreAlbumMbids($entity);
            } else {
                $this->mbidService->fetchAndStoreArtistMbid($entity);
            }

            return $entity->mbid ? 'found' : 'missing';
        } catch (Throwable $e) {
            Log::error($e);
            $this->newLine();
            $this->warn(sprintf('Could not look up "%s": %s', self::getLabelForEntity($entity), $e->getMessage()));

            return 'errors';
        }
    }

    private static function getLabelForEntity(Album|Artist $entity): string
    {
        return $entity instanceof Album ? sprintf('%s - %s', $entity->name, $entity->artist_name) : $entity->name;
    }
}
