<?php

namespace Tests\Integration\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\Integrations\LeGrooveService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeGrooveServiceTest extends TestCase
{
    #[Test]
    public function collectGenresFromTagsOfPostsMatchingTheAlbum(): void
    {
        $album = Album::factory()->for(Artist::factory()->create(['name' => 'Resin Dogs']))->create();
        Song::factory()->for($album)->create(['title' => 'Still the Beats']);

        Http::fake([
            '*/posts*' => Http::response([
                ['title' => ['rendered' => 'Resin Dogs &#8211; Still the Beats'], 'tags' => [1, 2, 3, 4]],
                ['title' => ['rendered' => 'Somebody Else &#8211; Still the Beats'], 'tags' => [5]],
            ]),
            '*/tags*' => Http::response([
                ['id' => 1, 'name' => 'funk', 'count' => 10],
                ['id' => 2, 'name' => 'Brisbane', 'count' => 5],
                ['id' => 3, 'name' => 'hip-hop/rap', 'count' => 20],
                ['id' => 4, 'name' => 'remix', 'count' => 30],
            ]),
        ]);

        self::assertSame(['hip-hop/rap', 'funk'], app(LeGrooveService::class)->getAlbumGenres($album));
    }

    #[Test]
    public function returnNothingWhenTheRequestFails(): void
    {
        $album = Album::factory()->create();
        Song::factory()->for($album)->create();

        Http::fake(['*' => Http::response('', 500)]);

        self::assertSame([], app(LeGrooveService::class)->getAlbumGenres($album));
    }
}
