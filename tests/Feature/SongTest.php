<?php

namespace Tests\Feature;

use App\Facades\Dispatcher;
use App\Http\Resources\SongResource;
use App\Jobs\AnalyzeSongsJob;
use App\Jobs\DeleteSongFilesJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Song;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\create_admin;
use function Tests\create_user;
use function Tests\test_path;

class SongTest extends TestCase
{
    #[Test]
    public function index(): void
    {
        Song::factory()->createMany(2);

        $this->getAs('api/songs')->assertJsonStructure(SongResource::PAGINATION_JSON_STRUCTURE);
        $this->getAs('api/songs?sort=title&order=desc')->assertJsonStructure(SongResource::PAGINATION_JSON_STRUCTURE);
    }

    #[Test]
    public function includeTheFileSize(): void
    {
        $song = Song::factory()->createOne(['file_size' => 12_345_678]);

        $this->getAs("api/songs/{$song->id}", $song->owner)->assertJsonPath('file_size', 12_345_678);
    }

    #[Test]
    public function indexFilteredByCredit(): void
    {
        $composed = Song::factory()->create();
        $composed->credits()->create(['role' => 'composer', 'name' => 'Ada']);
        $produced = Song::factory()->create();
        $produced->credits()->create(['role' => 'producer', 'name' => 'Ada']);
        Song::factory()->create()->credits()->create(['role' => 'composer', 'name' => 'Bo']);

        $ids = static fn ($response) => collect($response->json('data'))->pluck('id')->sort()->values()->all();

        self::assertSame(
            collect([$composed, $produced])->pluck('id')->sort()->values()->all(),
            $ids($this->getAs('api/songs?cursor=&credit=Ada')),
        );
        self::assertSame([$composed->id], $ids($this->getAs('api/songs?cursor=&credit=Ada&credit_role=composer')));
    }

    #[Test]
    public function indexFilteredByBpmRange(): void
    {
        $slow = Song::factory()->create(['bpm' => 80]);
        $mid = Song::factory()->create(['bpm' => 120]);
        $fast = Song::factory()->create(['bpm' => 160]);
        Song::factory()->create(['bpm' => null]);

        $ids = static fn ($response) => collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $sorted = static fn (array $songs) => collect($songs)->pluck('id')->sort()->values()->all();

        self::assertSame($sorted([$mid, $fast]), $ids($this->getAs('api/songs?cursor=&bpm_min=100')));
        self::assertSame($sorted([$slow, $mid]), $ids($this->getAs('api/songs?cursor=&bpm_max=120')));
        self::assertSame([$mid->id], $ids($this->getAs('api/songs?cursor=&bpm_min=100&bpm_max=130')));
        self::assertSame(120, $this->getAs('api/songs?cursor=&bpm_min=100&bpm_max=130')->json('data.0.bpm'));
    }

    #[Test]
    public function analyzeSongs(): void
    {
        config([
            'koel.audio_analysis.python' => '/bin/sh',
            'koel.audio_analysis.script' => test_path('songs/full.mp3'),
        ]);

        $song = Song::factory()->create();
        Dispatcher::expects('dispatch')->with(AnalyzeSongsJob::class)->once();

        $this
            ->postAs('api/songs/analyze', ['songs' => [$song->id]], create_admin())
            ->assertAccepted()
            ->assertJson(['queued' => 1]);
    }

    #[Test]
    public function analyzeIsUnavailableWithoutThePythonEnvironment(): void
    {
        config(['koel.audio_analysis.python' => '/nonexistent/python']);

        $this->postAs('api/songs/analyze', ['songs' => [Song::factory()->create()->id]], create_admin())->assertStatus(
            503,
        );
    }

    #[Test]
    public function listCreditNames(): void
    {
        $song = Song::factory()->create();
        $song->credits()->createMany([
            ['role' => 'composer', 'name' => 'Bo'],
            ['role' => 'producer', 'name' => 'Ada'],
            ['role' => 'composer', 'name' => 'Ada'],
        ]);

        $this->getAs('api/song-credits/names')->assertJson(['names' => ['Ada', 'Bo']]);
        $this->getAs('api/song-credits/names?role=producer')->assertJson(['names' => ['Ada']]);
    }

