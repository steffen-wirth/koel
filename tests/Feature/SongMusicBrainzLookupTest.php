<?php

namespace Tests\Feature;

use App\Services\Integrations\MusicBrainzService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\create_user;

class SongMusicBrainzLookupTest extends TestCase
{
    #[Test]
    public function lookUpMatches(): void
    {
        $match = ['title' => 'Song', 'artist_name' => 'Artist', 'genre' => 'Rock'];

        $this
            ->mock(MusicBrainzService::class)
            ->expects('searchRecordings')
            ->with('Song', 'Artist', null)
            ->andReturn([$match]);

        $this->getAs('api/musicbrainz/songs?title=Song&artist=Artist', create_user())->assertJson([
            'matches' => [$match],
        ]);
    }

    #[Test]
    public function titleIsRequired(): void
    {
        $this->getAs('api/musicbrainz/songs', create_user())->assertUnprocessable();
    }

    #[Test]
    public function getRecordingCredits(): void
    {
        $credits = [['role' => 'composer', 'name' => 'Ada', 'instrument' => null, 'artist_mbid' => null]];
        $mbid = '11111111-1111-1111-1111-111111111111';

        $this->mock(MusicBrainzService::class)->expects('getRecordingCredits')->with($mbid)->andReturn($credits);

        $this->getAs("api/musicbrainz/recordings/$mbid/credits", create_user())->assertJson(['credits' => $credits]);
    }
}
