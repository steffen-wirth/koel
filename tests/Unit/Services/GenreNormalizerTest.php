<?php

namespace Tests\Unit\Services;

use App\Models\Genre;
use App\Services\GenreNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenreNormalizerTest extends TestCase
{
    #[Test]
    public function unifyAliasesAndReuseLibrarySpelling(): void
    {
        Genre::factory()->create(['name' => 'Deep House']);

        self::assertSame(
            ['Hip Hop', 'R&B', 'Drum & Bass', 'Deep House', 'Dub', 'Jazz-Funk', 'Nu Disco', 'IDM'],
            app(GenreNormalizer::class)->normalize([
                'hip-hop/rap',
                'hiphop',
                'rnb',
                'drum and bass',
                'dnb',
                'deephouse',
                'dub',
                'jazz-funk',
                'nudisco',
                'idm',
                'Nu Disco',
            ]),
        );
    }
}