    #[Test]
    public function indexFilteredByGenreAndFormat(): void
    {
        $rockFlac = Song::factory()->create(['path' => '/music/a.FLAC']);
        $rockFlac->syncGenres('Rock');
        $rockMp3 = Song::factory()->create(['path' => '/music/b.mp3']);
        $rockMp3->syncGenres('Rock');
        $jazzFlac = Song::factory()->create(['path' => '/music/c.flac']);
        $jazzFlac->syncGenres('Jazz');
        Song::factory()->create(['path' => '/music/d.mp3']);

        $ids = static fn ($response) => collect($response->json('data'))->pluck('id')->sort()->values()->all();

        self::assertSame(
            collect([$rockFlac, $rockMp3])->pluck('id')->sort()->values()->all(),
            $ids($this->getAs('api/songs?cursor=&genre=Rock')),
        );

        self::assertSame(
            collect([$rockFlac, $jazzFlac])->pluck('id')->sort()->values()->all(),
            $ids($this->getAs('api/songs?cursor=&formats[]=flac')),
        );

        self::assertCount(4, $this->getAs('api/songs?cursor=&formats[]=flac&formats[]=mp3')->json('data'));
        self::assertSame([$rockFlac->id], $ids($this->getAs('api/songs?cursor=&genre=Rock&formats[]=flac')));
    }

    #[Test]
    public function indexRejectsUnknownFormats(): void
    {
        $this->getAs('api/songs?cursor=&formats[]=wav')->assertUnprocessable();
    }

    #[Test]
    public function indexWithCursorReturnsCursorPagination(): void
    {
        Song::factory()->createMany(51);

        $response = $this->getAs(
            'api/songs?cursor=',
        )->assertJsonStructure(SongResource::CURSOR_PAGINATION_JSON_STRUCTURE);

        self::assertCount(50, $response->json('data'));
        self::assertNotNull($response->json('meta.next_cursor'));
        self::assertNull($response->json('meta.prev_cursor'));

        $secondPage = $this->getAs(
            'api/songs?cursor=' . $response->json('meta.next_cursor'),
        )->assertJsonStructure(SongResource::CURSOR_PAGINATION_JSON_STRUCTURE);

        self::assertCount(1, $secondPage->json('data'));
        self::assertNull($secondPage->json('meta.next_cursor'));
        self::assertNotNull($secondPage->json('meta.prev_cursor'));
    }

    #[Test]
    public function indexWithCursorTraversesAllSupportedSortsWithoutDuplicates(): void
    {
        $user = create_user();
        $songs = Song::factory()->createMany(60);

        foreach ($songs->take(20) as $i => $song) {
            Rating::factory()
                ->for($user)
                ->for($song, 'rateable')
                ->createOne(['rating' => ($i % 5) + 1]);
        }
        foreach ($songs->slice(20, 10) as $song) {
            Favorite::factory()->for($user)->for($song, 'favoriteable')->createOne();
        }

        foreach ([
            'title',
            'track',
            'length',
            'year',
            'created_at',
            'artist_name',
            'album_name',
            'rating',
            'favorite',
        ] as $sort) {
            $allIds = [];
            $cursor = '';
            $pages = 0;

            while ($cursor !== null && $pages < 4) {
                $pages++;
                $r = $this
                    ->getAs("api/songs?cursor={$cursor}&sort={$sort}&order=desc", $user)
                    ->assertOk()
                    ->assertJsonStructure(SongResource::CURSOR_PAGINATION_JSON_STRUCTURE);

                $allIds = array_merge($allIds, collect($r->json('data'))->pluck('id')->all());
                $cursor = $r->json('meta.next_cursor');
            }

            self::assertCount(60, $allIds, "sort={$sort} returned wrong total");
            self::assertCount(60, array_unique($allIds), "sort={$sort} returned duplicates");
        }
    }

    #[Test]
    public function indexSortedByFavoriteScopesToCurrentUser(): void
    {
        $user = create_user();
        $other = create_user();

        Song::factory()->createOne(['title' => 'Unfavorited']);
        $mine = Song::factory()->createOne(['title' => 'Mine']);
        $theirs = Song::factory()->createOne(['title' => 'Theirs']);

        Favorite::factory()->for($user)->for($mine, 'favoriteable')->createOne();
        Favorite::factory()->for($other)->for($theirs, 'favoriteable')->createOne();

        $descIds = $this->getAs('api/songs?sort=favorite&order=desc', $user)->json('data.*.id');

        // current user's favorited song comes first; other user's favorite is invisible to this sort
        self::assertSame($mine->id, $descIds[0]);
    }

    #[Test]
    public function indexSortedByRatingScopesToCurrentUser(): void
    {
        $user = create_user();
        $other = create_user();

        $low = Song::factory()->createOne(['title' => 'Low']);
        $high = Song::factory()->createOne(['title' => 'High']);
        $unrated = Song::factory()->createOne(['title' => 'Unrated']);

        Rating::factory()->for($user)->for($low, 'rateable')->createOne(['rating' => 2]);
        Rating::factory()->for($user)->for($high, 'rateable')->createOne(['rating' => 5]);
        Rating::factory()->for($other)->for($unrated, 'rateable')->createOne(['rating' => 5]);

        $descIds = $this->getAs('api/songs?sort=rating&order=desc', $user)->json('data.*.id');

        self::assertSame($high->id, $descIds[0]);
        self::assertSame($low->id, $descIds[1]);
        self::assertSame($unrated->id, $descIds[2]);

        $ascIds = $this->getAs('api/songs?sort=rating&order=asc', $user)->json('data.*.id');

        self::assertSame($unrated->id, $ascIds[0]);
        self::assertSame($low->id, $ascIds[1]);
        self::assertSame($high->id, $ascIds[2]);
    }

