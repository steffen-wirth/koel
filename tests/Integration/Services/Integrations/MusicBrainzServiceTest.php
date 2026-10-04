<?php

namespace Tests\Integration\Services\Integrations;

use App\Http\Integrations\MusicBrainz\Requests\GetRecordingCreditsRequest;
use App\Http\Integrations\MusicBrainz\Requests\SearchForRecordingRequest;
use App\Models\Album;
use App\Models\Artist;
use App\Pipelines\Encyclopedia\GetAlbumTracksUsingMbid;
use App\Pipelines\Encyclopedia\GetAlbumWikidataIdUsingReleaseGroupMbid;
use App\Pipelines\Encyclopedia\GetArtistWikidataIdUsingMbid;
use App\Pipelines\Encyclopedia\GetGenresUsingReleaseGroupMbid;
use App\Pipelines\Encyclopedia\GetMbidForArtist;
use App\Pipelines\Encyclopedia\GetReleaseAndReleaseGroupMbidsForAlbum;
use App\Pipelines\Encyclopedia\GetReleaseGroupMbidUsingReleaseMbid;
use App\Pipelines\Encyclopedia\GetWikipediaPageSummaryUsingPageTitle;
use App\Pipelines\Encyclopedia\GetWikipediaPageTitleUsingWikidataId;
use App\Services\Integrations\MusicBrainzService;
use App\Values\Album\AlbumInformation;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;
use Throwable;

use function Tests\create_user;
use function Tests\test_path;

