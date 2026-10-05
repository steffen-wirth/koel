<?php

namespace Tests\Integration\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Services\Integrations\BandcampService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BandcampServiceTest extends TestCase
{
    #[Test]
    public function doNothingWithoutGoogleCredentials(): void
    {
        config(['koel.services.google_search.key' => null]);
        Http::fake();

        self::assertSame([], app(BandcampService::class)->getAlbumGenres(Album::factory()->create()));
        Http::assertNothingSent();
    }

    #[Test]
    public function readTagsFromTheAlbumPageFoundThroughGoogle(): void
    {
        config(['koel.services.google_search.key' => 'secret', 'koel.services.google_search.engine_id' => 'cx']);

        $album = Album::factory()->for(Artist::factory()->create(['name' => 'Jstar']))->create([
            'name' => 'Most Wanted',
        ]);

        Http::fake([
            '*googleapis.com/customsearch/*' => Http::response([
                'items' => [
                    ['link' => 'https://jstar.bandcamp.com/track/other', 'title' => 'Other | Jstar'],
                    ['link' => 'https://jstar.bandcamp.com/album/most-wanted', 'title' => 'Most Wanted | Jstar'],
                ],
            ]),
            'https://jstar.bandcamp.com/album/most-wanted' => Http::response(
                '<script>{"keywords":["Dancehall Reggae","Reggae","digital","Berlin"]}</script>',
            ),
        ]);

        self::assertSame(['Dancehall Reggae', 'Reggae', 'Berlin'], app(BandcampService::class)->getAlbumGenres($album));
    }

    #[Test]
    public function stopSearchingAfter100QueriesPerDay(): void
    {
        config(['koel.services.google_search.key' => 'secret', 'koel.services.google_search.engine_id' => 'cx']);
        Cache::put('google-search-count:' . Carbon::now('America/Los_Angeles')->toDateString(), 100, now()->addDay());
        Http::fake();

        self::assertSame([], app(BandcampService::class)->getAlbumGenres(Album::factory()->create()));
        Http::assertNothingSent();
    }
}
