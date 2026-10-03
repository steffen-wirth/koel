<?php

namespace Tests\Feature\Commands;

use App\Models\Album;
use App\Models\Song;
use App\Services\Integrations\MusicBrainzService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchGenresCommandTest extends TestCase
{
    #[Test]
    public function failWhenMusicBrainzIsDisabled(): void
    {
        config(['koel.services.musicbrainz.enabled' => false]);

        $this->artisan('koel:fetch-genres')->assertFailed();
    }

    #[Test]
    public function addGenresOnlyToAlbumsWithUntaggedSongs(): void
    {
        config(['koel.services.musicbrainz.enabled' => true]);

        $untagged = Album::factory()->create();
        $untaggedSong = Song::factory()->for($untagged)->create();
        $taggedSong = Song::factory()->for($untagged)->create();
        $taggedSong->syncGenres('Blues');

        $tagged = Album::factory()->create();
        Song::factory()->for($tagged)->create()->syncGenres('Jazz');

        $this->mock(MusicBrainzService::class)->expects('getAlbumGenres')->once()->andReturn(['Rock']);

        $this->artisan('koel:fetch-genres')->assertSuccessful();

        self::assertSame('Rock', $untaggedSong->refresh()->genre);
        self::assertSame('Blues, Rock', $taggedSong->refresh()->genre);
    }

    #[Test]
    public function dryRunChangesNothing(): void
    {
        config(['koel.services.musicbrainz.enabled' => true]);

        $song = Song::factory()->create();

        $this->mock(MusicBrainzService::class)->expects('getAlbumGenres')->once()->andReturn(['Rock']);

        $this->artisan('koel:fetch-genres --dry-run')->assertSuccessful();

        self::assertSame('', (string) $song->refresh()->genre);
    }
}
