<?php

namespace App\Services\Integrations;

use App\Http\Integrations\MusicBrainz\MusicBrainzConnector;
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
use App\Services\Contracts\Encyclopedia;
use App\Values\Album\AlbumInformation;
use App\Values\Artist\ArtistInformation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Pipeline;

class MusicBrainzService implements Encyclopedia
{
    public function __construct(
        private readonly MusicBrainzConnector $connector,
    ) {}

    public static function enabled(): bool
    {
        return config('koel.services.musicbrainz.enabled');
    }

    public static function userAgent(): string
    {
        return config('koel.services.musicbrainz.user_agent') ?: koel_user_agent();
    }

    public function getArtistInformation(Artist $artist): ?ArtistInformation
    {
        if ($artist->is_unknown || $artist->is_various) {
            return null;
        }

        return rescue_if(static::enabled(), static function () use ($artist) {
            /** @var string|null $mbid */
            $mbid = $artist->mbid ?: Pipeline::send($artist->name)->through([GetMbidForArtist::class])->thenReturn();

            $wikipediaSummary = Pipeline::send($mbid)
                ->through([
                    GetArtistWikidataIdUsingMbid::class,
                    GetWikipediaPageTitleUsingWikidataId::class,
                    GetWikipediaPageSummaryUsingPageTitle::class,
                ])
                ->thenReturn();

            return $wikipediaSummary ? ArtistInformation::fromWikipediaSummary($wikipediaSummary) : null;
        });
    }

    /** @return array{0: ?string, 1: ?string} The release and release group identifiers */
    private static function resolveReleaseMbids(Album $album): array
    {
        if ($album->mbid) {
            /** @var string|null $releaseGroupMbid */
            $releaseGroupMbid = Pipeline::send($album->mbid)
                ->through([GetReleaseGroupMbidUsingReleaseMbid::class])
                ->thenReturn();

            return [$album->mbid, $releaseGroupMbid];
        }

        return Pipeline::send([
            'album' => $album->name,
            'artist' => $album->artist->name,
        ])->through([GetReleaseAndReleaseGroupMbidsForAlbum::class])->thenReturn();
    }

    public function getAlbumInformation(Album $album): ?AlbumInformation
    {
        if ($album->is_unknown || $album->artist->is_unknown) {
            return null;
        }

        return rescue_if(static::enabled(), static function () use ($album): ?AlbumInformation {
            // MusicBrainz has the concept of a "release" and a "release group".
            // A release is a specific version of an album, which contains the actual tracks.
            // A release group is a collection of releases (e.g. different formats or editions or markets
            // of the same album), which contains metadata like the Wikidata relationship.
            [$albumMbid, $releaseGroupMbid] = self::resolveReleaseMbids($album);

            if (!$albumMbid || !$releaseGroupMbid) {
                return null;
            }

            /** @var array<mixed> $tracks */
            $tracks = Pipeline::send($albumMbid)->through([GetAlbumTracksUsingMbid::class])->thenReturn() ?: [];

            $wikipediaSummary = Pipeline::send($releaseGroupMbid)
                ->through([
                    GetAlbumWikidataIdUsingReleaseGroupMbid::class,
                    GetWikipediaPageTitleUsingWikidataId::class,
                    GetWikipediaPageSummaryUsingPageTitle::class,
                ])
                ->thenReturn();

            return $wikipediaSummary
                ? AlbumInformation::fromWikipediaSummary($wikipediaSummary)->withMusicBrainzTracks($tracks)
                : AlbumInformation::make(url: "https://musicbrainz.org/release/$albumMbid")->withMusicBrainzTracks(
                    $tracks,
                );
        });
    }

    /**
     * The most voted MusicBrainz genres of an album's release group.
     *
     * @return list<string>
     */
    public function getAlbumGenres(Album $album): array
    {
        if ($album->is_unknown || $album->artist->is_unknown) {
            return [];
        }

        return (
            rescue_if(
                static::enabled(),
                static function () use ($album): array {
                    [, $releaseGroupMbid] = self::resolveReleaseMbids($album);

                    return Pipeline::send($releaseGroupMbid)
                        ->through([GetGenresUsingReleaseGroupMbid::class])
                        ->thenReturn();
                },
                [],
            ) ?? []
        );
    }

