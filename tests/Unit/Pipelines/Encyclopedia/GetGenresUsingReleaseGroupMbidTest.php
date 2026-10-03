<?php

namespace Tests\Unit\Pipelines\Encyclopedia;

use App\Http\Integrations\MusicBrainz\MusicBrainzConnector;
use App\Http\Integrations\MusicBrainz\Requests\GetReleaseGroupGenresRequest;
use App\Pipelines\Encyclopedia\GetGenresUsingReleaseGroupMbid;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Concerns\TestsPipelines;
use Tests\TestCase;

use function Tests\test_path;

class GetGenresUsingReleaseGroupMbidTest extends TestCase
{
    use TestsPipelines;

    #[Test]
    public function getTheMostVotedGenres(): void
    {
        Saloon::fake([
            GetReleaseGroupGenresRequest::class => MockResponse::make(body: File::json(test_path(
                'fixtures/musicbrainz/release-group-genres.json',
            ))),
        ]);

        $mock = self::createNextClosureMock(['Heavy Metal', 'Rock', 'Hard Rock']);

        (new GetGenresUsingReleaseGroupMbid(new MusicBrainzConnector()))('sample-mbid', $mock->next(...)); // @phpstan-ignore-line

        Saloon::assertSent(static function (GetReleaseGroupGenresRequest $request): bool {
            self::assertSame(['inc' => 'genres'], $request->query()->all());

            return true;
        });

        self::assertSame(
            ['Heavy Metal', 'Rock', 'Hard Rock'],
            Cache::get(cache_key('genres from release group mbid', 'sample-mbid')),
        );
    }

    #[Test]
    public function passOnAnEmptyListIfNothingIsFound(): void
    {
        Saloon::fake([GetReleaseGroupGenresRequest::class => MockResponse::make(body: ['genres' => []])]);

        $mock = self::createNextClosureMock([]);

        (new GetGenresUsingReleaseGroupMbid(new MusicBrainzConnector()))('sample-mbid', $mock->next(...)); // @phpstan-ignore-line
    }

    #[Test]
    public function justPassOnAnEmptyListIfMbidIsNull(): void
    {
        Saloon::fake([]);

        $mock = self::createNextClosureMock([]);

        (new GetGenresUsingReleaseGroupMbid(new MusicBrainzConnector()))(null, $mock->next(...)); // @phpstan-ignore-line

        Saloon::assertNothingSent();
    }
}
