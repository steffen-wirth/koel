<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Song;
use App\Services\Integrations\MusicBrainzService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\create_admin;
use function Tests\create_user;

class AlbumGenreTest extends TestCase
{
    #[Test]
    public function suggestGenres(): void
    {
        $this->mock(MusicBrainzService::class)->expects('getAlbumGenres')->andReturn(['Rock', 'Blues']);

        $this->getAs(
            'api/albums/' . Album::factory()->create()->id . '/genre-suggestions',
            create_admin(),
        )->assertJson(['genres' => ['Rock', 'Blues']]);
    }

    #[Test]
    public function nonEditorCannotGetSuggestions(): void
    {
        $this->getAs(
            'api/albums/' . Album::factory()->create()->id . '/genre-suggestions',
            create_user(),
        )->assertForbidden();
    }

    #[Test]
    public function addGenresKeepingTheExistingOnes(): void
    {
        $album = Album::factory()->create();
        $withGenre = Song::factory()->for($album)->create();
        $withGenre->syncGenres('Blues');
        $withSameGenre = Song::factory()->for($album)->create();
        $withSameGenre->syncGenres('rock');
        $withoutGenre = Song::factory()->for($album)->create();
        $otherAlbumSong = Song::factory()->create();

        $response = $this->postAs(
            "api/albums/{$album->id}/genres",
            ['genres' => ['Rock', 'Metal']],
            create_admin(),
        )->assertOk();

        self::assertSame('Blues, Metal, Rock', $withGenre->refresh()->genre);
        self::assertSame('Metal, rock', $withSameGenre->refresh()->genre);
        self::assertSame('Metal, Rock', $withoutGenre->refresh()->genre);
        self::assertSame('', (string) $otherAlbumSong->refresh()->genre);

        self::assertCount(3, $response->json('songs'));
        self::assertStringContainsString('Metal', $response->json('albums.0.genre'));
    }

    #[Test]
    public function addGenresRequiresGenres(): void
    {
        $this->postAs(
            'api/albums/' . Album::factory()->create()->id . '/genres',
            ['genres' => []],
            create_admin(),
        )->assertUnprocessable();
    }

    #[Test]
    public function nonEditorCannotAddGenres(): void
    {
        $album = Album::factory()->create();

        $this->postAs("api/albums/{$album->id}/genres", ['genres' => ['Rock']], create_user())->assertForbidden();
    }
}
