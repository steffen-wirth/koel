<?php

namespace App\Pipelines\Encyclopedia;

use App\Http\Integrations\MusicBrainz\MusicBrainzConnector;
use App\Http\Integrations\MusicBrainz\Requests\GetReleaseGroupGenresRequest;
use Closure;
use Illuminate\Support\Arr;

class GetGenresUsingReleaseGroupMbid
{
    use TriesRemember;

    private const int MAX_GENRES = 3;

    public function __construct(
        private readonly MusicBrainzConnector $connector,
    ) {}

    public function __invoke(?string $mbid, Closure $next): mixed
    {
        if (!$mbid) {
            return $next([]);
        }

        $genres = self::tryRememberForever(
            key: cache_key('genres from release group mbid', $mbid),
            nothingFoundTtl: now()->addWeek(),
            callback: function () use ($mbid): ?array {
                // The most voted genres first.
                $genres = collect($this->connector->send(new GetReleaseGroupGenresRequest($mbid))->json('genres', []))
                    ->sortByDesc(static fn (array $genre) => Arr::get($genre, 'count', 0))
                    ->take(self::MAX_GENRES)
                    ->map(static fn (array $genre) => mb_convert_case(
                        trim((string) Arr::get($genre, 'name')),
                        MB_CASE_TITLE,
                    ))
                    ->filter()
                    ->values()
                    ->all();

                return $genres ?: null;
            },
        );

        return $next($genres ?? []);
    }
}