class MusicBrainzServiceTest extends TestCase
{
    private MusicBrainzService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = app(MusicBrainzService::class);
    }

    private function mockPipelinePipe(string $class, mixed $input, mixed $output): void
    {
        $expectation = $this
            ->mock($class)
            ->expects('__invoke')
            ->with($input, Mockery::on(is_callable(...)));

        if ($output instanceof Throwable) {
            $expectation->andThrow($output);
        } else {
            $expectation->andReturnUsing(static fn ($_, $next) => $next($output));
        }
    }

    #[Test]
    public function getArtistInformation(): void
    {
        $this->mockPipelinePipe(GetMbidForArtist::class, 'Skid Row', 'sample-mbid');
        $this->mockPipelinePipe(GetArtistWikidataIdUsingMbid::class, 'sample-mbid', 'Q123456');
        $this->mockPipelinePipe(GetWikipediaPageTitleUsingWikidataId::class, 'Q123456', 'Skid Row (American band)');

        $this->mockPipelinePipe(
            GetWikipediaPageSummaryUsingPageTitle::class,
            'Skid Row (American band)',
            File::json(test_path('fixtures/wikipedia/artist-page-summary.json')),
        );

        $artist = Artist::factory()->createOne(['name' => 'Skid Row']);

        $info = $this->service->getArtistInformation($artist);

        self::assertSame(
            [
                'url' => 'https://en.wikipedia.org/wiki/Skid_Row_(American_band)',
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3b/2023_Sweden_Rock_-_3330_%2853049443466%29.jpg/330px-2023_Sweden_Rock_-_3330_%2853049443466%29.jpg',
                'bio' => [
                    'summary' => 'Skid Row is an American rock band formed in 1986…',
                    'full' => '<p><b>Skid Row</b> is an American rock band formed in 1986…</p>',
                ],
            ],
            $info->toArray(),
        );
    }

    #[Test]
    public function getArtistInformationReturnsNullUponAnyErrorInThePipeline(): void
    {
        $this->mockPipelinePipe(GetMbidForArtist::class, 'Skid Row', 'sample-mbid');

        $this->mockPipelinePipe(
            GetArtistWikidataIdUsingMbid::class,
            'sample-mbid',
            new Exception('Something went wrong'),
        );
        $artist = Artist::factory()->createOne(['name' => 'Skid Row']);

        self::assertNull($this->service->getArtistInformation($artist));
    }

    #[Test]
    public function getAlbumGenres(): void
    {
        $this->mockPipelinePipe(
            GetReleaseAndReleaseGroupMbidsForAlbum::class,
            ['album' => 'Slave to the Grind', 'artist' => 'Skid Row'],
            ['sample-album-mbid', 'sample-release-group-mbid'],
        );
        $this->mockPipelinePipe(GetGenresUsingReleaseGroupMbid::class, 'sample-release-group-mbid', ['Rock', 'Metal']);

        $album = Album::factory()->for(Artist::factory()->createOne(['name' => 'Skid Row']))->createOne([
            'name' => 'Slave to the Grind',
        ]);

        self::assertSame(['Rock', 'Metal'], $this->service->getAlbumGenres($album));
    }

    #[Test]
    public function getAlbumGenresReturnsEmptyUponAnyError(): void
    {
        $this->mockPipelinePipe(
            GetReleaseAndReleaseGroupMbidsForAlbum::class,
            ['album' => 'Slave to the Grind', 'artist' => 'Skid Row'],
            new Exception('Something went wrong'),
        );

        $album = Album::factory()->for(Artist::factory()->createOne(['name' => 'Skid Row']))->createOne([
            'name' => 'Slave to the Grind',
        ]);

        self::assertSame([], $this->service->getAlbumGenres($album));
    }

    #[Test]
    public function getAlbumInformation(): void
    {
        $recordingsJson = File::json(test_path('fixtures/musicbrainz/recordings.json'));

        $tracks = [];

        foreach (Arr::get($recordingsJson, 'media', []) as $media) {
            array_push($tracks, ...Arr::get($media, 'tracks', []));
        }

        $this->mockPipelinePipe(
            GetReleaseAndReleaseGroupMbidsForAlbum::class,
            [
                'album' => 'Slave to the Grind',
                'artist' => 'Skid Row',
            ],
            ['sample-album-mbid', 'sample-release-group-mbid'],
        );

        $this->mockPipelinePipe(GetAlbumTracksUsingMbid::class, 'sample-album-mbid', $tracks);
        $this->mockPipelinePipe(GetAlbumWikidataIdUsingReleaseGroupMbid::class, 'sample-release-group-mbid', 'Q123456');
        $this->mockPipelinePipe(GetWikipediaPageTitleUsingWikidataId::class, 'Q123456', 'Slave to the Grind');

        $this->mockPipelinePipe(
            GetWikipediaPageSummaryUsingPageTitle::class,
            'Slave to the Grind',
            File::json(test_path('fixtures/wikipedia/album-page-summary.json')),
        );

        $user = create_user();
        $album = Artist::factory()
            ->for($user)
            ->createOne(['name' => 'Skid Row'])
            ->albums() // @phpstan-ignore-line
            ->create([
                'name' => 'Slave to the Grind',
                'user_id' => $user->id,
            ]);

        self::assertInstanceOf(AlbumInformation::class, $this->service->getAlbumInformation($album));

        // eh, good enough
    }

    #[Test]
    public function getAlbumInformationUsesAKnownReleaseIdentifierInsteadOfSearching(): void
    {
        $this->mock(GetReleaseAndReleaseGroupMbidsForAlbum::class)->shouldNotReceive('__invoke');

        $this->mockPipelinePipe(
            GetReleaseGroupMbidUsingReleaseMbid::class,
            'release-mbid-from-tags',
            'sample-release-group-mbid',
        );

        $this->mockPipelinePipe(GetAlbumTracksUsingMbid::class, 'release-mbid-from-tags', []);
        $this->mockPipelinePipe(GetAlbumWikidataIdUsingReleaseGroupMbid::class, 'sample-release-group-mbid', 'Q123456');
        $this->mockPipelinePipe(GetWikipediaPageTitleUsingWikidataId::class, 'Q123456', 'Slave to the Grind');

        $this->mockPipelinePipe(
            GetWikipediaPageSummaryUsingPageTitle::class,
            'Slave to the Grind',
            File::json(test_path('fixtures/wikipedia/album-page-summary.json')),
        );

        $user = create_user();
        $artist = Artist::factory()->for($user)->createOne(['name' => 'Skid Row']);
        $album = Album::factory()
            ->for($artist)
            ->for($user)
            ->createOne([
                'name' => 'Slave to the Grind',
                'mbid' => 'release-mbid-from-tags',
            ]);

        self::assertInstanceOf(AlbumInformation::class, $this->service->getAlbumInformation($album));
    }

    #[Test]
    public function getAlbumInformationReturnsNullUponAnyErrorInThePipeline(): void
    {
        $this->mockPipelinePipe(
            GetReleaseAndReleaseGroupMbidsForAlbum::class,
            [
                'album' => 'Slave to the Grind',
                'artist' => 'Skid Row',
            ],
            ['sample-album-mbid', 'sample-release-group-mbid'],
        );

        $this->mockPipelinePipe(GetAlbumTracksUsingMbid::class, 'sample-album-mbid', new Exception('Oopsie'));

        $user = create_user();
        $album = Artist::factory()
            ->for($user)
            ->createOne(['name' => 'Skid Row'])
            ->albums() // @phpstan-ignore-line
            ->create([
                'name' => 'Slave to the Grind',
                'user_id' => $user->id,
            ]);

        self::assertNull($this->service->getAlbumInformation($album));
    }

    #[Test]
    public function searchRecordings(): void
    {
        config(['koel.services.musicbrainz.enabled' => true]);

        MockClient::global([
            SearchForRecordingRequest::class => MockResponse::make([
                'recordings' => [
                    [
                        'id' => 'rec-mbid',
                        'title' => 'Song',
                        'artist-credit' => [
                            ['name' => 'Artist A', 'joinphrase' => ' & ', 'artist' => ['id' => 'artist-mbid']],
                            ['name' => 'Artist B'],
                        ],
                        'releases' => [
                            [
                                'id' => 'release-mbid',
                                'title' => 'Album',
                                'date' => '1999-05-01',
                                'artist-credit' => [['name' => 'Various Artists', 'artist' => ['id' => 'va-mbid']]],
                                'release-group' => ['id' => 'rg-mbid'],
                                'media' => [['position' => 2, 'track' => [['number' => '7']]]],
                            ],
                        ],
                    ],
                    ['title' => 'No release'],
                ],
            ]),
        ]);

        $this->mockPipelinePipe(GetGenresUsingReleaseGroupMbid::class, 'rg-mbid', ['Rock', 'Blues']);

        self::assertSame(
            [
                [
                    'url' => 'https://musicbrainz.org/recording/rec-mbid',
                    'mbid' => 'rec-mbid',
                    'album_mbid' => 'release-mbid',
                    'artist_mbid' => 'artist-mbid',
                    'albumartist_mbid' => 'va-mbid',
                    'title' => 'Song',
                    'artist_name' => 'Artist A & Artist B',
                    'album_name' => 'Album',
                    'album_artist_name' => 'Various Artists',
                    'track' => 7,
                    'disc' => 2,
                    'year' => 1999,
                    'genre' => 'Rock, Blues',
                ],
            ],
            $this->service->searchRecordings('Song', 'Artist A'),
        );
    }

    #[Test]
    public function getRecordingCredits(): void
    {
        config(['koel.services.musicbrainz.enabled' => true]);

        MockClient::global([
            GetRecordingCreditsRequest::class => MockResponse::make([
                'relations' => [
                    ['type' => 'producer', 'attributes' => [], 'artist' => ['id' => 'a1', 'name' => 'Pat']],
                    ['type' => 'instrument', 'attributes' => ['guitar', 'bass'], 'artist' => ['name' => 'Gil']],
                    [
                        'type' => 'performance',
                        'work' => [
                            'relations' => [
                                ['type' => 'composer', 'attributes' => [], 'artist' => ['id' => 'a2', 'name' => 'Ada']],
                                ['type' => 'composer', 'attributes' => [], 'artist' => ['id' => 'a2', 'name' => 'Ada']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        self::assertSame(
            [
                ['role' => 'producer', 'name' => 'Pat', 'instrument' => null, 'artist_mbid' => 'a1'],
                ['role' => 'instrument', 'name' => 'Gil', 'instrument' => 'guitar, bass', 'artist_mbid' => null],
                ['role' => 'composer', 'name' => 'Ada', 'instrument' => null, 'artist_mbid' => 'a2'],
            ],
            $this->service->getRecordingCredits('11111111-1111-1111-1111-111111111111'),
        );
    }
}