    /**
     * Look up recordings and map each (recording, release) pair to song metadata.
     *
     * @return list<array<string, mixed>>
     */
    public function searchRecordings(string $title, ?string $artistName = null, ?string $albumName = null): array
    {
        return (
            rescue_if(
                static::enabled(),
                function () use ($title, $artistName, $albumName): array {
                    $recordings = $this->connector->send(new SearchForRecordingRequest(
                        $title,
                        $artistName,
                        $albumName,
                    ))->json('recordings', []);

                    $matches = [];

                    foreach ($recordings as $recording) {
                        $release = Arr::get($recording, 'releases.0');

                        if (!$release) {
                            continue;
                        }

                        $track = Arr::get($release, 'media.0.track.0.number');

                        $matches[] = [
                            'url' => 'https://musicbrainz.org/recording/' . Arr::get($recording, 'id'),
                            'mbid' => Arr::get($recording, 'id'),
                            'album_mbid' => Arr::get($release, 'id'),
                            'artist_mbid' => Arr::get($recording, 'artist-credit.0.artist.id'),
                            'albumartist_mbid' => Arr::get($release, 'artist-credit.0.artist.id'),
                            'title' => Arr::get($recording, 'title'),
                            'artist_name' => self::joinCredits(Arr::get($recording, 'artist-credit', [])),
                            'album_name' => Arr::get($release, 'title'),
                            'album_artist_name' => self::joinCredits(Arr::get($release, 'artist-credit', [])),
                            'track' => is_numeric($track) ? (int) $track : null,
                            'disc' => Arr::get($release, 'media.0.position'),
                            'year' => (int) substr((string) Arr::get($release, 'date', ''), 0, 4) ?: null,
                            'genre' => implode(
                                ', ',
                                Pipeline::send(Arr::get($release, 'release-group.id'))
                                    ->through([GetGenresUsingReleaseGroupMbid::class])
                                    ->thenReturn(),
                            ),
                        ];
                    }

                    return $matches;
                },
                [],
            ) ?? []
        );
    }

    /**
     * The people credited on a recording: the recording's own relationships (producer, performers with their
     * instruments, …) and those of the works it performs (composer, lyricist, …).
     *
     * @return list<array{role: string, name: string, instrument: ?string, artist_mbid: ?string}>
     */
    public function getRecordingCredits(string $mbid): array
    {
        return (
            rescue_if(
                static::enabled(),
                function () use ($mbid): array {
                    return Cache::remember(cache_key('recording credits', $mbid), now()->addWeek(), function () use (
                        $mbid,
                    ): array {
                        $recording = $this->connector->send(new GetRecordingCreditsRequest($mbid))->json();

                        $relations = collect(Arr::get($recording, 'relations', []));

                        // The composer and lyricist are related to the work, not to the recording itself.
                        $workRelations = $relations->flatMap(static fn (array $relation) => Arr::get(
                            $relation,
                            'work.relations',
                            [],
                        ));

                        return $relations
                            ->merge($workRelations)
                            ->filter(static fn (array $relation) => Arr::has($relation, 'artist.name'))
                            ->map(static fn (array $relation) => [
                                'role' => (string) $relation['type'],
                                'name' => $relation['artist']['name'],
                                'instrument' => implode(', ', Arr::get($relation, 'attributes', [])) ?: null,
                                'artist_mbid' => Arr::get($relation, 'artist.id'),
                            ])
                            ->unique(static fn (array $credit) => implode('|', $credit))
                            ->values()
                            ->all();
                    });
                },
                [],
            ) ?? []
        );
    }

    private static function joinCredits(array $credits): string
    {
        return collect($credits)->map(
            static fn (array $credit) => Arr::get($credit, 'name', '') . Arr::get($credit, 'joinphrase', ''),
        )->implode('');
    }
}