    #[Test]
    public function show(): void
    {
        $song = Song::factory()->createOne();

        $this->getAs("api/songs/{$song->id}")->assertJsonStructure(SongResource::JSON_STRUCTURE);
    }

    #[Test]
    public function showIncludesTheMusicBrainzIdentifier(): void
    {
        $song = Song::factory()->createOne(['mbid' => '4d1f7b6e-1d6e-4b3f-9a0d-8c2f1a5b6c7d']);

        $this->getAs("api/songs/{$song->id}")->assertJsonPath('mbid', '4d1f7b6e-1d6e-4b3f-9a0d-8c2f1a5b6c7d');
    }

    #[Test]
    public function showOmitsTheMusicBrainzIdentifierWhenMusicBrainzIsDisabled(): void
    {
        config(['koel.services.musicbrainz.enabled' => false]);
        $song = Song::factory()->createOne(['mbid' => '4d1f7b6e-1d6e-4b3f-9a0d-8c2f1a5b6c7d']);

        $this->getAs("api/songs/{$song->id}")->assertJsonMissingPath('mbid');
    }

    #[Test]
    public function destroy(): void
    {
        Bus::fake();
        Dispatcher::expects('dispatch')->with(DeleteSongFilesJob::class);

        $songs = Song::factory()->createMany(2);

        $this->deleteAs('api/songs', ['songs' => $songs->modelKeys()], create_admin())->assertNoContent();

        $songs->each($this->assertModelMissing(...));
    }

    #[Test]
    public function unauthorizedDelete(): void
    {
        Bus::fake();
        $songs = Song::factory()->createMany(2);

        Dispatcher::expects('dispatch')->never();

        $this->deleteAs('api/songs', ['songs' => $songs->modelKeys()])->assertForbidden();

        $songs->each($this->assertModelExists(...));
    }

    #[Test]
    public function singleUpdateAllInfoNoCompilation(): void
    {
        $song = Song::factory()->createOne();

        $this->putAs(
            '/api/songs',
            [
                'songs' => [$song->id],
                'data' => [
                    'title' => 'Foo Bar',
                    'artist_name' => 'John Cena',
                    'album_name' => 'One by One',
                    'lyrics' => 'Lorem ipsum dolor sic amet.',
                    'track' => 1,
                    'disc' => 2,
                ],
            ],
            create_admin(),
        )->assertOk();

        /** @var Artist|null $artist */
        $artist = Artist::query()->where('name', 'John Cena')->first();
        self::assertNotNull($artist);

        /** @var Album|null $album */
        $album = Album::query()->where('name', 'One by One')->first();
        self::assertNotNull($album);

        $this->assertDatabaseHas(Song::class, [
            'id' => $song->id,
            'album_id' => $album->id,
            'lyrics' => 'Lorem ipsum dolor sic amet.',
            'track' => 1,
            'disc' => 2,
        ]);
    }

    #[Test]
    public function singleUpdateSomeInfoNoCompilation(): void
    {
        $song = Song::factory()->createOne();

        $originalArtistId = $song->artist->id;

        $this->putAs(
            '/api/songs',
            [
                'songs' => [$song->id],
                'data' => [
                    'title' => '',
                    'artist_name' => '',
                    'album_name' => 'One by One',
                    'lyrics' => 'Lorem ipsum dolor sic amet.',
                    'track' => 1,
                ],
            ],
            create_admin(),
        )->assertOk();

        // We don't expect the song's artist to change
        self::assertSame($originalArtistId, $song->refresh()->artist->id);

        // But we expect a new album to be created for this artist and contain this song
        self::assertSame('One by One', $song->album->name);
    }

    #[Test]
    public function multipleUpdateNoCompilation(): void
    {
        $songIds = Song::factory()->createMany(2)->modelKeys();

        $this->putAs(
            '/api/songs',
            [
                'songs' => $songIds,
                'data' => [
                    'title' => null,
                    'artist_name' => 'John Cena',
                    'album_name' => 'One by One',
                    'lyrics' => null,
                    'track' => 9999,
                ],
            ],
            create_admin(),
        )->assertOk();

        /** @var Collection<array-key, Song> $songs */
        $songs = Song::query()->whereIn('id', $songIds)->get();

        // All of these songs must now belong to a new album and artist set
        self::assertSame('One by One', $songs[0]->album->name);
        self::assertSame($songs[0]->album_id, $songs[1]->album_id);

        self::assertSame('John Cena', $songs[0]->artist->name);
        self::assertSame($songs[0]->artist_id, $songs[1]->artist_id);

        // Since the lyrics and title were not set, they should be left unchanged
        self::assertNotSame($songs[0]->title, $songs[1]->title);
        self::assertNotSame($songs[0]->lyrics, $songs[1]->lyrics);

        self::assertSame(9999, $songs[0]->track);
        self::assertSame(9999, $songs[1]->track);
    }

    #[Test]
    public function multipleUpdateWithOnlyGenreKeepsOtherFields(): void
    {
        $songs = Song::factory(2)->create(['disc' => 2, 'year' => 2001, 'track' => 7, 'lyrics' => 'La la']);

        $this->putAs(
            'api/songs',
            [
                'songs' => $songs->pluck('id')->all(),
                'data' => ['genre' => 'Rock'],
            ],
            create_admin(),
        )->assertOk();

        $songs->each(static function (Song $song): void {
            $song->refresh();

            self::assertSame('Rock', $song->genre);
            self::assertSame(2, $song->disc);
            self::assertSame(2001, $song->year);
            self::assertSame(7, $song->track);
            self::assertSame('La la', $song->lyrics);
        });
    }

    #[Test]
    public function multipleUpdateCreatingNewAlbumsAndArtists(): void
    {
        $originalSongs = Song::factory()->createMany(2);
        $originalSongIds = $originalSongs->modelKeys();
        $originalAlbumNames = $originalSongs->pluck('album.name')->all();
        $originalAlbumIds = $originalSongs->pluck('album_id')->all();

        $this->putAs(
            '/api/songs',
            [
                'songs' => $originalSongIds,
                'data' => [
                    'title' => 'Foo Bar',
                    'artist_name' => 'John Cena',
                    'album_name' => '',
                    'lyrics' => 'Lorem ipsum dolor sic amet.',
                    'track' => 1,
                ],
            ],
            create_admin(),
        )->assertOk();

        $songs = Song::query()->whereIn('id', $originalSongIds)->get()->orderByArray($originalSongIds);

        // Even though the album name doesn't change, a new artist should have been created
        // and thus, a new album with the same name was created as well.
        collect([0, 1])->each(static function (int $i) use ($songs, $originalAlbumNames, $originalAlbumIds): void {
            self::assertSame($songs[$i]->album->name, $originalAlbumNames[$i]);
            self::assertNotSame($songs[$i]->album_id, $originalAlbumIds[$i]);
        });

        // And of course, the new artist is...
        self::assertSame('John Cena', $songs[0]->artist->name); // JOHN CENA!!!
        self::assertSame('John Cena', $songs[1]->artist->name); // And... JOHN CENAAAAAAAAAAA!!!
    }

    #[Test]
    public function singleUpdateAllInfoWithCompilation(): void
    {
        $song = Song::factory()->createOne();

        $this->putAs(
            '/api/songs',
            [
                'songs' => [$song->id],
                'data' => [
                    'title' => 'Foo Bar',
                    'artist_name' => 'John Cena',
                    'album_name' => 'One by One',
                    'album_artist_name' => 'John Lennon',
                    'lyrics' => 'Lorem ipsum dolor sic amet.',
                    'track' => 1,
                    'disc' => 2,
                ],
            ],
            create_admin(),
        )->assertOk();

        /** @var Album $album */
        $album = Album::query()->where('name', 'One by One')->first();

        /** @var Artist $albumArtist */
        $albumArtist = Artist::query()->where('name', 'John Lennon')->first();

        /** @var Artist $artist */
        $artist = Artist::query()->where('name', 'John Cena')->first();

        $this->assertDatabaseHas(Song::class, [
            'id' => $song->id,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'lyrics' => 'Lorem ipsum dolor sic amet.',
            'track' => 1,
            'disc' => 2,
        ]);

        self::assertTrue($album->artist->is($albumArtist));
    }

    #[Test]
    public function updateSingleSongWithEmptyTrackAndDisc(): void
    {
        $song = Song::factory()->createOne([
            'track' => 12,
            'disc' => 2,
        ]);

        $this->putAs(
            '/api/songs',
            [
                'songs' => [$song->id],
                'data' => [
                    'track' => null,
                    'disc' => null,
                ],
            ],
            create_admin(),
        )->assertOk();

        $song->refresh();

        self::assertSame(0, $song->track);
        self::assertSame(1, $song->disc);
    }
}
